# Zalomení — Development Notes

## Before Release

- [ ] Run `wp plugin check zalomeni --severity=error` (Plugin Check / PCP) and fix any errors. Warnings on dev files (`.distignore`, `.github`, `CLAUDE.md`, `tests/`, `.phpunit.result.cache`) are expected — PCP scans the working tree, not the dist payload, and `.distignore` excludes them all from the actual WP.org release. The two `no_texturize_tags` / `no_texturize_shortcodes` warnings on `zalomeni.php:397-398` are intentional reads of WordPress core filters and can also be ignored.
- [ ] Run `vendor/bin/phpunit` — all 60 tests must pass
- [ ] Test on a live WordPress install (activate, check Settings → Reading, verify non-breaking spaces in post content)
- [ ] Verify the admin credit box displays correctly
- [ ] Validate `readme.txt` at https://wordpress.org/plugins/developers/readme-validator/
- [ ] Confirm short description (line right after the headers) is ≤150 characters: `awk 'NR==11 {print length($0)}' readme.txt`
- [ ] Bump `Stable tag` in `readme.txt` and `Version` in `zalomeni.php` header + `Zalomeni::version` constant
- [ ] Confirm `Tested up to:` in `readme.txt` is a *released* WordPress version (RCs and unreleased majors are rejected by the validator)

## Release & Deploy

The release flow is fully automated by GitHub Actions:

- `.github/workflows/deploy.yml` — runs on `release: published`. Calls `10up/action-wordpress-plugin-deploy` which packages via `.distignore`, pushes to WordPress.org SVN trunk + tag, and attaches the generated `zalomeni.zip` to the GitHub release.
- `.github/workflows/update-readme.yml` — runs on push to `main` when `readme.txt` or `.wordpress-org/**` change. Pushes only readme + assets to WP.org SVN trunk so listing updates don't require a full release.

Both workflows need two repository secrets — set them once with:

```bash
gh secret set SVN_USERNAME --body 'your-wporg-username'
gh secret set SVN_PASSWORD   # prompts; do not pass --body so it stays out of shell history
```

`SVN_USERNAME` is your wordpress.org login slug (e.g. `vyskoczilova`, not the email).

`SVN_PASSWORD` is **not** your wordpress.org account password. As of 2025, wp.org issues a separate SVN-only password that you generate at https://profiles.wordpress.org/me/profile/edit/group/3/?screen=svn-password — it's shown once, save it to your password manager. Trying to use the regular account password will silently fail with `403 Forbidden` from SVN.

To cut a release: bump version (see checklist above) → commit → `gh release create vX.Y.Z --generate-notes`. The workflow does the rest.

### First-time / manual SVN push

When the GitHub Actions deploy isn't an option (initial submission, recovery from a stuck workflow, or a wp.org review-team requested re-upload), push directly via SVN:

```bash
svn checkout https://plugins.svn.wordpress.org/zalomeni/ /tmp/zalomeni-svn

# trunk gets the dist payload (filtered through .distignore)
rsync -a --delete --exclude-from=.distignore --exclude='.git' ./ /tmp/zalomeni-svn/trunk/

# assets/ gets banners, icons, screenshots from .wordpress-org/
rsync -a .wordpress-org/ /tmp/zalomeni-svn/assets/

cd /tmp/zalomeni-svn
svn add --force trunk/* assets/*
svn status                                  # verify before committing
svn commit -m "X.Y.Z — short description" --username vyskoczilova --password '...'

# tag the release (wp.org convention)
svn copy trunk tags/X.Y.Z
svn commit -m "Tag X.Y.Z" --username vyskoczilova --password '...'
```

## Distribution Payload

The WP.org release contains exactly three files: `readme.txt`, `uninstall.php`, `zalomeni.php`. Verify before tagging:

```bash
rsync -a --delete --exclude-from=.distignore --exclude='.git' ./ /tmp/zalomeni-dist/
find /tmp/zalomeni-dist -mindepth 1
```

`.distignore` must end with a trailing newline — without it, rsync silently drops the last exclude pattern. (This bit us once, leaking `description-cs.txt` into a release.)

## Running Tests

```bash
composer install
vendor/bin/phpunit
```

Tests use WP_Mock (`10up/wp_mock ^1.0`) with PHPUnit 9.6 and require PHP 7.4+ to *run* (dev environment). The plugin itself runs on PHP 7.0+ (`Requires PHP: 7.0`) — the runtime code uses no PHP 7.4-specific syntax.

## Project Structure

- `zalomeni.php` — main plugin file (single-file plugin)
- `uninstall.php` — cleans up all options on plugin deletion
- `tests/` — PHPUnit tests (SecurityTest + TexturizeTest, 60 tests / 114 assertions)
- `readme.txt` — WordPress.org readme (must be in English)
- `description-cs.txt` — original Czech description (dev reference, excluded from dist)
- `.distignore` — files excluded from the WordPress.org SVN/ZIP distribution
- `.wordpress-org/` — banners, icons, and screenshots for the WP.org listing (uploaded to SVN `assets/`, not bundled with the plugin)
