import { expect, test, type Locator, type Page } from '@playwright/test';
import { as, startSession } from './helpers';

/**
 * Ein ganzer Schießtag: Vorstand plant heute, teilt die Aufsicht ein,
 * ein Mitglied bucht, die Aufsicht bestätigt am Stand – das landet in der Kladde.
 */

function today(): string {
    const d = new Date();

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

test('Schießtag von der Planung bis zum Eintrag in der Kladde', async ({
    browser,
}) => {
    // Gebucht werden kann nur, was heute noch nicht vorbei ist.
    test.skip(
        new Date().getHours() >= 23,
        'Kurz vor Mitternacht ist heute keine Buchung mehr frei.',
    );

    const petra = await as(browser, 'petra.vorstand@demo.test');
    await petra.goto('/verwaltung/schiesstage/neu');
    await petra.locator('#date').fill(today());
    await petra.locator('#starts_at').fill('00:00');
    await petra.locator('#ends_at').fill('23:45');
    await petra.locator('#title').fill('E2E-Tag');
    await petra.getByRole('button', { name: 'Anlegen', exact: true }).click();
    await expect(petra).toHaveURL(/verwaltung\/schiesstage/);

    await petra.goto('/schiesstage');
    await petra.getByRole('link', { name: /E2E-Tag/ }).click();
    await expect(petra).toHaveURL(/schiesstage\/\d+/);
    const dayUrl = petra.url();
    await petra.getByRole('button', { name: 'Aufsicht übernehmen' }).click();
    await petra.locator('#sv-user').selectOption({ label: 'Helga Schießwart' });
    await petra
        .locator('section[aria-labelledby="supervision"]')
        .getByRole('button', { name: 'Eintragen' })
        .click();
    await expect(
        petra.locator('section[aria-labelledby="supervision"]'),
    ).toContainText('Helga Schießwart');

    const erika = await as(browser, 'erika.musterfrau@demo.test');
    await erika.goto(dayUrl);
    await erika.locator('table button:not([disabled])').first().click();
    await erika.getByRole('button', { name: /Uhr buchen/ }).click();
    await expect(
        erika.getByRole('heading', { name: 'Ihre Buchungen an diesem Tag' }),
    ).toBeVisible();

    const ben = await as(browser, 'ben.bogen@demo.test');
    await ben.goto(dayUrl);
    await ben.locator('table button:not([disabled])').first().click();
    await ben.getByRole('button', { name: /Uhr buchen/ }).click();
    await expect(
        ben.getByRole('heading', { name: 'Ihre Buchungen an diesem Tag' }),
    ).toBeVisible();

    const helga = await as(browser, 'helga.schiesswart@demo.test');
    await helga.goto('/stand');
    await expect(helga.getByText('E2E-Tag').first()).toBeVisible();
    await startSession(helga);

    // Erika meldet sich per QR-Code am Stand, die Aufsicht sieht „ist da“.
    const arrivalUrl = await helga.evaluate(
        () =>
            (
                window.history.state as {
                    page?: { props?: { arrivalUrl?: string } };
                }
            )?.page?.props?.arrivalUrl,
    );
    expect(arrivalUrl).toContain('/da/');
    await erika.goto(arrivalUrl!);
    await expect(
        erika.getByRole('heading', { name: 'Sie sind gemeldet' }),
    ).toBeVisible();
    await helga.reload();
    const erikaRow = helga
        .locator('li')
        .filter({ hasText: 'Erika Musterfrau' })
        .filter({ has: helga.getByRole('button', { name: 'Aktivieren' }) });
    await expect(erikaRow).toContainText('ist da');
    await activate(helga, erikaRow);

    const participants = helga.locator(
        'section[aria-labelledby="participants"] > ul > li',
    );
    await expect(
        participants.filter({ hasText: 'Erika Musterfrau' }),
    ).toHaveCount(1);
    await expect(
        participants.filter({ hasText: 'Erika Musterfrau' }),
    ).toContainText('aus Buchung');

    // Nach dem Aktivieren sieht Erika „Für die Session aktiviert“.
    await erika.goto(arrivalUrl!);
    await expect(
        erika.getByRole('heading', { name: 'Für die Session aktiviert' }),
    ).toBeVisible();

    // Ben erscheint nicht – und kommt doch: zurücknehmen und bestätigen.
    await helga
        .getByRole('button', { name: 'Ben Bogen nicht erschienen' })
        .click();
    await expect(
        helga.getByText('nicht erschienen', { exact: true }),
    ).toBeVisible();
    await expect(participants.filter({ hasText: 'Ben Bogen' })).toHaveCount(0);
    await helga
        .getByRole('button', {
            name: '„nicht erschienen“ für Ben Bogen zurücknehmen',
        })
        .click();
    await activate(
        helga,
        helga
            .locator('li')
            .filter({ hasText: 'Ben Bogen' })
            .filter({ has: helga.getByRole('button', { name: 'Aktivieren' }) }),
    );
    await expect(participants.filter({ hasText: 'Ben Bogen' })).toHaveCount(1);
});

/** „Aktivieren“ in der Buchungsliste, mit einmaligem Haken „volljährig geprüft“, falls gefragt. */
async function activate(page: Page, row: Locator): Promise<void> {
    await row.getByRole('button', { name: 'Aktivieren' }).click();
    const adult = page.getByText(
        'volljährig geprüft (einmalig, Ausweis gesehen)',
    );

    if ((await adult.count()) > 0 && (await adult.first().isVisible())) {
        await adult.first().click();
        await page
            .getByRole('button', { name: 'Aktivieren', exact: true })
            .first()
            .click();
    }
}
