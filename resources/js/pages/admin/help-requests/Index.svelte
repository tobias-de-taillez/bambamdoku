<script module lang="ts">
    import { index } from '@/routes/admin/help-requests';

    export const layout = {
        breadcrumbs: [{ title: 'Rückmeldungen', href: index() }],
    };
</script>

<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { toUrl } from '@/lib/utils';
    import { show } from '@/routes/admin/help-requests';

    type Item = {
        id: number;
        reference: string;
        at: string;
        user: string | null;
        url: string;
        component: string | null;
        message: string;
        status: 'offen' | 'erledigt';
    };

    let {
        requests,
        status,
        openCount,
    }: {
        requests: {
            data: Item[];
            prev_page_url: string | null;
            next_page_url: string | null;
            from: number | null;
            to: number | null;
            total: number;
        };
        status: 'offen' | 'erledigt' | 'alle';
        openCount: number;
    } = $props();

    const filters = [
        { value: 'offen', label: 'Offen' },
        { value: 'erledigt', label: 'Erledigt' },
        { value: 'alle', label: 'Alle' },
    ] as const;

    const formatter = new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });
</script>

<AppHead title="Rückmeldungen" />

<div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Rückmeldungen"
        description="Meldungen über „Report Bug / Request Feature“: was kaputt ist oder besser werden sollte, mit Bildschirmfoto und technischem Zustand der Seite. Erledigte verschwinden nach 90 Tagen, alle nach einem Jahr."
    />

    <nav aria-label="Filter" class="flex flex-wrap gap-2">
        {#each filters as filter (filter.value)}
            <Link
                href={toUrl(index({ query: { status: filter.value } }))}
                preserveScroll
                aria-current={status === filter.value ? 'page' : undefined}
                class="rounded-full border px-3 py-1 text-sm aria-[current=page]:bg-primary aria-[current=page]:text-primary-foreground"
                >{filter.label}{filter.value === 'offen' ? ` (${openCount})` : ''}</Link
            >
        {/each}
    </nav>

    {#if requests.data.length === 0}
        <p class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
            Keine Rückmeldungen.
        </p>
    {:else}
        <ol class="divide-y rounded-lg border">
            {#each requests.data as item (item.id)}
                <li>
                    <Link
                        href={toUrl(show(item.id))}
                        class="grid gap-1 p-3 text-sm hover:bg-accent sm:grid-cols-[9rem_1fr_auto] sm:gap-3"
                    >
                        <time class="text-muted-foreground tabular-nums" datetime={item.at}
                            >{formatter.format(new Date(item.at))}</time
                        >
                        <span class="min-w-0">
                            <span class="block break-words">{item.message}</span>
                            <span class="block truncate text-muted-foreground"
                                >{item.user ?? 'Gast'} · {item.component ?? '–'} · {item.url}</span
                            >
                        </span>
                        <span class="flex items-start gap-2 sm:flex-col sm:items-end">
                            <span class="font-mono text-xs">{item.reference}</span>
                            <span
                                class={item.status === 'offen'
                                    ? 'rounded bg-amber-100 px-1.5 text-xs text-amber-900 dark:bg-amber-900/40 dark:text-amber-100'
                                    : 'rounded bg-muted px-1.5 text-xs text-muted-foreground'}
                                >{item.status}</span
                            >
                        </span>
                    </Link>
                </li>
            {/each}
        </ol>
        <div class="flex items-center justify-between text-sm text-muted-foreground">
            <span>{requests.from}–{requests.to} von {requests.total}</span>
            <div class="flex gap-4">
                {#if requests.prev_page_url}
                    <Link href={requests.prev_page_url} preserveScroll class="hover:text-foreground"
                        >Neuere</Link
                    >
                {/if}
                {#if requests.next_page_url}
                    <Link href={requests.next_page_url} preserveScroll class="hover:text-foreground"
                        >Ältere</Link
                    >
                {/if}
            </div>
        </div>
    {/if}
</div>
