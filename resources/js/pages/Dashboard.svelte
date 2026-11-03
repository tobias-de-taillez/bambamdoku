<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [{ title: 'Übersicht', href: dashboard() }],
    };
</script>

<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import CalendarDays from '@lucide/svelte/icons/calendar-days';
    import NotebookPen from '@lucide/svelte/icons/notebook-pen';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { formatDate, formatLongDate, isToday } from '@/lib/format';
    import { edit as editMember } from '@/routes/admin/users';
    import { toUrl } from '@/lib/utils';
    import { index as bookingsIndex } from '@/routes/bookings';
    import { show as rangeOffice } from '@/routes/range-office';
    import { show as todayShow } from '@/routes/today';
    import { index as daysIndex, show } from '@/routes/shooting-days';

    type DayItem = {
        id: number;
        date: string;
        starts_at: string;
        ends_at: string;
        title: string | null;
        supervised?: boolean;
        booked?: number;
        has_any?: boolean;
    };

    type Entry = {
        id: number;
        shooting_day_id: number;
        date: string;
        starts_at: string;
        ends_at: string;
        range: string | null;
    };

    let {
        nextDays,
        myBookings,
        myDuties,
        needsSupervision,
        canSupervise,
        qualificationExpires = null,
        expiringQualifications = [],
        today = null,
    }: {
        nextDays: DayItem[];
        myBookings: Entry[];
        myDuties: Entry[];
        needsSupervision: DayItem[];
        canSupervise: boolean;
        qualificationExpires?: string | null;
        expiringQualifications?: { id: number; name: string; until: string }[];
        today?: {
            open: boolean;
            supervisor: string;
            pending: number;
            rejected: number;
        } | null;
    } = $props();

    const firstName = $derived(page.props.auth.user.name.split(' ')[0]);

    function when(date: string): string {
        return isToday(date) ? 'Heute' : formatLongDate(date);
    }
</script>

<AppHead title="Übersicht" />

<div class="flex max-w-5xl flex-col gap-8 p-4 md:p-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Hallo, {firstName}
            </h1>
            <p class="text-muted-foreground">
                {myBookings.length > 0
                    ? `Ihre nächste Buchung: ${when(myBookings[0].date)}, ${myBookings[0].starts_at} Uhr.`
                    : 'Sie haben gerade keine Buchung.'}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            {#if page.props.auth.supervisesToday}
                <Button asChild>
                    {#snippet children(props)}
                        <Link href={toUrl(rangeOffice())} class={props.class}>
                            <NotebookPen class="size-4" />
                            Am Stand eintragen
                        </Link>
                    {/snippet}
                </Button>
            {/if}
            <Button variant={page.props.auth.supervisesToday ? 'outline' : 'default'} asChild>
                {#snippet children(props)}
                    <Link href={toUrl(daysIndex())} class={props.class}>
                        <CalendarDays class="size-4" />
                        Bahn buchen
                    </Link>
                {/snippet}
            </Button>
        </div>
    </div>

    {#if today}
        <section
            class="flex flex-wrap items-center gap-4 rounded-xl border-2 border-emerald-600/40 bg-emerald-50 p-4 dark:bg-emerald-950/30"
            aria-labelledby="today-card"
        >
            <div class="min-w-0 flex-1">
                <h2 id="today-card" class="text-lg font-semibold">Heute schießen</h2>
                <p class="text-sm text-muted-foreground">
                    {today.open
                        ? `Aktiviert von ${today.supervisor}. Erfassen Sie Ihre Schüsse hier am Handy.`
                        : 'Die Session ist abgezeichnet.'}
                    {today.pending > 0 ? ` ${today.pending} offen.` : ''}
                    {today.rejected > 0 ? ` ${today.rejected} abgelehnt – bitte ansehen.` : ''}
                </p>
            </div>
            <Button size="lg" class="h-12" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(todayShow())} class={props.class}>
                        <NotebookPen class="size-5" />
                        {today.open ? 'Erfassen' : 'Ansehen'}
                    </Link>
                {/snippet}
            </Button>
        </section>
    {/if}

    {#if qualificationExpires}
        <p
            class="rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-sm dark:bg-amber-950/30"
        >
            Ihre Qualifikation als Standaufsicht läuft am
            <strong>{formatDate(qualificationExpires)}</strong> ab. Danach können Sie
            nicht mehr als Aufsicht eingeteilt werden. Bitte reichen Sie den Nachweis
            rechtzeitig beim Vorstand ein.
        </p>
    {/if}

    {#if expiringQualifications.length > 0}
        <section class="rounded-lg border p-4">
            <h2 class="font-medium">Aufsichts-Qualifikationen laufen bald ab</h2>
            <ul class="mt-2 grid gap-1 text-sm">
                {#each expiringQualifications as q (q.id)}
                    <li>
                        <Link
                            href={toUrl(editMember(q.id))}
                            class="underline-offset-4 hover:underline">{q.name}</Link
                        >
                        <span class="text-muted-foreground">– {formatDate(q.until)}</span>
                    </li>
                {/each}
            </ul>
        </section>
    {/if}

    {#if needsSupervision.length > 0}
        <section
            class="rounded-lg border border-amber-500/40 bg-amber-50 p-4 dark:bg-amber-950/30"
        >
            <h2 class="flex items-center gap-2 font-medium">
                <ShieldAlert class="size-4 text-amber-700 dark:text-amber-400" />
                Hier fehlt noch Standaufsicht
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                {canSupervise
                    ? 'Ohne Aufsicht kann niemand buchen. Können Sie einspringen?'
                    : 'Teilen Sie eine Aufsicht ein, damit Mitglieder buchen können.'}
            </p>
            <ul class="mt-3 grid gap-1">
                {#each needsSupervision as day (day.id)}
                    <li>
                        <Link
                            href={toUrl(show(day.id))}
                            class="text-sm font-medium underline-offset-4 hover:underline"
                        >
                            {when(day.date)}, {day.starts_at}–{day.ends_at} Uhr{day.title
                                ? ` · ${day.title}`
                                : ''}
                            <span class="font-normal text-muted-foreground"
                                >{day.has_any ? '(teilweise besetzt)' : '(niemand)'}</span
                            >
                        </Link>
                    </li>
                {/each}
            </ul>
        </section>
    {/if}

    <div class="grid gap-6 md:grid-cols-2">
        <section class="grid content-start gap-2">
            <div class="flex items-baseline justify-between">
                <h2 class="font-medium">Meine Buchungen</h2>
                <Link
                    href={toUrl(bookingsIndex())}
                    class="text-sm text-muted-foreground hover:text-foreground">Alle</Link
                >
            </div>
            {#if myBookings.length === 0}
                <p class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                    Keine anstehenden Buchungen.
                </p>
            {:else}
                <ul class="divide-y rounded-lg border">
                    {#each myBookings as item (item.id)}
                        <li>
                            <Link
                                href={toUrl(show(item.shooting_day_id))}
                                class="flex items-baseline gap-3 p-3 hover:bg-muted/40"
                            >
                                <span class="font-medium">{when(item.date)}</span>
                                <span class="tabular-nums">{item.starts_at}–{item.ends_at}</span>
                                <span class="ml-auto truncate text-sm text-muted-foreground"
                                    >{item.range}</span
                                >
                            </Link>
                        </li>
                    {/each}
                </ul>
            {/if}
        </section>

        <section class="grid content-start gap-2">
            <h2 class="font-medium">Nächste Schießtage</h2>
            {#if nextDays.length === 0}
                <p class="rounded-lg border border-dashed p-6 text-sm text-muted-foreground">
                    Zurzeit sind keine Schießtage geplant.
                </p>
            {:else}
                <ul class="divide-y rounded-lg border">
                    {#each nextDays as day (day.id)}
                        <li>
                            <Link
                                href={toUrl(show(day.id))}
                                class="flex items-center gap-3 p-3 hover:bg-muted/40"
                            >
                                {#if day.supervised}
                                    <ShieldCheck class="size-4 shrink-0 text-emerald-600" />
                                {:else}
                                    <ShieldAlert class="size-4 shrink-0 text-red-600" />
                                {/if}
                                <span class="font-medium">{when(day.date)}</span>
                                <span class="text-sm tabular-nums text-muted-foreground"
                                    >{day.starts_at}–{day.ends_at}</span
                                >
                                <span class="ml-auto text-sm text-muted-foreground"
                                    >{day.booked} gebucht</span
                                >
                            </Link>
                        </li>
                    {/each}
                </ul>
            {/if}
        </section>
    </div>

    {#if myDuties.length > 0}
        <section class="grid gap-2">
            <h2 class="font-medium">Meine Aufsichtsdienste</h2>
            <ul class="divide-y rounded-lg border">
                {#each myDuties as duty (duty.id)}
                    <li>
                        <Link
                            href={toUrl(show(duty.shooting_day_id))}
                            class="flex items-baseline gap-3 p-3 hover:bg-muted/40"
                        >
                            <ShieldCheck class="size-4 self-center text-emerald-600" />
                            <span class="font-medium">{when(duty.date)}</span>
                            <span class="tabular-nums">{duty.starts_at}–{duty.ends_at}</span>
                            <span class="ml-auto text-sm text-muted-foreground"
                                >{duty.range ?? 'ganze Anlage'}</span
                            >
                        </Link>
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</div>
