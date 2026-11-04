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
    import { disciplinePdf, pdf } from '@/routes/record';
    import type {
        DisciplinePrint,
        NotRecordedEntry,
        RecordEntry,
        RecordTotal,
    } from '@/types/record';

    /** stornierte Zeilen kommen mit, durchgestrichen und ohne Zählung (K8) */
    type Entry = RecordEntry & { cancelled_by: string | null };

    let {
        entries,
        totals,
        catalog,
        disciplines,
        filters,
        notRecorded = [],
    }: {
        entries: Entry[];
        totals: RecordTotal[];
        catalog: boolean;
        disciplines: DisciplinePrint[];
        filters: { from: string; to: string };
        notRecorded?: NotRecordedEntry[];
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
            description="Die Kladde führt der Verein. Hier sehen Sie Ihre Termine, bestätigt von der Standaufsicht."
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
        {#if entries.some((e) => !e.active)}
            <p class="text-xs text-muted-foreground">Stornierte Einträge zählen nicht.</p>
        {/if}
        <p class="text-xs text-muted-foreground">
            Keine Bewertung: Ob die Voraussetzungen nach § 14 WaffG erfüllt sind, bescheinigt
            Ihr Schießsportverband. Für den Nachweis braucht er den vom Vorstand unterschriebenen
            Auszug.
        </p>
    </section>

    <section class="grid gap-3" aria-labelledby="discipline-prints">
        <h2 id="discipline-prints" class="text-lg font-semibold">Teilnahmenachweis je Disziplin</h2>
        {#if !catalog}
            <p class="text-sm text-muted-foreground">
                Nicht verfügbar, solange kein Disziplinkatalog gewählt ist.
            </p>
        {:else if disciplines.length === 0}
            <p class="text-sm text-muted-foreground">Im Zeitraum gibt es keine Termine mit Disziplin.</p>
        {:else}
            <div class="flex flex-wrap gap-2">
                {#each disciplines as d (d.key)}
                    <Button variant="outline" size="sm" asChild>
                        {#snippet children(props)}
                            <a href={toUrl(disciplinePdf({ query: { discipline: d.key, from: filters.from, to: filters.to } }))} class={props.class}>
                                <Download class="size-4" />
                                {d.label} ({d.days} {d.days === 1 ? 'Tag' : 'Tage'})
                            </a>
                        {/snippet}
                    </Button>
                {/each}
            </div>
            <p class="text-xs text-muted-foreground">
                Zur Information. Den Nachweis zur Vorlage beim Verband unterschreibt der Vorstand.
            </p>
        {/if}
    </section>

    <section class="grid gap-3" aria-labelledby="entries">
        <h2 id="entries" class="text-lg font-semibold">Termine</h2>
        {#if entries.length === 0}
            <p class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
                Im Zeitraum sind keine Termine in der Kladde. Termine erscheinen hier, sobald die
                Standaufsicht die Session abgezeichnet hat.
            </p>
        {:else}
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <!-- Am Handy drei Spalten: Waffe steht unter der Kategorie, damit Schüsse ohne Wischen sichtbar bleiben. -->
                        <tr>
                            <th class="px-2 py-2.5 font-medium md:px-4">Datum</th>
                            <th class="px-2 py-2.5 font-medium md:px-4">Kategorie</th>
                            <th class="hidden px-4 py-2.5 font-medium md:table-cell">Waffe</th>
                            <th class="px-2 py-2.5 text-right font-medium md:px-4">Schüsse</th>
                            <th class="hidden px-4 py-2.5 font-medium md:table-cell">Erfasst über</th>
                            <th class="hidden px-4 py-2.5 font-medium md:table-cell">Bestätigt von</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each entries as e (e.id)}
                            <tr class={e.active ? '' : 'text-muted-foreground'}>
                                <!-- Am Handy rutschen die Vermerke unter das Datum, das Datum selbst bricht nicht um. -->
                                <td class="px-2 py-2.5 tabular-nums md:px-4 md:whitespace-nowrap">
                                    <span class="whitespace-nowrap"
                                        ><span class={e.active ? 'text-muted-foreground' : 'line-through'}>{formatWeekday(e.date)}</span>
                                        <span class={e.active ? '' : 'line-through'}>{formatDate(e.date)}</span></span
                                    >
                                    {#if e.late}<Badge variant="outline" class="md:ml-1">Nachtrag</Badge>{/if}
                                    {#if e.correction}<Badge variant="outline" class="md:ml-1">Korrektur</Badge>{/if}
                                </td>
                                <td class="px-2 py-2.5 md:px-4">
                                    <span class={e.active ? '' : 'line-through'}
                                        >{e.weapon_label}{e.discipline ? ` · ${e.discipline}` : ''}</span
                                    >
                                    <span class="block md:hidden {e.active ? 'text-muted-foreground' : 'line-through'}"
                                        >{e.weapon_owner ?? '–'}</span
                                    >
                                    {#if !e.active}
                                        <span class="block text-xs"
                                            >storniert{e.cancelled_by ? ` von ${e.cancelled_by}` : ''}{e.cancel_reason
                                                ? `: ${e.cancel_reason}`
                                                : ''}</span
                                        >
                                    {:else if e.correction}
                                        <!-- am Handy ist „Bestätigt von“ ausgeblendet; dort steht sonst der Grund -->
                                        <span class="block text-xs text-muted-foreground md:hidden"
                                            >Korrektur: {e.late_reason}</span
                                        >
                                    {/if}
                                </td>
                                <td class="hidden px-4 py-2.5 md:table-cell"><span class={e.active ? '' : 'line-through'}>{e.weapon_owner ?? '–'}</span></td>
                                <td class="px-2 py-2.5 text-right whitespace-nowrap tabular-nums md:px-4"><span class={e.active ? '' : 'line-through'}>{e.shots ?? '–'}</span></td>
                                <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell">{e.via}</td>
                                <td class="hidden px-4 py-2.5 text-muted-foreground md:table-cell"
                                    >{e.confirmed_by}</td
                                >
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>

    {#if notRecorded.length > 0}
        <section class="grid gap-3" aria-labelledby="not-recorded">
            <h2 id="not-recorded" class="text-lg font-semibold">Nicht in der Kladde</h2>
            <p class="text-sm text-muted-foreground">
                Ihre Einträge im Zeitraum, die nicht abgezeichnet wurden: zurückgezogen,
                abgelehnt oder verfallen, weil die Session nicht am selben Tag abgezeichnet wurde.
                Sie zählen nicht.
            </p>
            <ul class="grid gap-2">
                {#each notRecorded as e (e.id)}
                    <li class="grid gap-1 rounded-lg border px-3 py-2 text-sm">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">
                                <span class="text-muted-foreground">{formatWeekday(e.date)}</span>
                                {formatDate(e.date)}, {e.time} Uhr
                            </span>
                            <span class="min-w-0 flex-1"
                                >{e.weapon_label}{e.weapon_owner ? ` · ${e.weapon_owner}` : ''}{e.discipline
                                    ? ` · ${e.discipline}`
                                    : ''} · {e.shots === 1 ? '1 Schuss' : `${e.shots ?? '–'} Schüsse`} · über {e.via}</span
                            >
                            <Badge variant={e.status === 'rejected' ? 'destructive' : 'secondary'}
                                >{e.status_label}</Badge
                            >
                        </div>
                        <p class="text-muted-foreground">Aufsicht {e.supervisor}</p>
                        {#if e.status === 'rejected'}
                            <p class="text-destructive">
                                Abgelehnt{e.rejected_by ? ` von ${e.rejected_by}` : ''}{e.reject_reason
                                    ? `: ${e.reject_reason}`
                                    : ''}
                            </p>
                        {/if}
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</div>
