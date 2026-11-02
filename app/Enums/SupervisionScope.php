<?php

namespace App\Enums;

/**
 * Umfang einer Registrierung als Aufsichtsperson (§ 10 Abs. 3 AWaffV).
 * Für erlaubnispflichtige Waffen braucht die Aufsicht zusätzlich Waffensachkunde
 * (DSB-Richtlinie Standaufsicht).
 */
enum SupervisionScope: string
{
    case Exempt = 'exempt';
    case Licensed = 'licensed';
    case Revoked = 'revoked';

    public function label(): string
    {
        return match ($this) {
            self::Exempt => 'nur erlaubnisfreie Waffen',
            self::Licensed => 'auch erlaubnispflichtige Waffen',
            self::Revoked => 'entzogen',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $s) => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
