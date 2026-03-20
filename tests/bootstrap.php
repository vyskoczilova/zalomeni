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

// Stub _wptexturize_pushpop_element (WP core private function for tag tracking)
if ( ! function_exists( '_wptexturize_pushpop_element' ) ) {
    function _wptexturize_pushpop_element( $text, &$stack, $disabled_elements, $opening, $closing ) {
        $tag = trim( str_replace( $closing, '', str_replace( $opening, '', $text ) ) );
        $tag = explode( ' ', $tag )[0]; // strip attributes
        if ( in_array( $tag, $disabled_elements ) ) {
            $stack[] = $tag;
        } elseif ( strpos( $tag, '/' ) === 0 ) {
            $tag = ltrim( $tag, '/' );
            if ( ( $key = array_search( $tag, $stack ) ) !== false ) {
                unset( $stack[ $key ] );
                $stack = array_values( $stack );
            }
        }
    }
}

require_once __DIR__ . '/../zalomeni.php';
