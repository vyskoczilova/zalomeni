<?php

use WP_Mock\Tools\TestCase;

/**
 * Functional tests for the texturize pipeline.
 *
 * These tests exercise prepare_matches() → prepare_replacements() → texturize()
 * end-to-end to catch any breakage from security hardening.
 */
class TexturizeTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
        // texturize() caches compiled patterns per request; each test mocks
        // different options, so the cache must not leak between tests.
        Zalomeni::flush_pattern_cache();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    /**
     * Build matches/replacements arrays from options using reflection.
     * Does not use WP_Mock — calls prepare_matches/prepare_replacements
     * with a temporary get_option mock, then tears it down.
     */
    private static function build_patterns( array $options ): array {
        // Temporarily define get_option as a plain PHP function for reflection calls
        // We can't use WP_Mock here since we need to tear it down cleanly
        WP_Mock::userFunction( 'get_option' )->andReturnUsing(
            function ( $key, $default = '' ) use ( $options ) {
                return $options[ $key ] ?? $default;
            }
        );
        WP_Mock::userFunction( 'update_option' );

        $prepare_matches = new ReflectionMethod( 'Zalomeni', 'prepare_matches' );
        $prepare_matches->setAccessible( true );
        $matches = $prepare_matches->invoke( null );

        $prepare_replacements = new ReflectionMethod( 'Zalomeni', 'prepare_replacements' );
        $prepare_replacements->setAccessible( true );
        $replacements = $prepare_replacements->invoke( null );

        return [ $matches, $replacements ];
    }

    /**
     * Helper: mock options, build matches/replacements, run texturize.
     *
     * Uses tearDown/setUp between phases because WP_Mock doesn't support
     * replacing an existing mock — a second userFunction('get_option')
     * stacks on top rather than overriding.
     */
    private function texturize_with_options( string $text, array $overrides = [] ): string {
        $defaults = [
            'zalomeni_prepositions'                 => Zalomeni::default_prepositions,
            'zalomeni_prepositions_list'             => Zalomeni::default_prepositions_list,
            'zalomeni_conjunctions'                  => Zalomeni::default_conjunctions,
            'zalomeni_conjunctions_list'              => Zalomeni::default_conjunctions_list,
            'zalomeni_abbreviations'                 => Zalomeni::default_abbreviations,
            'zalomeni_abbreviations_list'             => Zalomeni::default_abbreviations_list,
            'zalomeni_between_number_and_unit'       => Zalomeni::default_between_number_and_unit,
            'zalomeni_between_number_and_unit_list'  => Zalomeni::default_between_number_and_unit_list,
            'zalomeni_space_between_numbers'         => Zalomeni::default_space_between_numbers,
            'zalomeni_spaces_in_scales'              => Zalomeni::default_spaces_in_scales,
            'zalomeni_space_after_ordered_number'    => Zalomeni::default_space_after_ordered_number,
            'zalomeni_custom_terms'                  => Zalomeni::default_custom_terms,
        ];
        $options = array_merge( $defaults, $overrides );

        // Phase 1: build patterns (needs get_option mock for individual options)
        list( $matches, $replacements ) = self::build_patterns( $options );

        // Phase 2: reset mocks and set up for texturize (needs get_option
        // to return compiled patterns). WP_Mock requires tearDown/setUp
        // to replace an existing mock for the same function.
        WP_Mock::tearDown();
        WP_Mock::setUp();

        WP_Mock::userFunction( 'get_option' )->andReturnUsing(
            function ( $key, $default = '' ) use ( $matches, $replacements ) {
                if ( $key === 'zalomeni_matches' ) return $matches;
                if ( $key === 'zalomeni_replacements' ) return $replacements;
                return $default;
            }
        );
        WP_Mock::userFunction( 'apply_filters' )->andReturnUsing(
            function ( $tag, $value ) {
                return $value;
            }
        );

        return Zalomeni::texturize( $text );
    }

    // =========================================================================
    // Prepositions (k, s, v, z)
    // =========================================================================

    public function test_preposition_v_gets_nbsp(): void {
        $result = $this->texturize_with_options( 'Bydlím v Praze a pracuji v Brně.' );
        $this->assertStringContainsString( 'v&nbsp;Praze', $result );
        $this->assertStringContainsString( 'v&nbsp;Brně', $result );
    }

    public function test_preposition_k_gets_nbsp(): void {
        $result = $this->texturize_with_options( 'Šel k mostu a pak k řece.' );
        $this->assertStringContainsString( 'k&nbsp;mostu', $result );
        $this->assertStringContainsString( 'k&nbsp;řece', $result );
    }

    public function test_preposition_s_gets_nbsp(): void {
        $result = $this->texturize_with_options( 'Jdu s bratrem do kina.' );
        $this->assertStringContainsString( 's&nbsp;bratrem', $result );
    }

    public function test_preposition_z_gets_nbsp(): void {
        $result = $this->texturize_with_options( 'Přijel z nádraží.' );
        $this->assertStringContainsString( 'z&nbsp;nádraží', $result );
    }

    public function test_preposition_case_insensitive(): void {
        // The regex requires a preceding boundary (space, ;, newline, etc.)
        // At absolute start of string, "V" has no preceding boundary so it won't match
        $result = $this->texturize_with_options( 'Jdu V lese. K večeru se ochladilo.' );
        $this->assertStringContainsString( 'V&nbsp;lese', $result );
        $this->assertStringContainsString( 'K&nbsp;večeru', $result );
    }

    public function test_preposition_at_start_of_text_no_match(): void {
        $result = $this->texturize_with_options( 'V lese' );
        // No preceding boundary at absolute start — regex does not match
        $this->assertSame( 'V lese', $result );
    }

    public function test_preposition_after_semicolon(): void {
        $result = $this->texturize_with_options( 'text; v lese' );
        $this->assertStringContainsString( 'v&nbsp;lese', $result );
    }

    public function test_word_containing_v_not_affected(): void {
        $result = $this->texturize_with_options( 'Vypadá to dobře.' );
        // "Vypadá" should NOT be split — v is only replaced as a standalone word
        $this->assertStringContainsString( 'Vypadá', $result );
    }

    public function test_prepositions_disabled(): void {
        $result = $this->texturize_with_options(
            'Bydlím v Praze.',
            [ 'zalomeni_prepositions' => '' ]
        );
        $this->assertStringContainsString( 'v Praze', $result );
        $this->assertStringNotContainsString( 'v&nbsp;Praze', $result );
    }

    // =========================================================================
    // Conjunctions (a, i, o, u) — disabled by default
    // =========================================================================

    public function test_conjunctions_disabled_by_default(): void {
        $result = $this->texturize_with_options( 'Petr a Pavel i Jakub.' );
        $this->assertStringNotContainsString( 'a&nbsp;Pavel', $result );
    }

    public function test_conjunctions_enabled(): void {
        $result = $this->texturize_with_options(
            'Petr a Pavel i Jakub.',
            [ 'zalomeni_conjunctions' => 'on' ]
        );
        $this->assertStringContainsString( 'a&nbsp;Pavel', $result );
        $this->assertStringContainsString( 'i&nbsp;Jakub', $result );
    }

    // =========================================================================
    // Abbreviations — disabled by default
    // =========================================================================

    public function test_abbreviations_disabled_by_default(): void {
        $result = $this->texturize_with_options( 'To je např. výborné.' );
        $this->assertStringNotContainsString( 'např.&nbsp;', $result );
    }

    public function test_abbreviations_enabled(): void {
        $result = $this->texturize_with_options(
            'To je např. výborné a tj. důležité.',
            [ 'zalomeni_abbreviations' => 'on' ]
        );
        $this->assertStringContainsString( 'např.&nbsp;výborné', $result );
        $this->assertStringContainsString( 'tj.&nbsp;důležité', $result );
    }

    // =========================================================================
    // Number + unit (5 m, 10 kg, etc.)
    // =========================================================================

    public function test_number_and_unit_gets_nbsp(): void {
        $result = $this->texturize_with_options( 'Vzdálenost je 5 m a hmotnost 10 kg.' );
        $this->assertStringContainsString( '5&nbsp;m', $result );
        $this->assertStringContainsString( '10&nbsp;kg', $result );
    }

    public function test_number_and_currency(): void {
        $result = $this->texturize_with_options( 'Cena je 100 Kč za kus.' );
        $this->assertStringContainsString( '100&nbsp;Kč', $result );
    }

    public function test_number_and_percent(): void {
        $result = $this->texturize_with_options( 'Sleva 20 % na vše.' );
        $this->assertStringContainsString( '20&nbsp;%', $result );
    }

    public function test_number_and_temperature(): void {
        $result = $this->texturize_with_options( 'Venku je 25 °C dnes.' );
        $this->assertStringContainsString( '25&nbsp;°C', $result );
    }

    public function test_units_disabled(): void {
        $result = $this->texturize_with_options(
            'Vzdálenost je 5 m daleko.',
            [ 'zalomeni_between_number_and_unit' => '' ]
        );
        $this->assertStringContainsString( '5 m', $result );
        $this->assertStringNotContainsString( '5&nbsp;m', $result );
    }

    // =========================================================================
    // Space between numbers (800 123 456)
    // =========================================================================

    public function test_space_between_numbers(): void {
        $result = $this->texturize_with_options( 'Volej 800 123 456 hned.' );
        $this->assertStringContainsString( '800&nbsp;123&nbsp;456', $result );
    }

    public function test_space_between_numbers_disabled(): void {
        $result = $this->texturize_with_options(
            'Volej 800 123 hned.',
            [ 'zalomeni_space_between_numbers' => '' ]
        );
        $this->assertStringContainsString( '800 123', $result );
    }

    // =========================================================================
    // Scales / ratios (1 : 50 000)
    // =========================================================================

    public function test_scales(): void {
        $result = $this->texturize_with_options( 'Mapa 1 : 50 000 je přesná.' );
        $this->assertStringContainsString( '1&nbsp;:&nbsp;50', $result );
    }

    public function test_scales_disabled(): void {
        $result = $this->texturize_with_options(
            'Mapa 1 : 50 je přesná.',
            [ 'zalomeni_spaces_in_scales' => '' ]
        );
        $this->assertStringContainsString( '1 : 50', $result );
    }

    // =========================================================================
    // Ordinal numbers (1. ledna, 3. svazek)
    // =========================================================================

    public function test_ordinal_number(): void {
        $result = $this->texturize_with_options( 'Dne 1. ledna se slaví.' );
        $this->assertStringContainsString( '1.&nbsp;ledna', $result );
    }

    public function test_ordinal_number_with_digit(): void {
        $result = $this->texturize_with_options( 'Kapitola 5. 3 je důležitá.' );
        $this->assertStringContainsString( '5.&nbsp;3', $result );
    }

    public function test_ordinal_disabled(): void {
        $result = $this->texturize_with_options(
            'Dne 1. ledna se slaví.',
            [ 'zalomeni_space_after_ordered_number' => '' ]
        );
        $this->assertStringContainsString( '1. ledna', $result );
        $this->assertStringNotContainsString( '1.&nbsp;ledna', $result );
    }

    // =========================================================================
    // Custom terms (Formule 1, Windows \d, iPhone \d, etc.)
    // =========================================================================

    public function test_custom_term_formule_1(): void {
        $result = $this->texturize_with_options( 'Závod Formule 1 je rychlý.' );
        $this->assertStringContainsString( 'Formule&nbsp;1', $result );
    }

    public function test_custom_term_windows_digit(): void {
        $result = $this->texturize_with_options( 'Nainstaluj Windows 7 prosím.' );
        $this->assertStringContainsString( 'Windows&nbsp;7', $result );
    }

    public function test_custom_term_iphone_digit(): void {
        $result = $this->texturize_with_options( 'Mám iPhone 6 v kapse.' );
        $this->assertStringContainsString( 'iPhone&nbsp;6', $result );
    }

    public function test_custom_term_playstation_digit(): void {
        $result = $this->texturize_with_options( 'Hraji na PlayStation 5 dnes.' );
        $this->assertStringContainsString( 'PlayStation&nbsp;5', $result );
    }

    public function test_custom_term_wii_u(): void {
        $result = $this->texturize_with_options( 'Koupil jsem Wii U včera.' );
        $this->assertStringContainsString( 'Wii&nbsp;U', $result );
    }

    public function test_custom_term_xbox_360(): void {
        $result = $this->texturize_with_options( 'Starý XBox 360 ještě funguje.' );
        $this->assertStringContainsString( 'XBox&nbsp;360', $result );
    }

    public function test_custom_terms_disabled(): void {
        $result = $this->texturize_with_options(
            'Závod Formule 1 je rychlý.',
            [ 'zalomeni_custom_terms' => '' ]
        );
        $this->assertStringContainsString( 'Formule 1', $result );
        $this->assertStringNotContainsString( 'Formule&nbsp;1', $result );
    }

    // =========================================================================
    // HTML tag handling — must not modify content inside tags
    // =========================================================================

    public function test_does_not_modify_inside_html_tags(): void {
        $result = $this->texturize_with_options( '<a href="v lese">text v lese</a>' );
        // The attribute "v lese" inside the tag must NOT be changed
        $this->assertStringContainsString( 'href="v lese"', $result );
        // But the text content should be changed (v after space boundary)
        $this->assertStringContainsString( 'v&nbsp;lese', $result );
    }

    public function test_does_not_modify_inside_pre_tags(): void {
        $result = $this->texturize_with_options( '<pre>v lese k mostu</pre> v lese' );
        $this->assertStringContainsString( '<pre>v lese k mostu</pre>', $result );
        $this->assertStringContainsString( 'v&nbsp;lese', $result );
    }

    public function test_does_not_modify_inside_code_tags(): void {
        $result = $this->texturize_with_options( '<code>v lese</code>' );
        $this->assertStringContainsString( '<code>v lese</code>', $result );
    }

    public function test_does_not_modify_inside_script_tags(): void {
        $result = $this->texturize_with_options( '<script>var v = "v lese";</script>' );
        $this->assertStringContainsString( '<script>var v = "v lese";</script>', $result );
    }

    // =========================================================================
    // Shortcode handling
    // =========================================================================

    public function test_does_not_modify_inside_code_shortcode(): void {
        $result = $this->texturize_with_options( '[code]v lese k mostu[/code]' );
        $this->assertStringContainsString( '[code]v lese k mostu[/code]', $result );
    }

    // =========================================================================
    // Combined / real-world scenarios
    // =========================================================================

    public function test_full_czech_paragraph(): void {
        $input = 'Dne 1. ledna jsem šel v Praze k mostu s bratrem z nádraží. Vzdálenost byla 5 km a teplota 3 °C. Volej 800 123 456.';
        $result = $this->texturize_with_options( $input );

        $this->assertStringContainsString( '1.&nbsp;ledna', $result );
        $this->assertStringContainsString( 'v&nbsp;Praze', $result );
        $this->assertStringContainsString( 'k&nbsp;mostu', $result );
        $this->assertStringContainsString( 's&nbsp;bratrem', $result );
        $this->assertStringContainsString( 'z&nbsp;nádraží', $result );
        $this->assertStringContainsString( '3&nbsp;°C', $result );
        $this->assertStringContainsString( '800&nbsp;123&nbsp;456', $result );
    }

    public function test_mixed_html_and_text(): void {
        $input = '<p>Bydlím v <strong>Praze</strong> a mám iPhone 6 za 100 Kč.</p>';
        $result = $this->texturize_with_options( $input );

        $this->assertStringContainsString( 'v&nbsp;<strong>', $result );
        $this->assertStringContainsString( 'iPhone&nbsp;6', $result );
        $this->assertStringContainsString( '100&nbsp;Kč', $result );
    }

    public function test_empty_string_returns_empty(): void {
        $result = $this->texturize_with_options( '' );
        $this->assertSame( '', $result );
    }

    public function test_text_without_matches_unchanged(): void {
        $result = $this->texturize_with_options( 'Žádné předložky zde nejsou.' );
        $this->assertSame( 'Žádné předložky zde nejsou.', $result );
    }

    public function test_all_features_disabled_returns_original(): void {
        $input = 'Šel v lese k mostu 5 m daleko dne 1. ledna.';
        $result = $this->texturize_with_options( $input, [
            'zalomeni_prepositions'              => '',
            'zalomeni_conjunctions'              => '',
            'zalomeni_abbreviations'             => '',
            'zalomeni_between_number_and_unit'   => '',
            'zalomeni_space_between_numbers'     => '',
            'zalomeni_spaces_in_scales'          => '',
            'zalomeni_space_after_ordered_number' => '',
            'zalomeni_custom_terms'              => '',
        ] );
        $this->assertSame( $input, $result );
    }

    // =========================================================================
    // Edge cases
    // =========================================================================

    public function test_multiple_prepositions_in_sequence(): void {
        $result = $this->texturize_with_options( 'Jdi k v z lesa.' );
        // Each standalone preposition followed by space should get nbsp
        $this->assertStringContainsString( 'k&nbsp;', $result );
    }

    public function test_nbsp_entities_not_doubled(): void {
        // Already has &nbsp; — should not produce &amp;nbsp; or &&nbsp;
        $result = $this->texturize_with_options( 'v&nbsp;lese' );
        $this->assertStringNotContainsString( '&amp;nbsp;', $result );
    }

    public function test_custom_term_with_user_defined_list(): void {
        $result = $this->texturize_with_options(
            'Běží na Ubuntu 22 doma.',
            [ 'zalomeni_custom_terms' => "Ubuntu \\d\\d" ]
        );
        $this->assertStringContainsString( 'Ubuntu&nbsp;22', $result );
    }
}
