<script lang="ts">
    import Bug from '@lucide/svelte/icons/bug';
    import { onDestroy, tick } from 'svelte';
    import { store } from '@/routes/help-requests';
    import { captureScreenshot } from './screenshot';
    import { collectState } from './state';

    let { root }: { root: HTMLElement } = $props();

    const MAX = 5000;

    let dialog: HTMLDialogElement | undefined = $state();
    let textarea: HTMLTextAreaElement | undefined = $state();
    let message = $state('');
    let includeScreenshot = $state(true);
    let capturing = $state(false);
    let screenshot: Blob | null = $state(null);
    let previewUrl: string | null = $state(null);
    let snapshot: Record<string, unknown> | null = null;
    let sending = $state(false);
    let error: string | null = $state(null);
    let reference: string | null = $state(null);
    let run = 0;

    function reset(): void {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        previewUrl = null;
        screenshot = null;
        snapshot = null;
        includeScreenshot = true;
        error = null;
        reference = null;
    }

    async function open(): Promise<void> {
        reset();
        const current = ++run;
        // Zustand und Bild so, wie die Seite beim Klick aussah – Knopf und Dialog sind ausgenommen.
        snapshot = collectState(root);
        capturing = true;
        dialog?.showModal();
        await tick();
        textarea?.focus();

        const blob = await captureScreenshot(root);

        if (current !== run) {
            return;
        }

        capturing = false;
        screenshot = blob;
        previewUrl = blob ? URL.createObjectURL(blob) : null;
    }

    function close(): void {
        run++;
        capturing = false;
        dialog?.close();

        if (reference) {
            message = '';
        }

        reset();
    }

    function xsrfToken(): string {
        const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);

        return match ? decodeURIComponent(match[1]) : '';
    }

    function post(body: FormData): Promise<Response> {
        return fetch(store.url(), {
            method: 'POST',
            body,
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-XSRF-TOKEN': xsrfToken(),
            },
        });
    }

    async function submit(event: SubmitEvent): Promise<void> {
        event.preventDefault();

        if (sending || message.trim() === '') {
            return;
        }

        sending = true;
        error = null;

        const body = new FormData();
        body.append('message', message.trim());
        body.append('state', JSON.stringify(snapshot ?? collectState(root)));

        if (includeScreenshot && screenshot) {
            body.append('screenshot', screenshot, 'screenshot.jpg');
        }

        try {
            let response = await post(body);

            // Sitzung abgelaufen oder Fehlerseite ohne Sitzung: frisches CSRF-Cookie holen, einmal wiederholen.
            if (response.status === 419) {
                await fetch('/datenschutz', { credentials: 'same-origin' });
                response = await post(body);
            }

            if (response.ok) {
                const data = (await response.json()) as { reference: string };
                reference = data.reference;
                message = '';

                return;
            }

            if (response.status === 422) {
                const data = (await response.json()) as {
                    message?: string;
                    errors?: Record<string, string[]>;
                };
                error =
                    Object.values(data.errors ?? {})[0]?.[0] ??
                    data.message ??
                    'Die Meldung ist unvollständig.';
            } else if (response.status === 429) {
                error = 'Zu viele Meldungen in kurzer Zeit. Bitte später noch einmal versuchen.';
            } else if (response.status === 413) {
                error = 'Die Meldung ist zu groß. Bitte ohne Bildschirmfoto senden.';
            } else {
                error = `Senden hat nicht geklappt (Fehler ${response.status}). Bitte später noch einmal versuchen.`;
            }
        } catch {
            error = 'Keine Verbindung zum Server. Bitte später noch einmal versuchen.';
        } finally {
            sending = false;
        }
    }

    onDestroy(reset);
</script>

<button
    type="button"
    class="fixed right-[max(0.75rem,env(safe-area-inset-right))] bottom-[max(0.75rem,env(safe-area-inset-bottom))] z-40 inline-flex size-10 items-center justify-center gap-2 rounded-full border border-border bg-background/90 text-sm font-medium text-foreground shadow-md backdrop-blur transition hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none sm:h-10 sm:w-auto sm:px-4 print:hidden"
    aria-label="Report Bug / Request Feature"
    aria-haspopup="dialog"
    onclick={open}
>
    <Bug class="size-5 shrink-0" aria-hidden="true" />
    <span class="sr-only sm:not-sr-only">Report Bug / Request Feature</span>
</button>

<dialog
    bind:this={dialog}
    aria-labelledby="help-title"
    class="m-auto w-[min(36rem,calc(100vw-1.5rem))] max-h-[calc(100dvh-1.5rem)] overflow-y-auto rounded-lg border bg-background p-0 text-foreground shadow-lg backdrop:bg-black/50"
    onclose={close}
>
    <div class="grid gap-4 p-5 sm:p-6">
        <div class="space-y-1">
            <h2 id="help-title" class="text-lg font-semibold">Report Bug / Request Feature</h2>
            {#if !reference}
                <p class="text-sm text-muted-foreground">
                    Melden Sie, was nicht funktioniert oder besser werden sollte. Der Vorstand und
                    die Entwickler kümmern sich darum.
                </p>
            {/if}
        </div>

        {#if reference}
            <div role="status" class="grid gap-2">
                <p>Danke! Ihre Meldung ist angekommen.</p>
                <p>
                    Kennung der Meldung:
                    <strong class="font-mono tracking-wider" data-testid="help-reference"
                        >{reference}</strong
                    >
                </p>
                <p class="text-sm text-muted-foreground">
                    Nennen Sie die Kennung, wenn Sie den Vorstand darauf ansprechen.
                </p>
            </div>
            <div class="flex justify-end">
                <button
                    type="button"
                    class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    onclick={close}>Schließen</button
                >
            </div>
        {:else}
            <form class="grid gap-4" onsubmit={submit}>
                <div class="grid gap-2">
                    <label for="help-message" class="text-sm font-medium"
                        >Was ist kaputt oder was sollte besser werden?</label
                    >
                    <textarea
                        id="help-message"
                        bind:this={textarea}
                        bind:value={message}
                        required
                        maxlength={MAX}
                        rows="5"
                        aria-describedby="help-count help-notice"
                        class="w-full rounded-md border border-input bg-background px-3 py-2 text-base shadow-xs focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:text-sm"
                    ></textarea>
                    <p id="help-count" class="text-right text-xs text-muted-foreground">
                        {message.length} / {MAX} Zeichen
                    </p>
                </div>

                <p id="help-notice" class="text-sm text-muted-foreground">
                    Mitgeschickt werden ein Bildschirmfoto dieser Seite und ihr technischer
                    Zustand (Adresse, angezeigte Daten, Browser, letzte Fehler). Passwörter, PINs
                    und Schlüssel werden vorher geschwärzt. Mehr im
                    <a href="/datenschutz#hilfe" class="underline underline-offset-4"
                        >Datenschutzhinweis</a
                    >.
                </p>

                <div class="grid gap-2">
                    {#if capturing}
                        <p class="text-sm text-muted-foreground" aria-live="polite">
                            Bildschirmfoto wird erstellt …
                        </p>
                    {:else if previewUrl}
                        <label class="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                bind:checked={includeScreenshot}
                                class="size-4 accent-primary"
                            />
                            Bildschirmfoto mitschicken
                        </label>
                        {#if includeScreenshot}
                            <img
                                src={previewUrl}
                                alt="Vorschau des Bildschirmfotos"
                                class="max-h-48 w-full rounded border object-contain object-top"
                            />
                        {/if}
                    {:else}
                        <p class="text-sm text-muted-foreground">
                            Kein Bildschirmfoto möglich; die Meldung geht ohne Bild.
                        </p>
                    {/if}
                </div>

                {#if error}
                    <p role="alert" class="text-sm text-red-600 dark:text-red-400">{error}</p>
                {/if}

                <div class="flex flex-wrap justify-end gap-2">
                    <button
                        type="button"
                        class="inline-flex h-9 items-center rounded-md border border-input bg-background px-4 text-sm font-medium hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        onclick={close}>Abbrechen</button
                    >
                    <button
                        type="submit"
                        disabled={sending || capturing || message.trim() === ''}
                        class="inline-flex h-9 items-center rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground hover:bg-primary/90 focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                        >{sending ? 'Wird gesendet …' : 'Absenden'}</button
                    >
                </div>
            </form>
        {/if}
    </div>
</dialog>
