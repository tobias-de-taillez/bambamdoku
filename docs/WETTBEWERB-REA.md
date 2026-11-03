# Wettbewerbs-REA: Was der lean Kern von bambamdoku können muss

Stand: 2026-11-02. Grundlage: öffentliche Erfassung von 13 Anbietern, eine Rechtsrecherche (Anforderungen R01–R26), drei Jury-Voten und ein Abgleich mit dem Repo. Die Jurys heißen hier **R** (rechtliches Minimum), **S** (Geschwindigkeit und Robustheit am Stand) und **B** (Betriebs- und Wartungskosten).

Dieses Dokument entscheidet nichts. Es empfiehlt, und die Entscheidungen Q1 bis Q8 in Abschnitt 9 trifft der Nutzer. Was entschieden und umgesetzt ist, steht in [ENTSCHEIDUNGEN.md](ENTSCHEIDUNGEN.md).

> **Nachtrag vom 03.11.2026, mittags**
>
> Der Nutzer hat Q1b und Q5 mit einem eigenen Modell entschieden. Mitglieder tragen in einer Schieß-Session selbst ein, am eigenen Handy oder am Kiosk. Die Aufsicht zeichnet ab, und erst damit wird der Eintrag zum Kladde-Eintrag. Der Eintrag erfasst auch die Schusszahl.
>
> Damit sind überholt:
>
> - Abschnitt 5.2 (Ablauf am Stand)
> - K1, K2 und K6 (die Aufsicht trägt ein, nie für sich selbst)
> - 7.2 (Datenmodell)
> - die Zeilen zu Kiosk, Schusszahl, Selbsteintrag und Polling in Abschnitt 8
>
> Die Komfortfunktionen sind einzeln entschieden. Maßgeblich sind jetzt [UMBAU-SESSION.md](UMBAU-SESSION.md) und [ENTSCHEIDUNGEN.md](ENTSCHEIDUNGEN.md).

> **Nachtrag vom 03.11.2026, 00:10 Uhr**
>
> - **Entschieden:** Q1 = A (Kladde gehört dem Verein), bestätigt am 02.11.2026. Q2 = A (Buchung bleibt), bestätigt am 03.11.2026, und damit Q7 = B. Kurzfazit und Abschnitt 7 nennen noch Q2 = C. Maßgeblich sind jetzt die dort beschriebenen Varianten für Q2 = A.
> - **Stand main `fead87b` (PRs bis #48):** Der Kern ist unter Q7 = B gebaut, die Liste steht in docs/ENTSCHEIDUNGEN.md. Das Einchecken legt den Eintrag über das Tor an, nur am Schießtag selbst. Ein bestätigter Besuch lässt sich nicht zurücksetzen. Abschnitt 7.3 und die Code-Befunde zu Q7 beschreiben deshalb einen überholten Stand.
> - **Gegen den Gesetzestext geprüft:** § 14 Abs. 3–4 WaffG, § 15 Abs. 1 Nr. 7 b WaffG, § 58 Abs. 21 WaffG (Übergangsregel bis 31.12.2025), § 10 Abs. 1, 3 und 5 AWaffV und der Standvermerk zur Änderung vom 22.10.2026 (O12). Alle stimmen mit gesetze-im-internet.de überein.
> - **Repo seit der Prüfung weitergelaufen:** Die Vorbemerkung und Abschnitt 7.3 beschreiben `f07b679` (PRs #1–#10). Seitdem sind PRs #11–#18 in main: README und Plan, Planungsübersicht, „Aufsicht gesucht“, `fix/kleinigkeiten`, Statistik für den Vorstand, Sicherheit (kein Selbstlöschen, CSV gegen Formel-Injection), QR-Selbst-Check-in und Hinweise/App-Icon. Gearbeitet wird gerade an `feat/mail-pruefen`.
>     - Der QR-Selbst-Check-in (#17) meldet nur „ist da“. Bestätigt wird weiter durch die Aufsicht, das verträgt sich mit K1 und K6.
>     - Kein Selbstlöschen des Kontos (#16) erledigt den entsprechenden Punkt aus K16.
>     - Statistik (#15) und „Aufsicht gesucht“ (#13) stehen in Abschnitt 8 auf „gestrichen“ bzw. „später“; die Empfehlung „nicht mergen“ in 7.3 ist durch die Merges überholt und wird zur Frage Q7.

> **Vorbemerkung: Stand des Repos (geprüft am 02.11.2026 gegen 23:50 Uhr; origin/main = `f07b679`, lokal main = `b7a6758`).** Das Repo ändert sich während dieser Prüfung laufend; alle Aussagen über den Code beziehen sich auf diesen Stand.
>
> - **Dokumente:** PRODUCT.md und docs/PLAN.md beschreiben schon seit Commit `da6fccf` die Schießkladde des Vereins. Mitglieder sehen dort ihre eigenen Einträge, Vorstand und Schießwart sehen alle; das entspricht Q1 = A. Der Auftrag geht von einem privaten Schießbuch aus, in dem der Admin keine fremden Einträge sieht. Das ist der Stand von `94420bc` (M0) und überholt. Q1 bleibt trotzdem eine Entscheidung des Nutzers.
> - **Code:** M1 bis M5 sind gebaut und in main gemergt (PRs #1 bis #5). Dazu kommen:
>     - PR #6: Absage eines Schießtags storniert offene Buchungen und verschickt Mails.
>     - PR #7: Vereinseinstellungen (`settings`-Tabelle).
>     - PR #8: Protokoll `audit_entries` für Buchungen, Aufsichten und Mitglieder.
>     - PR #9: Erinnerungen am Vortag und Kalender-Abo.
>     - PR #10: CSV-Import für Mitglieder.
>     - Auf dem Branch `fix/kleinigkeiten`, noch nicht in main: Mails „Aufsicht gesucht“ und Löschen leerer Schießtage.
>
>     Die Kladde aus M5 (`app/Domain/ShootingRecord.php`) besteht aus Buchungen mit Status `checked_in`. Sie urteilt „erfüllt / nicht erfüllt“ und druckt das ins PDF. Lokal in main (noch nicht gepusht) hält PLAN.md im neuen Abschnitt „Stand (2026-11-03)“ das als bewusste Abweichung fest, ebenso `supervisor_until` statt einer eigenen Tabelle.
>
> - **Folge:** Die Empfehlungen zu Q2 und Q3 treffen Code, der schon in main liegt. Abschnitt 7.3 stellt Ist und Ziel gegenüber, Q7 fragt, was mit dem gebauten Code passiert.
> - **Betrieb:** `cron.php` ist getrackt und läuft laut docs/DEPLOY.md jede Minute als konsoleH-Cronjob. Er legt die SQLite-Datei an, falls sie fehlt, ruft `migrate --force` auf, seedet den ersten Admin und ruft `schedule:run` auf. Im Betrieb läuft SQLite (DEPLOY.md); MariaDB steht nur in PLAN §2. Eine Continuous-Deploy-Pipeline gibt es noch nicht. `tests.yml` prüft bei Push auf main und bei Pull Requests, deployt wird von Hand mit `scripts/deploy-hetzner.sh`, und das Skript führt keine Tests aus. Ob dieser Stand schon produktiv läuft, ist unbekannt.

## 1. Kurzfazit

Das folgende gilt unter den empfohlenen Antworten Q1 = A, Q1b = b, Q2 = C, Q3 = C und Q5 = A, je Baustein ein Satz.

1. **Eintrag:** Eine Aufsicht aus dem Register sucht am Stand das Mitglied per Namen oder Mitgliedsnummer oder tippt einen Gastnamen ein und legt damit den Eintrag an; das Anlegen ist zugleich die Bestätigung (Muster: KNSA).
2. **Rechtliches Tor:** Der Server nimmt den Eintrag nur an, wenn die eintragende Person am Schießtag eine gültige Zeile im Aufsichtsregister nach [§ 10 Abs. 3 AWaffV](https://www.gesetze-im-internet.de/awaffv/__10.html) hat, sich nicht selbst einträgt, das Datum heute ist und sie für Kurz- und Langwaffe den Umfang „erlaubnispflichtig“ hat; Bahnbuchung, Ankündigung und ein eigenes Dienst-Objekt braucht es dafür nicht.
3. **Inhalt:** Ein Eintrag hält Person bzw. Gastname, Datum, Kategorie (Kurz-, Lang- oder erlaubnisfreie Waffe), eigene oder Vereinswaffe, Disziplin aus dem Verbandskatalog, wer eingetragen hat und die Herkunft fest; Bahn, Schusszahl, Kaliber, Waffendaten und Check-out gehören nicht dazu.
4. **Korrektur:** In die Kladde wird nur geschrieben; ein Fehler wird durch Storno mit Grund und einen neuen Eintrag behoben, gelöscht wird nichts, und Nachträge legt nur der Admin mit Pflichtgrund an.
5. **Verein:** Eine Admin-Rolle, die nur Vorstand und Schießwart erhalten, sieht die ganze Kladde, exportiert sie als CSV und erzeugt je Mitglied den PDF-Auszug „zur Vorlage beim Verband“ mit Einzelterminen, Anzahl je Kategorie und Besitz, Feld für Unterschrift und Stempel und ohne Urteil „erfüllt“, denn bescheinigen muss der Verband ([§ 14 Abs. 3 und 4 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html), [§ 58 Abs. 21 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__58.html)).
6. **Mitglied:** Ein Mitglied braucht weder Konto noch Smartphone, und ob es sich überhaupt anmeldet, um seinen Auszug selbst zu laden, ist Entscheidung Q1b (Empfehlung: nein).
7. **Papier:** Vorkommnisse, Minderjährige samt Einwilligungen und der Aushang der Aufsicht bleiben vorerst auf Papier, deshalb muss das Erfolgskriterium in PRODUCT.md („Schießtag komplett ohne Papierliste“) enger gefasst werden (Q5).
8. **Betrieb:** Vor dem Echtbetrieb müssen tägliche Sicherungen, ein Deploy nur nach grüner CI und die rechtlichen Invarianten als Pest-Tests stehen; das sind Voraussetzungen, keine Funktionen.

## 2. Methode und Abdeckung

### 2.1 Erfasste Anbieter

Die Evidenz ist so abgestuft:

- **stark:** offizielles Handbuch oder Schema
- **mittel:** Handbuch, öffentlich ausgelieferter Code oder Screenshots des Herstellers
- **schwach:** nur Marketing oder Zusammenfassungen von Suchtreffern

| Anbieter         | Land | Wie erfasst                                                                                                                                       | Evidenz                                                         | Nicht erfasst                                   |
| ---------------- | ---- | ------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------- | ----------------------------------------------- |
| Rangetime        | DE   | REA (Startseite, Hilfe, Preisrechner, öffentliche Seiten einer Demo- und einer Vereinsinstanz), Client-Skripte per curl, Rechtstexte per WebFetch | mittel; Handbuch v1.12.2 hinkt den Instanzen (v2.3.2) hinterher | alles hinter dem Login                          |
| Düsel / Kladdify | DE   | REA: HTTP 403; WebFetch: ECONNREFUSED; nur Zusammenfassungen von Suchtreffern                                                                     | schwach                                                         | das ganze Produkt (Bot-Schutz nicht umgangen)   |
| svscore          | DE   | REA, öffentliche OpenAPI-Spezifikation, Frontend-Bundles statisch gelesen                                                                         | stark (Felder), mittel (Abläufe)                                | Nachweis-PDF, serverseitige Regeln              |
| VERION           | DE   | REA, FAQ aus JSON-LD, Screenshots des Herstellers, App Store                                                                                      | mittel; der Blog widerspricht der FAQ                           | App hinter dem Login, echter PDF-Export         |
| StandFrei        | DE   | REA, Rechtstexte, rund 35 öffentliche JS-Chunks des Portals                                                                                       | mittel (Code gelesen, nicht live bedient)                       | App im Betrieb                                  |
| StandLotse       | DE   | REA auf zwei Landing Pages, README und Commits des Repos                                                                                          | schwach (nur Herstellerangaben)                                 | App (Code privat)                               |
| Schiessbuch.org  | DE   | REA, Hilfe-Handbücher, Login-Seite, Versionsverlauf im Store                                                                                      | mittel                                                          | App und Portal hinter dem Login                 |
| High Ready       | AT   | REA, Lizenzvertrag und Datenschutz als PDF, Screenshots des Herstellers, Store-Rezensionen                                                        | mittel                                                          | App; ein Interview lieferte 403                 |
| KNSA             | NL   | REA, offizielle Handbücher, Musterregister, App Store, niederländische Rechtstexte                                                                | stark                                                           | App nach dem Login (reCAPTCHA nicht angefasst)  |
| Range Mate       | UK   | REA, ganzes GitBook-Handbuch (77 Seiten), Trust Centre, gov.uk                                                                                    | mittel (Handbuch, keine Oberfläche)                             | Vereins-Subdomains (Cloudflare, nicht umgangen) |
| CelPal           | PL   | REA, Kopplungsbildschirm des Kiosks, öffentliche UI-Strings (2081), App Store, polnische Verordnung                                               | mittel                                                          | Oberfläche hinter dem Login                     |
| Joulix           | DE   | REA, 16 Hilfeartikel, Datenschutzerklärung, AVV                                                                                                   | stark                                                           | App; für die Kernfrage kaum relevant            |
| PureDynamic      | CH   | REA, Online-Hilfe V0.3.0, Sicherheitsseite, öffentliche Client-Chunks                                                                             | mittel                                                          | Fach-App hinter dem Login                       |

### 2.2 Was grundsätzlich nicht erfasst wurde

- **Keine Oberfläche nach dem Login, bei keinem Anbieter.** Es gab keine Anmeldung, kein Konto, kein abgeschicktes Formular und kein Umgehen von Bot-Schutz (betroffen: Düsel, Range Mate, Militär Aktuell, chrome-stats). „Gesehen“ heißt höchstens: öffentliche Seiten, Kopplungs- und Login-Bildschirme, Screenshots des Herstellers.
- **Kaum unabhängige Erfahrungsberichte.** Es gibt ein Forum zu svscore (3 Beiträge, davon 2 vom Hersteller) und Store-Rezensionen zu High Ready, CelPal und VERION.
- **Nicht ausgewertet:** disLog, Hecht Software, Vereinskladde, die App „Shooter“ und Shootwise tauchten nur in Suchtreffern auf. Ihre Funktionen sind keinem erfassten Anbieter zugerechnet. Laut Suchtreffern haben gerade disLog, Hecht und Vereinskladde eine Freigabe durch die Aufsicht bzw. eine Sperre ohne Aufsicht. Aussagen über Alleinstellung gelten deshalb nur für die 13 erfassten Anbieter.
- **Rechtsquellen:** Abgerufen am 02.11.2026. Am selben Tag erneut im Wortlaut geprüft wurden § 14 Abs. 3 Nr. 2 und Abs. 4 S. 1 WaffG, § 15 Abs. 1 Nr. 7 WaffG sowie § 10 Abs. 3 S. 3–5 und Abs. 5 AWaffV. Die WaffVwV ist von 2012 und nutzt die Nummerierung vor 2020 (R05). Eine Änderung des WaffG vom 22.10.2026 ist laut gesetze-im-internet.de noch nicht vollständig eingearbeitet (O12).
- **Repo:** Gelesen wurden Git-Historie, Migrationen, Domain-Klassen, Routen, `cron.php`, `tests.yml`, `scripts/deploy-hetzner.sh` und docs/DEPLOY.md. Ein Deployment wurde nicht geprüft.

### 2.3 Wie aus drei Voten eine Liste wurde

- **Mehrheit:** Zwei von drei Jurys entscheiden, ob eine Funktion im Kern bleibt, später kommt oder gestrichen wird. Die Stimmen stehen als R/S/B dabei, zum Beispiel „2:1, S: später“.
- **Schweigen ist Enthaltung.** Ohne zwei ausdrückliche Stimmen gilt das Lean-Kriterium: „später“, wenn sich ein klarer Auslöser benennen lässt, sonst „gestrichen“.
- **Einig im Ob, uneinig im Wie: Es gilt die schlankste Variante.** Die Abweichung steht dabei. Das betrifft:
    - Aufsicht als Voraussetzung (3:0 für einen Standdienst): Umgesetzt als Prüfung am Eintrag, ohne eigenes Dienst-Objekt.
    - Nachvollziehbarkeit (Audit-Log 3:0): Die Kladde ist insert-only, die Tabelle ist damit selbst das Protokoll, eine separate Log-Tabelle entfällt.
    - Doppelschutz (3:0) und Idempotenz (S+B): Ein eindeutiger Index statt eines zusätzlichen Idempotenzschlüssels.
    - Disziplinkatalog (3:0): Versionierte Konfiguration statt Tabelle mit Pflege-Oberfläche.
    - Neutrale Zählung (3:0): Liste plus Anzahl je Kategorie, ohne Rechnung über Monate, Quartale oder 12-Monats-Blöcke (Q3).
- **Das Lean-Kriterium bleibt verbindlich, auch gegen eine Mehrheit.** Erfüllt eine Funktion weder a noch b noch c (Definition in Abschnitt 7.1), kommt sie nach „später“ oder „gestrichen“, auch wenn zwei Jurys sie im Kern wollten. Diese Fälle sind in Abschnitt 8 als „Lean-Override“ markiert:
    1. Ankündigung von Standdiensten und digitaler Aushang der Aufsicht (beide R+S Kern)
    2. Notiz zu Vorkommnissen am Dienst (S+B Kern)
    3. Vermerk für Minderjährige samt Jugend-Eignung im Register (3:0 Kern). Grund: Der Haken ersetzt die schriftliche Erklärung nicht, und eine korrekte digitale Lösung bräuchte Altersstufe und Jugend-Aufsicht. Deshalb vorerst ganz auf Papier.
    4. „Allein auf dem Stand“ als eigener Eintragsweg (R+B Kern)
    5. Gastgeber am Gasteintrag (R+B Pflicht)
    6. Fremder Ort am Nachtrag (R+B)
    7. Nachweisdokument der Aufsicht, Register-Export und die Register-Felder Nachweisquelle und „volljährig bestätigt“ (R+B)
    8. Protokoll der Exporte (R+B)
    9. CSV-Export für Mitglieder (R+B)
    10. Stände als Namensliste (3:0 Kern)
    11. Mitglieder-Login mit eigener Ansicht (3:0 vorausgesetzt). Das ist keine stille Streichung, sondern Entscheidung Q1b.
- **Keine Entscheidung durch dieses Dokument.** Abschnitt 7 nennt, was sich bei anderen Antworten ändert.
- **Eigene Worte:** Texte, Screenshots und Designs der Anbieter sind nicht übernommen.

## 3. Anbieter-Steckbriefe

**Rangetime** (DE, SaaS mit Subdomain je Verein, modular ab 6 €/Monat)

- Die Aufsicht bucht im Buchungsraster die erste Spalte jeder Zeitzeile. „Aufsicht erforderlich“ und die Anwesenheitsbestätigung (Terminal mit E-Mail und PIN oder rotierender QR-Code) lassen sich pro Stand einschalten, beide sind standardmäßig aus.
- Das Schießbuch ist privat und entsteht aus abgeschlossenen Buchungen. Der Admin sieht es nicht, und das Mitglied darf auch automatisch erzeugte Einträge standardmäßig ändern oder löschen.
- Dazu viel Peripherie: Zahlung, Inventar, Arbeitsdienst, Kleinanzeigen.
- Quellen: [Hilfe Schießbuch](https://rangetime.de/help/shooting_diary), [Hilfe Aufsicht](https://rangetime.de/help/supervisors), [Client-API](https://demo.rangetime.de/javascripts/rangetime/api.js)

**Düsel / Kladdify** (DE, SaaS, ab etwa 12,99 €/Monat, schwach belegt)

- Laut Hersteller gibt es eine Kladde mit An- und Abmeldung. Ein Eintrag enthält Datum, Stand, Waffenart, Disziplin und Kaliber. Der Nachweis läuft getrennt nach Kurz- und Langwaffe über 24 Monate, Export als PDF und CSV.
- Der Hersteller nennt sich offen die Datengrundlage für die Bescheinigung des Verbands. Daneben gibt es Chat, Kasse und Kleinanzeigen.
- Nur aus Zusammenfassungen von Suchtreffern belegt. Quelle: [duesel-it.de/schiesskladde](https://duesel-it.de/schiesskladde)

**svscore** (DE, Wettkampf-Suite, Paket Verein 24 €/Monat)

- Kiosk unter einem angemeldeten Vereinskonto: Check-in per QR-Code aus der App oder per Namenssuche, derselbe Scan checkt aus, zum Ende der Betriebszeit checkt das System automatisch aus.
- Laut Schema ein schlanker Datensatz: Person, Kommen, Gehen, Stand, Disziplin, Waffenart, Quelle, Notiz. In den Dienstplan kommt nur, wer eine gültige Qualifikation hat.
- Keine Bestätigung je Eintrag. Der Hersteller erklärt alle waffenrechtlichen Funktionen für unverbindlich, beruft sich für Ampel und Nachweis-PDF aber auf „§ 14 Abs. 5 WaffG“.
- Quellen: [OpenAPI](https://svscore.de/api/v1/openapi.json), [Anleitung](https://svscore.de/anleitung)

**VERION** (DE, allgemeine Vereinssoftware; PDF-Export ab Plan PRO, Buchungen und damit der Schießnachweis nur im Plan MAX, regulär 199 €/Monat)

- Jede Standbuchung zählt automatisch als Trainingstermin. Ist jemand nicht erschienen, muss die Aufsicht das nachträglich markieren.
- Es gibt keinen Eintrag für Disziplin oder Waffe; der Hersteller rät selbst, mit der Behörde zu klären, ob das reicht. Brauchbar ist die Export-Historie.
- Quelle: [Lösungsseite Schützenvereine](https://www.verion-app.de/loesungen/schuetzenvereine)

**StandFrei** (DE, Reservierung, 1,19 € je Stand und Monat)

- Vorab-Reservierung im Wochenraster. Die Zeile „Aufsicht“ ist nur ein Dienstplan und wird nicht erzwungen. Gäste werden nur mit Namen erfasst, Storno ist ein Statuswechsel.
- Der Hersteller sagt selbst, dass eine Buchung keine Teilnahme belegt und der Export kein Nachweis ist. Brauchbar sind der eindeutige Schlüssel gegen Doppelbuchungen und die Trennung von „für wen“ und „angelegt von“.
- Quellen: [Funktionen](https://standfrei.de/funktionen/), [AVV](https://portal.standfrei.de/avv)

**StandLotse** (DE, lokal auf Hardware des Vereins, 299 € einmalig; nur Angaben des Herstellers)

- Die Aufsicht legt im geführten Modus die Belegung der Bahn selbst an. Beim Beenden des Trainings wird daraus der Kladde-Eintrag. Per RFID entsteht der Eintrag dagegen ohne Aufsicht.
- Einträge sind unveränderlich. Storno nur mit Grund, Sofort-Rücknahme direkt nach dem Anlegen, Anonymisierung nach 30 Monaten.
- Rechtliche Invarianten laufen als Tests in der CI. Es gibt keinen Zugang für Mitglieder.
- Quelle: [Technikseite](https://mlang-de.github.io/schiesskladde-page/tech-index.html)

**Schiessbuch.org** (DE, App für den einzelnen Schützen, 0,99 €/Monat)

- Der Schütze legt einen Entwurf an (Stand, Disziplin, Waffentyp, Kaliber, Schusszahl) und reicht ihn ein. Die Aufsicht verifiziert oder lehnt ab, per Liste oder QR-Scan, auch zeitversetzt. Seit Version 1.0.5 ist das versehentliche Selbstverifizieren blockiert.
- Der Export enthält voreingestellt nur verifizierte Einträge. Die Kladde liegt bei der Plattform, Vereine verwalten nur Mitgliedschaften, Gäste gibt es nicht.
- Quellen: [Hilfe Schützen](https://www.schiessbuch.org/hilfe/schuetzen), [Hilfe Schießstände](https://www.schiessbuch.org/hilfe/schiessstaende)

**High Ready** (AT, App für den einzelnen Schützen, 79,99 €/Jahr)

- Privates Schießbuch mit Selbstauskunft (Waffe aus dem eigenen Waffenschrank, Schusszahl). „Bestätigt“ heißt: Das Handy war am NFC-Tag eines Partnerstands, und der Besitzer hat sich binnen 10 Minuten per Biometrie ausgewiesen. Eine Aufsicht ist nicht beteiligt.
- „Legalisierung per NFC“ ist eine Behauptung des Herstellers zum österreichischen Recht. Das PDF gibt es nur mit laufendem Abo, ohne Filter nach Zeitraum.
- Quellen: [Startseite](https://highreadyapp.com/), [Lizenzvertrag](https://highreadyapp.com/wp-content/uploads/2025/03/Lizenzvertrag_2025.pdf)

**KNSA Digitaal Schietregister** (NL, Angebot des Verbands, für Schützen kostenlos)

- Nach dem Schießen scannt eine befristet berechtigte Sicherheitsperson den QR-Code des Schützen. Damit ist die Schießeinheit angelegt und bestätigt.
- Ein Scan speist die Liste des Schützen und das Anwesenheitsregister des Vereins (Excel-Export). Erfasst werden nur Person, Datum, Verein und wer gescannt hat, höchstens eine Einheit pro Tag. Gäste, Waffen und Munition bleiben auf Papier.
- Quellen: [Handbuch Schützen](https://www.knsa.nl/media/3238/handleiding-digitaal-schietregister.pdf), [Handbuch Vereine](https://www.knsa.nl/media/1972/handleiding-mijn-knsa-voor-knsa-verenigingen.pdf)

**Range Mate** (UK, SaaS, 4,99 bis 39,99 £/Monat)

- Register je Termin: Die Aufsicht hakt die Anwesenden ab, trägt eine Gesamtschusszahl ein und schließt mit „Validate & lock“. Sie erfasst auch Laufkundschaft und Mitglieder ohne Smartphone.
- Einträge mit konkreter Waffe (eigene, Vereins- oder geliehene) legt meist das Mitglied an. Ohne den Strict Mode kann die Aufsicht sie nicht prüfen.
- Quellen: [Range Logs](https://docs.range-mate.co.uk/for-club-admins/attendance-and-range-logs), [Strict Mode](https://docs.range-mate.co.uk/for-club-admins/strict-record-keeping-mode)

**CelPal** (PL, SaaS, 29 bis 499 zł/Monat)

- Tablet-Kiosk, gedruckter QR-Code am Stand und App führen in ein Standbuch, dessen Felder genau der polnischen Verordnung folgen: Name, dazu Erlaubnisnummer oder Adresse, Unterschrift.
- Die Tagesaufsicht eröffnet das Buch, vorher lehnt das System Einträge ab. Optional gibt das Personal Einträge frei, danach sind sie gesperrt. Gelöschte Einträge lassen sich wiederherstellen, endgültiges Löschen ist aber möglich. Der Kiosk wird per QR-Code und 4-stelligem Kurzcode gekoppelt und zeigt nie frühere Einträge.
- Quellen: [UI-Strings](https://book.celpal.net/locales/pl-PL/common.json), [Produkt](https://celpal.net/produkt)

**Joulix** (DE, Beta, 19,99 €/Monat)

- Spiele, Training und Standanzeige für elektronische Schießanlagen. Kein Nachweis nach WaffG.
- Übertragbar: Gäste und Schützen ohne Konto, Frist für Gastnamen, Protokoll nur mit Namen geänderter Felder, CSV-Export durch das Mitglied.
- Quellen: [Rollen und Rechte](https://joulix.app/hilfe/rollen-und-rechte), [Datenschutz](https://joulix.app/datenschutzerklaerung)

**PureDynamic** (CH, Beta V0.3.0, Preis nicht veröffentlicht)

- Schweizer Wettkampf- und Stichbetrieb mit Status je Bahn und Resultaten per Foto oder Scan. Abgeschlossene Wettkampf-Durchgänge werden gesperrt, die Schusszahl wird je Schießtag gemeldet (vermutlich als Summe). Kein waffenrechtlicher Nachweis.
- Quelle: [Online-Hilfe](https://app.puredynamic.cloud/help)

## 4. Vergleichstabelle der Standbuch-Felder

**Legende:**

- „nein“: in der Quelle ausdrücklich nicht vorhanden oder nicht gefunden
- „?“: unbekannt
- „opt.“: optional

**Evidenz je Spalte:**

- [D] Doku oder Handbuch
- [C] öffentlich ausgelieferter Code oder Schema
- [H] nur Angaben des Herstellers
- [S] nur Zusammenfassungen von Suchtreffern

Die Spalte „bambamdoku“ zeigt den empfohlenen Kern aus Abschnitt 7.

### 4a. Deutsche Anbieter

| Feld                 | Rangetime [D, C]                                                    | Düsel [S]                       | svscore [C, D]                                      | VERION [H]                                | StandFrei [C]                               | StandLotse [H]                                                                | Schiessbuch.org [D]                                                                     | bambamdoku                                                                                   |
| -------------------- | ------------------------------------------------------------------- | ------------------------------- | --------------------------------------------------- | ----------------------------------------- | ------------------------------------------- | ----------------------------------------------------------------------------- | --------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------- |
| person               | Konto                                                               | Konto (vermutet)                | Personen-ID; QR oder Namenssuche                    | Konto                                     | Konto; „angelegt von“ getrennt              | Name aus Mitglieder-Import                                                    | eigenes Konto                                                                           | Mitglied (mit Mitgliedsnummer) oder Gastname                                                 |
| gast                 | eigenes Gastkonto, nur an freigegebenen Tagen                       | Rolle „Gast“, Ablauf ?          | in der Personendatenbank; am Kiosk kein Gast-Ablauf | nicht belegt                              | nur Name, nur durch Admin                   | Aufsicht erfasst beim Belegen; Stammgäste                                     | nein                                                                                    | nur Name und Kategorie; kein Konto; Minderjährige vorerst auf Papier                         |
| zeit                 | gebuchter Slot; kein Check-out                                      | An- und Abmeldung               | Kommen und Gehen; automatischer Check-out           | gebuchter Slot                            | geplanter Slot                              | Datum, Uhrzeit, Dauer                                                         | Datum und Startzeit                                                                     | Datum; Zeitstempel automatisch; kein Check-out                                               |
| stand/bahn           | Stand und Bahn                                                      | Stand; Bahn bei der Belegung    | Stand opt.; keine Bahn                              | Ressource (Stand oder Bahn)               | Halle und Stand                             | Bahn                                                                          | Stand aus Katalog                                                                       | weder Stand noch Bahn; Ort ist der Verein aus der Konfiguration                              |
| disziplin            | nein                                                                | ja, dazu Art der Aktivität      | Vereinsliste oder Freitext                          | nein (laut FAQ)                           | nein (nur im Hallennamen)                   | „Disziplin bzw. Waffe“                                                        | Katalog (u. a. DSB)                                                                     | Pflicht bei Mitgliedern; Verbandskatalog als Konfiguration                                   |
| waffe/kategorie      | Ausrüstungsart Pflicht; opt. einzelnes Stück                        | Waffenart; Kurz/Lang getrennt   | Kurz/Lang opt.                                      | nein                                      | nein (nur Restcode)                         | siehe Disziplin; Leihwaffe per Scan                                           | Waffentyp; opt. eigene Waffe                                                            | Kurz/Lang/erlaubnisfrei, Pflicht                                                             |
| kaliber              | Freitext, nur bei Munitionserfassung                                | ja                              | nein                                                | nein                                      | nein                                        | ja                                                                            | Katalog                                                                                 | nein (später, falls der Verband es verlangt)                                                 |
| eigen/vereinswaffe   | ja (Tresor oder Inventar)                                           | ?                               | nein                                                | nein                                      | nein (nur Restcode)                         | indirekt über Leihwaffe                                                       | kein eigenes Feld                                                                       | Pflicht bei Mitgliedern                                                                      |
| WBK                  | nein                                                                | ?                               | nur als Dokument der Person                         | nein                                      | nein                                        | nein                                                                          | indirekt (Profil, NWR-IDs)                                                              | bewusst nein                                                                                 |
| schusszahl           | opt., Pflicht oder limitiert                                        | ?                               | nein                                                | nein                                      | nein                                        | nein                                                                          | Pflicht                                                                                 | nein (später, falls der Verband es verlangt)                                                 |
| aufsicht-bestätigung | nicht je Eintrag; Anwesenheit manuell bestätigbar                   | Aufsicht je Slot; Bestätigung ? | nein; nur Dienstplan und Tagebuch                   | nein; nur nachträglich „nicht erschienen“ | nein; Aufsicht-Zeile ohne Wirkung           | Aufsicht legt im geführten Modus an (Feld „Aufsicht“); per RFID ohne Aufsicht | ja: verifizieren oder ablehnen; versehentliche Selbstverifizierung seit 1.0.5 blockiert | ja: Anlegen durch eine registrierte Aufsicht ist Bestätigen                                  |
| bestätigungsart      | Terminal mit E-Mail und PIN, rotierender QR, manuell (Standard aus) | ?                               | Kiosk unter Vereinskonto; „erfasst von“             | Selbstbuchung; opt. Genehmigung           | keine                                       | Name wählen oder RFID; Zusammenfassung vor dem Speichern                      | Liste oder QR-Scan, auch zeitversetzt                                                   | Eintrag im Konto der Aufsicht; der Server prüft das Register am Datum                        |
| export               | Schießbuch als PDF; Admin PDF und CSV                               | PDF und CSV                     | XLSX, JSON-API, Nachweis-PDF über 12 Monate         | PDF ab PRO; Nachweis faktisch nur MAX     | PDF (Admin und eigene), iCal                | Excel der ganzen Kladde, PDF je Tag                                           | PDF, voreingestellt nur verifizierte                                                    | CSV der Kladde (Admin); PDF-Auszug je Mitglied                                               |
| unveränderbarkeit    | schwach: Auto-Einträge standardmäßig änderbar                       | ?                               | Admin kann endgültig löschen                        | nicht belegt                              | Storno als Status; harte Löschung vorhanden | unveränderlich; Storno mit Grund                                              | Statusmaschine; nach Verifizierung ?                                                    | insert-only; Storno mit Grund; nichts wird gelöscht                                          |
| sichtbarkeit         | Schießbuch nur das Mitglied; Admin sieht Auswertungen               | ? (8 Rollen)                    | Mitglied eigene; Admin alle                         | Mitglied eigene; Vorstand alle            | Namensanzeige einstellbar; Report nur Admin | Vorstand, Aufsicht, Betrachter; kein Zugang für Mitglieder                    | Schütze eigene; Aufsicht ihr Stand; Verein keine                                        | Admin (Vorstand, Schießwart) alle; Aufsicht nur ihre heutigen Einträge; Mitglieder siehe Q1b |

### 4b. Ausländische und fachfremde Anbieter

| Feld                 | KNSA, NL [D]                                          | Range Mate, UK [D]                                                   | CelPal, PL [C]                                                                           | High Ready, AT [H]                                   | Joulix [D]                                   | PureDynamic, CH [D, C]              | bambamdoku                                               |
| -------------------- | ----------------------------------------------------- | -------------------------------------------------------------------- | ---------------------------------------------------------------------------------------- | ---------------------------------------------------- | -------------------------------------------- | ----------------------------------- | -------------------------------------------------------- |
| person               | Lizenznummer (QR oder Barcode)                        | Konto                                                                | Name, dazu Nummer der Erlaubnis und Behörde oder Adresse                                 | nur der Kontoinhaber                                 | Konto, Schütze ohne Konto, Vorname des Gasts | Datensatz je Schütze                | Mitglied oder Gastname                                   |
| gast                 | nur auf Papier (Gästeregister)                        | Gastkonto am einladenden Mitglied; Laufkundschaft durch die Aufsicht | über Kiosk, QR oder App; einführende Person                                              | nein                                                 | Link je Partie, nur bei Spielen              | wie ein Mitglied                    | nur Name und Kategorie                                   |
| zeit                 | nur Datum                                             | Datum und Termin; Haken ohne Uhrzeit                                 | Datum; kein Check-out                                                                    | Datum und Zeitspanne (vermutlich fest)               | Zeitraum des Trainings, Zeit je Schuss       | Datum des Schießtags                | Datum; Zeitstempel automatisch                           |
| stand/bahn           | nur Verein                                            | Stand über den Termin; Bahn opt.                                     | Stand; keine Bahn                                                                        | Anlage über NFC                                      | Anlage und Standnummer                       | Bahn im Durchgang                   | nur Verein                                               |
| disziplin            | nein                                                  | nicht am Eintrag                                                     | nein                                                                                     | nein (nur Art des Events)                            | aus der Anlage                               | Programm oder Stich                 | Pflicht bei Mitgliedern                                  |
| waffe/kategorie      | nein (in NL nicht relevant)                           | konkrete Waffe(n)                                                    | nein                                                                                     | Waffe aus dem Waffenschrank                          | nein                                         | Waffenart                           | Kurz/Lang/erlaubnisfrei                                  |
| kaliber              | nein                                                  | über die Waffe                                                       | nein                                                                                     | über die Waffe                                       | nein                                         | nein                                | nein                                                     |
| eigen/vereinswaffe   | nein (eigenes Papierregister)                         | eigen, Verein oder geliehen                                          | nein                                                                                     | nein                                                 | nein                                         | nein                                | Pflicht bei Mitgliedern                                  |
| WBK                  | Nummer der Erlaubnis erst beim Einreichen             | indirekt (Erlaubnis am Profil)                                       | Nummer der Erlaubnis (an der Person)                                                     | nein (Seriennummer opt.)                             | nein                                         | nein                                | bewusst nein                                             |
| schusszahl           | nein                                                  | je Waffe, Schnellfeld                                                | nein                                                                                     | ja, Selbstauskunft                                   | jeder Schuss (von der Anlage)                | vermutlich Summe je Schießtag       | nein                                                     |
| aufsicht-bestätigung | ja: Der Scan der Sicherheitsperson legt an            | Register mit verantwortlicher Aufsicht; Status „verified“            | opt.: Tagesaufsicht und Freigabe                                                         | nein (NFC und Biometrie)                             | nein                                         | nein (nur beim Fernwettkampf)       | ja: Anlegen ist Bestätigen                               |
| bestätigungsart      | Scan von QR oder Barcode; manuelle Nacherfassung      | Haken, dann „Validate & lock“; Kiosk                                 | Unterschrift auf dem Bildschirm, Karte, QR oder App, Geofence, Freigabe                  | NFC-Tag und Face ID oder Fingerabdruck               | Daten der Anlage                             | Foto, Scan oder QR des Standblatts  | Eintrag im Konto der Aufsicht                            |
| export               | Übermittlung an die Polizei und PDF; Verein als Excel | Jahresbericht druckbar; Format ?                                     | PDF und XLS, XLSX für den Verband, Aufenthaltsliste                                      | PDF nur mit Abo, ohne Filter nach Zeitraum           | CSV (Mitglied), JSON (Verein)                | Standblätter als PDF; kein Nachweis | CSV und PDF-Auszug                                       |
| unveränderbarkeit    | ? (Status „eingereicht“)                              | Sperre nach Frist, nach Check-in oder nach „Validate & lock“         | nach Freigabe gesperrt; Historie; Löschen wiederherstellbar, endgültiges Löschen möglich | nach Bestätigung kein Bearbeiten-Knopf mehr zu sehen | veränderbar („Nicht meins“)                  | Wettkampf-Durchgang sperrbar        | insert-only; Storno mit Grund                            |
| sichtbarkeit         | Schütze eigene; Verein das Anwesenheitsregister       | Mitglied eigene; Admins alle                                         | Kiosk zeigt keine früheren Einträge; Betreiber alle                                      | nur der Kontoinhaber                                 | Mitglied eigene; Trainer die Gruppe          | Rechte je Rolle                     | Admin alle; Aufsicht ihre heutigen; Mitglieder siehe Q1b |

**Was die Tabellen zeigen:**

- Unter den 13 erfassten Anbietern verbindet keiner ab Werk alle drei Dinge, die bambamdoku braucht:
    - eine Bestätigung je Eintrag durch die anwesende Aufsicht
    - die Felder nach § 14 WaffG (Kategorie, eigen oder Verein, Disziplin)
    - eine Kladde, die beim Verein liegt
- Am nächsten kommen vier Anbieter, jeweils mit einer Lücke:
    - **KNSA:** Bestätigung und Kladde beim Verein, aber keine Waffenfelder.
    - **Range Mate im Strict Mode:** Register beim Verein; eigene, Vereins- und geliehene Waffen; Prüfung durch die Aufsicht vor dem Check-in. Es fehlt die Disziplin, der Modus ist standardmäßig aus, und das Handbuch widerspricht sich bei der Verifikation.
    - **Schiessbuch.org:** Bestätigung, Disziplin und Waffentyp. Es fehlt ein Feld „eigen oder Verein“, und die Kladde liegt bei der Plattform.
    - **StandLotse (mit Vorbehalt, nur Herstellerangaben):** Die Aufsicht legt an, die Kladde liegt beim Verein, Leihwaffen sind vermerkt. Kurz- und Langwaffe sind nicht ausdrücklich getrennt, es gibt keinen Zugang für Mitglieder, und per RFID entsteht der Eintrag ohne Aufsicht.
- disLog, Hecht Software und Vereinskladde haben laut Suchtreffern Aufsichtsfreigaben, sind aber nicht geprüft. Bevor bambamdoku sich öffentlich abgrenzt, sollten diese drei mindestens öffentlich erfasst werden.

## 5. Ablauf am Stand

### 5.1 Beste Muster je Anbieter

| Anbieter                   | Muster                                                                                                                                                                                                                                                                                                            | Übernahme                                                                                                                            |
| -------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------ |
| KNSA                       | Bestätigen heißt Anlegen: Nur die Aufsicht erzeugt einen Eintrag, der Schütze zeigt nur seinen Code. Ein Vorgang speist die Sicht des Mitglieds und das Register des Vereins. Die Berechtigung zum Scannen ist befristet. Die manuelle Nacherfassung braucht drei Felder. Am Eintrag steht, wer ihn angelegt hat. | ja, ohne QR (Namenssuche reicht)                                                                                                     |
| CelPal                     | Ohne Tagesaufsicht lehnt das System Einträge ab. Ein zweiter Eintrag am selben Tag bekommt die Meldung „heute schon eingetragen“. Der Kiosk zeigt keine fremden Einträge. Die Felder richten sich streng nach der Verordnung.                                                                                     | ja, aber die Sperre sitzt am Eintrag: Er braucht eine registrierte Aufsicht, ein eigenes Tagesobjekt gibt es nicht                   |
| Range Mate                 | Die Aufsicht trägt Laufkundschaft und Mitglieder ohne Smartphone ein. Mindestzahlen zählen nur echte Check-ins. „Validate & lock“ friert das Register ein.                                                                                                                                                        | Eintragen durch die Aufsicht ja; statt „Abschließen“ sperrt das Datum: ab dem Folgetag darf nur der Admin stornieren oder nachtragen |
| svscore                    | Namenssuche ohne App, Bestätigungskarte mit Name und Uhrzeit, Qualifikation mit „gültig bis“                                                                                                                                                                                                                      | ja; vorbelegt wird aus dem letzten Eintrag der Person, nicht je Gerät                                                                |
| StandLotse                 | Rücknahme statt Bearbeiten. Storno nur mit Grund. Rechtliche Invarianten als Tests in der CI.                                                                                                                                                                                                                     | ja; Stornos stehen mit Vermerk im CSV und in der Admin-Ansicht, nicht im Auszug für den Verband                                      |
| StandFrei                  | „Für wen“ und „eingetragen von“ getrennt; ein eindeutiger Schlüssel gegen Doppelungen                                                                                                                                                                                                                             | ja                                                                                                                                   |
| Schiessbuch.org            | Wer bestätigt, kann sich nicht selbst bestätigen. Der Export enthält nur bestätigte Einträge. Disziplinen kommen aus einem Katalog.                                                                                                                                                                               | ja; der Entwurf, den der Schütze selbst anlegt, nein                                                                                 |
| Joulix                     | Gast und Schütze ohne Konto                                                                                                                                                                                                                                                                                       | ja; Frist für Gastnamen erst mit dem Löschlauf; ein Protokoll geänderter Felder braucht es nicht, weil nichts bearbeitet wird        |
| VERION                     | Export-Historie. Gegenbeispiel: Die Buchung zählt als Anwesenheit, solange niemand widerspricht.                                                                                                                                                                                                                  | Export-Historie später                                                                                                               |
| Düsel                      | Ehrliche Rolle als Datengrundlage, keine Bescheinigung; Kurz- und Langwaffe getrennt                                                                                                                                                                                                                              | ja, im Text des PDFs                                                                                                                 |
| PureDynamic                | Abgeschlossene Durchgänge werden gesperrt.                                                                                                                                                                                                                                                                        | nur als Prinzip: Die Sperre ergibt sich aus dem Datum                                                                                |
| Rangetime (Gegenbeispiel)  | Schutzschalter sind standardmäßig aus. Der Kiosk verlangt E-Mail und Passwort, nach Schätzung von Jury S kostet das 45 bis 90 s pro Person.                                                                                                                                                                       | nein; bei uns sind die Regeln fest eingebaut                                                                                         |
| High Ready (Gegenbeispiel) | Der Export hängt am Abo und hat keinen Filter nach Zeitraum. Ein statischer NFC-Tag lässt sich kopieren.                                                                                                                                                                                                          | nein                                                                                                                                 |

### 5.2 Empfohlener Ablauf für bambamdoku

Der Ablauf setzt Q1 = A, Q1b = b, Q2 = C und Q5 = A voraus. Was bei anderen Antworten anders wäre, steht am Ende.

**Einmalig vorab (Admin):**

- In der Konfiguration (`config/verein.php` bzw. `.env`) eintragen:
    - Vereinsname, Verband, Vereinsnummer im Verband, Anschrift
    - den Disziplinkatalog des Verbands
    - das Datum „digital seit“
- Mitglieder anlegen: Name und Mitgliedsnummer, E-Mail-Adresse optional. Ein Konto ist nicht nötig. Angelegt werden nur Volljährige; das Formular verlangt eine Bestätigung, gespeichert wird sie nicht.
- Aufsichtspersonen ins Register eintragen, je Prüfung eine Zeile: Umfang, gültig bis, geprüft am und von. Das Formular verlangt die Bestätigung „volljährig geprüft“.
- Aufsichten bekommen ein Konto über einen Einladungslink zum Kopieren.

**Am Stand:**

1. **Eintragen öffnen.** Die Aufsicht öffnet auf ihrem Gerät „Eintragen“. Hat sie heute keine gültige Registerzeile, ist die Ansicht gesperrt und nennt den Grund. Es gibt nichts zu eröffnen, zu übergeben oder zu schließen.
2. **Mitglied eintragen.** Der Schütze nennt seinen Namen. Die Aufsicht tippt zwei oder drei Buchstaben oder die Mitgliedsnummer und wählt die Person aus.
    - Vorbelegt aus dem letzten Eintrag der Person sind Kategorie, eigen oder Verein und Disziplin.
    - Passt etwas nicht, ändert ein Tap es. Dann „Eintragen“.
    - Jury S schätzt 5 bis 8 s pro Mitglied, gemessen ist das nicht.
3. **Prüfungen auf dem Server:**
    - Die eintragende Person hat am Datum eine gültige Registerzeile.
    - Sie trägt nicht sich selbst ein.
    - Das Datum setzt der Server, und zwar auf heute.
    - Einträge mit Kurz- oder Langwaffe gehen nur, wenn die Registerzeile den Umfang „erlaubnispflichtig“ hat.
    - Gibt es heute schon einen gültigen Eintrag mit demselben Schlüssel (Person, Datum, Kategorie, eigen oder Verein), meldet die Oberfläche „heute schon eingetragen“ und zeigt trotzdem die grüne Karte. So erzeugen ein doppelter Tap oder ein Neusenden nach Netzabbruch nichts Neues.
4. **Rückmeldung.** Eine grüne Karte zeigt Name und Uhrzeit. Die Rückmeldung ist sichtbar, nicht hörbar, weil die Schützen Gehörschutz tragen.
5. **Gast eintragen.** Unter „Gast hinzufügen“ gibt die Aufsicht den Namen ein, wählt die Kategorie und bestätigt „volljährig“ (Pflichthaken, wird nicht gespeichert). Jury S hat für die längere Variante mit Gastgeber 15 bis 20 s geschätzt, ohne Gastgeber dürften es etwa 10 s sein (nicht gemessen).
6. **Minderjährige** werden im MVP nicht digital eingetragen. Die Oberfläche sagt das ausdrücklich: „Minderjährige weiter auf Papier.“ Einwilligung und schriftliche Erklärung nach [§ 27 Abs. 3 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__27.html) und die Jugend-Aufsicht nach [§ 10 Abs. 5 AWaffV](https://www.gesetze-im-internet.de/awaffv/__10.html) laufen wie bisher (Q5).
7. **Fehler korrigieren.** Die Aufsicht storniert einen eigenen Eintrag von heute mit dem Schnellgrund „Fehleingabe“ und legt einen neuen an. Bearbeitet wird nichts.
8. **Mehrere Aufsichten** tragen jeweils mit ihrem eigenen Konto ein; jeder Eintrag zeigt, wer ihn angelegt hat. Zwei qualifizierte Personen können sich gegenseitig eintragen. Eine Übergabe gibt es nicht.
9. **Tagesende.** Es gibt nichts abzuschließen. Ab dem Folgetag kann die Aufsicht die Einträge nicht mehr stornieren, das ergibt sich aus dem Datum und braucht keinen Cron.

**Wenn Netz oder Server ausfallen:** Die Aufsicht führt die Papier-Ersatzliste. Die Vorlage liegt im Repo und hat dieselben Spalten wie ein Nachtrag: Datum, Name oder Mitgliedsnummer, Kategorie, eigen oder Verein, Disziplin, Aufsicht. Der Admin trägt die Liste später als Nachtrag ein, mehrere Personen in einem Formular mit einem gemeinsamen Grund, etwa „Papierliste vom …, Aufsicht: …“.

**Danach (Admin):**

- Vorstand und Schießwart sehen die Kladde, filtern nach Person und Zeitraum und exportieren sie als CSV.
- Sie erzeugen je Mitglied den PDF-Auszug mit Feld für Unterschrift und Stempel, auch für deaktivierte Mitglieder. Beim Austritt wird der Auszug ausgehändigt, zusammen mit der Meldung nach [§ 15 Abs. 5 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__15.html) (R18).
- Nachträge legt nur der Admin an, mit Pflichtgrund und gekennzeichnet.

**Vorgaben für die Oberfläche am Stand.** Das sind Designregeln nach Kriterium c, keine eigenen Funktionen (nach Jury S):

- Bedienelemente mindestens 64 px groß
- keine Wischgesten, keine Unterschrift
- der Schütze tippt nichts
- Rückmeldungen sichtbar, nicht hörbar

**Falls Q2 = A (Buchung bleibt wie gebaut):**

- Eine Buchung füllt nur die Eincheckliste der Aufsicht vor. Das Einchecken legt eine eigene, insert-only Zeile in `log_entries` an.
- Eine Buchung zählt nie als Anwesenheit (3:0). „Nicht erschienen“ bleibt ein Status der Buchung ohne Nachweiswert.
- „Zurücksetzen“ eines Check-ins wird zum Storno des Kladde-Eintrags mit Grund.

**Falls Q2 = B (Standdienst als eigenes Objekt):** Die Aufsicht eröffnet und schließt einen Dienst. Gebaut wird er aus `supervisions` mit `opened_at` und `closed_at`. Zusätzlich zum Tor aus Schritt 3 braucht ein Eintrag dann einen offenen Dienst. Gesperrt wird trotzdem über das Datum, nicht über den Scheduler.

## 6. Rechtliche Mindestanforderungen

### 6.1 Anforderungen mit Quelle und Verbindlichkeit

| ID  | Anforderung                                                                                                                                                                                                                                                                                                                                           | Quelle                                                                                                                                                                | Verbindlichkeit                                                      | Folge für den Kern                                                                                                                                                                                                                            |
| --- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| R01 | Der Verband muss seine Vereine verpflichten und regelmäßig prüfen, dass sie „einen Nachweis über die Häufigkeit der schießsportlichen Aktivitäten jedes ihrer Mitglieder“ in den 24 Monaten vor der Bedürfnisprüfung führen.                                                                                                                          | [§ 15 Abs. 1 Nr. 7 b WaffG](https://www.gesetze-im-internet.de/waffg_2002/__15.html)                                                                                  | Gesetz; trifft den Verband direkt, den Verein über das Verbandsrecht | Der Verein muss liefern können, deshalb gehört die Kladde dem Verein. Je Mitglied, Aufbewahrung mindestens 24 Monate. Der Wortlaut ist nicht auf erlaubnispflichtige Waffen beschränkt, deshalb bleiben erlaubnisfreie Termine im Kern (O15). |
| R02 | Erwerb: Das Mitglied hat innerhalb der vergangenen zwölf Monate „einmal in jedem ganzen Monat“ oder „18 Mal insgesamt“ den Schießsport betrieben, mit erlaubnispflichtiger Waffe. Vereinswaffen zählen, es braucht einen Bezug zur Disziplin.                                                                                                         | [§ 14 Abs. 3 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html); WaffVwV 14.4                                                                            | Gesetz                                                               | Einzeltermine; erlaubnisfrei zählt hier nicht; Feld Disziplin                                                                                                                                                                                 |
| R03 | Besitz: in den letzten 24 Monaten mit EIGENER erlaubnispflichtiger Waffe, entweder „mindestens einmal alle drei Monate in diesem Zeitraum“ (Nr. 1) oder „mindestens sechsmal innerhalb eines abgeschlossenen Zeitraums von jeweils zwölf Monaten“ (Nr. 2), getrennt nach Kurz- und Langwaffe (S. 2). Nach 10 Jahren genügt die Mitgliedschaft (S. 3). | [§ 14 Abs. 4 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html) i. V. m. § 4 Abs. 4                                                                      | Gesetz; Auslegung von Nr. 1 und Nr. 2 offen (O5)                     | Pflichtfelder Kategorie und eigen/Verein. Keine Rechnung im Kern, weil offen ist, ob „alle drei Monate“ ein Kalenderquartal meint und was „abgeschlossen“ heißt.                                                                              |
| R04 | Für § 14 Abs. 3 bescheinigt der Verband. Für Abs. 4 tut er das seit 01.01.2026 ebenfalls, bis 31.12.2025 konnte es der Verein.                                                                                                                                                                                                                        | § 14 Abs. 3 und 4 WaffG; [§ 58 Abs. 21 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__58.html)                                                                | Gesetz                                                               | Die App erzeugt einen „Auszug zur Vorlage beim Verband“, keine Bescheinigung.                                                                                                                                                                 |
| R05 | Die Bescheinigung braucht nachvollziehbare Angaben, ein Satz „erfüllt“ reicht nicht.                                                                                                                                                                                                                                                                  | [WaffVwV](https://www.verwaltungsvorschriften-im-internet.de/bsvwvbund_05032012_BMJKM5.htm) Nr. 14.2.1 (Nummerierung vor 2020)                                        | Verwaltungsvorschrift; bindet nur Behörden                           | Jeder Termin steht einzeln im PDF.                                                                                                                                                                                                            |
| R06 | Ein „vergleichbarer Nachweis“ statt Schießbuch ist zulässig.                                                                                                                                                                                                                                                                                          | [WaffVwV](https://www.verwaltungsvorschriften-im-internet.de/bsvwvbund_05032012_BMJKM5.htm) Nr. 4.4, 15.1                                                             | Verwaltungsvorschrift                                                | Digital grundsätzlich möglich; ob es ohne Unterschrift angenommen wird, ist offen (O2).                                                                                                                                                       |
| R07 | Der Betreiber überwacht, dass jeder rechtmäßig schießt.                                                                                                                                                                                                                                                                                               | [§ 9 Abs. 1 AWaffV](https://www.gesetze-im-internet.de/awaffv/__9.html)                                                                                               | Verordnung; Überwachungspflicht, keine Dokumentationspflicht         | Gastangaben minimal; kein Feld „Rechtsgrundlage“; keine WBK-Nummer                                                                                                                                                                            |
| R08 | Ohne ausreichende Aufsicht darf der Schießbetrieb weder beginnen noch weiterlaufen; die Aufsicht ist mindestens 18.                                                                                                                                                                                                                                   | [§ 10 Abs. 1 AWaffV](https://www.gesetze-im-internet.de/awaffv/__10.html); Ordnungswidrigkeit nach § 34 Nr. 4                                                         | Verordnung                                                           | Kein Eintrag ohne registrierte Aufsicht; die Sperre ist nicht abschaltbar.                                                                                                                                                                    |
| R09 | Der Verein registriert die Aufsichtspersonen und vermerkt die Sachkunde. Er stellt ihnen ein Nachweisdokument aus (S. 3) und gewährt auf Verlangen Einblick in die Registrierung (S. 5).                                                                                                                                                              | [§ 10 Abs. 3 AWaffV](https://www.gesetze-im-internet.de/awaffv/__10.html); WaffVwV 27.4.1, 27.7                                                                       | Verordnung                                                           | Aufsichtsregister im Kern. Das Nachweisdokument stellt der Verein vorerst auf Papier aus, Einblick gibt die Liste im Admin-Bereich.                                                                                                           |
| R11 | Die Aufsicht beaufsichtigt ständig. Wer zur Aufsicht befähigt ist, darf schießen, wenn er sicher allein auf dem Stand ist.                                                                                                                                                                                                                            | [§ 11 Abs. 1–3 AWaffV](https://www.gesetze-im-internet.de/awaffv/__11.html)                                                                                           | Verordnung                                                           | Allein schießen ist erlaubt, aber nicht aufzuzeichnen. Ein eigener Eintragsweg kommt später, bis dahin ein Admin-Nachtrag mit Grund.                                                                                                          |
| R12 | Der Name der Aufsicht hängt aus; die Aufsicht schießt während des Dienstes nicht.                                                                                                                                                                                                                                                                     | [DSB-Schießstandordnung](https://www.dsb.de/fileadmin/DSB.DE/PDF/PDF_2024/Schiessstandordnung_fuer_alle_Schusswaffen_05-2024.pdf) Nr. 12                              | Verbandspraxis                                                       | Keine Selbstbestätigung; der Aushang bleibt Papier.                                                                                                                                                                                           |
| R13 | Für erlaubnispflichtige Waffen braucht die Aufsicht zusätzlich die Waffensachkunde; für Jugendtraining eine Eignung (JuBaLi).                                                                                                                                                                                                                         | [DSB-Richtlinie Standaufsicht 2025](https://www.dsb.de/fileadmin/DSB.DE/PDF/PDF_2025/DSB_Richtlinine_Standaufsicht_2025.pdf)                                          | Verbandspraxis (über § 10 Abs. 6 AWaffV)                             | Feld „Umfang“ im Register; Prüfung bei Kurz- und Langwaffe. Die Jugend-Eignung kommt mit dem Jugendbetrieb.                                                                                                                                   |
| R14 | Altersgrenzen je Waffenart; Einwilligung des Sorgeberechtigten. Die Aufsicht nimmt die Einwilligung entgegen und hält sie vor.                                                                                                                                                                                                                        | [§ 27 Abs. 3–4 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__27.html)                                                                                        | Gesetz                                                               | Minderjährige im MVP nicht digital (Q5). Ein Haken würde die schriftliche Erklärung ohnehin nicht ersetzen.                                                                                                                                   |
| R15 | Erlaubnis für die Schießstätte und Versicherung des Betreibers; der Bescheid legt die zugelassenen Waffen fest.                                                                                                                                                                                                                                       | [§ 27 Abs. 1 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__27.html)                                                                                          | Gesetz                                                               | Keine Dokumentation je Gast; zugelassene Waffen je Stand erst später (O3)                                                                                                                                                                     |
| R16 | Auflagen im Erlaubnisbescheid können ein Standbuch verlangen.                                                                                                                                                                                                                                                                                         | [§ 9 Abs. 1–2 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__9.html)                                                                                          | Behördenpraxis                                                       | Eine bundesrechtliche Pflicht für ein Aufsichtsbuch fand die Recherche nicht; den Bescheid des Vereins prüfen (O3).                                                                                                                           |
| R18 | Der Verein meldet den Austritt eines WBK-Inhabers.                                                                                                                                                                                                                                                                                                    | [§ 15 Abs. 5 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__15.html)                                                                                          | Gesetz                                                               | Gehört zur Mitgliederverwaltung, daher später; der Auszug für deaktivierte Mitglieder ist im Kern.                                                                                                                                            |
| R19 | Ein Verzeichnis der Teilnehmer mit Personalien gibt es nur beim Verteidigungsschießen.                                                                                                                                                                                                                                                                | [§§ 22–24 AWaffV](https://www.gesetze-im-internet.de/awaffv/__24.html)                                                                                                | Verordnung                                                           | Gegenbeispiel: Diese schweren Felder NICHT übernehmen.                                                                                                                                                                                        |
| R20 | VdRBw: Kladde mit Aufsicht, Schusszahl und Kaliber; der Schießleiter zeichnet ab; pro Tag wird einmal gezählt.                                                                                                                                                                                                                                        | [VdRBw-Schießsportordnung](https://rk-karlsruhe.de/wp-content/uploads/2024/06/240102-Schiesssportordnung.pdf) Nr. 107–108                                             | Verbandspraxis                                                       | Bestätigt das Modell „ein Datensatz, zwei Sichten“; Schusszahl und Kaliber nur, wenn der Verband es verlangt                                                                                                                                  |
| R21 | BSSB: Der Vorstand unterschreibt, stempelt und bestätigt die Richtigkeit, falsche Angaben können seine Zuverlässigkeit gefährden. Die Disziplinnummer muss erkennbar sein.                                                                                                                                                                            | [BSSB-Ausfüllhilfe](https://www.bssb.de/fileadmin/Service/Waffenrecht/Regelung_fuer_Beduerfnisbescheinigungen/Ausfuellhilfe_WBK_Gruen.pdf)                            | Verbandspraxis                                                       | Der Vorstand sieht alle Einträge; SpO-Nummer; Feld für die Bestätigung im PDF; Nachweiswert hat der Auszug, den der Vorstand unterschreibt.                                                                                                   |
| R22 | Behördenformular: „Mitglied seit“, je Kategorie ein Ja oder Nein                                                                                                                                                                                                                                                                                      | [KPB Herford](https://herford.polizei.nrw/sites/default/files/2022-04/18_Bed%C3%BCrfnisbescheinigung%20Sportsch%C3%BCtzen%20%28%C2%A7%2014%20Abs.%204%20WaffG%29.pdf) | Behördenpraxis                                                       | „Mitglied seit“ erst, wenn das Formular des Verbands es verlangt                                                                                                                                                                              |
| R23 | Bedürfnis über dem Grundkontingent nur mit Wettkampfteilnahme                                                                                                                                                                                                                                                                                         | [§ 14 Abs. 5 WaffG](https://www.gesetze-im-internet.de/waffg_2002/__14.html)                                                                                          | Gesetz                                                               | Nicht im Kern                                                                                                                                                                                                                                 |
| R24 | Benutzungsordnung (BHDS-Muster): Schießbuch mit Beginn, Ende, Aufsicht und Vorkommnissen                                                                                                                                                                                                                                                              | [BHDS-Serviceinfo](https://schuetzen.erzbistum-koeln.de/export/sites/schuetzen/bhds-old-Service/service_infos/schiessstand_nutzungsordnung.pdf)                       | Verbandspraxis bzw. Vertrag                                          | Keine gesetzliche Pflicht; eine Notiz zu Vorkommnissen erst, wenn Bescheid oder Mietvertrag es verlangen (Abschnitt 8)                                                                                                                        |
| R25 | Datenminimierung, Speicherbegrenzung, Informationspflicht, Verzeichnis der Verarbeitungstätigkeiten, Datenschutz durch Voreinstellungen, Sicherheit                                                                                                                                                                                                   | [DSGVO](https://eur-lex.europa.eu/eli/reg/2016/679/oj) Art. 5, 6, 13, 25, 30, 32                                                                                      | Gesetz; die Anwendung auf Vereinsdaten ist eine Einschätzung         | Keine Waffendaten; Fristen festlegen; Hinweis als statischer Text; Sicherungen (Art. 32)                                                                                                                                                      |
| R26 | Die Obhut über das Schießen durch Kinder und Jugendliche übt eine dafür qualifizierte, auf der Schießstätte anwesende Aufsichtsperson aus. Sie ist für die Ausbildung leitend verantwortlich und darf jederzeit Weisungen geben.                                                                                                                      | [§ 10 Abs. 5 AWaffV](https://www.gesetze-im-internet.de/awaffv/__10.html)                                                                                             | Verordnung                                                           | Wer Minderjährige digital führt, muss auch diese Aufsicht prüfen. Deshalb kommen Minderjährige ganz oder gar nicht ins System (Q5).                                                                                                           |

R10 (Anzeige an die Behörde ohne Verband) und R17 (sicherheitstechnische Prüfung des Stands) betreffen den Kern nicht.

### 6.2 Was ausdrücklich nicht verlangt ist

- **Bahn- oder Standbuchung.** In den erfassten Rechtsquellen steht keine Norm dazu. § 10 Abs. 1 AWaffV knüpft an den Schießbetrieb an, nicht an eine Reservierung (R08).
- **Ein Dienst- oder Sitzungsobjekt bzw. Aufsichtsbuch.** Eine bundesrechtliche Pflicht fand die Recherche nicht (R16). Der Erlaubnisbescheid kann es aber verlangen (O3).
- **Gästeliste im Sportbetrieb.** Ein Verzeichnis mit Personalien gibt es nur beim Verteidigungsschießen (§ 24 AWaffV, R19). Für Gäste gilt nur die Überwachungspflicht nach § 9 Abs. 1 S. 3 AWaffV (R07).
- **Schusszahl, Kaliber, Beginn und Ende.** Das ist nur Verbands- bzw. Vertragspraxis (VdRBw R20, BHDS R24). Die Gewissheit ist laut Recherche „unklar“.
- **Seriennummer, WBK-Nummer, Hersteller, Modell.** Weder ein Gesetz noch ein gefundenes Verbandsmuster verlangt sie für den sportlichen Schießnachweis. § 24 AWaffV gilt nur für das Verteidigungsschießen.
- **Check-out oder Uhrzeit des Gehens.** Die Zählregeln brauchen nur das Datum (R02, R03).
- **Feld „Rechtsgrundlage“ beim Gast.** § 9 Abs. 1 S. 3 AWaffV ist eine Überwachungspflicht, keine Dokumentationspflicht (R07; Gewissheit „unklar“).

### 6.3 Aussagen von Herstellern, die wir nicht übernehmen

- **Rangetime** schreibt, das Schießbuch erfülle „§ 14 Abs. 5 WaffG“. Nach heutiger Nummerierung ist Abs. 5 die Regel zum Kontingent (R23; zur alten Nummerierung siehe R05). Einen Beleg gibt es nicht. Außerdem verspricht Rangetime „Rechtssicherheit“ durch eine Anwesenheitsbestätigung, die standardmäßig aus ist.
- **svscore** beruft sich für Bedürfnis-Ampel und Nachweis-PDF ebenfalls auf „§ 14 Abs. 5 WaffG“, also auf dieselbe falsche Absatzangabe.
- **High Ready** spricht von „Legalisierung per NFC“. Das ist eine unbelegte Behauptung zum österreichischen Recht.
- **CelPal** hält die Unterschrift auf dem Bildschirm für rechtswirksam. Das ist die Auslegung des Herstellers, bezogen auf Polen.
- **StandLotse** leitet seine 30 Monate aus „24 Monate Nachweis plus Puffer“ ab. Das Gesetz nennt einen Betrachtungszeitraum von 24 Monaten, aber keine Aufbewahrungsfrist (R01).
- **VERION:** Der Blog widerspricht der eigenen FAQ, etwa bei Disziplin und Zuordnung der Aufsicht.
- **Ehrliche Haftungshinweise** geben Düsel („Datengrundlage, der Verband bescheinigt“) sowie svscore und Schiessbuch.org („unverbindlich, keine rechtliche Bewertung“). Diese Haltung übernimmt bambamdoku, die Absatzangabe von svscore nicht.
- **bambamdoku selbst (Code in main):** Das PDF behauptet, jeder Termin sei „am Schießtag von der eingeteilten Standaufsicht bestätigt“ worden. Admins können aber an jedem Tag einchecken und zurücksetzen (`Bookings::canRunTheRange`). Der Satz stimmt also nicht immer und muss mit dem neuen Kern fallen (Q7).

### 6.4 Offene Rechtsfragen

| #   | Frage                                                                                                                                                                                                                                                                                                                                              | Wer klärt sie                                        |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------- |
| O1  | Welchem anerkannten Verband gehört der Pilotverein an (DSB/Landesverband, BDS, BDMP, DSU, VdRBw, BHDS)? Welches Formular und welcher Disziplinkatalog gelten? Gehört er mehreren an?                                                                                                                                                               | Pilotverein                                          |
| O2  | Nimmt der Verband einen rein digitalen Auszug an, also eine elektronische Bestätigung statt Unterschrift und Stempel (R06)?                                                                                                                                                                                                                        | Verband                                              |
| O3  | Was regelt der Erlaubnisbescheid der Schießstätte (§ 9 Abs. 2 WaffG): Standbuch, Mindestzahl an Aufsichten, zugelassene Waffen je Stand?                                                                                                                                                                                                           | Pilotverein (Bescheid)                               |
| O4  | Was verlangen die Schießstandrichtlinien an Aufzeichnungen über den Betrieb? Der Volltext war nicht prüfbar.                                                                                                                                                                                                                                       | offen                                                |
| O5  | Wie ist § 14 Abs. 4 S. 1 auszulegen? Meint „mindestens einmal alle drei Monate“ ein Kalenderquartal oder einen lückenlosen Abstand von höchstens drei Monaten? Braucht jeder der beiden abgeschlossenen 12-Monats-Zeiträume sechs Termine? Zählt pro Tag und Kategorie nur ein Termin? Muss eine ganze Disziplin geschossen werden (so der VdRBw)? | Verband                                              |
| O6  | Zählen Termine bei fremden Vereinen zur Aufzeichnung des eigenen Vereins? Wenn ja, welcher Beleg genügt?                                                                                                                                                                                                                                           | Verband                                              |
| O7  | Darf das Nachweisdokument der Aufsicht (§ 10 Abs. 3 S. 3 AWaffV) digital sein?                                                                                                                                                                                                                                                                     | Behörde bzw. Verband                                 |
| O8  | Genügt eine Einwilligung der Sorgeberechtigten, die im System gespeichert ist? Ist eine Einwilligung für eine ganze Saison zulässig (§ 27 Abs. 3 S. 1 gegenüber S. 2 WaffG)? Erst relevant mit dem Jugendbetrieb.                                                                                                                                  | Behörde                                              |
| O9  | Verlangt die Versicherung des Vereins eine namentliche Gästeliste, die Adresse, einen Gastgeber oder eine Höchstzahl an Besuchen?                                                                                                                                                                                                                  | Pilotverein (Versicherungsvertrag)                   |
| O10 | Welche Aufbewahrungsfristen gelten, und auf welche Rechtsgrundlage stützt sich das Protokoll der Mitglieder (Art. 6 Abs. 1 lit. b/f oder lit. c DSGVO)?                                                                                                                                                                                            | Vereinsbeschluss, Datenschutzbeauftragter (siehe Q6) |
| O11 | Soll die App ein Merkmal „WBK-Inhaber“ führen, damit sie beim Austritt an die Meldung erinnert (§ 15 Abs. 5 WaffG)?                                                                                                                                                                                                                                | später; Pilotverein                                  |
| O12 | Ist die Rechtslage noch aktuell? Die WaffVwV ist von 2012, die Änderung des WaffG vom 22.10.2026 ist laut gesetze-im-internet.de noch nicht vollständig eingearbeitet. Vor dem Release erneut prüfen.                                                                                                                                              | Projekt                                              |
| O13 | Vermietet der Pilotverein seinen Stand an fremde Vereine? Dann kämen Pflichten aus dem Mietvertrag dazu.                                                                                                                                                                                                                                           | Pilotverein                                          |
| O14 | Übergang von Papier zu digital: Werden alte Termine per Sammelnachtrag übernommen? Oder trägt jeder Auszug den Kopfvermerk „Digitale Aufzeichnung seit <Datum>; frühere Termine siehe Papierkladde“? Empfehlung: Kopfvermerk.                                                                                                                      | Pilotverein                                          |
| O15 | Gehören erlaubnisfreie Termine (Luftdruck, Bogen) in die Aufzeichnung nach § 15 Abs. 1 Nr. 7 b? Der Wortlaut („Häufigkeit der schießsportlichen Aktivitäten“) spricht dafür. Wenn nein, wird `weapon_class` zu short/long, und `qualifications.scope` entfällt.                                                                                    | Verband                                              |

## 7. LEAN-KERN

> **Kern unter den empfohlenen Antworten Q1 = A, Q1b = b, Q2 = C, Q3 = C und Q5 = A.**
>
> Bei anderen Antworten ändert sich Folgendes:
>
> - **Q1b = a:** Mitglieder bekommen ein Konto, sehen ihre eigenen Einträge und laden ihren Auszug selbst, gekennzeichnet als „zur Information“. Dazu kommen Einladungen für Mitglieder.
> - **Q2 = A:** `shooting_days`, `supervisions`, `bookings` und `ranges` bleiben, wie sie gebaut sind. Beim Einchecken entsteht eine eigene Zeile in `log_entries`, dazu kommt optional `log_entries.booking_id`.
> - **Q2 = B:** Ein Dienst-Objekt aus `supervisions` mit `opened_at` und `closed_at` kommt dazu. Ein Eintrag braucht dann zusätzlich einen offenen Dienst.
> - **Q3 = A oder B:** Dazu kommt eine Rechnung über Monate, 3-Monats-Abschnitte und 12-Monats-Blöcke. Für A braucht es außerdem Angaben, welche Kategorien das Mitglied besitzt.
> - **Q5 = B:** Dazu kommen das Dienst-Objekt mit Notiz und digitalem Aushang sowie Altersstufe, Einwilligungsvermerk und Jugend-Aufsicht.
> - **Q1 = B oder C:** Es kommt ein zweiter Schreibweg für das Mitglied dazu, mit eigenen Regeln für Bearbeiten und Löschen.

### 7.1 Funktionen im Kern

In der Spalte „Regel“ steht, welches Lean-Kriterium die Funktion erfüllt:

- **a:** Sie erzeugt, bestätigt oder exportiert den Eintrag „wer hat wann mit was geschossen“.
- **b:** Sie ist eine rechtliche Vorbedingung.
- **c:** Ohne sie funktioniert der Ablauf am Stand nicht.

| #   | Funktion                                                                                                                                                                                                                                                             | Regel | Stimmen R/S/B                                                                | Begründung                                                                                                                                                                                                  | Abweichung im Detail                                                                                                                                                                                                                                                                               |
| --- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----- | ---------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| K1  | Eine registrierte Aufsicht trägt ein, und damit ist der Eintrag bestätigt.                                                                                                                                                                                           | a     | 3:0                                                                          | Der Verein muss liefern können (R01), die Angaben müssen nachvollziehbar sein (R05). Muster: KNSA. Ein Vorgang statt Buchung, Check-in und Bestätigung.                                                     | keine                                                                                                                                                                                                                                                                                              |
| K2  | Prüfung am Eintrag, das „Tor“: Die eintragende Person hat am Datum eine gültige Registerzeile, trägt nicht sich selbst ein, das Datum ist heute (setzt der Server), und für Kurz- und Langwaffe hat sie den Umfang `licensed`. Die Sperre ist nicht abschaltbar.     | b     | „ohne Aufsicht kein Eintrag“ 3:0; Prüfung von Register, Alter und Umfang 3:0 | Ohne Aufsicht kein Schießbetrieb (R08); Register (R09); Umfang nach DSB-Richtlinie (R13). Bei Rangetime ist die entsprechende Option standardmäßig aus.                                                     | Alle drei Jurys wollten dafür einen Dienst: R einen Aufsichtsdienst je Tag und Zeitfenster, S eine Standsitzung, B einen Standdienst. Gewählt ist die schlankste Variante ohne eigenes Objekt. Ein offener Dienst belegt keine Anwesenheit, die eintragende Aufsicht schon. Rückfall siehe Q2 = B. |
| K3  | Namenssuche nach Name oder Mitgliedsnummer, vorbelegt aus dem letzten Eintrag der Person                                                                                                                                                                             | c     | Suche 3:0; Vorbelegung S+B                                                   | Ohne schnelle Suche staut sich die Schlange am Stand. Die Mitgliedsnummer trennt gleiche Namen.                                                                                                             | S wollte Standardwerte je Stand; ohne Stände entfällt das.                                                                                                                                                                                                                                         |
| K4  | Mitglieder lassen sich ohne Konto und ohne Smartphone eintragen.                                                                                                                                                                                                     | c     | S+B ausdrücklich, R passt dazu                                               | Ein Teil der Mitglieder hat kein Smartphone oder keine E-Mail-Adresse.                                                                                                                                      | Braucht eine Migration: `users.email` und `users.password` sind heute Pflicht.                                                                                                                                                                                                                     |
| K5  | Schutz vor Doppeleinträgen über einen eindeutigen Index für gültige Einträge. Ein zweiter Tap oder ein Neusenden zeigt „heute schon eingetragen“ als Erfolg.                                                                                                         | c     | Schutz 3:0; Idempotenz S+B                                                   | Mit Handschuhen wird doppelt getippt, bei schlechtem Netz wird neu gesendet.                                                                                                                                | Statt eines zusätzlichen Idempotenzschlüssels reicht der Index (Abschnitt 7.2). R wollte „eigen ersetzt Verein“. Gezählt wird ohnehin über unterschiedliche Tage je Kategorie, deshalb ändert die Wahl des Schlüssels keine Zählung.                                                               |
| K6  | Keine Selbstbestätigung, ohne Ausnahme                                                                                                                                                                                                                               | a     | 3:0                                                                          | Sonst ist der Nachweis wertlos. Muster: Schiessbuch.org. Verbandspraxis: Die Aufsicht schießt nicht (R12). Im heutigen Code verhindert das Einchecken das nicht.                                            | Der Sonderweg „allein auf dem Stand“ kommt später (Abschnitt 8).                                                                                                                                                                                                                                   |
| K7  | Gast ohne Konto: Name und Kategorie; das Formular verlangt die Bestätigung „volljährig“                                                                                                                                                                              | a/b   | Gast ohne Konto 3:0; Gastkonten gestrichen 3:0                               | Überwachungspflicht (R07). Eine Gästeliste ist nicht verlangt (R19). Die Kategorie braucht K2 für die Prüfung des Umfangs.                                                                                  | Volljährigkeit wird nicht gespeichert. Den Gastgeber wollten R und B als Pflicht, er kommt später (O9, Lean-Override). Besitz und Disziplin entfallen, weil das Bedürfnis eines Gasts dessen eigener Verein nachweist.                                                                             |
| K8  | Storno statt Bearbeiten: In die Kladde wird nur geschrieben. Ein Storno braucht immer einen Grund, am selben Tag gibt es den Schnellgrund „Fehleingabe“; danach folgt ein neuer Eintrag. Die Aufsicht storniert nur eigene Einträge von heute, danach nur der Admin. | a     | Storno statt Löschen 3:0                                                     | Nachvollziehbarkeit (R05); der Vorstand bestätigt die Richtigkeit (R21). Die Tabelle ist damit selbst das Protokoll.                                                                                        | S, R und B wollten freies Korrigieren bis Dienstende bzw. in einem kurzen Fenster. Gewählt ist das Muster von StandLotse: Rücknahme statt Bearbeiten.                                                                                                                                              |
| K9  | Nachtrag nur durch den Admin, gekennzeichnet, mit Pflichtgrund. Der Grund nennt Beleg und Aufsicht laut Beleg. Ein Formular nimmt mehrere Personen mit gemeinsamem Datum und Grund.                                                                                  | a     | Nachtrag 3:0                                                                 | Lücken durch Netzausfall, vergessene Einträge, Papierlisten                                                                                                                                                 | Alle wollten Nachträge auch durch die Aufsicht; ein Weg reicht, weil die Aufsicht am selben Tag ohnehin neu einträgt. Fremder Ort (R+B) kommt später (O6).                                                                                                                                         |
| K10 | Aufsichtsregister, in das nur geschrieben wird: Umfang (`exempt` oder `licensed`), gültig bis, geprüft am und von. Jede Prüfung und jeder Entzug ist eine neue Zeile, es gilt die jüngste.                                                                           | b     | Register 3:0                                                                 | § 10 Abs. 3 AWaffV (R09). Nur eine Registerzeile, die man nicht überschreiben kann, belegt später, wer am Datum qualifiziert war.                                                                           | Typ „Jugend“, Nachweisquelle und das Feld „volljährig bestätigt“ entfallen; die Bestätigung steckt im Formular. Nachweisdokument und Register-Export (R+B) kommen später. `users.supervisor_until` wird übernommen und entfernt.                                                                   |
| K11 | Sichtbarkeit: Eine Admin-Rolle, die nur Vorstand und Schießwart bekommen, sieht alles. Die Aufsicht sieht nur ihre heutigen Einträge. Gäste haben kein Konto. Mitglieder siehe Q1b.                                                                                  | b     | 3:0                                                                          | R01, R21; Datenschutz durch Voreinstellungen (R25, Einschätzung)                                                                                                                                            | S wollte die Einsicht an die Vereinsfunktion binden, R und B halten eine Rolle für genug; gelöst durch eine Rolle, die nur diese Funktionen erhalten.                                                                                                                                              |
| K12 | Kladde für den Admin: Filter nach Person und Zeitraum; CSV mit allen Einträgen, stornierte mit Vermerk; PDF je Mitglied, auch für deaktivierte                                                                                                                       | a     | 3:0                                                                          | Der Verband prüft die Aufzeichnung (R01); der Verein prüft vor der Bescheinigung durch den Verband (R20, R21); Vereinswechsel und Austritt (R18). Kein Lock-in.                                             | Den CSV-Export für Mitglieder wollten R und B; Anfragen nach Art. 15 und 20 DSGVO bedient der Admin mit seinem CSV (Lean-Override).                                                                                                                                                                |
| K13 | PDF-Auszug „Auszug aus der Schießkladde des Vereins zur Vorlage beim Verband“, Aufbau siehe unten                                                                                                                                                                    | a     | 3:0                                                                          | R04, R05, R21                                                                                                                                                                                               | Die neutrale Zählung über Monate und Quartale (3:0) wird zur Liste mit Anzahl je Kategorie und Besitz (Q3 = C). „Mitglied seit“ (nur R) kommt später.                                                                                                                                              |
| K14 | Disziplinkatalog des Verbands als versionierte Konfiguration im Repo (Schlüssel „Verband:SpO-Nummer“, Name), gewählt über `.env`. Änderungen kommen per Release.                                                                                                     | a     | Katalog 3:0                                                                  | Disziplin nach Sportordnung (§ 9 Abs. 1 Nr. 2a AWaffV, R07); der BSSB verlangt die Nummer (R21). Mit dem Verband im Schlüssel bleibt die SpO-Nummer eindeutig, wenn der Verein mehreren Verbänden angehört. | Statt Tabelle, `active`-Feld und Pflege-Oberfläche (S8). Gewissheit nur „wahrscheinlich“; mit dem Verband bestätigen (O1).                                                                                                                                                                         |
| K15 | Vereinsdaten in der Konfiguration: Name, Verband(e), Vereinsnummer, Anschrift, „digital seit“                                                                                                                                                                        | a     | Teil von K13 (3:0)                                                           | Kopf des Auszugs (R05, R21); Kopfvermerk für den Übergang (O14)                                                                                                                                             | `config/verein.php` hat schon Name und Anschrift. Die Oberfläche zum Bearbeiten (`settings`, PR #7) ist Komfort und gehört nicht in den Kern.                                                                                                                                                      |
| K16 | Minimale Benutzerverwaltung: Personen einzeln anlegen (Name, Mitgliedsnummer, E-Mail optional); Einladungslink nur für Konten, also Admin und Aufsichten; Rollen `admin` und `member`; deaktivieren statt löschen; kein Selbstlöschen                                | c     | 3:0 (B ausdrücklich, R und S setzen es voraus)                               | Ohne Personen im System findet die Aufsicht niemanden. Der kopierbare Link macht den Mailversand unkritisch.                                                                                                | Gruppen und die Rolle `guest` gestrichen. Das Selbstlöschen des Kontos gibt es heute (`profile.destroy`); es widerspricht „nichts wird gelöscht“.                                                                                                                                                  |

**Aufbau des Auszugs (K13):**

- **Kopf:**
    - Vereinsdaten aus K15
    - Mitglied mit Mitgliedsnummer
    - Zeitraum und Stichtag (Pflicht beim Erzeugen; Schnellwahl 12 oder 24 Monate vor dem Stichtag)
    - Erstellt am, erstellt von
    - Kopfvermerk „Digitale Aufzeichnung seit …“
- **Spalten:** Datum, Kategorie, eigen oder Verein, Disziplin (Verband und SpO-Nummer), eingetragen von, Kennzeichen „Nachtrag“
- **Inhalt:** nur gültige Einträge. Am Ende die Anzahl der Tage je Kategorie, getrennt nach „eigene Waffe“ und „alle“.
- **Abschluss:**
    - Hinweis „keine Bescheinigung, keine rechtliche Bewertung; bescheinigen muss der Verband“
    - Feld für Unterschrift und Stempel des Vorstands
- **Nachweiswert** hat erst der Auszug, den der Vorstand unterschreibt (R21). Bei Q1b = a trägt der Auszug, den das Mitglied selbst lädt, den Vermerk „zur Information“.

**Prozess, keine Funktion (3:0): rechtliche Invarianten als Pest-Tests in der CI**

- Es wird nichts gelöscht. Auf `log_entries` und `qualifications` gibt es kein DELETE. Fremdschlüssel sind `restrictOnDelete`. Ein Benutzer mit Einträgen lässt sich nur deaktivieren.
- Auf `log_entries` ist als UPDATE nur das Setzen der Storno-Felder erlaubt. `qualifications` ist append-only.
- Einen Eintrag legt nur an, wer am Datum eine gültige Registerzeile hat. Kurz- und Langwaffe nur mit `licensed`.
- Niemand trägt sich selbst ein.
- Die Aufsicht trägt nur für heute ein und storniert nur eigene Einträge von heute. Ab dem Folgetag stornieren oder ergänzen nur Admins, und ein Nachtrag ohne Grund wird abgelehnt.
- Die Aufsicht sieht nur ihre heutigen Einträge. Die Rechte je Rolle stimmen.
- Zwei Doppel-Taps und ein Netzabbruch ergeben genau einen gültigen Eintrag.

Muster: StandLotse.

**Betriebsvoraussetzungen (keine Funktionen, aber Bedingung für den Echtbetrieb)**

Die Kladde ersetzt eine Aufzeichnung, die mindestens 24 Monate halten muss (R01). docs/DEPLOY.md führt „Backups der SQLite-Datei“ unter „Offen“.

1. **Tägliche Sicherung:** `VACUUM INTO` in ein Verzeichnis außerhalb des Deploy-Spiegels, mit Rotation. Die Sicherungen werden regelmäßig heruntergeladen. Alternative siehe Q8.
2. **`cron.php` darf eine fehlende Datenbank nur beim allerersten Start anlegen,** erkennbar etwa an einer Marker-Datei. Sonst bricht es laut ab. Heute legt `cron.php` eine fehlende SQLite-Datei still neu an, migriert und seedet einen neuen Admin. Ein verlorener Datenbestand würde also leer weiterlaufen.
3. **Restore-Test** im Feldtest.
4. **Vor jeder Migration, die Daten entfernt, wird eine Sicherung erzwungen.** Lieber stilllegen als droppen, denn `cron.php` spielt neue Migrationen spätestens eine Minute nach dem Deploy ein.
5. **Deploy nur nach grüner CI:** `scripts/deploy-hetzner.sh` bricht ab, wenn HEAD nicht `origin/main` ist oder der CI-Lauf für diesen Commit nicht grün war (Prüfung mit `gh run list`). Erst damit schützen die Invarianten die Kladde.
6. **Papier-Ersatzliste** als Vorlage im Repo, Spalten wie beim Nachtrag (K9).

### 7.2 Datenmodell

**`log_entries`: der Eintrag in der Kladde**

| Feld                                                                                 | Pflicht                               | Begründung                                                                                                                                                                                                                                                                                  |
| ------------------------------------------------------------------------------------ | ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `id`                                                                                 | ja                                    |                                                                                                                                                                                                                                                                                             |
| `member_id`                                                                          | ja, wenn kein Gast                    | Der Verein zeichnet je Mitglied auf (R01). Genau eines von `member_id` und `guest_name` ist gesetzt. Fremdschlüssel `restrictOnDelete`.                                                                                                                                                     |
| `guest_name`                                                                         | ja bei Gast                           | Nur der Name, keine Adresse (R07, R19)                                                                                                                                                                                                                                                      |
| `shot_on` (Datum)                                                                    | ja                                    | Die Zählregeln brauchen einzelne Daten (R02, R03, R05). Bei Einträgen am Stand setzt es der Server auf heute.                                                                                                                                                                               |
| `created_at`                                                                         | ja, automatisch                       | Zeitpunkt von Eintrag und Bestätigung                                                                                                                                                                                                                                                       |
| `weapon_class` (`short`, `long`, `exempt`)                                           | ja, auch bei Gästen                   | Kurz und Lang getrennt (§ 14 Abs. 4 S. 2, R03). Erlaubnisfrei zählt nicht für § 14, wohl aber für die Häufigkeit nach § 15 Abs. 1 Nr. 7 b (R01, O15). Die Prüfung des Umfangs (K2) braucht das Feld auch bei Gästen.                                                                        |
| `weapon_owner` (`own`, `club`)                                                       | ja bei Mitgliedern, auch bei `exempt` | Für den Besitz zählt nur die eigene Waffe (R03), für den Erwerb auch die des Vereins (R02). Als Pflichtfeld kann es im Index nicht NULL sein.                                                                                                                                               |
| `discipline` (Schlüssel aus dem Katalog, z. B. Verband:SpO-Nummer)                   | ja bei Mitgliedern                    | Disziplin nach Sportordnung (R07, R21); im heutigen PLAN optional, im heutigen Code nicht vorhanden                                                                                                                                                                                         |
| `recorded_by_id`                                                                     | ja, automatisch                       | Wer eingetragen und damit bestätigt hat. Trennt „für wen“ von „durch wen“ (Muster: StandFrei). Fremdschlüssel `restrictOnDelete`.                                                                                                                                                           |
| `source` (`stand`, `late_entry`)                                                     | ja                                    | Herkunft. Ein Nachtrag wird im Auszug gekennzeichnet. Ob ein Eintrag bestätigt ist, folgt aus `source`.                                                                                                                                                                                     |
| `late_reason`                                                                        | ja bei `late_entry`                   | Pflichtgrund mit Beleg und Aufsicht laut Beleg, z. B. „Papierliste vom …, Aufsicht: …“ oder „§ 11 Abs. 3 AWaffV, allein“                                                                                                                                                                    |
| `status` (`active`, `cancelled`), `cancel_reason`, `cancelled_by_id`, `cancelled_at` | Status ja; der Rest bei Storno        | Storno statt Löschen (R05, R21). Nur diese Felder dürfen sich nach dem Anlegen ändern.                                                                                                                                                                                                      |
| `active_key` (generierte Spalte: 1 bei `active`, sonst NULL)                         | automatisch                           | Trägt die Eindeutigkeit nur für gültige Einträge. MariaDB kennt keine partiellen Indizes; NULL kollidiert in Unique-Indizes nicht, stornierte Zeilen stören also nicht. In Laravel mit `storedAs` beim Anlegen der Tabelle. Unter der jeweils produktiven Datenbank mit einem Test belegen. |
| eindeutige Indizes                                                                   |                                       | Mitglieder: (`member_id`, `shot_on`, `weapon_class`, `weapon_owner`, `active_key`). Gäste: (`guest_name`, `shot_on`, `active_key`).                                                                                                                                                         |

**Bewusst nicht im Eintrag:**

- `confirmed_by_id`: wäre immer gleich `recorded_by_id`
- `session_id` (gibt es nur bei Q2 = B) und `booking_id` (gibt es nur bei Q2 = A)
- ein Idempotenzschlüssel des Clients, weil der Index genügt
- Stand, Bahn, `checked_out_at`
- Schusszahl und Kaliber (später)
- Seriennummer, WBK-Nummer, Hersteller und Modell (niemals)
- Gastgeber (später, O9), Volljährigkeit und Einwilligung (später, Q5), fremder Ort (später, O6)
- private Notiz, Fotos, Rechtsgrundlage

**Weitere Tabellen und Konfiguration im Kern**

```
qualifications  id, user_id, scope(exempt|licensed), valid_until, checked_on,
                checked_by_id, created_at
                -- Register nach § 10 Abs. 3 AWaffV; nur anhängen, es gilt die jüngste Zeile
users           id, name, member_number?, email?, password?, role(admin|member), active
                -- email und password werden optional (K4); deaktivieren statt löschen
config/verein   name, address, federation(s), club_number, digital_since,
                discipline_catalog            -- über .env, versioniert im Repo (K14, K15)
```

### 7.3 Ist und Ziel im Repo

Stand: origin/main `f07b679` (PRs #1 bis #10), dazu der Branch `fix/kleinigkeiten`.

„Stilllegen“ heißt:

- Routen, Oberflächen und Navigation entfernen und nichts Neues darauf aufbauen.
- Die Tabellen bleiben vorerst stehen, bestehende Migrationsdateien werden nicht gelöscht.
- Eine Drop-Migration kommt erst nach einer Sicherung (Betriebsvoraussetzung 4).
- Der Code der Buchung bleibt auf einem Archiv-Branch erhalten, damit Q2 = D später darauf aufbauen kann.

| Objekt im Repo                                                                                                                                  | Stand                      | Ziel bei Q2 = C                                                                                                                                                                                      | Ziel bei Q2 = A                                                                        | betroffene Tests                                                    |
| ----------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------- | ------------------------------------------------------------------- |
| `users.role` mit `guest` (`App\Enums\Role::Guest`)                                                                                              | in main (fe7497b, M1)      | `guest` entfernen                                                                                                                                                                                    | `guest` entfernen                                                                      | `RoleTest`, `Admin/UserManagementTest`                              |
| `users.member_number`                                                                                                                           | in main (M1)               | behalten (Suche, Auszug)                                                                                                                                                                             | behalten                                                                               | –                                                                   |
| `users.supervisor_until` (überschreibbar)                                                                                                       | in main (M1)               | per Datenmigration in `qualifications` überführen, dann entfernen                                                                                                                                    | ebenso                                                                                 | `BookingRulesTest`, `Admin/UserManagementTest`                      |
| `users.member_since`, Mitgliedschaftsbescheinigung (`pdf/membership`)                                                                           | in main (M5)               | stilllegen, später (R22, 10-Jahres-Fall)                                                                                                                                                             | ebenso                                                                                 | `ShootingRecordTest`                                                |
| `users.email` und `users.password` als Pflicht                                                                                                  | Starter-Kit (M0)           | optional machen (K4)                                                                                                                                                                                 | optional machen                                                                        | Tests unter `tests/Feature/Auth`                                    |
| Passkeys (Tabelle, Routen), E-Mail-Verifizierung (`MustVerifyEmail`, Middleware `verified`), 2FA-Spalten                                        | Starter-Kit (M0)           | Passkeys und Verifizierung entfernen; die Verifizierung ist ohnehin tot, weil die Einladung `email_verified_at` sofort setzt. 2FA nur für Admins als bewusste Entscheidung zu Art. 32, sonst später. | ebenso                                                                                 | `tests/Feature/Auth`, `Settings/SecurityTest`                       |
| Selbstlöschen des Kontos (`profile.destroy`)                                                                                                    | Starter-Kit (M0)           | entfernen                                                                                                                                                                                            | entfernen                                                                              | `Settings/ProfileUpdateTest`                                        |
| `groups`, `group_user`, `group_shooting_day`, `shooting_days.restricted_to_groups`                                                              | in main (M1, M2)           | stilllegen                                                                                                                                                                                           | stilllegen (3:0 gestrichen)                                                            | `Admin/UserManagementTest`, `Admin/ShootingDayManagementTest`       |
| `ranges` (`lanes`, `weapon_types` JSON, `position`, `active`), `range_shooting_day`                                                             | in main (M2)               | stilllegen; Ort ist der Verein aus der Konfiguration                                                                                                                                                 | behalten; `weapon_types` ist dann „zugelassene Waffen je Stand“ (R15)                  | `Admin/ShootingDayManagementTest`, `BookingRulesTest`               |
| `shooting_days` (`series`, `title`, `notes`, `guests_allowed`, Absage mit Mail aus PR #6)                                                       | in main (M2, PR #6)        | stilllegen                                                                                                                                                                                           | behalten, ohne `series`                                                                | `Admin/ShootingDayManagementTest`                                   |
| `supervisions`                                                                                                                                  | in main (M3)               | stilllegen; das Tor aus K2 ersetzt sie                                                                                                                                                               | behalten                                                                               | `BookingRulesTest`, `BookingHttpTest`                               |
| `bookings` (`host_id`, `club_weapon`, `checked_in_by_id`, Status `no_show`), Raster, „Meine Buchungen“, Stornofrist                             | in main (M3)               | stilllegen                                                                                                                                                                                           | behalten als Vorbefüllung                                                              | `BookingRulesTest`, `BookingHttpTest`, `Unit/Domain/TimeWindowTest` |
| Standansicht `range-office` (Polling alle 15 s, „nicht erschienen“, „zurücksetzen“)                                                             | in main (M4)               | ersetzen durch „Eintragen“ (Abschnitt 5.2)                                                                                                                                                           | umbauen: Einchecken legt `log_entries` an, kein Zurücksetzen ohne Storno, kein Polling | `RangeOfficeTest`                                                   |
| `ShootingRecord` (Kladde aus Buchungsstatus, `fulfilled`), `Checks.svelte`, `pdf/record`                                                        | in main (M5)               | neu auf `log_entries`; Liste mit Anzahl (Q3 = C)                                                                                                                                                     | ebenso                                                                                 | `ShootingRecordTest`                                                |
| `config/verein.php` (`name`, `address`, `slot_minutes`, `cancel_minutes_before`, `booking_horizon_days`); `settings`-Tabelle und `ClubSettings` | in main (PR #7)            | Name und Anschrift in der Konfiguration behalten, Buchungswerte entfernen; Oberfläche stilllegen                                                                                                     | Name und Anschrift behalten                                                            | –                                                                   |
| Protokoll `audit_entries` mit Verwaltungsseite (`actor_id` `nullOnDelete`)                                                                      | in main (PR #8)            | stilllegen; die insert-only Kladde ist ihr eigenes Protokoll                                                                                                                                         | behalten für Buchungen und Aufsichten; die Kladde liegt trotzdem getrennt              | `Admin/AuditTest`                                                   |
| Erinnerungen am Vortag (`verein:erinnern`), Kalender-Abo                                                                                        | in main (PR #9)            | stilllegen                                                                                                                                                                                           | stilllegen (B: gestrichen)                                                             | zugehörige Feature-Tests                                            |
| CSV-Import für Mitglieder                                                                                                                       | in main (PR #10)           | stilllegen (S+B: später, Auslöser regelmäßiger Abgleich)                                                                                                                                             | ebenso                                                                                 | zugehörige Feature-Tests                                            |
| Mails „Aufsicht gesucht“ (`verein:aufsicht-gesucht`), Löschen leerer Schießtage                                                                 | Branch `fix/kleinigkeiten` | nicht mergen                                                                                                                                                                                         | „Aufsicht gesucht“ nicht mergen (Komfort); Löschen leerer Tage unkritisch              | `Admin/ShootingDayManagementTest`                                   |
| `log_entries`, `qualifications`                                                                                                                 | gibt es noch nicht         | neu anlegen, Fremdschlüssel `restrictOnDelete`                                                                                                                                                       | neu anlegen                                                                            | neue Invarianten-Tests                                              |
| Fremdschlüssel mit `cascadeOnDelete` bzw. `nullOnDelete` (`supervisions.user_id`, `bookings.shooting_day_id`/`range_id`, `bookings.user_id`)    | in main (M3)               | entfällt mit dem Stilllegen                                                                                                                                                                          | unkritisch, weil die Kladde dann getrennt liegt                                        | –                                                                   |

## 8. Bewusst gestrichen oder später

| Funktion                                                                                                                         | Status                                                | Stimmen R/S/B                                                                      | Grund                                                                                                                                                                                                                   | Auslöser (bei „später“)                                                                                                                                                                |
| -------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------- | ---------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Bahnbuchung (Reservierung je Slot, Kapazität, Stornofrist)                                                                       | später                                                | 3:0                                                                                | Kein Gesetz verlangt sie, und sie erzeugt keinen Eintrag. VERION und StandFrei räumen selbst ein, dass eine Buchung keine Teilnahme belegt.                                                                             | Der Pilotverein meldet einen echten Engpass bei den Bahnen. Dann nur als Vorbefüllung der Eincheckliste (Q2 = D).                                                                      |
| Ankündigung von Standdiensten (Datum, Zeit, Aufsicht)                                                                            | später (Lean-Override)                                | R+S Kern, B später                                                                 | Erzeugt keinen Eintrag, ist keine Pflicht, der Stand läuft ohne sie (KNSA, svscore).                                                                                                                                    | Mitglieder finden die Öffnungszeiten nachweislich nicht über Website oder Messenger. Dann ein schlanker Dienstplan ohne Bahnen.                                                        |
| Standdienst als eigenes Objekt (eröffnen, schließen, Übergabe, automatisches Schließen)                                          | gestrichen bei Q2 = C                                 | 3:0 für einen Dienst; das Wie ist streitig                                         | Eine eigene Zustandsmaschine, die nichts absichert, was K2 nicht schon leistet. Ein Aufsichtsbuch ist bundesrechtlich nicht verlangt (R16).                                                                             | Rückfall Q2 = B, wenn der Nutzer den Dienst behalten will                                                                                                                              |
| Notiz zu Vorkommnissen bzw. Standbuch je Sitzung                                                                                 | später (Lean-Override)                                | S+B Kern, R später                                                                 | Erfüllt weder a noch b noch c: bundesrechtlich nicht verlangt (R16), der Eintrag entsteht ohne sie, der Stand läuft ohne sie. Ohne Dienst-Objekt hat sie keinen Ort. Vorkommnisse bleiben im bisherigen Standbuch (Q5). | Der Bescheid oder ein Mietvertrag verlangt ein Standbuch (O3, R16, R24).                                                                                                               |
| Digitaler Aushang „Aufsicht jetzt“                                                                                               | später (Lean-Override)                                | R+S Kern (als Teil des Dienstes), B gestrichen                                     | Verbandspraxis (R12), keine Pflicht; auf Papier billiger                                                                                                                                                                | zusammen mit einem Dienst-Objekt (Q2 = B)                                                                                                                                              |
| Minderjährige digital: Altersstufe, Einwilligungsvermerk, Jugend-Eignung der Aufsicht, Warnung „Alter passt nicht zur Waffenart“ | später (Lean-Override)                                | Vermerk 3:0 Kern; Warnung R Kern, B später; Pflicht zur Jugend-Aufsicht R+B später | Der Haken ersetzt die schriftliche Erklärung nicht (§ 27 Abs. 3 WaffG). Eine korrekte Lösung braucht zusätzlich Altersstufen und die Prüfung der Aufsicht nach § 10 Abs. 5 AWaffV (R26). Halbe Lösungen sind Zeremonie. | Der Pilotverein hat Jugendbetrieb. Dann vollständig: Altersstufe statt Ja/Nein, Sperre unter 12 Jahren, 12 bis 13 nur erlaubnisfrei, Aufsicht mit Jugend-Eignung, als Pest-Invariante. |
| „Allein auf dem Stand“ als eigener Eintragsweg                                                                                   | später (Lean-Override)                                | 2:1 Kern (S später)                                                                | § 11 Abs. 3 AWaffV erlaubt das Schießen allein, verlangt aber keine Aufzeichnung. Ein selbst angelegter Eintrag wäre genau die Eigenangabe, die der Kern sonst ausschließt.                                             | Der Pilotverein hat den Fall regelmäßig. Bis dahin ein Admin-Nachtrag mit dem Grund „§ 11 Abs. 3 AWaffV, allein“.                                                                      |
| Gastgeber am Gasteintrag                                                                                                         | später (Lean-Override)                                | R+B Pflicht, S empfohlen                                                           | Überwachung leistet die eintragende Aufsicht (R07), eine Gästeliste ist nicht verlangt (R19).                                                                                                                           | Die Versicherung verlangt einen Gastgeber oder eine Höchstzahl an Besuchen (O9).                                                                                                       |
| Gastbeleg für Schützen fremder Vereine (PDF mit einem Termin)                                                                    | später                                                | keine Stimme                                                                       | Hilft dem Gast, erzeugt aber keinen Eintrag für unseren Verein                                                                                                                                                          | Antwort auf O6 oder Nachfrage von Gastschützen                                                                                                                                         |
| Fremder Ort am Nachtrag (`external_venue`)                                                                                       | später (Lean-Override)                                | R+B                                                                                | Nimmt O6 vorweg. Solche Termine belegt der fremde Verein.                                                                                                                                                               | Der Verband beantwortet O6 mit Ja.                                                                                                                                                     |
| Nachweisdokument der Aufsicht als PDF, Register-Export                                                                           | später (Lean-Override)                                | R+B                                                                                | Die Pflicht des Vereins (§ 10 Abs. 3 S. 3 AWaffV) lässt sich auf Papier erfüllen. Den Einblick nach S. 5 gibt die Liste im Admin-Bereich.                                                                               | O7 ist geklärt, oder eine Behörde verlangt den Export.                                                                                                                                 |
| Typ „Jugend“, Nachweisquelle, „volljährig bestätigt“ im Register                                                                 | gestrichen bzw. mit dem Jugendbetrieb (Lean-Override) | Jugend-Eignung 3:0; Nachweisquelle und „volljährig bestätigt“ R+B                  | Gespeichert, aber nie geprüft: tote Daten                                                                                                                                                                               | Jugend-Eignung mit dem Jugendbetrieb                                                                                                                                                   |
| Separates Audit-Log (`audit_entries`, gebaut in PR #8)                                                                           | gestrichen bei Q2 = C                                 | Log 3:0                                                                            | Die Tabelle, in die nur geschrieben wird, ist selbst das Protokoll. Erst ein Bearbeiten würde ein Log nötig machen.                                                                                                     | –                                                                                                                                                                                      |
| Protokoll der Exporte und jeder Admin-Einsicht                                                                                   | später (Lean-Override)                                | Exporte R+B; Einsicht nur R                                                        | Erzeugt, bestätigt und exportiert keinen Eintrag                                                                                                                                                                        | Der Datenschutzbeauftragte verlangt es.                                                                                                                                                |
| Bearbeiten von Einträgen                                                                                                         | gestrichen                                            | 3:0 für Korrektur, das Wie ist streitig                                            | Storno mit neuem Eintrag ersetzt es (K8).                                                                                                                                                                               | –                                                                                                                                                                                      |
| Idempotenzschlüssel des Clients                                                                                                  | gestrichen                                            | S+B                                                                                | Der eindeutige Index fängt Doppel-Tap und Neusenden ab (K5).                                                                                                                                                            | –                                                                                                                                                                                      |
| Stände als Namensliste                                                                                                           | gestrichen bei Q2 = C (Lean-Override)                 | 3:0 Kern                                                                           | Ohne Dienst und Buchung hat die Liste keinen Abnehmer; als Ort genügt der Verein.                                                                                                                                       | Der Bescheid legt je Stand Waffen oder eine Mindestzahl an Aufsichten fest (O3).                                                                                                       |
| Disziplinpflege durch den Admin (Tabelle, Oberfläche S8)                                                                         | gestrichen                                            | 3:0 für den Katalog, das Wie ist streitig                                          | Der Katalog ist eine Vorgabe des Verbands und kommt per Release (K14).                                                                                                                                                  | –                                                                                                                                                                                      |
| Rechnung über Monate, 3-Monats-Abschnitte und 12-Monats-Blöcke                                                                   | später                                                | 3:0 für eine neutrale Zählung                                                      | Die Abgrenzung hängt an der offenen Auslegung (R03, O5). Gezählt wird beim Verband (R04).                                                                                                                               | Der Verband legt die Auslegung fest (Q3).                                                                                                                                              |
| Bewertung oder Ampel „erfüllt / nicht erfüllt“                                                                                   | später                                                | 3:0                                                                                | Die Auslegung ist offen (R03, O5). Der Verband bescheinigt (R04). Ein Urteil zum Besitz bräuchte Besitzdaten.                                                                                                           | wie oben                                                                                                                                                                               |
| Mitglieder-CSV                                                                                                                   | gestrichen (Lean-Override)                            | R+B                                                                                | Eine zweite Route mit eigenen Rechten; Art. 20 DSGVO ist hier nur eine Einschätzung.                                                                                                                                    | –                                                                                                                                                                                      |
| Mitglieder-Login, eigene Ansicht, Auszug durch das Mitglied                                                                      | Entscheidung Q1b                                      | 3:0 vorausgesetzt                                                                  | Nachweiswert hat nur der unterschriebene Auszug (R21).                                                                                                                                                                  | –                                                                                                                                                                                      |
| QR-Check-in (Code des Mitglieds, die Aufsicht scannt)                                                                            | später                                                | 3:0                                                                                | Die Namenssuche reicht. Das Mitglied müsste sonst mit Handschuhen das Handy herausholen.                                                                                                                                | Die Suche wird zu langsam (S: ab etwa 150 aktiven Mitgliedern).                                                                                                                        |
| Kiosk zur Selbstbedienung mit Kopplung per Kurzcode                                                                              | später                                                | 3:0                                                                                | Die Aufsicht trägt ohnehin ein.                                                                                                                                                                                         | Die Aufsicht schafft die Schlange nachweislich nicht. Vorlage: die Kopplung bei CelPal.                                                                                                |
| Schusszahl und Kaliber                                                                                                           | später                                                | 3:0                                                                                | Gesetzlich nicht verlangt, nur beim VdRBw (R20)                                                                                                                                                                         | Der Verband des Pilotvereins verlangt es. Dann als Schalter für den Verein.                                                                                                            |
| Offline-Warteschlange im Browser                                                                                                 | später                                                | 2:1 (S: Kern)                                                                      | Der eindeutige Index macht das Neusenden sicher.                                                                                                                                                                        | Im Feldtest bricht das Netz ab (Q4).                                                                                                                                                   |
| Lösch- bzw. Anonymisierungslauf                                                                                                  | später, mit harter Frist                              | 2:1 (B: Kern)                                                                      | Speicherbegrenzung (Art. 5 Abs. 1 lit. e DSGVO, R25). Die Fristen beschließt der Verein (Q6).                                                                                                                           | Bevor die erste Frist abläuft (Gäste: 12 Monate nach dem ersten Echtbetrieb)                                                                                                           |
| Wettkampf-Kennzeichen                                                                                                            | später                                                | R später; S und B keine Stimme                                                     | Nur für das Bedürfnis über dem Kontingent nötig (R23)                                                                                                                                                                   | Ein Mitglied braucht es.                                                                                                                                                               |
| Merkmal „WBK-Inhaber“ und Erinnerung an die Austrittsmeldung                                                                     | später                                                | R+B                                                                                | Pflicht des Vereins (R18), gehört aber zur Mitgliederverwaltung                                                                                                                                                         | Die App verwaltet Austritte.                                                                                                                                                           |
| Mitgliedschaftsbescheinigung nach 10 Jahren, `member_since`, `first_wbk_on`                                                      | später                                                | R+B                                                                                | Bis dahin genügt eine Briefvorlage. In main schon gebaut, wird stillgelegt.                                                                                                                                             | Bedarf im Verein                                                                                                                                                                       |
| „Mitglied seit“ im Kopf des PDFs                                                                                                 | später                                                | nur R                                                                              | Keine Mehrheit; Behördenformular (R22)                                                                                                                                                                                  | Das Formular des Verbands verlangt es.                                                                                                                                                 |
| Vereinsdaten in der Oberfläche bearbeiten (`settings`-Tabelle, gebaut in PR #7)                                                  | später, bis dahin stilllegen                          | keine Stimme                                                                       | Name und Anschrift ändern sich selten; `.env` reicht.                                                                                                                                                                   | Der Verein hat niemanden mit FTP-Zugang.                                                                                                                                               |
| Mindestzahl an Aufsichten, zugelassene Waffenarten je Stand                                                                      | später                                                | R+B                                                                                | Nur, wenn der Bescheid es festlegt (R08, R15)                                                                                                                                                                           | O3                                                                                                                                                                                     |
| Wiederkehrende Dienste oder Schießtage (Serien)                                                                                  | gestrichen bei Q2 = C                                 | R+S später, B gestrichen                                                           | Ohne Dienst und Schießtag gibt es nichts zu wiederholen.                                                                                                                                                                | –                                                                                                                                                                                      |
| CSV-Import der Mitglieder mit Abgleich (gebaut in PR #10)                                                                        | später, bis dahin stilllegen                          | S+B                                                                                | Eingabe von Hand reicht für einen Pilotverein.                                                                                                                                                                          | Regelmäßiger Abgleich mit der Verwaltung des Verbands                                                                                                                                  |
| Mehrere Mitglieder auf einmal einfügen                                                                                           | später                                                | B Kern, S später, R keine Stimme                                                   | Ohne Mehrheit gilt das Lean-Kriterium.                                                                                                                                                                                  | Bei der Einrichtung des Pilotvereins                                                                                                                                                   |
| Prüfcode oder QR auf dem PDF mit öffentlicher Prüfseite                                                                          | später                                                | nur B                                                                              | Unterschrift und Stempel reichen.                                                                                                                                                                                       | Der Verband nimmt digitale Auszüge an (O2).                                                                                                                                            |
| MariaDB-Testcontainer in der CI                                                                                                  | später                                                | – (Betrieb)                                                                        | Im Betrieb läuft SQLite.                                                                                                                                                                                                | Wechsel auf MariaDB (Q8)                                                                                                                                                               |
| Passkeys, E-Mail-Verifizierung                                                                                                   | gestrichen                                            | – (Befund im Code)                                                                 | Weder a noch b noch c; die Verifizierung ist durch die Einladung faktisch tot und passt nicht zu K4.                                                                                                                    | –                                                                                                                                                                                      |
| Zwei-Faktor-Anmeldung für alle                                                                                                   | später                                                | – (Befund im Code)                                                                 | Für Admins als bewusste Entscheidung zu Art. 32 möglich                                                                                                                                                                 | Datenschutzbeauftragter                                                                                                                                                                |
| Selbstlöschen des Kontos                                                                                                         | gestrichen                                            | – (Befund im Code)                                                                 | Widerspricht „nichts wird gelöscht“                                                                                                                                                                                     | –                                                                                                                                                                                      |
| Privates Schießbuch, das das Mitglied selbst führt; private Notizen; Trainingstagebuch                                           | gestrichen                                            | 3:0 (private Notizen: R+B gestrichen, S später)                                    | Eigenangaben ohne Beweiswert, zwei Wahrheiten. Bei Rangetime darf das Mitglied Einträge ändern.                                                                                                                         | –                                                                                                                                                                                      |
| Mitglied meldet sich selbst oder reicht einen Termin bei einem fremden Verein mit Foto ein                                       | gestrichen                                            | 2:1 (R: später; B: Selbstmeldung später, Fotos gestrichen)                         | Zwei Schritte statt einem                                                                                                                                                                                               | –                                                                                                                                                                                      |
| Gastkonten, Rolle „Gast“, Limits für Gäste                                                                                       | gestrichen                                            | 3:0                                                                                | Ein Gast ist ein Eintrag, kein Benutzer.                                                                                                                                                                                | –                                                                                                                                                                                      |
| Gruppen, Freigaben je Schießtag, Wartelisten, Genehmigungen, Limits, Mannschaftsbuchung                                          | gestrichen                                            | 3:0                                                                                | Reiner Komfort beim Buchen                                                                                                                                                                                              | –                                                                                                                                                                                      |
| Buchung als Anwesenheitsnachweis, „nicht erschienen“ als Nachweis                                                                | gestrichen                                            | 3:0                                                                                | Eine Buchung beweist keine Anwesenheit (VERION, StandFrei).                                                                                                                                                             | –                                                                                                                                                                                      |
| Check-out, Uhrzeit des Gehens                                                                                                    | gestrichen                                            | 3:0                                                                                | Nicht verlangt (R02, R03); verdoppelt die Taps am Stand                                                                                                                                                                 | –                                                                                                                                                                                      |
| Bahn im Eintrag; Belegungs-, Anzeige- und TV-Bildschirm; Live-Polling                                                            | gestrichen                                            | 3:0 (Polling: S+B)                                                                 | Weder Eintrag noch Pflicht. Nur das Gerät der Aufsicht schreibt.                                                                                                                                                        | –                                                                                                                                                                                      |
| Unterschrift auf dem Bildschirm, NFC, Geofence, Biometrie, RFID                                                                  | gestrichen                                            | 3:0 (RFID: S)                                                                      | Die Rechtswirkung ist in DE nicht belegt (CelPal, High Ready). Die Bestätigung durch die Aufsicht ist billiger und stärker.                                                                                             | –                                                                                                                                                                                      |
| Waffenregister mit Seriennummer, WBK-Nummer, NWR-IDs; Dokumentenablage                                                           | gestrichen                                            | 3:0                                                                                | Nicht verlangt (Abschnitt 6.2). Datenminimierung (R25). Eine solche Liste wäre eine Liste möglicher Einbruchsziele.                                                                                                     | –                                                                                                                                                                                      |
| Feld „Rechtsgrundlage“ beim Gast                                                                                                 | gestrichen                                            | nur R                                                                              | Überwachungspflicht, keine Dokumentationspflicht (R07)                                                                                                                                                                  | –                                                                                                                                                                                      |
| Inventar, Tresor, Munitionsbestand, Ausgabe von Leihwaffen                                                                       | gestrichen                                            | 3:0 (Leihwaffenausgabe: B später)                                                  | Für § 14 reicht der Wert „Vereinswaffe“.                                                                                                                                                                                | –                                                                                                                                                                                      |
| Zahlung, Gebühren, Kasse, Abo                                                                                                    | gestrichen                                            | 3:0                                                                                | Kein Bezug zum Eintrag; PRODUCT.md schließt Stripe aus.                                                                                                                                                                 | –                                                                                                                                                                                      |
| Newsletter, Chat, Kleinanzeigen, Forum, Kurse, Arbeitsdienst, Tickets, Wartung                                                   | gestrichen                                            | 3:0                                                                                | Vereinsverwaltung, kein Standbuch                                                                                                                                                                                       | –                                                                                                                                                                                      |
| Wettkampf-Suite, Ranglisten, Statistik, Gamification                                                                             | gestrichen                                            | 3:0                                                                                | Ein anderes Produkt                                                                                                                                                                                                     | –                                                                                                                                                                                      |
| BImSchG-Limits für Munition                                                                                                      | gestrichen                                            | R+B                                                                                | Nur bei einer Lärmauflage relevant                                                                                                                                                                                      | –                                                                                                                                                                                      |
| `equipment_types` und `range_equipment`                                                                                          | gestrichen                                            | 3:0                                                                                | Gibt es nur in PLAN.md, nicht im Code; das Enum `weapon_class` reicht.                                                                                                                                                  | –                                                                                                                                                                                      |
| Schutzschalter und Erfassungsmodi zum Einstellen                                                                                 | gestrichen                                            | 3:0                                                                                | Die Regeln sind fest eingebaut. Zwei Modi verdoppeln Tests und Doku (Range Mate).                                                                                                                                       | –                                                                                                                                                                                      |
| Editor für Rollen, Zusatzrollen                                                                                                  | gestrichen                                            | B, S passt dazu                                                                    | `admin`, `member` und das Register decken die Pflichten ab.                                                                                                                                                             | –                                                                                                                                                                                      |
| Benachrichtigungen, Push, iCal: Absage-Mail (PR #6), Erinnerungen und Kalender-Abo (PR #9), Mails „Aufsicht gesucht“             | gestrichen                                            | nur B                                                                              | Ohne Buchung gibt es keinen Anlass dafür.                                                                                                                                                                               | –                                                                                                                                                                                      |
| Native Apps                                                                                                                      | gestrichen                                            | nur B                                                                              | Eine Web-App reicht (Range Mate, StandFrei).                                                                                                                                                                            | –                                                                                                                                                                                      |
| Übungsmodus in der App                                                                                                           | gestrichen                                            | S später, B gestrichen                                                             | Ohne Mehrheit gilt das Lean-Kriterium; eine Demo-Instanz mit Seed ersetzt ihn.                                                                                                                                          | –                                                                                                                                                                                      |
| Mandanten, Subdomains, Branding, Hardware-Aktoren, eigene CA                                                                     | gestrichen                                            | R+B                                                                                | PRODUCT.md schließt das aus; Ziel ist günstiges Webhosting.                                                                                                                                                             | –                                                                                                                                                                                      |
| Kiosk-Login mit E-Mail und Passwort                                                                                              | gestrichen                                            | nur S                                                                              | Kostet 45 bis 90 s pro Person (Schätzung von S) und ist ein Sicherheitsrisiko.                                                                                                                                          | –                                                                                                                                                                                      |

## 9. Entscheidungen für den Nutzer

### Q1: Wem gehört die Kladde?

- **A:** Die Kladde des Vereins ist die einzige Datenquelle. „Hybrid“ heißt höchstens: eine zweite Sicht auf dieselben Zeilen, wie bei KNSA.
- **B:** Privates Schießbuch des Mitglieds wie bei Rangetime; der Admin sieht keine fremden Einträge.
- **C:** Hybrid mit zwei Datenhaltungen: Kladde des Vereins plus privates Schießbuch mit eigenen Einträgen und Notizen.

**Empfehlung: A (3:0).** PRODUCT.md und PLAN.md beschreiben A schon seit `da6fccf`.

**Entschieden: A** (Nutzer, 02.11.2026).

**Evidenz:**

- **Recht:** Der Verband muss seine Vereine verpflichten, einen Nachweis über die Häufigkeit der Aktivitäten jedes Mitglieds zu führen ([§ 15 Abs. 1 Nr. 7 b WaffG](https://www.gesetze-im-internet.de/waffg_2002/__15.html), R01). Der Verein muss also liefern können, und das kann er nicht aus einem Buch, das nur das Mitglied sieht.
- **Bescheinigung:** Für § 14 Abs. 3 bescheinigt der Verband, für Abs. 4 seit 01.01.2026 ebenfalls (R04). Beim BSSB bestätigt der Vorstand die Richtigkeit, falsche Angaben können seine Zuverlässigkeit gefährden (R21). Beim VdRBw gleicht der Vorsitzende Schießbuch und Kladde ab (R20). Beides geht nicht ohne Einsicht. Die Angaben müssen nachvollziehbar sein (R05).
- **Anbieter:** svscore nennt Selbsteinträge „Eigenangaben“. Bei Rangetime darf das Mitglied auch automatisch erzeugte Einträge standardmäßig ändern oder löschen. Bei High Ready hängt der Export am Abo, und das Löschen des Kontos löscht die Historie. KNSA, Range Mate und CelPal führen die Kladde beim Verein (ausländisches Recht, nur Beleg der Machbarkeit).
- **Kosten (B):** C hieße zweiter Schreibweg mit eigenen Regeln zum Bearbeiten und Löschen, dazu Abgleich und zweiter Export.
- **Stärkste Argumente für B oder C:** Ein privates Buch bündelt von selbst Termine bei fremden Vereinen und den Vereinswechsel, weil das 24-Monats-Fenster zwei Vereine umfassen kann. Innerhalb von A wird das so gelöst: Den Auszug gibt es auch für deaktivierte Mitglieder; er wird beim Austritt zusammen mit der Meldung nach § 15 Abs. 5 ausgehändigt (R18). Termine bei fremden Vereinen kommen als Nachtrag mit fremdem Ort, sobald O6 geklärt ist.

### Q1b: Melden sich Mitglieder im MVP an?

- **a:** Ja. Mitglieder haben ein Konto, sehen ihre eigenen Einträge und laden ihren Auszug selbst, mit dem Vermerk „zur Information“.
- **b:** Nein. Mitglieder sind Datensätze ohne Konto (Name, Mitgliedsnummer). Den Auszug erzeugt der Vorstand (K12, K13). Auskunft und Übertragbarkeit nach Art. 15 und 20 DSGVO bedient der Admin auf Anfrage mit seinem CSV.

**Empfehlung: b.** Das ist ein Lean-Override gegen die Annahme aller drei Jurys und weicht vom Erfolgskriterium in PRODUCT.md ab („Mitglieder erzeugen ihren Nachweis selbst als PDF“). Wird b gewählt, muss das Kriterium geändert werden.

**Evidenz:**

- Nachweiswert hat nur der Auszug, den der Vorstand unterschreibt und stempelt (R21; Behördenformular R22). Ein PDF, das das Mitglied selbst lädt, läuft also ohnehin über den Vorstand.
- Konten braucht es dann nur für Admins und Aufsichten. Das spart Einladungen für Mitglieder, Mitgliederansicht (S7), Rechte und Tests.
- Mitglieder ohne Smartphone oder E-Mail-Adresse (K4) sind ohnehin Datensätze ohne Konto.
- Die Einladung für Konten gibt es schon (M1), a ist also später billig nachzurüsten.

### Q2: Gehört die Bahn- bzw. Standbuchung in den Kern?

- **A:** Ja, wie gebaut (M3 und M4 in main): Schießtage, Aufsichtsschichten, Buchung mit Kapazität, „nicht erschienen“, Regel 1 an der Buchung.
- **B:** Kein Buchungsmodul, aber ein Standdienst als eigenes Objekt, gebaut aus `supervisions`. Die Aufsicht eröffnet und schließt ihn, ein Eintrag braucht einen offenen Dienst; die Ankündigung bleibt optional.
- **C:** Kein Buchungsmodul, kein Dienst-Objekt, keine Ankündigung. Das Tor sitzt am Eintrag (K2), Termine laufen über Website oder Messenger.
- **D:** Ergänzung zu B oder C: eine schlanke Reservierung später, nur wenn der Pilotverein einen echten Engpass bei den Bahnen meldet. Sie füllt nur die Eincheckliste vor und hat keinen Nachweiswert.

**Entschieden: A** (Nutzer, 03.11.2026). Die Buchung bleibt im Kern. Es gelten die Varianten „Falls Q2 = A“ in 5.2, im Kasten zu Abschnitt 7 und in der Spalte „Ziel bei Q2 = A“ in 7.3.

**Empfehlung war: C jetzt, D als Erweiterung mit Auslöser.** Die Stimmen: Bahnbuchung nicht im Kern 3:0. Ein Standdienst 3:0 im Ob, die Reduktion auf das Tor ist die schlankste Variante im Wie. Ankündigung R+S dafür, B „später“, hier Lean-Override.

**Evidenz:**

- **Recht:** Keine Norm in den erfassten Quellen verlangt eine Reservierung. § 10 Abs. 1 AWaffV knüpft an den Schießbetrieb an (R08), nicht an eine Buchung. Regel 1 „Ohne Aufsicht keine Buchung“ zielt deshalb auf das falsche Objekt. Auch ein Aufsichtsbuch fand die Recherche bundesrechtlich nicht (R16).
- **Nachweis:** Eine Buchung belegt keine Teilnahme. StandFrei sagt das selbst. VERION zählt Buchungen per Opt-out und rät, die Behörde zu fragen. Rangetime braucht eine Anwesenheitsbestätigung, die standardmäßig aus ist; auf der echten Vereinsinstanz hsg-nbg ist sie nicht aktiv.
- **Praxis ohne Buchung:** KNSA, CelPal (dort ist die Reservierung ein eigenes Modul), svscore, Range Mate (Laufkundschaft) und StandLotse erzeugen Einträge ohne Buchung.
- **Warum kein Dienst-Objekt (C statt B):** Nach K2 trägt nur eine registrierte Aufsicht ein, und der Server prüft bei jedem Eintrag das Register. Ein Eintrag ohne Aufsicht ist damit ohnehin unmöglich. Ein offener Dienst belegt keine Anwesenheit, denn er lässt sich genauso von zu Hause eröffnen. Der Dienst brächte Folgeregeln mit sich: Übergabe, automatisches Schließen, Sichtbarkeit je Dienst, Sonderregel „allein nur bei leerem Dienst“.
- **Kosten:**
    - M2 bis M4 liegen in main. C heißt: Schießtage, Stände, Aufsichtsschichten, Buchungen, Gruppen und die Standansicht stilllegen und den Code auf einen Archiv-Branch legen (Abschnitt 7.3).
    - Dafür entfallen künftig: Kapazität, Raster, Stornofristen, „nicht erschienen“, Polling, Serien, Freigaben, Absage-Mails, Erinnerungen, Kalender-Abo, Mails „Aufsicht gesucht“, die Dienst-Zustandsmaschine und deren Tests.
    - Die Rechnung „die Hälfte des MVP entfällt“ gilt nicht mehr, weil der Code schon gebaut ist: Gespart werden Pflege und Testlast, dazu kommt Rückbau.
- **Achtung:** PRODUCT.md (Capabilities) und PLAN §1 nennen „Standbuchung“ ausdrücklich als MVP. Die Empfehlung weicht von dieser Entscheidung des Nutzers ab. Vorher den Pilotverein fragen, ob es Engpässe bei den Bahnen gibt.

### Q3: Urteil „erfüllt“ oder neutrale Darstellung?

- **A:** Der Server urteilt je Mitglied „erfüllt / nicht erfüllt“. So ist es in main gebaut (`ShootingRecord`, PDF), entsprechend PLAN Regel 7 und dem Fertig-Kriterium von M5.
- **B:** Neutrale Zählung je Kategorie und Besitz über Monate, 3-Monats-Abschnitte und 12-Monats-Blöcke, ohne Urteil.
- **C:** Der Auszug listet die Termine chronologisch, gruppiert nach Kategorie, dazu die Anzahl je Kategorie und eigen/Verein im gewählten Zeitraum und den Hinweis „keine rechtliche Bewertung“. Keine Rechnung über Monate, Abschnitte oder Blöcke.

**Empfehlung: C.** Die Jurys stimmten 3:0 für „kein Urteil“ und für eine neutrale Zählung; C ist die schlankste Variante davon.

**Evidenz:**

- **Wortlaut** von § 14 Abs. 4 S. 1 (geprüft am 02.11.2026): Nr. 1 verlangt „mindestens einmal alle drei Monate in diesem Zeitraum“, Nr. 2 „mindestens sechsmal innerhalb eines abgeschlossenen Zeitraums von jeweils zwölf Monaten“. Ein Kalenderquartal steht da nicht. Wer am 1. Januar und am 30. Juni schießt, hat zwei Quartale mit Termin, aber eine Lücke von sechs Monaten. Wo ein abgeschlossener 12-Monats-Zeitraum beginnt, hängt vom Prüftermin ab. Beides ist offen (O5). Schon B wäre also nicht neutral.
- **Code in main:** Er rechnet acht gleitende 3-Monats-Abschnitte und das beste beliebige 12-Monats-Fenster. Das ist eine von mehreren möglichen Auslegungen, und das PDF druckt daraus „erfüllt“.
- **Recht:** Der Verband bescheinigt (R04). Ein Summenvermerk genügt nicht, Einzeltermine schon (R05). C liefert genau die Einzeltermine. Ein Urteil zum Besitz bräuchte zusätzlich Angaben, welche Kategorien das Mitglied besitzt (§ 14 Abs. 4 S. 2).
- **Anbieter:** svscore, Schiessbuch.org und Düsel verzichten ausdrücklich auf eine rechtliche Bewertung.
- **Folge:** Das Fertig-Kriterium von M5 ändert sich: Der Auszug enthält alle gültigen Einträge des Zeitraums vollständig und korrekt gruppiert. Rechnung und Urteil kommen erst, wenn der Verband die Auslegung festlegt.

### Q4: Offline-Warteschlange am Stand im Kern?

- **A:** Ja. Der Browser puffert Aktionen (IndexedDB oder localStorage) und zeigt „n Aktionen ausstehend“ sowie das Alter der Daten (S).
- **B:** Nein. Vorerst sorgen der eindeutige Index und erneutes Senden für Sicherheit. Die Warteschlange kommt, wenn der Feldtest Netzabbrüche zeigt (R, B).

**Empfehlung: B (2:1, S dagegen).**

**Evidenz:**

- Für A spricht schlechtes Netz am Stand. VERION wirbt mit Offline-Eingabe, der Bedarf ist also real (S).
- Für B: Wegen des eindeutigen Index erzeugt ein Neusenden keinen zweiten Eintrag (K5). Ausfälle deckt die Papier-Ersatzliste mit Nachtrag (K9).
- Der Test von S bleibt gültig: Zwei Doppel-Taps und ein Netzabbruch ergeben genau einen Eintrag.

### Q5: Was bleibt vorerst auf Papier?

- **A:** Das Erfolgskriterium in PRODUCT.md wird ehrlich enger gefasst: „Die Anwesenheit für den Nachweis wird ohne Papier erfasst. Vorkommnisse, Minderjährige mit ihren Einwilligungen und der Aushang der Aufsicht bleiben vorerst auf Papier.“
- **B:** Papier vollständig ersetzen. Dafür braucht es ein Dienst-Objekt mit Notiz zu Vorkommnissen und digitalem Aushang (also Q2 = B) sowie Minderjährige mit Altersstufe, Einwilligungsvermerk und Prüfung der Jugend-Aufsicht.

**Empfehlung: A.**

**Evidenz:**

- **Vorkommnisse und Aushang:** Notiz bzw. Standbuch sind bundesrechtlich nicht verlangt (R16), der Aushang ist Verbandspraxis (R12).
- **Einwilligungen:** Die schriftliche Erklärung nach § 27 Abs. 3 S. 2–3 WaffG muss die Aufsicht ohnehin vorhalten, ein Haken ersetzt sie nicht.
- **Jugendtraining:** Es braucht eine qualifizierte, leitend verantwortliche Aufsicht (§ 10 Abs. 5 AWaffV, R26). Das digital richtig zu prüfen, ist mehr als ein Haken.
- **Kein Wettbewerb um die Wahrheit:** Ein paralleles Papierbuch wird oft zur maßgeblichen Aufzeichnung. Hier enthält das Papier aber nur noch Vorkommnisse, keine Anwesenheiten. Für den Nachweis nach § 14 bleibt die Kladde die einzige Quelle.
- **Ehrlichkeit:** A ändert das Erfolgskriterium, statt es stillschweigend zu verfehlen.

### Q6: Aufbewahrungsfristen

Die Fristen beschließt der Verein. Abschnitt 8 regelt nur, wann der Löschlauf gebaut wird.

- **A:** Kladde 5 Jahre ab Termin, Gastnamen 12 Monate. Vorschlag von R und B; der VdRBw bewahrt Anwesenheitslisten 5 Jahre auf (R20).
- **B:** 30 Monate, dann anonymisieren. So macht es StandLotse; das ist eine Ableitung des Herstellers, kein Gesetz.
- **C:** 10 Jahre bzw. bis zum Ende des 10-Jahres-Fensters bei WBK-Inhabern (Empfehlung des BDS laut Rechtsrecherche).

**Empfehlung: A als Vorschlag für den Vereinsbeschluss.** Die Fristen gehören vom ersten Tag an ins Verzeichnis nach Art. 30 DSGVO. Der Löschlauf muss stehen, bevor die erste Frist abläuft; bei Gästen sind das 12 Monate.

**Evidenz:**

- Kein Gesetz nennt eine Frist für Schießnachweise.
- Die Bedürfnisprüfung erfolgt alle 5 Jahre (§ 4 Abs. 4 WaffG) und blickt 24 Monate zurück (R03). Eine Frist von genau 24 Monaten ließe keinen Puffer für den Prüftermin und die Bearbeitung.
- Alles ist Einschätzung; der Datenschutzbeauftragte sollte bestätigen (O10).

### Q7: Was passiert mit dem gebauten Code (M1–M5 und PRs #6–#10 in main)?

- **A:** Rückbau auf den Kern (bei Q2 = C):
    - Neu bauen: `log_entries` und `qualifications`, Eintragen am Stand, Kladde und Auszug.
    - Stilllegen, was Abschnitt 7.3 nennt, und den Buchungscode auf einen Archiv-Branch legen.
    - Drop-Migrationen erst nach einer Sicherung.
- **B:** Buchung behalten (bei Q2 = A):
    - Die Kladde trotzdem als eigene, insert-only Tabelle `log_entries` neu bauen.
    - Die Standansicht so umbauen, dass das Einchecken einen Eintrag anlegt; Zurücksetzen nur als Storno mit Grund; kein Polling.
- **C:** Weitermachen wie bisher: Die Kladde bleibt der Buchungsstatus `checked_in`.

**Entschieden: B** (folgt aus Q2 = A). Auf main schon umgesetzt in #24 bis #29. Offen bleiben nur die Komfortfunktionen rund um die Buchung, siehe docs/ENTSCHEIDUNGEN.md.

**Empfehlung war:** Keine weitere Arbeit am Buchungs- und Kladde-Code und an den Zusatzfunktionen rund um die Buchung (Erinnerungen, „Aufsicht gesucht“, Buchungswerte in den Vereinseinstellungen), bis Q1b, Q2 und Q3 entschieden sind. Danach A bei Q2 = C bzw. B bei Q2 = A. **C in keinem Fall.**

**Evidenz (Befunde im Code, geprüft an origin/main `f07b679`):**

- **Mutable Kladde:** `ShootingRecord` liest die Kladde aus `bookings` mit Status `checked_in`. `Bookings::reset` setzt einen Check-in auf „gebucht“ zurück und leert `checked_in_at` und `checked_in_by_id`. Seit PR #8 steht das im Protokoll `audit_entries`, die Kladde selbst ändert sich aber trotzdem; Protokoll und Kladde sind damit zwei Quellen. Admins dürfen das an jedem Tag (`canRunTheRange`).
- **Löschwege:** `bookings` hängt per `cascadeOnDelete` an `shooting_days` und `ranges`. `bookings.user_id` ist `nullOnDelete`, und jeder Benutzer kann sein Konto selbst löschen (`profile.destroy`). Damit verliert ein Eintrag seine Person.
- **Fehlende Regeln und Felder:** Das Einchecken verhindert die Selbstbestätigung nicht. Ein Feld für die Disziplin gibt es nicht.
- **Urteil und PDF-Satz:** Das PDF urteilt „erfüllt“ (Q3 = A) und behauptet eine Bestätigung am Schießtag, die bei Admin-Check-ins nicht stimmt.
- **Betrieb:** Ob der Stand schon produktiv läuft, ist unbekannt. `cron.php` spielt jede neue Migration binnen einer Minute ein.

### Q8: Datenbank und Sicherung im Echtbetrieb

- **A:** SQLite behalten, wie es läuft. Dazu tägliche Sicherung per `VACUUM INTO` mit Rotation und regelmäßigem Download, ein Marker in `cron.php` gegen stilles Neuanlegen und ein Restore-Test.
- **B:** Vor dem Echtbetrieb auf MariaDB wechseln (PLAN §2) und das Backup des Hosters nutzen. Dann kommt ein MariaDB-Testcontainer in `tests.yml` dazu.

**Empfehlung: A.**

**Evidenz:**

- docs/DEPLOY.md: Im Betrieb läuft `database/database.sqlite`, Backups stehen unter „Offen“.
- `cron.php` legt eine fehlende Datei still neu an und seedet einen neuen Admin.
- PLAN §2 nennt MariaDB „im Betrieb“, das widerspricht DEPLOY.md.
- Für einen Verein mit einem schreibenden Gerät pro Aufsicht reicht SQLite.
- Die generierte Spalte für den Doppelschutz (7.2) geht unter beiden Datenbanken.
- Mit A braucht es keinen zweiten Datenbanktest in der CI.

## 10. Vorgeschlagene Änderungen an docs/PLAN.md

Die Änderungen sind **nicht angewendet**. Sie beziehen sich auf docs/PLAN.md in lokal main `b7a6758` (mit dem Abschnitt „Stand (2026-11-03)“, der noch nicht gepusht ist; ohne diesen Abschnitt gleicht die Datei origin/main `f07b679`) und setzen Q1 = A, Q1b = b, Q2 = C, Q3 = C, Q5 = A und Q8 = A voraus. Wo eine Änderung an einer Entscheidung hängt, steht die Entscheidung in eckigen Klammern.

**§1 Ziel und Umfang**

- MVP neu: „Digitale Schießkladde des Vereins: Eintrag durch eine registrierte Aufsicht, Auszug je Mitglied, minimale Benutzerverwaltung mit Aufsichtsregister“ [Q2].
- „Standbuchung mit Standaufsicht“ nach „Später“ verschieben, Auslöser: gemeldeter Engpass bei den Bahnen [Q2].
- „Minimale Benutzerverwaltung (Rollen, Gruppen, Einladung per Mail)“ wird zu „Rollen admin und member, Einladungslink zum Kopieren für Admins und Aufsichten“ [Q1b]. Gruppen streichen.
- „Nicht vorgesehen“ ergänzen um: Arbeitsdienste, Inventar und Tresor, Gebühren, Newsletter, Kleinanzeigen, BImSchG-Limits (3:0).

**§2 Stack**

- Datenbank: „SQLite im Betrieb, Sicherung täglich per `VACUUM INTO`“ [Q8]. Die Angabe „MariaDB im Betrieb“ streichen.
- „Mails … falls das Hosting Cron anbietet“ durch den Ist-Stand ersetzen: `cron.php` läuft laut DEPLOY.md jede Minute und ruft `migrate --force` und `schedule:run` auf. Darauf läuft später der Löschlauf.
- Die Zeile „Live-Ansicht am Stand fragt alle 10–15 s neu ab (Inertia-Polling)“ streichen. Nur das Gerät der Aufsicht schreibt (S+B).
- Ergänzen: Eintragen ist über einen eindeutigen Index gegen Doppelungen gesichert, Neusenden ist sicher; eine Offline-Warteschlange kommt später [Q4].
- Ergänzen: Deploy nur nach grüner CI (`deploy-hetzner.sh` prüft HEAD gegen `origin/main` und den CI-Lauf).

**§3 Datenmodell**

- Ersetzen durch Abschnitt 7.2 dieses Dokuments: `log_entries`, `qualifications` (nur anhängen), `users` mit optionaler E-Mail und optionalem Passwort, Konfiguration statt `disciplines`.
- Streichen bzw. stilllegen nach Abschnitt 7.3: `groups`, `group_user`, `shooting_days` samt Gruppen und Ständen, `supervisions`, `bookings`, `ranges`, `users.role=guest`, `users.supervisor_until` (nach Datenmigration), `users.first_wbk_on`, `audit_log`. `equipment_types` und `range_equipment` standen nur im PLAN.
- `log_entries`:
    - `discipline` wird Pflicht bei Mitgliedern.
    - `confirmed_by_id`, `checked_in_at`, `range_id` und `booking_id` entfallen.
    - Dazu kommen `recorded_by_id`, `late_reason`, die Storno-Felder und `active_key`.
- Neuer Grundsatz: Alle Fremdschlüssel auf die Kladde und das Register sind `restrictOnDelete`.

**§4 Regeln**

- Regel 1 neu: „Ohne registrierte Aufsicht kein Eintrag. Wer einträgt, hat am Datum eine gültige Registerzeile, trägt nicht sich selbst ein, trägt nur für heute ein, und für Kurz- und Langwaffe hat er den Umfang `licensed`. Die Sperre ist nicht abschaltbar.“ Quelle: [§ 10 Abs. 1 und 3 AWaffV](https://www.gesetze-im-internet.de/awaffv/__10.html). Umgesetzt als Policy mit Test.
- Regel 2 neu: „Das Aufsichtsregister wird nur fortgeschrieben, jede Prüfung und jeder Entzug ist eine Zeile, es gilt die jüngste. Beim Anlegen bestätigt der Admin die Volljährigkeit.“
- Regel 3 (Kapazität) streichen [Q2].
- Regel 4 neu: „Die Aufsicht sieht und storniert nur ihre eigenen Einträge von heute.“ „Buchungen verwalten“ streichen.
- Regel 5 ersetzen:
    - In die Kladde wird nur geschrieben; korrigiert wird per Storno mit Grund und neuem Eintrag.
    - Nachträge legt nur der Admin an, mit Pflichtgrund.
    - Keine Selbstbestätigung, ohne Ausnahme.
    - Eindeutigkeit für gültige Einträge über `active_key`.
    - Die Admin-Rolle bekommen nur Vorstand und Schießwart.
    - Minderjährige werden im MVP nicht digital eingetragen [Q5].
- Regel 6 (Audit-Log) ersetzen: „Die Kladde ist ihr eigenes Protokoll. Auf `log_entries` sind nur die Storno-Felder änderbar.“ Ein Protokoll der Exporte kommt später.
- Regel 7 ersetzen [Q3]: Den Wortlaut von § 14 Abs. 3 Nr. 2 und Abs. 4 S. 1 zitieren, nicht umschreiben. Weder „in einem“ noch „in jedem“ abgeschlossenen 12-Monats-Zeitraum schreiben und „alle drei Monate“ nicht mit „Quartal“ gleichsetzen. Die Auslegung als offen markieren (O5). Der Auszug listet die Termine und die Anzahl je Kategorie und Besitz, ohne Rechnung und ohne Urteil. Abs. 4 S. 3 (10-Jahres-Fall) kommt später.
- Neue Regel 8: Nichts wird gelöscht. Kein Selbstlöschen von Konten; Benutzer mit Einträgen werden deaktiviert.
- Neue Regel 9: Die Aufbewahrungsfristen beschließt der Verein, der Löschlauf steht vor Ablauf der ersten Frist [Q6].

**§5 Oberflächen**

- S2 bis S5, S8 und S9 streichen [Q2]. S7 (eigene Einträge) entfällt bei Q1b = b.
- S6 wird „Eintragen“:
    - zuerst die Suche nach Name oder Mitgliedsnummer, mit Vorbelegung
    - ein Tap „Eintragen“, dann die grüne Karte
    - „Gast hinzufügen“ mit Name, Kategorie und Haken „volljährig“
    - Storno eigener Einträge von heute mit Schnellgrund
    - kein Polling, kein „nicht erschienen“, kein Eröffnen oder Schließen
- S7a wird „Kladde“: Filter nach Person und Zeitraum, CSV, PDF je Mitglied auch für deaktivierte, Nachtrag mit mehreren Personen.
- S10: Personen anlegen (E-Mail optional), Einladungslink für Konten, Aufsichtsregister. Keine Gruppen.
- Zustände streichen: „Schießtag ohne Aufsicht: Buchen gesperrt“, „Stand voll“, „Buchung in der Vergangenheit“, „Schwelle nicht erreicht“.
- Zustände neu:
    - „Keine gültige Registerzeile: Eintragen gesperrt, mit Grund“
    - „Heute schon eingetragen“ (als Erfolg)
    - „Für Kurz- und Langwaffe fehlt der Umfang erlaubnispflichtig“
    - „Minderjährige weiter auf Papier“
    - „Zeitraum ohne Einträge“
- Neue Vorgabe für die Oberfläche am Stand: Bedienelemente mindestens 64 px, keine Wischgesten, keine Unterschrift, der Schütze tippt nichts, Rückmeldung sichtbar statt hörbar.

**§6 Meilensteine**

- **Ist-Stand vermerken:** M0 bis M5 und PRs #6 bis #10 sind gebaut und in main. M4 und M5 bauen auf der Buchung auf [Q7].
- **Abschnitt „Stand (2026-11-03)“** (lokal in main, `2e102c9`): Die Abweichungen „Die Kladde ist eine Sicht auf bestätigte Buchungen“ und „Qualifikation als Ablaufdatum `supervisor_until`“ als zu korrigieren markieren (Kladde als eigene Tabelle, Register mit Zeilen, die man nicht überschreibt) [Q7]. „Bahnen werden gezählt“ entfällt bei Q2 = C. Die Zusatzfunktionen aus #6 bis #10 nach Abschnitt 8 dieses Dokuments einordnen.
- **Neu M6 „Kladde“:**
    - Inhalt: `log_entries`, `qualifications` mit Übernahme aus `supervisor_until`, „Eintragen“ (S6 neu), Storno, Nachtrag, Pest-Invarianten.
    - Fertig, wenn alle Invarianten grün sind, ein Mitglied in höchstens 10 s und ein Gast in höchstens 15 s eingetragen ist und zwei Doppel-Taps und ein Netzabbruch genau einen Eintrag ergeben (Playwright mit gedrosseltem Netz).
- **Neu M7 „Auszug“:**
    - Inhalt: S7a, CSV, PDF nach K13.
    - Fertig, wenn der Auszug alle gültigen Einträge des Zeitraums vollständig und korrekt gruppiert enthält [Q3].
- **Neu M8 „Stilllegen“:**
    - Inhalt: Oberflächen und Routen für Buchung, Schießtage, Stände, Gruppen, Standansicht, Passkeys, E-Mail-Verifizierung und Selbstlöschen entfernen; Buchungscode auf einen Archiv-Branch.
    - Drop-Migrationen erst nach einer Sicherung [Q2, Q7].
- **Feldtest** (bisher M6): dazu ein Restore-Test der Sicherung.
- **Betrieb vor dem Echtbetrieb:** tägliche Sicherung, Marker in `cron.php`, Deploy nur nach grüner CI [Q8].
- **„Später“ neu sortieren,** jeweils mit Auslöser:
    - Bahnbuchung bzw. Vorab-Anmeldung
    - Ankündigung von Diensten
    - Notiz zu Vorkommnissen
    - Minderjährige
    - „allein auf dem Stand“
    - Gastgeber
    - fremder Ort
    - QR-Check-in
    - Kiosk
    - Schusszahl und Kaliber
    - Rechnung und Bewertung nach § 14
    - Löschlauf
    - Offline-Warteschlange
    - Merkmal „WBK-Inhaber“
    - Mitgliedschaftsbescheinigung
    - Wettkampf-Kennzeichen
    - CSV-Import
    - Protokoll der Exporte
    - Nachweisdokument der Aufsicht
- Arbeitsdienste, Inventar, Gebühren, BImSchG, Newsletter und Kleinanzeigen aus „Später“ entfernen und unter „Nicht vorgesehen“ führen.

**§7 Offene Entscheidungen**

- „Zeitfenster“ und „Bahnen“ streichen. Ohne Buchung sind beide hinfällig [Q2].
- „Gäste“ als entschieden eintragen: Name und Kategorie, kein Konto, Minderjährige auf Papier. Adresse und Gastgeber nur, wenn der Versicherungsvertrag sie verlangt (O9).
- Neue offene Punkte:
    - Verband, Formular und Disziplinkatalog des Pilotvereins (O1)
    - Nimmt der Verband einen digitalen Auszug an (O2)?
    - Was regelt der Erlaubnisbescheid (O3)?
    - Auslegung von § 14 Abs. 4 S. 1 (O5)
    - Termine bei fremden Vereinen (O6)
    - Aufbewahrungsfristen [Q6]
    - Übergang von Papier zu digital (O14)
    - erlaubnisfreie Termine in der Aufzeichnung (O15)
    - Gibt es einen Engpass bei den Bahnen [Q2]?

**Dazu passende Änderungen an PRODUCT.md** (ebenfalls nicht angewendet)

- Users: „Gastschütze“ ist keine Rolle mehr; Gäste haben kein Konto, die Aufsicht trägt sie ein. „Standaufsicht“ ist ein Eintrag im Register, keine Rolle.
- Product Purpose: „Schießtage planen, Bahnen buchen, Standaufsicht sicherstellen und“ streichen; es bleibt „am Stand eine digitale Schießkladde führen, die nur registrierte Aufsichten füllen“ [Q2].
- Erfolgskriterium nach Q5 = A und Q1b = b neu fassen: „Die Anwesenheit für den Nachweis wird ohne Papier erfasst; der Vorstand erzeugt je Mitglied den Auszug als PDF.“
- Operating Context:
    - Den Punkt zu Schießtagen (Termine, auch wiederkehrend; Bahnkapazität; Freigabe für Gruppen und Gäste) ersetzen durch „Eingetragen wird nur von einer Aufsicht mit gültiger Registerzeile“ [Q2].
    - „Vorstand und Schießwart sehen alle“ präzisieren zu „eine Admin-Rolle, die nur Vorstand und Schießwart erhalten“.
    - „Mitglieder sehen nur ihre eigenen Einträge und exportieren daraus ihren Auszug“ nach Q1b anpassen.
- Capabilities:
    - MVP neu: „Digitale Schießkladde mit Eintrag durch die Aufsicht, Auszug je Mitglied, minimale Benutzerverwaltung mit Aufsichtsregister“.
    - „Später“ neu: Bahnbuchung und Kiosk, jeweils mit Auslöser.
    - „Ausdrücklich nicht geplant“ ergänzen um: Arbeitsdienste, Inventar und Tresor, Gebühren, Newsletter, Kleinanzeigen.
- Prinzip 2: „ohne Aufsicht keine Buchung“ wird zu „ohne registrierte Aufsicht kein Eintrag“.

**Dazu passend in docs/DEPLOY.md und den Skripten** (nicht angewendet)

- „Offen: Backups“ durch die tägliche Sicherung mit Rotation und Restore-Test ersetzen [Q8].
- `cron.php`: Marker-Datei; eine fehlende Datenbank nur beim allerersten Start anlegen.
- `scripts/deploy-hetzner.sh`: Abbruch, wenn HEAD nicht `origin/main` ist oder die CI für den Commit nicht grün war.

## 11. Quellen

Mehrere Anker auf derselben Seite (`#…`) sind zu einer URL zusammengefasst. Je Host steht die Basis-URL vorn, danach die Pfade; Basis plus Pfad ergibt die vollständige URL.

**Rangetime**

- https://rangetime.de: `/`, `/order/index`, `/demo/index`, `/help/index`, `/help/shooting_diary`, `/help/supervisors`, `/help/shooting_ranges`, `/help/inventories`, `/help/members`, `/privacy/index`, `/terms/index`, `/about/index`, `/partner/index`
- https://demo.rangetime.de: `/users/sign_in`, `/interactive`, `/javascripts/rangetime/api.js`, `/javascripts/locales/de.js`, `/javascripts/rangetime/terminal/checkin.js`, `/javascripts/rangetime/interactive/booking.js`
- https://hsg-nbg.rangetime.de: `/users/sign_in`, `/interactive`

**Düsel / Kladdify**

- https://duesel-it.de: `/schiesskladde`, `/`, `/tools`
- https://forum.waffen-online.de/topic/429705-elektronische-schiesskladde-schiessbuch/

**svscore**

- https://svscore.de: `/`, `/anleitung`, `/preise`, `/einzel`, `/changelog`, `/entwickler`, `/api/v1/openapi.json`, `/mobil/kiosk`, `/sv-waldenbuch`, `/admin/`, `/chunk-BEKSWT06.js`, `/datenschutz`, `/avv`
- https://meisterschuetzen.org/forum/thread/13246-svscore-de-wettkampfverwaltung-fuer-schuetzenvereine/
- https://apps.apple.com/de/app/svscore-de/id6823931723
- https://play.google.com/store/apps/details?id=de.svscore.app&hl=de

**VERION**

- https://www.verion-app.de: `/`, `/loesungen/schuetzenvereine`, `/loesungen/buchungssystem`, `/loesungen/arbeitsstunden`, `/faq`, `/datenschutz`, `/agb`, `/blog/schiessstand-digital-belegen-papierliste-2026`, `/blog/standbuchungen-schuetzenverein-2025`, `/appscreenshots/desktop/Buchen_dunkel.jpg`, `/appscreenshots/desktop/Statistiken_dunkel.jpg`, `/appscreenshots/Buchungen.jpg`
- https://dashboard.verion-app.de/login
- https://apps.apple.com/de/app/verion-vereinsmanagement/id6759559102
- https://play.google.com/store/apps/details?id=com.verionapp.com&hl=de
- https://omr.com/de/reviews/product/verion

**StandFrei**

- https://standfrei.de: `/`, `/funktionen/`, `/standbelegung/`, `/fuer-wen/`, `/fuer-wen/mitglieder/`, `/fuer-wen/sportleiter/`, `/fuer-wen/trainer/`, `/fuer-wen/vorstaende/`, `/preise/`, `/vereinssoftware/`, `/vergleich/`, `/mannschaftstraining-planen/`, `/schiessstandverwaltung-einfuehren/`, `/schiessstand-belegungsplan-excel/`, `/datenschutz/`
- https://portal.standfrei.de: `/`, `/login`, `/avv`, `/datenschutz`, `/agb`, `/manifest.json`, `/assets/index-CbNYLFBH.js`, `/assets/BookingPage-BgBDqHg-.js`, `/assets/HallSchedule-DWz99MOq.js`, `/assets/BookingReports-2LbntQv1.js`, `/assets/MyBookings-CE_BCA_-.js`, `/assets/calendarHelpers-BiUkq189.js`, `/assets/RoleForm-D1R6cQwn.js`, `/assets/parseInviteCsv-CYkVNKuf.js`, `/assets/BlockedPeriodForm-u3OGuMi_.js`, `/assets/BookingSettings-BeNfPYpA.js`

**StandLotse**

- https://mlang-de.github.io: `/schiesskladde-page/`, `/schiesskladde-page/tech-index.html`
- https://github.com: `/mlang-de/schiesskladde-page`, `/mlang-de/schiesskladde-page/commits/main`, `/mlang-de?tab=repositories`
- https://raw.githubusercontent.com/mlang-de/schiesskladde-page/main/index.html

**Schiessbuch.org**

- https://www.schiessbuch.org: `/`, `/verifizierte-trainingsnachweise`, `/schuetzen`, `/schiessstaende`, `/fuer-vereine-und-verbaende`, `/preise`, `/roadmap`, `/hilfe/schuetzen`, `/hilfe/schiessstaende`, `/hilfe/vereine`, `/hilfe/verbaende`, `/datenschutz`, `/nutzungsbedingungen`, `/sitemap.xml`
- https://portal.schiessbuch.org/login
- https://play.google.com/store/apps/details?id=org.schiessbuch.app
- https://apps.apple.com/de/app/schiessbuch-org/id6783682582
- https://www.youtube.com/oembed?url=https://www.youtube.com/shorts/ljCc3m6qGWM
- https://chrome-stats.com/d/org.schiessbuch.app

**High Ready**

- https://highreadyapp.com: `/`, `/das-digitale-schiessbuch-ein-intelligentes-tool/`, `/unverbindliches-angebot/`, `/high-ready-bei-2min2mio-unsere-mission-im-rampenlicht/`, `/wp-content/uploads/2024/03/Website-Schiessbuch-Eintrag-768x1555.png`, `/wp-content/uploads/2024/03/Website-Schiessbuch-768x1555.png`, `/wp-content/uploads/2024/03/Website-Waffenschrank-Details-768x1555.png`, `/wp-content/uploads/2024/01/Buddy-Foto-scaled.jpg`, `/wp-content/uploads/elementor/thumbs/03_Identitaet-bestaetigen-scaled-qifwn03woe8vg19kx1z0hr9zsl7v6j6rm6p49ri3x4.jpg`, `/wp-content/uploads/2024/10/Dashboard-2024-768x1555.png`, `/wp-content/uploads/2025/03/Lizenzvertrag_2025.pdf`, `/wp-content/uploads/2025/03/High_Ready_Datenschutzerklaerung_2025.pdf`
- https://partner.highreadyapp.com/
- https://apps.apple.com/at/app/high-ready/id6463715522
- https://play.google.com/store/apps/details?id=at.apptec.high_ready&hl=de
- https://www.trendingtopics.eu/high-ready-app-soll-schiesstraining-unterstuetzen/
- https://militaeraktuell.at/high-ready-app-interview-mit-gruender-christian-h/

**KNSA Digitaal Schietregister (NL)**

- https://www.knsa.nl: `/`, `/media/3238/handleiding-digitaal-schietregister.pdf`, `/media/1972/handleiding-mijn-knsa-voor-knsa-verenigingen.pdf`, `/downloadcenter/wwm-modellen/`, `/media/2668/model_schietregister.pdf`, `/media/1192/presentieregister.doc`, `/media/1190/register_introduces.doc`, `/media/1195/uitleenregister-verenigingswapens.doc`, `/media/1080/wapenuitgiftestaat_sv.pdf`, `/media/1074/munitiestaat.pdf`, `/media/1200/richtlijnen-voor-de-uitgifte-van-verenigingswapens-en-munitie.pdf`, `/aanvraagformulier-deelname-digitaal-schietregister/`, `/Privacyverklaring`, `/media/2999/eindrapport-commissie-wet-wapens-en-munitie-ringen-rond-de-roos.pdf`
- https://www.schietbeurten.nl/
- https://svblijfvoort-site.e-captain.nl/bestanden/handleiding-digitaal-schietregister.pdf
- https://apps.apple.com/nl/app/schietregister/id1547983683
- https://play.google.com/store/apps/details?id=nl.renflex.knsaschietbeurtenapp
- https://zoek.officielebekendmakingen.nl/stcrt-2014-18098.html
- https://wetten.overheid.nl/BWBR0008804/
- https://www.youtube.com/watch?v=lfC9WROmYvw

**Range Mate (UK)**

- https://range-mate.co.uk: `/`, `/clubs/`, `/features/`, `/pricing/`, `/faqs/`, `/compare/`, `/case-studies/phoenix-rifle-club/`, `/trust-centre/privacy/`, `/trust-centre/security/`, `/trust-centre/data-subprocessors/`
- https://prc.range-mate.co.uk/
- https://docs.range-mate.co.uk: `/`, `/llms.txt`, `/for-members/your-shooting-records`, `/for-members/booking-onto-events`, `/for-members/sharing-guns`, `/for-members/gun-documents`, `/for-members/installing-the-app`, `/for-members/accounts-passwords-and-logins`, `/for-club-admins/shooting-records`, `/for-club-admins/strict-record-keeping-mode`, `/for-club-admins/attendance-and-range-logs`, `/for-club-admins/club-calibres`, `/for-club-admins/membership-lifecycle`, `/for-club-admins/membership-workflows`, `/for-club-admins/managing-events`, `/for-club-admins/invitations`, `/for-club-admins/renewal-check`, `/for-club-admins/reach-compliance`, `/changelog/15th-feb-24`, `/changelog/12th-jun-26`, `/changelog/3rd-aug-26`, `/changelog/26th-aug-26`
- https://www.gov.uk/government/publications/approval-of-rifle-and-muzzle-loading-pistol-clubs/approval-of-rifle-and-muzzle-loading-pistol-clubs-accessible

**CelPal (PL)**

- https://celpal.net: `/`, `/produkt`, `/pytania`, `/bezpieczenstwo`, `/ui`, `/static/js/main.f53d8ec7.js`
- https://book.celpal.net: `/login`, `/locales/pl-PL/common.json`
- https://online.celpal.net: `/login`, `/locales/pl-PL/common.json`
- https://admin.celpal.net/login
- https://portal.celpal.net/
- https://forum.celpal.net/
- https://apps.apple.com/pl/app/celpal/id1562006193
- https://play.google.com/store/apps/details?id=com.celpal.book
- https://mwm.ai/apps/celpal/1562006193
- https://eli.gov.pl/api/acts/DU/2000/234/text/T/D20000234L.pdf
- https://api.sejm.gov.pl/eli/acts/DU/2002/238/text.pdf
- https://www.youtube.com/watch?v=soPfLM6k61M

**Joulix**

- https://joulix.app: `/`, `/ablauf`, `/einsatz/jugend`, `/einsatz/training`, `/einsatz/vereinsabend`, `/einsatz/veranstaltungen`, `/hilfe`, `/hilfe/rollen-und-rechte`, `/hilfe/kinder-und-jugendliche`, `/hilfe/standanzeige-und-mitspielen`, `/hilfe/partie-steuern`, `/hilfe/partie-starten`, `/hilfe/schuesse-zuordnen`, `/hilfe/training-starten`, `/hilfe/trainingsgruppen`, `/hilfe/live-uebersicht`, `/hilfe/sender-einrichten`, `/hilfe/verein-einrichten`, `/hilfe/personen-einladen`, `/hilfe/ranglisten-und-rekorde`, `/hilfe/keine-schuesse`, `/hilfe/sender-meldungen`, `/hilfe/abo-und-testphase`, `/datenschutzerklaerung`, `/avv`
- https://www.google.com/search?q=Joulix+Sch%C3%BCtzenverein+DISAG

**PureDynamic (CH)**

- https://puredynamic.ch: `/`, `/sportschiessverein/`, `/kiosk-tv/`, `/standreservation/`, `/volunteer/`, `/target-sprint/`
- https://app.puredynamic.cloud: `/help`, `/security`, `/register`, `/club/eroeffnen`, `/public/startlists`, `/shooting/login`, `/shooting-days/overview`, `/shooting-days/shot-report`, `/shooters`, `/mobile/result-upload`, `/fernwettkampf`, `/_next/static/chunks/0pqegm4j.imp0.js`, `/datenschutz`, `/nutzungsbedingungen`, `/agb`, `/startseite`, `/news/stand-im-blick`

**Recht (DE)**

- https://www.gesetze-im-internet.de/waffg_2002/__9.html (R16)
- https://www.gesetze-im-internet.de/waffg_2002/__14.html (R02, R03, R23; Wortlaut am 02.11.2026 geprüft)
- https://www.gesetze-im-internet.de/waffg_2002/__15.html (R01, R18; Wortlaut am 02.11.2026 geprüft)
- https://www.gesetze-im-internet.de/waffg_2002/__27.html (R14, R15)
- https://www.gesetze-im-internet.de/waffg_2002/__27a.html (R17)
- https://www.gesetze-im-internet.de/waffg_2002/__58.html (R04)
- https://www.gesetze-im-internet.de/awaffv/__9.html (R07)
- https://www.gesetze-im-internet.de/awaffv/__10.html (R08, R09, R10, R26; Wortlaut am 02.11.2026 geprüft)
- https://www.gesetze-im-internet.de/awaffv/__11.html (R11)
- https://www.gesetze-im-internet.de/awaffv/__24.html (R19)
- https://www.verwaltungsvorschriften-im-internet.de/bsvwvbund_05032012_BMJKM5.htm (R05, R06)
- https://www.dsb.de/fileadmin/DSB.DE/PDF/PDF_2024/Schiessstandordnung_fuer_alle_Schusswaffen_05-2024.pdf (R12)
- https://www.dsb.de/fileadmin/DSB.DE/PDF/PDF_2025/DSB_Richtlinine_Standaufsicht_2025.pdf (R13)
- https://rk-karlsruhe.de/wp-content/uploads/2024/06/240102-Schiesssportordnung.pdf (R20)
- https://www.bssb.de/fileadmin/Service/Waffenrecht/Regelung_fuer_Beduerfnisbescheinigungen/Ausfuellhilfe_WBK_Gruen.pdf (R21)
- https://herford.polizei.nrw/sites/default/files/2022-04/18_Bed%C3%BCrfnisbescheinigung%20Sportsch%C3%BCtzen%20%28%C2%A7%2014%20Abs.%204%20WaffG%29.pdf (R22)
- https://schuetzen.erzbistum-koeln.de/export/sites/schuetzen/bhds-old-Service/service_infos/schiessstand_nutzungsordnung.pdf (R24)
- https://eur-lex.europa.eu/eli/reg/2016/679/oj (R25)

**Recht (andere Länder, nur zum Vergleich)**

- NL: https://zoek.officielebekendmakingen.nl/stcrt-2020-5669.html
- CH: https://police.be.ch/content/dam/police/dokumente/police-be-ch/d/waffen/formular-schiessnachweis-d.pdf
- UK: https://gov.uk/government/publications/approval-of-rifle-and-muzzle-loading-pistol-clubs

**Befunde im Repo (keine URLs, Stand origin/main `f07b679`, lokal main `b7a6758`)**

- Git-Historie: `94420bc` (privates Schießbuch in PRODUCT.md und PLAN.md), `da6fccf` (Vereinskladde in PRODUCT.md und PLAN.md, `cron.php`), PRs #1 bis #10, `2e102c9` (PLAN.md, Abschnitt „Stand“), Branch `fix/kleinigkeiten` (`0491597`, `42065b3`)
- `database/migrations/2026_11_03_000000_add_membership_fields_and_groups.php`, `…000100_create_ranges_and_shooting_days.php`, `…000200_create_supervisions_and_bookings.php`, `…000300_add_member_since_to_users.php`, `0001_01_01_000000_create_users_table.php`
- `app/Domain/ShootingRecord.php`, `app/Domain/Bookings.php` (`checkIn`, `reset`, `canRunTheRange`), `app/Enums/WeaponType.php`, `app/Enums/BookingStatus.php`, `app/Models/User.php`, `resources/views/pdf/record.blade.php`, `resources/js/pages/range-office/Show.svelte` (`usePoll(15000)`)
- `routes/web.php`, `routes/settings.php` (`profile.destroy`), `config/verein.php`, `app/Support/ClubSettings.php`, `database/migrations/2026_11_03_000400_create_settings_table.php`, `…000500_create_audit_entries_table.php`, `app/Console/Commands/RequestSupervision.php` (Branch `fix/kleinigkeiten`)
- `cron.php`, `docs/DEPLOY.md`, `.github/workflows/tests.yml`, `scripts/deploy-hetzner.sh`
