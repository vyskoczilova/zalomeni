<?php

use WP_Mock\Tools\TestCase;

/**
 * Regression tests for the matches/replacements array desync.
 *
 * preg_replace() pairs pattern and replacement arrays by POSITION, ignoring
 * keys. prepare_matches() skips a pattern when its word list is empty, but
 * prepare_replacements() used to add the replacement whenever the checkbox
 * was on — shifting every following replacement onto the wrong pattern and
 * corrupting front-end output ("5 kg" → "5kg&nbsp;").
 */
class MatchesReplacementsSyncTest extends TestCase {

    public function setUp(): void {
        WP_Mock::setUp();
    }

    public function tearDown(): void {
        WP_Mock::tearDown();
    }

    private static function default_options(): array {
        return [
            'zalomeni_prepositions'                 => '',
            'zalomeni_prepositions_list'            => '',
            'zalomeni_conjunctions'                 => '',
            'zalomeni_conjunctions_list'            => '',
            'zalomeni_abbreviations'                => '',
            'zalomeni_abbreviations_list'           => '',
            'zalomeni_between_number_and_unit'      => '',
            'zalomeni_between_number_and_unit_list' => '',
            'zalomeni_space_between_numbers'        => '',
            'zalomeni_spaces_in_scales'             => '',
            'zalomeni_space_after_ordered_number'   => '',
            'zalomeni_custom_terms'                 => '',
        ];
    }

    /**
     * Run update_matches_and_replacements() with the given options and
     * capture what it writes to the two compiled options.
     */
    private function compile( array $overrides ): array {
        $options = array_merge( self::default_options(), $overrides );
        $written = [];

        WP_Mock::userFunction( 'get_option' )->andReturnUsing(
            function ( $key, $default = '' ) use ( $options ) {
                return $options[ $key ] ?? $default;
            }
        );
        WP_Mock::userFunction( 'update_option' )->andReturnUsing(
            function ( $key, $value ) use ( &$written ) {
                $written[ $key ] = $value;
                return true;
            }
        );

        Zalomeni::update_matches_and_replacements();

        return [ $written['zalomeni_matches'], $written['zalomeni_replacements'] ];
    }

    public function keys_stay_in_sync_provider(): array {
        return [
            'prepositions on, list emptied' => [ [
                'zalomeni_prepositions'      => 'on',
                'zalomeni_prepositions_list' => '',
            ] ],
            'prepositions on, list only separators' => [ [
                'zalomeni_prepositions'      => 'on',
                'zalomeni_prepositions_list' => ' , , ',
            ] ],
            'units on, list emptied' => [ [
                'zalomeni_between_number_and_unit'      => 'on',
                'zalomeni_between_number_and_unit_list' => '',
            ] ],
            'words empty but units and numbers active' => [ [
                'zalomeni_prepositions'                 => 'on',
                'zalomeni_prepositions_list'            => '',
                'zalomeni_between_number_and_unit'      => 'on',
                'zalomeni_between_number_and_unit_list' => 'kg, m',
                'zalomeni_space_between_numbers'        => 'on',
            ] ],
            'everything on with defaults' => [ [
                'zalomeni_prepositions'                 => 'on',
                'zalomeni_prepositions_list'            => Zalomeni::default_prepositions_list,
                'zalomeni_conjunctions'                 => 'on',
                'zalomeni_conjunctions_list'            => Zalomeni::default_conjunctions_list,
                'zalomeni_abbreviations'                => 'on',
                'zalomeni_abbreviations_list'           => Zalomeni::default_abbreviations_list,
                'zalomeni_between_number_and_unit'      => 'on',
                'zalomeni_between_number_and_unit_list' => Zalomeni::default_between_number_and_unit_list,
                'zalomeni_space_between_numbers'        => 'on',
                'zalomeni_spaces_in_scales'             => 'on',
                'zalomeni_space_after_ordered_number'   => 'on',
                'zalomeni_custom_terms'                 => Zalomeni::default_custom_terms,
            ] ],
        ];
    }

    /**
     * @dataProvider keys_stay_in_sync_provider
     */
    public function test_compiled_arrays_have_identical_keys( array $overrides ): void {
        list( $matches, $replacements ) = $this->compile( $overrides );

        $this->assertSame( array_keys( $matches ), array_keys( $replacements ),
            'zalomeni_matches and zalomeni_replacements must stay positionally paired' );
    }

    public function test_empty_word_list_does_not_corrupt_output(): void {
        // Prepositions checkbox on but list emptied by the user: the units
        // pattern must NOT inherit the words replacement.
        list( $matches, $replacements ) = $this->compile( [
            'zalomeni_prepositions'                 => 'on',
            'zalomeni_prepositions_list'            => '',
            'zalomeni_between_number_and_unit'      => 'on',
            'zalomeni_between_number_and_unit_list' => 'kg, m',
            'zalomeni_space_between_numbers'        => 'on',
        ] );

        // Re-mock get_option so texturize() reads the compiled arrays.
        WP_Mock::tearDown();
        WP_Mock::setUp();
        WP_Mock::userFunction( 'get_option' )->andReturnUsing(
            function ( $key ) use ( $matches, $replacements ) {
                if ( $key === 'zalomeni_matches' ) return $matches;
                if ( $key === 'zalomeni_replacements' ) return $replacements;
                return '';
            }
        );
        WP_Mock::userFunction( 'apply_filters' )->andReturnUsing(
            function ( $tag, $value ) {
                return $value;
            }
        );

        $result = Zalomeni::texturize( 'Váží 5 kg a stojí 1 000 Kč.' );

        $this->assertStringContainsString( '5&nbsp;kg', $result );
        $this->assertStringContainsString( '1&nbsp;000', $result );
        $this->assertStringNotContainsString( '5kg', $result,
            'units pattern was paired with the words replacement — arrays desynced' );
    }
}
