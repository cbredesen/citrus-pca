# Plugin Review Findings

**Scope:** `flc-anniversaries` and `flc-file-include` in `plugins/`
**Reviewer:** GLM (OpenCode)
**Date:** 2026-10-17
**Nature:** Read-only recommendations — no source files were modified.

---

# `flc-file-include`

## Security

### 1. Rendered HTML is injected wholesale — only `<style>` tags are stripped (highest-impact finding)

`includes/class-block-renderer.php:93` injects the file contents raw into the page after removing `<style>` elements and `style=""` attributes. `<script>`, `<iframe>`, `<object>`, `on*` event handler attributes (`onclick`, `onerror`, …), and `javascript:` hrefs all pass through untouched, so any script in the included file executes in the origin of the site.

These files are produced by third-party tooling (the AXWare timing software sitting in the plugin root is an example) and land in a directory under `wp-content/uploads/` by default. If the configured directory is writable by anyone other than a trusted admin — other admins (who are unrestricted by `unfiltered_html` anyway), a compromised hosting account, or FTP-tamper — this becomes a stored-XSS/defacement vector on every page using the block.

**Recommendations:**

- Extend the DOM cleanup to remove `<script>`, `<iframe>`, `<object>`, `<embed>`, `<form>`, any attribute starting with `on`, and neutralize `javascript:`/`data:` URLs — or, better, run the extracted body through a proper sanitizer (e.g. `wp_kses` with a generous-but-allowlisted tag set) instead of DOM surgery.
- At minimum, document explicitly that included files are fully trusted and must live in an admin-controlled directory.

### 2. Directory option isn't validated at read time

`sanitize_directory()` rejects `..`, but `flc_file_include_get_directory()` (main plugin file, line 27) doesn't. If the option is ever changed outside `register_setting` — direct DB edit, WP-CLI, a migration script — the renderer's containment check is relative to whatever directory the option names, not to `ABSPATH`, so a `..`-laden value could resolve outside the WordPress root and outside uploads.

**Recommendation:** Apply the same `..` check (or return the default) in the getter. The `realpath()` containment in the renderer is otherwise solid — no change needed there.

### 3. "View raw file" link exposes the included file directly to everyone

That's inherent to the feature (the content is on the page anyway), but worth double-checking that whatever directory is configured does not accidentally contain files that are *not* meant for public display, since anything in there is both servable by URL and auto-selectable in "latest" mode.

**Recommendation:** A note in the settings UI (like the PII note in the other plugin) would prevent a future mistake.

### 4. PII test artifact in the plugin root

`citrus-ax-2026-03-19.html` is excluded from `plugin:zip` by a hardcoded filename (package.json, line 11). That's brittle — the next test file dropped into the root gets deployed and publicly served from `wp-content/plugins/`.

**Recommendation:** Move dev artifacts into a clearly-named dev folder excluded by pattern (e.g. `-x 'flc-file-include/dev/*'`), or a shareable ignore convention for both plugins' zip scripts.

## Code quality

- **No `uninstall.php`.** The `flc_file_include_directory` option persists after deletion, unlike its sibling plugin which cleans up properly. Add one.
- **Hardcoded version strings in three places** (`1.1.0` in the main plugin file twice, `block.json`, `package.json`). The sibling plugin already drifts (see below). Read the dependency version from the generated `build/index.asset.php` or define a single version constant and use it.
- **Stale-build risk:** `build/` is committed but nothing catches a dirty build. Consider a CI lint/build-check step.
- **No unit tests** for the renderer/scanner, unlike `flc-anniversaries`, which has a solid test suite. `get_files()` ordering, the path-containment logic, and `extract_body()` are all very testable in isolation.
- `usort` with subtraction (`$b['modified'] - $a['modified']`) is fine on 64-bit PHP, but the spaceship operator (`$b['modified'] <=> $a['modified']`) is the more idiomatic and overflow-safe form.

---

# `flc-anniversaries`

## Security

Overall the PII posture here is genuinely good — the parser keeps all contact fields out of the return value, the uploaded file is deleted unconditionally even on failure, and there's nothing sensitive in the DB. The test CSVs in `data/` are properly anonymized (nodomain.com, 555 numbers). The remaining items are minor:

### 1. `replace_all()` commits partially-filled data on mid-loop failure

`class-db.php:82-108`: the transaction never rolls back. If an insert fails partway through, whatever succeeded gets committed (`$wpdb->insert` failures are silently skipped, only counted). For a "replace everything" semantic, consider either a single multi-row `INSERT`, or rolling back / erroring out when `$inserted !== count($members)` so a corrupt upload can't silently leave a truncated roster.

### 2. Upload handler skips the canonical `$_FILES['error']` check

`class-settings-page.php:130-135`: `is_uploaded_file()` covers the common failure modes (including size-exceeded, where PHP drops the tmp file), so this is functionally OK, but checking `$_FILES[self::UPLOAD_FIELD]['error'] === UPLOAD_ERR_OK` first would give better, more specific error messages ("file exceeds upload_max_filesize" vs "no file uploaded").

## Code quality

- **Version inconsistency:** plugin header says `2.0.0`, `package.json` says `1.2.0`, and `src/block.json` (so `build/block.json`) still says `1.2.0`. Either the shipped build is stale relative to src, or the versions were never synced. Worth reconciling and then adopting the single-source-of-truth approach suggested above. Same drift pattern exists for the hardcoded `'2.0.0'` asset version at `flc-anniversaries.php:37`.
- **Timezone:** `date( 'n' )`/`date( 'Y' )` in the block renderer uses server time, not the site's configured timezone. Use `wp_date()` or `(int) current_time( 'n' )` so "this month's anniversaries" matches what members expect.
- **i18n inconsistency:** the settings page mixes untranslated hardcoded English strings (`'Enter at least one chapter code.'`, `'Comma-separated chapter codes…'`, the "Cleared %d anniversary record(s)." notice) with properly-escaped `__()`/`esc_html__()` calls elsewhere, despite declaring a text domain. Pick one; the codebase is mostly English literals, so either translate consistently or drop the domain.
- **Docblock nit:** `class-settings-page.php:6-13` header says "Two independent things live here" then lists three.
- **Robustness edge case:** `maybe_create_table()` early-returns if the *option* matches, so a manually-dropped table is never recreated until uninstall/reinstall. Rare, but a cheap fix is verifying the table exists (`SHOW TABLES LIKE`) before trusting the version option.
- **Checked, no issue:** `skipped_chapters` count keys come straight from the CSV but are correctly escaped at output.

---

# Cross-plugin

1. **Single source of truth for versions** — both plugins maintain 3–4 copies of the version string (header, block.json, package.json, hardcoded asset version), and both have already drifted. A shared convention (constant in the main file, or `get_plugin_data()` at init) would eliminate the whole mismatch class.
2. **Zip/deploy scripts** could share exclusion patterns so test artifacts and dev files can't slip into a production zip.
3. **Things done well that are worth keeping:**
   - Dynamic-block + server-render pattern.
   - Nonce + capability checks everywhere (upload, clear, settings, REST).
   - REST endpoint correctly gated to `edit_posts`.
   - Path-traversal defense in the renderer (`wp_basename` + `realpath` containment).
   - PII minimization in the roster pipeline.
   - Test suite for the anniversaries parsing/milestone logic.

---

# Suggested priority

1. `flc-file-include`: HTML output sanitizer (strip `<script>`/`<iframe>`/event handlers, or `wp_kses` pass) — security.
2. `flc-anniversaries`: `replace_all()` rollback/atomicity — data integrity.
3. Version-string reconciliation in both plugins — quality.
4. The rest as capacity allows.
