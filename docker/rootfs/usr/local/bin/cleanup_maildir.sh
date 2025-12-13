#!/usr/bin/env sh
set -eu

BASE="/var/www/opentrashmail/data"
LOGFILE="/var/www/opentrashmail/logs/cleanup_maildir.log"

if [ ! -d "$BASE" ] || [ "$BASE" = "/" ]; then
  echo "$(date -Iseconds) [ERROR] Invalid BASE directory: $BASE" >> "$LOGFILE"
  exit 1
fi

echo "$(date -Iseconds) [INFO] Running cleanup script..." >> "$LOGFILE"

find "$BASE" -mindepth 1 -maxdepth 1 -type d -mmin +15 -print0 |
  while IFS= read -r -d '' dir; do
    if [ "$dir" = "$BASE" ] || [ ! -d "$dir" ]; then
      echo "$(date -Iseconds) [WARN] Skipping invalid dir: $dir" >> "$LOGFILE"
      continue
    fi

    echo "$(date -Iseconds) [INFO] Deleting expired mailbox: $dir" >> "$LOGFILE"

    if rm -rf -- "$dir"; then
      echo "$(date -Iseconds) [INFO] Successfully deleted: $dir" >> "$LOGFILE"
    else
      echo "$(date -Iseconds) [ERROR] Failed to delete: $dir" >> "$LOGFILE"
    fi
  done
