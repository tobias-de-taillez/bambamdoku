import { expect, test, type Page } from '@playwright/test';
import { as, startSession } from './helpers';

/** Weitere Abläufe; jeder Test nutzt eigene Personen, damit sie sich nicht stören. */

async function openMember(admin: Page, name: string): Promise<void> {
    await admin.goto('/verwaltung/mitglieder');
    await admin.getByRole('link', { name, exact: true }).click();
    await expect(admin.locator('#name')).toHaveValue(name);
}

test('Gast nur mit Bestätigung „volljährig“; nach Registerentzug kein Eintragen mehr', async ({
    browser,
}) => {
    const jonas = await as(browser, 'jonas.aufsicht@demo.test');
    await jonas.goto('/stand');
    await startSession(jonas);
    await jonas.getByRole('button', { name: 'Eintragen' }).first().click();
    await jonas.getByRole('tab', { name: 'Gast' }).click();
    await jonas.getByLabel('Name des Gastes').fill('Gerd Gast');
    await jonas
        .getByRole('button', { name: 'erlaubnisfrei', exact: true })
        .click();
    await jonas
        .locator('form')
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();
    await expect(
        jonas.getByText('Bitte bestätigen, dass der Gast volljährig ist.'),
    ).toBeVisible();

    await jonas.getByText('Gast ist volljährig (Ausweis gesehen)').click();
    await jonas
        .locator('form')
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();
    const entry = jonas
        .locator('section[aria-labelledby="participants"] > ul > li')
        .filter({ hasText: 'Gerd Gast' });
    await expect(entry).toHaveCount(1);
    await expect(entry).toContainText('Gast');

    const petra = await as(browser, 'petra.vorstand@demo.test');
    await openMember(petra, 'Jonas Aufsicht');
    await petra.getByRole('button', { name: 'Prüfung eintragen' }).click();
    await petra.locator('#reg-scope').selectOption('revoked');
    await petra.locator('#reg-note').fill('Lehrgang nicht aufgefrischt');
    await petra
        .locator('section[aria-labelledby="register"]')
        .getByRole('button', { name: 'Eintragen' })
        .click();
    await expect(
        petra.locator('section[aria-labelledby="register"] li').first(),
    ).toContainText('aktuell');

    await jonas.goto('/stand');
    await expect(
        jonas.getByText(
            'Eine Session starten dürfen nur Personen mit gültiger Zeile im Aufsichtsregister',
        ),
    ).toBeVisible();
    await expect(jonas.getByRole('button', { name: 'Eintragen' })).toHaveCount(
        0,
    );
});

test('Einladungslink: Passwort festlegen und angemeldet sein', async ({
    browser,
}) => {
    const petra = await as(browser, 'petra.vorstand@demo.test');
    await petra.goto('/verwaltung/mitglieder/neu');
    await petra.locator('#name').fill('Ida Eingeladen');
    await petra.locator('#email').fill('ida.eingeladen@demo.test');
    // Die E2E-Suite läuft mit MAIL_MAILER=array: kein Mailversand eingerichtet.
    await expect(
        petra.getByText(
            'Der Mailversand ist nicht eingerichtet. Sie bekommen den Einladungslink nach dem Anlegen zum Weitergeben.',
        ),
    ).toBeVisible();
    await petra.getByRole('button', { name: 'Anlegen und einladen' }).click();
    const notice = petra
        .getByRole('status')
        .filter({ hasText: 'Einladungslink für Ida Eingeladen' });
    await expect(notice).toContainText(
        'wurde erstellt, aber nicht verschickt.',
    );
    await expect(notice).toContainText(
        'Der Mailversand ist nicht eingerichtet. Bitte geben Sie den Link selbst weiter.',
    );
    await expect(notice).not.toContainText('per E-Mail verschickt');
    const link = await petra.getByLabel('Einladungslink').inputValue();
    expect(link).toContain('http');

    const ida = await (await browser.newContext()).newPage();
    await ida.goto(link);
    await ida.locator('#password').fill('ein-sicheres-passwort-42');
    await ida
        .locator('#password_confirmation')
        .fill('ein-sicheres-passwort-42');
    await ida
        .getByRole('button', { name: 'Passwort festlegen und anmelden' })
        .click();
    await expect(ida).toHaveURL(/dashboard/);

    // Der Link funktioniert nur einmal.
    const again = await (await browser.newContext()).newPage();
    await again.goto(link);
    await expect(again).toHaveURL(/login/);
    await expect(
        again.getByText('Dieser Einladungslink ist nicht mehr gültig.'),
    ).toBeVisible();
});

test('Mitglied bucht und storniert die eigene Buchung', async ({ browser }) => {
    const lena = await as(browser, 'lena.langwaffe@demo.test');
    await lena.goto('/schiesstage');
    // Ein Termin ohne Buchung von Lena, mit Aufsicht
    await lena
        .getByRole('link')
        .filter({ hasText: 'Aufsicht:' })
        .filter({ hasNotText: 'Gebucht' })
        .first()
        .click();
    await expect(lena).toHaveURL(/schiesstage\/\d+/);
    await lena.locator('table button:not([disabled])').first().click();
    await lena.getByRole('button', { name: /Uhr buchen/ }).click();
    await expect(
        lena.getByRole('heading', { name: 'Ihre Buchungen an diesem Tag' }),
    ).toBeVisible();

    await lena.goto('/buchungen');
    const before = await lena
        .getByRole('button', { name: 'Stornieren' })
        .count();
    expect(before).toBeGreaterThan(0);
    await lena.getByRole('button', { name: 'Stornieren' }).first().click();
    await expect(lena.getByRole('button', { name: 'Stornieren' })).toHaveCount(
        before - 1,
    );
});

test('Mitglied sieht die eigene Kladde und lädt den Auszug', async ({
    browser,
}) => {
    const lena = await as(browser, 'lena.langwaffe@demo.test');
    await lena.goto('/schiesskladde');
    await expect(
        lena.getByRole('heading', {
            name: 'Meine Einträge in der Schießkladde',
        }),
    ).toBeVisible();
    await lena.getByRole('button', { name: 'letzte 24 Monate' }).click();
    await expect(lena.getByRole('heading', { name: 'Termine' })).toBeVisible();

    const download = lena.waitForEvent('download');
    await lena.getByRole('link', { name: 'Auszug zur Information' }).click();
    expect((await download).suggestedFilename()).toMatch(
        /^Schiesskladde-lena-langwaffe-/,
    );
});

test('Deaktiviertes Mitglied kann sich nicht anmelden, der Auszug bleibt abrufbar', async ({
    browser,
}) => {
    const petra = await as(browser, 'petra.vorstand@demo.test');
    await openMember(petra, 'Tim Jungschütze');
    await petra
        .getByText('– deaktivierte Mitglieder können sich nicht anmelden')
        .click();
    await petra.getByRole('button', { name: 'Speichern' }).click();
    await expect(petra).toHaveURL(/verwaltung\/mitglieder/);

    const tim = await (await browser.newContext()).newPage();
    const tryLogin = async (password: string): Promise<void> => {
        await tim.goto('/login');
        await tim
            .getByLabel('E-Mail-Adresse')
            .fill('tim.jungschutze@demo.test');
        await tim.getByLabel('Passwort', { exact: true }).fill(password);
        await tim.locator('[data-test="login-button"]').click();
    };
    const disabled = tim.getByText(
        'Ihr Zugang ist deaktiviert. Bitte wenden Sie sich an den Vorstand.',
    );
    const generic = tim.getByText(
        'Diese Zugangsdaten stimmen nicht mit unseren Daten überein.',
    );

    // Mit richtigem Passwort: eigener Hinweis statt der allgemeinen Meldung.
    await tryLogin('demo-passwort');
    await expect(disabled).toBeVisible();
    await expect(generic).toHaveCount(0);
    await expect(tim).toHaveURL(/login/);

    // Mit falschem Passwort verrät die Anmeldung nichts über den Zugang.
    await tryLogin('falsches-passwort');
    await expect(generic).toBeVisible();
    await expect(disabled).toHaveCount(0);
    await expect(tim).toHaveURL(/login/);

    await petra.goto('/verwaltung/schiesskladde');
    await petra.locator('#member').selectOption({ label: 'Tim Jungschütze' });
    await petra.getByRole('button', { name: 'Anzeigen' }).click();
    const download = petra.waitForEvent('download');
    await petra.getByRole('link', { name: /Auszug Tim Jungschütze/ }).click();
    expect((await download).suggestedFilename()).toMatch(
        /^Schiesskladde-tim-jungschutze-/,
    );
});

test('Vorstand pflegt die Angaben für den Kopf des Auszugs', async ({
    browser,
}) => {
    const petra = await as(browser, 'petra.vorstand@demo.test');
    await petra.goto('/verwaltung/einstellungen');
    await petra.locator('#federation').fill('BSSB');
    await petra.locator('#club_number').fill('V-1234');
    await petra.locator('#digital_since').fill('2026-11-01');
    await petra.getByRole('button', { name: 'Speichern' }).click();
    await expect(petra.getByText('Einstellungen gespeichert.')).toBeVisible();
    await petra.reload();
    await expect(petra.locator('#club_number')).toHaveValue('V-1234');
    await expect(petra.locator('#digital_since')).toHaveValue('2026-11-01');
});

test('Impressum aus den Einstellungen erscheint öffentlich auf der Anmeldung', async ({
    browser,
}) => {
    const petra = await as(browser, 'petra.vorstand@demo.test');
    await petra.goto('/verwaltung/einstellungen');
    await petra
        .locator('#imprint')
        .fill('SV Demo e. V.\nAm Schießstand 1, 12345 Demodorf');
    await petra.getByRole('button', { name: 'Speichern' }).click();
    await expect(petra.getByText('Einstellungen gespeichert.')).toBeVisible();

    const guest = await browser.newPage();
    await guest.goto('/login');
    await guest.getByRole('link', { name: 'Impressum' }).click();
    await expect(
        guest.getByRole('heading', { name: 'Impressum' }),
    ).toBeVisible();
    await expect(
        guest.getByText('Am Schießstand 1, 12345 Demodorf'),
    ).toBeVisible();
    // Ohne die Seitenleiste der Mitglieder, mit eigenem Rückweg
    await expect(
        guest.getByRole('link', { name: 'Zur Startseite' }),
    ).toBeVisible();
    await expect(
        guest.getByRole('button', { name: 'Seitenleiste ein-/ausblenden' }),
    ).toHaveCount(0);
    for (const name of ['Übersicht', 'Meine Buchungen', 'Schießkladde']) {
        await expect(
            guest.getByRole('link', { name, exact: true }),
        ).toHaveCount(0);
    }
    await guest.close();

    // Angemeldet ebenso ohne Seitenleiste, zurück geht es zur Übersicht.
    await petra.goto('/impressum');
    await expect(
        petra.getByRole('heading', { name: 'Impressum' }),
    ).toBeVisible();
    await expect(
        petra.getByRole('link', { name: 'Zurück zur Übersicht' }),
    ).toBeVisible();
    await expect(
        petra.getByRole('button', { name: 'Seitenleiste ein-/ausblenden' }),
    ).toHaveCount(0);
    await expect(
        petra.getByRole('link', { name: 'Mitglieder', exact: true }),
    ).toHaveCount(0);
});

test('„Angemeldet bleiben“ setzt das Remember-Cookie', async ({ browser }) => {
    const page = await (await browser.newContext()).newPage();

    await page.goto('/login');
    await page.getByLabel('E-Mail-Adresse').fill('max.mustermann@demo.test');
    await page.getByLabel('Passwort', { exact: true }).fill('demo-passwort');
    await page.getByRole('checkbox', { name: 'Angemeldet bleiben' }).click();
    await page.locator('[data-test="login-button"]').click();
    await expect(page).toHaveURL(/dashboard/);

    const cookies = await page.context().cookies();
    expect(
        cookies.some((cookie) => cookie.name.startsWith('remember_web_')),
    ).toBe(true);
});
