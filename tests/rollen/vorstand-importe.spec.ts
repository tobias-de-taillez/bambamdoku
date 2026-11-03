import { readFileSync, writeFileSync } from 'node:fs';
import type { Download, Page } from '@playwright/test';
import {
    anmelden,
    beweis,
    expect,
    heute,
    jahr,
    laden,
    oeffnen,
    PERSONEN,
    tag,
    test,
    type Person,
} from './beweis';
import { tagPlanen } from './planung';
import { sessionStarten } from './stand';

// Die Demo-Samstage (10–14 Uhr) belegen alle vier Stände, auch die Bogenwiese. Nachmittags ist
// sie an jedem Tag frei; die Termine dieser Geschichten liegen deshalb dort von 15 bis 18 Uhr.
const BOGENWIESE = 'Bogenwiese';
const VON = '15:00';
const BIS = '18:00';

const SERIE = 'Bogentraining am Samstag';

const KURS = 'Bogenkurs Teil';
const KELLER = 'Kellerschießen';
const VERDREHT = 'Verdrehte Zeiten';

const NORA: Person = {
    name: 'Nora Neumann',
    rolle: 'Aufsicht',
    geraet: 'tablet',
    email: 'nora.neumann@demo.test',
};
const NORA_PASSWORT = 'Nora-Passwort-2026';
const PAUL = { name: 'Paul Pfeil', email: 'paul.pfeil@demo.test' };

/** Zeilen einer heruntergeladenen CSV-Vorlage, ohne BOM und ohne Leerzeile am Ende. */
async function csvZeilen(download: Download): Promise<string[]> {
    return readFileSync(await download.path(), 'utf8')
        .replace(/^﻿/, '')
        .split('\r\n')
        .filter((zeile) => zeile !== '');
}

/** CSV-Datei zum Hochladen in den Ordner dieses Tests schreiben, nicht ins Repository. */
function csvDatei(name: string, zeilen: string[]): string {
    const pfad = test.info().outputPath(name);
    writeFileSync(pfad, `﻿${zeilen.join('\r\n')}\r\n`);

    return pfad;
}

/** „Samstag, 7. November“, wie die Karten unter „Schießtage“ einen Tag nennen. */
function langesDatum(iso: string): string {
    return new Date(`${iso}T12:00:00Z`).toLocaleDateString('de-DE', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'UTC',
    });
}

/**
 * Jeder 1. und 3. Samstag im Monat von morgen bis einschließlich `bis` Tage nach heute,
 * unabhängig von der App gerechnet. Der erste ist der Start der Serie.
 */
function ersteUndDritteSamstage(
    bis: number,
): { iso: string; anzeige: string }[] {
    const samstage: { iso: string; anzeige: string }[] = [];

    for (let versatz = 1; versatz <= bis; versatz++) {
        const kandidat = tag(versatz);
        const datum = new Date(`${kandidat.iso}T12:00:00Z`);
        const woche = Math.floor((datum.getUTCDate() - 1) / 7) + 1;

        if (datum.getUTCDay() === 6 && (woche === 1 || woche === 3)) {
            samstage.push(kandidat);
        }
    }

    return samstage;
}

/** Karten eines Termins unter „Schießtage“, gefunden über den Titel. */
function karten(page: Page, titel: string | RegExp) {
    return page.getByRole('link', {
        name: typeof titel === 'string' ? new RegExp(titel) : titel,
    });
}

test(
    'Serie: Vorstand legt jeden 1. und 3. Samstag einen Termin an, Mitglied sieht genau diese Samstage',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Petra plant auf der Bogenwiese ein Bogentraining an jedem 1. und 3. Samstag im Monat, vom nächsten passenden Samstag an für rund drei Monate. Das Formular zeigt vor dem Speichern, welche Samstage angelegt werden. Ben sieht danach auf dem Handy genau diese Samstage unter „Schießtage“, keinen 2. oder 4. Samstag.',
        },
    },
    async ({ browser }) => {
        // 89 Tage: Mitglieder sehen Termine bis 90 Tage im Voraus (booking_horizon_days).
        const samstage = ersteUndDritteSamstage(89);
        const start = samstage[0];
        const bis = tag(89);
        const anzahl = samstage.length;
        expect(anzahl).toBeGreaterThanOrEqual(5);

        const petra = await anmelden(browser, PERSONEN.petra);
        await tagPlanen(petra, {
            datum: start.iso,
            von: VON,
            bis: BIS,
            titel: SERIE,
            stand: BOGENWIESE,
        });
        await petra.locator('#repeat').selectOption('monthly_weekday');
        await expect(petra.locator('#repeat')).toContainText(
            'monatlich am selben Wochentag (Samstag)',
        );

        const wochen = petra.getByRole('group', {
            name: 'Welche Samstage im Monat?',
        });

        for (const name of ['1. Samstag', '3. Samstag']) {
            const box = wochen.getByRole('checkbox', { name, exact: true });

            if ((await box.getAttribute('aria-checked')) !== 'true') {
                await box.click();
            }

            await expect(box).toHaveAttribute('aria-checked', 'true');
        }

        await expect(
            wochen.getByRole('checkbox', { checked: true }),
        ).toHaveCount(2);
        await petra.locator('#repeat_until').fill(bis.iso);
        // Ohne Fokus ist im Foto kein Datumsteil markiert.
        await petra.locator('#repeat_until').blur();

        // Die Vorschau nennt höchstens sechs Samstage, dann „…“ und den letzten.
        const vorschau = petra
            .locator('[aria-live="polite"]')
            .filter({ hasText: /Es werden \d+ Termine angelegt/ });
        await expect(vorschau).toContainText(
            `Es werden ${anzahl} Termine angelegt`,
        );
        const gezeigt =
            anzahl <= 6
                ? samstage
                : [...samstage.slice(0, 6), samstage.at(-1)!];
        await expect(vorschau.getByRole('listitem')).toHaveText(
            gezeigt.map((samstag) => `Sa ${samstag.anzeige}`),
        );
        await beweis(
            petra,
            `plant das Bogentraining jeden 1. und 3. Samstag vom ${start.anzeige} bis ${bis.anzeige}; die Vorschau zeigt die ${anzahl} Samstage`,
            { zeige: vorschau, belegt: ['P7'] },
        );

        await petra
            .getByRole('button', { name: `${anzahl} Termine anlegen` })
            .click();
        await expect(petra).toHaveURL(/verwaltung\/schiesstage$/);
        const angelegt = petra.getByText(`${anzahl} Schießtage angelegt.`);
        await expect(angelegt).toBeVisible();
        await expect(
            petra.getByRole('listitem').filter({ hasText: SERIE }),
        ).toHaveCount(anzahl);
        await beweis(
            petra,
            `legt die Serie in einem Schritt an: ${anzahl} Schießtage`,
            { zeige: angelegt, belegt: ['P7'] },
        );

        const ben = await anmelden(browser, PERSONEN.ben);
        await ben.goto('/schiesstage');
        const serie = karten(ben, SERIE);
        await expect(serie).toHaveCount(anzahl);
        // In dieser Reihenfolge, und es sind alle: kein 2., 4. oder 5. Samstag dazwischen.
        await expect(serie).toContainText(
            samstage.map((samstag) => langesDatum(samstag.iso)),
        );
        await expect(
            serie.filter({ hasText: `${VON}–${BIS} Uhr` }),
        ).toHaveCount(anzahl);
        await expect(serie.filter({ hasText: BOGENWIESE })).toHaveCount(anzahl);

        const erster = langesDatum(start.iso);
        await beweis(
            ben,
            `sieht unter „Schießtage“ genau die ${anzahl} Samstage der Serie, zuerst ${erster}`,
            { zeige: serie.filter({ hasText: erster }), belegt: ['P7'] },
        );

        const letzter = langesDatum(samstage.at(-1)!.iso);
        await beweis(
            ben,
            `und zuletzt ${letzter}, alle ${VON}–${BIS} Uhr auf der Bogenwiese`,
            { zeige: serie.filter({ hasText: letzter }), belegt: ['P7'] },
        );
    },
);

test(
    'Schießtage-Import: Vorstand lädt die Vorlage, importiert eine CSV-Datei mit zwei fehlerhaften Zeilen, Mitglied sieht die übrigen Termine',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Petra lädt die Vorlage für den Schießtage-Import und füllt sie mit fünf Terminen: drei Teile eines Bogenkurses, einen Termin auf einem Stand, den es nicht gibt, und einen, der endet, bevor er beginnt. Die Vorschau nennt die zwei fehlerhaften Zeilen und lässt sie aus; Petra übernimmt die drei übrigen. Max sieht den Bogenkurs auf dem Handy, die fehlerhaften Termine nicht.',
        },
    },
    async ({ browser }) => {
        const petra = await anmelden(browser, PERSONEN.petra);
        await petra.goto('/verwaltung/schiesstage');
        await petra
            .getByRole('link', { name: 'Importieren', exact: true })
            .click();
        await expect(petra).toHaveURL(/verwaltung\/schiesstage\/import$/);

        const vorlage = await laden(
            petra,
            petra.getByRole('link', { name: 'Vorlage (CSV)' }),
        );
        expect(vorlage.suggestedFilename()).toBe('schiesstage-vorlage.csv');
        const [kopf] = await csvZeilen(vorlage);
        expect(kopf).toBe(
            'Datum;Beginn;Ende;Titel;Stände;Gäste erlaubt;Hinweise',
        );
        await beweis(
            petra,
            'lädt die Vorlage schiesstage-vorlage.csv mit der Kopfzeile „Datum;Beginn;Ende;Titel;Stände;Gäste erlaubt;Hinweise“',
            {
                zeige: petra.getByRole('region', { name: 'Spalten der Datei' }),
                belegt: ['P8'],
            },
        );

        const teil1 = tag(2);
        const teil2 = tag(9);
        const teil3 = tag(16);
        const datei = csvDatei('schiesstage.csv', [
            kopf,
            `${teil1.anzeige};${VON};${BIS};${KURS} 1;${BOGENWIESE};ja;`,
            `${tag(5).anzeige};${VON};${BIS};${KELLER};Schießkeller;ja;`,
            `${teil2.anzeige};${VON};${BIS};${KURS} 2;${BOGENWIESE};ja;`,
            `${tag(12).anzeige};${BIS};${VON};${VERDREHT};${BOGENWIESE};ja;`,
            `${teil3.anzeige};${VON};${BIS};${KURS} 3;${BOGENWIESE};nein;Bitte eigene Bögen mitbringen`,
        ]);
        await petra.locator('#file').setInputFiles(datei);
        await petra.getByRole('button', { name: 'Datei prüfen' }).click();

        const fehler = petra
            .getByText('2 Zeilen werden übersprungen')
            .locator('..');
        await expect(fehler.getByRole('listitem')).toHaveText([
            'Zeile 3: Stand „Schießkeller“ gibt es nicht.',
            'Zeile 5: Das Ende muss nach dem Beginn liegen.',
        ]);
        await beweis(
            petra,
            'sieht in der Vorschau die zwei fehlerhaften Zeilen: unbekannter Stand, Ende vor Beginn',
            { zeige: fehler, belegt: ['P8'] },
        );

        const tabelle = petra.getByRole('table');
        await expect(tabelle.getByRole('row')).toHaveCount(4);
        await expect(
            tabelle.getByRole('row', { name: new RegExp(`${KURS} 3`) }),
        ).toContainText('ohne Gäste');
        await expect(tabelle).not.toContainText(KELLER);
        await expect(tabelle).not.toContainText(VERDREHT);
        const uebernehmen = petra.getByRole('button', {
            name: '3 Schießtage anlegen (2 Zeilen übersprungen)',
        });
        await expect(uebernehmen).toBeVisible();
        await beweis(
            petra,
            'sieht darunter die drei gültigen Termine des Bogenkurses, die sie übernehmen kann',
            { zeige: tabelle, belegt: ['P8'] },
        );

        await uebernehmen.click();
        await expect(petra).toHaveURL(/verwaltung\/schiesstage$/);
        const angelegt = petra.getByText('3 Schießtage angelegt.');
        await expect(angelegt).toBeVisible();
        await expect(
            petra.getByRole('listitem').filter({ hasText: KURS }),
        ).toHaveCount(3);
        await beweis(petra, 'übernimmt die drei Termine', {
            zeige: angelegt,
            belegt: ['P8'],
        });

        const max = await anmelden(browser, PERSONEN.max);
        await max.goto('/schiesstage');
        const kurs = karten(max, KURS);
        await expect(kurs).toHaveCount(3);
        await expect(kurs).toContainText([
            langesDatum(teil1.iso),
            langesDatum(teil2.iso),
            langesDatum(teil3.iso),
        ]);
        await expect(karten(max, KELLER)).toHaveCount(0);
        await expect(karten(max, VERDREHT)).toHaveCount(0);
        const ersterTeil = karten(max, `${KURS} 1`);
        await expect(ersterTeil).toContainText(langesDatum(teil1.iso));
        await expect(ersterTeil).toContainText(`${VON}–${BIS} Uhr`);
        await expect(ersterTeil).toContainText(BOGENWIESE);
        await beweis(
            max,
            `sieht den importierten Bogenkurs unter „Schießtage“, Teil 1 am ${teil1.anzeige}; die zwei fehlerhaften Termine gibt es nicht`,
            { zeige: ersterTeil, belegt: ['P8'] },
        );
    },
);

test(
    'Mitglieder-Import: Vorstand importiert neue Mitglieder, eine gleich als Aufsicht; die eigene Registrierung weist der Import ab, die neue Aufsicht startet eine Session',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Petra lädt die Vorlage für den Mitglieder-Import und trägt drei Zeilen ein: Nora Neumann als Aufsicht für erlaubnisfreie Waffen, sich selbst mit einem neuen Registereintrag und Paul Pfeil als einfaches Mitglied. Die Vorschau weist Petras eigene Registrierung ab (Vier-Augen-Prinzip). Petra bestätigt Volljährigkeit und Sachkunde, importiert mit Einladungen und gibt Nora den Link. Nora setzt am Tablet ihr Passwort und startet am Stand eine Session.',
        },
    },
    async ({ browser }) => {
        const petra = await anmelden(browser, PERSONEN.petra);
        await petra.goto('/verwaltung/mitglieder');
        await petra
            .getByRole('link', { name: 'Importieren', exact: true })
            .click();
        await expect(petra).toHaveURL(/verwaltung\/mitglieder\/import$/);

        const vorlage = await laden(
            petra,
            petra.getByRole('link', { name: 'Vorlage (CSV)' }),
        );
        expect(vorlage.suggestedFilename()).toBe('mitglieder-vorlage.csv');
        const [kopf] = await csvZeilen(vorlage);
        expect(kopf).toBe(
            'Vorname;Nachname;E-Mail;Mitgliedsnummer;Eintritt;Volljährig geprüft;Aufsicht;Aufsicht gültig bis;Aufsicht geprüft am;Aufsicht Bemerkung',
        );
        await beweis(
            petra,
            'lädt die Vorlage mitglieder-vorlage.csv, mit den Spalten für das Aufsichtsregister',
            {
                zeige: petra.getByRole('region', {
                    name: 'Spalten',
                    exact: true,
                }),
                belegt: ['A8'],
            },
        );

        const gueltigBis = `31.12.${jahr() + 1}`;
        const datei = csvDatei('mitglieder.csv', [
            kopf,
            `Nora;Neumann;${NORA.email};;${heute()};ja;erlaubnisfrei;${gueltigBis};${heute()};Lehrgang Standaufsicht`,
            `Petra;Vorstand;${PERSONEN.petra.email};;;;erlaubnisfrei;${gueltigBis};${heute()};`,
            `Paul;Pfeil;${PAUL.email};;${heute()};ja;;;;`,
        ]);
        await petra.locator('#file').setInputFiles(datei);
        await petra.getByRole('button', { name: 'Datei prüfen' }).click();

        const abgewiesen = petra
            .getByText('1 Zeile wird nicht übernommen')
            .locator('..');
        await expect(abgewiesen.getByRole('listitem')).toHaveText([
            'Zeile 3: Die eigene Registrierung trägt ein anderes Vorstandsmitglied ein.',
        ]);
        await beweis(
            petra,
            'sieht, dass der Import ihre eigene Registrierung als Aufsicht abweist (Vier-Augen-Prinzip)',
            { zeige: abgewiesen, belegt: ['A8'] },
        );

        await expect(
            petra.getByText(
                /2\s+neu,\s+0\s+werden\s+aktualisiert,\s+1\s+neu\s+im\s+Aufsichtsregister/,
            ),
        ).toBeVisible();
        const noraZeile = petra.getByRole('row', { name: /Nora Neumann/ });
        await expect(noraZeile).toContainText(
            `erlaubnisfrei bis ${gueltigBis}`,
        );
        await expect(noraZeile).toContainText(`geprüft am ${heute()}`);
        await expect(noraZeile).toContainText('Neu');
        await expect(
            petra.getByRole('row', { name: /Paul Pfeil/ }),
        ).toContainText('Neu');
        await expect(
            petra.getByRole('row', { name: /Petra Vorstand/ }),
        ).toHaveCount(0);
        await beweis(
            petra,
            `sieht Nora als neues Mitglied mit Aufsicht erlaubnisfrei bis ${gueltigBis}, daneben Paul ohne Aufsicht`,
            { zeige: noraZeile, belegt: ['A8'] },
        );

        await petra
            .getByRole('checkbox', {
                name: /Einladungen an neue Mitglieder mit E-Mail senden/,
            })
            .click();
        const sachkunde = petra.getByRole('checkbox', {
            name: /Volljährigkeit und Sachkunde geprüft/,
        });
        await sachkunde.click();
        await expect(sachkunde).toHaveAttribute('aria-checked', 'true');
        await petra
            .getByRole('button', {
                name: '2 Mitglieder übernehmen, 1 Zeile mit Fehlern auslassen',
            })
            .click();

        await expect(
            petra
                .getByRole('status')
                .filter({ hasText: 'Import abgeschlossen' }),
        ).toContainText(
            '2 neu angelegt, 0 aktualisiert, 1 im Aufsichtsregister eingetragen.',
        );
        const einladungen = petra.getByRole('region', {
            name: 'Einladungslinks',
        });
        await expect(einladungen).toContainText(
            'Der Mailversand ist nicht eingerichtet',
        );
        await expect(einladungen.getByRole('listitem')).toHaveCount(2);
        await expect(
            einladungen.getByLabel(`Einladungslink für ${PAUL.name}`),
        ).toHaveValue(/\/einladung\//);
        const link = await einladungen
            .getByLabel(`Einladungslink für ${NORA.name}`)
            .inputValue();
        expect(link).toContain('/einladung/');
        await beweis(
            petra,
            'importiert Nora und Paul und bekommt die Einladungslinks zum Weitergeben',
            { zeige: einladungen, belegt: ['A8'] },
        );

        await petra.goto('/verwaltung/mitglieder?status=supervisors');
        const noraAufsicht = petra.getByRole('row', { name: /Nora Neumann/ });
        await expect(noraAufsicht).toContainText(
            `bis ${gueltigBis} (erlaubnisfrei)`,
        );
        await expect(noraAufsicht).toContainText('Einladung offen');
        // Petras eigene Zeile aus der Datei ist nicht angekommen: Umfang und Ende wie vorher.
        const petraAufsicht = petra.getByRole('row', {
            name: /Petra Vorstand/,
        });
        await expect(petraAufsicht).toContainText('bis ');
        await expect(petraAufsicht).not.toContainText('erlaubnisfrei');
        await expect(petraAufsicht).not.toContainText(gueltigBis);
        await beweis(
            petra,
            `findet Nora unter „Aufsichten“, erlaubnisfrei bis ${gueltigBis}; Petras eigener Registereintrag ist unverändert`,
            { zeige: noraAufsicht, belegt: ['A8'] },
        );

        const nora = await oeffnen(browser, NORA);
        const willkommen = await nora.goto(link);
        expect(willkommen?.status()).toBe(200);
        await beweis(nora, 'öffnet den Einladungslink am Tablet', {
            zeige: nora.getByText(`Hallo ${NORA.name}!`),
        });

        await nora.locator('#password').fill(NORA_PASSWORT);
        await nora.locator('#password_confirmation').fill(NORA_PASSWORT);
        await nora
            .getByRole('button', { name: 'Passwort festlegen und anmelden' })
            .click();
        await expect(nora).toHaveURL(/dashboard/);
        await beweis(nora, 'setzt ihr Passwort und ist angemeldet', {
            zeige: nora.getByRole('heading', { name: 'Hallo, Nora' }),
        });

        await nora.goto('/stand');
        await expect(
            nora.getByText(/Eine Session starten dürfen nur Personen/),
        ).toHaveCount(0);
        await beweis(nora, 'darf am Stand eine Session starten', {
            zeige: nora.getByRole('button', { name: 'Session starten' }),
            belegt: ['A8'],
        });

        await sessionStarten(nora);
        await beweis(nora, 'startet ihre erste Session als Aufsicht', {
            zeige: nora.getByText(/Session seit/),
            belegt: ['A8'],
        });
    },
);
