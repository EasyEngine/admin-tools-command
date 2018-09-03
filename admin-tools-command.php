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


EE::add_hook('before_invoke:admin-tools up', function ( $args, $assoc_args ) {
	$site = \EE\Site\Utils\auto_site_name( $args, 'admin-tools', 'up' );
	$fs = new \Symfony\Component\Filesystem\Filesystem();

	if ( ! is_array( EE::get_runner()->find_command_to_run( [ 'auth' ] ) ) ) {
		EE::error( 'Auth command needs to be registered for admin_tools' );
	}

	EE::run_command( [ 'auth', 'init' ] );
});
