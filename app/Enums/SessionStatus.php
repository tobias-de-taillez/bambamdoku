<?php

namespace App\Enums;

/**
 * Status einer Schieß-Session. „Verfallen“ ist kein eigener Status, sondern berechnet:
 * `open` mit `shot_on` vor heute (UMBAU-SESSION.md, Zustände).
 */
enum SessionStatus: string
{
    case Open = 'open';
    case Signed = 'signed';
}
