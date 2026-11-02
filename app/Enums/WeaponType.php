<?php

namespace App\Enums;

/**
 * Waffenart, wie sie in der Schießkladde und im Nachweis nach § 14 WaffG
 * auftaucht (Kurz- oder Langwaffe). Luftdruck und Bogen sind erlaubnisfrei,
 * werden aber für den Standbetrieb ebenfalls gebucht.
 */
enum WeaponType: string
{
    case Pistol = 'kurzwaffe';
    case Rifle = 'langwaffe';
    case Airgun = 'luftdruck';
    case Bow = 'bogen';

    public function label(): string
    {
        return match ($this) {
            self::Pistol => 'Kurzwaffe',
            self::Rifle => 'Langwaffe',
            self::Airgun => 'Luftdruck',
            self::Bow => 'Bogen',
        };
    }

    /**
     * Zählt für den Bedürfnisnachweis nach § 14 WaffG.
     */
    public function requiresLicense(): bool
    {
        return in_array($this, [self::Pistol, self::Rifle], true);
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $type) => ['value' => $type->value, 'label' => $type->label()], self::cases());
    }
}
