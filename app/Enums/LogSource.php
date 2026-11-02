<?php

namespace App\Enums;

enum LogSource: string
{
    /** Am Stand von der Aufsicht eingetragen und damit bestätigt. */
    case Stand = 'stand';

    /** Vom Admin nachgetragen, mit Pflichtgrund (z. B. Papierliste). */
    case LateEntry = 'late_entry';
}
