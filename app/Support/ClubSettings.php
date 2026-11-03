<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Vereinseinstellungen aus der Oberfläche. Sie überschreiben die Werte aus
 * config/verein.php (bzw. .env), damit ein Verein ohne Serverzugriff
 * Name, Raster und Fristen ändern kann.
 */
class ClubSettings
{
    /**
     * Erlaubte Schlüssel mit ihrem Typ.
     *
     * @var array<string, 'string'|'int'>
     */
    public const KEYS = [
        'name' => 'string',
        'address' => 'string',
        'contact_email' => 'string',
        'federation' => 'string',
        'club_number' => 'string',
        'digital_since' => 'string',
        'imprint' => 'string',
        'discipline_catalog' => 'string',
        'slot_minutes' => 'int',
        'cancel_minutes_before' => 'int',
        'booking_horizon_days' => 'int',
        'registration_open' => 'int',
        'retention_record_years' => 'int',
        'retention_guest_months' => 'int',
    ];

    /**
     * Lädt gespeicherte Werte in die Konfiguration. Fehlt die Tabelle noch
     * (vor der ersten Migration), bleibt alles beim Standard.
     */
    public static function apply(): void
    {
        try {
            if (! Schema::hasTable('settings')) {
                return;
            }

            $stored = DB::table('settings')->whereIn('key', array_keys(self::KEYS))->pluck('value', 'key');
        } catch (Throwable) {
            return;
        }

        foreach ($stored as $key => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            config(["verein.$key" => self::KEYS[$key] === 'int' ? (int) $value : $value]);
        }
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public static function save(array $values): void
    {
        foreach (array_intersect_key($values, self::KEYS) as $key => $value) {
            DB::table('settings')->updateOrInsert(
                ['key' => $key],
                ['value' => $value === null ? null : (string) $value, 'updated_at' => now(), 'created_at' => now()],
            );
        }

        self::apply();
    }

    /**
     * @return array<string, mixed>
     */
    public static function current(): array
    {
        return collect(self::KEYS)->mapWithKeys(fn ($type, $key) => [$key => config("verein.$key")])->all();
    }
}
