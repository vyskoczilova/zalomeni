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

// Provide wp_strip_all_tags for test stubs (mirrors WP core behavior)
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
    function wp_strip_all_tags( $text, $remove_breaks = false ) {
        $text = preg_replace( '@<(script|style)[^>]*?>.*?</\\1>@si', '', $text );
        // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- this IS the wp_strip_all_tags polyfill; calling it here would recurse.
        $text = strip_tags( $text );
        if ( $remove_breaks ) {
            $text = preg_replace( '/[\r\n\t ]+/', ' ', $text );
        }
        return trim( $text );
    }
}

require_once __DIR__ . '/../zalomeni.php';
