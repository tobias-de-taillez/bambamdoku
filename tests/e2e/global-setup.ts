import { execFileSync, execSync } from 'node:child_process';
import { rmSync, writeFileSync } from 'node:fs';

/**
 * Frische Datenbank mit Demo-Verein für jeden Lauf. Den Termin, den der DemoSeeder mittwochs
 * und samstags für heute anlegt, nimmt sie wieder heraus: day.spec.ts plant heute einen
 * eigenen Tag auf allen Ständen, und zwei Tage auf denselben Ständen dürfen sich nicht
 * überschneiden. So hängt kein Test vom Wochentag ab.
 */
export default function globalSetup(): void {
    const env = {
        ...process.env,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: 'database/e2e.sqlite',
    };
    rmSync('test-results/.auth', { recursive: true, force: true });
    writeFileSync('database/e2e.sqlite', '');
    execSync('php artisan migrate:fresh --force --seed --seeder=DemoSeeder', {
        stdio: 'inherit',
        env,
    });
    execFileSync(
        'php',
        [
            'artisan',
            'tinker',
            '--execute',
            "App\\Models\\ShootingDay::whereDate('date', today())->get()->each->delete();",
        ],
        { stdio: 'pipe', env },
    );
}
