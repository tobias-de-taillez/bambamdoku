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
    import { regenerate } from '@/routes/calendar';
    import CalendarPlus from '@lucide/svelte/icons/calendar-plus';
    import { index as daysIndex, show } from '@/routes/shooting-days';
    import type { MyBooking } from '@/types/booking';
    import { statusLabels } from '@/types/booking';

    let {
        upcoming,
        past,
        calendarUrl,
    }: { upcoming: MyBooking[]; past: MyBooking[]; calendarUrl: string } =
        $props();

    let copied = $state(false);

    async function copyCalendar() {
        await navigator.clipboard.writeText(calendarUrl);
        copied = true;
        setTimeout(() => (copied = false), 2000);
    }
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

    <section class="grid gap-3 rounded-lg border p-4">
        <h2 class="flex items-center gap-2 font-medium">
            <CalendarPlus class="size-4" />
            Im eigenen Kalender abonnieren
        </h2>
        <p class="text-sm text-muted-foreground">
            Ihre Buchungen und Aufsichtsdienste erscheinen automatisch in Ihrem
            Handy- oder Computerkalender. Fügen Sie diese Adresse dort als
            Kalenderabo hinzu. Behandeln Sie den Link wie ein Passwort.
        </p>
        <div class="flex flex-wrap gap-2">
            <input
                readonly
                value={calendarUrl}
                aria-label="Kalenderadresse"
                class="h-9 min-w-0 flex-1 rounded-md border border-input bg-muted/40 px-3 font-mono text-xs"
                onfocus={(e) => (e.currentTarget as HTMLInputElement).select()}
            />
            <Button variant="outline" onclick={copyCalendar}
                >{copied ? 'Kopiert' : 'Kopieren'}</Button
            >
            <Button
                variant="ghost"
                onclick={() => router.post(toUrl(regenerate()), {}, { preserveScroll: true })}
                >Neuen Link erzeugen</Button
            >
        </div>
    </section>
</div>
