import {
    anmelden,
    beweis,
    expect,
    heute,
    KIOSK,
    KIOSK_PIN,
    laden,
    oeffnen,
    pdfText,
    PERSONEN,
    test,
} from './beweis';
import {
    abzeichnen,
    aktivieren,
    kioskLink,
    kioskListe,
    kioskPin,
    sessionStarten,
    teilnehmer,
} from './stand';

test(
    'Trainingsabend am Kiosk: Aufsicht gibt frei, Mitglied trägt mit PIN ein, Aufsicht zeichnet ab',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Helga hat Aufsicht und stellt das Tablet als Kiosk auf. Max trägt seine Schüsse dort selbst mit PIN ein, Helga sieht den Eintrag und zeichnet am Ende ab. Danach steht der Besuch in Max’ Schießkladde.',
        },
    },
    async ({ browser }) => {
        const helga = await anmelden(browser, PERSONEN.helga);
        await sessionStarten(helga);
        await beweis(helga, 'startet die Session am Stand', {
            zeige: helga.getByText(/Session seit/),
            belegt: ['S1'],
        });

        await aktivieren(helga, 'Max Mustermann');
        await aktivieren(helga, 'Erika Musterfrau');
        const bereich = teilnehmer(helga);
        await beweis(helga, 'hat Max und Erika für die Session aktiviert', {
            zeige: bereich,
        });

        const url = await kioskLink(helga);
        await beweis(
            helga,
            'gibt den Kiosk frei und holt den Link für das Tablet',
            {
                zeige: helga.locator('[data-test="kiosk-url"]'),
                belegt: ['S5'],
            },
        );

        const kiosk = await oeffnen(browser, KIOSK);
        await kiosk.goto(url);
        const liste = kiosk.getByRole('heading', { name: 'Selbst eintragen' });
        await expect(liste).toBeVisible();
        await expect(kiosk.getByRole('navigation')).toHaveCount(0);
        const personen = kioskListe(kiosk).getByRole('button');
        await expect(personen).toHaveCount(2);
        await expect(
            personen.filter({ hasText: 'Max Mustermann' }),
        ).toBeVisible();
        await expect(
            personen.filter({ hasText: 'Erika Musterfrau' }),
        ).toBeVisible();
        await beweis(
            kiosk,
            'zeigt ohne Anmeldung nur die aktivierten Mitglieder Max und Erika',
            { zeige: kioskListe(kiosk), belegt: ['S5'] },
        );

        await kioskPin(kiosk, 'Max Mustermann', KIOSK_PIN);
        await expect(kiosk.getByText('Ihr Eintrag für heute')).toBeVisible();
        await kiosk.getByRole('button', { name: 'Kurzwaffe' }).click();
        await kiosk.getByRole('button', { name: 'Vereinswaffe' }).click();
        await kiosk.getByLabel('Schüsse').fill('30');
        await beweis(
            kiosk,
            'Max hat sich mit PIN angemeldet und trägt 30 Schuss Kurzwaffe ein',
            {
                zeige: kiosk.getByLabel('Schüsse'),
                belegt: ['S6'],
            },
        );

        await kiosk.getByRole('button', { name: 'Erfassen' }).click();
        await beweis(kiosk, 'bestätigt den Eintrag von Max', {
            zeige: kiosk.locator('[role="status"]', {
                hasText: 'Max Mustermann: erfasst um',
            }),
            belegt: ['S6'],
        });

        await helga.reload();
        await expect(bereich).toContainText('erfasst über Kiosk');
        await beweis(
            helga,
            'sieht den Eintrag von Max mit dem Vermerk „über Kiosk“',
            {
                zeige: bereich.getByText('erfasst über Kiosk'),
                belegt: ['S6'],
            },
        );

        await abzeichnen(helga);
        await beweis(helga, 'zeichnet die Session ab', {
            zeige: helga.getByRole('heading', { name: 'Heute abgezeichnet' }),
            belegt: ['S11'],
        });

        await kiosk.reload();
        await beweis(kiosk, 'zeigt nach dem Abzeichnen „Session beendet“', {
            zeige: kiosk.getByRole('heading', { name: 'Session beendet' }),
            belegt: ['S11'],
        });

        const max = await anmelden(browser, PERSONEN.max);
        await max.goto('/schiesskladde');
        await expect(
            max.getByRole('heading', {
                name: 'Meine Einträge in der Schießkladde',
            }),
        ).toBeVisible();
        const besuch = max.getByRole('row', {
            name: new RegExp(`${heute()}.*Kurzwaffe.*Verein.*30`),
        });
        // Am Handy liegt die Spalte Schüsse rechts außerhalb; die Zelle wird ins Bild geholt.
        await beweis(
            max,
            'findet den abgezeichneten Besuch mit 30 Schuss in seiner Schießkladde',
            {
                zeige: besuch.getByRole('cell', { name: '30', exact: true }),
                belegt: ['K1'],
            },
        );

        const auszug = await laden(
            max,
            max.getByRole('link', { name: /Auszug zur Information/ }),
        );
        expect(auszug.suggestedFilename()).toMatch(
            /^Schiesskladde-max-mustermann-.*\.pdf$/,
        );
        expect(await pdfText(auszug)).toContain(
            `${heute()} Kurzwaffe Verein – 30 Kiosk Helga Schießwart`,
        );
        await beweis(
            max,
            'lädt den Auszug seiner Schießkladde als PDF, mit dem Besuch von heute',
            {
                zeige: max.getByRole('link', {
                    name: /Auszug zur Information/,
                }),
                belegt: ['K1'],
            },
        );
    },
);
