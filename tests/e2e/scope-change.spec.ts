import { execFileSync } from 'node:child_process';
import {
    devices,
    expect,
    test,
    type Browser,
    type Page,
} from '@playwright/test';
import {
    as,
    failOnConsoleErrors,
    login,
    PASSWORD,
    startSession,
} from './helpers';

/**
 * Die Aufsicht verliert während der Session den Umfang erlaubnispflichtig: Ein Mitglied hat
 * „Heute schießen“ offen und Kurzwaffe gewählt, der Vorstand stuft Helga im Aufsichtsregister
 * auf erlaubnisfrei herab. Ohne Neuladen weist der Server die Kurzwaffe ab, die Meldung bleibt
 * unter der Kategorie stehen und die Auswahl springt auf erlaubnisfrei. Ohne Absenden bringt
 * der Poll denselben Wechsel.
 *
 * Helga ist die Aufsicht mit Umfang erlaubnispflichtig aus dem Demo-Verein. Alle Specs teilen
 * die Datenbank, darum stellt afterAll ihren Umfang wieder her. Das Mitglied ist eine eigene
 * Person. Ohne Schießtag und Buchung, darum an jedem Wochentag und ohne Nachtpause.
 */

const HELGA = 'helga.schiesswart@demo.test';
const MEMBER = 'uwe.umfang@e2e.test';
const SCOPE_ERROR =
    'Für Kurz- und Langwaffen braucht die Aufsicht der Session den Umfang „erlaubnispflichtige Waffen“ im Register.';

/** Helga wie im DemoSeeder mit Umfang erlaubnispflichtig, auch nach einem abgebrochenen Lauf. */
const HELGA_LICENSED = `
    $helga = App\\Models\\User::where('email', '${HELGA}')->firstOrFail();
    if (! $helga->canSupervise(licensed: true)) {
        App\\Models\\Qualification::register($helga, App\\Enums\\SupervisionScope::Licensed, now()->addYear()->toDateString(), now()->toDateString(), null, 'E2E: Umfang wiederhergestellt');
    }
`;

function tinker(php: string): void {
    // tinker schreibt Exceptions auf stdout; geerbt steht die Ursache im Log.
    execFileSync('php', ['artisan', 'tinker', '--execute', php], {
        stdio: ['ignore', 'inherit', 'inherit'],
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: 'database/e2e.sqlite',
        },
    });
}

test.beforeAll(() => {
    tinker(`
        $u = App\\Models\\User::where('email', '${MEMBER}')->first() ?? new App\\Models\\User;
        $u->forceFill(['name' => 'Uwe Umfang', 'email' => '${MEMBER}', 'password' => '${PASSWORD}', 'email_verified_at' => now(), 'member_number' => 'E2E-6', 'adult_confirmed_at' => now()])->save();
        ${HELGA_LICENSED}
    `);
});

test.afterAll(() => {
    tinker(HELGA_LICENSED);
});

async function phone(browser: Browser, email: string): Promise<Page> {
    const page = await (
        await browser.newContext({ ...devices['Pixel 7'] })
    ).newPage();
    await login(page, email);

    return page;
}

/** Über die Suche aktivieren, nur im Abschnitt der Teilnehmer (darüber stehen Buchungen). */
async function activate(supervisor: Page, name: string): Promise<void> {
    const participants = supervisor.locator(
        'section[aria-labelledby="participants"]',
    );
    await participants
        .getByLabel('Mitglied aktivieren')
        .fill(name.split(' ')[0]);
    await participants
        .getByRole('button', { name: new RegExp(`^${name}`) })
        .click();
    await expect(
        participants.getByRole('button', {
            name: `${name} aus der Session entfernen`,
        }),
    ).toBeVisible();
}

/** Neue Zeile im Aufsichtsregister ausfüllen, ohne abzusenden. */
async function fillRegister(
    petra: Page,
    scope: 'exempt' | 'licensed',
): Promise<void> {
    await petra.getByRole('button', { name: 'Prüfung eintragen' }).click();
    await petra.locator('#reg-scope').selectOption(scope);
    await petra
        .locator('#reg-until')
        .fill(`${new Date().getFullYear() + 1}-12-31`);
    await petra
        .locator('#reg-note')
        .fill(
            scope === 'exempt'
                ? 'E2E: herabgestuft'
                : 'E2E: wieder erlaubnispflichtig',
        );
    await petra.getByRole('checkbox', { name: /Ich habe geprüft/ }).click();
}

/** Ausgefüllte Zeile absenden und warten, bis sie die aktuelle ist. */
async function submitRegister(petra: Page, label: string): Promise<void> {
    const register = petra.locator('section[aria-labelledby="register"]');
    await register.getByRole('button', { name: 'Eintragen' }).click();
    const current = register.getByRole('listitem').first();
    await expect(current).toContainText(label);
    await expect(current).toContainText('aktuell');
}

test('Aufsicht verliert den Umfang erlaubnispflichtig: Kurzwaffe wird abgewiesen, die Auswahl springt auf erlaubnisfrei', async ({
    browser,
}) => {
    // Der zweite Teil wartet auf den Poll, der alle 30 Sekunden läuft.
    test.setTimeout(120_000);

    const helga = await as(browser, HELGA);
    await helga.goto('/stand');
    await startSession(helga);
    await activate(helga, 'Uwe Umfang');

    const petra = await as(browser, 'petra.vorstand@demo.test');
    await petra.goto('/verwaltung/mitglieder');
    await petra
        .getByRole('link', { name: 'Helga Schießwart', exact: true })
        .click();
    await expect(petra.locator('#name')).toHaveValue('Helga Schießwart');
    await fillRegister(petra, 'exempt');

    // Ab dem Laden bis zum Absenden nur wenige Sekunden: Der Poll (30 Sekunden nach dem Laden)
    // brächte den neuen Umfang sonst schon vorher, und abgesendet würde erlaubnisfrei.
    const uwe = await phone(browser, MEMBER);
    const errors = failOnConsoleErrors(uwe);
    await uwe.goto('/heute');
    await expect(uwe.getByText('Aufsicht Helga Schießwart')).toBeVisible();
    const category = uwe.getByRole('group', { name: 'Kategorie' });
    const short = category.getByRole('button', {
        name: 'Kurzwaffe',
        exact: true,
    });
    const exempt = category.getByRole('button', {
        name: 'erlaubnisfrei',
        exact: true,
    });
    await short.click();
    await uwe
        .getByRole('button', { name: 'Vereinswaffe', exact: true })
        .click();
    await uwe.getByLabel('Schüsse').fill('20');

    await submitRegister(petra, 'nur erlaubnisfreie Waffen');

    // Noch kein Poll seit dem Laden: Kurzwaffe ist weiter gewählt und frei.
    await expect(short).toHaveAttribute('aria-pressed', 'true');
    await expect(short).toBeEnabled();
    const submit = uwe.getByRole('button', { name: 'Erfassen', exact: true });
    await submit.click();

    await expect(category.getByText(SCOPE_ERROR)).toBeVisible();
    await expect(exempt).toHaveAttribute('aria-pressed', 'true');
    await expect(short).toHaveAttribute('aria-pressed', 'false');
    await expect(short).toBeDisabled();
    await expect(
        uwe.locator('section[aria-labelledby="own-entries"]'),
    ).toHaveCount(0);

    await submit.click();
    await expect(
        uwe.getByText(/Es zählt, sobald die Aufsicht die Session/),
    ).toBeVisible();
    const own = uwe.locator('section[aria-labelledby="own-entries"] li');
    await expect(own).toHaveCount(1);
    await expect(own).toContainText('erlaubnisfrei');
    await expect(uwe.getByText(SCOPE_ERROR)).toHaveCount(0);

    // Zweiter Teil ohne Absenden: wieder erlaubnispflichtig, Kurzwaffe gewählt, herabgestuft.
    await fillRegister(petra, 'licensed');
    await submitRegister(petra, 'auch erlaubnispflichtige Waffen');
    await uwe.reload();
    await expect(short).toBeEnabled();
    await short.click();
    await expect(short).toHaveAttribute('aria-pressed', 'true');

    await fillRegister(petra, 'exempt');
    await submitRegister(petra, 'nur erlaubnisfreie Waffen');

    // Der Poll bringt den neuen Umfang, die Auswahl springt von selbst.
    await expect(exempt).toHaveAttribute('aria-pressed', 'true', {
        timeout: 40_000,
    });
    await expect(short).toHaveAttribute('aria-pressed', 'false');
    await expect(short).toBeDisabled();
    await expect(short).toHaveAttribute(
        'title',
        'Die Aufsicht dieser Session hat nur den Umfang erlaubnisfreie Waffen.',
    );
    await expect(uwe.getByText(SCOPE_ERROR)).toHaveCount(0);
    await expect(own).toHaveCount(1);
    expect(errors).toEqual([]);
});
