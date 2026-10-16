<?php
/**
 * Focused checks against the active plugin; no database fixtures are written.
 * ZALOMENI_WP_LOAD=/path/to/wp-load.php php tests/bricks-support.php --group=legacy
 * Groups: legacy, bricks, compatibility. Bricks contexts: builder, public-rest, builder-rest.
 */

function expect_same($label, $expected, $actual) {
    if ($expected !== $actual) {
        throw new RuntimeException($label . "\nExpected: " . var_export($expected, true)
            . "\nActual: " . var_export($actual, true));
    }
    echo 'OK ' . $label . "\n";
}

function with_options(array $options, callable $check) {
    $filters = array();
    try {
        foreach ($options as $name => $value) {
            $filters[$name] = function () use ($value) { return $value; };
            add_filter('pre_option_' . $name, $filters[$name]);
        }
        return $check();
    } finally {
        foreach ($filters as $name => $callback) {
            remove_filter('pre_option_' . $name, $callback);
        }
    }
}

function zalomeni_bindings() {
    global $wp_filter;
    $bindings = array();
    foreach ($wp_filter as $hook => $registry) {
        foreach ($registry->callbacks as $priority => $callbacks) {
            foreach ($callbacks as $binding) {
                $callback = $binding['function'];
                if (is_array($callback) && $callback[0] === 'Zalomeni'
                    && in_array($callback[1], array('texturize', 'texturize_plain', 'texturize_bricks'), true)) {
                    $bindings[] = array($hook, $callback, $priority, $binding['accepted_args']);
                }
            }
        }
    }
    sort($bindings);
    return $bindings;
}

function remove_zalomeni_bindings() {
    foreach (zalomeni_bindings() as $binding) {
        remove_filter($binding[0], $binding[1], $binding[2]);
    }
}

function with_registration_options(array $options, callable $check) {
    $saved = zalomeni_bindings();
    remove_zalomeni_bindings();
    try {
        return with_options($options, function () use ($check) {
            zalomeni_init()->add_filters();
            return $check();
        });
    } finally {
        remove_zalomeni_bindings();
        foreach ($saved as $binding) {
            add_filter($binding[0], $binding[1], $binding[2], $binding[3]);
        }
    }
}

function decoded_text($html) {
    $processor = WP_HTML_Processor::create_fragment($html);
    $text = '';
    while ($processor->next_token()) {
        if ($processor->get_token_type() === '#text') {
            $text .= $processor->get_modifiable_text();
        }
    }
    return $text;
}

function run_bricks_checks($disabled, $context) {
    if (!class_exists('WP_HTML_Processor') || !method_exists('WP_HTML_Processor', 'set_modifiable_text')) {
        throw new RuntimeException('The bricks group requires the real WordPress HTML text-update API (WordPress 6.7+).');
    }
    if (!is_callable(array('Bricks\\Frontend', 'render_data'))
        || !function_exists('bricks_is_builder') || !function_exists('bricks_is_builder_call')) {
        throw new RuntimeException('The bricks group requires active Bricks Builder with Frontend::render_data(), bricks_is_builder() and bricks_is_builder_call().');
    }
    $nbsp = "\xC2\xA0";
    expect_same('Bricks callback priority', 20, has_filter('bricks/frontend/render_data', array('Zalomeni', 'texturize_bricks')));
    expect_same('no legacy Bricks callback', false, has_filter('bricks/frontend/render_data', array('Zalomeni', 'texturize')));
    if ($context !== '') {
        expect_same('builder classification', $context === 'builder', bricks_is_builder());
        if ($context !== 'builder') {
            expect_same('builder call classification', $context === 'builder-rest', bricks_is_builder_call());
        }
        with_options(array_merge($disabled, array('zalomeni_prepositions' => 'on', 'zalomeni_prepositions_list' => 'v')), function () use ($context, $nbsp) {
            expect_same($context . ' output', $context === 'public-rest' ? '<p>v'.$nbsp.'lese</p>' : '<p>v lese</p>',
                apply_filters('bricks/frontend/render_data', '<p>v lese</p>', null, 'content'));
        });
        echo 'PASS bricks ' . $context . "\n";
        return;
    }

    // Specification expectations, not captured legacy output or compiler-derived values.
    $words = array_merge($disabled, array(
        'zalomeni_prepositions' => 'on', 'zalomeni_prepositions_list' => 'k, s, v, z',
        'zalomeni_conjunctions' => 'on', 'zalomeni_conjunctions_list' => 'a, i, o, u',
    ));
    with_options($words, function () use ($nbsp) {
        expect_same('decoded words', '<p>V'.$nbsp.'lese a'.$nbsp.'v'.$nbsp.'zahradě.</p>',
            apply_filters('bricks/frontend/render_data', '<p>V lese a v zahradě.</p>', null, 'content'));
        $cases = array(
            'heading' => array('<h2>V lese</h2>', '<h2>V'.$nbsp.'lese</h2>'),
            'rich text' => array('<div><p>V lese <strong>a v zahradě</strong>.</p></div>', '<div><p>V'.$nbsp.'lese <strong>a'.$nbsp.'v'.$nbsp.'zahradě</strong>.</p></div>'),
            'line feed' => array("<p>v lese\na v domě</p>", '<p>v'.$nbsp."lese\na".$nbsp.'v'.$nbsp.'domě</p>'),
            'Windows line endings' => array("<p>v lese\r\na v domě</p>", '<p>v'.$nbsp."lese\na".$nbsp.'v'.$nbsp.'domě</p>'),
            'carriage return' => array("<p>v lese\ra v domě</p>", '<p>v'.$nbsp."lese\na".$nbsp.'v'.$nbsp.'domě</p>'),
            'null byte' => array("<p>v lese\0\na v domě</p>", '<p>v'.$nbsp."lese\na".$nbsp.'v'.$nbsp.'domě</p>'),
            'encoded spaces and control characters' => array(
                '<p>v&#32;lese&#13;X&#10;v&#32;lese&#0;</p>',
                '<p>v'.$nbsp."lese\rX\nv".$nbsp."lese\xEF\xBF\xBD</p>",
            ),
            'normalized text around protected content' => array(
                "<p>v lese</p><code>v lese\r\n\0 a v domě</code><p>v lese\r\na v domě</p>",
                '<p>v'.$nbsp."lese</p><code>v lese\r\n\0 a v domě</code><p>v".$nbsp."lese\na".$nbsp.'v'.$nbsp.'domě</p>',
            ),
            // Neutral Czech fixture
            'representative paragraph' => array(
                '<p class="brxe-test01 brxe-text-basic">Návrh vzniká s důrazem na detail. Prostor v našem pojetí vypráví příběh, v němž se design propojuje s potřebami klientů.</p>',
                '<p class="brxe-test01 brxe-text-basic">Návrh vzniká s'.$nbsp.'důrazem na detail. Prostor v'.$nbsp.'našem pojetí vypráví příběh, v'.$nbsp.'němž se design propojuje s'.$nbsp.'potřebami klientů.</p>',
            ),
            'quoted greater than' => array('<div data-test="a > v lese">V lese</div>', '<div data-test="a > v lese">V'.$nbsp.'lese</div>'),
            'comments' => array('<!-- a > v lese --><p>v lese</p>', '<!-- a > v lese --><p>v'.$nbsp.'lese</p>'),
            'doctype and processing instruction' => array('<!DOCTYPE html><?fixture v lese?><p>v lese</p>', '<!DOCTYPE html><?fixture v lese?><p>v'.$nbsp.'lese</p>'),
            'CSS' => array('<style>.x::after {content:" v lese"}</style><p>v lese</p>', '<style>.x::after {content:" v lese"}</style><p>v'.$nbsp.'lese</p>'),
            'uppercase script' => array('<SCRIPT>const text = " v lese";</SCRIPT><p>v lese</p>', '<SCRIPT>const text = " v lese";</SCRIPT><p>v'.$nbsp.'lese</p>'),
            'mixed multiline JSON' => array("<ScRiPt\n type=\"application/ld+json\">{\"text\":\" v lese\"}</sCrIpT><p>v lese</p>", "<ScRiPt\n type=\"application/ld+json\">{\"text\":\" v lese\"}</sCrIpT><p>v".$nbsp.'lese</p>'),
            'nested exclusions and void tags' => array('<PRE><code>v lese</code> v lese<br></PRE><br><p>v lese</p>', '<PRE><code>v lese</code> v lese<br></PRE><br><p>v'.$nbsp.'lese</p>'),
            'remaining default exclusions' => array('<kbd>v lese</kbd><tt>v lese</tt><p>v lese</p>', '<kbd>v lese</kbd><tt>v lese</tt><p>v'.$nbsp.'lese</p>'),
            'atomic textarea' => array('<textarea>v lese</textarea><p>v lese</p>', '<textarea>v lese</textarea><p>v'.$nbsp.'lese</p>'),
            'existing NBSP' => array('<p>v'.$nbsp.'lese a v zahradě</p>', '<p>v'.$nbsp.'lese a'.$nbsp.'v'.$nbsp.'zahradě</p>'),
            'unchanged bytes' => array('<p title="v lese">Already&nbsp;joined &amp; intact</p>', '<p title="v lese">Already&nbsp;joined &amp; intact</p>'),
        );
        foreach (array(
            '<svg><![CDATA[ v lese > v lese ]]></svg>',
            '<svg><title/><text>v lese</text></svg>',
            '<svg><foreignObject><script>const s="</svg> v lese";</script></foreignObject></svg>',
            '<math><annotation-xml encoding="text/html"><script>const s="</math> v lese";</script></annotation-xml></math>',
        ) as $index => $foreign) {
            $cases['foreign subtree ' . $index] = array($foreign.'<p>v lese</p>', $foreign.'<p>v'.$nbsp.'lese</p>');
        }
        foreach ($cases as $label => $case) {
            $actual = apply_filters('bricks/frontend/render_data', $case[0], null, 'content');
            expect_same($label, $case[1], $actual);
            expect_same($label . ' second complete adapter call', $actual, apply_filters('bricks/frontend/render_data', $actual, null, 'content'));
        }
        foreach (array('"', '„', '“', '‚', '‘', '(', "\n", $nbsp) as $opening) {
            expect_same('decoded opening ' . bin2hex($opening), $opening.'V'.$nbsp.'lese',
                decoded_text(apply_filters('bricks/frontend/render_data', '<p>'.$opening.'V lese</p>', null, 'content')));
        }
        foreach (array("America's Response", 'America’s Response', 'x;v lese') as $unchanged) {
            expect_same('possessive or semicolon ' . $unchanged, '<p>'.$unchanged.'</p>',
                apply_filters('bricks/frontend/render_data', '<p>'.$unchanged.'</p>', null, 'content'));
        }
        expect_same('literal entity and decoded brackets', '&nbsp; <text v'.$nbsp.'lese> [text v'.$nbsp.'lese]',
            decoded_text(apply_filters('bricks/frontend/render_data', '<p>&amp;nbsp; &lt;text v lese&gt; [text v lese]</p>', null, 'content')));
        foreach (array('<p>v lese</p><span title="', '<p>v lese</p><plaintext>v lese') as $incomplete) {
            expect_same('incomplete or unsupported original fragment', $incomplete, apply_filters('bricks/frontend/render_data', $incomplete, null, 'content'));
        }
        $unsupported = WP_HTML_Processor::create_fragment('<p>v lese</p><plaintext>v lese');
        while ($unsupported->next_token()) {}
        expect_same('unsupported fixture reaches parser error', true, $unsupported->get_last_error() !== null);
        $chain = '<p>'.str_repeat('v ', 40).'lese</p>';
        $expected = '<p>'.str_repeat('v'.$nbsp, 40).'lese</p>';
        $actual = apply_filters('bricks/frontend/render_data', $chain, null, 'content');
        expect_same('long preposition chain', $expected, $actual);
        expect_same('chain second call', $actual, apply_filters('bricks/frontend/render_data', $actual, null, 'content'));

        $custom_tags = function () { return array('SpAn', 'br'); };
        add_filter('no_texturize_tags', $custom_tags);
        try {
            expect_same('custom excluded ancestors and removed code', '<span>v lese</span><br><code>v'.$nbsp.'lese</code><p>v'.$nbsp.'lese</p>',
                apply_filters('bricks/frontend/render_data', '<span>v lese</span><br><code>v lese</code><p>v lese</p>', null, 'content'));
        } finally {
            remove_filter('no_texturize_tags', $custom_tags);
        }

        foreach (array('content', 'header', 'footer', 'popup') as $area) {
            $rendered = Bricks\Frontend::render_data(array(array(
                'id' => 'ztst01', 'name' => 'text-basic', 'parent' => 0,
                'children' => array(), 'settings' => array('text' => 'v lese'),
            )), $area);
            expect_same('real Bricks renderer ' . $area, 'v'.$nbsp.'lese', decoded_text($rendered));
        }

        $templates = get_posts(array('post_type' => 'bricks_template', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids'));
        $selected = null;
        foreach ($templates as $id) {
            $elements = get_post_meta($id, BRICKS_DB_PAGE_CONTENT, true);
            $type = get_post_meta($id, BRICKS_DB_TEMPLATE_TYPE, true);
            if (in_array($type, array('section', 'content'), true) && is_array($elements) && count($elements)
                && ($selected === null || count($elements) < $selected['count'])) {
                $selected = array('id' => $id, 'type' => $type, 'count' => count($elements));
            }
        }
        if ($selected === null) {
            echo "UNVERIFIED actual nested template: no suitable existing published section/content template\n";
        } else {
            $renders = 0;
            $observe = function ($html) use (&$renders) { $renders++; return $html; };
            add_filter('bricks/frontend/render_data', $observe, 19);
            try {
                $nested = Bricks\Frontend::render_data(array(
                    array('id' => 'ztst02', 'name' => 'template', 'parent' => 0, 'children' => array(), 'settings' => array('template' => $selected['id'])),
                    array('id' => 'ztst03', 'name' => 'text-basic', 'parent' => 0, 'children' => array(), 'settings' => array('text' => 'v lese')),
                ));
                expect_same('actual nested template runs inner and outer filters', true, $renders >= 2);
                expect_same('actual nested template retains processed outer sibling', true, strpos(decoded_text($nested), 'v'.$nbsp.'lese') !== false);
                expect_same('actual nested template second adapter call', $nested, Zalomeni::texturize_bricks($nested));
                echo 'Existing nested template ' . $selected['id'] . '; type ' . $selected['type'] . '; elements ' . $selected['count'] . "\n";
            } finally {
                remove_filter('bricks/frontend/render_data', $observe, 19);
            }
        }

        $earlier_filter = function ($html, $post) {
            expect_same('earlier-filter receives null post', null, $post);
            return str_replace('%%zalomeni_test%%', 'v lese', $html);
        };
        add_filter('bricks/frontend/render_data', $earlier_filter, 10, 2);
        try {
            expect_same('earlier-filter ordering processes replacement text', '<p>v'.$nbsp.'lese</p>',
                apply_filters('bricks/frontend/render_data', '<p>%%zalomeni_test%%</p>', null, 'content'));
        } finally {
            remove_filter('bricks/frontend/render_data', $earlier_filter, 10);
        }

        $previous_limit = ini_get('pcre.backtrack_limit');
        try {
            ini_set('pcre.backtrack_limit', '0');
            $compiler = new ReflectionMethod('Zalomeni', 'prepare_matches');
            $compiler->setAccessible(true);
            expect_same('regex failure fixture actually fails', null, @preg_replace($compiler->invoke(null, true), 'x', 'v lese'));
            expect_same('regex failure fixture reaches backtrack limit', PREG_BACKTRACK_LIMIT_ERROR, preg_last_error());
            expect_same('decoded regex failure leaves original node', '<p>v lese</p>', Zalomeni::texturize_bricks('<p>v lese</p>'));
        } finally {
            ini_set('pcre.backtrack_limit', $previous_limit);
        }
    });

    with_options(array_merge($disabled, array(
        'zalomeni_prepositions' => 'on', 'zalomeni_prepositions_list' => '',
        'zalomeni_between_number_and_unit' => 'on', 'zalomeni_between_number_and_unit_list' => 'kg',
    )), function () use ($nbsp) {
        expect_same('rule pairing', '<p>5'.$nbsp.'kg costs</p>',
            apply_filters('bricks/frontend/render_data', '<p>5 kg costs</p>', null, 'content'));
    });
    with_options(array_merge($disabled, array('zalomeni_custom_terms' => "Alpha Beta\nAlpha Beta Gamma")), function () use ($nbsp) {
        expect_same('overlapping terms', '<p>Alpha'.$nbsp.'Beta'.$nbsp.'Gamma</p>',
            apply_filters('bricks/frontend/render_data', '<p>Alpha Beta Gamma</p>', null, 'content'));
    });

    $rule_cases = array(
        'abbreviations' => array(array('zalomeni_abbreviations' => 'on', 'zalomeni_abbreviations_list' => 'např.'), 'např. příklad', 'např.'.$nbsp.'příklad'),
        'units' => array(array('zalomeni_between_number_and_unit' => 'on', 'zalomeni_between_number_and_unit_list' => 'kg'), '5 kg costs', '5'.$nbsp.'kg costs'),
        'units NBSP trailing separator' => array(array('zalomeni_between_number_and_unit' => 'on', 'zalomeni_between_number_and_unit_list' => 'kg', 'zalomeni_prepositions' => 'on', 'zalomeni_prepositions_list' => 'v'), '5 kg'.$nbsp.'v lese', '5'.$nbsp.'kg'.$nbsp.'v'.$nbsp.'lese'),
        'numbers' => array(array('zalomeni_space_between_numbers' => 'on'), '800 123 456', '800'.$nbsp.'123'.$nbsp.'456'),
        'scales' => array(array('zalomeni_spaces_in_scales' => 'on'), '1 : 50', '1'.$nbsp.':'.$nbsp.'50'),
        'ordinals' => array(array('zalomeni_space_after_ordered_number' => 'on'), '1. ledna', '1.'.$nbsp.'ledna'),
        'overlapping terms' => array(array('zalomeni_custom_terms' => "Alpha Beta\nAlpha Beta Gamma"), 'Alpha Beta Gamma', 'Alpha'.$nbsp.'Beta'.$nbsp.'Gamma'),
        'word before custom term' => array(array('zalomeni_custom_terms' => 'v lese dnes', 'zalomeni_prepositions' => 'on', 'zalomeni_prepositions_list' => 'v'), 'v lese dnes', 'v'.$nbsp.'lese'.$nbsp.'dnes'),
        'literal custom settings' => array(array('zalomeni_custom_terms' => 'Alpha &amp;'), 'Alpha &amp;', 'Alpha'.$nbsp.'&amp;'),
        'disabled rules' => array(array(), 'v lese a v zahradě 5 kg 800 123 1 : 50 1. ledna Alpha Beta', 'v lese a v zahradě 5 kg 800 123 1 : 50 1. ledna Alpha Beta'),
    );
    foreach (array('', ', ,') as $empty) {
        $rule_cases['rule pairing words ' . $empty] = array(array('zalomeni_prepositions' => 'on', 'zalomeni_prepositions_list' => $empty, 'zalomeni_between_number_and_unit' => 'on', 'zalomeni_between_number_and_unit_list' => 'kg'), '5 kg costs', '5'.$nbsp.'kg costs');
        $rule_cases['rule pairing units ' . $empty] = array(array('zalomeni_between_number_and_unit' => 'on', 'zalomeni_between_number_and_unit_list' => $empty, 'zalomeni_space_between_numbers' => 'on'), '800 123', '800'.$nbsp.'123');
    }
    foreach ($rule_cases as $label => $case) {
        with_options(array_merge($disabled, $case[0]), function () use ($label, $case) {
            $actual = apply_filters('bricks/frontend/render_data', '<p>'.htmlspecialchars($case[1], ENT_QUOTES, 'UTF-8').'</p>', null, 'content');
            expect_same($label, $case[2], decoded_text($actual));
            expect_same($label . ' second call', $actual, apply_filters('bricks/frontend/render_data', $actual, null, 'content'));
        });
    }

    // Preserve all supported custom escapes in decoded mode as well as the legacy fixtures below.
    with_options(array_merge($disabled, array('zalomeni_custom_terms' => "Digit \\d\nNondigit \\D\nWord \\w\nNonword \\W\nSpace \\s\nNonspace \\S\nLiteral (x)+")), function () use ($nbsp) {
        expect_same('decoded supported escapes', 'Digit'.$nbsp.'7; Nondigit'.$nbsp.'A; Word'.$nbsp.'x; Nonword'.$nbsp.'!; Space'.$nbsp.' ; Nonspace'.$nbsp.'Z; Literal'.$nbsp.'(x)+', decoded_text(
            apply_filters('bricks/frontend/render_data', '<p>Digit 7; Nondigit A; Word x; Nonword !; Space  ; Nonspace Z; Literal (x)+</p>', null, 'content')));
    });
    with_options(array_merge($words, array('zalomeni_matches' => array('words' => '@(^|;| |&nbsp;|\(|\n)(v) @i'), 'zalomeni_replacements' => array('words' => '$1$2&nbsp;'))), function () use ($nbsp) {
        $acf = apply_filters('acf/format_value/type=text', 'v lese');
        expect_same('ACF plain output', 'v'.$nbsp.'lese', $acf);
        $html = '<p>'.esc_html($acf).'</p>';
        expect_same('ACF existing spaces survive adapter', $html, apply_filters('bricks/frontend/render_data', $html, null, 'content'));
    });

    $helper = new ReflectionMethod('Zalomeni', 'replace_spaces');
    $helper->setAccessible(true);
    expect_same('decoded regex failure restores text', 'v lese', $helper->invoke(null, 'v lese', array('/[/'), array('x'), true));
    expect_same('decoded nondecreasing pass restores text', 'v lese', $helper->invoke(null, 'v lese', array('/v/'), array('x'), true));
    expect_same('legacy helper scalar inputs', 'v_lese', $helper->invoke(null, 'v lese', '/ /', '_'));

    $saved = zalomeni_bindings();
    $stored = array_merge($words, array('zalomeni_matches' => array('words' => '@(^|;| |&nbsp;|\(|\n)(v) @i'), 'zalomeni_replacements' => array('words' => '$1$2&nbsp;')));
    $calls = 0;
    $opt_out = function ($filters) use (&$calls) { $calls++; unset($filters['bricks/frontend/render_data']); return $filters; };
    add_filter('zalomeni_filtry', $opt_out);
    try {
        with_registration_options($stored, function () {
            expect_same('Bricks opt-out registration', false, has_filter('bricks/frontend/render_data', array('Zalomeni', 'texturize_bricks')));
        });
        with_registration_options($words, function () {
            expect_same('empty stored arrays register nothing', array(), zalomeni_bindings());
        });
    } finally {
        remove_filter('zalomeni_filtry', $opt_out);
    }
    expect_same('filter list applied only with stored rules', 1, $calls);
    expect_same('bindings restored after opt-out', $saved, zalomeni_bindings());
    with_registration_options($stored, function () use ($nbsp) {
        expect_same('stored rules Bricks priority', 20, has_filter('bricks/frontend/render_data', array('Zalomeni', 'texturize_bricks')));
        expect_same('no legacy Bricks callback', false, has_filter('bricks/frontend/render_data', array('Zalomeni', 'texturize')));
        foreach (zalomeni_bindings() as $binding) {
            if ($binding[0] === 'bricks/frontend/render_data') {
                expect_same('Bricks accepts HTML only', 1, $binding[3]);
            }
        }
        expect_same('stored rules current output', '<p>v'.$nbsp.'lese</p>', apply_filters('bricks/frontend/render_data', '<p>v lese</p>', null, 'content'));
    });
    expect_same('bindings restored after stored rules', $saved, zalomeni_bindings());
    foreach (array('', null, false, array('v lese')) as $value) {
        expect_same('empty or nonstring guard', $value, Zalomeni::texturize_bricks($value));
    }
    echo "PASS bricks\n";
}

$group = 'legacy';
$context = '';
try {
    foreach (array_slice($argv, 1) as $argument) {
        if (strpos($argument, '--context=') === 0) {
            $context = substr($argument, strlen('--context='));
            continue;
        }
        if (strpos($argument, '--group=') !== 0) {
            throw new RuntimeException('Unsupported argument: ' . $argument);
        }
        $group = substr($argument, strlen('--group='));
    }
    if (!in_array($group, array('legacy', 'bricks', 'compatibility'), true)) {
        throw new RuntimeException('Unsupported group: ' . $group . '; supported: legacy, bricks, compatibility');
    }
    if (!in_array($context, array('', 'builder', 'public-rest', 'builder-rest'), true) || ($context !== '' && $group !== 'bricks')) {
        throw new RuntimeException('Unsupported context/group combination.');
    }

    $wp_load = getenv('ZALOMENI_WP_LOAD');
    if (!$wp_load || !is_file($wp_load) || !is_readable($wp_load)) {
        throw new RuntimeException('Set ZALOMENI_WP_LOAD to an existing readable WordPress wp-load.php file.');
    }

    $_GET = $_POST = $_REQUEST = array();
    $_SERVER['HTTP_HOST'] = 'localhost';
    $_SERVER['SERVER_NAME'] = 'localhost';
    $_SERVER['SERVER_PORT'] = '443';
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/';
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    unset($_SERVER['HTTP_X_BRICKS_IS_BUILDER'], $_SERVER['HTTP_REFERER']);
    if ($context === 'builder') {
        $_GET['bricks'] = 'run';
        $_REQUEST = $_GET;
    } elseif ($context === 'public-rest' || $context === 'builder-rest') {
        define('REST_REQUEST', true);
        if ($context === 'builder-rest') {
            $_SERVER['HTTP_X_BRICKS_IS_BUILDER'] = '1';
        }
    }
    // A CLI typography check must not spawn scheduled work during bootstrap.
    if (!defined('DISABLE_WP_CRON')) {
        define('DISABLE_WP_CRON', true);
    }
    if (!defined('WP_USE_THEMES')) {
        define('WP_USE_THEMES', false);
    }
    require $wp_load;
    if (!class_exists('Zalomeni', false)) {
        throw new RuntimeException('The active Zalomeni plugin was not loaded by WordPress.');
    }
    $loaded_plugin = realpath((new ReflectionClass('Zalomeni'))->getFileName());
    $checkout_plugin = realpath(__DIR__ . '/../zalomeni.php');
    if ($loaded_plugin === false || $checkout_plugin === false || $loaded_plugin !== $checkout_plugin) {
        throw new RuntimeException('WordPress loaded a different Zalomeni checkout. Expected: '
            . var_export($checkout_plugin, true) . '; loaded: ' . var_export($loaded_plugin, true));
    }
    echo 'WordPress ' . $wp_version . '; PHP ' . PHP_VERSION . "\n";

    if ($group === 'compatibility') {
        if (class_exists('WP_HTML_Processor') && method_exists('WP_HTML_Processor', 'set_modifiable_text')) {
            echo "NOT APPLICABLE older WordPress text-update guard: required API exists\n";
        } else {
            expect_same('older WordPress guard returns original HTML', '<p>v lese</p>', Zalomeni::texturize_bricks('<p>v lese</p>'));
        }
        echo "PASS compatibility\n";
        exit(0);
    }

    $disabled = array(
        'zalomeni_prepositions' => '',
        'zalomeni_prepositions_list' => '',
        'zalomeni_conjunctions' => '',
        'zalomeni_conjunctions_list' => '',
        'zalomeni_abbreviations' => '',
        'zalomeni_abbreviations_list' => '',
        'zalomeni_between_number_and_unit' => '',
        'zalomeni_between_number_and_unit_list' => '',
        'zalomeni_spaces_in_scales' => '',
        'zalomeni_space_between_numbers' => '',
        'zalomeni_space_after_ordered_number' => '',
        'zalomeni_custom_terms' => '',
        'zalomeni_matches' => array(),
        'zalomeni_replacements' => array(),
    );

    if ($group === 'bricks') {
        run_bricks_checks($disabled, $context);
        exit(0);
    }

    // Original-plugin characterization values captured before runtime edits,
    // from zalomeni.php at e05c652 on WordPress 7.1.3 / PHP 8.4.24.
    // Upstream c22ec3baf5fc8c1bba05251f30236d8e3117088e has the identical original
    // zalomeni.php blob b0dd4a7ee5879815365f9636f00a3bfc411ccb42.
    // Keep compiler arrays literal: stored fixtures alone cannot detect compiler changes.
    $fixtures = array(
        'words' => array(
            'options' => array(
                'zalomeni_prepositions' => 'on',
                'zalomeni_prepositions_list' => 'k, s, v, z',
                'zalomeni_conjunctions' => 'on',
                'zalomeni_conjunctions_list' => 'a, i, o, u',
            ),
            'matches' => array(
                'words' => '@(^|;| |&nbsp;|\(|\n)(?<!&#8217;)(?<!&#39;)(?<!&#039;)(?<!&rsquo;)(?<!&apos;)(?<!&#x2019;)(?<!&#x27;)(k|s|v|z|a|i|o|u) @i',
            ),
            'replacements' => array('words' => '$1$2&nbsp;'),
            'cases' => array(
                'legacy HTML' => array('<p>V lese a v zahradě.</p>', '<p>V&nbsp;lese a&nbsp;v&nbsp;zahradě.</p>'),
                'protected legacy' => array('<code>v lese</code>', '<code>v lese</code>'),
                'legacy apostrophe' => array('America&#8217;s Response', 'America&#8217;s Response'),
                'literal shortcode protection' => array('[code]v lese[/code] v lese', '[code]v lese[/code] v&nbsp;lese'),
            ),
        ),
        'numeric' => array(
            'options' => array(
                'zalomeni_between_number_and_unit' => 'on',
                'zalomeni_between_number_and_unit_list' => 'kg, Kč, m²',
                'zalomeni_spaces_in_scales' => 'on',
                'zalomeni_space_between_numbers' => 'on',
                'zalomeni_space_after_ordered_number' => 'on',
            ),
            'matches' => array(
                'units' => '@(\d) (kg|kč|m²)(^|[;\.!:]| |&nbsp;|\?|\n|\)|<|\010|\013|$)@i',
                'numbers' => '@(\d) (\d)@i',
                'scales' => '@(\d) : (\d)@i',
                'orders' => '@(\d\.) ([0-9a-záčďéěíňóřšťúýž])@',
            ),
            'replacements' => array(
                'units' => '$1&nbsp;$2$3',
                'numbers' => '$1&nbsp;$2',
                'scales' => '$1&nbsp;:&nbsp;$2',
                'orders' => '$1&nbsp;$2',
            ),
            'cases' => array(
                'legacy units' => array('5 kg, 10 Kč a 2 m².', '5 kg, 10&nbsp;Kč a 2&nbsp;m².'),
                'legacy numbers' => array('800 123 456', '800&nbsp;123&nbsp;456'),
                'legacy ordinals' => array('1. ledna a 2. 3. 2026', '1.&nbsp;ledna a 2.&nbsp;3.&nbsp;2026'),
                'legacy scales' => array('1 : 50 000', '1&nbsp;:&nbsp;50&nbsp;000'),
            ),
        ),
        'custom' => array(
            'options' => array(
                'zalomeni_custom_terms' => "Digit \\d\nNondigit \\D\nWord \\w\nNonword \\W\nSpace \\s\nNonspace \\S\nLiteral (x)+",
            ),
            'matches' => array(
                'customterm1' => '/(Digit) (\d)/i',
                'customterm2' => '/(Nondigit) (\D)/i',
                'customterm3' => '/(Word) (\w)/i',
                'customterm4' => '/(Nonword) (\W)/i',
                'customterm5' => '/(Space) (\s)/i',
                'customterm6' => '/(Nonspace) (\S)/i',
                'customterm7' => '/(Literal) (\(x\)\+)/i',
            ),
            'replacements' => array(
                'customterm1' => '$1&nbsp;$2',
                'customterm2' => '$1&nbsp;$2',
                'customterm3' => '$1&nbsp;$2',
                'customterm4' => '$1&nbsp;$2',
                'customterm5' => '$1&nbsp;$2',
                'customterm6' => '$1&nbsp;$2',
                'customterm7' => '$1&nbsp;$2',
            ),
            'cases' => array(
                'supported custom escapes and literal metacharacters' => array(
                    'Digit 7; Nondigit A; Word x; Nonword !; Space  ; Nonspace Z; Literal (x)+',
                    'Digit&nbsp;7; Nondigit&nbsp;A; Word&nbsp;x; Nonword&nbsp;!; Space&nbsp; ; Nonspace&nbsp;Z; Literal&nbsp;(x)+',
                ),
            ),
        ),
        'empty' => array(
            'options' => array(),
            'matches' => array(),
            'replacements' => array(),
            'cases' => array(
                'empty matches' => array('<p>v lese 5 kg 1 : 50 000</p>', '<p>v lese 5 kg 1 : 50 000</p>'),
            ),
        ),
    );

    $matches_compiler = new ReflectionMethod('Zalomeni', 'prepare_matches');
    $replacements_compiler = new ReflectionMethod('Zalomeni', 'prepare_replacements');
    $matches_compiler->setAccessible(true);
    $replacements_compiler->setAccessible(true);
    foreach ($fixtures as $name => $fixture) {
        $options = array_merge($disabled, $fixture['options'], array(
            'zalomeni_matches' => $fixture['matches'],
            'zalomeni_replacements' => $fixture['replacements'],
        ));
        with_options($options, function () use ($name, $fixture, $matches_compiler, $replacements_compiler) {
            expect_same($name . ' default matches compiler', $fixture['matches'], $matches_compiler->invoke(null));
            expect_same($name . ' default replacements compiler', $fixture['replacements'], $replacements_compiler->invoke(null));
            foreach ($fixture['cases'] as $label => $case) {
                expect_same($label, $case[1], Zalomeni::texturize($case[0]));
            }
            if ($name === 'words') {
                $nbsp = "\xC2\xA0";
                expect_same('legacy plain', 'v'.$nbsp.'lese', Zalomeni::texturize_plain('v lese'));
                expect_same('plain nonstring', array('v lese'), Zalomeni::texturize_plain(array('v lese')));
            }
        });
    }

    with_options(array_merge($disabled, array(
        'zalomeni_matches' => array('/[/'),
        'zalomeni_replacements' => array('changed'),
    )), function () {
        expect_same('malformed pattern fallback', '<p>v lese</p>', Zalomeni::texturize('<p>v lese</p>'));
    });

    with_options(array_merge($disabled, array(
        'zalomeni_matches' => array('/(*NO_START_OPT)(*NO_AUTO_POSSESS)(X)(a+c)|(a+)+b/'),
        'zalomeni_replacements' => array('$2'),
    )), function () {
        $previous_limit = ini_get('pcre.backtrack_limit');
        try {
            if (ini_set('pcre.backtrack_limit', '50') === false) {
                throw new RuntimeException('Cannot set pcre.backtrack_limit for the second-pass failure fixture.');
            }
            $input = 'Xaaaaaaaaaaaaaac';
            $matches = get_option('zalomeni_matches');
            $replacements = get_option('zalomeni_replacements');
            $first = preg_replace($matches, $replacements, $input);
            expect_same('second-pass fixture first result', 'aaaaaaaaaaaaaac', $first);
            expect_same('second-pass fixture first error', PREG_NO_ERROR, preg_last_error());
            $second = preg_replace($matches, $replacements, $first);
            expect_same('second-pass fixture second result', null, $second);
            expect_same('second-pass fixture second error', PREG_BACKTRACK_LIMIT_ERROR, preg_last_error());
            expect_same('second-pass failure restores complete original input', $input, Zalomeni::texturize($input));
        } finally {
            ini_set('pcre.backtrack_limit', $previous_limit);
        }
        expect_same('PCRE limit restored', $previous_limit, ini_get('pcre.backtrack_limit'));
    });

    echo 'PASS legacy' . "\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL ' . $group . ': ' . $error->getMessage() . "\n");
    exit(1);
}
