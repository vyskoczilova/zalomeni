<?php
/**
 * Uninstall handler for Zalomení plugin.
 *
 * Removes all plugin options from the database when the plugin is deleted
 * via the WordPress admin interface. On multisite, options are removed
 * from every site, since settings are stored per-site.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

function zalomeni_delete_options() {
  $zalomeni_options = array(
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
    'zalomeni_options', // legacy pre-1.3 combined option
  );

  foreach ( $zalomeni_options as $zalomeni_option ) {
    delete_option( $zalomeni_option );
  }
}

if ( is_multisite() ) {
  foreach ( get_sites( array( 'fields' => 'ids', 'number' => 0 ) ) as $zalomeni_site_id ) {
    switch_to_blog( $zalomeni_site_id );
    zalomeni_delete_options();
    restore_current_blog();
  }
} else {
  zalomeni_delete_options();
}
