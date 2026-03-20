<?php
/**
 * Plugin Name: Zalomení
 * Plugin URI:  https://wordpress.org/plugins/zalomeni/
 * Description: Puts non-breakable space after one-letter Czech prepositions like 'k', 's', 'v' or 'z'.
 * Version:     1.6.0
 * Author:      Karolína Vyskočiová
 * Author URI:  https://kybernaut.cz
 * Text Domain: zalomeni
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

defined( 'ABSPATH' ) || exit;

class Zalomeni {
  const version = '1.6.0';

  public function __construct() {
    register_activation_hook(__FILE__, array(__CLASS__, 'activate'));
    if (is_admin()) {
      add_action('admin_init', array($this, 'admin_init'));
    } else {
      add_action('init', array($this, 'add_filters'));
    }
  }

  public function add_filters() {
    $zalomeni_matches = get_option('zalomeni_matches');
    if (!empty($zalomeni_matches)) {
      $filters = array('comment_author', 'term_name', 'link_name', 'link_description', 'link_notes', 'bloginfo', 'wp_title', 'widget_title', 'term_description', 'the_title', 'the_content', 'the_excerpt', 'comment_text', 'single_post_title', 'list_cats');
      $filters = array_combine($filters, $filters);
      $filters = apply_filters('zalomeni_filtry', $filters);
      foreach ($filters as $filter) {
        add_filter($filter, array(__CLASS__, 'texturize'));
      }
    }
  }

  public static function activate() {
    $required_php_version = '7.4';
    if ( version_compare( phpversion(), $required_php_version, '<' ) ) {
      wp_die(
        esc_html( str_replace(
          array( '%1', '%2' ),
          array( $required_php_version, phpversion() ),
          __( 'Plugin Zalomení vyžaduje PHP verze %1 nebo vyšší. Na tomto webu je nainstalováno PHP verze %2', 'zalomeni' )
        ) )
      );
    }
    self::add_options();
  }
  
  const default_prepositions                 = 'on';
  const default_prepositions_list            = 'k, s, v, z';
  const default_conjunctions                 = '';
  const default_conjunctions_list            = 'a, i, o, u';
  const default_abbreviations                = '';
  const default_abbreviations_list           = 'cca., č., čís., čj., čp., fa, fě, fy, kupř., mj., např., p., pí, popř., př., přib., přibl., sl., str., sv., tj., tzn., tzv., zvl.';
  const default_between_number_and_unit      = 'on';
  const default_between_number_and_unit_list = 'm, m², l, kg, h, °C, Kč, lidí, dní, %';
  const default_spaces_in_scales             = 'on';
  const default_space_between_numbers        = 'on';
  const default_space_after_ordered_number   = 'on';
  const default_custom_terms                 = "Formule 1\nWindows \d\niPhone \d\niPhone S\d\niPad \d\nWii U\nPlayStation \d\nXBox 360";
  
  private static function get_default($key) {
    static $defaults = null;
    if ($defaults === null) {
      $defaults = array(
        'prepositions'                 => self::default_prepositions,
        'prepositions_list'            => self::default_prepositions_list,
        'conjunctions'                 => self::default_conjunctions,
        'conjunctions_list'            => self::default_conjunctions_list,
        'abbreviations'                => self::default_abbreviations,
        'abbreviations_list'           => self::default_abbreviations_list,
        'between_number_and_unit'      => self::default_between_number_and_unit,
        'between_number_and_unit_list' => self::default_between_number_and_unit_list,
        'spaces_in_scales'             => self::default_spaces_in_scales,
        'space_between_numbers'        => self::default_space_between_numbers,
        'space_after_ordered_number'   => self::default_space_after_ordered_number,
        'custom_terms'                 => self::default_custom_terms,
      );
    }
    return isset($defaults[$key]) ? $defaults[$key] : '';
  }

  private static function add_options() {
    add_option('zalomeni_version', self::version);

    add_option('zalomeni_prepositions',                 self::default_prepositions);
    add_option('zalomeni_prepositions_list',            self::default_prepositions_list);
    add_option('zalomeni_conjunctions',                 self::default_conjunctions);
    add_option('zalomeni_conjunctions_list',            self::default_conjunctions_list);
    add_option('zalomeni_abbreviations',                self::default_abbreviations);
    add_option('zalomeni_abbreviations_list',           self::default_abbreviations_list);
    add_option('zalomeni_between_number_and_unit',      self::default_between_number_and_unit);
    add_option('zalomeni_between_number_and_unit_list', self::default_between_number_and_unit_list);
    add_option('zalomeni_spaces_in_scales',             self::default_spaces_in_scales);
    add_option('zalomeni_space_between_numbers',        self::default_space_between_numbers);
    add_option('zalomeni_space_after_ordered_number',   self::default_space_after_ordered_number);
    add_option('zalomeni_custom_terms',                 self::default_custom_terms);

    self::update_matches_and_replacements();
  }

  private function update_plugin_version() {
    $registered_version = get_option('zalomeni_version', '0');
    if ($registered_version === '0') return;

    if (version_compare($registered_version, self::version, '<')) {
      if (version_compare($registered_version, '1.3', '<')) {
        $old_options = get_option('zalomeni_options');
        if (is_array($old_options)) {
          update_option('zalomeni_prepositions',      $old_options['zalomeni_prepositions']);
          update_option('zalomeni_prepositions_list', $old_options['zalomeni_prepositions_list']);
          update_option('zalomeni_conjunctions',      $old_options['zalomeni_conjunctions']);
          update_option('zalomeni_conjunctions_list', $old_options['zalomeni_conjunctions_list']);
          if (!version_compare($registered_version, '1.1', '<')) {
            // these options were introduced in version 1.1
            update_option('zalomeni_abbreviations',         $old_options['zalomeni_abbreviations']);
            update_option('zalomeni_abbreviations_list',    $old_options['zalomeni_abbreviations_list']);
            update_option('zalomeni_space_between_numbers', $old_options['zalomeni_numbers']);
          }
          delete_option('zalomeni_options');
        }
      }

      self::add_options();
      update_option('zalomeni_version', self::version);
    }
  }

  private static $this_plugin;
  public function add_settings_to_plugin_actions($links, $file) {
    if (!self::$this_plugin) {
      include_once(ABSPATH . 'wp-admin/includes/plugin.php');
      self::$this_plugin = plugin_basename(__FILE__);
    }
    if ($file === self::$this_plugin) {
      $settings_link = '<a href="options-reading.php#zalomeni_options_desc">' . __('Settings', 'zalomeni') . '</a>';
      array_unshift( $links, $settings_link ); // before other links
    }
    return $links;
  }

  public function admin_init() {
    $this->update_plugin_version();
    add_filter('plugin_action_links', array($this, 'add_settings_to_plugin_actions'), 10, 2);

    register_setting('reading', 'zalomeni_prepositions', array('sanitize_callback' => array(__CLASS__, 'sanitize_checkbox')));
    register_setting('reading', 'zalomeni_prepositions_list', array('sanitize_callback' => array(__CLASS__, 'sanitize_text_list')));
    register_setting('reading', 'zalomeni_conjunctions', array('sanitize_callback' => array(__CLASS__, 'sanitize_checkbox')));
    register_setting('reading', 'zalomeni_conjunctions_list', array('sanitize_callback' => array(__CLASS__, 'sanitize_text_list')));
    register_setting('reading', 'zalomeni_abbreviations', array('sanitize_callback' => array(__CLASS__, 'sanitize_checkbox')));
    register_setting('reading', 'zalomeni_abbreviations_list', array('sanitize_callback' => array(__CLASS__, 'sanitize_text_list')));
    register_setting('reading', 'zalomeni_between_number_and_unit', array('sanitize_callback' => array(__CLASS__, 'sanitize_checkbox')));
    register_setting('reading', 'zalomeni_between_number_and_unit_list', array('sanitize_callback' => array(__CLASS__, 'sanitize_text_list')));
    register_setting('reading', 'zalomeni_space_between_numbers', array('sanitize_callback' => array(__CLASS__, 'sanitize_checkbox')));
    register_setting('reading', 'zalomeni_space_after_ordered_number', array('sanitize_callback' => array(__CLASS__, 'sanitize_checkbox')));
    register_setting('reading', 'zalomeni_spaces_in_scales', array('sanitize_callback' => array(__CLASS__, 'sanitize_checkbox')));
    register_setting('reading', 'zalomeni_custom_terms', array('sanitize_callback' => array(__CLASS__, 'sanitize_custom_terms')));

    add_settings_section('zalomeni_section', self::texturize(__('Nevhodná slova a zalomení na konci řádku', 'zalomeni')), array( __CLASS__, 'settings_section_description' ), 'reading');

    add_settings_field('zalomeni_prepositions', __('Předložky', 'zalomeni'), array( __CLASS__, 'settings_field_checkbox' ), 'reading', 'zalomeni_section', array('option'=>'prepositions', 'description'=>__('Vkládat pevnou mezeru za následující předložky.', 'zalomeni'), 'toggle_list_read_only'=>true));
    add_settings_field('zalomeni_prepositions_list', '', array( __CLASS__, 'settings_field_textlist' ), 'reading', 'zalomeni_section', array('option'=>'prepositions', 'description'=>__('(oddělte jednotlivé předložky čárkou)', 'zalomeni')));
    add_settings_field('zalomeni_conjunctions', __('Spojky', 'zalomeni'), array( __CLASS__, 'settings_field_checkbox' ), 'reading', 'zalomeni_section', array('option'=>'conjunctions', 'description'=>__('Vkládat pevnou mezeru za následující spojky.', 'zalomeni'), 'toggle_list_read_only'=>true));
    add_settings_field('zalomeni_conjunctions_list', '', array( __CLASS__, 'settings_field_textlist' ), 'reading', 'zalomeni_section', array('option'=>'conjunctions', 'description'=>__('(oddělte jednotlivé spojky čárkou)', 'zalomeni')));
    add_settings_field('zalomeni_abbreviations', __('Zkratky', 'zalomeni'), array( __CLASS__, 'settings_field_checkbox' ), 'reading', 'zalomeni_section', array('option'=>'abbreviations', 'description'=>__('Vkládat pevnou mezeru za následující zkratky.', 'zalomeni'), 'toggle_list_read_only'=>true));
    add_settings_field('zalomeni_abbreviations_list', '', array( __CLASS__, 'settings_field_textlist' ), 'reading', 'zalomeni_section', array('option'=>'abbreviations', 'description'=>__('(oddělte jednotlivé zkratky čárkou)', 'zalomeni')));
    add_settings_field('zalomeni_between_number_and_unit', __('Jednotky a míry', 'zalomeni'), array( __CLASS__, 'settings_field_checkbox' ), 'reading', 'zalomeni_section', array('option'=>'between_number_and_unit', 'description'=>__('Vkládat pevnou mezeru mezi číslovku a jednotku míry (měrné jednotky, měna apod., např. <em>5 m</em> nebo <em>10 kg</em>).', 'zalomeni'), 'toggle_list_read_only'=>true));
    add_settings_field('zalomeni_between_number_and_unit_list', '', array( __CLASS__, 'settings_field_textlist' ), 'reading', 'zalomeni_section', array('option'=>'between_number_and_unit', 'description'=>__('(oddělte jednotlivé míry čárkou)', 'zalomeni')));
    add_settings_field('zalomeni_space_between_numbers', __('Mezery uprostřed čísel', 'zalomeni'), array( __CLASS__, 'settings_field_checkbox' ), 'reading', 'zalomeni_section', array('option'=>'space_between_numbers', 'description'=>__('Pokud jsou dvě čísla oddělena mezerou, předpokládat, že se jedná o formátování čísla pomocí mezery (např. telefonní číslo <em>800 123 456</em>) a nahrazovat mezeru pevnou mezerou, aby nedošlo k zalomení řádku uprostřed čísla.', 'zalomeni')));
    add_settings_field('zalomeni_space_after_ordered_number', __('Řadové číslovky', 'zalomeni'), array( __CLASS__, 'settings_field_checkbox' ), 'reading', 'zalomeni_section', array('option'=>'space_after_ordered_number', 'description'=>__('Zabránit zalomení řádku za řadovou číslovkou; díky tomu nedojde k zalomení řádku uprostřed data (např. <em>1. ledna</em>) a v podobných případech (<em>19. ročník</em>, <em>3. svazek</em>, <em>5. kapitola</em> apod.)', 'zalomeni')));
    add_settings_field('zalomeni_spaces_in_scales', __('Měřítka a poměry', 'zalomeni'), array( __CLASS__, 'settings_field_checkbox' ), 'reading', 'zalomeni_section', array('option'=>'spaces_in_scales', 'description'=>__('Pevné mezery v měřítkách a poměrech (např. <em>1 : 50 000</em>)', 'zalomeni')));
    add_settings_field('zalomeni_custom_terms', __('Vlastní výrazy', 'zalomeni'), array( __CLASS__, 'settings_field_custom_terms' ), 'reading', 'zalomeni_section');

    if ( empty( get_option('zalomeni_matches') ) ) {
      self::update_matches_and_replacements();
    }

    $this->add_update_option_hooks();
  }

  public static function settings_field_checkbox(array $args) {
    $option = sanitize_key( $args['option'] );
    echo(
      '<input type="checkbox" name="zalomeni_' . esc_attr( $option ) . '" id="zalomeni_' . esc_attr( $option ) . '" value="on" '
      . checked('on', get_option("zalomeni_" . $option, self::get_default($args['option'])), false)
      . (array_key_exists('toggle_list_read_only', $args) ? ' onchange="document.getElementById(\'zalomeni_' . esc_js( $option ) . '_list\').readOnly = this.checked?\'\':\'1\';"' : '')
      . ' /> '
      . wp_kses_post( self::texturize($args['description']) )
    );
  }

  public static function settings_field_textlist(array $args) {
    $option = sanitize_key( $args['option'] );
    echo(
      '<input type="text" name="zalomeni_' . esc_attr( $option ) . '_list" id="zalomeni_' . esc_attr( $option ) . '_list" class="regular-text" value="' . esc_attr( get_option('zalomeni_' . $option . '_list', self::get_default($args['option'] . '_list')) ) . '"'
       . ((get_option("zalomeni_" . $option, self::get_default($args['option'])) !== 'on') ? ' readonly="1"' : '')
      . ' /> '
      . wp_kses_post( self::texturize($args['description']) )
    );
  }

  public static function settings_field_custom_terms() {
    echo(
      wp_kses_post( self::texturize(__('Zde můžete uvést vlastní termíny, v nichž mají být mezery nahrazeny pevnými mezerami tak, aby nedošlo k zalomení uvnitř těchto výrazů. Uveďte vždy každý výraz na samostatný řádek; pokud je výraz složen z více jak dvou slov, tedy je v něm více jak jedna mezera, pak všechny mezery budou nahrazeny za pevné mezery. Lze použít výrazu \\d pro libovolnou číslici (pro pokročilé administrátory: algoritmus používá <a href="https://www.php.net/manual/en/reference.pcre.pattern.syntax.php" target="_blank">Perl Compatible Regular Expressions</a>, lze využít syntaxe této specifikace).', 'zalomeni')) )
      . '<p><textarea name="zalomeni_custom_terms" id="zalomeni_custom_terms" rows="10" cols="50" class="regular-text">'
      . esc_textarea( get_option('zalomeni_custom_terms', self::default_custom_terms) )
      . '</textarea></p>'
    );
  }

  public static function sanitize_checkbox($value) {
    return $value === 'on' ? 'on' : '';
  }

  public static function sanitize_text_list($value) {
    return sanitize_text_field($value);
  }

  public static function sanitize_custom_terms($value) {
    return sanitize_textarea_field($value);
  }

  private function add_update_option_hooks() {
    foreach (array('update_option_zalomeni_prepositions',
                   'update_option_zalomeni_prepositions_list',
                   'update_option_zalomeni_conjunctions',
                   'update_option_zalomeni_conjunctions_list',
                   'update_option_zalomeni_abbreviations',
                   'update_option_zalomeni_abbreviations_list',
                   'update_option_zalomeni_between_number_and_unit',
                   'update_option_zalomeni_between_number_and_unit_list',
                   'update_option_zalomeni_space_between_numbers',
                   'update_option_zalomeni_space_after_ordered_number',
                   'update_option_zalomeni_spaces_in_scales',
                   'update_option_zalomeni_custom_terms') as $i) {
      add_action($i, array(__CLASS__, 'update_matches_and_replacements'));
    }
  }

  public static function update_matches_and_replacements() {
    update_option('zalomeni_matches', self::prepare_matches());
    update_option('zalomeni_replacements', self::prepare_replacements());
  }

  private static function prepare_matches() {
    $return_array = array();

    $word_matches = '';
    foreach (array('prepositions', 'conjunctions', 'abbreviations') as $i) {
      if (get_option('zalomeni_'.$i, self::get_default($i)) === 'on') {
        $temp_array = explode(',', get_option('zalomeni_'.$i.'_list', self::get_default($i.'_list')));
        foreach ($temp_array as $j) {
          $j = preg_quote(mb_strtolower(trim($j), 'UTF-8'), '@');
          if ($j === '') continue;
          $word_matches .= ($word_matches === '' ? '' : '|') . $j;
        }
      }
    }
    if ($word_matches !== '') {
      $return_array['words'] = '@($|;| |&nbsp;|\(|\n)('.$word_matches.') @i';
    }

    $word_matches = '';
    if (get_option('zalomeni_between_number_and_unit', self::default_between_number_and_unit) === 'on') {
      $temp_array = explode(',', get_option('zalomeni_between_number_and_unit_list', self::default_between_number_and_unit_list));
      foreach ($temp_array as $j) {
        $j = preg_quote(mb_strtolower(trim($j), 'UTF-8'), '@');
        if ($j === '') continue;
        $word_matches .= ($word_matches === '' ? '' : '|') . $j;
      }
    }
    if ($word_matches !== '') {
      $return_array['units'] = '@(\d) ('.$word_matches.')(^|[;\.!:]| |&nbsp;|\?|\n|\)|<|\010|\013|$)@i';
    }

    if (get_option('zalomeni_space_between_numbers', self::default_space_between_numbers) === 'on') {
      $return_array['numbers'] = '@(\d) (\d)@i';
    }

    if (get_option('zalomeni_spaces_in_scales', self::default_spaces_in_scales) === 'on') {
      $return_array['scales'] = '@(\d) : (\d)@i';
    }

    if (get_option('zalomeni_space_after_ordered_number', self::default_space_after_ordered_number) === 'on') {
      $return_array['orders'] = '@(\d\.) ([0-9a-záčďéěíňóřšťúýž])@';
    }

    if (get_option('zalomeni_custom_terms', self::default_custom_terms) !== '') {
      $term_counter = 1;
      $custom_terms = explode(chr(10), str_replace(chr(13), '', get_option('zalomeni_custom_terms', self::default_custom_terms)));
      foreach ($custom_terms as $i) {
        if (strpos($i, ' ') !== false) {
          $term = '';
          $words_split = explode(' ', $i);
          foreach ($words_split as $j) {
            $escaped = preg_quote($j, '/');
            // Restore supported backslash sequences (\d, \w, \s, etc.)
            $escaped = preg_replace('/\\\\\\\\([dDwWsS])/', '\\\\$1', $escaped);
            $term .= ($term === '' ? '(' : ' (') . $escaped . ')';
          }
          $term = '/' . $term . '/i';
          $return_array['customterm' . $term_counter++] = $term;
        }
      }
    }

    return $return_array;
  }

  private static function prepare_replacements() {
    $return_array = array();

    foreach (array('prepositions', 'conjunctions', 'abbreviations') as $i) {
      if (get_option('zalomeni_'.$i, self::get_default($i)) === 'on') {
        $return_array['words'] = '$1$2&nbsp;';
        break;
      }
    }

    if (get_option('zalomeni_between_number_and_unit', self::default_between_number_and_unit) === 'on') {
      $return_array['units'] = '$1&nbsp;$2$3';
    }

    if (get_option('zalomeni_space_between_numbers', self::default_space_between_numbers) === 'on') {
      $return_array['numbers'] = '$1&nbsp;$2';
    }

    if (get_option('zalomeni_spaces_in_scales', self::default_spaces_in_scales) === 'on') {
      $return_array['scales'] = '$1&nbsp;:&nbsp;$2';
    }

    if (get_option('zalomeni_space_after_ordered_number', self::default_space_after_ordered_number) === 'on') {
      $return_array['orders'] = '$1&nbsp;$2';
    }

    if (get_option('zalomeni_custom_terms', self::default_custom_terms) !== '') {
      $term_counter = 1;
      $custom_terms = explode(chr(10), str_replace(chr(13), '', get_option('zalomeni_custom_terms', self::default_custom_terms)));
      foreach ($custom_terms as $i) {
        if (strpos($i, ' ') !== false) {
          $term = '';
          $words_split = explode(' ', $i);
          $word_counter = 1;
          foreach ($words_split as $j) {
            $term .= ($term === '' ? '' : '&nbsp;') . '$' . $word_counter++;
          }
          $return_array['customterm' . $term_counter++] = $term;
        }
      }
    }

    return $return_array;
  }

  public static function settings_section_description() {
    echo(
      '<div id="zalomeni_options_desc" style="margin:0 0 15px 10px;-webkit-border-radius:3px;border-radius:3px;border-width:1px;border-color:#e6db55;border-style:solid;float:right;background:#FFFBCC;text-align:center;width:200px">'
      . '<p style="line-height:1.5em;">Plugin <strong>Zalomení</strong><br />' . esc_html__('Maintainer:', 'zalomeni') . ' <a href="https://kybernaut.cz" class="external" target="_blank">Karolína Vyskočilová</a><br /><small>' . esc_html__('Originally by', 'zalomeni') . ' <a href="https://www.honza.info/" class="external" target="_blank">Honza Skýpala</a></small></p>'
      . '</div>'
      . '<p>' . wp_kses_post( self::texturize(__('Upravujeme-li písemný dokument, radí nám <strong>Pravidla českého pravopisu</strong> nepsat neslabičné předložky <em>v, s, z, k</em> na konec řádku, ale psát je na stejný řádek se slovem, které nese přízvuk (např. ve spojení <em>k mostu</em>, <em>s bratrem</em>, <em>v Plzni</em>, <em>z nádraží</em>). Typografické normy jsou ještě přísnější: podle některých je nepatřičné ponechat na konci řádku jakékoli jednopísmenné slovo, tedy také předložky a spojky <em>a, i, o, u</em>;. Někteří pisatelé dokonce nechtějí z estetických důvodů ponechávat na konci řádků jakékoli jednoslabičné výrazy (např. <em>ve, ke, ku, že, na, do, od, pod</em>).', 'zalomeni')) ) . '</p>'
      . '<p>' . wp_kses_post( self::texturize(__('<a href="https://prirucka.ujc.cas.cz/?id=880" class="external" target="_blank">Více informací</a> na webu Ústavu pro jazyk český, Akademie věd ČR.', 'zalomeni')) ) . '</p>'
      . '<p>' . wp_kses_post( self::texturize(__('Tento plugin řeší některé z uvedených příkladů: v textu nahrazuje běžné mezery za pevné tak, aby nedošlo k zalomení řádku v nevhodném místě.', 'zalomeni')) ) . '</p>'
    );
  }

  private static function pushpop_element($text, &$stack, $disabled_elements, $opening, $closing) {
    $tag = trim( str_replace( $closing, '', str_replace( $opening, '', $text ) ) );
    $tag = explode( ' ', $tag )[0];
    if ( in_array( $tag, $disabled_elements, true ) ) {
      $stack[] = $tag;
    } elseif ( strpos( $tag, '/' ) === 0 ) {
      $tag = ltrim( $tag, '/' );
      if ( ( $key = array_search( $tag, $stack, true ) ) !== false ) {
        unset( $stack[ $key ] );
        $stack = array_values( $stack );
      }
    }
  }

  public static function texturize($text) {
    $matches = get_option('zalomeni_matches');
    if (empty($matches)) return $text;
    $replacements = get_option('zalomeni_replacements');

    $output = '';
    $curl = '';
    $textarr = preg_split('/(<.*>|\[.*\])/Us', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    $stop = count($textarr);

    $no_texturize_tags = apply_filters('no_texturize_tags', array('pre', 'code', 'kbd', 'style', 'script', 'tt'));
    $no_texturize_shortcodes = apply_filters('no_texturize_shortcodes', array('code'));
    $no_texturize_tags_stack = array();
    $no_texturize_shortcodes_stack = array();

    for ($i = 0; $i < $stop; $i++) {
      $curl = $textarr[$i];

      if (!empty($curl)) {
        if ('<' !== $curl[0] && '[' !== $curl[0]
            && empty($no_texturize_shortcodes_stack) && empty($no_texturize_tags_stack)) {
          $result = @preg_replace($matches, $replacements, $curl);
          if ($result !== null) {
            $result = @preg_replace($matches, $replacements, $result);
          }
          $curl = ($result !== null) ? $result : $curl;
        } else {
          self::pushpop_element($curl, $no_texturize_tags_stack, $no_texturize_tags, '<', '>');
          self::pushpop_element($curl, $no_texturize_shortcodes_stack, $no_texturize_shortcodes, '[', ']');
        }
      }

      $output .= $curl;
    }

    return $output;
  }
}

function zalomeni_init() {
  static $instance = null;
  if ( $instance === null ) {
    $instance = new Zalomeni();
  }
  return $instance;
}
zalomeni_init();