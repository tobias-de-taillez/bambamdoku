import { router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';
import type { FlashToast } from '@/types/ui';

/**
 * Fehler ohne eigenes Formularfeld (z. B. "Stornieren nicht mehr möglich")
 * kommen von Aktionen ohne Formular und würden sonst unsichtbar bleiben.
 */
const GLOBAL_ERROR_KEYS = ['booking', 'shooting_day', 'supervision'];

/** Statt der rohen Fehlerseite im Overlay eine verständliche Meldung. */
const HTTP_MESSAGES: Record<number, string> = {
    419: 'Die Sitzung ist abgelaufen. Die Seite wird neu geladen – danach bitte noch einmal tippen.',
    429: 'Zu viele Versuche. Bitte einen Moment warten.',
    503: 'Gerade wird eine neue Version eingespielt. Bitte in einer Minute noch einmal tippen.',
};

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
    });

    // Am Stand ist das Netz oft schlecht. Eintragen ist idempotent (K5),
    // erneutes Tippen ist also sicher.
    router.on('networkError', () => {
        toast.error(
            'Keine Verbindung – nichts gespeichert. Bitte noch einmal tippen.',
        );
    });

    router.on('httpException', (event) => {
        const status = (event as CustomEvent).detail?.response?.status as
            | number
            | undefined;
        const message = status ? HTTP_MESSAGES[status] : undefined;

        if (!message) {
            return;
        }

        event.preventDefault();
        toast.error(message);

        // Abgelaufene Sitzung (z. B. Tablet über Nacht offen): neu laden holt ein frisches Token.
        if (status === 419) {
            setTimeout(() => window.location.reload(), 1500);
        }
    });

    router.on('error', (event) => {
        const errors = ((event as CustomEvent).detail?.errors ?? {}) as Record<
            string,
            string
        >;

        for (const key of GLOBAL_ERROR_KEYS) {
            if (errors[key]) {
                toast.error(errors[key]);
            }
        }
    });
}
