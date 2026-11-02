# Deploy auf Hetzner-Webhosting

`scripts/deploy-hetzner.sh` baut lokal und lädt per FTPS hoch. Auf dem Server läuft kein Build.

| Server-Pfad (FTP-Home)    | Inhalt                                                                                              |
| ------------------------- | --------------------------------------------------------------------------------------------------- |
| `bambamdoku-app/`         | Laravel-Code, `vendor/`, `storage/`, `.env`, ggf. SQLite-Datei. Nicht im Web erreichbar.            |
| `public_html/bambamdoku/` | Nur `public/` (Docroot von bambamdoku.irazari-te.ch). `index.php` zeigt auf `../../bambamdoku-app`. |

## Einmalig einrichten (erledigt am 2026-11-02)

1. konsoleH → PHP-Konfiguration: **PHP 8.4** (gilt für den ganzen Webspace; die anderen Seiten dort nutzen kein PHP).
2. FTP-Passwort im Schlüsselbund ablegen (siehe Kopf des Skripts).
3. `scripts/deploy-hetzner.sh` ausführen.
4. `bambamdoku-app/.env` per FTP hochladen (Rechte 600). Wichtig: `APP_ENV=production`, `APP_DEBUG=false`, `APP_KEY`, `APP_URL`, `ADMIN_EMAIL` / `ADMIN_PASSWORD`. `DB_DATABASE` leer lassen, dann nutzt Laravel `database/database.sqlite`.
5. konsoleH → Cronjob Manager: `* * * * * /usr/bin/php84 /usr/home/ebrh7y/bambamdoku-app/cron.php`, exklusive Ausführung.

Das Admin-Passwort liegt im macOS-Schlüsselbund:

```bash
security find-generic-password -s bambamdoku-admin -w
```

## Warum ein Cronjob statt SSH

Webhosting S hat keine SSH-Shell (SFTP ja, Befehle nein). `cron.php` übernimmt deshalb jede Minute, was sonst per SSH liefe: SQLite-Datei anlegen, `migrate --force`, ersten Admin seeden (nur solange es keinen Benutzer gibt) und `schedule:run`. Nach einem Deploy mit neuen Migrationen sind diese also spätestens nach einer Minute eingespielt.

## Pfade

PHP sieht den Webspace unter `/usr/www/users/ebrh7y/` (= `public_html`), das Home unter `/usr/home/ebrh7y/`. Das generierte `index.php` nutzt deshalb den absoluten Pfad `/usr/home/ebrh7y/bambamdoku-app`.

## Sicherheitsnetze

- `scripts/deploy-hetzner.sh` liefert nur aus, wenn `HEAD` gleich `origin/main` ist, nichts uncommittet ist und die CI für diesen Commit grün war. Notfall-Ausnahme: `DEPLOY_OHNE_CI=1`.
- `cron.php` legt die Datenbank und den ersten Admin nur beim allerersten Start an und schreibt dann `storage/app/private/installed`. Fehlt die Datenbank später, bricht der Cronjob mit einer Fehlermeldung ab, statt still eine leere anzulegen. Wiederherstellen: eine Datei aus `storage/app/private/backups` nach `database/database.sqlite` kopieren. Restore-Test am 03.11.2026: Sicherung per `VACUUM INTO` erzeugt, als Datenbank eingesetzt – Integrität, Migrationsstand, generierte Spalte `active_key` und Unique-Indizes der Kladde bleiben erhalten.

## Jedes weitere Deploy

```bash
scripts/deploy-hetzner.sh
```

`.env`, Logs, Sessions, Cache, Sicherungen (`storage/app/private/backups`) und `database/*.sqlite` auf dem Server werden nie überschrieben oder gelöscht.

## Offen

- Mailversand: `MAIL_MAILER=log`, Mails landen also nur im Log. Für Einladungen (M1) braucht es SMTP-Zugangsdaten.
