import { router } from '@inertiajs/svelte';
import { toast } from 'svelte-sonner';
import type { FlashToast } from '@/types/ui';

/**
 * Fehler ohne eigenes Formularfeld (z. B. "Stornieren nicht mehr möglich")
 * kommen von Aktionen ohne Formular und würden sonst unsichtbar bleiben.
 */
const GLOBAL_ERROR_KEYS = ['booking', 'shooting_day', 'supervision'];

export function initializeFlashToast(): void {
    router.on('flash', (event) => {
        const flash = (event as CustomEvent).detail?.flash;
        const data = flash?.toast as FlashToast | undefined;

        if (!data) {
            return;
        }

        toast[data.type](data.message);
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
