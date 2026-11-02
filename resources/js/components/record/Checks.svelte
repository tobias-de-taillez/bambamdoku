<script lang="ts">
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import CircleDashed from '@lucide/svelte/icons/circle-dashed';
    import type { AcquisitionCheck, PossessionCheck } from '@/types/record';

    let {
        acquisition,
        possession,
    }: { acquisition: AcquisitionCheck; possession: PossessionCheck[] } = $props();

    const monthLabel = new Intl.DateTimeFormat('de-DE', { month: 'narrow' });
    const monthLong = new Intl.DateTimeFormat('de-DE', {
        month: 'long',
        year: 'numeric',
    });

    function month(value: string, long = false): string {
        const [y, m] = value.split('-').map(Number);
        const date = new Date(y, m - 1, 1);

        return long ? monthLong.format(date) : monthLabel.format(date);
    }
</script>

<div class="grid gap-4 lg:grid-cols-3">
    <article class="rounded-lg border p-4">
        <header class="flex items-start justify-between gap-2">
            <div>
                <h3 class="font-medium">Erwerb</h3>
                <p class="text-xs text-muted-foreground">§ 14 Abs. 3 Nr. 2 WaffG</p>
            </div>
            {#if acquisition.fulfilled}
                <CircleCheck class="size-5 text-emerald-600" aria-label="erfüllt" />
            {:else}
                <CircleDashed class="size-5 text-muted-foreground" aria-label="nicht erfüllt" />
            {/if}
        </header>
        <p class="mt-3 text-sm">
            Jeden Monat oder 18-mal in den letzten 12 Monaten.
        </p>
        <div class="mt-3 flex gap-1" role="list" aria-label="Monate mit Training">
            {#each acquisition.months as m (m.month)}
                <div
                    role="listitem"
                    class="flex flex-1 flex-col items-center gap-1"
                    title="{month(m.month, true)}: {m.count} Termine"
                >
                    <div
                        class="h-6 w-full rounded-sm {m.count > 0
                            ? 'bg-emerald-500'
                            : 'bg-muted'}"
                    ></div>
                    <span class="text-[10px] text-muted-foreground">{month(m.month)}</span>
                </div>
            {/each}
        </div>
        <p class="mt-3 text-sm text-muted-foreground tabular-nums">
            {acquisition.months_covered} von 12 Monaten · {acquisition.total} Termine
        </p>
    </article>

    {#each possession as p (p.weapon_type)}
        <article class="rounded-lg border p-4">
            <header class="flex items-start justify-between gap-2">
                <div>
                    <h3 class="font-medium">Besitz {p.label}</h3>
                    <p class="text-xs text-muted-foreground">
                        § 14 Abs. 4 WaffG · eigene Waffe
                    </p>
                </div>
                {#if p.fulfilled}
                    <CircleCheck class="size-5 text-emerald-600" aria-label="erfüllt" />
                {:else}
                    <CircleDashed class="size-5 text-muted-foreground" aria-label="nicht erfüllt" />
                {/if}
            </header>
            <p class="mt-3 text-sm">
                24 Monate: alle drei Monate oder 6-mal in 12 Monaten.
            </p>
            <div class="mt-3 flex gap-1" role="list" aria-label="Quartale mit Training">
                {#each p.quarters as q, i (q.from)}
                    <div
                        role="listitem"
                        class="h-6 flex-1 rounded-sm {q.count > 0
                            ? 'bg-emerald-500'
                            : 'bg-muted'}"
                        title="Zeitraum {i + 1}: {q.count} Termine"
                    ></div>
                {/each}
            </div>
            <p class="mt-3 text-sm text-muted-foreground tabular-nums">
                {#if p.total === 0}
                    Keine Termine mit eigener {p.label}
                {:else}
                    {p.quarters_covered} von 8 Zeiträumen · bestes Jahr {p.best_twelve_months}-mal
                {/if}
            </p>
        </article>
    {/each}
</div>
<p class="text-xs text-muted-foreground">
    Orientierung für Sie und den Verein. Maßgeblich ist die Bescheinigung Ihres
    Schießsportverbands. Gezählt wird ein Termin je Tag, nur von der Standaufsicht
    bestätigte Besuche.
</p>
