# bambamdoku

Freie Software für Sportschützenvereine: Schießtage planen, Bahnen buchen, Standaufsicht sicherstellen und die Schießkladde für den Bedürfnisnachweis führen. Für einen Verein, der seine Daten selbst behält – läuft auf günstigem Webhosting.

## Was es kann

**Für Mitglieder**

- Mitgliedschaft online beantragen, sobald der Verein die Registrierung freischaltet; der Vorstand nimmt auf
- Schießtage ansehen und eine Bahn in zwei Klicks buchen; Raster zeigt freie Bahnen je Stand und Zeit
- Gäste mitbringen, Vereinswaffe angeben, selbst stornieren (bis zur Stornofrist)
- Meine Buchungen auf einen Blick
- **Heute schießen**: in der Session der Aufsicht selbst eintragen – Disziplin, Schüsse, Kategorie, eigene oder Vereinswaffe; am eigenen Handy oder am Kiosk-Tablet mit eigener PIN
- **Schießkladde**: die eigenen, von der Aufsicht abgezeichneten Termine mit Schüssen, Anzahl Tage je Kategorie (alle / eigene Waffe), Auszug und Teilnahmenachweis je Disziplin zur Information – bewusst ohne Urteil „erfüllt“, bescheinigen muss der Verband
- lässt sich wie eine App auf den Home-Bildschirm legen

**Für Standaufsichten**

- **Session am Stand**: starten, Schützen aktivieren (aus der Buchungsliste, per Name oder Mitgliedsnummer, Gäste), Einträge prüfen, falsche mit Grund ablehnen, am Ende **abzeichnen** – erst dann stehen sie in der Kladde
- Das Tor sitzt an der Session: nur mit gültiger Zeile im Aufsichtsregister (§ 10 Abs. 3 AWaffV), Datum vom Server, Kurz-/Langwaffe nur mit passendem Umfang; wer beaufsichtigt, schießt nicht, gegenseitiges Abzeichnen ist ausgeschlossen
- **Kiosk**: Link oder QR-Code für ein Tablet; Mitglieder tippen Namen und PIN, Gäste und Mitglieder ohne PIN trägt die Aufsicht ein
- Kladde-Einträge werden nie bearbeitet, nur storniert; doppeltes Tippen erzeugt keinen zweiten Eintrag; die Ansicht aktualisiert sich selbst
- **Aufbewahrung** (Einstellungen): Nach der Frist des Vereins (Standard 5 Jahre) löscht ein nächtlicher Lauf Kladde, Sessions und Buchungen; Gastnamen werden nach 12 Monaten anonymisiert, auch im Protokoll. Nur ein protokollierter Lauf darf das, die Datenbank-Trigger weisen alles andere ab
- QR-Code für die Ankunft (wechselt alle 5 Minuten); Mitglieder erscheinen als „ist da“
- Aufsicht selbst übernehmen; **ohne eingeteilte Aufsicht keine Buchung** (§ 27 WaffG)
- Hinweis „Hier fehlt noch Standaufsicht“ auf der Übersicht; Hinweis vor Ablauf der eigenen Qualifikation

**Für den Vorstand**

- Mitglieder anlegen (auch ohne E-Mail und Konto), per Link einladen; **Anträge** aufnehmen (Identität und Volljährigkeit geprüft) oder ablehnen
- **Aufsichtsregister** nach § 10 Abs. 3 AWaffV: jede Prüfung eine neue Zeile mit Umfang, gültig bis, geprüft am und von
- Stände mit Bahnen und zugelassenen Waffenarten; Schießtage einzeln oder wöchentlich wiederholt angelegt; Schalter „Gastschützen erlaubt“ für die Online-Buchung
- Absage storniert alle Buchungen und benachrichtigt die Betroffenen per Mail (erst mit eingerichtetem Mailversand)
- Schießkladde des Vereins inkl. Gäste, Stornos und Korrekturen, CSV-Export, **Nachträge** und **Korrekturen** mit Pflichtgrund und Passwort (nie an eigenen Einträgen), Sessions mit Protokoll je Tag, **Auszug zur Vorlage beim Verband** und Teilnahmenachweis je Disziplin, Papier-Ersatzliste, Mitgliedschaftsbescheinigung (§ 14 Abs. 4 Satz 3)
- Protokoll aller Buchungen, Aufsichten und Änderungen
- **Rückmeldungen**: Über „Report Bug / Request Feature“ unten rechts auf jeder Seite melden Mitglieder und Gäste, was kaputt ist oder besser werden sollte – mit Bildschirmfoto und technischem Zustand der Seite (Geheimnisse geschwärzt); Liste offen/erledigt unter Verwaltung, Entwickler holen die Meldungen mit `scripts/hilfe-holen.sh` ab (docs/DEPLOY.md)
- Jahresstatistik: Besuche je Monat, Auslastung je Stand, aktivste Mitglieder, Aufsichtsdienste
- Vereinseinstellungen: Name, Anschrift, Verband, Vereinsnummer, Disziplinkatalog, Registrierung, Buchungsraster, Stornofrist; Mail-Status mit Testmail; nächtliche Datensicherung zum Herunterladen

## Technik

Laravel 13 (PHP ≥ 8.3), Inertia, Svelte 5, TypeScript, Tailwind 4. SQLite; MariaDB ist für den Echtbetrieb noch nicht freigegeben (siehe docs/UMBAU-NOTIZEN.md). Keine Dauerprozesse nötig: ein Cronjob pro Minute (`cron.php`) reicht für Migrationen, die Datensicherung (3:30 Uhr) und den Löschlauf nach den Aufbewahrungsfristen (`verein:aufbewahrung`, 4:30 Uhr; Probelauf mit `--dry-run`).

Die Fachregeln liegen gebündelt in `app/Domain`: `Sessions` (Schieß-Session, Selbsteintrag, Abzeichnen), `Logbook` (Kladde, Nachtrag, Korrektur), `Applications` (Mitgliedsantrag), `Retention` (Löschlauf nach den Aufbewahrungsfristen), `Bookings` und `Schedule` (Buchung und Aufsicht), `ShootingRecord`, `SessionProtocol`, `TimeWindow`. Die rechtlichen Invarianten prüfen `tests/Feature/SessionSignOffTest.php`, `SessionSchemaTest.php`, `LogbookTest.php`, `CorrectionTest.php` und `RetentionTest.php`.

Fachliche Grundlage und Entscheidungen: [docs/ENTSCHEIDUNGEN.md](docs/ENTSCHEIDUNGEN.md), Bauplan des Session-Modells: [docs/UMBAU-SESSION.md](docs/UMBAU-SESSION.md), Notizen zum Bau: [docs/UMBAU-NOTIZEN.md](docs/UMBAU-NOTIZEN.md).

## Lokal ausprobieren

```bash
composer setup                              # Abhängigkeiten, .env, SQLite, Frontend-Build
php artisan db:seed --class=DemoSeeder      # Beispielverein mit zwei Jahren Historie
composer dev                                # http://localhost:8000
```

Demo-Konten, Passwort jeweils `demo-passwort`, Kiosk-PIN `1234`:

| Konto                                                                                                                                   | Rolle                                                                                      |
| --------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ |
| `petra.vorstand@demo.test`                                                                                                              | Vorstand (Admin) und Aufsicht                                                              |
| `helga.schiesswart@demo.test`                                                                                                           | Aufsicht                                                                                   |
| `jonas.aufsicht@demo.test`                                                                                                              | Aufsicht                                                                                   |
| `max.mustermann@demo.test`, `erika.musterfrau@demo.test`, `karl.kurzwaffe@demo.test`, `lena.langwaffe@demo.test`, `ben.bogen@demo.test` | Mitglieder                                                                                 |
| `tim.jungschutze@demo.test`                                                                                                             | Mitglied ohne Volljährigkeitsvermerk und ohne PIN (zeigt die Abfrage „volljährig geprüft“) |
| `anna.antrag@demo.test`                                                                                                                 | offener Mitgliedsantrag, Anmeldung erst nach Aufnahme                                      |

Ausprobieren: Helga startet unter _Am Stand_ eine Session und aktiviert Max; Max trägt unter _Heute schießen_ ein, oder am Kiosk-Link mit PIN `1234`; Helga zeichnet ab.

Ohne Demo: `ADMIN_EMAIL` und `ADMIN_PASSWORD` in `.env` setzen und `php artisan db:seed` ausführen. Die Selbstregistrierung ist anfangs aus; der Vorstand schaltet sie in den Einstellungen frei oder lädt Mitglieder ein.

## Prüfen

```bash
composer ci:check     # Formatierung, svelte-check, Pint, PHPStan, PHPUnit
```

Browser-Tests der Kernabläufe (Eintragen am Stand, Buchen, Nachtrag, Auszug) gegen den Demo-Verein:

```bash
npx playwright install chromium   # einmalig
npm run build && npm run test:e2e
```

## Betrieb

- Übergabe und erste Schritte: [docs/UEBERGABE.md](docs/UEBERGABE.md)
- Shared Hosting (Hetzner o. ä.): [docs/DEPLOY.md](docs/DEPLOY.md)
- Konfiguration: `.env` (`VEREIN_*`) oder in der Oberfläche unter _Verwaltung → Einstellungen_
- Mailversand: SMTP in `.env` (`MAIL_*`); ohne SMTP landen Mails im Log

## Rechtliches

Die Auswertung nach § 14 WaffG ist eine Orientierung. Maßgeblich bleibt die Bescheinigung des Schießsportverbands. Rechtsgrundlagen: [§ 14 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html), [§ 27 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__27.html), [§§ 10, 11 AWaffV](https://www.gesetze-im-internet.de/awaffv/).

## Lizenz

[Apache License 2.0](LICENSE)
