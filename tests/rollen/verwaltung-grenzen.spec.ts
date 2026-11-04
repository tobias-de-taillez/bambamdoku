import type { Page } from '@playwright/test';
import {
    anmelden,
    anmeldenVersuchen,
    beweis,
    expect,
    jahr,
    KIOSK,
    KIOSK_PIN,
    laden,
    oeffnen,
    pdfText,
    PERSONEN,
    tag,
    test,
    wirdZu,
    type Person,
} from './beweis';
import { bahnZelle, tagPlanen, tagSpeichern } from './planung';
import {
    aktivieren,
    erfassungsformular,
    kioskLink,
    kioskPin,
    sessionStarten,
    teilnehmerZeile,
} from './stand';

const KEIN_REGISTER =
    'Eine Session starten dürfen nur Personen mit gültiger Zeile im Aufsichtsregister';

/** Ben nach dem Eintrag im Aufsichtsregister. */
const BEN_AUFSICHT: Person = { ...PERSONEN.ben, rolle: 'Aufsicht' };

// Petras eigener Termin für die Grenze bei der Planung; der Titel macht ihn auffindbar.
const KURZ_STAND = '25 m Kurzwaffe';
const KURZ_TAG = 'Frühschießen Kurzwaffe';

async function mitgliedOeffnen(petra: Page, name: string): Promise<void> {
    await petra.goto('/verwaltung/mitglieder');
    await petra.getByRole('link', { name, exact: true }).click();
    await expect(petra.locator('#name')).toHaveValue(name);
}

test(
    'Grenzen der Rollen: Mitglied ohne Verwaltung und Register, Vorstand vergibt und entzieht die Aufsicht, löscht eine Kiosk-PIN',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Max ist Mitglied ohne Registereintrag: Er kann am Stand keine Session starten und kommt nicht in die Verwaltung. Petra trägt Ben nur für erlaubnisfreie Waffen ins Aufsichtsregister ein; Ben führt eine Session, in der Kurz- und Langwaffe gesperrt sind. Petra löscht Karls Kiosk-PIN, sodass Ben ihn am Stand erfassen muss. Bei der Planung kann Ben die Aufsicht für einen Kurzwaffenstand nicht übernehmen, Max kann dort nicht buchen. Am Ende entzieht Petra Ben die Registrierung, während Max am Kiosk seinen Eintrag ausfüllt: Der Kiosk weist ihn ab und nimmt keine Einträge mehr an, Bens Session ist gesperrt, und Max wird auch am eigenen Handy abgewiesen.',
        },
    },
    async ({ browser }) => {
        const max = await anmelden(browser, PERSONEN.max);
        await max.goto('/stand');
        const ohneRegister = max.getByText(KEIN_REGISTER);
        await expect(ohneRegister).toBeVisible();
        await expect(
            max.getByRole('button', { name: 'Session starten' }),
        ).toHaveCount(0);
        await expect(
            max.getByRole('button', { name: 'Eintragen' }),
        ).toHaveCount(0);
        await beweis(
            max,
            'kann am Stand ohne Registereintrag keine Session starten',
            {
                zeige: ohneRegister,
                belegt: ['G3'],
            },
        );

        const mitglieder = await max.goto('/verwaltung/mitglieder');
        expect(mitglieder?.status()).toBe(403);
        await expect(max.getByText('Fehler 403')).toBeVisible();
        await beweis(
            max,
            'wird bei der Mitgliederliste der Verwaltung abgewiesen (403)',
            {
                zeige: max.getByRole('heading', { name: 'Kein Zugriff' }),
                belegt: ['G2'],
            },
        );

        const kladde = await max.goto('/verwaltung/schiesskladde');
        expect(kladde?.status()).toBe(403);
        await beweis(
            max,
            'wird auch bei der Kladde des Vereins abgewiesen (403)',
            {
                zeige: max.getByRole('heading', { name: 'Kein Zugriff' }),
                belegt: ['G2'],
            },
        );

        // /verwaltung selbst ist keine Seite (404), auch für Petra nicht; kein Beleg für G2.
        const wurzel = await max.goto('/verwaltung');
        expect(wurzel?.status()).toBe(404);

        await max.goto('/dashboard');
        await max
            .getByRole('button', { name: 'Seitenleiste ein-/ausblenden' })
            .click();
        const menue = max.locator('[data-slot="sidebar"][data-mobile="true"]');
        await expect(menue).toBeInViewport({ ratio: 1 });
        await expect(
            menue.getByRole('link', { name: 'Schießkladde' }),
        ).toBeVisible();
        await expect(menue.getByText('Verwaltung')).toHaveCount(0);
        await expect(
            menue.getByRole('link', { name: 'Mitglieder' }),
        ).toHaveCount(0);
        await expect(menue.getByRole('link', { name: 'Am Stand' })).toHaveCount(
            0,
        );
        await beweis(max, 'findet in seinem Menü keinen Punkt „Verwaltung“', {
            zeige: menue.locator('[data-slot="sidebar-content"]'),
            belegt: ['G2'],
        });

        const petra = await anmelden(browser, PERSONEN.petra);
        expect((await petra.goto('/verwaltung'))?.status()).toBe(404);

        await mitgliedOeffnen(petra, 'Ben Bogen');
        const register = petra.locator('section[aria-labelledby="register"]');
        await expect(register).toContainText('Nicht im Register.');
        const bis = jahr() + 1;
        await petra.getByRole('button', { name: 'Prüfung eintragen' }).click();
        await petra.locator('#reg-scope').selectOption('exempt');
        await petra.locator('#reg-until').fill(`${bis}-12-31`);
        await petra
            .locator('#reg-note')
            .fill('Lehrgang Standaufsicht, ohne Waffensachkunde');
        await petra.getByRole('checkbox', { name: /Ich habe geprüft/ }).click();
        await register.getByRole('button', { name: 'Eintragen' }).click();
        const erlaubnisfrei = register
            .getByRole('listitem')
            .filter({ hasText: 'nur erlaubnisfreie Waffen' });
        await expect(erlaubnisfrei).toContainText(`gültig bis 31.12.${bis}`);
        await expect(erlaubnisfrei).toContainText('aktuell');
        await beweis(
            petra,
            'trägt Ben ins Aufsichtsregister ein, nur für erlaubnisfreie Waffen',
            { zeige: erlaubnisfrei, belegt: ['V1', 'G8'] },
        );

        const ben = await anmelden(browser, PERSONEN.ben);
        wirdZu(ben, BEN_AUFSICHT);
        await ben.goto('/stand');
        await expect(ben.getByText(KEIN_REGISTER)).toHaveCount(0);
        await beweis(ben, 'darf jetzt am Stand eine Session starten', {
            zeige: ben.getByRole('button', { name: 'Session starten' }),
            belegt: ['V1'],
        });

        await sessionStarten(ben);
        await beweis(ben, 'startet seine Session als Aufsicht', {
            zeige: ben.getByText(/Session seit/),
            belegt: ['V1'],
        });

        await aktivieren(ben, 'Karl Kurzwaffe');
        await aktivieren(ben, 'Max Mustermann');
        const url = await kioskLink(ben);

        const kiosk = await oeffnen(browser, KIOSK);
        await kiosk.goto(url);
        await expect(
            kiosk.getByRole('heading', { name: 'Selbst eintragen' }),
        ).toBeVisible();
        const karlAmKiosk = kiosk.getByRole('button', {
            name: /Karl Kurzwaffe/,
        });
        await expect(karlAmKiosk).toBeEnabled();
        await beweis(
            kiosk,
            'zeigt Karl noch mit PIN, er könnte selbst eintragen',
            {
                zeige: karlAmKiosk,
            },
        );

        await mitgliedOeffnen(petra, 'Karl Kurzwaffe');
        const pin = petra.locator('section[aria-labelledby="kiosk-pin"]');
        await expect(pin).toContainText('Gesetzt.');
        petra.once('dialog', (dialog) => void dialog.accept());
        await pin.getByRole('button', { name: 'PIN löschen' }).click();
        const geloescht = petra.getByText(
            'Kiosk-PIN von Karl Kurzwaffe gelöscht.',
        );
        await expect(geloescht).toBeVisible();
        await expect(pin).toHaveCount(0);
        await beweis(petra, 'löscht Karls Kiosk-PIN', {
            zeige: geloescht,
            belegt: ['V2'],
        });

        await kiosk.reload();
        await expect(karlAmKiosk).toBeDisabled();
        await expect(karlAmKiosk).toContainText(
            'bitte bei der Aufsicht eintragen',
        );
        await expect(
            kiosk.getByRole('button', { name: /Max Mustermann/ }),
        ).toBeEnabled();
        await beweis(
            kiosk,
            'sperrt Karl ohne PIN: „bitte bei der Aufsicht eintragen“',
            {
                zeige: karlAmKiosk,
                belegt: ['V2'],
            },
        );

        const karl = teilnehmerZeile(ben, 'Karl Kurzwaffe');
        await karl.getByRole('button', { name: 'Erfassen' }).click();
        const formular = erfassungsformular(ben);
        await expect(formular).toContainText('Karl Kurzwaffe');
        const kategorie = formular.getByRole('group', { name: 'Kategorie' });
        const kurz = kategorie.getByRole('button', {
            name: 'Kurzwaffe',
            exact: true,
        });
        await expect(kurz).toBeDisabled();
        await expect(kurz).toHaveAttribute(
            'title',
            'Ihr Registereintrag umfasst nur erlaubnisfreie Waffen.',
        );
        await expect(
            kategorie.getByRole('button', { name: 'Langwaffe', exact: true }),
        ).toBeDisabled();
        await expect(
            kategorie.getByRole('button', {
                name: 'erlaubnisfrei',
                exact: true,
            }),
        ).toHaveAttribute('aria-pressed', 'true');
        await beweis(
            ben,
            'kann am Stand für Karl nur erlaubnisfrei erfassen, Kurz- und Langwaffe sind gesperrt',
            { zeige: kategorie, belegt: ['G8'] },
        );

        await formular.locator('#entry-shots').fill('25');
        await formular
            .getByRole('button', { name: 'Erfassen', exact: true })
            .click();
        const karlEintrag = karl
            .locator('ul > li')
            .filter({ hasText: 'erfasst über Aufsicht' });
        await expect(karlEintrag).toContainText('erlaubnisfrei');
        await expect(karlEintrag).toContainText('25 Schüsse');
        await beweis(
            ben,
            'trägt Karl selbst ein, erlaubnisfrei mit 25 Schuss',
            {
                zeige: karlEintrag,
                belegt: ['V2', 'G8'],
            },
        );

        await kioskPin(kiosk, 'Max Mustermann', KIOSK_PIN);
        await expect(kiosk.getByText('Ihr Eintrag für heute')).toBeVisible();
        const maxKategorie = kiosk.getByRole('group', { name: 'Kategorie' });
        const maxKurz = maxKategorie.getByRole('button', {
            name: 'Kurzwaffe',
            exact: true,
        });
        await expect(maxKurz).toBeDisabled();
        await expect(maxKurz).toHaveAttribute(
            'title',
            'Die Aufsicht dieser Session hat nur den Umfang erlaubnisfreie Waffen.',
        );
        await expect(
            maxKategorie.getByRole('button', {
                name: 'Langwaffe',
                exact: true,
            }),
        ).toBeDisabled();
        await beweis(
            kiosk,
            'lässt Max in Bens Session nur erlaubnisfrei eintragen, Kurz- und Langwaffe sind gesperrt',
            { zeige: maxKategorie, belegt: ['G8'] },
        );

        // Bei der Planung prüft die App den Umfang: Petra plant in einer Woche einen eigenen Termin
        // nur auf dem Kurzwaffenstand, früh am Morgen, damit er sich mit keinem Demo-Termin auf
        // diesem Stand überschneidet (Mittwoch 17–21, Samstag 10–14 Uhr).
        const termin = tag(7);
        await tagPlanen(petra, {
            datum: termin.iso,
            von: '07:00',
            bis: '09:00',
            titel: KURZ_TAG,
            stand: KURZ_STAND,
        });
        await tagSpeichern(petra);

        await ben.goto('/schiesstage');
        await ben.getByRole('link', { name: new RegExp(KURZ_TAG) }).click();
        await expect(ben).toHaveURL(/schiesstage\/\d+$/);
        // Der Tag hat nur den Kurzwaffenstand: Ben bekommt die Übernahme gar nicht erst angeboten,
        // der rote Hinweis über dem Raster nennt den Grund.
        const aufsicht = ben.locator('section[aria-labelledby="supervision"]');
        await expect(
            aufsicht.getByRole('button', { name: 'Aufsicht übernehmen' }),
        ).toHaveCount(0);
        await expect(ben.locator('#sv-range')).toHaveCount(0);
        const umfang = ben
            .locator('section[aria-labelledby="book"]')
            .getByText(
                'Für die Stände an diesem Tag braucht die Aufsicht den Umfang erlaubnispflichtig. Sie sind nur für erlaubnisfreie Waffen registriert.',
            );
        await expect(umfang).toBeVisible();
        await expect(
            aufsicht.getByText('Noch niemand eingetragen.'),
        ).toBeVisible();
        await expect(
            aufsicht.getByRole('listitem').filter({ hasText: 'Ben Bogen' }),
        ).toHaveCount(0);
        await beweis(
            ben,
            `bekommt am Tag nur mit „${KURZ_STAND}“ keine Übernahme der Aufsicht angeboten: Dafür braucht die Aufsicht den Umfang erlaubnispflichtig`,
            { zeige: umfang, belegt: ['G9'] },
        );

        await max.goto('/schiesstage');
        await max.getByRole('link', { name: new RegExp(KURZ_TAG) }).click();
        await expect(max).toHaveURL(/schiesstage\/\d+$/);
        await expect(max.getByText('Aufsicht: Ben Bogen')).toHaveCount(0);
        const ohneAufsicht = max.getByText(
            'Buchen ist erst möglich, wenn eine Standaufsicht eingetragen ist.',
        );
        await expect(ohneAufsicht).toBeVisible();
        const kurzZelle = bahnZelle(max, KURZ_STAND, '07:00');
        await expect(kurzZelle).toBeDisabled();
        await expect(kurzZelle).toHaveAttribute(
            'aria-label',
            `${KURZ_STAND}, 07:00 Uhr: keine Aufsicht`,
        );
        await expect(
            max.locator('section[aria-labelledby="my-bookings"]'),
        ).toHaveCount(0);
        await beweis(
            max,
            `kann auf „${KURZ_STAND}“ keine Bahn für Kurzwaffe buchen: Ben ist dort nicht Aufsicht, der Stand bleibt ohne Aufsicht gesperrt`,
            { zeige: kurzZelle, belegt: ['G9'] },
        );

        await mitgliedOeffnen(petra, 'Ben Bogen');
        await petra.getByRole('button', { name: 'Prüfung eintragen' }).click();
        await petra.locator('#reg-scope').selectOption('revoked');
        await petra.locator('#reg-note').fill('Lehrgang nicht aufgefrischt');

        // Max füllt am Kiosk seinen Eintrag aus, während Petra den Entzug einträgt. Das Formular von
        // oben steht meist noch, aber der Poll (alle 30 Sekunden seit dem Neuladen oben) zeigte den
        // Entzug auf einem langsamen Rechner womöglich vor dem Absenden, und nach 90 Sekunden ohne
        // Eingabe geht der Kiosk zur Liste zurück. Frisch geladen bleiben bis zum Absenden nur wenige
        // Sekunden ohne Poll.
        await kiosk.goto(url);
        await kioskPin(kiosk, 'Max Mustermann', KIOSK_PIN);
        const maxFormular = kiosk.getByText('Ihr Eintrag für heute');
        await expect(maxFormular).toBeVisible();
        await kiosk
            .getByRole('button', { name: 'erlaubnisfrei', exact: true })
            .click();
        await kiosk
            .getByRole('button', { name: 'Vereinswaffe', exact: true })
            .click();
        await kiosk.getByLabel('Schüsse').fill('20');

        await register.getByRole('button', { name: 'Eintragen' }).click();
        const entzogen = register
            .getByRole('listitem')
            .filter({ hasText: 'entzogen' });
        await expect(entzogen).toContainText('aktuell');
        await beweis(petra, 'entzieht Ben die Registrierung als Aufsicht', {
            zeige: entzogen,
            belegt: ['V5'],
        });

        // Ohne Neuladen absenden: Das Formular steht noch, seit dem Laden kam kein Poll.
        await expect(maxFormular).toBeVisible();
        const abgesendet = kiosk.waitForResponse(
            (antwort) =>
                antwort.request().method() === 'POST' &&
                antwort.url().endsWith('/eintrag'),
        );
        await kiosk
            .getByRole('button', { name: 'Erfassen', exact: true })
            .click();
        await abgesendet;
        const abgelehnt = kiosk.getByRole('status').filter({
            has: kiosk.getByRole('heading', { name: 'Keine Einträge möglich' }),
        });
        await expect(abgelehnt).toContainText(
            'Die Aufsicht dieser Session ist nicht mehr im Aufsichtsregister.',
        );
        await expect(kiosk.getByText(/erfasst um/)).toHaveCount(0);
        // Abgewiesen hat der Server beim Absenden (Sessions::SUPERVISOR_NOT_REGISTERED), nicht erst der Poll.
        expect(
            await kiosk.evaluate(
                () =>
                    (
                        window.history.state as {
                            page?: {
                                props?: { errors?: Record<string, string> };
                            };
                        }
                    )?.page?.props?.errors?.entry,
            ),
        ).toBe(
            'Die Aufsicht dieser Session ist nicht mehr im Aufsichtsregister. Bitte bei einer anderen Aufsicht eintragen.',
        );
        await beweis(
            kiosk,
            'weist Max’ Eintrag ab, den er vor dem Entzug ausgefüllt und danach ohne Neuladen abgesendet hat: „Keine Einträge möglich“',
            { zeige: abgelehnt, belegt: ['V6'] },
        );

        await ben.goto('/stand');
        const wiederOhne = ben.getByText(KEIN_REGISTER);
        await expect(wiederOhne).toBeVisible();
        await expect(
            ben.getByRole('button', { name: 'Eintragen' }),
        ).toHaveCount(0);
        await expect(
            ben.getByRole('button', { name: 'Session starten' }),
        ).toHaveCount(0);
        await expect(
            ben.getByRole('heading', { name: 'Teilnehmer dieser Session' }),
        ).toHaveCount(0);
        await expect(
            ben.getByRole('button', { name: 'Abzeichnen' }),
        ).toHaveCount(0);
        await beweis(
            ben,
            'kann nach dem Entzug nicht mehr eintragen, abzeichnen oder eine Session starten',
            {
                zeige: wiederOhne,
                belegt: ['V5'],
            },
        );
        const gesperrteSession = ben.locator('[data-test="locked-session"]');
        await expect(gesperrteSession).toContainText(
            'nimmt keine Einträge mehr an und lässt sich ohne gültige Zeile nicht abzeichnen.',
        );
        // Offen ist nur Karls Eintrag; Max’ abgewiesener Eintrag am Kiosk ist keiner.
        await expect(gesperrteSession).toContainText(
            'Der offene Eintrag verfällt um Mitternacht ohne Nachweis',
        );
        await beweis(
            ben,
            'sieht seine offene Session gesperrt: keine Einträge, kein Abzeichnen, Karls offener Eintrag verfällt um Mitternacht',
            { zeige: gesperrteSession, belegt: ['V6'] },
        );
        wirdZu(ben, PERSONEN.ben);

        // Bens Session ist noch offen, nimmt nach dem Entzug aber keine Einträge mehr an: Der Kiosk
        // zeigt keine Liste mehr, und Max’ Handy zeigt statt des Formulars einen Hinweis.
        await kiosk.goto(url);
        const gesperrt = kiosk.getByRole('status').filter({
            has: kiosk.getByRole('heading', { name: 'Keine Einträge möglich' }),
        });
        await expect(gesperrt).toContainText(
            'Die Aufsicht dieser Session ist nicht mehr im Aufsichtsregister.',
        );
        await expect(
            kiosk.getByRole('button', { name: /Max Mustermann/ }),
        ).toHaveCount(0);
        await expect(
            kiosk.getByRole('button', { name: /Karl Kurzwaffe/ }),
        ).toHaveCount(0);
        await beweis(
            kiosk,
            'nimmt nach dem Entzug keine Einträge mehr an: „Keine Einträge möglich“, ohne Personenliste',
            { zeige: gesperrt, belegt: ['V6'] },
        );

        await max.goto('/heute');
        await expect(max.getByText('Aufsicht Ben Bogen')).toBeVisible();
        const abgewiesen = max.getByRole('status').filter({
            has: max.getByRole('heading', { name: 'Keine Einträge möglich' }),
        });
        await expect(abgewiesen).toContainText(
            'Die Aufsicht dieser Session ist nicht mehr im Aufsichtsregister.',
        );
        await expect(
            max.getByRole('region', { name: 'Neuer Eintrag' }),
        ).toHaveCount(0);
        await expect(
            max.getByRole('button', { name: 'Erfassen', exact: true }),
        ).toHaveCount(0);
        await expect(
            max.getByText(/Es zählt, sobald die Aufsicht die Session/),
        ).toHaveCount(0);
        await expect(
            max.locator('section[aria-labelledby="own-entries"]'),
        ).toHaveCount(0);
        await beweis(
            max,
            'sieht am eigenen Handy in Bens Session „Keine Einträge möglich“ statt des Formulars: Die Aufsicht ist nicht mehr im Aufsichtsregister, es entsteht kein Eintrag',
            { zeige: abgewiesen, belegt: ['V6'] },
        );
    },
);

test(
    'Vorstand deaktiviert ein Mitglied: keine Anmeldung mehr, der Auszug bleibt abrufbar',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Erika ist am Handy angemeldet, als Petra ihren Zugang deaktiviert. Beim nächsten Klick fliegt Erika hinaus und kann sich auch neu nicht anmelden. Petra lädt Erikas Auszug aus der Schießkladde trotzdem weiter herunter.',
        },
    },
    async ({ browser }) => {
        const erika = await anmelden(browser, PERSONEN.erika);

        const petra = await anmelden(browser, PERSONEN.petra);
        await mitgliedOeffnen(petra, 'Erika Musterfrau');
        const aktiv = petra.getByRole('checkbox', { name: /Zugang aktiv/ });
        await expect(aktiv).toBeChecked();
        await petra
            .getByText('– deaktivierte Mitglieder können sich nicht anmelden')
            .click();
        await expect(aktiv).not.toBeChecked();
        await petra.getByRole('button', { name: 'Speichern' }).click();
        await expect(petra).toHaveURL(/verwaltung\/mitglieder/);
        await petra.getByRole('tab', { name: 'Deaktiviert' }).click();
        const zeile = petra.getByRole('row', { name: /Erika Musterfrau/ });
        await expect(zeile).toContainText('Deaktiviert');
        await beweis(petra, 'hat Erikas Zugang deaktiviert', {
            zeige: zeile,
            belegt: ['V3'],
        });

        await erika.reload();
        await expect(erika).toHaveURL(/login/);
        const hinaus = erika.getByText('Ihr Zugang ist deaktiviert.');
        await expect(hinaus).toBeVisible();
        await beweis(
            erika,
            'wird beim nächsten Klick abgemeldet: „Ihr Zugang ist deaktiviert.“',
            {
                zeige: hinaus,
                belegt: ['V3', 'G4'],
            },
        );

        await anmeldenVersuchen(erika, PERSONEN.erika.email);
        const abgewiesen = erika.getByText(
            'Ihr Zugang ist deaktiviert. Bitte wenden Sie sich an den Vorstand.',
        );
        await expect(abgewiesen).toBeVisible();
        await expect(erika).toHaveURL(/login/);
        await beweis(
            erika,
            'wird bei der neuen Anmeldung mit richtigem Passwort abgewiesen, mit Hinweis auf den deaktivierten Zugang',
            {
                zeige: abgewiesen,
                belegt: ['V3', 'G4'],
            },
        );

        await petra.goto('/verwaltung/schiesskladde');
        await petra
            .locator('#member')
            .selectOption({ label: 'Erika Musterfrau' });
        await petra.getByRole('button', { name: 'Anzeigen' }).click();
        const auszug = petra.getByRole('link', {
            name: /Auszug Erika Musterfrau/,
        });
        const download = await laden(petra, auszug);
        expect(download.suggestedFilename()).toMatch(
            /^Schiesskladde-erika-musterfrau-.*\.pdf$/,
        );
        expect(await pdfText(download)).toContain('Erika Musterfrau');
        await beweis(
            petra,
            'lädt Erikas Auszug aus der Schießkladde weiterhin herunter',
            {
                zeige: auszug,
                belegt: ['V3'],
            },
        );
    },
);
