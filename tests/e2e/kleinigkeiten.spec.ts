import { execFileSync } from 'node:child_process';
import { expect, test, type Page } from '@playwright/test';
import { as, login, PASSWORD, startSession } from './helpers';

/**
 * Kleinere Fehler aus dem Rollen-Rundgang (Abschnitt „Weitere“): Vorbelegung ohne
 * abgelehnte Einträge, Hinweis „Haken nötig“ und die eigene Kladde am Handy.
 *
 * Eigene Personen, damit andere Specs (Demo-Mitglieder) diese Abläufe nicht stören:
 * flows.spec.ts erfasst vorher für Max, danach wäre sein jüngster Eintrag nicht mehr
 * der abgelehnte mit 999 Schüssen.
 */

const ROLF = 'rolf.rueckblick@e2e.test';
const VERA = 'vera.vorbelegung@e2e.test';

test.beforeAll(() => {
    // Wie DemoSeeder::signedDemoSession für Max: gestern 40 Schuss Langwaffe eigene
    // abgezeichnet, dazu ein abgelehnter Eintrag mit 999 Schüssen Kurzwaffe Verein.
    const php = `
        $make = function (string $name, string $email, string $nr, bool $adult = true) {
            $u = App\\Models\\User::where('email', $email)->first() ?? new App\\Models\\User;
            $u->forceFill(['name' => $name, 'email' => $email, 'password' => '${PASSWORD}', 'email_verified_at' => now(), 'member_number' => $nr, 'adult_confirmed_at' => $adult ? now() : null])->save();
            return $u;
        };
        $rolf = $make('Rolf Rückblick', '${ROLF}', 'E2E-21');
        if (! $rolf->canSupervise(licensed: true)) {
            App\\Models\\Qualification::register($rolf, App\\Enums\\SupervisionScope::Licensed, now()->addYear()->toDateString(), now()->subMonth()->toDateString(), null, 'E2E');
        }
        $vera = $make('Vera Vorbelegung', '${VERA}', 'E2E-22');
        $make('Udo Ungeprüft', 'udo.ungeprueft@e2e.test', 'E2E-23', false);
        if (! App\\Models\\SessionParticipant::where('user_id', $vera->id)->exists()) {
            Illuminate\\Support\\Carbon::setTestNow(now()->subDay()->setTime(18, 30));
            try {
                $sessions = app(App\\Domain\\Sessions::class);
                $session = $sessions->open($rolf);
                $forVera = $sessions->addMember($rolf, $session, $vera, true);
                $own = $sessions->stage($vera, $forVera, App\\Enums\\EntryVia::OwnDevice, App\\Enums\\WeaponClass::Long, App\\Enums\\WeaponOwner::Own, 40)['entry'];
                $typo = $sessions->stage($vera, $forVera, App\\Enums\\EntryVia::OwnDevice, App\\Enums\\WeaponClass::Short, App\\Enums\\WeaponOwner::Club, 999)['entry'];
                $sessions->reject($rolf, $typo, 'Fehleingabe: 999 Schüsse');
                $sessions->signOff($rolf, $session, [$own->id]);
            } finally {
                Illuminate\\Support\\Carbon::setTestNow();
            }
        }
    `;
    execFileSync('php', ['artisan', 'tinker', '--execute', php], {
        env: {
            ...process.env,
            DB_CONNECTION: 'sqlite',
            DB_DATABASE: 'database/e2e.sqlite',
        },
        // tinker schreibt Exceptions auf stdout; geerbt steht die Ursache im Log.
        stdio: ['ignore', 'inherit', 'inherit'],
    });
});

/** Abschnitt „Teilnehmer dieser Session“ auf /stand. */
function participants(page: Page) {
    return page.locator('section[aria-labelledby="participants"]');
}

test('„Heute schießen“: ein abgelehnter Eintrag mit 999 Schüssen belegt nichts vor, es gilt die letzte Zeile der Kladde', async ({
    browser,
}) => {
    const rolf = await as(browser, ROLF);
    await rolf.goto('/stand');
    await startSession(rolf);
    const area = participants(rolf);
    await area.getByLabel('Mitglied aktivieren').fill('Vera');
    await area.getByRole('button', { name: /^Vera Vorbelegung/ }).click();
    await expect(
        area.getByRole('button', {
            name: 'Vera Vorbelegung aus der Session entfernen',
        }),
    ).toBeVisible();

    const vera = await as(browser, VERA);
    await vera.goto('/heute');
    const form = vera.getByRole('region', { name: 'Neuer Eintrag' });
    await expect(form).toBeVisible();
    await expect(form.getByLabel('Schüsse')).not.toHaveValue('999');
    await expect(form.getByLabel('Schüsse')).toHaveValue('40');
    await expect(
        form.getByRole('button', { name: 'Langwaffe', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');
    await expect(
        form.getByRole('button', { name: 'eigene', exact: true }),
    ).toHaveAttribute('aria-pressed', 'true');
});

test('Mitglied aktivieren: „Haken nötig“ verschwindet, sobald der Haken gesetzt ist', async ({
    browser,
}) => {
    const rolf = await as(browser, ROLF);
    await rolf.goto('/stand');
    await startSession(rolf);
    const area = participants(rolf);
    const search = area.getByLabel('Mitglied aktivieren');
    const adult = area.getByRole('checkbox', { name: /volljährig geprüft/ });
    const udo = area.getByRole('button', { name: /^Udo Ungeprüft/ });

    await search.fill('Udo');
    await expect(udo).toBeDisabled();
    await expect(udo).toContainText('Haken „volljährig geprüft“ nötig');

    // Die Vorschlagsliste liegt über dem Haken: erst leeren, dann anklicken.
    await search.fill('');
    await adult.click();
    await expect(adult).toBeChecked();
    await search.fill('Udo');
    await expect(udo).toBeEnabled();
    await expect(udo).not.toContainText('nötig');
    await expect(udo).toContainText('E2E-23');

    await search.fill('');
    await adult.click();
    await expect(adult).not.toBeChecked();
    await search.fill('Udo');
    await expect(udo).toBeDisabled();
    await expect(udo).toContainText('Haken „volljährig geprüft“ nötig');
});

test.describe('am Handy', () => {
    test.use({
        viewport: { width: 390, height: 844 },
        hasTouch: true,
        isMobile: true,
    });

    test('Eigene Kladde: die Spalte Schüsse steht ohne seitliches Wischen im Bild', async ({
        page,
    }) => {
        await login(page, 'max.mustermann@demo.test');
        await page.goto('/schiesskladde');
        await page.getByRole('button', { name: 'letzte 24 Monate' }).click();
        await expect(
            page.getByRole('heading', { name: 'Termine' }),
        ).toBeVisible();

        // Die Seite selbst schneidet seitlich ab (overflow-x-clip); gescrollt hat bisher
        // der Rahmen um die Tabelle.
        const wrapper = page.locator(
            'section[aria-labelledby="entries"] div.overflow-x-auto',
        );
        const table = wrapper.getByRole('table');
        const header = table.getByRole('columnheader', {
            name: 'Schüsse',
            exact: true,
        });
        // Vierte Zelle: Datum, Kategorie, Waffe (am Handy ausgeblendet), Schüsse
        const shots = table.locator('tbody tr').first().locator('td').nth(3);
        await expect(header).toBeVisible();
        await expect(shots).toBeVisible();
        await expect(
            table.getByRole('columnheader', { name: 'Waffe', exact: true }),
        ).toBeHidden();

        const scroll = await wrapper.evaluate(
            (el) => el.scrollWidth - el.clientWidth,
        );
        expect(scroll, `Tabelle scrollt seitlich um ${scroll}px`).toBe(0);

        const frame = (await wrapper.boundingBox())!;
        for (const cell of [header, shots]) {
            const box = (await cell.boundingBox())!;
            expect(box.x + box.width).toBeLessThanOrEqual(
                frame.x + frame.width + 0.5,
            );
        }

        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );
        expect(overflow).toBeLessThanOrEqual(0);
    });
});
