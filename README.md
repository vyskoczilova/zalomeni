## Zalomeni

Zalomeni improves line breaks in WordPress by inserting non-breaking spaces after Czech prepositions, conjunctions, abbreviations, units, and custom terms.

Maintained by [vyskoczilova](https://profiles.wordpress.org/vyskoczilova/#content-plugins) (Karolina Vyskocilova). Originally created by Honza Skypala.

### Changes in 1.6.0 (reopening submission)

The plugin was closed due to security issues. All reported vulnerabilities have been fixed and the codebase has been hardened:

**Security fixes:**
- Added `sanitize_callback` to all 12 `register_setting` calls (was completely missing)
- All user-defined option lists (prepositions, units, etc.) are now escaped with `preg_quote` before being used in regex patterns — prevents regex injection
- Custom terms are escaped with `preg_quote` while selectively preserving intentional `\d`, `\w`, `\s` sequences
- Empty list items are filtered out to prevent catch-all `||` alternation in regex
- `preg_replace` return value is checked for `null` — returns original text on regex failure instead of silently corrupting content
- Admin output uses `esc_attr`, `esc_js`, `esc_textarea`, and `sanitize_key` on all dynamic values
- Replaced `dynamic constant()` calls with a static lookup array
- Replaced dependency on WP-private `_wptexturize_pushpop_element` with an inlined private method
- Added `defined('ABSPATH') || exit` direct access guard

**Standards and compatibility:**
- Added required plugin headers: `License`, `Requires at least` (6.0), `Requires PHP` (7.4)
- Removed closing `?>` PHP tag
- All comparisons use strict `===`/`!==` operators
- All static methods use `__CLASS__` callbacks (no `$this->staticMethod()` — PHP 8+ compatible)
- All admin strings are extractable by `wp i18n make-pot`
- Added `uninstall.php` to clean up all plugin options on deletion
- Updated minimum PHP version from 5.3 to 7.4

**Test suite:**
- 60 tests / 114 assertions using WP_Mock
- Security tests: sanitize callback verification, regex injection, ReDoS resistance, preg_replace error handling
- Functional tests: all replacement rules end-to-end, HTML/shortcode handling, edge cases
