# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

A WordPress plugin that ingests PCA National's monthly region-report CSV (admin-uploaded), stores each active in-chapter member's first name, last initial, and anniversary date in a custom database table, and renders a page listing all anniversaries for the current month where the years of membership are divisible by 5 (5, 10, 15, 20, etc.).

## Architecture

Pure PHP WordPress plugin — no Node.js build step for the PHP; a small `@wordpress/scripts` build exists only for the Gutenberg block's CSS/JS registration (`src/` → `build/`).

- `FLC_Anniversaries_Report_Parser` (`includes/class-report-parser.php`) — pure PHP, no WordPress dependency. Parses the region-report CSV, keeps only rows where `STATUS` is Active and `CHAPTER` is in the configured list, and returns each as `{first_name, last_initial, anniversary_date}` — no other column (name, email, address, phone, etc.) is ever returned.
- `FLC_Anniversaries_Milestone_Calculator` (`includes/class-milestone-calculator.php`) — pure PHP. Groups a member list into milestone-year buckets for a given month/year (years-since-anniversary divisible by 5).
- `FLC_Anniversaries_DB` (`includes/class-db.php`) — `$wpdb`-backed storage for the `{$wpdb->prefix}flc_anniversary_members` table, created via `dbDelta` on activation. `FLC_Anniversaries_DB_Interface` exists so ingestion can be unit tested against a fake.
- `FLC_Anniversaries_Ingestion_Service` (`includes/class-ingestion-service.php`) — orchestrates parse → replace-table, and unconditionally deletes the uploaded file afterward.
- `FLC_Anniversaries_Settings_Page` (`includes/class-settings-page.php`) — admin UI: configured chapter list (plugin-level, not block-level) and the roster upload form; shows a summary of the last ingest (counts only, no PII).
- `FLC_Anniversaries_Block_Renderer` (`includes/class-block-renderer.php`) — WordPress-glue only; queries the DB for the requested month and renders via the milestone calculator. Not unit tested (mirrors `class-settings-page.php` in depending on WP globals).

## Key Requirements

- **Data source:** Admin uploads the full PCA National region-report CSV (27 columns) each month via **Settings > FLC Anniversaries**. The file is parsed and discarded in the same request — never written to disk permanently.
- **Chapter scope:** Configurable at the plugin level (not per-block) via a comma-separated list of chapter codes (default `FLC`). Rows for other chapters are skipped and reported in the upload summary, not treated as errors.
- **Anniversary date:** Milestones are computed from `ANNIVERSARY_DATE`, not `JOIN_DATE`.
- **Sync strategy:** Each upload is a full replace — the table is wiped and repopulated from that upload's active, in-chapter members. This is what "removes expired members while adding new members" means in practice. A parse that yields zero members never wipes the table (protects against a bad/empty upload nuking good data).
- **PII handling:** Only `first_name`, `last_initial`, `anniversary_date` are ever persisted. Parser error messages are line-number + reason only, never a name/email/address, so the last-ingest summary is safe to keep around indefinitely.
- **Filter logic:** show only members where `(current_year - anniversary_year) % 5 === 0` AND `current_month === anniversary_month`.
- **Grouping:** group results by anniversary milestone (5 years, 10 years, etc.), each in its own semantic HTML section.
- **Layout:** names displayed in multiple columns that adapt to page width (CSS `column-count` or CSS Grid).
- **Output mechanism:** WordPress shortcode (`[flc_anniversaries]`) and a Gutenberg block, both backed by the same render callback.

## Test Fixtures (`data/`)

- `report-valid.csv` / `report-invalid.csv` — small, hand-crafted, deterministic fixtures (documented inline in `tests/ReportParserTest.php`) for precise milestone/error-line assertions.
- `FLC-Active-and-Expired-Members - TEST ANON.csv` — ~1000 rows of fully synthetic data spanning multiple chapters/statuses/years, used as a volume/integration check (cross-verified against an independent tally in the test, not hardcoded names). No real member data.

## WordPress Plugin Conventions

- Plugin header comment block in the main PHP file is required for WordPress to recognize the plugin.
- Table creation/upgrade goes through `dbDelta()`, gated on a stored `flc_anniversaries_db_version` option so it only runs when the schema actually changes.
- Use `add_shortcode()` to register the output; avoid `the_content` filters.
- Enqueue CSS via `wp_enqueue_style()` hooked to `wp_enqueue_scripts`.
- `uninstall.php` drops the table and deletes plugin options on plugin deletion (not deactivation).
