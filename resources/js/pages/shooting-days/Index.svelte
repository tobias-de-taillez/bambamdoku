<script module lang="ts">
    import { index } from '@/routes/shooting-days';

    export const layout = {
        breadcrumbs: [{ title: 'Schießtage', href: index() }],
    };
</script>

<script lang="ts">
    import AppHead from '@/components/AppHead.svelte';
    import DayCard from '@/components/booking/DayCard.svelte';
    import Heading from '@/components/Heading.svelte';
    import { formatMonth } from '@/lib/format';
    import type { DaySummary } from '@/types/booking';

    let { days }: { days: DaySummary[] } = $props();

    const months = $derived.by(() => {
        const groups: { month: string; days: DaySummary[] }[] = [];

        for (const day of days) {
            const month = formatMonth(day.date);
            const last = groups.at(-1);

            if (last?.month === month) {
                last.days.push(day);
            } else {
                groups.push({ month, days: [day] });
            }
        }

        return groups;
    });
</script>

<AppHead title="Schießtage" />

<div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Schießtage"
        description="Wählen Sie einen Tag, um eine Bahn zu buchen oder sich als Standaufsicht einzutragen."
    />

    {#if days.length === 0}
        <p
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Zurzeit sind keine Schießtage geplant.
        </p>
    {/if}

    {#each months as group (group.month)}
        <section class="grid gap-2">
            <h3 class="text-sm font-medium text-muted-foreground">
                {group.month}
            </h3>
            {#each group.days as day (day.id)}
                <DayCard {day} />
            {/each}
        </section>
    {/each}
</div>
