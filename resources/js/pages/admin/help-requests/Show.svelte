<script module lang="ts">
    import { index } from '@/routes/admin/help-requests';

    export const layout = {
        breadcrumbs: [{ title: 'Rückmeldungen', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';
    import { destroy, screenshot, update } from '@/routes/admin/help-requests';

    type Detail = {
        id: number;
        reference: string;
        at: string;
        user: string | null;
        role: string | null;
        url: string;
        component: string | null;
        message: string;
        status: 'offen' | 'erledigt';
        resolvedAt: string | null;
        path: string;
        ip: string | null;
        browser: {
            userAgent?: string;
            language?: string;
            timeZone?: string;
            colorScheme?: string;
            viewport?: { width: number; height: number; devicePixelRatio: number };
        } | null;
        hasScreenshot: boolean;
    };

    let { helpRequest, stateJson }: { helpRequest: Detail; stateJson: string | null } =
        $props();

    let busy = $state(false);

    const formatter = new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'medium',
    });

    function setStatus(status: 'offen' | 'erledigt') {
        router.put(toUrl(update(helpRequest.id)), { status }, {
            preserveScroll: true,
            onStart: () => (busy = true),
            onFinish: () => (busy = false),
        });
    }

    function remove() {
        if (
            !confirm(
                'Rückmeldung mit Bildschirmfoto und Zustand endgültig löschen? Das geht zum Beispiel nach einem Widerspruch der meldenden Person.',
            )
        ) {
            return;
        }
        router.delete(toUrl(destroy(helpRequest.id)), {
            onStart: () => (busy = true),
            onFinish: () => (busy = false),
        });
    }
</script>

<AppHead title={`Rückmeldung ${helpRequest.reference}`} />

<div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title={`Rückmeldung ${helpRequest.reference}`}
        description={`${formatter.format(new Date(helpRequest.at))} · ${helpRequest.user ?? 'Gast'}${helpRequest.role ? ` (${helpRequest.role})` : ''}`}
    />

    <div class="flex flex-wrap items-center gap-3">
        <span
            class={helpRequest.status === 'offen'
                ? 'rounded bg-amber-100 px-2 py-0.5 text-sm text-amber-900 dark:bg-amber-900/40 dark:text-amber-100'
                : 'rounded bg-muted px-2 py-0.5 text-sm text-muted-foreground'}
            >{helpRequest.status}{helpRequest.resolvedAt
                ? ` seit ${formatter.format(new Date(helpRequest.resolvedAt))}`
                : ''}</span
        >
        {#if helpRequest.status === 'offen'}
            <Button disabled={busy} onclick={() => setStatus('erledigt')}>Erledigt</Button>
        {:else}
            <Button variant="outline" disabled={busy} onclick={() => setStatus('offen')}
                >Wieder öffnen</Button
            >
        {/if}
        <Button variant="destructive" disabled={busy} onclick={remove}>Löschen</Button>
        <Link href={toUrl(index())} class="text-sm underline underline-offset-4"
            >Zur Liste</Link
        >
    </div>

    <section aria-labelledby="hr-message" class="grid gap-2">
        <h3 id="hr-message" class="font-medium">Meldung</h3>
        <p class="rounded-lg border p-3 text-sm break-words whitespace-pre-wrap">
            {helpRequest.message}
        </p>
    </section>

    <section aria-labelledby="hr-context" class="grid gap-2">
        <h3 id="hr-context" class="font-medium">Wo und womit</h3>
        <dl class="grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[10rem_1fr]">
            <dt class="text-muted-foreground">Adresse</dt>
            <dd class="break-all">{helpRequest.url || '–'}</dd>
            <dt class="text-muted-foreground">Seite (Komponente)</dt>
            <dd>{helpRequest.component ?? '–'}</dd>
            <dt class="text-muted-foreground">Browser</dt>
            <dd class="break-words">{helpRequest.browser?.userAgent ?? '–'}</dd>
            <dt class="text-muted-foreground">Fenster</dt>
            <dd>
                {#if helpRequest.browser?.viewport}
                    {helpRequest.browser.viewport.width} × {helpRequest.browser.viewport.height}
                    (×{helpRequest.browser.viewport.devicePixelRatio})
                {:else}
                    –
                {/if}
            </dd>
            <dt class="text-muted-foreground">Sprache, Zeitzone</dt>
            <dd>{helpRequest.browser?.language ?? '–'}, {helpRequest.browser?.timeZone ?? '–'}</dd>
            <dt class="text-muted-foreground">Farbschema</dt>
            <dd>{helpRequest.browser?.colorScheme ?? '–'}</dd>
            <dt class="text-muted-foreground">IP (gekürzt)</dt>
            <dd>{helpRequest.ip ?? '–'}</dd>
            <dt class="text-muted-foreground">Ablage</dt>
            <dd class="font-mono text-xs break-all">storage/app/private/{helpRequest.path}</dd>
        </dl>
    </section>

    <section aria-labelledby="hr-screenshot" class="grid gap-2">
        <h3 id="hr-screenshot" class="font-medium">Bildschirmfoto</h3>
        {#if helpRequest.hasScreenshot}
            <a href={toUrl(screenshot(helpRequest.id))} target="_blank" rel="noopener">
                <img
                    src={toUrl(screenshot(helpRequest.id))}
                    alt={`Bildschirmfoto zur Rückmeldung ${helpRequest.reference}`}
                    class="w-full rounded-lg border"
                    loading="lazy"
                />
            </a>
        {:else}
            <p class="text-sm text-muted-foreground">Ohne Bildschirmfoto gesendet.</p>
        {/if}
    </section>

    <section aria-labelledby="hr-state" class="grid gap-2">
        <h3 id="hr-state" class="font-medium">Technischer Zustand</h3>
        {#if stateJson}
            <details class="rounded-lg border">
                <summary class="cursor-pointer p-3 text-sm">JSON anzeigen</summary>
                <!-- svelte-ignore a11y_no_noninteractive_tabindex -->
                <pre
                    aria-label="Technischer Zustand als JSON"
                    class="max-h-[32rem] overflow-auto border-t p-3 text-xs"
                    tabindex="0">{stateJson}</pre>
            </details>
        {:else}
            <p class="text-sm text-muted-foreground">Kein Zustand gespeichert.</p>
        {/if}
    </section>
</div>
