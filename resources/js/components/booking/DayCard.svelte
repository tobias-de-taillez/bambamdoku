<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import ChevronRight from '@lucide/svelte/icons/chevron-right';
    import SupervisionBadge from '@/components/booking/SupervisionBadge.svelte';
    import { Badge } from '@/components/ui/badge';
    import { formatLongDate, isToday } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { show } from '@/routes/shooting-days';
    import type { DaySummary } from '@/types/booking';

    let { day }: { day: DaySummary } = $props();

    const load = $derived(
        day.capacity > 0 ? Math.min(100, Math.round((day.booked / day.capacity) * 100)) : 0,
    );
</script>

<Link
    href={toUrl(show(day.id))}
    class="group flex items-center gap-4 rounded-lg border bg-card p-4 transition-colors hover:border-foreground/20 hover:bg-muted/40 {day.cancelled
        ? 'opacity-70'
        : ''}"
>
    <div class="min-w-0 flex-1 space-y-1.5">
        <div class="flex flex-wrap items-baseline gap-x-2">
            <span class="font-medium {day.cancelled ? 'line-through' : ''}">
                {isToday(day.date) ? 'Heute' : formatLongDate(day.date)}
            </span>
            <span class="text-sm text-muted-foreground tabular-nums"
                >{day.starts_at}–{day.ends_at} Uhr</span
            >
            {#if day.title}
                <span class="text-sm text-muted-foreground">· {day.title}</span>
            {/if}
        </div>
        {#if day.cancelled}
            <Badge variant="destructive"
                >Abgesagt{day.cancel_reason ? `: ${day.cancel_reason}` : ''}</Badge
            >
        {:else}
            <SupervisionBadge state={day.supervision} names={day.supervisors} />
            <div class="flex flex-wrap items-center gap-2">
                <div
                    class="h-1.5 w-24 overflow-hidden rounded-full bg-muted"
                    title="{day.booked} von {day.capacity} Bahnen gebucht"
                >
                    <div
                        class="h-full rounded-full bg-foreground/60"
                        style="width: {load}%"
                    ></div>
                </div>
                <span class="text-xs text-muted-foreground"
                    >{day.booked} Buchungen · {day.ranges.join(', ')}</span
                >
                {#if day.my_booking_count > 0}
                    <Badge>Gebucht</Badge>
                {/if}
                {#if day.i_supervise}
                    <Badge variant="outline">Sie haben Aufsicht</Badge>
                {/if}
            </div>
        {/if}
    </div>
    <ChevronRight
        class="size-5 shrink-0 text-muted-foreground transition-transform group-hover:translate-x-0.5"
    />
</Link>
