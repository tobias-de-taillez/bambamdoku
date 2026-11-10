<?php

namespace Tests\Feature;

use App\Domain\Disciplines;
use App\Domain\Logbook;
use App\Enums\WeaponClass;
use App\Enums\WeaponOwner;
use App\Models\LogEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Katalog „bds“ aus der BDS-Kennziffernliste 07/2026 (O1). Schlüssel sind dauerhaft:
 * Ein neues Erzeugen der Liste darf keinen verlieren oder umbenennen.
 */
class BdsCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['verein.discipline_catalog' => 'bds']);
    }

    public function test_catalog_has_all_kennziffern_of_the_list()
    {
        $all = Disciplines::all();

        $this->assertCount(291, $all);

        foreach (array_keys($all) as $key) {
            $this->assertMatchesRegularExpression('/^BDS:\d{4,5}$/', $key);
        }
    }

    public function test_double_numbers_are_split_into_praezision_and_kombi()
    {
        $all = Disciplines::all();

        // Sporthandbuch Kurzwaffen K3: 10xx Präzision, 11xx Kombi
        $this->assertSame('25m Schießen Präzision – Pistole bis 9 mm', $all['BDS:1001']);
        $this->assertSame('25m Schießen Kombi – Pistole bis 9 mm', $all['BDS:1101']);

        // Sporthandbuch Langwaffen L 7.01 / L 7.02: 20xx Präzision, 28xx Kombi Büchse
        $this->assertStringStartsWith('25m Präzision Schießen Büchse – ', $all['BDS:2001']);
        $this->assertStringStartsWith('25m Kombi Schießen Büchse – ', $all['BDS:2801']);
    }

    public function test_five_digit_key_is_stored_in_the_logbook()
    {
        $admin = User::factory()->admin()->create();
        $member = User::factory()->create();

        $result = app(Logbook::class)->lateEntries($admin, [$member], today()->toDateString(), WeaponClass::Short, WeaponOwner::Own, 'BDS:10001', 'Papierliste vom Tag');

        $this->assertSame(['created' => 1, 'duplicates' => 0], $result);
        $this->assertSame('BDS:10001', LogEntry::active()->where('user_id', $member->id)->value('discipline'));
        $this->assertSame('BDS:10001 Steel Challenge – Open Pistol', Disciplines::label('BDS:10001'));
    }
}
