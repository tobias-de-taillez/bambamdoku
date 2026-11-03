<?php

namespace App\Enums;

/**
 * Status eines Eintrags in der Vorstufe. Nur `pending` lässt sich noch ändern (Trigger).
 */
enum SessionEntryStatus: string
{
    case Pending = 'pending';
    case Withdrawn = 'withdrawn';
    case Rejected = 'rejected';
    case Signed = 'signed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'offen',
            self::Withdrawn => 'zurückgezogen',
            self::Rejected => 'abgelehnt',
            self::Signed => 'abgezeichnet',
        };
    }
}
