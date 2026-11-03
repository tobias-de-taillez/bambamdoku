import { expect, type Locator, type Page } from '@playwright/test';

/** Planen und Buchen, von mehreren Geschichten gebraucht. */

/**
 * Formular für einen neuen Schießtag ausfüllen, geöffnet ist nur `stand`. So überschneidet sich
 * der Termin mit keinem Tag des Demo-Vereins, egal welcher Wochentag heute ist. Die Seite gehört
 * dem Vorstand. Gibt die Auswahl der Stände zurück (für das Foto); gespeichert wird mit tagSpeichern().
 */
export async function tagPlanen(
    vorstand: Page,
    tag: {
        datum: string;
        von: string;
        bis: string;
        titel: string;
        stand: string;
    },
): Promise<Locator> {
    await vorstand.goto('/verwaltung/schiesstage/neu');
    await vorstand.locator('#date').fill(tag.datum);
    await vorstand.locator('#starts_at').fill(tag.von);
    await vorstand.locator('#ends_at').fill(tag.bis);
    await vorstand.locator('#title').fill(tag.titel);

    const staende = vorstand.getByRole('group', { name: 'Geöffnete Stände' });
    const andere = staende.locator('label').filter({ hasNotText: tag.stand });

    for (const label of await andere.all()) {
        const box = label.getByRole('checkbox');

        if ((await box.getAttribute('aria-checked')) === 'true') {
            await box.click();
        }
    }

    const ziel = staende.getByRole('checkbox', {
        name: new RegExp(tag.stand),
    });

    if ((await ziel.getAttribute('aria-checked')) !== 'true') {
        await ziel.click();
    }

    await expect(staende.getByRole('checkbox', { checked: true })).toHaveCount(
        1,
    );
    await expect(ziel).toHaveAttribute('aria-checked', 'true');

    return staende;
}

/** Planungsformular abschicken (nach tagPlanen und eventuellen weiteren Angaben). */
export async function tagSpeichern(vorstand: Page): Promise<void> {
    await vorstand
        .getByRole('button', { name: 'Anlegen', exact: true })
        .click();
    await expect(vorstand).toHaveURL(/verwaltung\/schiesstage$/);
}

/** Feld im Buchungsraster eines Standes auf der Seite des Schießtags. */
export function bahnZelle(page: Page, stand: string, zeit: string): Locator {
    return page.getByRole('button', {
        name: new RegExp(`^${stand}, ${zeit} Uhr:`),
    });
}

/** Bahn buchen und warten, bis die eigene Buchung erscheint. Gibt „Meine Buchungen“ zurück. */
export async function bahnBuchen(
    page: Page,
    stand: string,
    zeit: string,
): Promise<Locator> {
    await bahnZelle(page, stand, zeit).click();
    await page.getByRole('button', { name: /Uhr buchen$/ }).click();
    const meine = page.locator('section[aria-labelledby="my-bookings"]');
    await expect(meine).toContainText(zeit);

    return meine;
}
