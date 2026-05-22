# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This repository manages the WordPress site **flc.pca.org**. It contains custom WordPress plugins and maintenance tooling for the site.

Goals:
- Custom WordPress plugins for site functionality
- Functional/validation tests to catch regressions after site changes
- 404 link checking and general site maintenance

## Repository Structure

Each subdirectory is a self-contained WordPress plugin with its own `CLAUDE.md`:

- `flc-anniversaries/` — Plugin: displays club member milestone anniversaries for the current month (pure PHP, no build step)
- `flc-file-include/` — Plugin: Gutenberg block that includes HTML files from a server-side directory (React/Gutenberg block with `@wordpress/scripts` build toolchain)

Planned but not yet present: `themes/`, `config/`, `tests/`, `scripts/`

## Plugin Build Commands (for plugins with a JS build step)

Run from within the plugin directory (e.g., `cd flc-file-include`):

```bash
npm install         # Install dependencies
npm run build       # Production build -> build/
npm run start       # Watch mode for development
npm run lint:js     # ESLint
npm run lint:css    # Stylelint
npm run plugin:zip  # Build and package plugin as a .zip (excludes dev files)
```

`flc-anniversaries` is pure PHP — no build step required.

## Pulling Production to Local

`bin/pull-prod.sh` downloads the latest UpdraftPlus backup from production via FTP and restores it to a Local (LocalWP) site.

```bash
# First-time setup
cp bin/.env.example bin/.env
# Edit bin/.env with FTP credentials and your Local site path

# Full restore (files + database)
./bin/pull-prod.sh

# Database only (faster — use when only content changed)
./bin/pull-prod.sh --db-only

# Preview what would happen without changing anything
./bin/pull-prod.sh --dry-run

# List available backup sets
./bin/pull-prod.sh --list
```

**wp-cli note:** run the script from Local's "Open Site Shell" so `wp` points to Local's bundled wp-cli, or set `WP_CLI` in `bin/.env` to the full path.

## Deploying Plugins

Package a plugin for upload to WordPress via:
```bash
npm run plugin:zip   # run from within the plugin directory
```
This produces a `.zip` in the repo root (one level up from the plugin directory), excluding `node_modules`, `src`, and dev-only files.

## WordPress Context

- Site URL: flc.pca.org
- Platform: WordPress (self-hosted)
- Avoid direct database edits; prefer wp-cli or WordPress APIs
- Plugins follow standard WordPress conventions: plugin header in main `.php` file, `wp_enqueue_*` for assets, shortcodes or `register_block_type()` for output

## wp-cli Reference

```bash
wp plugin update --all
wp theme update --all
wp export --dir=./backups
wp import file.xml --authors=create
```
