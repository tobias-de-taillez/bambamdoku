import {
    existsSync,
    readdirSync,
    readFileSync,
    rmSync,
    statSync,
} from 'node:fs';
import { join } from 'node:path';
import { expect, test, type Page } from '@playwright/test';
import { login } from './helpers';

/** „Report Bug / Request Feature“: Knopf auf jeder Seite, Meldung mit Bildschirmfoto und Zustand landet auf dem Server. */

const HILFE = 'storage/app/private/hilfe';

function folderOf(reference: string): string | null {
    if (!existsSync(HILFE)) {
        return null;
    }

    for (const day of readdirSync(HILFE)) {
        const match = readdirSync(join(HILFE, day)).find((d) =>
            d.endsWith(`-${reference}`),
        );

        if (match) {
            return join(HILFE, day, match);
        }
    }

    return null;
}

async function report(page: Page, text: string): Promise<string> {
    const button = page.getByRole('button', {
        name: 'Report Bug / Request Feature',
    });
    await expect(button).toBeVisible();
    await button.click();

    const dialog = page.getByRole('dialog', {
        name: 'Report Bug / Request Feature',
    });
    await expect(dialog).toBeVisible();
    await dialog
        .getByLabel('Was ist kaputt oder was sollte besser werden?')
        .fill(text);
    // Bildschirmfoto fertig (Vorschau) oder ausdrücklich ohne
    await expect(
        dialog
            .getByRole('img', { name: 'Vorschau des Bildschirmfotos' })
            .or(dialog.getByText('Kein Bildschirmfoto möglich')),
    ).toBeVisible({ timeout: 15_000 });
    await dialog.getByRole('button', { name: 'Absenden' }).click();

    await expect(
        dialog.getByText('Danke! Ihre Meldung ist angekommen.'),
    ).toBeVisible();
    const reference =
        (await dialog.getByTestId('help-reference').textContent())?.trim() ??
        '';
    expect(reference).toMatch(/^[A-Z2-9]{6}$/);

    return reference;
}

test('Meldung von der Startseite: Bildschirmfoto und geschwärzter Zustand liegen auf dem Server', async ({
    page,
}) => {
    await page.goto('/');
    const reference = await report(page, 'E2E: Die Startseite lädt langsam.');

    const folder = folderOf(reference);
    expect(folder).not.toBeNull();

    try {
        const meldung = JSON.parse(
            readFileSync(join(folder!, 'meldung.json'), 'utf8'),
        );
        expect(meldung.kennung).toBe(reference);
        expect(meldung.nutzer).toBe('Gast');
        expect(meldung.text).toBe('E2E: Die Startseite lädt langsam.');
        expect(meldung.komponente).toBe('Welcome');
        expect(meldung.state.page.component).toBe('Welcome');
        expect(meldung.state.client.userAgent).toContain('Chrome');
        expect(meldung.state.navigations.length).toBeGreaterThan(0);
        expect(JSON.stringify(meldung)).not.toContain('XSRF');
        expect(meldung.bildschirmfoto).toBe('screenshot.jpg');
        expect(statSync(join(folder!, 'screenshot.jpg')).size).toBeGreaterThan(
            2000,
        );
    } finally {
        rmSync(folder!, { recursive: true, force: true });
    }

    // Schließen und der Knopf ist wieder da
    await page.getByRole('button', { name: 'Schließen', exact: true }).click();
    await expect(
        page.getByRole('dialog', { name: 'Report Bug / Request Feature' }),
    ).toBeHidden();
    await expect(
        page.getByRole('button', { name: 'Report Bug / Request Feature' }),
    ).toBeFocused();
});

test('Knopf auf Anmeldung und Fehlerseite, Meldung nach Login erscheint in den Rückmeldungen', async ({
    page,
}) => {
    await page.goto('/login');
    await expect(
        page.getByRole('button', { name: 'Report Bug / Request Feature' }),
    ).toBeVisible();

    await page.goto('/gibt-es-nicht');
    await expect(
        page.getByRole('heading', { name: 'Seite nicht gefunden' }),
    ).toBeVisible();
    await expect(
        page.getByRole('button', { name: 'Report Bug / Request Feature' }),
    ).toBeVisible();

    await login(page, 'petra.vorstand@demo.test');
    await page.goto('/schiesstage');
    const reference = await report(
        page,
        'E2E: Auf der Terminliste fehlt ein Filter.',
    );
    await page.keyboard.press('Escape');
    await expect(
        page.getByRole('dialog', { name: 'Report Bug / Request Feature' }),
    ).toBeHidden();

    const folder = folderOf(reference);

    try {
        const meldung = JSON.parse(
            readFileSync(join(folder!, 'meldung.json'), 'utf8'),
        );
        expect(meldung.nutzer.name).toContain('Petra');
        expect(meldung.state.page.component).toBe('shooting-days/Index');

        await page.goto('/verwaltung/rueckmeldungen');
        await page.getByRole('link', { name: new RegExp(reference) }).click();
        await expect(
            page.getByRole('heading', { name: `Rückmeldung ${reference}` }),
        ).toBeVisible();
        await expect(
            page.getByText('E2E: Auf der Terminliste fehlt ein Filter.'),
        ).toBeVisible();
        const image = page.getByRole('img', {
            name: `Bildschirmfoto zur Rückmeldung ${reference}`,
        });
        await expect(image).toBeVisible();
        expect(
            await image.evaluate((img: HTMLImageElement) => img.naturalWidth),
        ).toBeGreaterThan(0);

        await page.getByRole('button', { name: 'Erledigt' }).click();
        await expect(
            page.getByRole('button', { name: 'Wieder öffnen' }),
        ).toBeVisible();
    } finally {
        if (folder) {
            rmSync(folder, { recursive: true, force: true });
        }
    }
});
