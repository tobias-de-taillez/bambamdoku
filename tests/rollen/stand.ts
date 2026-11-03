import { expect, type Page } from '@playwright/test';

/** Handgriffe am Stand, die mehrere Geschichten brauchen. Die Seite gehört der Aufsicht. */

export async function sessionStarten(aufsicht: Page): Promise<void> {
    await aufsicht.goto('/stand');
    const start = aufsicht.getByRole('button', { name: 'Session starten' });

    if (await start.isVisible()) {
        await start.click();
    }

    await expect(aufsicht.getByText(/Session seit/)).toBeVisible();
}

export async function aktivieren(aufsicht: Page, name: string): Promise<void> {
    await aufsicht.getByLabel('Mitglied aktivieren').fill(name.split(' ')[0]);
    await aufsicht
        .getByRole('button', { name: new RegExp(`^${name}`) })
        .click();
    // Die Zeile mit „Entfernen“ gibt es erst, wenn die Aktivierung gespeichert ist.
    await expect(
        aufsicht.getByRole('button', {
            name: `${name} aus der Session entfernen`,
        }),
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
