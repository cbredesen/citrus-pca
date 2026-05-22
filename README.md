# citrus-pca

WordPress plugin development and site maintenance tooling for **flc.pca.org**.

## Plugins

| Directory | Description |
|---|---|
| `flc-anniversaries/` | Displays club member milestone anniversaries for the current month |
| `flc-file-include/` | Gutenberg block that includes HTML files from a server-side directory |

Each plugin has its own `README.md` and `CLAUDE.md`.

## Pulling Production to Local

`bin/pull-prod.sh` downloads the latest UpdraftPlus backup from production via FTP and restores it into a [Local (LocalWP)](https://localwp.com) site.

### First-time setup

```bash
cp bin/.env.example bin/.env
```

Edit `bin/.env` and fill in:
- `FTP_HOST`, `FTP_USER`, `FTP_PASS` — your FTP credentials for flc.pca.org
- `REMOTE_BACKUP_DIR` — path on the server where UpdraftPlus stores backups (browse via FTP to confirm; typically `/public_html/wp-content/updraft`)
- `LOCAL_SITE_ROOT` — absolute path to your Local site root (the folder containing `wp-config.php`)

`bin/.env` is gitignored and will never be committed.

### Usage

Run from Local's **Open Site Shell** (so `wp` resolves to Local's bundled wp-cli), or set `WP_CLI` in `bin/.env` to the full path of your wp-cli binary.

```bash
# Full restore — files (plugins, themes, uploads) + database
./bin/pull-prod.sh

# Database only — faster, use when only content has changed
./bin/pull-prod.sh --db-only

# Files only — skips database import
./bin/pull-prod.sh --files-only

# Preview without writing anything
./bin/pull-prod.sh --dry-run

# List available backup sets on the server
./bin/pull-prod.sh --list

# Restore a specific backup set instead of the latest
./bin/pull-prod.sh --set backup_2026-04-13-0400_flc...
```

After a full restore, the script automatically runs `wp search-replace` to swap production URLs for your local URL.
