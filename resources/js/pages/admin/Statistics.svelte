<script module lang="ts">
    import { statistics } from '@/routes/admin';

    export const layout = {
        breadcrumbs: [{ title: 'Statistik', href: statistics() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import ChevronLeft from '@lucide/svelte/icons/chevron-left';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';

    let {
        year,
        totals,
        months,
        classes,
        topMembers,
        duties,
    }: {
        year: number;
        totals: {
            days: number;
            cancelled: number;
            visits: number;
            guests: number;
            members: number;
            no_shows: number;
        };
        months: { month: number; visits: number; guests: number }[];
        classes: { name: string; visits: number }[];
        topMembers: { name: string; visits: number }[];
        duties: { name: string; duties: number; hours: number }[];
    } = $props();

    const monthNames = ['Jan', 'Feb', 'Mär', 'Apr', 'Mai', 'Jun', 'Jul', 'Aug', 'Sep', 'Okt', 'Nov', 'Dez'];
    const peak = $derived(Math.max(1, ...months.map((m) => m.visits)));
    const classPeak = $derived(Math.max(1, ...classes.map((r) => r.visits)));

    function go(target: number) {
        router.get(toUrl(statistics()), { jahr: target }, { preserveScroll: true });
    }

    const tiles = $derived([
        { label: 'Schießtage', value: totals.days, note: totals.cancelled ? `${totals.cancelled} abgesagt` : '' },
        { label: 'Besuche', value: totals.visits, note: 'Einträge in der Kladde' },
        { label: 'Aktive Mitglieder', value: totals.members, note: 'mind. ein Besuch' },
        { label: 'Gäste', value: totals.guests, note: '' },
        { label: 'Nicht erschienen', value: totals.no_shows, note: 'gebucht, aber gefehlt' },
    ]);
</script>

<AppHead title="Statistik {year}" />

<div class="flex max-w-5xl flex-col gap-8 p-4 md:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Statistik {year}"
            description="Aktivität des Vereins aus der Kladde, Grundlage für Jahresbericht und Planung."
        />
        <div class="flex items-center gap-1">
            <Button variant="outline" size="icon" aria-label="Vorjahr" onclick={() => go(year - 1)}>
                <ChevronLeft class="size-4" />
            </Button>
            <span class="w-14 text-center font-medium tabular-nums">{year}</span>
            <Button variant="outline" size="icon" aria-label="Folgejahr" onclick={() => go(year + 1)}>
                <ChevronRight class="size-4" />
            </Button>
        </div>
    </div>

    <dl class="grid grid-cols-2 gap-3 md:grid-cols-5">
        {#each tiles as tile (tile.label)}
            <div class="rounded-lg border p-4">
                <dt class="text-sm text-muted-foreground">{tile.label}</dt>
                <dd class="mt-1 text-2xl font-semibold tabular-nums">{tile.value}</dd>
                {#if tile.note}
                    <dd class="text-xs text-muted-foreground">{tile.note}</dd>
                {/if}
            </div>
        {/each}
    </dl>

    <section class="grid gap-3">
        <h2 class="font-medium">Besuche je Monat</h2>
        <div class="flex h-48 items-end gap-2 rounded-lg border p-4" role="img" aria-label="Besuche je Monat">
            {#each months as m (m.month)}
                <div class="flex h-full flex-1 flex-col items-center justify-end gap-1">
                    <span class="text-xs text-muted-foreground tabular-nums">{m.visits || ''}</span>
                    <div
                        class="w-full rounded-t-sm bg-foreground/70"
                        style="height: {(m.visits / peak) * 100}%"
                        title="{monthNames[m.month - 1]}: {m.visits} Besuche, davon {m.guests} Gäste"
                    ></div>
                    <span class="text-xs text-muted-foreground">{monthNames[m.month - 1]}</span>
                </div>
            {/each}
        </div>
    </section>

    <div class="grid gap-6 lg:grid-cols-3">
        <section class="grid content-start gap-3">
            <h2 class="font-medium">Kategorien</h2>
            {#if classes.length === 0}
                <p class="text-sm text-muted-foreground">Keine Besuche.</p>
            {:else}
                <ul class="grid gap-3">
                    {#each classes as r (r.name)}
                        <li class="grid gap-1 text-sm">
                            <div class="flex justify-between">
                                <span>{r.name}</span>
                                <span class="text-muted-foreground tabular-nums">{r.visits}</span>
                            </div>
                            <div class="h-2 rounded-full bg-muted">
                                <div
                                    class="h-full rounded-full bg-foreground/60"
                                    style="width: {(r.visits / classPeak) * 100}%"
                                ></div>
                            </div>
                        </li>
                    {/each}
                </ul>
            {/if}
        </section>

        <section class="grid content-start gap-3">
            <h2 class="font-medium">Aktivste Mitglieder</h2>
            {#if topMembers.length === 0}
                <p class="text-sm text-muted-foreground">Keine Besuche.</p>
            {:else}
                <ol class="divide-y rounded-lg border text-sm">
                    {#each topMembers as m, i (m.name + i)}
                        <li class="flex justify-between px-3 py-2">
                            <span>{i + 1}. {m.name}</span>
                            <span class="text-muted-foreground tabular-nums">{m.visits} Tage</span>
                        </li>
                    {/each}
                </ol>
            {/if}
        </section>

        <section class="grid content-start gap-3">
            <h2 class="font-medium">Aufsichtsdienste</h2>
            {#if duties.length === 0}
                <p class="text-sm text-muted-foreground">Keine Dienste.</p>
            {:else}
                <ol class="divide-y rounded-lg border text-sm">
                    {#each duties as d (d.name)}
                        <li class="flex justify-between px-3 py-2">
                            <span>{d.name}</span>
                            <span class="text-muted-foreground tabular-nums"
                                >{d.duties} · {d.hours} Std.</span
                            >
                        </li>
                    {/each}
                </ol>
            {/if}
        </section>
    </div>
</div>
