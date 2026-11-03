import type { Locator, Page } from '@playwright/test';
import { anmelden, beweis, expect, PERSONEN, tag, test } from './beweis';
import { bahnBuchen, bahnZelle, tagPlanen, tagSpeichern } from './planung';

const STAND = 'Stand Ost';
const ABEND = 'Abendtraining Ost';
const NACHMITTAG = 'Vereinsnachmittag';
const GRUND = 'Wartung der Bahnanlage';

/** Zeile eines Termins in der Planungsliste des Vorstands. */
function zeile(petra: Page, titel: string): Locator {
    return petra.getByRole('listitem').filter({ hasText: titel });
}

test(
    'Planung und Buchung: Vorstand plant am neuen Stand, Aufsicht trägt sich ein, Mitglieder buchen, stornieren und sehen die Absage',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Petra legt den neuen Stand Ost an und plant darauf für heute Abend einen Schießtag. Jonas übernimmt die Aufsicht, Ben bucht, Petra sieht die Buchung in der Planung und Jonas am Stand. Am Vereinsnachmittag nächste Woche bucht und storniert Erika. Max bucht dort ebenfalls, sieht Petras Absage und seine stornierte Buchung auf dem Handy; nachdem Petra den Tag wieder öffnet, bleibt seine Buchung storniert.',
        },
    },
    async ({ browser }) => {
        const petra = await anmelden(browser, PERSONEN.petra);
        await petra.goto('/verwaltung/staende');
        await petra.getByRole('button', { name: 'Stand anlegen' }).click();
        await petra.locator('#range-name').fill(STAND);
        await petra.locator('#range-lanes').fill('4');
        await petra
            .getByRole('checkbox', { name: 'Langwaffe', exact: true })
            .click();
        await petra.getByRole('button', { name: 'Speichern' }).click();
        const stand = petra.getByRole('listitem').filter({ hasText: STAND });
        await expect(stand).toContainText(/4\s*Bahnen/);
        await expect(stand).toContainText('Langwaffe');
        await beweis(
            petra,
            'legt den neuen Stand Ost mit 4 Bahnen für Langwaffe an',
            { zeige: stand, belegt: ['P6'] },
        );

        // Nur Stand Ost ist geöffnet und neu: Der Abend teilt sich mit keinem Demo-Termin eine Bahn.
        const staende = await tagPlanen(petra, {
            datum: tag(0).iso,
            von: '21:00',
            bis: '23:59',
            titel: ABEND,
            stand: STAND,
        });
        await beweis(
            petra,
            'findet Stand Ost bei der Planung zur Wahl und öffnet nur ihn für den Schießtag heute Abend',
            { zeige: staende, belegt: ['P6', 'P1'] },
        );

        await tagSpeichern(petra);
        const abend = zeile(petra, ABEND);
        await expect(abend).toContainText('21:00–23:59 Uhr');
        await expect(abend).toContainText(STAND);
        await expect(abend).toContainText('keine Aufsicht');
        await expect(abend).toContainText('0 Buchungen');
        await beweis(
            petra,
            'sieht den neuen Schießtag in der Planung, noch ohne Aufsicht und ohne Buchung',
            { zeige: abend, belegt: ['P1'] },
        );

        // Ben, weil er heute sicher keine andere Buchung hat: Eine zweite Buchung zur selben Zeit
        // lehnt die App ab, und an einem Demo-Termin bis Mitternacht sind um 23:00 schon andere gebucht.
        const ben = await anmelden(browser, PERSONEN.ben);
        await ben.goto('/schiesstage');
        const karte = ben.getByRole('link', { name: new RegExp(ABEND) });
        await expect(karte).toContainText('Heute');
        await expect(karte).toContainText('Noch keine Standaufsicht');
        await beweis(
            ben,
            'sieht den Schießtag heute Abend unter „Schießtage“, noch ohne Standaufsicht',
            { zeige: karte, belegt: ['P1'] },
        );

        const jonas = await anmelden(browser, PERSONEN.jonas);
        await jonas.goto('/schiesstage');
        await jonas.getByRole('link', { name: new RegExp(ABEND) }).click();
        await expect(jonas).toHaveURL(/schiesstage\/\d+$/);
        const tagId = new URL(jonas.url()).pathname.split('/').pop();
        await jonas
            .getByRole('button', { name: 'Aufsicht übernehmen' })
            .click();
        const aufsicht = jonas.locator(
            'section[aria-labelledby="supervision"]',
        );
        await aufsicht.getByRole('button', { name: 'Eintragen' }).click();
        const eintrag = aufsicht
            .getByRole('listitem')
            .filter({ hasText: 'Jonas Aufsicht' });
        await expect(eintrag).toContainText('21:00–23:59 Uhr · ganze Anlage');
        await beweis(
            jonas,
            'trägt sich für den ganzen Abend als Standaufsicht ein',
            { zeige: eintrag, belegt: ['P2'] },
        );

        await petra.reload();
        await expect(abend).toContainText('Jonas Aufsicht');
        await expect(abend).not.toContainText('keine Aufsicht');
        await beweis(petra, 'sieht Jonas als Aufsicht in ihrer Planung', {
            zeige: abend,
            belegt: ['P2'],
        });

        await ben.reload();
        await expect(karte).toContainText('Aufsicht: Jonas Aufsicht');
        await beweis(ben, 'sieht, dass Jonas heute Abend Aufsicht hat', {
            zeige: karte,
            belegt: ['P2'],
        });

        // 23:00–23:59 bleibt bis zur Nachtpause buchbar, frühere Stunden wären am späten Abend vorbei.
        await karte.click();
        await expect(bahnZelle(ben, STAND, '23:00')).toHaveAttribute(
            'aria-label',
            `${STAND}, 23:00 Uhr: 4 von 4 Bahnen frei`,
        );
        const bensBuchung = await bahnBuchen(ben, STAND, '23:00');
        await expect(bensBuchung).toContainText('23:00–23:59');
        await expect(bensBuchung).toContainText(STAND);
        await expect(bensBuchung).toContainText('Langwaffe');
        await beweis(ben, 'bucht eine Bahn auf Stand Ost ab 23:00 Uhr', {
            zeige: bensBuchung,
            belegt: ['P3'],
        });

        await ben.goto('/schiesstage');
        await expect(karte.getByText('Gebucht', { exact: true })).toBeVisible();
        await beweis(ben, 'sieht den Abend in seiner Liste als „Gebucht“', {
            zeige: karte,
        });

        await petra.reload();
        await expect(
            abend.getByText('1 Buchung', { exact: true }),
        ).toBeVisible();
        await beweis(
            petra,
            'sieht in der Planung, dass der Abend jetzt eine Buchung hat',
            { zeige: abend, belegt: ['P3'] },
        );

        const amStand = await jonas.goto(`/stand?tag=${tagId}`);
        expect(amStand?.status()).toBe(200);
        await expect(
            jonas.getByText(new RegExp(`21:00–23:59 Uhr · ${ABEND}`)),
        ).toBeVisible();
        const benAmStand = jonas
            .locator('section')
            .filter({ has: jonas.getByRole('heading', { name: STAND }) })
            .getByRole('listitem')
            .filter({ hasText: 'Ben Bogen' });
        await expect(benAmStand).toContainText('23:00–23:59');
        await expect(benAmStand).toContainText('Langwaffe');
        await beweis(
            jonas,
            'sieht am Stand Bens Buchung für 23:00 Uhr auf Stand Ost',
            { zeige: benAmStand, belegt: ['P3'] },
        );

        // Stornieren geht nur bis 60 Minuten vor Beginn, dafür braucht es einen Termin in der Zukunft.
        const naechsteWoche = tag(7);
        await tagPlanen(petra, {
            datum: naechsteWoche.iso,
            von: '14:00',
            bis: '16:00',
            titel: NACHMITTAG,
            stand: STAND,
        });
        await tagSpeichern(petra);
        const nachmittag = zeile(petra, NACHMITTAG);
        await expect(nachmittag).toContainText('14:00–16:00 Uhr');
        await expect(nachmittag).toContainText('0 Buchungen');
        await beweis(
            petra,
            'plant für nächste Woche einen Vereinsnachmittag auf Stand Ost',
            { zeige: nachmittag, belegt: ['P1'] },
        );

        await nachmittag.getByRole('link', { name: 'Tagesansicht' }).click();
        await expect(petra).toHaveURL(/schiesstage\/\d+$/);
        const nachmittagUrl = petra.url();
        await petra
            .getByRole('button', { name: 'Aufsicht übernehmen' })
            .click();
        await expect(petra.locator('#sv-user')).toHaveValue('');
        const petrasAufsicht = petra.locator(
            'section[aria-labelledby="supervision"]',
        );
        await petrasAufsicht.getByRole('button', { name: 'Eintragen' }).click();
        const petraEintrag = petrasAufsicht
            .getByRole('listitem')
            .filter({ hasText: 'Petra Vorstand' });
        await expect(petraEintrag).toContainText('14:00–16:00 Uhr');
        await beweis(
            petra,
            'übernimmt die Aufsicht am Vereinsnachmittag selbst',
            { zeige: petraEintrag },
        );

        const erika = await anmelden(browser, PERSONEN.erika);
        await erika.goto('/schiesstage');
        await erika.getByRole('link', { name: new RegExp(NACHMITTAG) }).click();
        const erikasBuchung = await bahnBuchen(erika, STAND, '14:00');
        await expect(erikasBuchung).toContainText('14:00–15:00');
        await expect(bahnZelle(erika, STAND, '14:00')).toHaveAttribute(
            'aria-label',
            `${STAND}, 14:00 Uhr: 3 von 4 Bahnen frei`,
        );
        await beweis(
            erika,
            'bucht am Vereinsnachmittag eine Bahn ab 14:00 Uhr',
            {
                zeige: erikasBuchung,
                belegt: ['P4'],
            },
        );

        await petra.goto('/verwaltung/schiesstage');
        await expect(
            nachmittag.getByText('1 Buchung', { exact: true }),
        ).toBeVisible();
        await beweis(petra, 'sieht Erikas Buchung am Vereinsnachmittag', {
            zeige: nachmittag,
            belegt: ['P4'],
        });

        await erikasBuchung.getByRole('button', { name: 'Stornieren' }).click();
        await expect(erikasBuchung).toHaveCount(0);
        await expect(bahnZelle(erika, STAND, '14:00')).toHaveAttribute(
            'aria-label',
            `${STAND}, 14:00 Uhr: 4 von 4 Bahnen frei`,
        );
        await beweis(
            erika,
            'storniert ihre Buchung, um 14:00 Uhr sind wieder alle 4 Bahnen frei',
            { zeige: bahnZelle(erika, STAND, '14:00'), belegt: ['P4'] },
        );

        await petra.reload();
        await expect(
            nachmittag.getByText('0 Buchungen', { exact: true }),
        ).toBeVisible();
        await beweis(
            petra,
            'sieht nach Erikas Storno wieder 0 Buchungen am Vereinsnachmittag',
            { zeige: nachmittag, belegt: ['P4'] },
        );

        await petra.goto(nachmittagUrl);
        await expect(bahnZelle(petra, STAND, '14:00')).toHaveAttribute(
            'aria-label',
            `${STAND}, 14:00 Uhr: 4 von 4 Bahnen frei`,
        );
        await beweis(
            petra,
            'sieht in der Tagesansicht die Bahn um 14:00 Uhr wieder frei',
            { zeige: bahnZelle(petra, STAND, '14:00'), belegt: ['P4'] },
        );

        const max = await anmelden(browser, PERSONEN.max);
        await max.goto('/schiesstage');
        const maxKarte = max.getByRole('link', {
            name: new RegExp(NACHMITTAG),
        });
        await maxKarte.click();
        const maxBuchung = await bahnBuchen(max, STAND, '14:00');
        await beweis(max, 'bucht am Vereinsnachmittag eine Bahn ab 14:00 Uhr', {
            zeige: maxBuchung,
        });

        await petra.goto('/verwaltung/schiesstage');
        await expect(
            nachmittag.getByText('1 Buchung', { exact: true }),
        ).toBeVisible();
        await nachmittag.getByRole('button', { name: 'Absagen' }).click();
        await nachmittag.getByLabel('Grund der Absage').fill(GRUND);
        await nachmittag.getByRole('button', { name: 'Absagen' }).click();
        await expect(
            petra.getByText(
                'Schießtag abgesagt, eine Buchung storniert. Der Mailversand ist nicht eingerichtet, niemand wurde benachrichtigt.',
            ),
        ).toBeVisible();
        await expect(nachmittag).toContainText(`Abgesagt: ${GRUND}`);
        await expect(
            nachmittag.getByRole('button', { name: 'Wieder öffnen' }),
        ).toBeVisible();
        await beweis(
            petra,
            'sagt den Vereinsnachmittag wegen Wartung ab, die Planung zeigt ihn als abgesagt mit Grund',
            { zeige: nachmittag, belegt: ['P5'] },
        );

        await max.goto('/schiesstage');
        await expect(maxKarte).toContainText(`Abgesagt: ${GRUND}`);
        await beweis(
            max,
            'sieht auf dem Handy, dass der Vereinsnachmittag abgesagt ist, mit Grund',
            { zeige: maxKarte, belegt: ['P5'] },
        );

        await max.goto('/buchungen');
        const storniert = max
            .getByRole('listitem')
            .filter({ hasText: STAND })
            .filter({ hasText: naechsteWoche.anzeige });
        await expect(storniert).toContainText('14:00–15:00');
        await expect(storniert).toContainText('Storniert');
        await beweis(
            max,
            'findet seine Buchung für den Vereinsnachmittag nach der Absage als „Storniert“',
            { zeige: storniert, belegt: ['P5'] },
        );

        await nachmittag.getByRole('button', { name: 'Wieder öffnen' }).click();
        await expect(nachmittag).not.toContainText('Abgesagt');
        await expect(nachmittag).toContainText('Petra Vorstand');
        await expect(
            nachmittag.getByText('0 Buchungen', { exact: true }),
        ).toBeVisible();
        await beweis(
            petra,
            'öffnet den Vereinsnachmittag wieder, Max’ Buchung bleibt storniert: 0 Buchungen',
            { zeige: nachmittag, belegt: ['P5'] },
        );

        await max.goto('/schiesstage');
        await expect(maxKarte).not.toContainText('Abgesagt');
        await expect(maxKarte).toContainText('Aufsicht: Petra Vorstand');
        await expect(maxKarte).toContainText('0 Buchungen');
        await expect(maxKarte).not.toContainText('Gebucht');
        await beweis(
            max,
            'sieht den Vereinsnachmittag wieder offen, seine Buchung bleibt storniert: 0 Buchungen, kein „Gebucht“',
            { zeige: maxKarte, belegt: ['P5'] },
        );

        await max.goto('/buchungen');
        await expect(storniert).toContainText('14:00–15:00');
        await expect(storniert).toContainText('Storniert');
        await beweis(
            max,
            'findet seine Buchung nach dem Wiederöffnen weiter als „Storniert“',
            { zeige: storniert, belegt: ['P5'] },
        );
    },
);
