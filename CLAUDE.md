# Zalomení — Development Notes

## Before Release

- [ ] Run `wp plugin check zalomeni` (Plugin Check / PCP) and fix any errors
- [ ] Run `vendor/bin/phpunit` — all 60 tests must pass
- [ ] Test on a live WordPress install (activate, check Settings → Reading, verify non-breaking spaces in post content)
- [ ] Verify the admin credit box displays correctly
- [ ] Check `readme.txt` with the [WordPress Readme Validator](https://wordpress.org/plugins/developers/readme-validator/)
- [ ] Bump `Stable tag` in `readme.txt` and `Version` in `zalomeni.php` header + `Zalomeni::version` constant

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

Tests use WP_Mock (10up/wp_mock ^1.0) with PHPUnit 9.6. PHP 7.4+ required.

## Project Structure

- `zalomeni.php` — main plugin file (single-file plugin)
- `uninstall.php` — cleans up all options on plugin deletion
- `tests/` — PHPUnit tests (SecurityTest + TexturizeTest)
- `readme.txt` — WordPress.org readme (must be in English)
- `description-cs.txt` — original Czech description (dev reference, excluded from dist)
- `.distignore` — files excluded from the WordPress.org SVN/ZIP distribution
