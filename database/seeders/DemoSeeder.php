<?php

namespace Database\Seeders;

use App\Domain\Bookings;
use App\Domain\TimeWindow;
use App\Enums\BookingStatus;
use App\Enums\LogSource;
use App\Enums\Role;
use App\Enums\SupervisionScope;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Enums\WeaponType;
use App\Models\LogEntry;
use App\Models\Qualification;
use App\Models\Range;
use App\Models\ShootingDay;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * Beispielverein zum Ausprobieren: php artisan db:seed --class=DemoSeeder
 *
 * Alle Demo-Konten haben das Passwort "demo-passwort" und E-Mail-Adressen
 * unter @demo.test (diese Domain ist reserviert, es geht keine Mail raus).
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo-passwort';

    public function run(Bookings $bookings): void
    {
        $ranges = collect([
            ['name' => '25 m Kurzwaffe', 'lanes' => 6, 'weapon_types' => [WeaponType::Pistol]],
            ['name' => '50 m Gewehr', 'lanes' => 8, 'weapon_types' => [WeaponType::Rifle, WeaponType::Airgun]],
            ['name' => '10 m Luftdruck', 'lanes' => 10, 'weapon_types' => [WeaponType::Airgun]],
            ['name' => 'Bogenwiese', 'lanes' => 6, 'weapon_types' => [WeaponType::Bow]],
        ])->map(fn (array $data, int $i) => Range::firstOrCreate(['name' => $data['name']], [...$data, 'position' => $i]));

        $person = function (string $name, Role $role = Role::Member, bool $supervisor = false): User {
            $user = User::where('email', Str::slug($name, '.').'@demo.test')->first() ?? new User;
            $user->forceFill([
                'name' => $name,
                'email' => Str::slug($name, '.').'@demo.test',
                'password' => self::PASSWORD,
                'role' => $role,
                'email_verified_at' => now(),
                'member_number' => (string) (1000 + (User::max('id') ?? 0) + 1),
                'member_since' => now()->subYears(3 + strlen($name) % 12)->startOfMonth()->toDateString(),
            ])->save();

            if ($supervisor && ! $user->canSupervise(licensed: true)) {
                Qualification::register($user, SupervisionScope::Licensed, now()->addYears(2)->toDateString(), now()->subMonths(3)->toDateString(), null, 'Demo: Lehrgang Standaufsicht und Waffensachkunde');
            }

            return $user;
        };

        $chair = $person('Petra Vorstand', Role::Admin, true);
        $helga = $person('Helga Schießwart', Role::Member, true);
        $jonas = $person('Jonas Aufsicht', Role::Member, true);
        $members = collect([
            $person('Max Mustermann'),
            $person('Erika Musterfrau'),
            $person('Karl Kurzwaffe'),
            $person('Lena Langwaffe'),
            $person('Ben Bogen'),
            $person('Tim Jungschütze'),
        ]);

        // Ein offener Mitgliedsantrag (Selbstregistrierung), wartet auf den Vorstand.
        $applicant = User::where('email', 'anna.antrag@demo.test')->first() ?? new User;
        $applicant->forceFill([
            'name' => 'Anna Antrag',
            'email' => 'anna.antrag@demo.test',
            'password' => self::PASSWORD,
            'role' => Role::Applicant,
            'active' => false,
        ])->save();

        if (ShootingDay::exists()) {
            return;
        }

        $this->history($ranges->values()->all(), [$helga, $jonas], $members->values()->all());

        for ($week = 0; $week < 8; $week++) {
            $wednesday = Carbon::now()->startOfWeek()->addDays(2)->addWeeks($week);
            $saturday = Carbon::now()->startOfWeek()->addDays(5)->addWeeks($week);

            if ($wednesday->isAfter(today()->subDay())) {
                $day = ShootingDay::create(['date' => $wednesday->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00', 'title' => 'Trainingsabend']);
                $day->ranges()->sync($ranges->take(3)->pluck('id'));
            }

            if ($saturday->isAfter(today()->subDay())) {
                $day = ShootingDay::create(['date' => $saturday->toDateString(), 'starts_at' => '10:00', 'ends_at' => '14:00']);
                $day->ranges()->sync($ranges->pluck('id'));
            }
        }

        $youthDay = ShootingDay::create([
            'date' => Carbon::now()->startOfWeek()->addDays(4)->addWeek()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '18:00',
            'title' => 'Jugendtraining',
            'guests_allowed' => false,
            'notes' => 'Nur Luftdruck. Bitte Schießbrillen mitbringen.',
        ]);
        $youthDay->ranges()->sync([$ranges[2]->id]);

        // Die ersten Termine mit Aufsicht und Buchungen füllen, spätere bleiben offen.
        $supervisors = [$helga, $jonas, $chair];

        foreach (ShootingDay::with('ranges')->whereDate('date', '>=', today())->orderBy('date')->take(5)->get() as $i => $day) {
            $start = substr($day->starts_at, 0, 5);
            $end = substr($day->ends_at, 0, 5);
            $supervisor = $supervisors[$i % 3];

            try {
                $bookings->assignSupervision($chair, $day, $supervisor, null, TimeWindow::fromStrings($start, $end));
            } catch (ValidationException) {
                continue;
            }

            foreach ($members->shuffle()->take(4) as $j => $member) {
                $range = $day->ranges->first(fn (Range $r) => $r->weapon_types->contains(WeaponType::Pistol)) ?? $day->ranges->first();
                $from = TimeWindow::minutes($start) + ($j % 3) * 60;

                try {
                    $bookings->book($member, $day, $range, new TimeWindow($from, $from + 60), $range->weapon_types->first(), $j % 2 === 0, $member);
                } catch (ValidationException|HttpException) {
                    // Belegt oder außerhalb des Tages: für Demo-Daten egal.
                }
            }
        }
    }

    /**
     * Vergangene, von der Aufsicht bestätigte Besuche, damit die Schießkladde etwas zeigt.
     *
     * @param  array<int, Range>  $ranges
     * @param  array<int, User>  $supervisors
     * @param  array<int, User>  $members
     */
    private function history(array $ranges, array $supervisors, array $members): void
    {
        $habits = [
            // Name => [Abstand in Tagen, Waffenart, Vereinswaffe]
            'Max Mustermann' => [12, WeaponType::Pistol, false],
            'Erika Musterfrau' => [21, WeaponType::Rifle, false],
            'Karl Kurzwaffe' => [35, WeaponType::Pistol, false],
            'Lena Langwaffe' => [9, WeaponType::Rifle, true],
        ];

        for ($date = today()->subMonths(24); $date->lt(today()); $date = $date->addDays(7)) {
            $day = ShootingDay::create(['date' => $date->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00', 'title' => 'Trainingsabend']);
            $day->ranges()->sync(collect($ranges)->take(3)->pluck('id'));
            $supervisor = $supervisors[$date->weekOfYear % count($supervisors)];
            $day->supervisions()->create(['user_id' => $supervisor->id, 'starts_at' => '17:00', 'ends_at' => '21:00']);

            foreach ($members as $member) {
                if (! isset($habits[$member->name])) {
                    continue;
                }

                [$every, $type, $club] = $habits[$member->name];

                if ((int) $date->diffInDays(today()) % $every >= 7) {
                    continue;
                }

                $range = collect($ranges)->first(fn (Range $r) => $r->weapon_types->contains($type));
                $booking = $day->bookings()->create([
                    'range_id' => $range->id,
                    'user_id' => $member->id,
                    'starts_at' => '18:00',
                    'ends_at' => '19:00',
                    'weapon_type' => $type,
                    'club_weapon' => $club,
                    'booked_by_id' => $member->id,
                ]);
                $booking->forceFill([
                    'status' => BookingStatus::CheckedIn,
                    'checked_in_at' => $date->setTime(18, 2),
                    'checked_in_by_id' => $supervisor->id,
                ])->save();

                // Der Kladde-Eintrag ist der Nachweis, nicht die Buchung.
                LogEntry::create([
                    'user_id' => $member->id,
                    'shot_on' => $date->toDateString(),
                    'weapon_class' => WeaponClass::fromWeaponType($type),
                    'weapon_owner' => $club ? WeaponOwner::Club : WeaponOwner::Own,
                    'recorded_by_id' => $supervisor->id,
                    'source' => LogSource::Stand,
                    'booking_id' => $booking->id,
                ]);
            }
        }
    }
}
