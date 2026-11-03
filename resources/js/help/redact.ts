/**
 * Schwärzt in Hilfe-Meldungen alles, was nach Geheimnis aussieht.
 * Gegenstück zu app/Support/HelpRedactor.php (der Server schwärzt noch einmal).
 */
export const MASK = '[geschwärzt]';

const WORDS = new Set([
    'password',
    'passwort',
    'pin',
    'pins',
    'token',
    'tokens',
    'secret',
    'secrets',
    'remember',
    'csrf',
    'xsrf',
    'otp',
    'recovery',
    'signature',
    'cookie',
    'cookies',
    'authorization',
    'credential',
    'credentials',
    'hash',
    'fingerprint',
    'passkey',
    'passkeys',
]);

const PHRASES = [
    'two_factor',
    'twofactor',
    'api_key',
    'private_key',
    'access_key',
];

const MAX_DEPTH = 32;

export function isSensitiveKey(key: string): boolean {
    const normalized = key
        .replace(/([a-z0-9])([A-Z])/g, '$1_$2')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '_');

    if (PHRASES.some((phrase) => normalized.includes(phrase))) {
        return true;
    }

    return normalized.split('_').some((word) => WORDS.has(word));
}

function safeDecode(value: string): string {
    try {
        return decodeURIComponent(value);
    } catch {
        return value;
    }
}

/** Tokens in Pfaden, geheime Query-Parameter und lange Zufallsketten. */
export function redactString(value: string): string {
    return value
        .replace(
            /([?&;])([^=&#\s"']+)=([^&#\s"']*)/g,
            (match, sep: string, key: string) =>
                isSensitiveKey(safeDecode(key))
                    ? `${sep}${key}=${MASK}`
                    : match,
        )
        .replace(/\/da\/[a-f0-9]{12}\b/g, `/da/${MASK}`)
        .replace(/[A-Za-z0-9]{32,}/g, MASK);
}

/** Rekursiv: geheime Schlüssel ganz schwärzen, alle übrigen Texte absuchen. */
export function redact(value: unknown, depth = 0): unknown {
    if (depth > MAX_DEPTH) {
        return '[zu tief verschachtelt]';
    }

    if (typeof value === 'string') {
        return redactString(value);
    }

    if (Array.isArray(value)) {
        return value.map((item) => redact(item, depth + 1));
    }

    if (value !== null && typeof value === 'object') {
        return Object.fromEntries(
            Object.entries(value).map(([key, item]) => [
                key,
                isSensitiveKey(key) && item !== null && item !== undefined
                    ? MASK
                    : redact(item, depth + 1),
            ]),
        );
    }

    return value;
}
