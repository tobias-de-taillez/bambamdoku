<?php

namespace App\Support;

use App\Enums\Role;
use App\Models\User;
use DateTimeInterface;

/**
 * Geänderte Stammdaten eines Mitglieds mit altem und neuem Wert für das Protokoll,
 * gleich beim Bearbeiten von Hand und beim CSV-Import. Vor dem Speichern aufrufen.
 */
final class UserChanges
{
    private const LABELS = [
        'name' => 'Name',
        'email' => 'E-Mail',
        'member_number' => 'Nr.',
        'member_since' => 'Mitglied seit',
        'role' => 'Rolle',
        'active' => 'aktiv',
        'adult_confirmed_at' => 'volljährig geprüft',
    ];

    /**
     * @return list<string>
     */
    public static function of(User $user): array
    {
        $format = fn (mixed $value): string => match (true) {
            $value === null || $value === '' => '–',
            $value instanceof Role => $value->value,
            is_bool($value) => $value ? 'ja' : 'nein',
            $value instanceof DateTimeInterface => $value->format('Y-m-d'),
            default => (string) $value,
        };

        $changes = [];

        foreach (array_keys($user->getDirty()) as $key) {
            if (! isset(self::LABELS[$key])) {
                continue;
            }

            // Die E-Mail-Adresse steht nur als Feldname im Protokoll.
            $changes[] = match ($key) {
                'email' => self::LABELS[$key],
                'adult_confirmed_at' => self::LABELS[$key].': ja',
                default => sprintf('%s: %s → %s', self::LABELS[$key], $format($user->getOriginal($key)), $format($user->getAttribute($key))),
            };
        }

        return $changes;
    }
}
