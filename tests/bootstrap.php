<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Define ABSPATH so the plugin file can be loaded
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/' );
}

WP_Mock::bootstrap();

// Stub WordPress functions needed at file load time
WP_Mock::userFunction( 'register_activation_hook' );
WP_Mock::userFunction( 'is_admin' )->andReturn( false );
WP_Mock::userFunction( 'add_action' );
WP_Mock::userFunction( 'add_filter' );
WP_Mock::userFunction( 'get_option' )->andReturn( '' );

require_once __DIR__ . '/../zalomeni.php';
