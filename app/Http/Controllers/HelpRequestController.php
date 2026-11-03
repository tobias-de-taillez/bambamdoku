<?php

namespace App\Http\Controllers;

use App\Domain\HelpRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * „Report Bug / Request Feature“: Meldung mit Bildschirmfoto und Zustand der Seite, für Gäste und Angemeldete.
 */
class HelpRequestController extends Controller
{
    /**
     * Grenzen: Text 5000 Zeichen, Bildschirmfoto 4 MB, Zustand 1 MB.
     */
    public const MAX_MESSAGE = 5000;

    public const MAX_SCREENSHOT_KB = 4096;

    public const MAX_STATE = 1_000_000;

    public function store(Request $request, HelpRequests $helpRequests): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:'.self::MAX_MESSAGE],
            'state' => ['nullable', 'string', 'max:'.self::MAX_STATE, 'json'],
            'screenshot' => ['nullable', 'file', 'mimetypes:image/jpeg', 'max:'.self::MAX_SCREENSHOT_KB],
        ], [], [
            'message' => 'Beschreibung',
            'state' => 'Zustand der Seite',
            'screenshot' => 'Bildschirmfoto',
        ]);

        $state = isset($data['state']) ? json_decode($data['state'], true) : null;

        $reference = $helpRequests->store(
            $request->user(),
            trim($data['message']),
            is_array($state) ? $state : [],
            $request->file('screenshot'),
            $request->ip(),
        );

        return response()->json(['reference' => $reference], 201);
    }
}
