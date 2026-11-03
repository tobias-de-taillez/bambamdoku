import { expect, type Locator, type Page } from '@playwright/test';

/** Handgriffe am Stand, die mehrere Geschichten brauchen. Die Seite gehört der Aufsicht. */

export async function sessionStarten(aufsicht: Page): Promise<void> {
    await aufsicht.goto('/stand');
    const start = aufsicht.getByRole('button', { name: 'Session starten' });

    if (await start.isVisible()) {
        await start.click();
    }

    await expect(aufsicht.getByText(/Session seit/)).toBeVisible();
}

/** Abschnitt „Teilnehmer dieser Session“. */
export function teilnehmer(aufsicht: Page): Locator {
    return aufsicht.locator('section[aria-labelledby="participants"]');
}

/** Zeile einer aktivierten Person in der Session. */
export function teilnehmerZeile(aufsicht: Page, name: string): Locator {
    return teilnehmer(aufsicht).locator('ul > li').filter({ hasText: name });
}

/**
 * Über die Suche aktivieren, nur im Abschnitt der Teilnehmer: An einem Schießtag stehen
 * darüber die Buchungen, mit Knöpfen wie „Lena Langwaffe nicht erschienen“.
 */
export async function aktivieren(aufsicht: Page, name: string): Promise<void> {
    const bereich = teilnehmer(aufsicht);
    await bereich.getByLabel('Mitglied aktivieren').fill(name.split(' ')[0]);
    await bereich.getByRole('button', { name: new RegExp(`^${name}`) }).click();
    // Die Zeile mit „Entfernen“ gibt es erst, wenn die Aktivierung gespeichert ist.
    await expect(
        bereich.getByRole('button', {
            name: `${name} aus der Session entfernen`,
        }),
    ).toBeVisible();
}

/** Das Erfassungsformular der Aufsicht (nicht das versteckte Formular der Rückmeldung). */
export function erfassungsformular(aufsicht: Page): Locator {
    return aufsicht
        .locator('form')
        .filter({ has: aufsicht.locator('#entry-shots') });
}

/** Aufsicht erfasst selbst für eine aktivierte Person; Kategorie, Waffe und Schüsse immer ausdrücklich. */
export async function erfassen(
    aufsicht: Page,
    name: string,
    eintrag: { kategorie: string; waffe: string; schuesse: string },
): Promise<void> {
    const zeile = teilnehmerZeile(aufsicht, name);
    await zeile.getByRole('button', { name: 'Erfassen' }).click();
    const formular = erfassungsformular(aufsicht);
    await expect(formular).toContainText(name);
    await formular
        .getByRole('button', { name: eintrag.kategorie, exact: true })
        .click();
    await formular
        .getByRole('button', { name: eintrag.waffe, exact: true })
        .click();
    await formular.locator('#entry-shots').fill(eintrag.schuesse);
    await formular
        .getByRole('button', { name: 'Erfassen', exact: true })
        .click();
    await expect(zeile).toContainText(`${eintrag.schuesse} Schüsse`);
}

/** Selbsteintrag auf „Heute schießen“ am eigenen Handy. */
export async function selbstEintragen(
    page: Page,
    eintrag: { kategorie: string; schuesse: string; waffe?: string },
): Promise<void> {
    await page
        .getByRole('button', { name: eintrag.kategorie, exact: true })
        .click();
    await page
        .getByRole('button', { name: eintrag.waffe ?? 'eigene', exact: true })
        .click();
    await page.getByLabel('Schüsse').fill(eintrag.schuesse);
    await page.getByRole('button', { name: 'Erfassen', exact: true }).click();
    await expect(
        page.getByText(/Es zählt, sobald die Aufsicht die Session/),
    ).toBeVisible();
}

/** Kiosk freigeben und den Link für ein anderes Gerät holen. */
export async function kioskLink(aufsicht: Page): Promise<string> {
    await aufsicht.getByRole('button', { name: 'Kiosk', exact: true }).click();
    await aufsicht
        .getByRole('button', { name: 'Link für ein anderes Gerät' })
        .click();
    const url =
        (
            await aufsicht.locator('[data-test="kiosk-url"]').textContent()
        )?.trim() ?? '';
    expect(url).toContain('/kiosk/');

    return url;
}

/** Liste „Selbst eintragen“ am Kiosk (die einzige Liste im Hauptbereich). */
export function kioskListe(kiosk: Page): Locator {
    return kiosk.getByRole('main').getByRole('list');
}

/** Am Kiosk: Person wählen und die PIN über die Zifferntastatur eingeben. */
export async function kioskPin(
    kiosk: Page,
    name: string,
    pin: string,
): Promise<void> {
    await kiosk.getByRole('button', { name: new RegExp(name) }).click();

    for (const ziffer of pin) {
        await kiosk
            .getByRole('group', { name: 'Zifferntastatur' })
            .getByRole('button', { name: ziffer, exact: true })
            .click();
    }

    await kiosk.getByRole('button', { name: 'Weiter' }).click();
}

/** Abzeichnen bis zur Bestätigung „Heute abgezeichnet“. */
export async function abzeichnen(aufsicht: Page): Promise<void> {
    await aufsicht.getByRole('button', { name: 'Abzeichnen' }).click();
    await expect(
        aufsicht.getByText(/unter meiner Aufsicht geschossen/),
    ).toBeVisible();
    await aufsicht
        .getByRole('button', { name: 'Bestätigen und abzeichnen' })
        .click();
    await expect(
        aufsicht.getByRole('heading', { name: 'Heute abgezeichnet' }),
    ).toBeVisible();
}
