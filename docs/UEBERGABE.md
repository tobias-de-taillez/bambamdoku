# Übergabe (Stand 03.11.2026)

## Zuerst tun

1. **Aufsicht registrieren.** In Produktion steht noch niemand im Aufsichtsregister. Deshalb ist „Eintragen“ am Stand für alle gesperrt. Das ist kein Fehler, sondern das Eintrags-Tor (§ 10 Abs. 3 AWaffV). Sich selbst kann niemand registrieren. Tobias trägt also Magnus ein (Mitglieder → Magnus → „Prüfung eintragen“) oder Magnus Tobias.
2. **Passwort von Magnus ändern.** „123“ war ausdrücklich so gewünscht, ist auf einer öffentlich erreichbaren Seite aber unsicher. Der Login ist auf 5 Versuche je Minute begrenzt, das hält Raten nur auf.
3. **Vereinsdaten pflegen.** Unter Einstellungen: Name, Anschrift, Verband, Vereinsnummer, „Kladde digital seit“. Diese Angaben stehen im Kopf des Auszugs.

## Betrieb

- **Mail** ist nicht eingerichtet (`MAIL_MAILER=log`). Einladungen gibt man als Link weiter, der Link steht nach dem Anlegen in der Mitgliederliste.
- **Sicherung:** Jede Nacht um 03:30 auf dem Server, 30 Tage lang. Ein verpasster Lauf wird nachgeholt. In Produktion geprüft am 03.11.2026. Zusätzlich sichert jeder Deploy vorher nach `~/bambamdoku-backups/`.
- **Deploy** (`scripts/deploy-hetzner.sh`) läuft nur bei grüner CI auf `main`. Die Seite zeigt dabei etwa 2 Minuten „Kurze Wartung“. Bleibt sie hängen, `storage/logs/laravel.log` per FTP lesen. Details: [DEPLOY.md](DEPLOY.md).

## Was in der Nacht vom 02. auf den 03.11. dazukam

| PR       | Inhalt                                                                                                                                                                                        |
| -------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| #37, #38 | Browser-Tests der Kernabläufe in der CI. Behoben: Knopf „Auszug …“ fehlte nach dem Mitgliederfilter; vergangene Zeiten waren heute noch anklickbar.                                           |
| #39      | Deploy sichert vorher die Datenbank und zeigt während des Uploads eine Wartungsseite.                                                                                                         |
| #40      | Browser-Tests: Gast, Registerentzug, Einladung, Storno, eigene Kladde, Deaktivieren.                                                                                                          |
| #41      | CSV-Import auch für Mitglieder ohne E-Mail (Zuordnung über die Mitgliedsnummer). Browser-Tests: QR-Ankunft, nicht erschienen.                                                                 |
| #42      | Barrierefreiheit: Kontrast nach WCAG AA, axe-Prüfung hell und dunkel.                                                                                                                         |
| #43      | Schlechtes Netz am Stand: klare Meldung statt stummem Fehlschlag; abgelaufene Sitzung lädt neu.                                                                                               |
| #45      | Verband, Vereinsnummer und „digital seit“ in den Einstellungen (vorher nur über die `.env`).                                                                                                  |
| #46, #47 | Verpasste Nachtsicherung wird nachgeholt. Befunde einer unabhängigen Durchsicht: Migration läuft vor dem Ende der Wartung, Sicherung der ruhenden DB, Import hält die Zuordnung der Vorschau. |

Insgesamt prüfen jetzt über 200 PHP-Tests und 23 Browser-Abläufe jeden PR.

## Offen, entscheidest du

Q1b (Mitglieder-Login), Q2 (Buchung im Kern?), Q5 (Erfolgskriterium „ohne Papier“), Q6 (Aufbewahrungsfristen), Q7 (Buchungsteile stilllegen?). Fragen und Empfehlungen: [ENTSCHEIDUNGEN.md](ENTSCHEIDUNGEN.md). Bis dahin bleibt die Buchung bestehen, hat aber keinen Nachweiswert. Nachweis ist allein die Kladde.

Offen für den Feldtest (M6): ein echter Schießtag ohne Papierliste mit dem Verein.
