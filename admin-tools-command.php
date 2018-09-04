<?php

if ( ! class_exists( 'EE' ) ) {
	return;
}

if ( ! defined( 'ADMIN_TOOLS_FILE' ) ) {
	define( 'ADMIN_TOOLS_FILE', __DIR__ . '/ee-tools.json' );
}

if ( ! defined( 'ADMIN_TEMPLATE_ROOT' ) ) {
	define( 'ADMIN_TEMPLATE_ROOT', __DIR__ . '/templates' );
}

$autoload = dirname( __FILE__ ) . '/vendor/autoload.php';
if ( file_exists( $autoload ) ) {
	require_once $autoload;
}

EE::add_command( 'admin-tools', 'Admin_Tools_Command' );


EE::add_hook('before_invoke:admin-tools up', 'init_global_auth' );

/**
 * Initialize global auth if it's not present.
 *
 * @throws \EE\ExitException
 */
function init_global_auth() {
	if ( ! is_array( EE::get_runner()->find_command_to_run( [ 'auth' ] ) ) ) {
		EE::error( 'Auth command needs to be registered for mailhog' );
	}

	EE::run_command( [ 'auth', 'init' ] );
}
