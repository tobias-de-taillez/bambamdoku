<?php

namespace App\Enums;

/**
 * Kategorie eines Kladde-Eintrags. Kurz- und Langwaffe getrennt (§ 14 Abs. 4 S. 2 WaffG);
 * erlaubnisfreie Termine zählen nicht für § 14, wohl aber für die Häufigkeit nach
 * § 15 Abs. 1 Nr. 7 b WaffG.
 */
enum WeaponClass: string
{
    case Short = 'short';
    case Long = 'long';
    case Exempt = 'exempt';

    public function label(): string
    {
        return match ($this) {
            self::Short => 'Kurzwaffe',
            self::Long => 'Langwaffe',
            self::Exempt => 'erlaubnisfrei',
        };
    }

    public function requiresLicense(): bool
    {
        return $this !== self::Exempt;
    }

    public static function fromWeaponType(WeaponType $type): self
    {
        return match ($type) {
            WeaponType::Pistol => self::Short,
            WeaponType::Rifle => self::Long,
            WeaponType::Airgun, WeaponType::Bow => self::Exempt,
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $c) => ['value' => $c->value, 'label' => $c->label()], self::cases());
    }
}
