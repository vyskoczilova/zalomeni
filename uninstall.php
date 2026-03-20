<?php
/**
 * Uninstall handler for Zalomení plugin.
 *
 * Removes all plugin options from the database when the plugin is deleted
 * via the WordPress admin interface.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$options = array(
  'zalomeni_version',
  'zalomeni_prepositions',
  'zalomeni_prepositions_list',
  'zalomeni_conjunctions',
  'zalomeni_conjunctions_list',
  'zalomeni_abbreviations',
  'zalomeni_abbreviations_list',
  'zalomeni_between_number_and_unit',
  'zalomeni_between_number_and_unit_list',
  'zalomeni_space_between_numbers',
  'zalomeni_space_after_ordered_number',
  'zalomeni_spaces_in_scales',
  'zalomeni_custom_terms',
  'zalomeni_matches',
  'zalomeni_replacements',
);

foreach ( $options as $option ) {
  delete_option( $option );
}
