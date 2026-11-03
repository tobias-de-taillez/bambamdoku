import { page } from '@inertiajs/svelte';
import { isSensitiveKey, redact, redactString } from './redact';
import { trackedErrors, trackedNavigations, trackedPage } from './tracking';

/** Größer darf der Zustand nicht werden (Server-Grenze 1 MB); sonst nur die Übersicht der Props. */
const MAX_STATE_CHARS = 900_000;

const SECRET_AUTOCOMPLETE = /password|one-time-code|cc-|otp/i;

function plain(value: unknown): unknown {
    try {
        return JSON.parse(JSON.stringify(value ?? null));
    } catch {
        return '[nicht serialisierbar]';
    }
}

function colorScheme(): string {
    const dark = document.documentElement.classList.contains('dark');
    const prefers = window.matchMedia?.('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';

    return `${dark ? 'dark' : 'light'} (System: ${prefers})`;
}

function isSecretField(
    el: HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement,
): boolean {
    if (
        el instanceof HTMLInputElement &&
        ['password', 'hidden', 'file'].includes(el.type)
    ) {
        return true;
    }

    return (
        isSensitiveKey(el.name || '') ||
        isSensitiveKey(el.id || '') ||
        SECRET_AUTOCOMPLETE.test(el.getAttribute('autocomplete') ?? '')
    );
}

/** Sichtbare Formularfelder ohne Passwort-, PIN- und versteckte Felder. */
function formFields(root: Element | null): unknown[] {
    const fields = Array.from(
        document.querySelectorAll<
            HTMLInputElement | HTMLTextAreaElement | HTMLSelectElement
        >('input, textarea, select'),
    )
        .filter((el) => !root?.contains(el) && !isSecretField(el))
        .slice(0, 50);

    return fields.map((el) => {
        const label =
            ('labels' in el && el.labels?.[0]?.textContent?.trim()) ||
            el.getAttribute('aria-label') ||
            el.getAttribute('placeholder') ||
            '';
        const checkable =
            el instanceof HTMLInputElement &&
            ['checkbox', 'radio'].includes(el.type);

        return {
            name: el.name || el.id || null,
            label: label.slice(0, 200),
            type:
                el instanceof HTMLInputElement
                    ? el.type
                    : el.tagName.toLowerCase(),
            value: checkable
                ? (el as HTMLInputElement).checked
                : redactString(String(el.value ?? '').slice(0, 500)),
            disabled: el.disabled,
        };
    });
}

function sizesOf(props: Record<string, unknown>): Record<string, string> {
    return Object.fromEntries(
        Object.entries(props).map(([key, value]) => [
            key,
            `${(JSON.stringify(value) ?? '').length} Zeichen (zu groß, weggelassen)`,
        ]),
    );
}

/**
 * Der „exakte State“: Inertia-Seite, Adresse, Gerät, letzte Fehler und Navigationen –
 * ohne Cookies und mit geschwärzten Geheimnissen.
 */
export function collectState(
    helpRoot: Element | null,
): Record<string, unknown> {
    const current = page?.component ? page : trackedPage();
    const props = (plain(current?.props ?? {}) ?? {}) as Record<
        string,
        unknown
    >;

    const state: Record<string, unknown> = {
        at: new Date().toISOString(),
        url: redactString(window.location.href),
        referrer: redactString(document.referrer),
        title: document.title,
        page: {
            component: current?.component ?? null,
            url: redactString(current?.url ?? ''),
            version: current?.version ?? null,
            props: redact(props),
        },
        client: {
            userAgent: navigator.userAgent,
            language: navigator.language,
            languages: [...(navigator.languages ?? [])],
            timeZone: Intl.DateTimeFormat().resolvedOptions().timeZone,
            colorScheme: colorScheme(),
            viewport: {
                width: window.innerWidth,
                height: window.innerHeight,
                devicePixelRatio: window.devicePixelRatio,
            },
            screen: {
                width: window.screen?.width,
                height: window.screen?.height,
            },
            scroll: {
                x: Math.round(window.scrollX),
                y: Math.round(window.scrollY),
            },
            online: navigator.onLine,
            touch: navigator.maxTouchPoints > 0,
            standalone:
                window.matchMedia?.('(display-mode: standalone)').matches ??
                false,
        },
        formFields: formFields(helpRoot),
        errors: trackedErrors(),
        navigations: trackedNavigations(),
    };

    if (JSON.stringify(state).length > MAX_STATE_CHARS) {
        (state.page as Record<string, unknown>).props = sizesOf(props);
    }

    return state;
}
