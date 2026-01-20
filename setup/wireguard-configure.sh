#!/usr/bin/env bash
set -euo pipefail

STATUS_FILE="/var/www/daloradius/var/log/wireguard-config.status.json"
STATUS_RUN_ID="manual_wg_$(date +%s)"
CLIENT_KEY_FILE="/var/www/daloradius/var/log/wireguard-client.key"
SERVER_KEY_FILE="/var/www/daloradius/var/log/wireguard-server.pub"
CLIENT_PUBKEY=""
write_status() {
  printf "{\"status\":\"%s\",\"time\":%s,\"run_id\":\"%s\"}\\n" "$1" "$(date +%s)" "$STATUS_RUN_ID" > "$STATUS_FILE"
}

mkdir -p "$(dirname "$STATUS_FILE")"
write_status "running"
trap "write_status \"failed\"" ERR

if [ "$(id -u)" -ne 0 ]; then
  echo "This script must be run as root."
  exit 1
fi

if [ -f "$CLIENT_KEY_FILE" ]; then
  CLIENT_PUBKEY="$(head -n 1 "$CLIENT_KEY_FILE" | tr -d "[:space:]")"
fi
if [ -z "$CLIENT_PUBKEY" ]; then
  CLIENT_PUBKEY="PASTE_MIKROTIK_PUBLIC_KEY"
fi

missing_pkgs=()
for pkg in wireguard wireguard-tools iptables; do
  if ! dpkg -s "$pkg" >/dev/null 2>&1; then
    missing_pkgs+=("$pkg")
  fi
done

if [ "${#missing_pkgs[@]}" -gt 0 ]; then
  apt-get update
  apt-get install -y "${missing_pkgs[@]}"
fi

umask 077
mkdir -p /etc/wireguard
if [ ! -f /etc/wireguard/wg0.key ]; then
  wg genkey | tee /etc/wireguard/wg0.key | wg pubkey > /etc/wireguard/wg0.pub
else
  wg pubkey < /etc/wireguard/wg0.key > /etc/wireguard/wg0.pub
fi
mkdir -p "$(dirname "$SERVER_KEY_FILE")"
cp /etc/wireguard/wg0.pub "$SERVER_KEY_FILE"
chmod 600 /etc/wireguard/wg0.key
chmod 644 /etc/wireguard/wg0.pub

cat > /etc/wireguard/wg0.conf <<EOF_WG
[Interface]
Address = 10.200.200.1/24
ListenPort = 51820
PrivateKey = $(cat /etc/wireguard/wg0.key)
PostUp = sysctl -w net.ipv4.ip_forward=1
PostUp = iptables -A FORWARD -i %i -j ACCEPT; iptables -A FORWARD -o %i -j ACCEPT
PostDown = iptables -D FORWARD -i %i -j ACCEPT; iptables -D FORWARD -o %i -j ACCEPT

[Peer]
PublicKey = ${CLIENT_PUBKEY}
AllowedIPs = 10.200.200.2/32, 10.10.10.0/24
EOF_WG
chmod 600 /etc/wireguard/wg0.conf

cat > /etc/sysctl.d/99-wireguard.conf <<EOF_SYS
net.ipv4.ip_forward=1
EOF_SYS
sysctl --system

systemctl enable wg-quick@wg0
systemctl restart wg-quick@wg0

write_status "success"
