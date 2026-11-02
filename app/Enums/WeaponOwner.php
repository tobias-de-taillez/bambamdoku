<?php

namespace App\Enums;

/**
 * Für den Besitz (§ 14 Abs. 4) zählt nur die eigene Waffe, für den Erwerb (Abs. 3) auch die des Vereins.
 */
enum WeaponOwner: string
{
    case Own = 'own';
    case Club = 'club';

    public function label(): string
    {
        return match ($this) {
            self::Own => 'eigene',
            self::Club => 'Verein',
        };
    }
}
