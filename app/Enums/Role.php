<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Member = 'member';
    case Guest = 'guest';
    // Mitgliedsantrag: Konto ohne Anmeldung, bis der Vorstand aufnimmt (immer active=false).
    case Applicant = 'applicant';
}
