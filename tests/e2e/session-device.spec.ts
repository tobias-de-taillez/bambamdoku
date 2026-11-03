import { execFileSync } from 'node:child_process';
import {
    devices,
    expect,
    test,
    type Browser,
    type Page,
} from '@playwright/test';
import { as, failOnConsoleErrors, login, PASSWORD } from './helpers';

/**
 * Erfassen am eigenen Handy und am Kiosk mit PIN (PR 5 und PR 6):
 * Die Aufsicht startet die Session, aktiviert vor Ort, lehnt ab und zeichnet ab.
 *
 * Eigene Personen, damit andere Specs (Demo-Mitglieder) diesen Ablauf nicht stören.
 */

const SUPERVISOR = 'sven.session@e2e.test';

test.beforeAll(() => {
    const php = `
        $make = function (string $name, string $email, string $nr) {
            $u = App\\Models\\User::where('email', $email)->first() ?? new App\\Models\\User;
            $u->forceFill(['name' => $name, 'email' => $email, 'password' => '${PASSWORD}', 'email_verified_at' => now(), 'member_number' => $nr, 'adult_confirmed_at' => now()])->save();
            return $u;
        };
        $sven = $make('Sven Session', '${SUPERVISOR}', 'E2E-1');
        if (! $sven->canSupervise(licensed: true)) {
            App\\Models\\Qualification::register($sven, App\\Enums\\SupervisionScope::Licensed, now()->addYear()->toDateString(), now()->subMonth()->toDateString(), null, 'E2E');
        }
        $make('Hanna Handy', 'hanna.handy@e2e.test', 'E2E-2');
        $make('Kim Kiosk', 'kim.kiosk@e2e.test', 'E2E-3');
        $make('Olaf Ohnepin', 'olaf.ohnepin@e2e.test', 'E2E-4');
        $make('Paula Poll', 'paula.poll@e2e.test', 'E2E-5');
    `;
    execFileSync('php', ['artisan', 'tinker', '--execute', php], {
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: 'database/e2e.sqlite',
        },
    });
});

async function phone(browser: Browser, email: string): Promise<Page> {
    const page = await (
        await browser.newContext({ ...devices['Pixel 7'] })
    ).newPage();
    await login(page, email);

    return page;
}

async function startSession(sven: Page): Promise<void> {
    await sven.goto('/stand');
    const start = sven.getByRole('button', { name: 'Session starten' });

    if (await start.isVisible()) {
        await start.click();
    }

    await expect(sven.getByText(/Session seit/)).toBeVisible();
}

async function activate(sven: Page, name: string): Promise<void> {
    await sven.getByLabel('Mitglied aktivieren').fill(name.split(' ')[0]);
    await sven.getByRole('button', { name: new RegExp(`^${name}`) }).click();
    // Die Zeile mit „Entfernen“ gibt es erst, wenn die Aktivierung gespeichert ist.
    await expect(
        sven.getByRole('button', {
            name: `${name} aus der Session entfernen`,
        }),
    ).toBeVisible();
}

async function signOff(sven: Page): Promise<void> {
    await sven.getByRole('button', { name: 'Abzeichnen' }).click();
    await expect(
        sven.getByText(/unter meiner Aufsicht geschossen/),
    ).toBeVisible();
    await sven
        .getByRole('button', { name: 'Bestätigen und abzeichnen' })
        .click();
    await expect(
        sven.getByRole('heading', { name: 'Heute abgezeichnet' }),
    ).toBeVisible();
}

test('Mitglied trägt am Handy ein, Aufsicht lehnt ab und zeichnet ab', async ({
    browser,
}) => {
    const sven = await as(browser, SUPERVISOR);
    await startSession(sven);
    await activate(sven, 'Hanna Handy');

    const hanna = await phone(browser, 'hanna.handy@e2e.test');
    const errors = failOnConsoleErrors(hanna);
    await hanna.goto('/dashboard');
    const card = hanna.locator('section[aria-labelledby="today-card"]');
    await expect(card).toContainText('Aktiviert von Sven Session');
    await card.getByRole('link', { name: 'Erfassen' }).click();
    await expect(hanna).toHaveURL(/\/heute$/);

    await hanna.getByRole('button', { name: 'Kurzwaffe' }).click();
    await hanna.getByRole('button', { name: 'eigene' }).click();
    await hanna.getByLabel('Schüsse').fill('30');
    await hanna.getByRole('button', { name: 'Erfassen' }).click();
    await expect(
        hanna.getByText(/Es zählt, sobald die Aufsicht die Session/),
    ).toBeVisible();

    await hanna.getByRole('button', { name: 'Langwaffe' }).click();
    await hanna.getByRole('button', { name: 'Vereinswaffe' }).click();
    await hanna.getByLabel('Schüsse').fill('20');
    await hanna.getByRole('button', { name: 'Erfassen' }).click();
    const own = hanna.locator('section[aria-labelledby="own-entries"] li');
    await expect(own).toHaveCount(2);

    // gleicher Eintrag noch einmal: kein zweiter, Hinweis „schon erfasst“
    await hanna.getByRole('button', { name: 'Langwaffe' }).click();
    await hanna.getByRole('button', { name: 'Vereinswaffe' }).click();
    await hanna.getByLabel('Schüsse').fill('25');
    await hanna.getByRole('button', { name: 'Erfassen' }).click();
    await expect(hanna.getByText(/Schon erfasst \(20 Schüsse\)/)).toBeVisible();
    await expect(own).toHaveCount(2);

    // Die Aufsicht sieht beide Einträge „über eigenes Gerät“ und lehnt die Langwaffe ab.
    await sven.reload();
    const participants = sven.locator(
        'section[aria-labelledby="participants"]',
    );
    await expect(participants).toContainText('erfasst über eigenes Gerät');
    await participants
        .locator('ul.grid > li', { hasText: 'Langwaffe' })
        .getByRole('button', { name: 'Ablehnen' })
        .click();
    await participants
        .getByRole('button', { name: 'Falsche Schusszahl' })
        .click();
    await participants
        .getByRole('button', { name: 'Ablehnen', exact: true })
        .last()
        .click();
    await expect(participants).toContainText('Grund: Falsche Schusszahl');

    await hanna.reload();
    await expect(
        hanna.getByText('Abgelehnt: Falsche Schusszahl'),
    ).toBeVisible();

    await signOff(sven);

    await hanna.reload();
    await expect(
        hanna.getByText('Die Session ist abgezeichnet.', { exact: false }),
    ).toBeVisible();
    await expect(own.filter({ hasText: 'Kurzwaffe' })).toContainText(
        'abgezeichnet',
    );
    expect(errors).toEqual([]);
});

test('Kiosk mit PIN: Mitglied trägt am Tablet ein, Aufsicht zeichnet ab', async ({
    browser,
}) => {
    // Kim setzt die Kiosk-PIN nach Passwortbestätigung.
    const kim = await as(browser, 'kim.kiosk@e2e.test');
    await kim.goto('/settings/security');
    await expect(kim).toHaveURL(/confirm-password/);
    await kim.locator('#password').fill(PASSWORD);
    await kim.locator('[data-test="confirm-password-button"]').click();
    await expect(kim).toHaveURL(/settings\/security/);
    await kim.locator('#kiosk_pin').fill('2468');
    await kim.locator('#kiosk_pin_confirmation').fill('2468');
    await kim.getByRole('button', { name: 'PIN speichern' }).click();
    await expect(kim.locator('[data-test="kiosk-pin-status"]')).toHaveText(
        'Eine Kiosk-PIN ist gesetzt.',
    );
    await expect(kim.locator('#kiosk_pin')).toHaveValue('');

    const sven = await as(browser, SUPERVISOR);
    await startSession(sven);
    await activate(sven, 'Kim Kiosk');
    await activate(sven, 'Olaf Ohnepin');

    await sven.getByRole('button', { name: 'Kiosk', exact: true }).click();
    await sven
        .getByRole('button', { name: 'Link für ein anderes Gerät' })
        .click();
    const kioskUrl = (
        await sven.locator('[data-test="kiosk-url"]').textContent()
    )?.trim();
    expect(kioskUrl).toContain('/kiosk/');

    // Tablet ohne Anmeldung
    const tablet = await (
        await browser.newContext({ ...devices['Galaxy Tab S4'] })
    ).newPage();
    const errors = failOnConsoleErrors(tablet);
    await tablet.goto(kioskUrl!);
    const list = tablet.getByRole('heading', { name: 'Selbst eintragen' });
    await expect(list).toBeVisible();
    await expect(tablet.getByRole('navigation')).toHaveCount(0);
    const olaf = tablet.getByRole('button', { name: /Olaf Ohnepin/ });
    await expect(olaf).toBeDisabled();
    await expect(olaf).toContainText('bitte bei der Aufsicht eintragen');

    await tablet.getByRole('button', { name: /Kim Kiosk/ }).click();
    await tablet.getByLabel('PIN').fill('1111');
    await tablet.getByRole('button', { name: 'Weiter' }).click();
    await expect(tablet.getByText('PIN falsch.')).toBeVisible();

    // Sitzung des Tablets verloren (Standby, Ablauf): Der Kiosk arbeitet weiter,
    // er braucht keine Sitzungsdaten.
    await tablet.context().clearCookies();
    for (const digit of '2468') {
        await tablet
            .getByRole('group', { name: 'Zifferntastatur' })
            .getByRole('button', { name: digit, exact: true })
            .click();
    }
    await expect(tablet.getByLabel('PIN')).toHaveAttribute('type', 'password');
    await tablet.getByRole('button', { name: 'Weiter' }).click();
    await expect(tablet.getByText('Ihr Eintrag für heute')).toBeVisible();

    await tablet.getByRole('button', { name: 'Kurzwaffe' }).click();
    await tablet.getByRole('button', { name: 'Vereinswaffe' }).click();
    await tablet.getByLabel('Schüsse').fill('25');
    await tablet.getByRole('button', { name: 'Erfassen' }).click();
    await expect(
        tablet.locator('[role="status"]', {
            hasText: 'Kim Kiosk: erfasst um',
        }),
    ).toBeVisible();
    await tablet.getByRole('button', { name: 'Zurück zur Liste' }).click();
    await expect(list).toBeVisible();

    await sven.reload();
    await expect(
        sven.locator('section[aria-labelledby="participants"]'),
    ).toContainText('erfasst über Kiosk');
    await signOff(sven);

    await tablet.reload();
    await expect(
        tablet.getByRole('heading', { name: 'Session beendet' }),
    ).toBeVisible();
    expect(errors).toEqual([]);
});

test('„Heute schießen“ offen vor der Aktivierung: Kategorien kommen mit dem Poll', async ({
    browser,
}) => {
    // Der Poll läuft alle 30 Sekunden.
    test.setTimeout(90_000);

    const sven = await as(browser, SUPERVISOR);
    await startSession(sven);

    const paula = await phone(browser, 'paula.poll@e2e.test');
    const errors = failOnConsoleErrors(paula);
    await paula.goto('/heute');
    await expect(
        paula.getByText('Sie sind heute noch nicht aktiviert.'),
    ).toBeVisible();

    await activate(sven, 'Paula Poll');

    // ohne Neuladen: Formular mit Kategorien und dem Umfang der Aufsicht
    const short = paula.getByRole('button', { name: 'Kurzwaffe' });
    await expect(short).toBeVisible({ timeout: 45_000 });
    await expect(short).toBeEnabled();
    await short.click();
    await paula.getByRole('button', { name: 'eigene' }).click();
    await paula.getByLabel('Schüsse').fill('15');
    await paula.getByRole('button', { name: 'Erfassen' }).click();
    const own = paula.locator('section[aria-labelledby="own-entries"] li');
    await expect(own).toHaveCount(1);
    await expect(own).toContainText('Kurzwaffe');

    await sven.reload();
    await signOff(sven);
    expect(errors).toEqual([]);
});
