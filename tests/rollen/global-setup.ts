import { execFileSync, execSync } from 'node:child_process';
import { writeFileSync } from 'node:fs';
import { frischerVerein, VORLAGE } from './lauf';

/**
 * Wie an einem Mittwoch oder Samstag: ein Demo-Termin heute, Helga hat Aufsicht, vier
 * Mitglieder haben gebucht. Mit ROLLEN_TERMIN_HEUTE=1 lässt sich so an jedem Wochentag
 * prüfen, dass keine Geschichte vom Wochentag abhängt.
 */
const TERMIN_HEUTE = `
    $bookings = app(App\\Domain\\Bookings::class);
    $petra = App\\Models\\User::where('email', 'petra.vorstand@demo.test')->firstOrFail();
    $helga = App\\Models\\User::where('email', 'helga.schiesswart@demo.test')->firstOrFail();
    $ranges = App\\Models\\Range::orderBy('position')->take(3)->get();
    $day = App\\Models\\ShootingDay::create(['date' => today()->toDateString(), 'starts_at' => '00:00', 'ends_at' => '23:59', 'title' => 'Trainingsabend']);
    $day->ranges()->sync($ranges->pluck('id'));
    $day->load('ranges');
    $bookings->assignSupervision($petra, $day, $helga, null, App\\Domain\\TimeWindow::fromStrings('00:00', '23:59'));
    foreach (['max.mustermann', 'erika.musterfrau', 'karl.kurzwaffe', 'lena.langwaffe'] as $i => $slug) {
        $member = App\\Models\\User::where('email', $slug.'@demo.test')->firstOrFail();
        $range = $ranges->first();
        $from = App\\Domain\\TimeWindow::minutes('22:00') + ($i % 2) * 60;
        $bookings->book($member, $day, $range, new App\\Domain\\TimeWindow($from, min($from + 60, App\\Domain\\TimeWindow::minutes('23:59'))), $range->weapon_types->first(), $i % 2 === 0, $member);
    }
`;

/** Demo-Verein einmal je Lauf in die Vorlage seeden; die Geschichten kopieren sie. */
export default function globalSetup(): void {
    const env = {
        ...process.env,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: VORLAGE,
    };
    writeFileSync(VORLAGE, '');
    execSync('php artisan migrate:fresh --force --seed --seeder=DemoSeeder', {
        stdio: 'pipe',
        env,
    });

    if (process.env.ROLLEN_TERMIN_HEUTE === '1') {
        execFileSync('php', ['artisan', 'tinker', '--execute', TERMIN_HEUTE], {
            stdio: 'pipe',
            env,
        });
    }

    frischerVerein();
}
