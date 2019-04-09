<?php

namespace EE\Migration;

use EE;
use EE\Migration\Base;
use EE\Migration\SiteContainers;
use EE\RevertableStepProcessor;
use EE\Model\Site;

class UpdateIndexForPublicDir extends Base {

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
			foreach ( $this->sites as $site ) {
				if ( $site->admin_tools ) {
					$this->skip_this_migration = false;
				}
			}
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


		EE::debug( 'Starting update-admin-tools-index' );

		$admin_tools_index = EE_ROOT_DIR . '/admin-tools/index.php';
		$backup_index      = EE_BACKUP_DIR . '/admin-tools/index.php';
		$new_index_file    = EE_BACKUP_DIR . '/admin-tools/new-index.php';

		$index_path_data = [
			'db_path'       => DB,
			'ee_admin_path' => '/var/www/htdocs/ee-admin',
		];
		$index_file      = EE\Utils\mustache_render( ADMIN_TEMPLATE_ROOT . '/index.mustache', $index_path_data );
		$this->fs->dumpFile( $new_index_file, $index_file );

		self::$rsp->add_step(
			'take-admin-tools-index-backup',
			'EE\Migration\SiteContainers::backup_restore',
			'EE\Migration\SiteContainers::backup_restore',
			[ $admin_tools_index, $backup_index ],
			[ $backup_index, $admin_tools_index ]
		);

		self::$rsp->add_step(
			'update-admin-tools-index',
			'EE\Migration\SiteContainers::backup_restore',
			'EE\Migration\SiteContainers::backup_restore',
			[ $new_index_file, $admin_tools_index ],
			[ $admin_tools_index, $new_index_file ]
		);

		if ( ! self::$rsp->execute() ) {
			throw new \Exception( 'Unable run update-index migrations.' );
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
