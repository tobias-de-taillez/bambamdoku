<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Booked = 'booked';
    case CheckedIn = 'checked_in';
    case Cancelled = 'cancelled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Gebucht',
            self::CheckedIn => 'Anwesend',
            self::Cancelled => 'Storniert',
            self::NoShow => 'Nicht erschienen',
        };
    }

    /**
     * Belegt eine Bahn.
     */
    public function occupiesLane(): bool
    {
        return $this === self::Booked || $this === self::CheckedIn;
    }

    /**
     * @return list<string>
     */
    public static function occupying(): array
    {
        return [self::Booked->value, self::CheckedIn->value];
    }
}
