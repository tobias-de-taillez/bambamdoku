import { expect, test } from '@playwright/test';
import { as } from './helpers';

/** Schlechtes Netz am Stand: verständliche Meldung statt stummem Fehlschlag. */

test('Ohne Netz bleibt nichts hängen, erneutes Tippen trägt genau einmal ein', async ({
    browser,
}) => {
    const helga = await as(browser, 'helga.schiesswart@demo.test');
    await helga.goto('/stand');
    await helga.getByRole('button', { name: 'Eintragen' }).first().click();
    await helga.getByLabel('Mitglied suchen').fill('Lena');
    await helga.getByRole('button', { name: /Lena Langwaffe/ }).click();
    const discipline = helga.locator('#discipline');
    if (
        (await discipline.count()) > 0 &&
        (await discipline.inputValue()) === ''
    ) {
        await discipline.selectOption({ index: 1 });
    }

    await helga.context().setOffline(true);
    await helga
        .getByRole('button', { name: 'Eintragen', exact: true })
        .last()
        .click();
    await expect(
        helga.getByText('Keine Verbindung – nichts gespeichert.'),
    ).toBeVisible();

    await helga.context().setOffline(false);
    await helga
        .getByRole('button', { name: 'Eintragen', exact: true })
        .last()
        .click();
    await expect(
        helga.getByRole('status').filter({ hasText: 'eingetragen um' }),
    ).toBeVisible();
    await expect(
        helga
            .locator('section[aria-labelledby="today"] li')
            .filter({ hasText: 'Lena Langwaffe' }),
    ).toHaveCount(1);
});

test('Abgelaufene Sitzung: Hinweis und neu laden statt Fehlerseite', async ({
    browser,
}) => {
    const erika = await as(browser, 'erika.musterfrau@demo.test');
    await erika.goto('/buchungen');
    // So antwortet Laravel, wenn das CSRF-Token der offenen Seite abgelaufen ist.
    await erika.route('**/buchungen/**', (route) =>
        route.request().method() === 'DELETE'
            ? route.fulfill({
                  status: 419,
                  contentType: 'text/html',
                  body: '<h1>Page Expired</h1>',
              })
            : route.continue(),
    );
    await erika.getByRole('button', { name: 'Stornieren' }).first().click();
    await expect(erika.getByText('Die Sitzung ist abgelaufen.')).toBeVisible();
    await expect(erika.getByText('Page Expired')).toHaveCount(0);
    await erika.waitForEvent('load');
    await expect(erika).toHaveURL(/buchungen/);
});
