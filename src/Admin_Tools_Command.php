<?php

/**
 * Enables/Disables admin-tools on a site.
 *
 * ## EXAMPLES
 *
 *     # Enable admin tools on site
 *     $ ee admin-tools up example.com
 *
 * @package ee-cli
 */

use \Symfony\Component\Filesystem\Filesystem;
use \Composer\Console\Application;
use \Symfony\Component\Console\Input\ArrayInput;

class Admin_Tools_Command extends EE_Command {


	/**
	 * @var string $command Name of the command being run.
	 */
	private $command;

	/**
	 * @var Filesystem $fs Symfony Filesystem object.
	 */
	private $fs;

	/**
	 * @var array $site Associative array containing essential site related information.
	 */
	private $site;

	public function __construct() {

		$this->command = 'admin-tools';
		$this->fs      = new Filesystem();
		define( 'ADMIN_TOOL_DIR', EE_CONF_ROOT . '/admin-tools' );
	}

	/**
	 * Installs admin-tools for EasyEngine.
	 */
	public function install() {

		if ( ! $this->is_installed() ) {
			EE::log( 'Installing admin-tools. This may take some time.' );
			$this->fs->mkdir( ADMIN_TOOL_DIR );
		}

		$tools = json_decode( file_get_contents( ADMIN_TOOLS_FILE ), true );

		foreach ( $tools as $tool => $data ) {
			if ( ! $this->is_installed( $tool ) ) {
				EE::log( "Installing $tool" );
				$tool_path = ADMIN_TOOL_DIR . '/' . $tool;
				call_user_func_array( [ $this, "install_$tool" ], [ $data, $tool_path ] );
				EE::log( 'Done.' );
			} else {
				EE::log( "$tool already installed." );
			}
		}
	}


	/**
	 * Enables admin-tools on given site.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of website to enable admin-tools on.
	 */
	public function up( $args, $assoc_args ) {

		EE\Utils\delem_log( $this->command . ' ' . __FUNCTION__ . ' start' );
		$args = EE\SiteUtils\auto_site_name( $args, $this->command, __FUNCTION__ );
		$this->populate_site_info( $args );
		chdir( $this->site['root'] );

		$launch           = EE::launch( 'docker-compose config --services' );
		$services         = explode( PHP_EOL, trim( $launch->stdout ) );
		$min_req_services = [ 'nginx', 'php' ];

		if ( count( array_intersect( $services, $min_req_services ) ) !== count( $min_req_services ) ) {
			EE::error( sprintf( '%s site-type of %s-command does not support admin-tools.', $this->site['type'], $this->site['command'] ) );
		}

		if ( ! $this->is_installed() ) {
			EE::log( 'It seems admin-tools have not yet been installed.' );
			$this->install();
		}

		// TODO: services_enabled fnuction after db changes
		// if($this->services_enabled()){
		// 	EE::error('Services are already enabled.');
		// }

		$this->move_config_file( $this->site['root'] . '/docker-compose-admin.yml', 'docker-compose-admin.mustache' );

		if ( EE::exec( 'docker-compose -f docker-compose.yml -f docker-compose-admin.yml up -d nginx' ) ) {
			EE::success( sprintf( 'admin-tools enabled for %s site.', $this->site['name'] ) );
		} else {
			EE::error( sprintf( 'Error in enabling admin-tools for %s site. Check logs.', $this->site['name'] ) );
		}

		EE\Utils\delem_log( $this->command . ' ' . __FUNCTION__ . ' stop' );
	}

	/**
	 * Disables admin-tools on given site.
	 *
	 * ## OPTIONS
	 *
	 * [<site-name>]
	 * : Name of website to disable admin-tools on.
	 */
	public function down( $args, $assoc_args ) {

		EE\Utils\delem_log( $this->command . ' ' . __FUNCTION__ . ' start' );
		$args = EE\SiteUtils\auto_site_name( $args, $this->command, __FUNCTION__ );
		$this->populate_site_info( $args );

		// TODO: services_enabled fnuction after db changes
		// if($this->services_enabled()){
		// 	Then only run this...
		// }

		EE::docker()::docker_compose_up( $this->site['root'], [ 'nginx', 'php' ] );
		EE::success( sprintf( 'admin-tools disabled for %s site.', $this->site['name'] ) );

		EE\Utils\delem_log( $this->command . ' ' . __FUNCTION__ . ' stop' );
	}

	/**
	 * Check if a tools directory is installed.
	 *
	 * @param string $tool The tool whose directory has to be checked.
	 *
	 * @return bool status.
	 */
	private function is_installed( $tool = '' ) {

		$tool = in_array( $tool, [ 'index', 'phpinfo' ] ) ? $tool . '.php' : $tool;
		$tool = 'opcache' === $tool ? $tool . '-gui.php' : $tool;

		return $this->fs->exists( ADMIN_TOOL_DIR . '/' . $tool );
	}

	/**
	 * Function to download file to a path.
	 *
	 * @param string $path         Path to download the file on.
	 * @param string $download_url Url to download the file from.
	 */
	private function download( $path, $download_url ) {

		$headers = array();
		$options = array(
			'timeout'  => 1200,  // 20 minutes ought to be enough for everybody.
			'filename' => $path,
		);
		EE\Utils\http_request( 'GET', $download_url, null, $headers, $options );
	}

	/**
	 * Extract zip files.
	 *
	 * @param string $zip_file        Path to the zip file.
	 * @param string $path_to_extract Path where zip needs to be extracted to.
	 *
	 * @return bool Success of extraction.
	 */
	private function extract_zip( $zip_file, $path_to_extract ) {

		$zip = new ZipArchive;
		$res = $zip->open( $zip_file );
		if ( true === $res ) {
			$zip->extractTo( $path_to_extract );
			$zip->close();

			return true;
		}

		return false;
	}

	/**
	 * Place config files from templates to tools.
	 *
	 * @param string $config_file   Destination Path where the config file needs to go.
	 * @param string $template_file Source Template file from which the config needs to be created.
	 */
	private function move_config_file( $template_file, $config_file ) {

		$this->fs->dumpFile( $config_file, file_get_contents( ADMIN_TEMPLATE_ROOT . '/' . $template_file ) );
	}

	/**
	 * Populate basic site info from db.
	 */
	private function populate_site_info( $args ) {

		$this->site['name'] = EE\Utils\remove_trailing_slash( $args[0] );

		if ( EE::db()::site_in_db( $this->site['name'] ) ) {

			$db_select = EE::db()::select( [], [ 'sitename' => $this->site['name'] ], 'sites', 1 );

			$this->site['type']    = $db_select['site_type'];
			$this->site['root']    = $db_select['site_path'];
			$this->site['command'] = $db_select['site_command'];
		} else {
			EE::error( sprintf( 'Site %s does not exist.', $this->site['name'] ) );
		}
	}

	private function composer_install( $tool_path ) {

		putenv( 'COMPOSER_HOME=' . EE_VENDOR_DIR . '/bin/composer' );
		chdir( $tool_path );
		$input       = new ArrayInput( array( 'command' => 'install' ) );
		$application = new Application();
		$application->setAutoExit( false );
		$application->run( $input );
	}


	/**
	 * Function to install index.php file.
	 *
	 * @param array $data       Data about url and version from `tools.json`.
	 * @param string $tool_path Path to where the tool needs to be installed.
	 */
	private function install_index( $data, $tool_path ) {

		$this->move_config_file( 'index.mustache', $tool_path . '.php' );
	}

	/**
	 * Function to install phpinfo.php file.
	 *
	 * @param array $data       Data about url and version from `tools.json`.
	 * @param string $tool_path Path to where the tool needs to be installed.
	 */
	private function install_phpinfo( $data, $tool_path ) {

		$this->move_config_file( 'phpinfo.mustache', $tool_path . '.php' );
	}

	/**
	 * Function to install phpMyAdmin.
	 *
	 * @param array $data       Data about url and version from `tools.json`.
	 * @param string $tool_path Path to where the tool needs to be installed.
	 */
	private function install_pma( $data, $tool_path ) {

		$temp_dir      = EE\Utils\get_temp_dir();
		$download_path = $temp_dir . 'pma.zip';
		$version       = str_replace( '.', '_', $data['version'] );
		$download_url  = str_replace( '{version}', $version, $data['url'] );
		$this->download( $download_path, $download_url );
		$this->extract_zip( $download_path, $temp_dir );
		$this->fs->rename( $temp_dir . 'phpmyadmin-RELEASE_' . $version, $tool_path );
		$this->move_config_file( 'pma.config.mustache', $tool_path . '/config.inc.php' );
		$this->composer_install( $tool_path );
	}

	/**
	 * Function to install phpRedisAdmin.
	 *
	 * @param array $data       Data about url and version from `tools.json`.
	 * @param string $tool_path Path to where the tool needs to be installed.
	 */
	private function install_pra( $data, $tool_path ) {

		$temp_dir      = EE\Utils\get_temp_dir();
		$download_path = $temp_dir . 'pra.zip';
		$download_url  = str_replace( '{version}', $data['version'], $data['url'] );
		$this->download( $download_path, $download_url );
		$this->extract_zip( $download_path, $temp_dir );
		$this->fs->rename( $temp_dir . 'phpRedisAdmin-' . $data['version'], $tool_path );
		$this->move_config_file( 'pra.config.mustache', $tool_path . 'includes/config.inc.php' );
		$this->composer_install( $tool_path );
	}

	/**
	 * Function to install opcache gui.
	 *
	 * @param array $data       Data about url and version from `tools.json`.
	 * @param string $tool_path Path to where the tool needs to be installed.
	 */
	private function install_opcache( $data, $tool_path ) {

		$temp_dir      = EE\Utils\get_temp_dir();
		$download_path = $temp_dir . 'opcache-gui.php';
		$this->download( $download_path, $data['url'] );
		$this->fs->rename( $temp_dir . 'opcache-gui.php', $tool_path . '-gui.php' );
	}

}
