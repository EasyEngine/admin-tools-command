<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use EE\Migration\SiteContainers;
use EE\RevertableStepProcessor;
use EE\Model\Site;
use function EE\Utils\random_password;

class UpdatePmaConfig extends Base {

	private $sites;
	/** @var RevertableStepProcessor $rsp Keeps track of migration state. Reverts on error */
	private static $rsp;

	public function __construct() {

		parent::__construct();
		$this->sites = Site::all();
		if ( $this->is_first_execution || ! $this->sites ) {
			$this->skip_this_migration = true;
		} else {
			$this->skip_this_migration = true;
			if ( $this->fs->exists( EE_ROOT_DIR . '/admin-tools/pma/config.inc.php' ) ) {
				$this->skip_this_migration = false;
			}
		}
	}

	/**
	 * Execute pma config updates.
	 *
	 * @throws EE\ExitException
	 */
	public function up() {

		if ( $this->skip_this_migration ) {
			EE::debug( 'Skipping pma-config migration as it is not needed.' );

			return;
		}
		self::$rsp = new RevertableStepProcessor();


		EE::debug( 'Starting update-pma-config' );

		$pma_config    = EE_ROOT_DIR . '/admin-tools/pma/config.inc.php';
		$backup_config = EE_BACKUP_DIR . '/admin-tools/pma/config.inc.php';
		$new_config    = EE_BACKUP_DIR . '/admin-tools/new-pma/config.inc.php';

		$pma_config_data    = [
			'blowfish_secret' => random_password( 32 ),
		];
		$pma_config_content = EE\Utils\mustache_render( ADMIN_TEMPLATE_ROOT . '/pma.config.mustache', $pma_config_data );
		$this->fs->dumpFile( $new_config, $pma_config_content );

		self::$rsp->add_step(
			'take-admin-tools-pma-config-backup',
			'EE\Migration\SiteContainers::backup_restore',
			'EE\Migration\SiteContainers::backup_restore',
			[ $pma_config, $backup_config ],
			[ $backup_config, $pma_config ]
		);

		self::$rsp->add_step(
			'update-pma-config',
			'EE\Migration\SiteContainers::backup_restore',
			'EE\Migration\SiteContainers::backup_restore',
			[ $new_config, $pma_config ],
			[ $pma_config, $new_config ]
		);

		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable run pma-config migrations.' );
		}
	}

	/**
	 * Bring back the existing old config and path.
	 *
	 * @throws EE\ExitException
	 */
	public function down() {
	}
}
