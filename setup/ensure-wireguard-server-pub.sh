#!/usr/bin/env bash
set -euo pipefail

WG_DIR="/etc/wireguard"
SERVER_PUB="/var/www/daloradius/var/log/wireguard-server.pub"

if [ "$(id -u)" -ne 0 ]; then
  echo "This script must be run as root."
  exit 1
fi

if [ ! -f "${WG_DIR}/wg0.key" ]; then
  umask 077
  mkdir -p "${WG_DIR}"
  wg genkey | tee "${WG_DIR}/wg0.key" | wg pubkey > "${WG_DIR}/wg0.pub"
else
  wg pubkey < "${WG_DIR}/wg0.key" > "${WG_DIR}/wg0.pub"
fi

mkdir -p "$(dirname "$SERVER_PUB")"
cp "${WG_DIR}/wg0.pub" "${SERVER_PUB}"
chmod 600 "${WG_DIR}/wg0.key"
chmod 644 "${WG_DIR}/wg0.pub" "${SERVER_PUB}"

echo "OK: $(cat "$SERVER_PUB")"
