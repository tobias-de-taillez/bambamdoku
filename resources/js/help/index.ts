import { mount } from 'svelte';
import HelpWidget from './HelpWidget.svelte';

export { startHelpTracking } from './tracking';

/**
 * „Report Bug / Request Feature“ als eigene Svelte-Wurzel neben der Inertia-App: so steht der Knopf auf
 * jeder Seite, egal welches Layout sie hat (auch Startseite, Anmeldung, Kiosk, Fehlerseiten).
 */
export function mountHelpWidget(): void {
    if (document.querySelector('[data-help-root]')) {
        return;
    }

    const root = document.createElement('div');
    root.dataset.helpRoot = '';
    document.body.appendChild(root);
    mount(HelpWidget, { target: root, props: { root } });
}
