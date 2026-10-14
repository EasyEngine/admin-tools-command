<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use EE\Migration\SiteContainers;
use EE\Model\Site;
use EE\RevertableStepProcessor;
use Symfony\Component\Filesystem\Filesystem;

class DropEeDbMountFromSites extends Base {

	/** @var RevertableStepProcessor $rsp Keeps track of migration state. Reverts on error */
	private static $rsp;

	/** @var string $admin_tools_index Installed admin-tools index. */
	private $admin_tools_index;

	/** @var array $sites Sites with admin-tools enabled. */
	private $sites;

	/** @var bool $query_warned Whether the failed container query was already reported. */
	private static $query_warned = false;

	public function __construct() {

		parent::__construct();
		$this->admin_tools_index   = EE_ROOT_DIR . '/admin-tools/index.php';
		$this->sites               = Site::where( 'admin_tools', 1 );
		$this->skip_this_migration = $this->is_first_execution || ( empty( $this->sites ) && ! $this->fs->exists( $this->admin_tools_index ) );
	}

	/**
	 * Stop mounting the EE database into admin-tools sites; the index reads a per-site flags file instead.
	 *
	 * @throws EE\ExitException
	 */
	public function up() {

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping drop-ee-db-mount-from-sites migration as admin-tools are not in use.' );

			return;
		}
		self::$rsp = new RevertableStepProcessor();

		EE::debug( 'Starting drop-ee-db-mount-from-sites' );

		// The index goes first: the one it replaces fails once the database is no longer mounted.
		// Skipped when already current (e.g. a re-run after core reverted this migration), so the backup keeps the old index.
		$new_index = \Admin_Tools_Command::render_index();
		if ( $this->fs->exists( $this->admin_tools_index ) && file_get_contents( $this->admin_tools_index ) !== $new_index ) {
			$backup_index   = EE_BACKUP_DIR . '/admin-tools/index-with-db.php';
			$new_index_file = EE_BACKUP_DIR . '/admin-tools/index-without-db.php';
			$this->fs->dumpFile( $new_index_file, $new_index );

			self::$rsp->add_step(
				'take-admin-tools-index-backup',
				'EE\Migration\SiteContainers::backup_restore',
				'EE\Migration\SiteContainers::backup_restore',
				[ $this->admin_tools_index, $backup_index ],
				[ $backup_index, $this->admin_tools_index ]
			);

			self::$rsp->add_step(
				'regenerate-admin-tools-index-without-db',
				'EE\Migration\SiteContainers::backup_restore',
				'EE\Migration\SiteContainers::backup_restore',
				[ $new_index_file, $this->admin_tools_index ],
				[ $backup_index, $this->admin_tools_index ]
			);
		}

		// Fatal on purpose: dropping the mounts under the old index breaks it on every site, and core reverts and re-runs this migration next time.
		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable to run drop-ee-db-mount-from-sites migration.' );
		}

		// Decided from the containers, not the compose file: an interrupted earlier run leaves the file current and the mount in place.
		self::$query_warned = false;
		$mounted            = self::containers_mounting_db();

		foreach ( $this->sites as $site ) {
			self::migrate_site( $site, $mounted );
		}

		foreach ( Site::where( 'admin_tools', 0 ) as $site ) {
			self::drop_lingering_mount( $site, $mounted );
		}

		self::warn_remaining_mounts();
	}

	/**
	 * Nothing to undo: the new index and compose files also work with an older ee, and a re-run skips what is already migrated.
	 *
	 * @throws EE\ExitException
	 */
	public function down() {
	}

	/**
	 * Writes the site's flags file and recreates its php and nginx without the database mount.
	 * A failure only warns, so one broken site doesn't block the upgrade of the others.
	 *
	 * @param Site       $site    Site with admin-tools enabled.
	 * @param array|null $mounted Result of containers_mounting_db().
	 */
	public static function migrate_site( $site, $mounted = null ) {

		$fs            = new Filesystem();
		$admin_compose = $site->site_fs_path . '/docker-compose-admin.yml';

		if ( ! is_dir( $site->site_fs_path ) ) {
			EE::debug( "Skipping $site->site_url: $site->site_fs_path does not exist." );

			return;
		}

		$old_compose = null;
		$written     = false;
		try {
			\Admin_Tools_Command::write_site_data( $site->site_fs_path, $site->mailhog_enabled, $site->cache_nginx_fullpage );

			// Without it the site doesn't use admin-tools compose (e.g. custom-compose); `admin-tools enable` writes a fresh one.
			if ( ! $fs->exists( $admin_compose ) ) {
				return;
			}

			$old_compose = file_get_contents( $admin_compose );
			if ( false === $old_compose ) {
				throw new \Exception( "Could not read $admin_compose." );
			}
			$new_compose = \Admin_Tools_Command::render_admin_compose( $site->site_fs_path, $site->cache_host, $site->db_host );
			if ( $old_compose !== $new_compose ) {
				$fs->copy( $admin_compose, EE_BACKUP_DIR . '/' . $site->site_url . '/docker-compose-admin.yml.with-db', true );
				$written = true;
				$fs->dumpFile( $admin_compose, $new_compose );
			}

			// A disabled site picks the new file up on `ee site enable`, which re-enables admin-tools.
			if ( ! $site->site_enabled ) {
				return;
			}

			$containers = self::site_mounted_containers( $site, $mounted );
			if ( null === $containers ) {
				$recreate = $written;
				$start    = true;
			} else {
				$recreate = ! empty( $containers );
				$start    = self::any_up( $containers );
			}
			if ( ! $recreate ) {
				return;
			}

			// With nothing to restore, retry: a failed recreate can leave the site's old containers removed.
			if ( self::recreate( $site, [ 'docker-compose-admin.yml' ], 'nginx', $start, ! $written ) ) {
				return;
			}

			if ( $written ) {
				$fs->dumpFile( $admin_compose, $old_compose );
				$written = false;
				self::recreate( $site, [ 'docker-compose-admin.yml' ], 'nginx', $start );
			}
			EE::warning( sprintf( 'Could not recreate the containers of %1$s without the EasyEngine database mount. Run `ee admin-tools enable %1$s --force` to retry.', $site->site_url ) );
		} catch ( \Throwable $e ) {
			if ( $written ) {
				try {
					$fs->dumpFile( $admin_compose, $old_compose );
				} catch ( \Throwable $restore_error ) {
					EE::debug( $restore_error->getMessage() );
				}
			}
			EE::warning( sprintf( 'Could not migrate admin-tools of %1$s (%2$s). Run `ee admin-tools enable %1$s --force` to retry.', $site->site_url, $e->getMessage() ) );
		}
	}

	/**
	 * Recreates php and nginx from the site's normal compose when they still mount the database but admin-tools is off
	 * (e.g. an older `admin-tools disable` whose recreate failed), the same way `admin-tools disable` does.
	 *
	 * @param Site       $site    Site with admin-tools disabled.
	 * @param array|null $mounted Result of containers_mounting_db().
	 */
	public static function drop_lingering_mount( $site, $mounted ) {

		if ( ! $site->site_enabled || ! is_dir( $site->site_fs_path ) ) {
			return;
		}

		try {
			$containers = self::site_mounted_containers( $site, $mounted );
			if ( null === $containers && null !== $mounted ) {
				EE::warning( sprintf( 'Could not list the containers of %1$s to check them for the EasyEngine database mount. Run `ee admin-tools disable %1$s --force` if they still have it.', $site->site_url ) );
			}
			if ( empty( $containers ) ) {
				return;
			}
			if ( self::recreate( $site, [], 'nginx php', self::any_up( $containers ), true ) ) {
				return;
			}
			$reason = 'docker-compose failed';
		} catch ( \Throwable $e ) {
			$reason = $e->getMessage();
		}
		EE::warning( sprintf( 'Could not recreate the containers of %1$s without the EasyEngine database mount (%2$s). Run `ee admin-tools disable %1$s --force` to retry.', $site->site_url, $reason ) );
	}

	/**
	 * Containers of any site that mount the EE database.
	 *
	 * @return array|null Container ID => [ 'name' => name, 'up' => bool ]; null when docker can't be queried.
	 */
	private static function containers_mounting_db() {

		// Matches bind mounts by host or container path; the old compose used the same path for both.
		// {{.Status}} rather than {{.State}}, which older docker clients don't have.
		$launch = EE::launch( sprintf( "docker ps -a --no-trunc --filter %s --format '{{.ID}} {{.Names}} {{.Status}}'", escapeshellarg( 'volume=' . DB ) ) );
		if ( $launch->return_code ) {
			if ( ! self::$query_warned ) {
				self::$query_warned = true;
				EE::warning( 'Could not check which containers still mount the EasyEngine database. Run `ee admin-tools enable <site> --force` (or `disable` for sites without admin-tools) for sites whose php or nginx containers still have it.' );
			}

			return null;
		}

		$containers = [];
		foreach ( array_filter( explode( "\n", trim( $launch->stdout ) ) ) as $line ) {
			$parts                   = explode( ' ', trim( $line ), 3 );
			$status                  = isset( $parts[2] ) ? $parts[2] : '';
			$containers[ $parts[0] ] = [
				'name' => isset( $parts[1] ) ? $parts[1] : $parts[0],
				'up'   => 0 === strpos( $status, 'Up' ) || 0 === strpos( $status, 'Restarting' ),
			];
		}

		return $containers;
	}

	/**
	 * Warns once about containers that still mount the EE database after all sites were handled.
	 */
	private static function warn_remaining_mounts() {

		$remaining = self::containers_mounting_db();
		if ( empty( $remaining ) ) {
			return;
		}
		$names = array_column( $remaining, 'name' );
		sort( $names );
		EE::warning( sprintf( 'These containers still mount the EasyEngine database: %s. Run `ee admin-tools enable <site> --force` for their site (`ee admin-tools disable <site> --force` if it has admin-tools off), or recreate the containers.', implode( ', ', $names ) ) );
	}

	/**
	 * Whether any of the containers is up.
	 *
	 * @param array $containers Containers as returned by containers_mounting_db().
	 *
	 * @return bool
	 */
	private static function any_up( $containers ) {

		return in_array( true, array_column( $containers, 'up' ), true );
	}

	/**
	 * The site's containers that mount the EE database.
	 *
	 * @param Site       $site    Site.
	 * @param array|null $mounted Result of containers_mounting_db().
	 *
	 * @return array|null Same shape as containers_mounting_db(); null when unknown.
	 * @throws \Exception
	 */
	private static function site_mounted_containers( $site, $mounted ) {

		if ( empty( $mounted ) ) {
			return $mounted;
		}
		if ( ! chdir( $site->site_fs_path ) ) {
			throw new \Exception( "Could not change to $site->site_fs_path." );
		}
		$launch = EE::launch( \EE_DOCKER::docker_compose_with_custom() . ' ps -a -q' );
		if ( $launch->return_code ) {
			return null;
		}

		return array_intersect_key( $mounted, array_flip( array_filter( array_map( 'trim', explode( "\n", $launch->stdout ) ) ) ) );
	}

	/**
	 * Recreates the given services; when none of the old containers was up they are recreated but not started.
	 *
	 * @param Site   $site     Site.
	 * @param array  $files    Compose files to add to the site's own.
	 * @param string $services Services to bring up (dependencies follow).
	 * @param bool   $start    Whether to start them.
	 * @param bool   $retry    Whether to try once more after a failure.
	 *
	 * @return bool
	 * @throws \Exception
	 */
	private static function recreate( $site, $files, $services, $start, $retry = false ) {

		if ( ! chdir( $site->site_fs_path ) ) {
			throw new \Exception( "Could not change to $site->site_fs_path." );
		}
		$up = \EE_DOCKER::docker_compose_with_custom( $files ) . ( $start ? ' up -d ' : ' up --no-start ' ) . $services;
		if ( ! EE::exec( $up ) && ( ! $retry || ! EE::exec( $up ) ) ) {
			return false;
		}
		if ( $start ) {
			// nginx resolves php once at start; reload in case compose recreated php but not nginx.
			try {
				SiteContainers::reload_nginx( $site->site_fs_path );
			} catch ( \Exception $e ) {
				EE::warning( sprintf( 'Could not reload nginx of %1$s. Run `ee site reload %1$s --nginx`.', $site->site_url ) );
			}
		}

		return true;
	}
}
