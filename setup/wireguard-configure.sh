#!/usr/bin/env bash
set -euo pipefail

STATUS_FILE="/var/www/daloradius/var/log/wireguard-config.status.json"
STATUS_RUN_ID="manual_wg_$(date +%s)"
write_status() { printf "{\"status\":\"%s\",\"time\":%s,\"run_id\":\"%s\"}\n" "$1" "$(date +%s)" "$STATUS_RUN_ID" > "$STATUS_FILE"; }
mkdir -p "$(dirname "$STATUS_FILE")"
write_status "running"
trap write_status