<script module lang="ts">
    import { index } from '@/routes/record';

    export const layout = {
        breadcrumbs: [{ title: 'Schießkladde', href: index() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Download from '@lucide/svelte/icons/download';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import Totals from '@/components/record/Totals.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { formatDate, formatWeekday } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { pdf } from '@/routes/record';
    import type { RecordEntry, RecordTotal } from '@/types/record';

    let {
        entries,
        totals,
        filters,
    }: {
        entries: RecordEntry[];
        totals: RecordTotal[];
        filters: { from: string; to: string };
    } = $props();

    // svelte-ignore state_referenced_locally
    let from = $state(filters.from);
    // svelte-ignore state_referenced_locally
    let to = $state(filters.to);

    function apply() {
        router.get(toUrl(index()), { from, to }, { preserveState: true, preserveScroll: true });
    }

    function months(count: number) {
        const end = new Date();
        const start = new Date(end);
        start.setMonth(start.getMonth() - count);
        start.setDate(start.getDate() + 1);
        const iso = (d: Date) =>
            `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
        from = iso(start);
        to = iso(end);
        apply();
    }
</script>

<AppHead title="Schießkladde" />

<div class="flex max-w-4xl flex-col gap-8 p-4 md:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Meine Einträge in der Schießkladde"
            description="Die Kladde führt der Verein. Hier sehen Sie Ihre Termine, eingetragen von der Standaufsicht."
        />
        <Button asChild>
            {#snippet children(props)}
                <a href={toUrl(pdf({ query: { from, to } }))} class={props.class}>
                    <Download class="size-4" />
                    Auszug zur Information
                </a>
            {/snippet}
        </Button>
    </div>

    <form
        class="flex flex-wrap items-end gap-2"
        onsubmit={(e) => {
            e.preventDefault();
            apply();
        }}
    >
        <div class="grid gap-1">
            <Label for="from" class="text-xs">Von</Label>
            <Input id="from" type="date" bind:value={from} class="w-40" />
        </div>
        <div class="grid gap-1">
            <Label for="to" class="text-xs">Bis</Label>
            <Input id="to" type="date" bind:value={to} class="w-40" />
        </div>
        <Button type="submit" variant="outline">Anzeigen</Button>
        <Button type="button" variant="ghost" onclick={() => months(12)}>letzte 12 Monate</Button>
        <Button type="button" variant="ghost" onclick={() => months(24)}>letzte 24 Monate</Button>
    </form>

    <section class="grid gap-3" aria-labelledby="totals">
        <h2 id="totals" class="text-lg font-semibold">Anzahl Tage im Zeitraum</h2>
        <Totals {totals} />
        <p class="text-xs text-muted-foreground">
            Keine Bewertung: Ob die Voraussetzungen nach § 14 WaffG erfüllt sind, bescheinigt
            Ihr Schießsportverband. Für den Nachweis braucht er den vom Vorstand unterschriebenen
            Auszug.
        </p>
    </section>

    <section class="grid gap-3" aria-labelledby="entries">
        <h2 id="entries" class="text-lg font-semibold">Termine</h2>
        {#if entries.length === 0}
            <p class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                Im Zeitraum sind keine Termine eingetragen. Termine erscheinen hier, sobald die
                Standaufsicht Sie eingetragen hat.
            </p>
        {:else}
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Datum</th>
                            <th class="px-4 py-2.5 font-medium">Kategorie</th>
                            <th class="px-4 py-2.5 font-medium">Waffe</th>
                            <th class="hidden px-4 py-2.5 font-medium md:table-cell">Eingetragen von</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each entries as e (e.id)}
                            <tr>
                                <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                                    <span class="text-muted-foreground">{formatWeekday(e.date)}</span>
                                    {formatDate(e.date)}
                                    {#if e.late}<Badge variant="outline" class="ml-1">Nachtrag</Badge>{/if}
                                </td>
                                <td class="px-4 py-2.5"
                                    >{e.weapon_label}{e.discipline ? ` · ${e.discipline}` : ''}</td
                                >
                                <td class="px-4 py-2.5">{e.weapon_owner ?? '–'}</td>
                                <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell"
                                    >{e.recorded_by}</td
                                >
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>
</div>
