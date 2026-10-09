#!/usr/bin/env bash
# Zad Saudi — database backup BEFORE any content change. Run from the WordPress root (where wp-config.php is).
set -euo pipefail
STAMP=$(date +%Y%m%d-%H%M%S)
DIR="${HOME}/zad-backups"; mkdir -p "$DIR"
OUT="$DIR/zad-db-$STAMP.sql"
wp db export "$OUT" --add-drop-table
gzip -9 "$OUT"
ls -lh "$OUT.gz"
# quick sanity: the dump must contain the posts table and a reasonable size
zcat "$OUT.gz" | grep -c "CREATE TABLE .*posts" | xargs -I{} echo "posts table definitions found: {}"
echo "Backup OK: $OUT.gz  (keep a copy OFF the server too)"
