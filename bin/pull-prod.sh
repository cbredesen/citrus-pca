#!/usr/bin/env bash
# pull-prod.sh — Download the latest UpdraftPlus backup from production and
# restore it into a Local (LocalWP) site.
#
# Usage:
#   ./bin/pull-prod.sh [options]
#
# Options:
#   --db-only      Import database only (skip file restore)
#   --files-only   Restore files only (skip database import)
#   --dry-run      Download and extract but do not write to the Local site
#   --list         List available backup sets and exit
#   --set NAME     Use a specific backup set name instead of the latest
#                  (NAME is the prefix like backup_2026-04-13-0400_flc...)
#   -h, --help     Show this help

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="${SCRIPT_DIR}/.env"

# ── Load config ──────────────────────────────────────────────────────────────
if [[ -f "$ENV_FILE" ]]; then
  # shellcheck source=/dev/null
  source "$ENV_FILE"
else
  echo "ERROR: ${ENV_FILE} not found. Copy bin/.env.example to bin/.env and fill it in." >&2
  exit 1
fi

: "${FTP_HOST:?Set FTP_HOST in bin/.env}"
: "${FTP_USER:?Set FTP_USER in bin/.env}"
: "${FTP_PASS:?Set FTP_PASS in bin/.env}"
: "${LOCAL_SITE_ROOT:?Set LOCAL_SITE_ROOT in bin/.env}"

REMOTE_BACKUP_DIR="${REMOTE_BACKUP_DIR:-/public_html/wp-content/updraft}"
FTP_SCHEME="${FTP_SCHEME:-ftps}"
PROD_URL="${PROD_URL:-https://flc.pca.org}"
LOCAL_URL="${LOCAL_URL:-http://flc-pca.local}"
WP_CLI="${WP_CLI:-wp}"

# ── Parse flags ──────────────────────────────────────────────────────────────
MODE="full"       # full | db-only | files-only
DRY_RUN=false
LIST_ONLY=false
SPECIFIC_SET=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --db-only)     MODE="db-only" ;;
    --files-only)  MODE="files-only" ;;
    --dry-run)     DRY_RUN=true ;;
    --list)        LIST_ONLY=true ;;
    --set)         SPECIFIC_SET="$2"; shift ;;
    -h|--help)
      sed -n '3,18p' "$0" | sed 's/^# \{0,1\}//'
      exit 0
      ;;
    *) echo "Unknown option: $1" >&2; exit 1 ;;
  esac
  shift
done

# ── Helpers ───────────────────────────────────────────────────────────────────
log()  { echo "[$(date '+%H:%M:%S')] $*"; }
die()  { echo "ERROR: $*" >&2; exit 1; }
step() { echo; echo "▶ $*"; }

FTP_BASE="${FTP_SCHEME}://${FTP_HOST}${REMOTE_BACKUP_DIR}"
CURL_OPTS=(--silent --show-error --user "${FTP_USER}:${FTP_PASS}")

ftp_list() {
  curl "${CURL_OPTS[@]}" "${FTP_BASE}/" 2>/dev/null \
    || die "FTP connection failed. Check credentials and FTP_SCHEME (currently: ${FTP_SCHEME})."
}

ftp_get() {
  local remote_name="$1" local_path="$2"
  log "  Downloading ${remote_name}..."
  curl "${CURL_OPTS[@]}" --progress-bar "${FTP_BASE}/${remote_name}" -o "$local_path"
}

# ── Discover backup sets ─────────────────────────────────────────────────────
step "Scanning ${FTP_BASE}/"
LISTING=$(ftp_list)

# Extract unique backup set prefixes (everything before -db/-plugins/etc.)
SETS=$(echo "$LISTING" \
  | awk '{print $NF}' \
  | grep -E '^backup_[0-9]{4}-[0-9]{2}-[0-9]{2}' \
  | sed -E 's/-(db|plugins|themes|uploads|others)\..+$//' \
  | sort -u)

[[ -n "$SETS" ]] || die "No UpdraftPlus backups found in ${FTP_BASE}. Check REMOTE_BACKUP_DIR."

if $LIST_ONLY; then
  echo "Available backup sets:"
  echo "$SETS" | nl -ba
  exit 0
fi

if [[ -n "$SPECIFIC_SET" ]]; then
  BACKUP_SET="$SPECIFIC_SET"
  echo "$SETS" | grep -qF "$BACKUP_SET" || die "Backup set '${BACKUP_SET}' not found. Run --list to see available sets."
else
  BACKUP_SET=$(echo "$SETS" | tail -1)
fi

log "Using backup set: ${BACKUP_SET}"

# ── Temp workspace ───────────────────────────────────────────────────────────
WORK_DIR=$(mktemp -d)
trap 'rm -rf "$WORK_DIR"' EXIT
log "Working directory: ${WORK_DIR}"

# ── Download ─────────────────────────────────────────────────────────────────
download_part() {
  local part="$1"
  # UpdraftPlus uses .zip.gz for file parts and .gz for db
  local filename
  filename=$(echo "$LISTING" | awk '{print $NF}' \
    | grep "^${BACKUP_SET}-${part}\." | head -1)

  if [[ -z "$filename" ]]; then
    log "  WARNING: no '${part}' file in this backup set — skipping"
    return
  fi
  ftp_get "$filename" "${WORK_DIR}/${filename}"
  echo "${WORK_DIR}/${filename}"
}

step "Downloading backup parts"

DB_FILE=""
if [[ "$MODE" == "full" || "$MODE" == "db-only" ]]; then
  DB_FILE=$(download_part "db")
fi

declare -A FILE_PARTS=()
if [[ "$MODE" == "full" || "$MODE" == "files-only" ]]; then
  for part in plugins themes uploads others; do
    f=$(download_part "$part")
    [[ -n "$f" ]] && FILE_PARTS[$part]="$f"
  done
fi

# ── Extract files ─────────────────────────────────────────────────────────────
extract_part() {
  local part="$1" archive="$2"
  local dest="${WORK_DIR}/extract/${part}"
  mkdir -p "$dest"

  log "  Extracting ${part}..."
  case "$archive" in
    *.zip.gz)
      local zip_file="${archive%.gz}"
      gunzip -c "$archive" > "$zip_file"
      # UpdraftPlus zips contain the directory contents directly (no wrapper dir)
      unzip -q "$zip_file" -d "$dest"
      ;;
    *.tar.gz|*.tgz)
      tar -xzf "$archive" -C "$dest"
      ;;
    *)
      die "Unrecognised archive format for ${archive}"
      ;;
  esac
  echo "$dest"
}

if [[ "$MODE" == "full" || "$MODE" == "files-only" ]]; then
  step "Extracting archives"
  declare -A EXTRACT_DIRS=()
  for part in "${!FILE_PARTS[@]}"; do
    EXTRACT_DIRS[$part]=$(extract_part "$part" "${FILE_PARTS[$part]}")
  done
fi

# ── Restore files ─────────────────────────────────────────────────────────────
if [[ "$MODE" == "full" || "$MODE" == "files-only" ]]; then
  step "Syncing files to Local site"
  WP_CONTENT="${LOCAL_SITE_ROOT}/wp-content"

  [[ -d "$WP_CONTENT" ]] || die "wp-content not found at ${WP_CONTENT}. Check LOCAL_SITE_ROOT."

  # Map backup part names to wp-content subdirectory names
  declare -A DEST_MAP=(
    [plugins]="plugins"
    [themes]="themes"
    [uploads]="uploads"
    [others]="."   # 'others' contains mu-plugins, etc. — merge into wp-content root
  )

  for part in plugins themes uploads others; do
    [[ -v "EXTRACT_DIRS[$part]" ]] || continue
    src="${EXTRACT_DIRS[$part]}/"
    dest="${WP_CONTENT}/${DEST_MAP[$part]}"

    if $DRY_RUN; then
      log "  [DRY RUN] rsync ${src} -> ${dest}"
    else
      log "  Syncing ${part} -> ${dest}"
      rsync -a --delete \
        --exclude=".DS_Store" \
        "$src" "$dest/"
    fi
  done
fi

# ── Import database ───────────────────────────────────────────────────────────
if [[ "$MODE" == "full" || "$MODE" == "db-only" ]]; then
  [[ -n "$DB_FILE" && -f "$DB_FILE" ]] || die "Database file not downloaded or not found."

  step "Importing database"
  SQL_FILE="${WORK_DIR}/database.sql"
  log "  Decompressing SQL..."
  gunzip -c "$DB_FILE" > "$SQL_FILE"

  if $DRY_RUN; then
    log "  [DRY RUN] wp db import ${SQL_FILE}"
    log "  [DRY RUN] wp search-replace '${PROD_URL}' '${LOCAL_URL}'"
  else
    log "  Importing SQL into Local site..."
    "$WP_CLI" --path="$LOCAL_SITE_ROOT" db import "$SQL_FILE"

    step "Replacing URLs"
    log "  ${PROD_URL} -> ${LOCAL_URL}"
    "$WP_CLI" --path="$LOCAL_SITE_ROOT" search-replace \
      "$PROD_URL" "$LOCAL_URL" \
      --all-tables \
      --report-changed-only

    # Also replace www variant if different
    PROD_WWW="${PROD_URL/\/\//\/\/www.}"
    if [[ "$PROD_WWW" != "$PROD_URL" ]]; then
      "$WP_CLI" --path="$LOCAL_SITE_ROOT" search-replace \
        "$PROD_WWW" "$LOCAL_URL" \
        --all-tables \
        --report-changed-only
    fi
  fi
fi

# ── Done ──────────────────────────────────────────────────────────────────────
echo
if $DRY_RUN; then
  log "Dry run complete. No changes were made to ${LOCAL_SITE_ROOT}."
else
  log "Done. Site restored to: ${LOCAL_SITE_ROOT}"
  log "Open ${LOCAL_URL} in your browser."
fi
