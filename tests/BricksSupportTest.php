<?php

use WP_Mock\Tools\TestCase;

/** Specification expectations for the decoded-text rules and Bricks registration. */
class BricksSupportTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    private function check_registration( array $stored_matches, bool $opt_out = false ): void {
        $legacy_hooks = [
            'comment_author', 'term_name', 'link_name', 'link_description', 'link_notes',
            'bloginfo', 'wp_title', 'widget_title', 'term_description', 'the_title',
            'the_content', 'the_excerpt', 'comment_text', 'single_post_title', 'list_cats',
            'acf_the_content',
        ];
        $hooks = array_merge( $legacy_hooks, [ 'bricks/frontend/render_data' ] );
        $filters = array_combine( $hooks, $hooks );
        $applied = [];
        WP_Mock::userFunction( 'get_option' )->with( 'zalomeni_matches' )->andReturn( $stored_matches );
        WP_Mock::onFilter( 'zalomeni_filtry' )->with( $filters )->reply( new WP_Mock\InvokedFilterValue(
            function ( $filters ) use ( &$applied, $opt_out ) {
                $applied[] = 'zalomeni_filtry';
                if ( $opt_out ) {
                    unset( $filters['bricks/frontend/render_data'] );
                }
                return $filters;
            }
        ) );
        $acf_hooks = [ 'acf/format_value/type=text', 'acf/format_value/type=textarea' ];
        WP_Mock::onFilter( 'zalomeni_acf_filtry' )->with( array_combine( $acf_hooks, $acf_hooks ) )->reply( new WP_Mock\InvokedFilterValue(
            function ( $filters ) use ( &$applied ) {
                $applied[] = 'zalomeni_acf_filtry';
                return $filters;
            }
        ) );
        foreach ( [ 'texturize' => $legacy_hooks, 'texturize_plain' => $acf_hooks ] as $callback => $tags ) {
            foreach ( $tags as $tag ) {
                if ( $stored_matches ) {
                    WP_Mock::expectFilterAdded( $tag, [ 'Zalomeni', $callback ], 10, 1 );
                } else {
                    WP_Mock::expectFilterNotAdded( $tag, [ 'Zalomeni', $callback ], 10, 1 );
                }
            }
        }
        if ( $opt_out || ! $stored_matches ) {
            WP_Mock::expectFilterNotAdded( 'bricks/frontend/render_data', [ 'Zalomeni', 'texturize_bricks' ], 20, 1 );
        } else {
            WP_Mock::expectFilterAdded( 'bricks/frontend/render_data', [ 'Zalomeni', 'texturize_bricks' ], 20, 1 );
        }
        foreach ( [ 'texturize', 'texturize_plain' ] as $callback ) {
            foreach ( [ 10, 20 ] as $priority ) {
                WP_Mock::expectFilterNotAdded( 'bricks/frontend/render_data', [ 'Zalomeni', $callback ], $priority, 1 );
            }
        }

        zalomeni_init()->add_filters();
        $this->assertSame( $stored_matches ? [ 'zalomeni_filtry', 'zalomeni_acf_filtry' ] : [], $applied );
    }

    public function test_registers_nothing_with_empty_stored_patterns(): void {
        $this->check_registration( [] );
    }

    public function test_preserves_legacy_and_acf_registration(): void {
        $this->check_registration( [ 'words' => '/v /' ] );
    }

    public function test_respects_bricks_opt_out(): void {
        foreach ( [ [], [ 'words' => '/v /' ] ] as $stored_matches ) {
            // WP_Mock stacks function stubs, so reset before each option scenario.
            WP_Mock::tearDown();
            WP_Mock::setUp();
            $this->check_registration( $stored_matches, true );
        }
    }

    private function decoded_patterns( array $overrides ): array {
        $options = array_merge( [
            'zalomeni_prepositions' => '',
            'zalomeni_conjunctions' => '',
            'zalomeni_abbreviations' => '',
            'zalomeni_between_number_and_unit' => '',
            'zalomeni_space_between_numbers' => '',
            'zalomeni_spaces_in_scales' => '',
            'zalomeni_space_after_ordered_number' => '',
            'zalomeni_custom_terms' => '',
        ], $overrides );
        WP_Mock::userFunction( 'get_option' )->andReturnUsing(
            function ( $key, $default = '' ) use ( $options ) {
                return $options[ $key ] ?? $default;
            }
        );
        WP_Mock::userFunction( 'update_option' );

        $matches = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $replacements = new ReflectionMethod( 'Zalomeni', 'prepare_replacements' );
        $matches->setAccessible( true );
        $replacements->setAccessible( true );
        return [ $matches->invoke( null, true ), $replacements->invoke( null, true ) ];
    }

    public function test_decoded_compiler_uses_text_boundaries_and_unicode_spaces(): void {
        $nbsp = "\xC2\xA0";
        list( $matches, $replacements ) = $this->decoded_patterns( [
            'zalomeni_prepositions' => 'on',
            'zalomeni_prepositions_list' => 'v, s',
            'zalomeni_between_number_and_unit' => 'on',
            'zalomeni_between_number_and_unit_list' => 'kg',
            'zalomeni_custom_terms' => "Alpha Beta\nAlpha Beta Gamma",
        ] );
        $cases = [
            [ 'V lese', 'V'.$nbsp.'lese' ],
            [ '"V lese | „V lese | “V lese | ‚V lese | ‘V lese',
                '"V'.$nbsp.'lese | „V'.$nbsp.'lese | “V'.$nbsp.'lese | ‚V'.$nbsp.'lese | ‘V'.$nbsp.'lese' ],
            [ 'x;v lese | America\'s Response | America’s Response',
                'x;v lese | America\'s Response | America’s Response' ],
            [ $nbsp.'v lese', $nbsp.'v'.$nbsp.'lese' ],
            [ '5 kg'.$nbsp.'v lese', '5'.$nbsp.'kg'.$nbsp.'v'.$nbsp.'lese' ],
            [ 'Alpha'.$nbsp.'Beta Gamma', 'Alpha'.$nbsp.'Beta'.$nbsp.'Gamma' ],
            [ 'Alpha Beta Gamma', 'Alpha'.$nbsp.'Beta'.$nbsp.'Gamma' ],
        ];
        foreach ( $cases as $case ) {
            $this->assertSame( $case[1], preg_replace( $matches, $replacements, $case[0] ) );
        }
    }

    public function test_decoded_helper_reaches_stable_output(): void {
        $nbsp = "\xC2\xA0";
        list( $matches, $replacements ) = $this->decoded_patterns( [
            'zalomeni_prepositions' => 'on',
            'zalomeni_prepositions_list' => 'v',
        ] );
        $helper = new ReflectionMethod( 'Zalomeni', 'replace_spaces' );
        $helper->setAccessible( true );
        $input = 'v v v v v v v v v v v v v v v v lese';
        $expected = 'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp
            .'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'v'.$nbsp.'lese';

        $actual = $helper->invoke( null, $input, $matches, $replacements, true );
        $this->assertSame( $expected, $actual );
        $this->assertSame( $expected, $helper->invoke( null, $actual, $matches, $replacements, true ) );
    }

    public function test_decoded_helper_restores_original_on_failure(): void {
        $helper = new ReflectionMethod( 'Zalomeni', 'replace_spaces' );
        $helper->setAccessible( true );

        $this->assertSame( 'v lese', $helper->invoke( null, 'v lese', [ '/[/' ], [ 'x' ], true ) );
        $this->assertSame( 'v lese', $helper->invoke( null, 'v lese', [ '/v/' ], [ 'x' ], true ) );
    }

    public function test_bricks_adapter_returns_input_without_html_update_api(): void {
        $this->assertFalse( class_exists( 'WP_HTML_Processor' ), 'This test requires the WP_Mock environment without the real HTML processor.' );
        foreach ( [ '', null, false, 7, [ 'v lese' ], '<p>v lese</p>' ] as $input ) {
            $this->assertSame( $input, Zalomeni::texturize_bricks( $input ) );
        }
    }
}
