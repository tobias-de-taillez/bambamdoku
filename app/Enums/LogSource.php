<?php

namespace App\Enums;

enum LogSource: string
{
    /** Altbestand: am Stand von der Aufsicht eingetragen und damit bestätigt. */
    case Stand = 'stand';

    /** Vom Admin nachgetragen, mit Pflichtgrund (z. B. Papierliste); mit corrects_id eine Korrektur. */
    case LateEntry = 'late_entry';

    /** Beim Abzeichnen einer Schieß-Session von der Aufsicht bestätigt. */
    case Session = 'session';
}
