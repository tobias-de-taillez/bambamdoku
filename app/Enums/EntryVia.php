<?php

namespace App\Enums;

/**
 * Über welches Gerät ein Eintrag erfasst wurde („erfasst über“).
 */
enum EntryVia: string
{
    /** Das Mitglied am eigenen Handy, nur für sich. */
    case OwnDevice = 'own_device';

    /** Am Kiosk-Tablet der Session, nach PIN des Mitglieds. */
    case Kiosk = 'kiosk';

    /** Die Aufsicht am Stand, z. B. für Gäste und Mitglieder ohne PIN. */
    case Stand = 'stand';

    public function label(): string
    {
        return match ($this) {
            self::OwnDevice => 'eigenes Gerät',
            self::Kiosk => 'Kiosk',
            self::Stand => 'Aufsicht',
        };
    }
}
