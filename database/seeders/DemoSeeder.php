<?php

namespace Database\Seeders;

use App\Domain\Bookings;
use App\Domain\TimeWindow;
use App\Enums\Role;
use App\Enums\WeaponType;
use App\Models\Group;
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

        $youth = Group::firstOrCreate(['name' => 'Jugend'], ['description' => 'Schützen unter 18 Jahren']);
        $archers = Group::firstOrCreate(['name' => 'Bogen'], ['description' => 'Bogenabteilung']);

        $person = function (string $name, Role $role = Role::Member, bool $supervisor = false, ?Group $group = null): User {
            $user = User::where('email', Str::slug($name, '.').'@demo.test')->first() ?? new User;
            $user->forceFill([
                'name' => $name,
                'email' => Str::slug($name, '.').'@demo.test',
                'password' => self::PASSWORD,
                'role' => $role,
                'email_verified_at' => now(),
                'member_number' => (string) (1000 + (User::max('id') ?? 0) + 1),
                'supervisor_until' => $supervisor ? now()->addYears(2)->toDateString() : null,
            ])->save();

            if ($group) {
                $user->groups()->syncWithoutDetaching([$group->id]);
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
            $person('Ben Bogen', group: $archers),
            $person('Tim Jungschütze', group: $youth),
        ]);

        if (ShootingDay::exists()) {
            return;
        }

        $series = (string) Str::uuid();
        $saturdays = (string) Str::uuid();

        for ($week = 0; $week < 8; $week++) {
            $wednesday = Carbon::now()->startOfWeek()->addDays(2)->addWeeks($week);
            $saturday = Carbon::now()->startOfWeek()->addDays(5)->addWeeks($week);

            if ($wednesday->isAfter(today()->subDay())) {
                $day = ShootingDay::create(['series' => $series, 'date' => $wednesday->toDateString(), 'starts_at' => '17:00', 'ends_at' => '21:00', 'title' => 'Trainingsabend']);
                $day->ranges()->sync($ranges->take(3)->pluck('id'));
            }

            if ($saturday->isAfter(today()->subDay())) {
                $day = ShootingDay::create(['series' => $saturdays, 'date' => $saturday->toDateString(), 'starts_at' => '10:00', 'ends_at' => '14:00']);
                $day->ranges()->sync($ranges->pluck('id'));
            }
        }

        $youthDay = ShootingDay::create([
            'date' => Carbon::now()->startOfWeek()->addDays(4)->addWeek()->toDateString(),
            'starts_at' => '16:00',
            'ends_at' => '18:00',
            'title' => 'Jugendtraining',
            'restricted_to_groups' => true,
            'guests_allowed' => false,
            'notes' => 'Nur Luftdruck. Bitte Schießbrillen mitbringen.',
        ]);
        $youthDay->ranges()->sync([$ranges[2]->id]);
        $youthDay->groups()->sync([$youth->id]);

        // Die ersten Termine mit Aufsicht und Buchungen füllen, spätere bleiben offen.
        $supervisors = [$helga, $jonas, $chair];

        foreach (ShootingDay::with('ranges')->orderBy('date')->take(5)->get() as $i => $day) {
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
                    // Belegt, nicht freigegeben oder außerhalb des Tages: für Demo-Daten egal.
                }
            }
        }
    }
}
