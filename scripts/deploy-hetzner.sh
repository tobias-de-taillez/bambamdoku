#!/usr/bin/env bash
# bambamdoku auf Hetzner bringen (bambamdoku.irazari-te.ch).
# Laedt public/ per FTPS nach public_html/bambamdoku/. mirror --delete wirkt nur
# in diesem Unterverzeichnis, die anderen Seiten bleiben unberuehrt.
#
# Passwort kommt aus dem macOS-Schluesselbund. Einmalig ablegen
# (Passwort vorher in konsoleH -> Logindaten -> FTP-Hauptbenutzer kopieren):
#   security add-generic-password -U -s hetzner-irazari-ftp -a ebrh7y -w "$(pbpaste)"
set -euo pipefail
cd "$(dirname "$0")/.."

PW="$(security find-generic-password -s hetzner-irazari-ftp -a ebrh7y -w 2>/dev/null)" || {
  echo "Kein Passwort im Schlüsselbund. Einmalig ablegen:" >&2
  echo '  security add-generic-password -U -s hetzner-irazari-ftp -a ebrh7y -w "$(pbpaste)"' >&2
  exit 1; }

LFTP_PASSWORD="$PW" lftp --env-password -u ebrh7y www795.your-server.de -e "set cmd:fail-exit true; set dns:order inet; set net:timeout 15; set net:max-retries 1; set ftp:ssl-force true; set ftp:ssl-protect-data true; set ssl:verify-certificate true; mirror --reverse --delete --verbose --exclude-glob .well-known/ public public_html/bambamdoku; quit"
echo "Fertig: https://bambamdoku.irazari-te.ch"
