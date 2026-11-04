import type { Locator, Page } from '@playwright/test';
import {
    anmelden,
    beweis,
    expect,
    heute,
    jahr,
    laden,
    PASSWORD,
    pdfText,
    PERSONEN,
    tag,
    test,
} from './beweis';
import {
    abzeichnen,
    aktivieren,
    erfassen,
    sessionStarten,
    teilnehmer,
    teilnehmerZeile,
} from './stand';

/** Erste Zahl in einem Text, etwa „62 Tage“ oder „25 Schüsse erfasst · 61 ohne Angabe“. */
function zahl(text: string | null): number {
    return Number(text?.match(/\d+/)?.[0]);
}

/** Karte einer Kategorie unter „Anzahl Tage im Zeitraum“ in der eigenen Schießkladde. */
function summe(page: Page, kategorie: string): Locator {
    return page
        .getByRole('region', { name: 'Anzahl Tage im Zeitraum' })
        .getByText(kategorie, { exact: true })
        .locator('..');
}

/** Zeile einer Kladde-Tabelle: Datum, Text und genau diese Zahl in der Spalte Schüsse. */
function zeile(
    page: Page,
    datum: string,
    text: string,
    schuesse: string,
): Locator {
    return page
        .getByRole('row')
        .filter({ hasText: datum })
        .filter({ hasText: text })
        .filter({
            has: page.getByRole('cell', { name: schuesse, exact: true }),
        });
}

test(
    'Nach dem Schießtag: Vorstand korrigiert, storniert und trägt nach, die Mitglieder sehen es in ihrer Kladde',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Helga erfasst am Stand 40 Schuss Langwaffe für Lena und 25 Schuss Kurzwaffe für Max und zeichnet ab; danach können weder die beiden noch Helga etwas daran ändern. Petra korrigiert in der Kladde des Vereins Lenas Eintrag auf 45, storniert den von Max und trägt einen vergessenen Besuch von Ben nach, jeweils nach Passwortbestätigung und mit Grund. Lena, Max und Ben sehen das in ihrer eigenen Schießkladde, Petra lädt Session-Protokoll, Kladde-Auszug und Mitgliedschaftsbescheinigung.',
        },
    },
    async ({ browser }) => {
        const iso = tag(0).iso;
        const gestern = tag(-1);
        const grundKorrektur = 'Schießbuch der Aufsicht nachgezählt';
        const grundStorno = 'Max war nur Begleitung und hat nicht geschossen';
        const grundNachtrag = `Papierliste vom ${gestern.anzeige}, Aufsicht: Jonas Aufsicht`;

        // Bekannte Daten: Helga erfasst und zeichnet ab.
        const helga = await anmelden(browser, PERSONEN.helga);
        await sessionStarten(helga);
        await aktivieren(helga, 'Lena Langwaffe');
        await aktivieren(helga, 'Max Mustermann');
        await erfassen(helga, 'Lena Langwaffe', {
            kategorie: 'Langwaffe',
            waffe: 'Vereinswaffe',
            schuesse: '40',
        });
        await erfassen(helga, 'Max Mustermann', {
            kategorie: 'Kurzwaffe',
            waffe: 'eigene',
            schuesse: '25',
        });
        await expect(teilnehmerZeile(helga, 'Lena Langwaffe')).toContainText(
            'Langwaffe · Verein · 40 Schüsse · erfasst über Aufsicht',
        );
        await expect(teilnehmerZeile(helga, 'Max Mustermann')).toContainText(
            'Kurzwaffe · eigene · 25 Schüsse · erfasst über Aufsicht',
        );
        await beweis(
            helga,
            'erfasst am Stand für Lena 40 Schuss Langwaffe und für Max 25 Schuss Kurzwaffe',
            { zeige: teilnehmer(helga) },
        );

        await abzeichnen(helga);
        const abgezeichnet = helga
            .locator('section[aria-labelledby="signed"] li')
            .filter({ hasText: '2 Einträge' });
        await expect(teilnehmer(helga)).toHaveCount(0);
        await expect(
            helga.getByRole('button', { name: 'Zurückziehen' }),
        ).toHaveCount(0);
        await expect(
            helga.getByRole('button', { name: 'Ablehnen' }),
        ).toHaveCount(0);
        await expect(
            helga.getByRole('button', { name: 'Erfassen' }),
        ).toHaveCount(0);
        await beweis(
            helga,
            'hat abgezeichnet: 2 Einträge stehen fest, Zurückziehen, Ablehnen oder Erfassen gibt es nicht mehr',
            { zeige: abgezeichnet, belegt: ['G7'] },
        );

        const gesperrt = await helga.goto('/verwaltung/schiesskladde');
        expect(gesperrt?.status()).toBe(403);
        await beweis(
            helga,
            'wird von der Kladde des Vereins abgewiesen (403), ändern kann sie dort nichts',
            {
                zeige: helga.getByRole('heading', { name: 'Kein Zugriff' }),
                belegt: ['G7'],
            },
        );

        // Lena: der abgezeichnete Eintrag ist fest.
        const lena = await anmelden(browser, PERSONEN.lena);
        await lena.goto('/heute');
        const lenaHeute = lena
            .locator('section[aria-labelledby="own-entries"] li')
            .filter({ hasText: '40 Schüsse' });
        await expect(lenaHeute).toContainText('abgezeichnet');
        await expect(
            lena.getByText('Die Session ist abgezeichnet.', { exact: false }),
        ).toBeVisible();
        await expect(
            lena.getByRole('button', { name: 'Zurückziehen' }),
        ).toHaveCount(0);
        await expect(
            lena.getByRole('region', { name: 'Neuer Eintrag' }),
        ).toHaveCount(0);
        await beweis(
            lena,
            'sieht unter „Heute schießen“ ihren Eintrag als abgezeichnet, ohne Zurückziehen und ohne neues Formular',
            { zeige: lenaHeute, belegt: ['G7'] },
        );

        await lena.goto('/schiesskladde');
        const lena40 = zeile(lena, heute(), 'Langwaffe', '40');
        await expect(lena40).toContainText('Verein');
        await expect(lena40.getByRole('button')).toHaveCount(0);
        // Auch am Handy steht die Spalte Schüsse ohne seitliches Wischen im Bild.
        await beweis(
            lena,
            'findet heute 40 Schuss Langwaffe in ihrer Schießkladde, ohne Knopf zum Ändern',
            {
                zeige: lena40.getByRole('cell', { name: '40', exact: true }),
                belegt: ['G7'],
            },
        );

        // Max: vorher, damit das Storno messbar ist.
        const max = await anmelden(browser, PERSONEN.max);
        await max.goto('/schiesskladde');
        const max25 = zeile(max, heute(), 'Kurzwaffe', '25');
        await expect(max25).toContainText('eigene');
        const kurzwaffe = summe(max, 'Kurzwaffe');
        const tage = kurzwaffe.getByText(/^\d+ Tage$/);
        const tageVorher = zahl(await tage.textContent());
        const schuesseVorher = zahl(
            await kurzwaffe.getByText(/Schüsse erfasst/).textContent(),
        );
        expect(tageVorher).toBeGreaterThan(0);
        expect(schuesseVorher).toBeGreaterThanOrEqual(25);
        await beweis(
            max,
            `sieht den Besuch von heute mitgezählt: Kurzwaffe ${tageVorher} Tage, ${schuesseVorher} Schüsse erfasst (Ausgangswert)`,
            { zeige: kurzwaffe },
        );

        // Petra: Kladde des Vereins und Session-Protokoll.
        const petra = await anmelden(browser, PERSONEN.petra);
        await petra.goto('/verwaltung/schiesskladde');
        await expect(
            petra.getByRole('heading', { name: 'Schießkladde des Vereins' }),
        ).toBeVisible();
        const lenaVorher = zeile(petra, heute(), 'Lena Langwaffe', '40');
        const maxZeile = zeile(petra, heute(), 'Max Mustermann', '25');
        await expect(lenaVorher).toContainText('Langwaffe · Verein');
        await expect(lenaVorher).toContainText('Helga Schießwart');
        await expect(maxZeile).toContainText('Kurzwaffe · eigene');
        await beweis(
            petra,
            'sieht Lenas 40 Schuss Langwaffe, bestätigt von Helga, in der Kladde des Vereins',
            { zeige: lenaVorher, belegt: ['K2'] },
        );

        await expect(lenaVorher.getByRole('button')).toHaveCount(0);
        await expect(maxZeile.getByRole('button')).toHaveCount(0);
        await beweis(
            petra,
            'kann ohne erneute Passworteingabe nichts korrigieren oder stornieren',
            {
                zeige: petra.getByText(
                    'Nachtrag, Storno und Korrektur gibt es erst nach erneuter Eingabe Ihres Passworts.',
                ),
                belegt: ['G7'],
            },
        );

        const protokoll = petra.getByRole('link', {
            name: `Protokoll der Session am ${heute()}, Helga Schießwart`,
        });
        const session = petra.getByRole('row').filter({ has: protokoll });
        await expect(session).toContainText('abgezeichnet');
        await expect(session).toContainText('2 abgezeichnet');
        const protokollVorher = await laden(petra, protokoll);
        expect(protokollVorher.suggestedFilename()).toBe(
            `Session-Protokoll-${iso}-helga-schiesswart.pdf`,
        );
        const inhaltVorher = await pdfText(protokollVorher);
        expect(inhaltVorher).toContain(
            `Abgezeichnet von Helga Schießwart am ${heute()}`,
        );
        expect(inhaltVorher).toMatch(/Lena Langwaffe .* Langwaffe Verein 40 /);
        expect(inhaltVorher).toMatch(/Max Mustermann .* Kurzwaffe eigene 25 /);
        expect(inhaltVorher).toContain('Keine Stornos oder Korrekturen.');
        await beweis(
            petra,
            `lädt das Session-Protokoll (Session-Protokoll-${iso}-helga-schiesswart.pdf): Lena 40, Max 25 Schuss`,
            { zeige: session, belegt: ['K2'] },
        );

        await petra.getByRole('link', { name: 'Passwort bestätigen' }).click();
        await petra.getByLabel('Passwort', { exact: true }).fill(PASSWORD);
        await petra
            .getByRole('button', { name: 'Passwort bestätigen' })
            .click();
        await expect(
            petra.getByRole('heading', { name: 'Schießkladde des Vereins' }),
        ).toBeVisible();
        await expect(
            lenaVorher.getByRole('button', { name: 'Korrektur' }),
        ).toBeVisible();
        await expect(
            lenaVorher.getByRole('button', { name: 'Storno' }),
        ).toBeVisible();
        await beweis(
            petra,
            'kann nach der Passwortbestätigung Lenas Eintrag korrigieren oder stornieren',
            { zeige: lenaVorher, belegt: ['G7'] },
        );

        // Korrektur: 40 → 45
        await lenaVorher.getByRole('button', { name: 'Korrektur' }).click();
        const korrektur = petra
            .locator('form')
            .filter({ has: petra.locator('#correct-reason') });
        await expect(korrektur.locator('#correct-class')).toHaveValue('long');
        await expect(korrektur.locator('#correct-owner')).toHaveValue('club');
        await korrektur.locator('#correct-shots').fill('45');
        await korrektur.locator('#correct-reason').fill(grundKorrektur);
        await beweis(
            petra,
            'korrigiert Lenas Schüsse von 40 auf 45 und nennt den Grund',
            { zeige: korrektur, belegt: ['K3'] },
        );

        await korrektur.getByRole('button', { name: 'Korrigieren' }).click();
        await expect(petra.getByText('Eintrag korrigiert.')).toBeVisible();
        const lenaNeu = zeile(petra, heute(), 'Lena Langwaffe', '45');
        await expect(lenaNeu).toContainText('Korrektur');
        await expect(lenaNeu).toContainText(`korrigiert Zeile #`);
        await expect(lenaNeu).toContainText(
            `korrigiert von Petra Vorstand: ${grundKorrektur}`,
        );
        await expect(lenaVorher).toContainText(
            `storniert: Korrektur: ${grundKorrektur} (Petra Vorstand), neu: Zeile #`,
        );
        await beweis(
            petra,
            'sieht Lenas neue Zeile mit 45 Schuss als Korrektur von Petra mit Grund, die alte mit 40 ist storniert',
            { zeige: lenaNeu, belegt: ['K3', 'K6'] },
        );

        await lena.reload();
        const lena45 = zeile(lena, heute(), 'Langwaffe', '45');
        await expect(lena45).toContainText('Korrektur');
        await expect(lena40).toHaveCount(0);
        await beweis(
            lena,
            'sieht in ihrer Schießkladde jetzt 45 Schuss Langwaffe mit dem Vermerk „Korrektur“',
            {
                zeige: lena45.getByRole('cell', { name: '45', exact: true }),
                belegt: ['K3'],
            },
        );

        // Storno: Max
        await maxZeile.getByRole('button', { name: 'Storno' }).click();
        await maxZeile.getByLabel('Grund für das Storno').fill(grundStorno);
        await maxZeile
            .getByRole('button', { name: 'Storno', exact: true })
            .click();
        await expect(petra.getByText('Eintrag storniert.')).toBeVisible();
        await expect(maxZeile).toContainText(
            `storniert: ${grundStorno} (Petra Vorstand)`,
        );
        await expect(maxZeile.getByRole('button')).toHaveCount(0);
        await expect(
            maxZeile.getByText('Max Mustermann', { exact: true }),
        ).toHaveCSS('text-decoration-line', 'line-through');
        await beweis(
            petra,
            'storniert Max’ Eintrag; die Zeile bleibt durchgestrichen mit Grund und Namen stehen',
            { zeige: maxZeile, belegt: ['K4', 'K6'] },
        );

        // Die stornierte Zeile bleibt in Max’ Kladde stehen, durchgestrichen und ohne Zählung.
        await max.reload();
        await expect(max25).toHaveCount(1);
        await expect(
            max.getByRole('row').filter({ hasText: heute() }),
        ).toHaveCount(1);
        await expect(tage).toHaveText(`${tageVorher - 1} Tage`);
        await expect(kurzwaffe.getByText(/Schüsse erfasst/)).toContainText(
            `${schuesseVorher - 25} Schüsse erfasst`,
        );
        await expect(
            max.getByText('Stornierte Einträge zählen nicht.'),
        ).toBeVisible();
        await beweis(
            max,
            `zählt den stornierten Besuch nicht mehr mit: Kurzwaffe ${tageVorher - 1} Tage, ${schuesseVorher - 25} Schüsse erfasst`,
            { zeige: kurzwaffe, belegt: ['K4'] },
        );

        const maxStorno = max25
            .getByRole('cell')
            .filter({ hasText: 'storniert von' });
        await expect(maxStorno).toContainText(
            `storniert von Petra Vorstand: ${grundStorno}`,
        );
        await expect(maxStorno.getByText('Kurzwaffe')).toHaveCSS(
            'text-decoration-line',
            'line-through',
        );
        await expect(
            max25
                .getByRole('cell', { name: '25', exact: true })
                .locator('span'),
        ).toHaveCSS('text-decoration-line', 'line-through');
        await beweis(
            max,
            `sieht den stornierten Besuch in seiner Schießkladde durchgestrichen: „storniert von Petra Vorstand: ${grundStorno}“`,
            { zeige: maxStorno, belegt: ['K4', 'K8'] },
        );

        await max.goto('/heute');
        const maxHeute = max
            .locator('section[aria-labelledby="own-entries"] li')
            .filter({ hasText: '25 Schüsse' });
        await expect(
            maxHeute.getByText('storniert', { exact: true }),
        ).toBeVisible();
        await expect(maxHeute).not.toContainText('abgezeichnet');
        await expect(maxHeute).toContainText(
            `Storniert von Petra Vorstand: ${grundStorno}. Zählt nicht.`,
        );
        await expect(maxHeute.getByText(/25 Schüsse · über/)).toHaveCSS(
            'text-decoration-line',
            'line-through',
        );
        await expect(
            maxHeute.getByRole('button', { name: 'Zurückziehen' }),
        ).toHaveCount(0);
        await beweis(
            max,
            'sieht unter „Heute schießen“ seinen Eintrag als „storniert“ statt „abgezeichnet“, durchgestrichen, mit Petras Grund: zählt nicht',
            { zeige: maxHeute, belegt: ['K8'] },
        );

        // Nachtrag: Ben war gestern da, steht aber nur auf Papier.
        await petra.getByRole('button', { name: 'Nachtrag' }).click();
        const nachtrag = petra
            .locator('form')
            .filter({ has: petra.locator('#late-reason') });
        await nachtrag.locator('#late-filter').fill('Ben');
        await nachtrag.getByRole('checkbox', { name: /Ben Bogen/ }).click();
        await nachtrag.locator('#late-date').fill(gestern.iso);
        await nachtrag.locator('#late-class').selectOption('exempt');
        await nachtrag.locator('#late-owner').selectOption('own');
        await nachtrag.locator('#late-shots').fill('36');
        await nachtrag.locator('#late-reason').fill(grundNachtrag);
        await expect(
            nachtrag.getByText('Mitglieder (1 gewählt)'),
        ).toBeVisible();
        await beweis(
            petra,
            `trägt für Ben einen vergessenen Besuch vom ${gestern.anzeige} nach, mit Grund und Beleg`,
            { zeige: nachtrag, belegt: ['K5'] },
        );

        await nachtrag.getByRole('button', { name: '1 nachtragen' }).click();
        await expect(petra.getByText('1 nachgetragen')).toBeVisible();
        const benZeile = zeile(petra, gestern.anzeige, 'Ben Bogen', '36');
        await expect(benZeile).toContainText('Nachtrag');
        await expect(benZeile).toContainText(
            `Petra Vorstand, Nachtrag: ${grundNachtrag}`,
        );
        await beweis(
            petra,
            'sieht den Nachtrag für Ben in der Kladde, mit ihrem Namen und dem Grund',
            { zeige: benZeile, belegt: ['K5', 'K6'] },
        );

        const ben = await anmelden(browser, PERSONEN.ben);
        await ben.goto('/schiesskladde');
        const benKladde = zeile(ben, gestern.anzeige, 'erlaubnisfrei', '36');
        await expect(benKladde).toContainText('Nachtrag');
        await beweis(
            ben,
            'findet den Nachtrag mit 36 Schuss erlaubnisfrei in seiner Schießkladde',
            {
                zeige: benKladde.getByRole('cell', { name: '36', exact: true }),
                belegt: ['K5'],
            },
        );

        // Das Protokoll der Verwaltung hält das Abzeichnen fest; Korrektur, Storno und Nachtrag
        // führt die Kladde des Vereins selbst, mit Person und Grund.
        await petra.goto('/verwaltung/protokoll');
        const signiert = petra
            .getByRole('listitem')
            .filter({ hasText: `Session abgezeichnet: ${heute()}` });
        await expect(signiert).toContainText('Helga Schießwart');
        await beweis(
            petra,
            'sieht im Protokoll, dass Helga die Session von heute abgezeichnet hat',
            { zeige: signiert },
        );

        // Uhrzeit, Person und Grund stehen außerdem im Session-Protokoll (PDF).
        await petra.goto('/verwaltung/schiesskladde');
        const protokollNachher = await laden(petra, protokoll);
        expect(protokollNachher.suggestedFilename()).toBe(
            `Session-Protokoll-${iso}-helga-schiesswart.pdf`,
        );
        const inhaltNachher = await pdfText(protokollNachher);
        const um = `${heute().replaceAll('.', '\\.')}, \\d{2}:\\d{2} Uhr: `;
        expect(inhaltNachher).toMatch(
            new RegExp(
                `${um}Lena Langwaffe, Langwaffe \\(ohne Disziplin\\): korrigiert von Petra Vorstand: ${grundKorrektur}; neu: Langwaffe, Verein, ohne Disziplin, Schüsse 45`,
            ),
        );
        expect(inhaltNachher).toMatch(
            new RegExp(
                `${um}Max Mustermann, Kurzwaffe \\(ohne Disziplin\\): storniert von Petra Vorstand: ${grundStorno}`,
            ),
        );
        // Bens Nachtrag gehört zu keiner Session, er steht nur in der Kladde des Vereins.
        expect(inhaltNachher).not.toContain('Ben Bogen');
        await beweis(
            petra,
            'lädt das Session-Protokoll erneut: Korrektur und Storno stehen darin mit Uhrzeit, Person und Grund',
            { zeige: session, belegt: ['K6'] },
        );

        // Statistik und Nachweise eines Mitglieds
        await petra.goto('/verwaltung/statistik');
        await expect(
            petra.getByRole('heading', { name: `Statistik ${jahr()}` }),
        ).toBeVisible();
        const aktivste = petra
            .locator('section', {
                has: petra.getByRole('heading', {
                    name: 'Aktivste Mitglieder',
                }),
            })
            .getByRole('listitem')
            .filter({ hasText: 'Lena Langwaffe' });
        await beweis(
            petra,
            `sieht in der Statistik ${jahr()} Lena unter den aktivsten Mitgliedern`,
            { zeige: aktivste, belegt: ['K7'] },
        );

        await petra.goto('/verwaltung/mitglieder');
        await petra
            .getByRole('link', { name: 'Lena Langwaffe', exact: true })
            .click();
        await expect(petra.locator('#name')).toHaveValue('Lena Langwaffe');

        const auszug = petra.getByRole('link', { name: 'Auszug Schießkladde' });
        const auszugPdf = await laden(petra, auszug);
        expect(auszugPdf.suggestedFilename()).toBe(
            `Schiesskladde-lena-langwaffe-${iso}.pdf`,
        );
        const auszugText = await pdfText(auszugPdf);
        expect(auszugText).toContain('Mitglied Lena Langwaffe');
        expect(auszugText).toContain(
            `${heute()} Langwaffe Verein – 45 Korrektur`,
        );
        expect(auszugText).not.toContain(`${heute()} Langwaffe Verein – 40 `);
        await beweis(
            petra,
            `lädt Lenas Auszug (Schiesskladde-lena-langwaffe-${iso}.pdf), darin heute 45 Schuss als Korrektur`,
            { zeige: auszug, belegt: ['K7'] },
        );

        const bescheinigung = petra.getByRole('link', {
            name: 'Mitgliedschaftsbescheinigung',
        });
        const bescheinigungPdf = await laden(petra, bescheinigung);
        expect(bescheinigungPdf.suggestedFilename()).toBe(
            'Mitgliedschaftsbescheinigung-lena-langwaffe.pdf',
        );
        const bescheinigungText = await pdfText(bescheinigungPdf);
        expect(bescheinigungText).toContain('Name Lena Langwaffe');
        expect(bescheinigungText).toContain(
            `Erstellt am ${heute()} von Petra Vorstand`,
        );
        await beweis(
            petra,
            'lädt Lenas Mitgliedschaftsbescheinigung (Mitgliedschaftsbescheinigung-lena-langwaffe.pdf)',
            { zeige: bescheinigung, belegt: ['K7'] },
        );

        // Kein Teil von K7: Den Teilnahmenachweis je Disziplin gibt es erst mit einem Katalog.
        await expect(
            petra.getByRole('link', { name: /Teilnahmenachweis/ }),
        ).toHaveCount(0);
        await beweis(
            petra,
            'sieht neben Auszug und Bescheinigung den Hinweis, dass es ohne Disziplinkatalog keinen Teilnahmenachweis gibt',
            {
                zeige: petra.getByText(
                    'Teilnahmenachweis je Disziplin: nicht verfügbar, solange kein Disziplinkatalog gewählt ist.',
                ),
            },
        );
    },
);
