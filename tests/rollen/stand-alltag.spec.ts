import {
    anmelden,
    beweis,
    expect,
    KIOSK,
    KIOSK_PIN,
    oeffnen,
    PERSONEN,
    sicherheitOeffnen,
    tag,
    test,
} from './beweis';
import { bahnBuchen, tagPlanen, tagSpeichern } from './planung';
import {
    aktivieren,
    erfassen,
    erfassungsformular,
    kioskLink,
    kioskListe,
    kioskPin,
    selbstEintragen,
    sessionStarten,
    teilnehmerZeile,
} from './stand';

/**
 * Der Abend liegt nach allen Terminen, die der DemoSeeder anlegt (mittwochs bis 21 Uhr,
 * samstags bis 14 Uhr), und nur auf der Bogenwiese. Mit ROLLEN_TERMIN_HEUTE belegt ein
 * Demo-Tag heute 00:00–23:59 die ersten drei Stände, Helga hat dort den ganzen Tag Aufsicht,
 * Max und Karl haben 22–23 Uhr, Erika und Lena 23–23:59 Uhr gebucht. Darum ist hier Jonas
 * die Aufsicht, und um 23 Uhr buchen nur Karl und Ben: Beides geht an jedem Wochentag.
 */
const STAND = 'Bogenwiese';
const TITEL = 'Abendschießen';
const BEGINN = '22:00';
const ENDE = '23:45';
const SLOT = '23:00';
const GRUND = 'Es waren 25 Schuss, nicht 20';

test(
    'Abend am Stand: Aufsicht vermerkt die Ankunft, Mitglieder tragen am Handy ein, Aufsicht lehnt ab, erfasst selbst und zeichnet ab',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Petra legt für heute ein Abendschießen auf der Bogenwiese an und teilt Jonas als Aufsicht ein, Karl und Ben buchen; Jonas aktiviert Karl bei Ankunft und vermerkt Ben als nicht erschienen. Erika und Karl tragen am eigenen Handy ein, Jonas lehnt Erikas Eintrag mit Grund ab, Karl zieht seinen Eintrag selbst zurück. Ein Gast ohne Konto kommt nur mit Haken „volljährig“ in die Session. Max hat keine Kiosk-PIN mehr und wird von Jonas erfasst, Lena trägt am Kiosk mit PIN ein; am Ende zeichnet Jonas ab.',
        },
    },
    async ({ browser }) => {
        // Petra plant den Abend und teilt Jonas als Aufsicht ein.
        const petra = await anmelden(browser, PERSONEN.petra);
        await tagPlanen(petra, {
            datum: tag(0).iso,
            von: BEGINN,
            bis: ENDE,
            titel: TITEL,
            stand: STAND,
        });
        await tagSpeichern(petra);

        await petra.goto('/schiesstage');
        await petra.getByRole('link', { name: new RegExp(TITEL) }).click();
        await expect(petra).toHaveURL(/\/schiesstage\/\d+$/);
        const tagUrl = petra.url();
        const tagId = /\/schiesstage\/(\d+)$/.exec(tagUrl)?.[1];
        await petra
            .getByRole('button', { name: 'Aufsicht übernehmen' })
            .click();
        await petra
            .locator('#sv-user')
            .selectOption({ label: 'Jonas Aufsicht' });
        const aufsichten = petra.locator(
            'section[aria-labelledby="supervision"]',
        );
        await aufsichten.getByRole('button', { name: 'Eintragen' }).click();
        await beweis(
            petra,
            `legt für heute das Abendschießen ${BEGINN}–${ENDE} Uhr auf der Bogenwiese an und teilt Jonas als Aufsicht ein`,
            {
                zeige: aufsichten
                    .getByRole('listitem')
                    .filter({ hasText: 'Jonas Aufsicht' }),
            },
        );

        // Karl und Ben buchen am Handy die letzte Stunde.
        const karl = await anmelden(browser, PERSONEN.karl);
        await karl.goto(tagUrl);
        const karlsBuchung = (await bahnBuchen(karl, STAND, SLOT))
            .getByRole('listitem')
            .filter({ hasText: `${SLOT}–${ENDE}` });
        await expect(karlsBuchung).toContainText(STAND);
        await beweis(
            karl,
            `bucht für heute eine Bahn auf der Bogenwiese, ${SLOT}–${ENDE} Uhr`,
            { zeige: karlsBuchung },
        );

        const ben = await anmelden(browser, PERSONEN.ben);
        await ben.goto(tagUrl);
        const bensBuchung = (await bahnBuchen(ben, STAND, SLOT))
            .getByRole('listitem')
            .filter({ hasText: `${SLOT}–${ENDE}` });
        await expect(bensBuchung).toContainText(STAND);

        // Jonas startet seine Session und sieht die Buchungen des Abends.
        const jonas = await anmelden(browser, PERSONEN.jonas);
        await sessionStarten(jonas);
        await jonas.goto(`/stand?tag=${tagId}`);
        await expect(jonas.getByText(`· ${TITEL} ·`)).toBeVisible();
        const bogenwiese = jonas.locator('section', {
            has: jonas.getByRole('heading', { name: STAND, exact: true }),
        });
        const karlGebucht = bogenwiese
            .getByRole('listitem')
            .filter({ hasText: 'Karl Kurzwaffe' });
        const benGebucht = bogenwiese
            .getByRole('listitem')
            .filter({ hasText: 'Ben Bogen' });
        await expect(karlGebucht).toContainText(`${SLOT}–${ENDE}`);
        await expect(benGebucht).toContainText(`${SLOT}–${ENDE}`);

        await karlGebucht.getByRole('button', { name: 'Aktivieren' }).click();
        await expect(karlGebucht).toContainText('aktiviert');
        await benGebucht
            .getByRole('button', { name: 'Ben Bogen nicht erschienen' })
            .click();
        await expect(benGebucht).toContainText('nicht erschienen');
        const zeile = (name: string) => teilnehmerZeile(jonas, name);
        await expect(zeile('Karl Kurzwaffe')).toContainText('aus Buchung');
        await expect(zeile('Ben Bogen')).toHaveCount(0);
        await beweis(
            jonas,
            'aktiviert den gebuchten Karl bei Ankunft und vermerkt Ben als „nicht erschienen“',
            { zeige: bogenwiese, belegt: ['S2'] },
        );

        await ben.reload();
        await expect(bensBuchung).toContainText('Nicht erschienen');
        await beweis(
            ben,
            'sieht an seiner Buchung den Status „Nicht erschienen“, den Jonas vermerkt hat',
            { zeige: bensBuchung, belegt: ['S2'] },
        );

        await karl.reload();
        await expect(karlsBuchung).toContainText('Aktiviert');
        await beweis(karl, 'sieht an seiner Buchung den Status „Aktiviert“', {
            zeige: karlsBuchung,
            belegt: ['S2'],
        });

        // Erika kommt ohne Buchung, Jonas aktiviert sie über die Suche.
        await aktivieren(jonas, 'Erika Musterfrau');
        const erika = await anmelden(browser, PERSONEN.erika);
        const heuteKarte = erika.locator(
            'section[aria-labelledby="today-card"]',
        );
        await expect(heuteKarte).toContainText('Aktiviert von Jonas Aufsicht');
        await beweis(
            erika,
            'sieht auf dem Handy „Heute schießen“, aktiviert von Jonas',
            { zeige: heuteKarte, belegt: ['S3'] },
        );

        await heuteKarte.getByRole('link', { name: 'Erfassen' }).click();
        await expect(erika).toHaveURL(/\/heute$/);
        const neuerEintrag = erika.getByRole('region', {
            name: 'Neuer Eintrag',
        });
        await expect(
            neuerEintrag.getByRole('button', { name: 'Langwaffe' }),
        ).toBeEnabled();
        await beweis(erika, 'hat das Formular zum Selbsteintrag offen', {
            zeige: neuerEintrag,
            belegt: ['S3'],
        });

        // Erika trägt am eigenen Handy ein, Jonas sieht den offenen Eintrag.
        await selbstEintragen(erika, {
            kategorie: 'Langwaffe',
            schuesse: '20',
        });
        const erikasEintraege = erika.locator(
            'section[aria-labelledby="own-entries"]',
        );
        const erikaErster = erikasEintraege
            .getByRole('listitem')
            .filter({ hasText: '20 Schüsse' });
        await expect(erikaErster).toContainText('offen');
        await beweis(
            erika,
            'trägt am eigenen Handy 20 Schuss Langwaffe ein, der Eintrag ist offen',
            { zeige: erikaErster, belegt: ['S4'] },
        );

        await jonas.reload();
        const jonasErikaErster = zeile('Erika Musterfrau')
            .locator('ul > li')
            .filter({ hasText: '20 Schüsse' });
        await expect(jonasErikaErster).toContainText(
            'Langwaffe · eigene · 20 Schüsse · erfasst über eigenes Gerät',
        );
        await expect(jonasErikaErster).toContainText('offen');
        // Zähler im Kopf der Standansicht: die Kachel aus Zahl und „offene Einträge“.
        const offeneEintraege = jonas
            .locator('div')
            .filter({ hasText: /^\s*\d+\s*offene Einträge\s*$/ });
        await expect(offeneEintraege).toHaveText(/^\s*1\s*offene Einträge\s*$/);
        await beweis(
            jonas,
            'sieht Erikas Eintrag „erfasst über eigenes Gerät“ offen in seiner Session',
            { zeige: zeile('Erika Musterfrau'), belegt: ['S4'] },
        );

        // Jonas lehnt mit Grund ab, Erika sieht den Grund und trägt neu ein.
        await jonasErikaErster
            .getByRole('button', { name: 'Ablehnen' })
            .click();
        await jonasErikaErster
            .getByLabel('Grund für die Ablehnung')
            .fill(GRUND);
        await jonasErikaErster
            .getByRole('button', { name: 'Ablehnen', exact: true })
            .click();
        await expect(jonasErikaErster).toContainText(`Grund: ${GRUND}`);
        await expect(jonasErikaErster).toContainText('abgelehnt');
        await beweis(jonas, `lehnt Erikas Eintrag ab: „${GRUND}“`, {
            zeige: jonasErikaErster,
            belegt: ['S9'],
        });

        await erika.reload();
        await expect(erikaErster).toContainText('abgelehnt');
        await expect(erikaErster).toContainText(
            `Abgelehnt: ${GRUND}. Bitte neu erfassen.`,
        );
        await beweis(erika, 'sieht die Ablehnung mit dem Grund von Jonas', {
            zeige: erikaErster,
            belegt: ['S9'],
        });

        await selbstEintragen(erika, {
            kategorie: 'Langwaffe',
            schuesse: '25',
        });
        const erikaNeu = erikasEintraege
            .getByRole('listitem')
            .filter({ hasText: '25 Schüsse' });
        await expect(erikasEintraege.getByRole('listitem')).toHaveCount(2);
        await expect(erikaNeu).toContainText('offen');
        await beweis(
            erika,
            'trägt nach der Ablehnung neu ein: 25 Schuss Langwaffe, offen',
            { zeige: erikasEintraege, belegt: ['S9'] },
        );

        // Karl ist über seine Buchung aktiviert, erfasst versehentlich Langwaffe und zieht zurück.
        await karl.goto('/heute');
        await selbstEintragen(karl, { kategorie: 'Langwaffe', schuesse: '30' });
        const karlsEintraege = karl.locator(
            'section[aria-labelledby="own-entries"]',
        );
        const karlVersehen = karlsEintraege
            .getByRole('listitem')
            .filter({ hasText: 'Langwaffe' });
        await expect(karlVersehen).toContainText('offen');
        await karlVersehen
            .getByRole('button', { name: 'Zurückziehen' })
            .click();
        await expect(karlVersehen).toContainText('zurückgezogen');
        await expect(
            karlVersehen.getByRole('button', { name: 'Zurückziehen' }),
        ).toHaveCount(0);
        await beweis(
            karl,
            'zieht seinen selbst erfassten Eintrag Langwaffe zurück, solange nicht abgezeichnet ist',
            { zeige: karlVersehen, belegt: ['S10'] },
        );

        await selbstEintragen(karl, { kategorie: 'Kurzwaffe', schuesse: '30' });
        const karlRichtig = karlsEintraege
            .getByRole('listitem')
            .filter({ hasText: 'Kurzwaffe' });
        await expect(karlRichtig).toContainText('offen');

        await jonas.reload();
        const jonasKarl = zeile('Karl Kurzwaffe');
        await expect(
            jonasKarl.locator('ul > li').filter({ hasText: 'Langwaffe' }),
        ).toContainText('zurückgezogen');
        await expect(
            jonasKarl.locator('ul > li').filter({ hasText: 'Kurzwaffe' }),
        ).toContainText('offen');
        await beweis(
            jonas,
            'sieht Karls Langwaffe als „zurückgezogen“ und seinen neuen Eintrag Kurzwaffe offen',
            { zeige: jonasKarl, belegt: ['S10'] },
        );

        // Ein Gast ohne Konto kommt nur mit Bestätigung „volljährig“ in die Session.
        await jonas
            .getByRole('button', { name: 'Eintragen', exact: true })
            .click();
        const formular = erfassungsformular(jonas);
        await formular.getByRole('tab', { name: 'Gast' }).click();
        await formular.getByLabel('Name des Gastes').fill('Gerd Gast');
        await formular
            .getByRole('button', { name: 'erlaubnisfrei', exact: true })
            .click();
        await formular.getByLabel('Schüsse (freiwillig)').fill('20');
        await formular
            .getByRole('button', { name: 'Erfassen', exact: true })
            .click();
        const ohneHaken = formular.getByText(
            'Bitte bestätigen, dass der Gast volljährig ist.',
        );
        await expect(ohneHaken).toBeVisible();
        await expect(zeile('Gerd Gast')).toHaveCount(0);
        await beweis(
            jonas,
            'will Gerd Gast ohne Haken „volljährig“ erfassen und wird abgewiesen',
            { zeige: ohneHaken, belegt: ['S8'] },
        );

        await formular
            .getByText('Gast ist volljährig (Ausweis gesehen)')
            .click();
        await formular
            .getByRole('button', { name: 'Erfassen', exact: true })
            .click();
        await expect(formular.getByRole('status')).toContainText('Gerd Gast');
        await formular.getByRole('button', { name: 'Schließen' }).click();
        const gast = zeile('Gerd Gast');
        await expect(gast.getByText('Gast', { exact: true })).toBeVisible();
        await expect(gast).toContainText(
            'erlaubnisfrei · 20 Schüsse · erfasst über Aufsicht',
        );
        await beweis(
            jonas,
            'bestätigt „Gast ist volljährig“ und hat Gerd Gast ohne Konto in der Session',
            { zeige: gast, belegt: ['S8'] },
        );

        // Max löscht seine Kiosk-PIN selbst; am Kiosk geht es dann nur über die Aufsicht.
        const max = await anmelden(browser, PERSONEN.max);
        await sicherheitOeffnen(max);
        const pinStatus = max.locator('[data-test="kiosk-pin-status"]');
        await expect(pinStatus).toHaveText('Eine Kiosk-PIN ist gesetzt.');
        await max.getByRole('button', { name: 'PIN löschen' }).click();
        await expect(pinStatus).toHaveText('Noch keine Kiosk-PIN gesetzt.');
        await beweis(
            max,
            'löscht in seinen Einstellungen die eigene Kiosk-PIN',
            { zeige: pinStatus },
        );

        await aktivieren(jonas, 'Max Mustermann');
        await aktivieren(jonas, 'Lena Langwaffe');
        const url = await kioskLink(jonas);
        const kiosk = await oeffnen(browser, KIOSK);
        await kiosk.goto(url);
        await expect(
            kiosk.getByRole('heading', { name: 'Selbst eintragen' }),
        ).toBeVisible();
        const maxAmKiosk = kioskListe(kiosk).getByRole('button', {
            name: /Max Mustermann/,
        });
        await expect(maxAmKiosk).toBeDisabled();
        await expect(maxAmKiosk).toContainText(
            'bitte bei der Aufsicht eintragen',
        );
        await expect(
            kioskListe(kiosk).getByRole('button', { name: /Lena Langwaffe/ }),
        ).toBeEnabled();
        await expect(
            kioskListe(kiosk).getByRole('button', { name: /Gerd Gast/ }),
        ).toHaveCount(0);
        await beweis(
            kiosk,
            'zeigt Max ohne PIN gesperrt: „bitte bei der Aufsicht eintragen“; der Gast fehlt in der Liste',
            { zeige: maxAmKiosk, belegt: ['G5'] },
        );

        await kioskPin(kiosk, 'Lena Langwaffe', '9876');
        const pinFalsch = kiosk.getByText('PIN falsch.');
        await expect(pinFalsch).toBeVisible();
        await expect(kiosk.getByText('Ihr Eintrag für heute')).toHaveCount(0);
        await beweis(kiosk, 'weist Lenas falsche PIN ab: „PIN falsch.“', {
            zeige: pinFalsch,
            belegt: ['G5'],
        });

        // Lena bleibt gewählt, nur die PIN wird neu eingegeben.
        for (const ziffer of KIOSK_PIN) {
            await kiosk
                .getByRole('group', { name: 'Zifferntastatur' })
                .getByRole('button', { name: ziffer, exact: true })
                .click();
        }

        await kiosk.getByRole('button', { name: 'Weiter' }).click();
        await expect(kiosk.getByText('Ihr Eintrag für heute')).toBeVisible();
        await kiosk.getByRole('button', { name: 'Kurzwaffe' }).click();
        await kiosk.getByRole('button', { name: 'Vereinswaffe' }).click();
        await kiosk.getByLabel('Schüsse').fill('40');
        await kiosk.getByRole('button', { name: 'Erfassen' }).click();
        await beweis(
            kiosk,
            'nimmt Lenas richtige PIN an und bestätigt ihren Eintrag',
            {
                zeige: kiosk.locator('[role="status"]', {
                    hasText: 'Lena Langwaffe: erfasst um',
                }),
            },
        );

        // Max kann nicht selbst eintragen, Jonas erfasst für ihn.
        await jonas.reload();
        await erfassen(jonas, 'Max Mustermann', {
            kategorie: 'Kurzwaffe',
            waffe: 'eigene',
            schuesse: '30',
        });
        await expect(erfassungsformular(jonas)).toHaveCount(0);
        const jonasMax = zeile('Max Mustermann');
        await expect(jonasMax.locator('ul > li')).toContainText(
            'Kurzwaffe · eigene · 30 Schüsse · erfasst über Aufsicht',
        );
        await beweis(jonas, 'erfasst selbst 30 Schuss Kurzwaffe für Max', {
            zeige: jonasMax,
            belegt: ['S7'],
        });

        await max.goto('/heute');
        const maxEintrag = max.locator(
            'section[aria-labelledby="own-entries"] li',
        );
        await expect(maxEintrag).toContainText(
            'Kurzwaffe · eigene · 30 Schüsse · über Aufsicht',
        );
        await expect(maxEintrag).toContainText('offen');
        await expect(
            maxEintrag.getByRole('button', { name: 'Zurückziehen' }),
        ).toHaveCount(0);
        await beweis(
            max,
            'sieht auf dem Handy den Eintrag, den Jonas für ihn erfasst hat',
            { zeige: maxEintrag, belegt: ['S7'] },
        );

        // Jonas zeichnet ab: Abgelehntes und Zurückgezogenes kommt nicht mit.
        await jonas.reload();
        await jonas.getByRole('button', { name: 'Abzeichnen' }).click();
        const abzeichnen = jonas.locator(
            'section[aria-labelledby="sign-off-title"]',
        );
        await expect(abzeichnen).toContainText(
            /unter meiner Aufsicht geschossen/,
        );
        const posten = abzeichnen.getByRole('listitem');
        await expect(posten).toHaveCount(5);
        await expect(
            posten.filter({ hasText: 'Erika Musterfrau' }),
        ).toHaveCount(1);
        await expect(
            posten.filter({ hasText: 'Erika Musterfrau' }),
        ).toContainText('Langwaffe · eigene · 25 Schüsse');
        await expect(posten.filter({ hasText: 'Karl Kurzwaffe' })).toHaveCount(
            1,
        );
        await expect(
            posten.filter({ hasText: 'Karl Kurzwaffe' }),
        ).toContainText('Kurzwaffe · eigene · 30 Schüsse');
        await beweis(
            jonas,
            'prüft vor dem Abzeichnen die fünf offenen Einträge, ohne Abgelehntes und Zurückgezogenes',
            { zeige: abzeichnen },
        );

        await jonas
            .getByRole('button', { name: 'Bestätigen und abzeichnen' })
            .click();
        const abgezeichnet = jonas.locator('section[aria-labelledby="signed"]');
        await expect(abgezeichnet).toContainText('5 Einträge');
        await beweis(
            jonas,
            'zeichnet die Session ab, fünf Einträge sind fest',
            { zeige: abgezeichnet, belegt: ['S11'] },
        );

        await kiosk.reload();
        await beweis(kiosk, 'zeigt nach dem Abzeichnen „Session beendet“', {
            zeige: kiosk.getByRole('heading', { name: 'Session beendet' }),
            belegt: ['S11'],
        });

        await karl.reload();
        await expect(
            karl.getByText('Die Session ist abgezeichnet.', { exact: false }),
        ).toBeVisible();
        await expect(karlRichtig).toContainText('abgezeichnet');
        await expect(
            karlsEintraege.getByRole('button', { name: 'Zurückziehen' }),
        ).toHaveCount(0);
        await beweis(
            karl,
            'nach dem Abzeichnen ist sein Eintrag fest, Zurückziehen gibt es nicht mehr',
            { zeige: karlsEintraege, belegt: ['S10', 'S11'] },
        );
    },
);
