# Stand nach der Nacht vom 02. auf den 03.11.2026 und offene Entscheidungen

Grundlage: die Wettbewerbs- und Rechtsanalyse [WETTBEWERB-REA.md](WETTBEWERB-REA.md) (aus der Session „Rangetime.de EU-Alternativen“). Die Analyse empfiehlt, entscheidet aber nichts – die Fragen Q1b bis Q8 trifft der Nutzer. Umgesetzt wurde deshalb alles, was **unter jeder Antwort** gilt, und zwar in der Variante, die den gebauten Code behält (Q7 = B). Inzwischen entschieden: Q2 = A (Buchung bleibt), damit auch Q7 = B; außerdem Q1b (Session-Modell), Q5 (Drucke) und die Komfortfunktionen. Der Bauplan dazu steht in [UMBAU-SESSION.md](UMBAU-SESSION.md).

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

Aus dem Rollen-Rundgang (04.11.2026) behoben: K8, V6 und G9 (siehe UMBAU-NOTIZEN.md). Dabei festgelegt, ohne eigene Frage an den Nutzer:

- Eine Aufsicht zählt nur, solange ihre Registerzeile am Tag gilt und ihr Umfang zum Stand passt. Das wird jedes Mal neu geprüft, nicht beim Eintragen. Wer herabgestuft wird oder dessen Zeile entzogen wird oder abläuft, verliert damit auch das Aktivieren, Markieren und Stornieren fremder Buchungen.
- Dass eine eingeteilte Aufsicht nicht zählt, und warum, sehen nur der Vorstand und die Person selbst. Andere Mitglieder sehen nur Aufsichten, die zählen. Der Registerstand einer Person bleibt so in der Verwaltung.

## Deine Entscheidungen

### Q1b – Melden sich Mitglieder an?

**Entschieden** (Nutzer, 03.11.2026), mit eigenem Modell statt a oder b:

- Ein Verein je Installation.
- Jeder legt selbst ein Konto an und beantragt damit die Mitgliedschaft. Der Verein bestätigt. Er kann auch selbst Konten anlegen.
- Die Schießaufsicht ist eine zusätzliche Berechtigung eines Mitglieds (Aufsichtsregister).
- Die Aufsicht startet am Stand eine Schieß-Session und aktiviert die Mitglieder. Die tragen selbst ein, auf dem eigenen Handy oder am Kiosk-Tablet: Disziplin, Schusszahl, Kurz/Lang/erlaubnisfrei, eigen/Verein.
- Am Ende zeichnet die Aufsicht ab. Danach ändert nur ein Admin, und das wird dokumentiert.
- Jedes Mitglied sieht seine Schüsse und Buchungen.

**Gebaut** am 03.11.2026 (#54–#69) nach [UMBAU-SESSION.md](UMBAU-SESSION.md); Notizen zum Bau in [UMBAU-NOTIZEN.md](UMBAU-NOTIZEN.md). Die Detailfragen E1–E15 sind am 03.11.2026 entschieden: alle wie empfohlen, nur E2 nicht. Der Kiosk bekommt eine PIN je Mitglied.

### Q2 – Gehört die Bahnbuchung in den Kern?

**Entschieden: A** (Nutzer, 03.11.2026). Die Buchung bleibt im Kern: Schießtage, Aufsichtsschichten, Buchung mit Kapazität, Standansicht.
**Gilt weiter:** Die Buchung hat **keinen Nachweiswert**. Sie füllt nur die Eincheckliste vor, Nachweis ist allein der Kladde-Eintrag. Das Einchecken legt den Eintrag über das Tor an (`Bookings::checkIn` → `Logbook`), nur am Schießtag selbst. Ein bestätigter Besuch wird nicht zurückgesetzt, sondern in der Kladde storniert (#27).
Die Analyse hatte C empfohlen (keine Buchung, D bei Engpass). Das ist damit erledigt.

### Q3 – Urteil „erfüllt“ oder neutral?

**Heute:** neutral (C), wie empfohlen: Einzeltermine und Anzahl Tage je Kategorie, Hinweis „keine Bescheinigung, keine rechtliche Bewertung“. Die frühere Ampel „erfüllt / nicht erfüllt“ ist entfernt, weil die Auslegung von § 14 Abs. 4 offen ist (O5) und der Verband bescheinigt.

### Q4 – Offline-Warteschlange am Stand?

**Heute:** nein (B, empfohlen). Doppel-Tap und Neusenden sind durch den Index sicher; bei Ausfall die Papier-Ersatzliste.

### Q5 – Was bleibt auf Papier?

**Entschieden** (Nutzer, 03.11.2026): Ohne Papier ist der Goldstandard, drucken muss trotzdem gehen. Druckbar sein müssen:

- (a) ein Teilnahmenachweis je Disziplin; er bestätigt die Teilnahme, kein „erfolgreich“ und kein Urteil;
- (b) eine Übersicht aller Schießtage mit Schüssen;
- (c) ein Session-Protokoll je Tag;
- (d) die Mitgliedschaftsbescheinigung nach § 14 Abs. 4 S. 3 WaffG.

**Entschieden (E11, 03.11.2026):** Minderjährige samt Einwilligungen, Vorkommnisse und der Aushang der Aufsicht bleiben vorerst auf Papier. Das Erfolgskriterium in PRODUCT.md ist entsprechend gefasst. Alles digital wäre ein eigenes Vorhaben (§ 27 Abs. 3 WaffG, § 10 Abs. 5 AWaffV).

### Q6 – Aufbewahrungsfristen

**Umgesetzt nach Empfehlung A** (Nutzer, 03.11.2026): Kladde 5 Jahre ab dem Termin, Gastnamen 12 Monate. Beide Fristen stehen in den Vereinseinstellungen (Abschnitt „Aufbewahrung“; Kladde 3–10 Jahre, Gastnamen 1–60 Monate). Der Verein bestätigt sie per Beschluss und trägt sie ins Verzeichnis der Verarbeitungstätigkeiten nach Art. 30 DSGVO ein.

- Der Löschlauf `verein:aufbewahrung` läuft täglich um 04:30 Uhr. Er löscht Kladde, Sessions und Buchungen der Tage vor dem Stichtag und anonymisiert ältere Gastnamen in Kladde, Session, Buchung und Protokoll.
- Die Kladde bleibt insert-only. Die Trigger lassen Löschen und Anonymisieren nur während eines protokollierten Laufs zu (`retention_runs`), nie mit einem Stichtag jünger als 3 Jahre bzw. 1 Monat.
- Unter 3 Jahren geht nicht: Für die Bescheinigung nach § 14 WaffG blickt der Verband 24 Monate zurück.
- Nur SQLite. Unter MariaDB verweigert sich der Löschlauf, MariaDB ist ohnehin nicht freigegeben.
- Details und eigene Entscheidungen beim Bau: [UMBAU-NOTIZEN.md](UMBAU-NOTIZEN.md#q6-aufbewahrung-und-löschlauf).

### Q7 – Was passiert mit dem gebauten Code?

**Entschieden: B** (folgt aus Q2 = A): Buchung behalten, Kladde getrennt und insert-only. Kein Rückbau, kein Archiv-Branch.
**Komfortfunktionen, einzeln entschieden** (Nutzer, 03.11.2026). Umsetzung in den Schritten K1–K3 von [UMBAU-SESSION.md](UMBAU-SESSION.md#komfortfunktionen):

- **Stilllegen:**
    - Gruppen und Freigaben je Schießtag
    - Serien-Verknüpfung; die Mehrfachanlage bleibt (seit dem Abend des 03.11.2026 auch zweiwöchentlich und monatlich, ohne Verknüpfung der Termine)
    - ~~CSV-Import~~: am Abend des 03.11.2026 auf Wunsch des Vereins zurückgeholt, für Mitglieder (mit Aufsichtsregister) und für Schießtage. Auslöser waren zwei Rückmeldungen aus der App.
    - Erinnerung am Vortag
    - Kalender-Abo
    - Mail „Aufsicht gesucht“
    - Passkeys
    - ungenutzte Reste des Starter-Kits
- **Behalten:**
    - Statistik für den Vorstand
    - Absage-Mail
    - Zwei-Faktor-Anmeldung, freiwillig
    - Schalter „Gastschützen erlaubt“, beschriftet als „nur Online-Buchung“
- **Folgt aus dem Session-Modell, bleibt:**
    - Polling der Standansicht
    - E-Mail-Bestätigung, „Passwort vergessen“ und Mail-Status
    - Profil, eigene Kladde des Mitglieds
    - QR-Ankunft als Hinweis

**Voraussetzung:** SMTP einrichten. Ohne Mailversand gibt es weder Selbstregistrierung mit Bestätigung noch Absage-Mail.

### Q8 – Datenbank

**Heute:** SQLite mit nächtlicher Sicherung (A, empfohlen). Restore-Test steht noch aus: eine Sicherung herunterladen und lokal starten.

## Offene Rechtsfragen für den Pilotverein (Auswahl aus 6.4)

- **O1** Welcher Verband, welcher Disziplinkatalog? → BDS, LV 3 Niedersachsen/Bremen; Katalog `bds` in `config/disziplinen.php` (Kennziffernliste 07/2026). In den Einstellungen wählen.
- **O2** Nimmt der Verband einen digitalen Auszug an?
- **O3** Was verlangt der Erlaubnisbescheid der Schießstätte (Standbuch, Mindestzahl Aufsichten)?
- **O5** Auslegung „alle drei Monate“ / „abgeschlossener Zeitraum“ in § 14 Abs. 4.
- **O14** Übergang Papier → digital: `VEREIN_DIGITAL_SEIT` setzen (Kopfvermerk im Auszug).

## Betrieb auf Hetzner

- PHP 8.4, Cronjob jede Minute (`cron.php`): Migrationen und Sicherung 3:30 Uhr. Erinnerungen und „Aufsicht gesucht“ sind stillgelegt (K2).
- **Mailversand ist noch nicht eingerichtet** (`MAIL_MAILER=log`): Einladungen, Absagen, E-Mail-Bestätigungen und „Passwort vergessen“ landen nur im Log; die Meldung nach einer Absage sagt dann nicht „benachrichtigt“. Unter _Verwaltung → Einstellungen_ steht der Status und eine Testmail.
- Bestehende Konten (Tobias, Magnus, Test-User) haben noch **keine Registerzeile**. Zum Eintragen am Stand muss der Vorstand eine Prüfung im Aufsichtsregister eintragen.
