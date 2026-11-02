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
    import Checks from '@/components/record/Checks.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { formatDate, formatWeekday } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { pdf } from '@/routes/record';
    import type {
        AcquisitionCheck,
        PossessionCheck,
        RecordEntry,
    } from '@/types/record';

    let {
        entries,
        totals,
        acquisition,
        possession,
        filters,
    }: {
        entries: RecordEntry[];
        totals: Record<string, number>;
        acquisition: AcquisitionCheck;
        possession: PossessionCheck[];
        filters: { from: string; to: string };
    } = $props();

    // svelte-ignore state_referenced_locally
    let from = $state(filters.from);
    // svelte-ignore state_referenced_locally
    let to = $state(filters.to);

    function apply() {
        router.get(toUrl(index()), { from, to }, { preserveState: true, preserveScroll: true });
    }

    const totalLabels: Record<string, string> = {
        kurzwaffe: 'Kurzwaffe',
        langwaffe: 'Langwaffe',
        luftdruck: 'Luftdruck',
        bogen: 'Bogen',
    };
</script>

<AppHead title="Schießkladde" />

<div class="flex max-w-5xl flex-col gap-8 p-4 md:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Meine Schießkladde"
            description="Ihre von der Standaufsicht bestätigten Schießtermine und der Stand für den Bedürfnisnachweis."
        />
        <Button asChild>
            {#snippet children(props)}
                <a href={toUrl(pdf({ query: { from, to } }))} class={props.class}>
                    <Download class="size-4" />
                    Auszug als PDF
                </a>
            {/snippet}
        </Button>
    </div>

    <section class="grid gap-3" aria-labelledby="checks">
        <h2 id="checks" class="text-lg font-semibold">Stand heute</h2>
        <Checks {acquisition} {possession} />
    </section>

    <section class="grid gap-3" aria-labelledby="entries">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <h2 id="entries" class="text-lg font-semibold">Termine</h2>
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
            </form>
        </div>

        <div class="flex flex-wrap gap-2 text-sm">
            {#each Object.entries(totals).filter(([, n]) => n > 0) as [type, count] (type)}
                <span class="rounded-full border px-3 py-1"
                    >{totalLabels[type]}: <strong class="tabular-nums">{count}</strong></span
                >
            {/each}
        </div>

        {#if entries.length === 0}
            <p
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Im Zeitraum sind keine bestätigten Termine erfasst. Termine erscheinen
                hier, sobald die Standaufsicht Ihre Anwesenheit bestätigt hat.
            </p>
        {:else}
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Datum</th>
                            <th class="px-4 py-2.5 font-medium">Zeit</th>
                            <th class="px-4 py-2.5 font-medium">Stand</th>
                            <th class="px-4 py-2.5 font-medium">Waffe</th>
                            <th class="hidden px-4 py-2.5 font-medium md:table-cell"
                                >Bestätigt von</th
                            >
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each entries as e (e.id)}
                            <tr>
                                <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                                    <span class="text-muted-foreground"
                                        >{formatWeekday(e.date)}</span
                                    >
                                    {formatDate(e.date)}
                                </td>
                                <td class="px-4 py-2.5 tabular-nums"
                                    >{e.starts_at}–{e.ends_at}</td
                                >
                                <td class="px-4 py-2.5">{e.range}</td>
                                <td class="px-4 py-2.5"
                                    >{e.weapon_label}<span class="text-muted-foreground"
                                        >{e.club_weapon ? ' · Verein' : ' · eigene'}</span
                                    ></td
                                >
                                <td
                                    class="hidden px-4 py-2.5 text-muted-foreground md:table-cell"
                                    >{e.confirmed_by ?? '–'}</td
                                >
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>
</div>
