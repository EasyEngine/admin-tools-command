<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use EE\Migration\SiteContainers;
use EE\RevertableStepProcessor;
use EE\Model\Site;
use Symfony\Component\Filesystem\Filesystem;

class UpdateConfigWithEnv extends Base {

	private $sites;
	/** @var RevertableStepProcessor $rsp Keeps track of migration state. Reverts on error */
	private static $rsp;

	public function __construct() {

		parent::__construct();
		$this->sites = Site::all();
		if ( $this->is_first_execution ) {
			$this->skip_this_migration = true;
		}
		if ( $this->fs->exists( EE_ROOT_DIR . '/admin-tools/index.php' ) ) {
			$this->skip_this_migration = false;
		}
	}

	/**
	 * Execute php config updates.
	 *
	 * @throws EE\ExitException
	 */
	public function up() {

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping update-index migration as it is not needed.' );

			return;
		}
		self::$rsp = new RevertableStepProcessor();


		EE::debug( 'Starting update-config-with-env' );

		$pma_config     = EE_ROOT_DIR . '/admin-tools/pma/config.inc.php';
		$pra_config     = EE_ROOT_DIR . '/admin-tools/pra/includes/config.inc.php';
		$bak_pma_config = EE_BACKUP_DIR . '/admin-tools/pma/config.inc.php.bak';
		$bak_pra_config = EE_BACKUP_DIR . '/admin-tools/pra/includes/config.inc.php.bak';

		self::$rsp->add_step(
			'take-pma-config-backup',
			'EE\Migration\SiteContainers::backup_restore',
			'EE\Migration\SiteContainers::backup_restore',
			[ $pma_config, $bak_pma_config ],
			[ $bak_pma_config, $pma_config ]
		);

		self::$rsp->add_step(
			'take-pra-config-backup',
			'EE\Migration\SiteContainers::backup_restore',
			'EE\Migration\SiteContainers::backup_restore',
			[ $pra_config, $bak_pra_config ],
			[ $bak_pra_config, $pra_config ]
		);

		self::$rsp->add_step(
			'generate-new-config-files',
			'EE\Migration\UpdateConfigWithEnv::generate_new_config_files',
			null,
			null,
			null
		);

		foreach ( $this->sites as $site ) {
			if ( $site->admin_tools ) {
				$array_data = ( array ) $site;
				$site_data  = reset( $array_data );
				self::$rsp->add_step(
					're-enable-admin-tools',
					'EE\Migration\UpdateConfigWithEnv::re_enable_admin_tools',
					null,
					[ $site_data ],
					null
				);
			}
		}

		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable run update-config-env migrations.' );
		}
	}

	/**
	 * Not needed.
	 *
	 * @throws EE\ExitException
	 */
	public function down() {
	}

	public static function generate_new_config_files() {

		$new_pma_config = EE_ROOT_DIR . '/admin-tools/pma/config.inc.php';
		$new_pra_config = EE_ROOT_DIR . '/admin-tools/pra/includes/config.inc.php';

		$fs = new Filesystem();
		$fs->dumpFile( $new_pma_config, file_get_contents( ADMIN_TEMPLATE_ROOT . '/pma.config.mustache' ) );
		$fs->dumpFile( $new_pra_config, file_get_contents( ADMIN_TEMPLATE_ROOT . '/pra.config.mustache' ) );

		$index_path_data = [
			'db_path'       => DB,
			'ee_admin_path' => '/var/www/htdocs/ee-admin',
		];
		$index_file      = EE\Utils\mustache_render( ADMIN_TEMPLATE_ROOT . '/index.mustache', $index_path_data );
		$fs->dumpFile( EE_ROOT_DIR . '/admin-tools/index.php', $index_file );
	}

	public static function re_enable_admin_tools( $site ) {

		$fs = new Filesystem();
		chdir( $site['site_fs_path'] );

		$docker_compose_data  = [
			'ee_root_dir'   => EE_ROOT_DIR,
			'db_path'       => DB,
			'ee_admin_path' => '/var/www/htdocs/ee-admin',
			'redis_host'    => $site['cache_host'],
			'db_host'       => $site['db_host'],
		];
		$docker_compose_admin = EE\Utils\mustache_render( ADMIN_TEMPLATE_ROOT . '/docker-compose-admin.mustache', $docker_compose_data );
		$fs->dumpFile( $site['site_fs_path'] . '/docker-compose-admin.yml', $docker_compose_admin );

		if ( EE::exec( 'docker-compose -f docker-compose.yml -f docker-compose-admin.yml up -d nginx' ) ) {
			EE::debug( sprintf( 'admin-tools re-enabled for %s site.', $site['site_url'] ) );
		}
	}
}
