<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Vereinsdaten
    |--------------------------------------------------------------------------
    |
    | Erscheinen in der Oberfläche und auf den PDF-Nachweisen.
    |
    */

    'name' => env('VEREIN_NAME', 'Schützenverein'),
    'address' => env('VEREIN_ADDRESS'),
    'federation' => env('VEREIN_VERBAND'),
    'club_number' => env('VEREIN_NUMMER'),

    // Seit wann die Kladde digital geführt wird; frühere Termine stehen in der Papierkladde.
    'digital_since' => env('VEREIN_DIGITAL_SEIT'),

    // Anbieterkennzeichnung (Impressum) als freier Text, leer = keine Impressumsseite.
    'imprint' => env('VEREIN_IMPRESSUM'),

    // Schlüssel eines Katalogs aus config/disziplinen.php, leer = keine Disziplin abfragen.
    'discipline_catalog' => env('VEREIN_DISZIPLINKATALOG', ''),

    /*
    |--------------------------------------------------------------------------
    | Buchung
    |--------------------------------------------------------------------------
    |
    | slot_minutes: Raster für Buchungen und Aufsichtsschichten.
    | cancel_minutes_before: Bis wie viele Minuten vor Beginn Mitglieder
    | selbst stornieren können.
    |
    */

    'slot_minutes' => (int) env('VEREIN_SLOT_MINUTES', 60),
    'cancel_minutes_before' => (int) env('VEREIN_CANCEL_MINUTES_BEFORE', 60),
    'booking_horizon_days' => (int) env('VEREIN_BOOKING_HORIZON_DAYS', 90),

];
