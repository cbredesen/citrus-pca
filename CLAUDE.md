# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This repository manages the WordPress site **flc.pca.org**. It contains custom WordPress plugins and maintenance tooling for the site.

Goals:
- Custom WordPress plugins for site functionality
- Functional/validation tests to catch regressions after site changes
- 404 link checking and general site maintenance

## Repository Structure

- `plugins/` — WordPress plugins deployed to the site. See `plugins/CLAUDE.md` for shared plugin conventions and build/deploy commands; each plugin also has its own `CLAUDE.md`:
  - `plugins/flc-anniversaries/` — displays club member milestone anniversaries for the current month (pure PHP, no build step)
  - `plugins/flc-file-include/` — Gutenberg block that includes HTML files from a server-side directory (React/Gutenberg block with `@wordpress/scripts` build toolchain)
- `utilities/` — standalone tooling that supports the site but is not itself a WordPress plugin. Each is self-contained with its own `CLAUDE.md`:
  - `utilities/mail-minder/` — containerized Node.js app for posting reminders tied to calendar events and MJML email template previews

Planned but not yet present: `themes/`, `config/`, `tests/`, `scripts/`

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

## WordPress Context

- Site URL: flc.pca.org
- Platform: WordPress (self-hosted)
- Avoid direct database edits; prefer wp-cli or WordPress APIs
- See `plugins/CLAUDE.md` for plugin build/deploy commands and WordPress plugin conventions

## wp-cli Reference

```bash
wp plugin update --all
wp theme update --all
wp export --dir=./backups
wp import file.xml --authors=create
```
