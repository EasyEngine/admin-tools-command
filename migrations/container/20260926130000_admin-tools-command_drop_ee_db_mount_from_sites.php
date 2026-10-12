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

		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable to run drop-ee-db-mount-from-sites migration.' );
		}

		foreach ( $this->sites as $site ) {
			self::migrate_site( $site );
		}
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
	 * @param Site $site Site with admin-tools enabled.
	 */
	public static function migrate_site( $site ) {

		$fs            = new Filesystem();
		$admin_compose = $site->site_fs_path . '/docker-compose-admin.yml';

		if ( ! is_dir( $site->site_fs_path ) ) {
			EE::debug( "Skipping $site->site_url: $site->site_fs_path does not exist." );

			return;
		}

		\Admin_Tools_Command::write_site_data( $site->site_fs_path, $site->mailhog_enabled, $site->cache_nginx_fullpage );

		// Without it the site doesn't use admin-tools compose (e.g. custom-compose); `admin-tools enable` writes a fresh one.
		if ( ! $fs->exists( $admin_compose ) ) {
			return;
		}

		$old_compose = file_get_contents( $admin_compose );
		$new_compose = \Admin_Tools_Command::render_admin_compose( $site->site_fs_path, $site->cache_host, $site->db_host );
		if ( $old_compose === $new_compose ) {
			return;
		}
		$fs->copy( $admin_compose, EE_BACKUP_DIR . '/' . $site->site_url . '/docker-compose-admin.yml.with-db', true );
		$fs->dumpFile( $admin_compose, $new_compose );

		// A disabled site picks the new file up on `ee site enable`, which re-enables admin-tools.
		if ( ! $site->site_enabled ) {
			return;
		}

		chdir( $site->site_fs_path );
		$up = \EE_DOCKER::docker_compose_with_custom( [ 'docker-compose-admin.yml' ] ) . ' up -d nginx';
		if ( EE::exec( $up ) ) {
			// nginx resolves php once at start; reload in case compose recreated php but not nginx.
			try {
				SiteContainers::reload_nginx( $site->site_fs_path );
			} catch ( \Exception $e ) {
				EE::warning( sprintf( 'Could not reload nginx of %s. Run `ee site reload %s --nginx`.', $site->site_url, $site->site_url ) );
			}

			return;
		}

		$fs->dumpFile( $admin_compose, $old_compose );
		EE::exec( $up );
		EE::warning( sprintf( 'Could not recreate the containers of %1$s without the EasyEngine database mount. Run `ee admin-tools enable %1$s --force` to retry.', $site->site_url ) );
	}
}
