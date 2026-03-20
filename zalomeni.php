<?php
/*
Plugin Name: Zalomení
Plugin URI: http://wordpress.org/plugins/zalomeni/
Description: Puts non-breakable space after one-letter Czech prepositions like 'k', 's', 'v' or 'z'.
Version: 1.5
Author: Honza Skypala
Author URI: http://www.honza.info/
*/

include_once(ABSPATH . 'wp-admin/includes/plugin.php');

class Zalomeni {
  const version = '1.5';

  public function __construct() {
    register_activation_hook(__FILE__, array($this, 'activate'));
    if (is_admin()){
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
        add_filter($filter, array($this, 'texturize'));  // content filter
      }
    }
  }

  static function activate() {
    $required_php_version = '5.3';
    if (version_compare(phpversion(), $required_php_version, '<'))
      die(str_replace(array("%1", "%2"), array($required_php_version, phpversion()), __("Plugin Zalomení vyžaduje PHP verze %1 nebo vyšší. Na tomto webu je nainstalováno PHP verze %2", "zalomeni")));
    
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
  
  static function add_options() {
    add_option('zalomeni_version', self::version);

    add_option('zalomeni_prepositions',                 Zalomeni::default_prepositions);
    add_option('zalomeni_prepositions_list',            Zalomeni::default_prepositions_list);
    add_option('zalomeni_conjunctions',                 Zalomeni::default_conjunctions);
    add_option('zalomeni_conjunctions_list',            Zalomeni::default_conjunctions_list);
    add_option('zalomeni_abbreviations',                Zalomeni::default_abbreviations);
    add_option('zalomeni_abbreviations_list',           Zalomeni::default_abbreviations_list);
    add_option('zalomeni_between_number_and_unit',      Zalomeni::default_between_number_and_unit);
    add_option('zalomeni_between_number_and_unit_list', Zalomeni::default_between_number_and_unit_list);
    add_option('zalomeni_spaces_in_scales',             Zalomeni::default_spaces_in_scales);
    add_option('zalomeni_space_between_numbers',        Zalomeni::default_space_between_numbers);
    add_option('zalomeni_space_after_ordered_number',   Zalomeni::default_space_after_ordered_number);
    add_option('zalomeni_custom_terms',                 Zalomeni::default_custom_terms);

    self::update_matches_and_replacements();
  }

  private function update_plugin_version() {
    $registered_version = get_option('zalomeni_version', '0');
    if ($registered_version == '0') return;

    if (version_compare($registered_version, self::version, '<')) {
      if (version_compare($registered_version, '1.3', '<')) {
        $old_options = get_option('zalomeni_options');
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

      self::add_options();
      update_option('zalomeni_version', self::version);
    }
  }

  protected static $this_plugin;
  function add_settings_to_plugin_actions($links, $file) {
    // Add settings link to plugin list for this plugin
    if (!self::$this_plugin) self::$this_plugin = plugin_basename(__FILE__);
     if ($file == self::$this_plugin) {
      $settings_link = '<a href="options-reading.php#zalomeni_options_desc">' . __('Settings') . '</a>';
      array_unshift( $links, $settings_link ); // before other links
    }
    return $links;
  }

  function admin_init() {
    $this->update_plugin_version();
    add_filter('plugin_action_links', array($this, 'add_settings_to_plugin_actions'), 10, 2);  // link from Plugins list admin page to settings of this plugin

    register_setting('reading', 'zalomeni_prepositions');
    register_setting('reading', 'zalomeni_prepositions_list');
    register_setting('reading', 'zalomeni_conjunctions');
    register_setting('reading', 'zalomeni_conjunctions_list');
    register_setting('reading', 'zalomeni_abbreviations');
    register_setting('reading', 'zalomeni_abbreviations_list');
    register_setting('reading', 'zalomeni_between_number_and_unit');
    register_setting('reading', 'zalomeni_between_number_and_unit_list');
    register_setting('reading', 'zalomeni_space_between_numbers');
    register_setting('reading', 'zalomeni_space_after_ordered_number');
    register_setting('reading', 'zalomeni_spaces_in_scales');
    register_setting('reading', 'zalomeni_custom_terms');

    add_settings_section('zalomeni_section', $this->texturize(__('Nevhodná slova a zalomení na konci řádku', 'zalomeni')), 'Zalomeni::settings_section_description', 'reading');

    add_settings_field('zalomeni_prepositions', __('Předložky', 'zalomeni'), 'Zalomeni::settings_field_checkbox', 'reading', 'zalomeni_section', array('option'=>'prepositions', 'description'=>"Vkládat pevnou mezeru za následující předložky.", 'toggle_list_read_only'=>true));
    add_settings_field('zalomeni_prepositions_list', '', 'Zalomeni::settings_field_textlist', 'reading', 'zalomeni_section', array('option'=>'prepositions', 'description'=>"(oddělte jednotlivé předložky čárkou)"));
    add_settings_field('zalomeni_conjunctions', __('Spojky', 'zalomeni'), 'Zalomeni::settings_field_checkbox', 'reading', 'zalomeni_section', array('option'=>'conjunctions', 'description'=>"Vkládat pevnou mezeru za následující spojky.", 'toggle_list_read_only'=>true));
    add_settings_field('zalomeni_conjunctions_list', '', 'Zalomeni::settings_field_textlist', 'reading', 'zalomeni_section', array('option'=>'conjunctions', 'description'=>"(oddělte jednotlivé spojky čárkou)"));
    add_settings_field('zalomeni_abbreviations', __('Zkratky', 'zalomeni'), 'Zalomeni::settings_field_checkbox', 'reading', 'zalomeni_section', array('option'=>'abbreviations', 'description'=>"Vkládat pevnou mezeru za následující zkratky.", 'toggle_list_read_only'=>true));
    add_settings_field('zalomeni_abbreviations_list', '', 'Zalomeni::settings_field_textlist', 'reading', 'zalomeni_section', array('option'=>'abbreviations', 'description'=>"(oddělte jednotlivé zkratky čárkou)"));
    add_settings_field('zalomeni_between_number_and_unit', __('Jednotky a míry', 'zalomeni'), 'Zalomeni::settings_field_checkbox', 'reading', 'zalomeni_section', array('option'=>'between_number_and_unit', 'description'=>"Vkládat pevnou mezeru mezi číslovku a jednotku míry (měrné jednotky, měna apod., např. <em>5 m</em> nebo <em>10 kg</em>).", 'toggle_list_read_only'=>true));
    add_settings_field('zalomeni_between_number_and_unit_list', '', 'Zalomeni::settings_field_textlist', 'reading', 'zalomeni_section', array('option'=>'between_number_and_unit', 'description'=>"(oddělte jednotlivé míry čárkou)"));
    add_settings_field('zalomeni_space_between_numbers', __('Mezery uprostřed čísel', 'zalomeni'), 'Zalomeni::settings_field_checkbox', 'reading', 'zalomeni_section', array('option'=>'space_between_numbers', 'description'=>"Pokud jsou dvě čísla oddělena mezerou, předpokládat, že se jedná o formátování čísla pomocí mezery (např. telefonní číslo <em>800 123 456</em>) a nahrazovat mezeru pevnou mezerou, aby nedošlo k zalomení řádku uprostřed čísla."));
    add_settings_field('zalomeni_space_after_ordered_number', __('Řadové číslovky', 'zalomeni'), 'Zalomeni::settings_field_checkbox', 'reading', 'zalomeni_section', array('option'=>'space_after_ordered_number', 'description'=>"Zabránit zalomení řádku za řadovou číslovkou; díky tomu nedojde k zalomení řádku uprostřed data (např. <em>1. ledna</em>) a v podobných případech (<em>19. ročník</em>, <em>3. svazek</em>, <em>5. kapitola</em> apod.)"));
    add_settings_field('zalomeni_spaces_in_scales', __('Měřítka a poměry', 'zalomeni'), 'Zalomeni::settings_field_checkbox', 'reading', 'zalomeni_section', array('option'=>'spaces_in_scales', 'description'=>"Pevné mezery v měřítkách a poměrech (např. <em>1 : 50 000</em>)"));
    add_settings_field('zalomeni_custom_terms', __('Vlastní výrazy', 'zalomeni'), 'Zalomeni::settings_field_custom_terms', 'reading', 'zalomeni_section');
    
    if (get_option('zalomeni_matches') == '') {
      Zalomeni::update_matches_and_replacements();
    }
    
    $this->add_update_option_hooks();
  }

  static public function settings_field_checkbox(array $args) {
    echo(
      '<input type="checkbox" name="zalomeni_' . $args['option'] . '" id="zalomeni_' . $args['option'] . '" value="on" '
      . checked('on', get_option("zalomeni_" . $args['option'], constant('Zalomeni::default_' . $args['option'])), false)
      . (array_key_exists('toggle_list_read_only', $args) ? ' onchange="document.getElementById(\'zalomeni_' . $args['option'] . '_list\').readOnly = this.checked?\'\':\'1\';"' : '')
      . ' /> '
      . Zalomeni::texturize(__($args['description'], 'zalomeni'))
    );
  }

  static public function settings_field_textlist(array $args) {
    echo(
      '<input type="text" name="zalomeni_' . $args['option'] . '_list" id="zalomeni_' . $args['option'] . '_list" class="regular-text" value="' . get_option('zalomeni_' . $args['option'] . '_list', constant('Zalomeni::default_' . $args['option'] . '_list')) . '"'
       . ((get_option("zalomeni_" . $args['option'], constant('Zalomeni::default_' . $args['option'])) != 'on') ? ' readonly="1"' : '')
      . ' /> '
      . Zalomeni::texturize(__($args['description'], 'zalomeni'))
    );
  }

  static public function settings_field_custom_terms() {
    echo(
      Zalomeni::texturize(__('Zde můžete uvést vlastní termíny, v nichž mají být mezery nahrazeny pevnými mezerami tak, aby nedošlo k zalomení uvnitř těchto výrazů. Uveďte vždy každý výraz na samostatný řádek; pokud je výraz složen z více jak dvou slov, tedy je v něm více jak jedna mezera, pak všechny mezery budou nahrazeny za pevné mezery. Lze použít výrazu \\d pro libovolnou číslici (pro pokročilé administrátory: algoritmus používá <a href="http://www.php.net/manual/en/reference.pcre.pattern.syntax.php" target="_blank">Perl Compatible Regular Expressions</a>, lze využít syntaxe této specifikace).', 'zalomeni'))
      . '<p><textarea name="zalomeni_custom_terms" id="zalomeni_custom_terms" rows="10" cols="50" class="regular-text">'
      . get_option('zalomeni_custom_terms', Zalomeni::default_custom_terms)
      . '</textarea></p>'
    );
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
      add_action($i, array($this, 'update_matches_and_replacements'));
    }
  }

  static public function update_matches_and_replacements() {
    update_option('zalomeni_matches', Zalomeni::prepare_matches());
    update_option('zalomeni_replacements', Zalomeni::prepare_replacements());
  }

  static private function prepare_matches() {
    $return_array = array();

    $word_matches = '';
    foreach (array('prepositions', 'conjunctions', 'abbreviations') as $i) {
      if (get_option('zalomeni_'.$i, constant('Zalomeni::default_'.$i)) == 'on') {
        $temp_array = explode(',', get_option('zalomeni_'.$i.'_list', constant('Zalomeni::default_'.$i.'_list')));
        foreach ($temp_array as $j) {
          $j = mb_strtolower(trim($j));
          $word_matches .= ($word_matches == '' ? '' : '|') . $j;
        }
      }
    }
    if ($word_matches != '') {
      $return_array['words'] = '@($|;| |&nbsp;|\(|\n)('.$word_matches.') @i';
    }

    $word_matches = '';
    if (get_option('zalomeni_between_number_and_unit', Zalomeni::default_between_number_and_unit) == 'on') {
      $temp_array = explode(',', get_option('zalomeni_between_number_and_unit_list', Zalomeni::default_between_number_and_unit_list));
      foreach ($temp_array as $j) {
        $j = mb_strtolower(trim($j));
        $word_matches .= ($word_matches == '' ? '' : '|') . $j;
      }
    }
    if ($word_matches != '') {
      $return_array['units'] = '@(\d) ('.$word_matches.')(^|[;\.!:]| |&nbsp;|\?|\n|\)|<|\010|\013|$)@i';
    }

    if (get_option('zalomeni_space_between_numbers', Zalomeni::default_space_between_numbers) == 'on') {
      $return_array['numbers'] = '@(\d) (\d)@i';
    }

    if (get_option('zalomeni_spaces_in_scales', Zalomeni::default_spaces_in_scales) == 'on') {
      $return_array['scales'] = '@(\d) : (\d)@i';
    }

    if (get_option('zalomeni_space_after_ordered_number', Zalomeni::default_space_after_ordered_number) == 'on') {
      $return_array['orders'] = '@(\d\.) ([0-9a-záčďéěíňóřšťúýž])@';
    }

    if (get_option('zalomeni_custom_terms', Zalomeni::default_custom_terms) != '') {
      $term_counter = 1;
      $custom_terms = explode(chr(10), str_replace(chr(13), '', get_option('zalomeni_custom_terms', Zalomeni::default_custom_terms)));
      foreach ($custom_terms as $i) {
        if (strpos($i, ' ') !== false) {
          $term = '';
          $words_split = explode(' ', $i);
          foreach ($words_split as $j) {
            $term .= ($term == '' ? '(' : ' (') . str_replace(array('/', '(', ')'), array('\\/', '\\(', '\\)'), $j) . ')';
          }
          $term = '/' . $term . '/i';
          $return_array['customterm' . $term_counter++] = $term;
        }
      }
    }

    return $return_array;
  }

  static private function prepare_replacements() {
    $return_array = array();

    foreach (array('prepositions', 'conjunctions', 'abbreviations') as $i) {
      if (get_option('zalomeni_'.$i, constant('Zalomeni::default_'.$i)) == 'on') {
        $return_array['words'] = '$1$2&nbsp;';
        break;
      }
    }

    if (get_option('zalomeni_between_number_and_unit', Zalomeni::default_between_number_and_unit) == 'on') {
      $return_array['units'] = '$1&nbsp;$2$3';
    }

    if (get_option('zalomeni_space_between_numbers', Zalomeni::default_space_between_numbers) == 'on') {
      $return_array['numbers'] = '$1&nbsp;$2';
    }

    if (get_option('zalomeni_spaces_in_scales', Zalomeni::default_spaces_in_scales) == 'on') {
      $return_array['scales'] = '$1&nbsp;:&nbsp;$2';
    }

    if (get_option('zalomeni_space_after_ordered_number', Zalomeni::default_space_after_ordered_number) == 'on') {
      $return_array['orders'] = '$1&nbsp;$2';
    }

    if (get_option('zalomeni_custom_terms', Zalomeni::default_custom_terms) != '') {
      $term_counter = 1;
      $custom_terms = explode(chr(10), str_replace(chr(13), '', get_option('zalomeni_custom_terms', Zalomeni::default_custom_terms)));
      foreach ($custom_terms as $i) {
        if (strpos($i, ' ') !== false) {
          $term = '';
          $words_split = explode(' ', $i);
          $word_counter = 1;
          foreach ($words_split as $j) {
            $term .= ($term == '' ? '' : '&nbsp;') . '$' . $word_counter++;
          }
          $return_array['customterm' . $term_counter++] = $term;
        }
      }
    }

    return $return_array;
  }

  static public function settings_section_description() {
    echo(
      '<div id="zalomeni_options_desc" style="margin:0 0 15px 10px;-webkit-border-radius:3px;border-radius:3px;border-width:1px;border-color:#e6db55;border-style:solid;float:right;background:#FFFBCC;text-align:center;width:200px">'
      . '<p style="line-height:1.5em;">Plugin <strong>Zalomení</strong><br />Autor: <a href="http://www.honza.info/" class="external" target="_blank" title="http://www.honza.info/">Honza Skýpala</a></p>'
      . '</div>'
      . '<p>' . Zalomeni::texturize(__('Upravujeme-li písemný dokument, radí nám <strong>Pravidla českého pravopisu</strong> nepsat neslabičné předložky <em>v, s, z, k</em> na konec řádku, ale psát je na stejný řádek se slovem, které nese přízvuk (např. ve spojení <em>k mostu</em>, <em>s bratrem</em>, <em>v Plzni</em>, <em>z nádraží</em>). Typografické normy jsou ještě přísnější: podle některých je nepatřičné ponechat na konci řádku jakékoli jednopísmenné slovo, tedy také předložky a spojky <em>a, i, o, u</em>;. Někteří pisatelé dokonce nechtějí z estetických důvodů ponechávat na konci řádků jakékoli jednoslabičné výrazy (např. <em>ve, ke, ku, že, na, do, od, pod</em>).', 'zalomeni')) . '</p>'
      . '<p>' . Zalomeni::texturize(__('<a href="http://prirucka.ujc.cas.cz/?id=880" class="external" target="_blank">Více informací</a> na webu Ústavu pro jazyk český, Akademie věd ČR.', 'zalomeni')) . '</p>'
      . '<p>' . Zalomeni::texturize(__('Tento plugin řeší některé z uvedených příkladů: v textu nahrazuje běžné mezery za pevné tak, aby nedošlo k zalomení řádku v nevhodném místě.', 'zalomeni')) . '</p>'
    );
  }

  static public function texturize($text) {
    if (get_option('zalomeni_matches') == '') return $text; // no settings? then fall-back to just return the content
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
        global $wp_version;
        if ('<' != $curl[0] && '[' != $curl[0]
            && empty($no_texturize_shortcodes_stack) && empty($no_texturize_tags_stack)) { // If it's not a tag
          $curl = preg_replace(get_option('zalomeni_matches'), get_option('zalomeni_replacements'), $curl);
          $curl = preg_replace(get_option('zalomeni_matches'), get_option('zalomeni_replacements'), $curl);
        } else if (version_compare($wp_version, '2.9', '<')) {
          wptexturize_pushpop_element($curl, $no_texturize_tags_stack, $no_texturize_tags, '<', '>');
          wptexturize_pushpop_element($curl, $no_texturize_shortcodes_stack, $no_texturize_shortcodes, '[', ']');
        } else {
          _wptexturize_pushpop_element($curl, $no_texturize_tags_stack, $no_texturize_tags, '<', '>');
          _wptexturize_pushpop_element($curl, $no_texturize_shortcodes_stack, $no_texturize_shortcodes, '[', ']');
        }
      }

      $output .= $curl;
    }

    return $output;
  }
}

$wpZalomeni = new Zalomeni();
?>