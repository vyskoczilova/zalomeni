# Zalomení

[![Tests](https://github.com/vyskoczilova/zalomeni/actions/workflows/tests.yml/badge.svg)](https://github.com/vyskoczilova/zalomeni/actions/workflows/tests.yml)

A WordPress plugin that applies Czech typographic rules to your site's output, replacing ordinary spaces with non-breaking spaces wherever Czech typography forbids a line break.

**[Zalomení on WordPress.org →](https://wordpress.org/plugins/zalomeni/)**

Maintained by [Karolína Vyskočilová](https://profiles.wordpress.org/vyskoczilova/#content-plugins). Originally created by Honza Skýpala.

## What it does

Zalomení hooks WordPress output filters and inserts `&nbsp;` where a line break would be wrong:

- **Prepositions and conjunctions** — single letters like *k*, *s*, *v*, *z*, *a*, *i*, *o*, *u* must not end a line
- **Abbreviations** — *např.*, *tj.*, *tzv.*
- **Numbers and units** — *5 kg*, *10 Kč*
- **Number formatting** — phone numbers like *800 123 456* stay together
- **Ordinal numbers** — dates like *1. ledna*
- **Scales and ratios** — *1 : 50 000*
- **Custom terms** — your own multi-word terms

Every list is editable under **Settings → Reading**, so the plugin adapts to Slovak and other closely related languages without any code changes.

Content is rewritten on output only — nothing in the database is modified, and deactivating the plugin removes every inserted non-breaking space.

## Requirements

| | |
|---|---|
| WordPress | 6.0+ |
| PHP | 7.0+ |
| License | GPL-2.0-or-later |

## Extending

The `zalomeni_filtry` filter controls which WordPress hooks Zalomení attaches to:

```php
add_filter( 'zalomeni_filtry', 'remove_title_from_zalomeni' );
function remove_title_from_zalomeni( array $filters ) {
	unset( $filters['the_title'] );
	return $filters;
}
```

By default it applies to `comment_author`, `term_name`, `link_name`, `link_description`, `link_notes`, `bloginfo`, `wp_title`, `widget_title`, `term_description`, `the_title`, `the_content`, `the_excerpt`, `comment_text`, `single_post_title`, and `list_cats`.

## Development

```bash
composer install
vendor/bin/phpunit
```

The suite is 63 tests / 124 assertions using [WP_Mock](https://github.com/10up/wp_mock) with PHPUnit 9.6. Running the tests needs PHP 7.4+; the plugin itself targets PHP 7.0+.

See [CLAUDE.md](CLAUDE.md) for the pre-release checklist, the distribution payload, and the automated WordPress.org deploy flow.

## Reporting security issues

Please report security bugs through the [Patchstack Vulnerability Disclosure Program](https://patchstack.com/database/vdp/9e5fc6bf-7462-4a23-a890-9bf16e3d30ca) rather than the public issue tracker. Patchstack will handle verification, CVE assignment, and notifying the maintainer.

## Changelog

The full changelog lives in [readme.txt](readme.txt). Highlights:

- **2.0.1** — fixed a stray non-breaking space in English possessives (`America&#8217;s`) and a leading-boundary anchor that never matched, so prepositions opening a paragraph now get their `&nbsp;`
- **2.0.0** — security release under a new maintainer: sanitize callbacks on every setting, regex inputs escaped, admin output escaped, PHPUnit coverage added, license changed from WTFPL to GPL-2.0-or-later
