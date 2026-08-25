# FLC Anniversaries

A WordPress plugin that displays club member milestone anniversaries for the current month. Only members whose years of membership are divisible by 5 (5, 10, 15, 20, …) are shown, grouped by milestone in separate sections.

Membership data comes from a monthly roster report uploaded by an admin, not a file living on the server. Only three fields ever reach the database: first name, last initial, and anniversary date. Everything else in the uploaded report — last name, email, address, phone, etc. — is read only long enough to decide who's active and in-chapter, and is discarded the moment ingestion finishes.

## Installation

1. Copy the plugin folder into `wp-content/plugins/`.
2. Run `npm install && npm run build` to compile the stylesheet.
3. Activate the plugin in **Plugins** in the WordPress admin (this creates the `flc_anniversary_members` database table).

## Configuration

Go to **Settings > FLC Anniversaries**.

### Included chapters

The uploaded report is a PCA National *region* report, which can span multiple chapters. Set a comma-separated list of chapter codes (matching the report's `CHAPTER` column, e.g. `FLC`) to control which rows get ingested. Rows for any other chapter present in an upload are skipped and called out in the upload summary rather than treated as an error — useful if National ever changes what a report includes.

### Uploading the monthly report

Upload the full CSV export from PCA National. On upload, the plugin:

1. Parses every row.
2. Keeps only rows where `STATUS` is `Active` and `CHAPTER` is in your configured list.
3. Replaces the entire anniversaries table with that set — so members who lapsed since the last upload disappear, and new members appear, automatically.
4. Deletes the uploaded file.

A bad or empty upload (e.g. wrong file, no matching active members) never wipes out the existing anniversary list — the table is only replaced once a non-empty set of active, in-chapter members has been parsed.

The settings page shows a summary of the last upload: how many members were ingested, how many rows were skipped for being inactive or in another chapter, and any row-level parse errors (missing/invalid fields). These messages never include PII — only line numbers and the reason a row didn't parse.

## Roster report format

The expected CSV is PCA National's region report export — 27 columns including `CHAPTER`, `STATUS`, `LAST_NAME`, `FIRST_NAME`, and `ANNIVERSARY_DATE` (dates in `M/D/YYYY` format). Only those four columns are read; the rest (email, address, phone, vehicle info, etc.) are ignored.

`ANNIVERSARY_DATE` — not `JOIN_DATE` — is what milestone years are computed from, since it reflects the current membership term rather than the original signup date.

## Usage

Add the shortcode `[flc_anniversaries]` to any page or post where you want the anniversary list to appear, or use the FLC Anniversaries block.

## Development

```bash
npm install          # install dependencies
npm run build        # compile CSS to build/
npm run start         # watch mode
npm run lint:css     # lint stylesheet
npm run plugin:zip   # build and create deployable zip

composer install     # install PHP dev dependencies (PHPUnit)
vendor/bin/phpunit    # run the PHP test suite
```

### Test fixtures

- `data/report-valid.csv` / `data/report-invalid.csv` — small, hand-crafted fixtures with deterministic milestone members and documented error cases, used by `tests/ReportParserTest.php`.
- `data/FLC-Active-and-Expired-Members - TEST ANON.csv` — a larger (1000+ row), fully synthetic fixture spanning many chapters, statuses, and years, used as a volume/integration check. No real member data.

### Architecture

- `FLC_Anniversaries_Report_Parser` — pure PHP; parses the report CSV into active, in-chapter members reduced to the three non-PII fields. No WordPress dependency.
- `FLC_Anniversaries_Milestone_Calculator` — pure PHP; groups members into milestone-year buckets for a given month/year.
- `FLC_Anniversaries_DB` — `$wpdb`-backed storage for the `flc_anniversary_members` table (created via `dbDelta` on activation).
- `FLC_Anniversaries_Ingestion_Service` — orchestrates parsing + storage for an upload, and guarantees the uploaded file is deleted.
- `FLC_Anniversaries_Settings_Page` — admin UI for chapter configuration and roster upload.
- `FLC_Anniversaries_Block_Renderer` — renders the block/shortcode output from the database.
