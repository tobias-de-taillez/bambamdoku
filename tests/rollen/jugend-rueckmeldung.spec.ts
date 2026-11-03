import {
    anmelden,
    beweis,
    expect,
    KIOSK,
    oeffnen,
    PERSONEN,
    sicherheitOeffnen,
    test,
} from './beweis';
import {
    aktivieren,
    erfassungsformular,
    kioskLink,
    kioskListe,
    sessionStarten,
    teilnehmer,
} from './stand';

test(
    'Jugendlicher am Stand: ohne PIN und ohne „volljährig geprüft“ kommt Tim nicht in die Session',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Tim ist minderjährig und hat weder Kiosk-PIN noch den Vermerk „volljährig geprüft“; am Handy sieht er das Jugendtraining. Helga will ihn am Stand aktivieren und für ihn erfassen, doch die App verlangt dafür jedes Mal den Haken „volljährig geprüft“. Den Haken könnte sie setzen, die App kennt Tims Alter nicht; sie tut es nicht. Am Kiosk steht er nicht in der Liste, Tim bleibt auf Papier.',
        },
    },
    async ({ browser }) => {
        const tim = await anmelden(browser, PERSONEN.tim);
        await tim.goto('/schiesstage');
        const jugendtraining = tim.getByRole('link', {
            name: /Jugendtraining/,
        });
        await expect(jugendtraining).toHaveCount(1);
        await beweis(tim, 'sieht das Jugendtraining unter „Schießtage“', {
            zeige: jugendtraining,
        });

        await sicherheitOeffnen(tim);
        const pinStatus = tim.locator('[data-test="kiosk-pin-status"]');
        await expect(pinStatus).toHaveText('Noch keine Kiosk-PIN gesetzt.');
        await beweis(tim, 'hat keine Kiosk-PIN', {
            zeige: pinStatus,
            belegt: ['G6'],
        });

        const helga = await anmelden(browser, PERSONEN.helga);
        await sessionStarten(helga);
        await aktivieren(helga, 'Max Mustermann');

        const bereich = teilnehmer(helga);
        const suche = bereich.getByLabel('Mitglied aktivieren');
        const haken = bereich.getByRole('checkbox', {
            name: /volljährig geprüft/,
        });
        await expect(haken).not.toBeChecked();
        await suche.fill('Tim');
        const timImVorschlag = bereich.getByRole('button', {
            name: /^Tim Jungschütze/,
        });
        await expect(timImVorschlag).toBeDisabled();
        await expect(timImVorschlag).toContainText(
            'Haken „volljährig geprüft“ nötig',
        );
        await beweis(
            helga,
            'kann Tim ohne den Haken „volljährig geprüft“ nicht aktivieren, der Vorschlag bleibt gesperrt',
            {
                zeige: timImVorschlag,
                belegt: ['G6'],
            },
        );

        // G6: nur mit dem Haken „volljährig geprüft“ wird Tim aktivierbar. Die App kennt kein
        // Alter; ob der Haken stimmt, verantwortet die Aufsicht. Abgeschickt wird hier nichts.
        await suche.fill('');
        await haken.click();
        await expect(haken).toBeChecked();
        await beweis(
            helga,
            'setzt probeweise den Haken „volljährig geprüft“, den die Aufsicht verantwortet',
            { zeige: haken, belegt: ['G6'] },
        );
        await suche.fill('Tim');
        await expect(timImVorschlag).toBeEnabled();
        await beweis(
            helga,
            'erst mit gesetztem Haken (von der Vorschlagsliste verdeckt) wäre Tim aktivierbar; sie tippt ihn nicht an',
            { zeige: timImVorschlag, belegt: ['G6'] },
        );
        await suche.fill('');
        await haken.click();
        await expect(haken).not.toBeChecked();

        // Eintragen über das Formular aktiviert Tim ebenfalls (RangeOfficeController::enter).
        await helga
            .getByRole('button', { name: 'Eintragen', exact: true })
            .click();
        const formular = erfassungsformular(helga);
        await formular.getByLabel('Mitglied suchen').fill('Tim');
        await formular
            .getByRole('button', { name: /^Tim Jungschütze/ })
            .click();
        await expect(formular.getByLabel('Mitglied suchen')).toHaveValue(
            'Tim Jungschütze',
        );
        await formular
            .getByRole('button', { name: 'erlaubnisfrei', exact: true })
            .click();
        await formular.getByLabel('Schüsse').fill('20');
        const erfassen = formular.getByRole('button', {
            name: 'Erfassen',
            exact: true,
        });
        await expect(
            formular.getByRole('checkbox', {
                name: 'volljährig geprüft (einmalig, Ausweis gesehen)',
            }),
        ).not.toBeChecked();
        await expect(erfassen).toBeDisabled();
        await beweis(
            helga,
            'will Tim mit 20 Schuss erlaubnisfrei eintragen, „Erfassen“ bleibt ohne Haken „volljährig geprüft“ gesperrt',
            {
                zeige: formular,
                belegt: ['G6'],
            },
        );
        await formular
            .getByRole('button', { name: 'Schließen', exact: true })
            .click();
        await expect(formular).toHaveCount(0);
        await expect(
            bereich.getByRole('button', {
                name: 'Tim Jungschütze aus der Session entfernen',
            }),
        ).toHaveCount(0);

        const url = await kioskLink(helga);
        const kiosk = await oeffnen(browser, KIOSK);
        await kiosk.goto(url);
        await expect(
            kiosk.getByRole('heading', { name: 'Selbst eintragen' }),
        ).toBeVisible();
        const liste = kioskListe(kiosk);
        await expect(liste.getByRole('listitem')).toHaveCount(1);
        await expect(
            liste.getByRole('button', { name: /Max Mustermann/ }),
        ).toBeEnabled();
        await expect(
            kiosk.getByRole('button', { name: /Tim Jungschütze/ }),
        ).toHaveCount(0);
        await beweis(
            kiosk,
            'zeigt nur Max zum Selbsteintragen; Tim steht nicht in der Liste und kann sich hier nicht eintragen',
            {
                zeige: liste,
                belegt: ['G6'],
            },
        );

        await tim.goto('/heute');
        const hinweis = tim.getByText('Sie sind heute noch nicht aktiviert.');
        await expect(hinweis).toBeVisible();
        await expect(tim.getByLabel('Schüsse')).toHaveCount(0);
        await beweis(
            tim,
            'ist heute nicht aktiviert und kann am Handy nichts eintragen',
            { zeige: hinweis },
        );
    },
);

test(
    'Rückmeldung: Mitglied meldet einen Fehler mit Bildschirmfoto, Vorstand sieht Meldung und Bild und schließt sie ab',
    {
        annotation: {
            type: 'geschichte',
            description:
                'Max tippt am Handy auf „Report Bug / Request Feature“ und meldet einen Fehler samt Bildschirmfoto. Petra findet die Meldung unter Rückmeldungen, sieht Text und Bild und setzt sie auf erledigt.',
        },
    },
    async ({ browser }) => {
        const text =
            'Auf dem Handy springt die Liste der Schießtage nach dem Zurückgehen wieder ganz nach oben.';

        const max = await anmelden(browser, PERSONEN.max);
        await max.goto('/schiesstage');
        await expect(
            max.getByRole('heading', { name: 'Schießtage' }),
        ).toBeVisible();
        await max
            .getByRole('button', { name: 'Report Bug / Request Feature' })
            .click();
        const dialog = max.getByRole('dialog', {
            name: 'Report Bug / Request Feature',
        });
        await expect(dialog).toBeVisible();
        await dialog
            .getByLabel('Was ist kaputt oder was sollte besser werden?')
            .fill(text);
        const vorschau = dialog.getByRole('img', {
            name: 'Vorschau des Bildschirmfotos',
        });
        await expect(vorschau).toBeVisible({ timeout: 15_000 });
        await beweis(
            max,
            'beschreibt den Fehler, das Bildschirmfoto der Seite ist dabei',
            { zeige: vorschau, belegt: ['R1'] },
        );

        await dialog.getByRole('button', { name: 'Absenden' }).click();
        await expect(
            dialog.getByText('Danke! Ihre Meldung ist angekommen.'),
        ).toBeVisible();
        const kennungFeld = dialog.getByTestId('help-reference');
        const kennung = ((await kennungFeld.textContent()) ?? '').trim();
        expect(kennung).toMatch(/^[A-Z2-9]{6}$/);
        await beweis(max, `hat die Meldung abgeschickt, Kennung ${kennung}`, {
            zeige: dialog.getByRole('status'),
            belegt: ['R1'],
        });

        const petra = await anmelden(browser, PERSONEN.petra);
        await petra.goto('/verwaltung/rueckmeldungen');
        const eintrag = petra.getByRole('link', {
            name: new RegExp(kennung),
        });
        await expect(eintrag).toContainText(text);
        await expect(eintrag).toContainText('Max Mustermann');
        await expect(eintrag).toContainText('offen');
        await beweis(petra, 'sieht Max’ Meldung offen unter „Rückmeldungen“', {
            zeige: eintrag,
            belegt: ['R1'],
        });

        await eintrag.click();
        await expect(
            petra.getByRole('heading', { name: `Rückmeldung ${kennung}` }),
        ).toBeVisible();
        const meldung = petra.locator('section[aria-labelledby="hr-message"]');
        await expect(meldung).toContainText(text);
        await beweis(petra, 'öffnet die Meldung und liest Max’ Text', {
            zeige: meldung,
            belegt: ['R1'],
        });

        const bild = petra.getByRole('img', {
            name: `Bildschirmfoto zur Rückmeldung ${kennung}`,
        });
        // Das Bild lädt erst im sichtbaren Bereich (loading="lazy").
        await bild.scrollIntoViewIfNeeded();
        await expect
            .poll(() =>
                bild.evaluate(
                    (img: HTMLImageElement) => img.complete && img.naturalWidth,
                ),
            )
            .toBeGreaterThan(0);
        await beweis(
            petra,
            'sieht darunter Max’ Bildschirmfoto der Schießtage von seinem Handy',
            {
                zeige: bild,
                belegt: ['R1'],
            },
        );

        await petra.getByRole('button', { name: 'Erledigt' }).click();
        await expect(
            petra.getByRole('button', { name: 'Wieder öffnen' }),
        ).toBeVisible();
        await beweis(petra, 'setzt die Meldung auf erledigt', {
            zeige: petra.getByText(/^erledigt seit/),
            belegt: ['R1'],
        });

        await petra.goto('/verwaltung/rueckmeldungen');
        await expect(
            petra.getByRole('link', { name: new RegExp(kennung) }),
        ).toHaveCount(0);
        await petra
            .getByRole('link', { name: 'Erledigt', exact: true })
            .click();
        const erledigt = petra.getByRole('link', {
            name: new RegExp(kennung),
        });
        await expect(erledigt).toContainText('erledigt');
        await beweis(
            petra,
            'findet die Meldung nicht mehr unter „Offen“, sondern unter „Erledigt“',
            {
                zeige: erledigt,
                belegt: ['R1'],
            },
        );
    },
);
