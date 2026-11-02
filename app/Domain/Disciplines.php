<?php

namespace App\Domain;

/**
 * Disziplinkatalog des Verbands (K14), versioniert in config/disziplinen.php,
 * ausgewählt über VEREIN_DISZIPLINKATALOG. Leer = Feld Disziplin wird nicht abgefragt.
 */
class Disciplines
{
    /**
     * @return array<string, string> Schlüssel „Verband:SpO-Nummer“ => Name
     */
    public static function all(): array
    {
        $catalog = (string) config('verein.discipline_catalog', '');

        if ($catalog === '') {
            return [];
        }

        /** @var array<string, string> $entries */
        $entries = config("disziplinen.{$catalog}", []);

        return $entries;
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::all() as $key => $name) {
            $options[] = ['value' => $key, 'label' => "{$key} {$name}"];
        }

        return $options;
    }

    public static function label(?string $key): ?string
    {
        if ($key === null) {
            return null;
        }

        $name = self::all()[$key] ?? null;

        return $name ? "{$key} {$name}" : $key;
    }
}
