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
