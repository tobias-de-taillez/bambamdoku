#!/usr/bin/env bash
# Hilfe-Meldungen („Report Bug / Request Feature“) vom Hetzner-Webhosting abholen.
#
# Spiegelt bambamdoku-app/storage/app/private/hilfe/ nach ~/bambamdoku-hilfe/
# (anpassbar über BAMBAMDOKU_HILFE). Auf dem Server wird nichts verändert (kein Upload).
# --delete wirkt nur lokal: Was auf dem Server gelöscht ist (Frist, Widerspruch),
# verschwindet auch aus der Kopie, wie es der Datenschutzhinweis zusagt. Aufgeräumt wird dort von
# `verein:hilfe-aufraeumen` (siehe docs/DEPLOY.md).
#
# Passwort kommt wie beim Deploy aus dem macOS-Schluesselbund (hetzner-irazari-ftp),
# siehe Kopf von scripts/deploy-hetzner.sh.
set -euo pipefail
cd "$(dirname "$0")/.."

FTP_USER=ebrh7y
FTP_HOST=www795.your-server.de
REMOTE_DIR=bambamdoku-app/storage/app/private/hilfe
TARGET="${BAMBAMDOKU_HILFE:-$HOME/bambamdoku-hilfe}"

PW="$(security find-generic-password -s hetzner-irazari-ftp -a "$FTP_USER" -w 2>/dev/null)" || {
  echo "Kein Passwort im Schlüsselbund. Einmalig ablegen:" >&2
  echo "  security add-generic-password -U -s hetzner-irazari-ftp -a $FTP_USER -w \"\$(pbpaste)\"" >&2
  exit 1; }

mkdir -p "$TARGET"
echo "==> Hole $REMOTE_DIR nach $TARGET"
LFTP_PASSWORD="$PW" lftp --env-password -u "$FTP_USER" "$FTP_HOST" -e "
set cmd:fail-exit true; set dns:order inet; set net:timeout 15; set net:max-retries 2;
set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate true;
mirror --only-newer --delete --parallel=4 --no-perms $REMOTE_DIR $TARGET;
quit"

COUNT="$(find "$TARGET" -name meldung.json | wc -l | tr -d ' ')"
echo "Fertig: $COUNT Meldung(en) in $TARGET"
# Die zehn neuesten Ordner (<Datum>/<Uhrzeit>-<Kennung>)
ls -1d "$TARGET"/*/* 2>/dev/null | sort -r | head -10 | while read -r dir; do
  echo "  ${dir#"$TARGET"/}"
done
