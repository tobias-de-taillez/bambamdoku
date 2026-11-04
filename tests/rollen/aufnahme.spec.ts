import {
    anmelden,
    anmeldenVersuchen,
    beweis,
    einloggen,
    expect,
    heute,
    oeffnen,
    PERSONEN,
    test,
    wirdZu,
    type Person,
} from './beweis';

const GAST: Person = { name: 'Gast', rolle: 'Gast', geraet: 'handy' };

const RITA: Person = {
    name: 'Rita Registriert',
    rolle: 'Gast',
    geraet: 'handy',
    email: 'rita.registriert@demo.test',
};
const RITA_PASSWORT = 'Rita-Passwort-2026';

const IDA: Person = {
    name: 'Ida Eingeladen',
    rolle: 'Mitglied',
    geraet: 'handy',
    email: 'ida.eingeladen@demo.test',
};
const IDA_PASSWORT = 'Ida-Passwort-2026';

const ANSCHRIFT = 'Am Schießstand 1, 12345 Demodorf';
const IMPRESSUM = `SV Demo e. V.\n${ANSCHRIFT}\nVertreten durch Petra Vorstand`;

test(
    'Aufnahme: Gast beantragt online, Vorstand nimmt auf oder lehnt ab, Eingeladene setzt ihr Passwort',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Ein Gast schaut sich am Handy ohne Konto um, dann schaltet Petra den Online-Antrag frei und pflegt Anschrift und Impressum. Rita beantragt die Mitgliedschaft, Petra nimmt sie auf, danach setzt Rita ihre Kiosk-PIN; Annas Antrag lehnt Petra ab. Ida bekommt von Petra eine Einladung, setzt am Handy ihr Passwort, und ein zweites Mal gilt der Link nicht.',
        },
    },
    async ({ browser }) => {
        // Der Gast am Handy ist Rita, sie hat noch kein Konto.
        const rita = await oeffnen(browser, GAST);
        const start = await rita.goto('/');
        expect(start?.status()).toBe(200);
        await expect(
            rita.getByRole('link', { name: 'Mitgliedschaft beantragen' }),
        ).toHaveCount(0);
        await expect(rita.getByRole('link', { name: 'Impressum' })).toHaveCount(
            0,
        );
        expect((await rita.request.get('/impressum')).status()).toBe(404);
        await beweis(
            rita,
            'öffnet die Startseite ohne Konto, noch ohne „Mitgliedschaft beantragen“',
            {
                zeige: rita.locator('main header'),
                belegt: ['A1'],
            },
        );

        await rita.getByRole('link', { name: 'Datenschutzhinweis' }).click();
        await expect(rita).toHaveURL(/\/datenschutz$/);
        await expect(
            rita.getByRole('heading', { name: 'Datenschutzhinweis', level: 1 }),
        ).toBeVisible();
        await beweis(
            rita,
            'liest den Datenschutzhinweis ohne Konto, auch zum Mitgliedsantrag',
            {
                zeige: rita.getByRole('region', { name: 'Mitgliedsantrag' }),
                belegt: ['A1'],
            },
        );

        const kladde = await rita.goto('/schiesskladde');
        await expect(rita).toHaveURL(/\/login$/);
        expect(kladde?.request().redirectedFrom()?.url()).toContain(
            '/schiesskladde',
        );
        await expect(rita.locator('[data-test="register-link"]')).toHaveCount(
            0,
        );
        await beweis(
            rita,
            'will die Schießkladde öffnen und landet bei der Anmeldung',
            {
                zeige: rita.getByRole('heading', { name: 'Anmelden' }),
                belegt: ['G1', 'A1'],
            },
        );

        const petra = await anmelden(browser, PERSONEN.petra);
        await petra.goto('/verwaltung/einstellungen');
        await petra.getByLabel('Anschrift').fill(ANSCHRIFT);
        await petra.getByLabel('Impressum', { exact: true }).fill(IMPRESSUM);
        const antrag = petra.getByRole('checkbox', {
            name: 'Mitgliedschaft online beantragen lassen',
        });
        await expect(antrag).not.toBeChecked();
        await antrag.click();
        await petra
            .getByRole('button', { name: 'Speichern', exact: true })
            .click();
        await expect(
            petra.getByText('Einstellungen gespeichert.'),
        ).toBeVisible();

        await petra.reload();
        await expect(antrag).toBeChecked();
        await expect(petra.getByLabel('Anschrift')).toHaveValue(ANSCHRIFT);
        await expect(
            petra.getByLabel('Impressum', { exact: true }),
        ).toHaveValue(IMPRESSUM);
        const verein = petra.getByRole('group', {
            name: 'Verein',
            exact: true,
        });
        await beweis(petra, 'pflegt Anschrift und Impressum des Vereins', {
            zeige: verein,
            belegt: ['V4'],
        });
        await beweis(petra, 'schaltet den Online-Antrag frei', {
            zeige: petra.getByRole('group', { name: 'Mitgliedsanträge' }),
            belegt: ['A2'],
        });

        // Ohne Mailversand muss Petra Einladungslinks später selbst weitergeben.
        const mailversand = petra.locator('section').filter({
            has: petra.getByRole('heading', { name: 'E-Mail-Versand' }),
        });
        await expect(mailversand).toContainText(
            'Der Mailversand ist nicht eingerichtet',
        );
        await beweis(
            petra,
            'sieht unter „E-Mail-Versand“, dass keine Mails verschickt werden',
            { zeige: mailversand },
        );

        await rita.goto('/');
        const kopf = rita.locator('main header');
        await expect(kopf).toContainText(ANSCHRIFT);
        await expect(
            kopf.getByRole('link', { name: 'Mitgliedschaft beantragen' }),
        ).toBeVisible();
        await beweis(
            rita,
            'sieht jetzt „Mitgliedschaft beantragen“ und die Anschrift des Vereins',
            {
                zeige: kopf,
                belegt: ['A2', 'V4'],
            },
        );

        await rita.getByRole('link', { name: 'Impressum' }).click();
        await expect(rita).toHaveURL(/\/impressum$/);
        await expect(
            rita.getByRole('heading', { name: 'Impressum', level: 1 }),
        ).toBeVisible();
        // Ohne die Seitenleiste der Mitglieder (am Handy sonst hinter dem Knopf oben links),
        // mit eigenem Rückweg
        await expect(
            rita.getByRole('link', { name: 'Zur Startseite' }),
        ).toBeVisible();
        await expect(
            rita.getByRole('button', { name: 'Seitenleiste ein-/ausblenden' }),
        ).toHaveCount(0);
        await beweis(rita, 'liest das Impressum öffentlich, ohne Konto', {
            zeige: rita.getByText(/Vertreten durch Petra Vorstand/),
            belegt: ['V4'],
        });

        await rita.goto('/');
        await rita
            .getByRole('link', { name: 'Mitgliedschaft beantragen' })
            .click();
        await expect(rita).toHaveURL(/\/register$/);
        wirdZu(rita, RITA);
        await rita.getByLabel('Vor- und Nachname').fill(RITA.name);
        await rita.getByLabel('E-Mail-Adresse').fill(RITA.email!);
        await rita.getByLabel('Passwort', { exact: true }).fill(RITA_PASSWORT);
        await rita.getByLabel('Passwort bestätigen').fill(RITA_PASSWORT);
        await rita.locator('[data-test="register-button"]').click();
        const nurVolljaehrig = rita.getByText('Mitglied werden kann hier nur');
        await expect(nurVolljaehrig).toBeVisible();
        await expect(rita).toHaveURL(/\/register$/);
        await beweis(
            rita,
            'beantragt ohne Haken „volljährig“ und wird abgewiesen',
            {
                zeige: nurVolljaehrig,
                belegt: ['A3'],
            },
        );

        await rita.getByRole('checkbox', { name: /volljährig/ }).click();
        await rita.getByLabel('Passwort', { exact: true }).fill(RITA_PASSWORT);
        await rita.getByLabel('Passwort bestätigen').fill(RITA_PASSWORT);
        await rita.locator('[data-test="register-button"]').click();
        await expect(rita).toHaveURL(/\/login$/);
        wirdZu(rita, { ...RITA, rolle: 'Antragsteller' });
        await beweis(
            rita,
            'stellt den Antrag mit Haken „volljährig“, er liegt beim Vorstand',
            {
                zeige: rita.getByText('Danke! Ihr Antrag liegt beim Vorstand'),
                belegt: ['A3'],
            },
        );

        await anmeldenVersuchen(rita, RITA.email!, RITA_PASSWORT);
        const ritaAbgewiesen = rita.getByText(
            /^Ihr Antrag liegt beim Vorstand\. Sie können sich anmelden/,
        );
        await expect(ritaAbgewiesen).toBeVisible();
        await expect(rita).toHaveURL(/\/login$/);
        await beweis(
            rita,
            'wird bei der Anmeldung mit dem Hinweis auf den offenen Antrag abgewiesen',
            {
                zeige: ritaAbgewiesen,
                belegt: ['A3', 'G4'],
            },
        );

        await petra.goto('/verwaltung/mitglieder?status=applicants');
        await expect(petra.getByRole('tab', { name: /Anträge/ })).toHaveText(
            /^Anträge\s*2$/,
        );
        const ritaAntrag = petra.getByRole('row', { name: /Rita Registriert/ });
        await expect(ritaAntrag).toContainText(`beantragt am ${heute()}`);
        await expect(
            petra.getByRole('row', { name: /Anna Antrag/ }),
        ).toBeVisible();
        await beweis(
            petra,
            'sieht Ritas Antrag von heute neben dem von Anna unter „Anträge“',
            {
                zeige: ritaAntrag,
                belegt: ['A3', 'A4'],
            },
        );

        await ritaAntrag.getByRole('button', { name: 'Aufnehmen' }).click();
        // Der Dialog hat keinen zugänglichen Namen, deshalb über seinen Titel.
        const dialog = petra
            .getByRole('dialog')
            .filter({ hasText: 'Antrag aufnehmen' });
        await dialog.getByLabel('Mitgliedsnummer').fill('4711');
        await dialog.locator('[data-test="admit-submit"]').click();
        const bestaetigen = dialog.getByText('Bitte bestätigen Sie');
        await expect(bestaetigen).toBeVisible();
        await beweis(
            petra,
            'kann ohne Haken „Identität und Volljährigkeit geprüft“ nicht aufnehmen',
            {
                zeige: dialog,
                belegt: ['A4'],
            },
        );

        await dialog
            .getByRole('checkbox', {
                name: /Identität und Volljährigkeit geprüft/,
            })
            .click();
        await dialog.locator('[data-test="admit-submit"]').click();
        await expect(
            petra.getByText('Rita Registriert ist aufgenommen'),
        ).toBeVisible();
        await expect(ritaAntrag).toHaveCount(0);

        await petra.goto('/verwaltung/mitglieder?search=Rita');
        await expect(
            petra.getByRole('tab', { name: 'Aktiv', exact: true }),
        ).toHaveAttribute('aria-selected', 'true');
        const ritaMitglied = petra.getByRole('row', {
            name: /Rita Registriert/,
        });
        await expect(
            ritaMitglied.getByRole('cell', { name: '4711', exact: true }),
        ).toBeVisible();
        await expect(
            ritaMitglied.getByRole('cell', { name: 'Mitglied', exact: true }),
        ).toBeVisible();
        await beweis(
            petra,
            'nimmt Rita mit Mitgliedsnummer 4711 auf, sie steht bei den aktiven Mitgliedern',
            {
                zeige: ritaMitglied,
                belegt: ['A4'],
            },
        );

        wirdZu(rita, { ...RITA, rolle: 'Mitglied' });
        await einloggen(rita, RITA.email!, RITA_PASSWORT);
        await beweis(
            rita,
            'meldet sich nach der Aufnahme mit ihrem Passwort an',
            {
                zeige: rita.getByRole('heading', { name: 'Hallo, Rita' }),
                belegt: ['A4'],
            },
        );

        await rita.goto('/settings/security');
        await expect(rita).toHaveURL(/confirm-password/);
        await beweis(rita, 'muss vor der Kiosk-PIN ihr Passwort bestätigen', {
            zeige: rita.getByRole('heading', {
                name: 'Passwort bestätigen',
            }),
            belegt: ['A7'],
        });

        await rita.getByLabel('Passwort', { exact: true }).fill(RITA_PASSWORT);
        await rita.locator('[data-test="confirm-password-button"]').click();
        await expect(rita).toHaveURL(/settings\/security/);
        const pinStatus = rita.locator('[data-test="kiosk-pin-status"]');
        await expect(pinStatus).toHaveText('Noch keine Kiosk-PIN gesetzt.');
        await rita.locator('#kiosk_pin').fill('2468');
        await rita.locator('#kiosk_pin_confirmation').fill('2468');
        await rita.getByRole('button', { name: 'PIN speichern' }).click();
        await expect(pinStatus).toHaveText('Eine Kiosk-PIN ist gesetzt.');
        await expect(rita.locator('#kiosk_pin')).toHaveValue('');
        await beweis(rita, 'setzt ihre eigene Kiosk-PIN', {
            zeige: pinStatus,
            belegt: ['A7'],
        });

        await petra
            .getByRole('link', { name: 'Rita Registriert', exact: true })
            .click();
        await expect(petra.locator('#name')).toHaveValue('Rita Registriert');
        const ritaPin = petra.locator('section[aria-labelledby="kiosk-pin"]');
        await expect(ritaPin).toContainText('Gesetzt.');
        await beweis(petra, 'sieht bei Rita, dass eine Kiosk-PIN gesetzt ist', {
            zeige: ritaPin,
            belegt: ['A7'],
        });

        const anna = await oeffnen(browser, PERSONEN.anna);
        await anmeldenVersuchen(anna, PERSONEN.anna.email);
        const annaAbgewiesen = anna.getByText(
            /^Ihr Antrag liegt beim Vorstand\. Sie können sich anmelden/,
        );
        await expect(annaAbgewiesen).toBeVisible();
        await expect(anna).toHaveURL(/\/login$/);
        await beweis(
            anna,
            'wird mit offenem Antrag bei der Anmeldung mit Hinweis abgewiesen',
            {
                zeige: annaAbgewiesen,
                belegt: ['G4'],
            },
        );

        await petra.goto('/verwaltung/mitglieder?status=applicants');
        const annaAntrag = petra.getByRole('row', { name: /Anna Antrag/ });
        // Das Protokoll nennt den gelöschten Antrag nur mit seiner Nummer; die trägt der Knopf „Aufnehmen“.
        const annaNummer = (
            await annaAntrag
                .getByRole('button', { name: 'Aufnehmen' })
                .getAttribute('data-test')
        )?.replace('admit-', '');
        expect(annaNummer).toMatch(/^\d+$/);
        // „Ablehnen“ fragt im Browser-Dialog nach; Playwright lehnt Dialoge sonst ab.
        let rueckfrage = '';
        petra.once('dialog', (dialog) => {
            rueckfrage = dialog.message();
            void dialog.accept();
        });
        await annaAntrag.getByRole('button', { name: 'Ablehnen' }).click();
        await expect(annaAntrag).toHaveCount(0);
        expect(rueckfrage).toContain('Antrag von Anna Antrag ablehnen?');
        const keineAntraege = petra.getByText(
            'Keine offenen Mitgliedsanträge.',
        );
        await expect(keineAntraege).toBeVisible();
        await beweis(
            petra,
            'lehnt Annas Antrag ab, unter „Anträge“ liegt keiner mehr',
            {
                zeige: keineAntraege,
                belegt: ['A5'],
            },
        );

        await petra.goto('/verwaltung/protokoll');
        const geloescht = petra.getByRole('listitem').filter({
            hasText: `Mitgliedsantrag #${annaNummer} abgelehnt und gelöscht`,
        });
        await expect(geloescht).toContainText('Petra Vorstand');
        await beweis(
            petra,
            'sieht im Protokoll, dass Annas Antrag abgelehnt und gelöscht ist',
            {
                zeige: geloescht,
                belegt: ['A5'],
            },
        );

        // Mit der Ablehnung ist Annas Konto gelöscht, sie ist wieder Gast.
        wirdZu(anna, { ...PERSONEN.anna, rolle: 'Gast' });
        await anmeldenVersuchen(anna, PERSONEN.anna.email);
        const annaGesperrt = anna.getByText(
            'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.',
        );
        await expect(annaGesperrt).toBeVisible();
        await expect(anna).toHaveURL(/\/login$/);
        await beweis(
            anna,
            'bleibt nach der Ablehnung ausgesperrt, ihr Konto gibt es nicht mehr: nur die allgemeine Meldung „Diese Zugangsdaten stimmen nicht …“',
            {
                zeige: annaGesperrt,
                belegt: ['A5'],
            },
        );

        await petra.goto('/verwaltung/mitglieder/neu');
        await petra.locator('#name').fill(IDA.name);
        await petra.locator('#email').fill(IDA.email!);
        await petra
            .getByRole('button', { name: 'Anlegen und einladen' })
            .click();
        const einladung = petra
            .getByRole('status')
            .filter({ hasText: 'Einladungslink für Ida Eingeladen' });
        await expect(einladung).toBeVisible();
        const link = await petra.getByLabel('Einladungslink').inputValue();
        expect(link).toContain('/einladung/');
        const idaZeile = petra.getByRole('row', { name: /Ida Eingeladen/ });
        await expect(idaZeile).toContainText('Einladung offen');
        await beweis(
            petra,
            'legt Ida an und bekommt den Einladungslink zum Weitergeben',
            {
                zeige: einladung,
                belegt: ['A6'],
            },
        );
        await beweis(petra, 'sieht Ida mit offener Einladung in der Liste', {
            zeige: idaZeile,
            belegt: ['A6'],
        });

        const ida = await oeffnen(browser, IDA);
        const willkommen = await ida.goto(link);
        expect(willkommen?.status()).toBe(200);
        await beweis(ida, 'öffnet den Einladungslink am Handy', {
            zeige: ida.getByText('Hallo Ida Eingeladen!'),
            belegt: ['A6'],
        });

        await ida.locator('#password').fill(IDA_PASSWORT);
        await ida.locator('#password_confirmation').fill(IDA_PASSWORT);
        await ida
            .getByRole('button', { name: 'Passwort festlegen und anmelden' })
            .click();
        await expect(ida).toHaveURL(/dashboard/);
        await beweis(ida, 'setzt ihr Passwort und ist angemeldet', {
            zeige: ida.getByRole('heading', { name: 'Hallo, Ida' }),
            belegt: ['A6'],
        });

        await petra.reload();
        await expect(idaZeile).toBeVisible();
        await expect(idaZeile).not.toContainText('Einladung offen');
        await beweis(petra, 'sieht Idas Einladung als angenommen', {
            zeige: idaZeile,
            belegt: ['A6'],
        });

        // Ein zweites Gerät ohne Anmeldung, sonst leitet der Link nur zur Übersicht.
        const nochmal = await oeffnen(browser, { ...IDA, geraet: 'desktop' });
        const abgelaufen = await nochmal.goto(link);
        await expect(nochmal).toHaveURL(/\/login$/);
        expect(abgelaufen?.request().redirectedFrom()?.url()).toContain(
            '/einladung/',
        );
        await beweis(
            nochmal,
            'öffnet den Einladungslink am Rechner ein zweites Mal und wird abgewiesen',
            {
                zeige: nochmal.getByText(
                    'Dieser Einladungslink ist nicht mehr gültig.',
                ),
                belegt: ['A6'],
            },
        );

        // Zum Schluss die andere Hälfte der Grenze: ein deaktiviertes Mitglied.
        await petra.getByRole('link', { name: IDA.name, exact: true }).click();
        await expect(petra.locator('#name')).toHaveValue(IDA.name);
        await petra.getByRole('checkbox', { name: /Zugang aktiv/ }).click();
        await petra
            .getByRole('button', { name: 'Speichern', exact: true })
            .click();
        await expect(petra.getByText('Mitglied gespeichert.')).toBeVisible();
        await petra.goto('/verwaltung/mitglieder?status=inactive');
        const idaDeaktiviert = petra.getByRole('row', {
            name: /Ida Eingeladen/,
        });
        await expect(idaDeaktiviert).toContainText('Deaktiviert');
        await beweis(petra, 'deaktiviert Idas Zugang', {
            zeige: idaDeaktiviert,
        });

        await ida.reload();
        await expect(ida).toHaveURL(/\/login$/);
        const deaktiviert = ida.getByText('Ihr Zugang ist deaktiviert.');
        await expect(deaktiviert).toBeVisible();
        await beweis(
            ida,
            'wird aus der laufenden Sitzung abgemeldet, mit Hinweis auf den deaktivierten Zugang',
            {
                zeige: deaktiviert,
                belegt: ['G4'],
            },
        );

        await anmeldenVersuchen(ida, IDA.email!, IDA_PASSWORT);
        const idaAbgewiesen = ida.getByText(
            'Ihr Zugang ist deaktiviert. Bitte wenden Sie sich an den Vorstand.',
        );
        await expect(idaAbgewiesen).toBeVisible();
        await expect(ida).toHaveURL(/\/login$/);
        await beweis(
            ida,
            'kommt bei einer neuen Anmeldung mit ihrem Passwort nicht hinein und erfährt, dass ihr Zugang deaktiviert ist',
            {
                zeige: idaAbgewiesen,
                belegt: ['G4'],
            },
        );

        // Den Hinweis gibt es nur mit dem richtigen Passwort.
        await anmeldenVersuchen(ida, IDA.email!, 'falsches-passwort');
        const allgemein = ida.getByText(
            'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.',
        );
        await expect(allgemein).toBeVisible();
        await expect(idaAbgewiesen).toHaveCount(0);
        await beweis(
            ida,
            'bekommt mit falschem Passwort nur die allgemeine Meldung „Diese Zugangsdaten stimmen nicht …“',
            {
                zeige: allgemein,
                belegt: ['G4'],
            },
        );
    },
);
