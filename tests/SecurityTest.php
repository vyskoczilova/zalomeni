<?php

use WP_Mock\Tools\TestCase;

class SecurityTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
        // texturize() caches compiled patterns per request; each test mocks
        // different options, so the cache must not leak between tests.
        Zalomeni::flush_pattern_cache();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    // =========================================================================
    // 1. Sanitize callbacks on register_setting — verify via source inspection
    // =========================================================================

    public function test_all_register_setting_calls_have_sanitize_callback(): void {
        $source = file_get_contents( __DIR__ . '/../zalomeni.php' );

        // Find all register_setting calls
        preg_match_all( '/register_setting\s*\(\s*[\'"]reading[\'"]\s*,\s*[\'"]([^"\']+)[\'"]/', $source, $matches );
        $this->assertNotEmpty( $matches[1], 'No register_setting calls found' );

        // Every register_setting call must include sanitize_callback
        foreach ( $matches[1] as $option_name ) {
            // Build a pattern that matches the full register_setting call for this option
            $pattern = '/register_setting\s*\(\s*[\'"]reading[\'"]\s*,\s*[\'"]\Q' . $option_name . '\E[\'"].*?sanitize_callback/s';
            $this->assertMatchesRegularExpression( $pattern, $source,
                "register_setting for '$option_name' is missing sanitize_callback" );
        }
    }

    // =========================================================================
    // 2. Sanitize function behavior
    // =========================================================================

    public function test_sanitize_checkbox_accepts_only_on(): void {
        $this->assertSame( 'on', Zalomeni::sanitize_checkbox( 'on' ) );
        $this->assertSame( '', Zalomeni::sanitize_checkbox( 'off' ) );
        $this->assertSame( '', Zalomeni::sanitize_checkbox( '<script>alert(1)</script>' ) );
        $this->assertSame( '', Zalomeni::sanitize_checkbox( '1' ) );
        $this->assertSame( '', Zalomeni::sanitize_checkbox( 'yes' ) );
    }

    public function test_sanitize_text_list_strips_dangerous_characters(): void {
        WP_Mock::userFunction( 'sanitize_text_field' )->andReturnUsing( function ( $str ) {
            return trim( wp_strip_all_tags( $str ) );
        } );

        $this->assertSame( 'k, s, v, z', Zalomeni::sanitize_text_list( 'k, s, v, z' ) );

        $result = Zalomeni::sanitize_text_list( 'k, <script>alert(1)</script>, v' );
        $this->assertStringNotContainsString( '<script>', $result );
    }

    public function test_sanitize_custom_terms_strips_html(): void {
        WP_Mock::userFunction( 'sanitize_textarea_field' )->andReturnUsing( function ( $str ) {
            return trim( wp_strip_all_tags( $str ) );
        } );

        $result = Zalomeni::sanitize_custom_terms( "<script>alert('xss')</script>\nWindows \\d" );
        $this->assertStringNotContainsString( '<script>', $result );
    }

    // =========================================================================
    // 3. Regex injection via list items — preg_quote must be used
    // =========================================================================

    private function mock_prepare_matches_options( array $overrides = [] ): void {
        $defaults = [
            'zalomeni_prepositions'                 => '',
            'zalomeni_prepositions_list'             => '',
            'zalomeni_conjunctions'                  => '',
            'zalomeni_conjunctions_list'              => '',
            'zalomeni_abbreviations'                 => '',
            'zalomeni_abbreviations_list'             => '',
            'zalomeni_between_number_and_unit'       => '',
            'zalomeni_between_number_and_unit_list'  => '',
            'zalomeni_space_between_numbers'         => '',
            'zalomeni_spaces_in_scales'              => '',
            'zalomeni_space_after_ordered_number'    => '',
            'zalomeni_custom_terms'                  => '',
        ];
        $options = array_merge( $defaults, $overrides );

        WP_Mock::userFunction( 'get_option' )->andReturnUsing(
            function ( $key, $default = '' ) use ( $options ) {
                return $options[ $key ] ?? $default;
            }
        );
        WP_Mock::userFunction( 'update_option' );
    }

    public function test_regex_metacharacters_in_prepositions_list_are_escaped(): void {
        $this->mock_prepare_matches_options( [
            'zalomeni_prepositions'      => 'on',
            'zalomeni_prepositions_list' => 'k, s, v.+*, z',
        ] );

        $method = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $method->setAccessible( true );
        $matches = $method->invoke( null );

        $this->assertArrayHasKey( 'words', $matches );
        $result = @preg_match( $matches['words'], 'test v.+* text' );
        $this->assertNotFalse( $result, 'Regex pattern is invalid — metacharacters were not escaped' );

        $this->assertSame( 1, @preg_match( $matches['words'], ' v.+* ' ),
            'Literal metacharacters in list should be escaped and match literally' );
    }

    public function test_regex_metacharacters_in_units_list_are_escaped(): void {
        $this->mock_prepare_matches_options( [
            'zalomeni_between_number_and_unit'      => 'on',
            'zalomeni_between_number_and_unit_list' => 'm², kg, [evil]+',
        ] );

        $method = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $method->setAccessible( true );
        $matches = $method->invoke( null );

        $this->assertArrayHasKey( 'units', $matches );
        $result = @preg_match( $matches['units'], '5 [evil]+ ' );
        $this->assertNotFalse( $result, 'Regex pattern with units metacharacters is invalid' );
    }

    public function test_normal_prepositions_still_work_after_escaping(): void {
        $this->mock_prepare_matches_options( [
            'zalomeni_prepositions'      => 'on',
            'zalomeni_prepositions_list' => 'k, s, v, z',
        ] );

        $method = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $method->setAccessible( true );
        $matches = $method->invoke( null );

        $this->assertArrayHasKey( 'words', $matches );
        $this->assertSame( 1, preg_match( $matches['words'], ' v lese' ) );
        $this->assertSame( 1, preg_match( $matches['words'], ' k mostu' ) );
    }

    public function test_abbreviations_with_dots_are_escaped(): void {
        $this->mock_prepare_matches_options( [
            'zalomeni_abbreviations'      => 'on',
            'zalomeni_abbreviations_list' => 'např., tj., tzv.',
        ] );

        $method = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $method->setAccessible( true );
        $matches = $method->invoke( null );

        $this->assertArrayHasKey( 'words', $matches );
        // The dot should be escaped — "např." should not match "napřX"
        $result = @preg_match( $matches['words'], ' např. ' );
        $this->assertNotFalse( $result );
        $this->assertSame( 1, $result, 'Abbreviation with dot should match literally' );
    }

    public function test_empty_list_items_are_filtered_out(): void {
        $this->mock_prepare_matches_options( [
            'zalomeni_prepositions'      => 'on',
            'zalomeni_prepositions_list' => 'k, , s, , v, z',
        ] );

        $method = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $method->setAccessible( true );
        $matches = $method->invoke( null );

        $this->assertArrayHasKey( 'words', $matches );
        // Empty alternation branches (||) must not appear — they match everything
        $this->assertStringNotContainsString( '||', $matches['words'],
            'Empty list items should be filtered — || matches every position' );
        // Should still match valid prepositions
        $this->assertSame( 1, preg_match( $matches['words'], ' k mostu' ) );
    }

    public function test_percent_unit_is_properly_escaped(): void {
        $this->mock_prepare_matches_options( [
            'zalomeni_between_number_and_unit'      => 'on',
            'zalomeni_between_number_and_unit_list' => '%',
        ] );

        $method = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $method->setAccessible( true );
        $matches = $method->invoke( null );

        $this->assertArrayHasKey( 'units', $matches );
        $result = @preg_match( $matches['units'], '20 % sleva' );
        $this->assertNotFalse( $result, 'Regex with % is invalid' );
        $this->assertSame( 1, $result );
    }

    // =========================================================================
    // 4. Custom terms — incomplete escaping
    // =========================================================================

    public function test_custom_terms_with_regex_metacharacters_are_safe(): void {
        $this->mock_prepare_matches_options( [
            'zalomeni_custom_terms' => "Windows [10]+\nTest.*evil",
        ] );

        $method = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $method->setAccessible( true );
        $matches = $method->invoke( null );

        foreach ( $matches as $key => $pattern ) {
            if ( strpos( $key, 'customterm' ) === 0 ) {
                $result = @preg_match( $pattern, 'test input' );
                $this->assertNotFalse( $result,
                    "Custom term pattern '$key' is invalid regex: $pattern" );
            }
        }
    }

    public function test_custom_terms_preserve_backslash_d(): void {
        $this->mock_prepare_matches_options( [
            'zalomeni_custom_terms' => "iPhone \\d",
        ] );

        $method = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $method->setAccessible( true );
        $matches = $method->invoke( null );

        $this->assertArrayHasKey( 'customterm1', $matches );
        $this->assertSame( 1, preg_match( $matches['customterm1'], 'iPhone 5' ) );
        $this->assertSame( 0, preg_match( $matches['customterm1'], 'iPhone X' ) );
    }

    // =========================================================================
    // 5. preg_replace error handling — texturize must not return null
    // =========================================================================

    public function test_texturize_returns_original_text_on_invalid_regex(): void {
        $original_text = 'Hello world, this is a test.';

        WP_Mock::userFunction( 'get_option' )
            ->with( 'zalomeni_matches' )
            ->andReturn( [ 'broken' => '/(?:(?:(?:a]/' ] );
        WP_Mock::userFunction( 'get_option' )
            ->with( 'zalomeni_replacements' )
            ->andReturn( [ 'broken' => '$1' ] );
        WP_Mock::userFunction( 'apply_filters' )
            ->andReturnUsing( function ( $tag, $value ) {
                return $value;
            } );

        $result = @Zalomeni::texturize( $original_text );

        $this->assertNotNull( $result, 'texturize() returned null on broken regex' );
        $this->assertSame( $original_text, $result,
            'texturize() must return original text when preg_replace fails' );
    }

    public function test_texturize_returns_original_on_empty_matches(): void {
        WP_Mock::userFunction( 'get_option' )
            ->with( 'zalomeni_matches' )
            ->andReturn( '' );

        $text = 'Some text with v preposition';
        $result = Zalomeni::texturize( $text );
        $this->assertSame( $text, $result );
    }

    // =========================================================================
    // 6. ReDoS resistance
    // =========================================================================

    public function test_no_catastrophic_backtracking_with_large_input(): void {
        // Use actual prepare_matches/prepare_replacements output (not hardcoded patterns)
        $this->mock_prepare_matches_options( [
            'zalomeni_prepositions'                 => 'on',
            'zalomeni_prepositions_list'             => 'k, s, v, z',
            'zalomeni_conjunctions'                  => 'on',
            'zalomeni_conjunctions_list'              => 'a, i, o, u',
            'zalomeni_abbreviations'                 => 'on',
            'zalomeni_abbreviations_list'             => 'např., tj., tzv.',
            'zalomeni_between_number_and_unit'       => 'on',
            'zalomeni_between_number_and_unit_list'  => 'm, m², l, kg, h, °C, Kč, lidí, dní, %',
            'zalomeni_space_between_numbers'         => 'on',
            'zalomeni_spaces_in_scales'              => 'on',
            'zalomeni_space_after_ordered_number'    => 'on',
            'zalomeni_custom_terms'                  => "Formule 1\nWindows \\d\niPhone \\d",
        ] );

        $prepare_matches = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $prepare_matches->setAccessible( true );
        $matches = $prepare_matches->invoke( null );

        $prepare_replacements = new ReflectionMethod( 'Zalomeni', 'prepare_replacements' );
        $prepare_replacements->setAccessible( true );
        $replacements = $prepare_replacements->invoke( null );

        // Reset mocks — WP_Mock stacks rather than replaces
        WP_Mock::tearDown();
        WP_Mock::setUp();

        WP_Mock::userFunction( 'get_option' )->andReturnUsing(
            function ( $key ) use ( $matches, $replacements ) {
                if ( $key === 'zalomeni_matches' ) return $matches;
                if ( $key === 'zalomeni_replacements' ) return $replacements;
                return '';
            }
        );
        WP_Mock::userFunction( 'apply_filters' )
            ->andReturnUsing( function ( $tag, $value ) {
                return $value;
            } );

        $large_input = str_repeat( 'Dne 1. ledna v Praze k mostu s bratrem např. 5 m za 100 Kč a Formule 1 ', 1000 );

        $start = microtime( true );
        $result = Zalomeni::texturize( $large_input );
        $elapsed = microtime( true ) - $start;

        $this->assertNotNull( $result );
        $this->assertLessThan( 2.0, $elapsed,
            'texturize() took too long — possible catastrophic backtracking' );
        // Verify it actually did replacements
        $this->assertStringContainsString( 'v&nbsp;Praze', $result );
        $this->assertStringContainsString( 'Formule&nbsp;1', $result );
    }
}
