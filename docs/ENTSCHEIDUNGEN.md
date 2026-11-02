# Stand nach der Nacht vom 02. auf den 03.11.2026 und offene Entscheidungen

Grundlage: die Wettbewerbs- und Rechtsanalyse `docs/WETTBEWERB-REA.md` (aus der Session „Rangetime.de EU-Alternativen“; lag zum Zeitpunkt dieses Dokuments noch uncommittet in `.agents/rea-doc/docs/`). Die Analyse empfiehlt, entscheidet aber nichts – die Fragen Q1b bis Q8 trifft der Nutzer. Umgesetzt wurde deshalb alles, was **unter jeder Antwort** gilt, und zwar in der Variante, die den gebauten Code behält (Q7 = B). Nichts davon verbaut die Empfehlung Q2 = C.

## Aus der Analyse umgesetzt

| Analyse                                                 | Umsetzung                                                                                                                                                 | PR       |
| ------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------- | -------- |
| K1, K3: Aufsicht trägt ein, Namenssuche mit Vorbelegung | „Am Stand → Eintragen“: Name oder Mitgliedsnummer, Kategorie/Waffe aus dem letzten Eintrag, große Schaltflächen, grüne Bestätigungskarte                  | #27      |
| K2, K6: Tor am Eintrag                                  | nur mit gültiger Zeile im Aufsichtsregister, nie für sich selbst, Datum vom Server, Kurz/Lang nur mit Umfang „erlaubnispflichtig“ – auch für Admins       | #27      |
| K4: Mitglieder ohne Konto                               | E-Mail und Passwort optional; ohne E-Mail kein Konto, keine Einladung                                                                                     | #28      |
| K5: Doppelschutz                                        | generierte Spalte `active_key` + Unique-Indizes; Doppel-Tap meldet „heute schon eingetragen“                                                              | #27      |
| K7: Gast ohne Konto                                     | Name + Kategorie, Pflichthaken „volljährig“ (nicht gespeichert); Rolle „Gast“ entfernt                                                                    | #26, #27 |
| K8: Storno statt Bearbeiten                             | `log_entries` insert-only (Ändern/Löschen wirft), Storno mit Grund, Aufsicht nur eigene von heute, danach nur Admin                                       | #27      |
| K9: Nachtrag                                            | nur Admin, Pflichtgrund, mehrere Personen in einem Formular, im Auszug gekennzeichnet                                                                     | #27      |
| K10: Aufsichtsregister                                  | `qualifications` nur anhängen, Umfang/gültig bis/geprüft am/von, jüngste Zeile gilt                                                                       | #25      |
| K12, K13: Kladde des Vereins, Auszug                    | alle Einträge inkl. Gäste und Stornos, CSV, Auszug „zur Vorlage beim Verband“ ohne Urteil, Tage je Kategorie (alle/eigene), Feld für Unterschrift/Stempel | #27      |
| K14, K15: Disziplinkatalog, Vereinsdaten                | `config/disziplinen.php` (bewusst leer, Nummern nicht geraten), `VEREIN_VERBAND`, `VEREIN_NUMMER`, `VEREIN_DIGITAL_SEIT` mit Kopfvermerk                  | #27      |
| K16: kein Selbstlöschen                                 | entfernt                                                                                                                                                  | #16      |
| Betrieb 1: tägliche Sicherung                           | `verein:backup` 3:30 Uhr, `VACUUM INTO`, 30 Tage, Download                                                                                                | #20      |
| Betrieb 2: kein stilles Neuanlegen                      | Marker in `cron.php`, fehlende DB bricht laut ab                                                                                                          | #24      |
| Betrieb 5: Deploy nur nach grüner CI                    | `deploy-hetzner.sh` prüft `origin/main` und CI-Status                                                                                                     | #24      |
| Betrieb 6: Papier-Ersatzliste                           | einseitiges PDF in der Kladde des Vereins                                                                                                                 | #29      |
| 6.3: falscher Satz im PDF                               | ersetzt; Kladde-Einträge haben immer eine eintragende Aufsicht                                                                                            | #27      |
| Invarianten als Tests                                   | `tests/Feature/LogbookTest.php`                                                                                                                           | #27      |

Zusätzlich behoben: zwölf Befunde einer unabhängigen Code-Durchsicht (#26), u. a. dass abgelaufene/deaktivierte Aufsichten weiter als Abdeckung zählten.

## Deine Entscheidungen

### Q1b – Melden sich Mitglieder an?

**Heute:** Mitglieder _können_ ein Konto haben (Einladung) und sehen dann ihre eigenen Einträge und laden den Auszug „zur Information“. Ohne E-Mail geht es auch ohne Konto (K4).
**Empfehlung der Analyse:** nein (b). Dann würden Mitglieder-Einladungen, „Meine Buchungen“ und die Mitglieder-Kladde abgeschaltet.

### Q2 – Gehört die Bahnbuchung in den Kern?

**Heute:** Ja (A): Schießtage, Aufsichtsschichten, Buchung mit Kapazität, Standansicht. Die Buchung hat aber **keinen Nachweiswert mehr** – sie füllt nur die Eincheckliste vor; Nachweis ist allein der Kladde-Eintrag.
**Empfehlung der Analyse:** C (keine Buchung, kein Dienst-Objekt), D später nur bei echtem Bahnen-Engpass. Umstellen hieße: Navigation „Schießtage“, „Meine Buchungen“, „Termine planen“, „Stände“, „Gruppen“ und die Buchungsteile der Standansicht stilllegen. Die Kladde bleibt unverändert.
**Frage an den Pilotverein:** Gibt es Engpässe bei den Bahnen?

### Q3 – Urteil „erfüllt“ oder neutral?

**Heute:** neutral (C), wie empfohlen: Einzeltermine und Anzahl Tage je Kategorie, Hinweis „keine Bescheinigung, keine rechtliche Bewertung“. Die frühere Ampel „erfüllt / nicht erfüllt“ ist entfernt, weil die Auslegung von § 14 Abs. 4 offen ist (O5) und der Verband bescheinigt.

### Q4 – Offline-Warteschlange am Stand?

**Heute:** nein (B, empfohlen). Doppel-Tap und Neusenden sind durch den Index sicher; bei Ausfall die Papier-Ersatzliste.

### Q5 – Was bleibt auf Papier?

**Heute:** Vorkommnisse, Minderjährige samt Einwilligungen, Aushang der Aufsicht. Die Oberfläche sagt „Minderjährige weiter auf Papier“.
**Offen:** Das Erfolgskriterium in PRODUCT.md („Schießtag komplett ohne Papierliste“) müsste dann ehrlich enger gefasst werden – das ist deine Formulierung, deshalb nicht geändert.

### Q6 – Aufbewahrungsfristen

Nicht umgesetzt (Vereinsbeschluss). Vorschlag der Analyse: Kladde 5 Jahre, Gastnamen 12 Monate. Der Löschlauf muss stehen, bevor die erste Frist abläuft.

### Q7 – Was passiert mit dem gebauten Code?

**Heute:** B – Buchung behalten, Kladde getrennt und insert-only. Bei Q2 = C wäre der nächste Schritt das Stilllegen der Buchungsteile (Code auf Archiv-Branch, Tabellen erst nach Sicherung droppen).

### Q8 – Datenbank

**Heute:** SQLite mit nächtlicher Sicherung (A, empfohlen). Restore-Test steht noch aus: eine Sicherung herunterladen und lokal starten.

## Offene Rechtsfragen für den Pilotverein (Auswahl aus 6.4)

- **O1** Welcher Verband, welcher Disziplinkatalog? → dann `config/disziplinen.php` befüllen und `VEREIN_DISZIPLINKATALOG` setzen.
- **O2** Nimmt der Verband einen digitalen Auszug an?
- **O3** Was verlangt der Erlaubnisbescheid der Schießstätte (Standbuch, Mindestzahl Aufsichten)?
- **O5** Auslegung „alle drei Monate“ / „abgeschlossener Zeitraum“ in § 14 Abs. 4.
- **O14** Übergang Papier → digital: `VEREIN_DIGITAL_SEIT` setzen (Kopfvermerk im Auszug).

## Betrieb auf Hetzner

- PHP 8.4, Cronjob jede Minute (`cron.php`): Migrationen, Erinnerungen 17 Uhr, „Aufsicht gesucht“ 18 Uhr, Sicherung 3:30 Uhr.
- **Mailversand ist noch nicht eingerichtet** (`MAIL_MAILER=log`): Einladungen, Absagen und Erinnerungen landen nur im Log. Unter _Verwaltung → Einstellungen_ steht der Status und eine Testmail.
- Bestehende Konten (Tobias, Magnus, Test-User) haben noch **keine Registerzeile**. Zum Eintragen am Stand muss der Vorstand eine Prüfung im Aufsichtsregister eintragen.
