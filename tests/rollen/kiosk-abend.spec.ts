import {
    anmelden,
    beweis,
    expect,
    heute,
    KIOSK,
    KIOSK_PIN,
    oeffnen,
    PERSONEN,
    test,
} from './beweis';
import {
    abzeichnen,
    aktivieren,
    kioskLink,
    kioskPin,
    sessionStarten,
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
        const teilnehmer = helga.locator(
            'section[aria-labelledby="participants"]',
        );
        await beweis(helga, 'hat Max und Erika für die Session aktiviert', {
            zeige: teilnehmer,
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
        await expect(
            kiosk.getByRole('button', { name: /Karl Kurzwaffe/ }),
        ).toHaveCount(0);
        await beweis(
            kiosk,
            'zeigt ohne Anmeldung nur die aktivierten Mitglieder Max und Erika',
            {
                zeige: kiosk.getByRole('button', { name: /Max Mustermann/ }),
                belegt: ['S5'],
            },
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
        await expect(teilnehmer).toContainText('erfasst über Kiosk');
        await beweis(
            helga,
            'sieht den Eintrag von Max mit dem Vermerk „über Kiosk“',
            {
                zeige: teilnehmer.getByText('erfasst über Kiosk'),
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
        await beweis(
            max,
            'findet den abgezeichneten Besuch mit 30 Schuss in seiner Schießkladde',
            {
                zeige: max.getByRole('row', {
                    name: new RegExp(`${heute()}.*Kurzwaffe.*Verein.*30`),
                }),
                belegt: ['K1'],
            },
        );
    },
);
