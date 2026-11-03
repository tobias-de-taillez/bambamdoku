<script module lang="ts">
    import { index } from '@/routes/bookings';

    export const layout = {
        breadcrumbs: [{ title: 'Meine Buchungen', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { formatDate, formatLongDate, weaponTypeLabels } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { destroy } from '@/routes/bookings';
    import { index as daysIndex, show } from '@/routes/shooting-days';
    import type { MyBooking } from '@/types/booking';
    import { statusLabels } from '@/types/booking';

    let { upcoming, past }: { upcoming: MyBooking[]; past: MyBooking[] } =
        $props();
</script>

<AppHead title="Meine Buchungen" />

<div class="flex max-w-3xl flex-col gap-8 p-4 md:p-6">
    <Heading title="Meine Buchungen" />

    <section class="grid gap-2">
        <h2 class="text-sm font-medium text-muted-foreground">Anstehend</h2>
        {#if upcoming.length === 0}
            <div
                class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
            >
                Keine anstehenden Buchungen.
                <Link href={toUrl(daysIndex())} class="font-medium text-foreground underline"
                    >Schießtage ansehen</Link
                >
            </div>
        {:else}
            <ul class="divide-y rounded-lg border">
                {#each upcoming as item (item.id)}
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 p-3">
                        <Link
                            href={toUrl(show(item.shooting_day_id ?? 0))}
                            class="font-medium hover:underline"
                        >
                            {formatLongDate(item.date ?? '')}
                        </Link>
                        <span class="tabular-nums"
                            >{item.starts_at}–{item.ends_at} Uhr</span
                        >
                        <span class="text-sm text-muted-foreground"
                            >{item.range} · {weaponTypeLabels[item.weapon_type]}</span
                        >
                        {#if item.guest_name}
                            <Badge variant="secondary">Gast: {item.guest_name}</Badge>
                        {/if}
                        {#if item.can_cancel}
                            <Button
                                variant="ghost"
                                size="sm"
                                class="ml-auto"
                                onclick={() =>
                                    router.delete(toUrl(destroy(item.id)), {
                                        preserveScroll: true,
                                    })}>Stornieren</Button
                            >
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
    </section>

    {#if past.length > 0}
        <section class="grid gap-2">
            <h2 class="text-sm font-medium text-muted-foreground">
                Vergangen und storniert
            </h2>
            <ul class="divide-y rounded-lg border text-sm">
                {#each past as item (item.id)}
                    <li class="flex flex-wrap items-center gap-x-3 gap-y-1 p-3">
                        <span class="tabular-nums">{formatDate(item.date)}</span>
                        <span class="tabular-nums text-muted-foreground"
                            >{item.starts_at}–{item.ends_at}</span
                        >
                        <span class="text-muted-foreground">{item.range}</span>
                        {#if item.guest_name}
                            <span class="text-muted-foreground"
                                >Gast: {item.guest_name}</span
                            >
                        {/if}
                        <Badge
                            variant={item.status === 'checked_in'
                                ? 'default'
                                : 'outline'}
                            class="ml-auto">{statusLabels[item.status]}</Badge
                        >
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</div>
