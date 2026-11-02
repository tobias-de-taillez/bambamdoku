# bambamdoku

Freie Software für Sportschützenvereine: Schießtage planen, Bahnen buchen, Standaufsicht sicherstellen und die Schießkladde für den Bedürfnisnachweis führen. Für einen Verein, der seine Daten selbst behält – läuft auf günstigem Webhosting.

## Was es kann

**Für Mitglieder**

- Schießtage ansehen und eine Bahn in zwei Klicks buchen; Raster zeigt freie Bahnen je Stand und Zeit
- Gäste mitbringen, Vereinswaffe angeben, selbst stornieren (bis zur Stornofrist)
- Meine Buchungen, Erinnerung am Vortag, Kalender-Abo fürs Handy
- **Schießkladde**: alle von der Aufsicht bestätigten Termine mit Auswertung nach § 14 WaffG (Erwerb: jeden Monat oder 18-mal in 12 Monaten; Besitz je Kurz-/Langwaffe: alle drei Monate oder 6-mal in 12 von 24 Monaten) und PDF-Auszug

**Für Standaufsichten**

- Aufsicht selbst übernehmen (mit gültiger Qualifikation)
- **Ohne eingeteilte Aufsicht keine Buchung** (§ 27 WaffG) – vom Server erzwungen
- Standansicht am Schießtag: Anwesenheit bestätigen, nicht erschienen, Schützen und Gäste spontan eintragen; aktualisiert sich selbst

**Für den Vorstand**

- Mitglieder anlegen, per CSV importieren, per Link einladen; Rollen, Gruppen, Mitgliedsnummer, Eintritt, Aufsichts-Qualifikation mit Ablaufdatum
- Stände mit Bahnen und zugelassenen Waffenarten; Schießtage einzeln oder als wöchentliche Serie, für alle oder bestimmte Gruppen
- Absage storniert alle Buchungen und benachrichtigt die Betroffenen
- Schießkladde des Vereins inkl. Gäste, CSV-Export, PDF-Auszug je Mitglied, Mitgliedschaftsbescheinigung (§ 14 Abs. 4 Satz 3)
- Protokoll aller Buchungen, Check-ins und Änderungen
- Vereinseinstellungen: Name, Anschrift, Buchungsraster, Stornofrist

## Technik

Laravel 13 (PHP ≥ 8.3), Inertia, Svelte 5, TypeScript, Tailwind 4. SQLite oder MariaDB. Keine Dauerprozesse nötig: ein Cronjob pro Minute reicht (Migrationen, Erinnerungen).

Die Fachregeln liegen gebündelt in `app/Domain` (`Bookings`, `Schedule`, `ShootingRecord`, `TimeWindow`).

## Lokal ausprobieren

```bash
composer setup                              # Abhängigkeiten, .env, SQLite, Frontend-Build
php artisan db:seed --class=DemoSeeder      # Beispielverein mit zwei Jahren Historie
composer dev                                # http://localhost:8000
```

Demo-Konten: `petra.vorstand@demo.test` (Admin, Aufsicht), `helga.schiesswart@demo.test` (Aufsicht), `max.mustermann@demo.test` (Mitglied) – Passwort jeweils `demo-passwort`.

Ohne Demo: `ADMIN_EMAIL` und `ADMIN_PASSWORD` in `.env` setzen und `php artisan db:seed` ausführen. Die öffentliche Registrierung ist abgeschaltet; weitere Mitglieder lädt der Vorstand ein.

## Prüfen

```bash
composer ci:check     # Formatierung, svelte-check, Pint, PHPStan, PHPUnit
```

## Betrieb

- Shared Hosting (Hetzner o. ä.): [docs/DEPLOY.md](docs/DEPLOY.md)
- Konfiguration: `.env` (`VEREIN_*`) oder in der Oberfläche unter _Verwaltung → Einstellungen_
- Mailversand: SMTP in `.env` (`MAIL_*`); ohne SMTP landen Mails im Log

## Rechtliches

Die Auswertung nach § 14 WaffG ist eine Orientierung. Maßgeblich bleibt die Bescheinigung des Schießsportverbands. Rechtsgrundlagen: [§ 14 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html), [§ 27 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__27.html), [§§ 10, 11 AWaffV](https://www.gesetze-im-internet.de/awaffv/).

## Lizenz

[Apache License 2.0](LICENSE)
