<?php

namespace App\Support;

/**
 * Schwärzt in Hilfe-Meldungen alles, was nach Geheimnis aussieht.
 * Gegenstück zu resources/js/help/redact.ts; der Server schwärzt noch einmal, falls der Browser es nicht tat.
 */
final class HelpRedactor
{
    public const MASK = '[geschwärzt]';

    /**
     * Einzelne Wörter eines Schlüssels (snake_case, camelCase), die ihn geheim machen.
     */
    private const WORDS = [
        'password', 'passwort', 'pin', 'pins', 'token', 'tokens', 'secret', 'secrets', 'remember',
        'csrf', 'xsrf', 'otp', 'recovery', 'signature', 'cookie', 'cookies', 'authorization',
        'credential', 'credentials', 'hash', 'fingerprint', 'passkey', 'passkeys',
    ];

    /**
     * Wortfolgen, die ihn ebenfalls geheim machen.
     */
    private const PHRASES = ['two_factor', 'twofactor', 'api_key', 'private_key', 'access_key'];

    private const MAX_DEPTH = 32;

    public static function isSensitiveKey(string|int $key): bool
    {
        if (is_int($key)) {
            return false;
        }

        $normalized = strtolower((string) preg_replace('/([a-z0-9])([A-Z])/', '$1_$2', $key));
        $normalized = (string) preg_replace('/[^a-z0-9]+/', '_', $normalized);

        foreach (self::PHRASES as $phrase) {
            if (str_contains($normalized, $phrase)) {
                return true;
            }
        }

        return array_intersect(explode('_', $normalized), self::WORDS) !== [];
    }

    /**
     * Geheimnisse in Texten: Tokens in Pfaden (Kiosk, Einladung, Passwort zurücksetzen),
     * geheime Query-Parameter (signature, token …) und lange Zufallsketten.
     */
    public static function string(string $value): string
    {
        $value = (string) preg_replace_callback(
            '/([?&;])([^=&#\s"\']+)=([^&#\s"\']*)/',
            fn (array $m) => self::isSensitiveKey(urldecode($m[2])) ? $m[1].$m[2].'='.self::MASK : $m[0],
            $value,
        );
        // Ankunfts-Code am Stand (stand/{tag}/da/{code})
        $value = (string) preg_replace('#/da/[a-f0-9]{12}\b#', '/da/'.self::MASK, $value);

        // Kiosk-Token (48), Einladungs- und Bestätigungs-Hashes, Reset-Tokens, Session-IDs
        return (string) preg_replace('/[A-Za-z0-9]{32,}/', self::MASK, $value);
    }

    /**
     * Rekursiv: geheime Schlüssel ganz schwärzen, alle übrigen Texte nach Geheimnissen absuchen.
     */
    public static function value(mixed $value, int $depth = 0): mixed
    {
        if ($depth > self::MAX_DEPTH) {
            return '[zu tief verschachtelt]';
        }

        if (is_string($value)) {
            return self::string($value);
        }

        if (! is_array($value)) {
            return $value;
        }

        $result = [];
        foreach ($value as $key => $item) {
            $result[$key] = self::isSensitiveKey($key) && $item !== null ? self::MASK : self::value($item, $depth + 1);
        }

        return $result;
    }
}
