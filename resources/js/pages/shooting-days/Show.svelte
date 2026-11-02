<script module lang="ts">
    import { index } from '@/routes/shooting-days';

    export const layout = {
        breadcrumbs: [{ title: 'Schießtage', href: index() }],
    };
</script>

<script lang="ts">
    import { page, router, useForm } from '@inertiajs/svelte';
    import Info from '@lucide/svelte/icons/info';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import X from '@lucide/svelte/icons/x';
    import AppHead from '@/components/AppHead.svelte';
    import BookingGrid from '@/components/booking/BookingGrid.svelte';
    import SupervisionBadge from '@/components/booking/SupervisionBadge.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { formatLongDate, isToday, weaponTypeLabels } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { destroy as destroyBooking, store as storeBooking } from '@/routes/bookings';
    import {
        destroy as destroySupervision,
        store as storeSupervision,
    } from '@/routes/supervisions';
    import type {
        DaySummary,
        GridRange,
        GridSlot,
        MyBooking,
        SupervisionEntry,
    } from '@/types/booking';
    import { statusLabels } from '@/types/booking';

    let {
        day,
        grid,
        slotMinutes,
        supervisions,
        myBookings,
        weaponTypes,
        canSupervise,
        supervisorCandidates,
    }: {
        day: DaySummary;
        grid: GridRange[];
        slotMinutes: number;
        supervisions: SupervisionEntry[];
        myBookings: MyBooking[];
        weaponTypes: { value: string; label: string }[];
        canSupervise: boolean;
        supervisorCandidates: { id: number; name: string }[];
    } = $props();

    const isAdmin = $derived(page.props.auth.isAdmin);
    const bookable = $derived(!day.cancelled && !day.over);

    // ---- Buchung ----
    let selectedRange = $state<GridRange | null>(null);
    const booking = useForm({
        range_id: 0,
        starts_at: '',
        ends_at: '',
        weapon_type: '',
        club_weapon: false,
        guest_name: '',
    });
    let forGuest = $state(false);

    /** Mögliche Endzeiten ab dem gewählten Beginn: solange beaufsichtigt und frei. */
    const endOptions = $derived.by(() => {
        if (!selectedRange || !booking.starts_at) {
            return [] as string[];
        }

        const slots = selectedRange.slots;
        const from = slots.findIndex((s) => s.start === booking.starts_at);
        const ends: string[] = [];

        for (let i = from; i >= 0 && i < slots.length; i++) {
            if (!slots[i].supervised || slots[i].free === 0) {
                break;
            }

            ends.push(slots[i].end);
        }

        return ends;
    });

    const allowedWeapons = $derived(
        weaponTypes.filter((t) => selectedRange?.weapon_types.includes(t.value)),
    );

    function select(range: GridRange, slot: GridSlot) {
        selectedRange = range;
        booking.clearErrors();
        booking.range_id = range.id;
        booking.starts_at = slot.start;
        booking.ends_at = slot.end;

        if (!range.weapon_types.includes(booking.weapon_type)) {
            booking.weapon_type =
                range.weapon_types.length === 1 ? range.weapon_types[0] : '';
        }
    }

    function submitBooking(event: SubmitEvent) {
        event.preventDefault();
        booking.transform((data) => ({
            ...data,
            guest_name: forGuest ? data.guest_name : null,
        }));
        booking.post(toUrl(storeBooking(day.id)), {
            preserveScroll: true,
            onSuccess: () => {
                selectedRange = null;
                forGuest = false;
                booking.reset();
            },
        });
    }

    function cancelBooking(item: MyBooking) {
        router.delete(toUrl(destroyBooking(item.id)), { preserveScroll: true });
    }

    // ---- Aufsicht ----
    const times = $derived(
        grid[0]?.slots.flatMap((s, i) => (i === 0 ? [s.start, s.end] : [s.end])) ?? [
            day.starts_at,
            day.ends_at,
        ],
    );
    let supervising = $state(false);
    // svelte-ignore state_referenced_locally
    const supervision = useForm({
        user_id: '' as string | number,
        range_id: '' as string | number,
        starts_at: day.starts_at,
        ends_at: day.ends_at,
    });

    function submitSupervision(event: SubmitEvent) {
        event.preventDefault();
        supervision.transform((data) => ({
            ...data,
            user_id: data.user_id === '' ? null : data.user_id,
            range_id: data.range_id === '' ? null : data.range_id,
        }));
        supervision.post(toUrl(storeSupervision(day.id)), {
            preserveScroll: true,
            onSuccess: () => (supervising = false),
        });
    }

    function removeSupervision(entry: SupervisionEntry) {
        router.delete(toUrl(destroySupervision(entry.id)), {
            preserveScroll: true,
        });
    }

    const title = $derived(
        isToday(day.date) ? 'Heute' : formatLongDate(day.date),
    );
</script>

<AppHead title={title} />

<div class="flex max-w-5xl flex-col gap-6 p-4 md:p-6">
    <header class="space-y-2">
        <h1 class="text-2xl font-semibold tracking-tight">
            {title}
            <span
                class="block font-normal whitespace-nowrap text-muted-foreground tabular-nums sm:inline"
                >{day.starts_at}–{day.ends_at} Uhr</span
            >
        </h1>
        {#if day.title}
            <p class="text-lg">{day.title}</p>
        {/if}
        <div class="flex flex-wrap items-center gap-2">
            {#if day.cancelled}
                <Badge variant="destructive"
                    >Abgesagt{day.cancel_reason ? `: ${day.cancel_reason}` : ''}</Badge
                >
            {:else}
                <SupervisionBadge state={day.supervision} names={day.supervisors} />
            {/if}
            {#each day.groups as group (group)}
                <Badge variant="secondary">nur {group}</Badge>
            {/each}
            {#if !day.guests_allowed}
                <Badge variant="outline">ohne Gäste</Badge>
            {/if}
        </div>
        {#if day.notes}
            <p
                class="flex gap-2 rounded-lg border bg-muted/40 p-3 text-sm whitespace-pre-line"
            >
                <Info class="mt-0.5 size-4 shrink-0 text-muted-foreground" />
                {day.notes}
            </p>
        {/if}
    </header>

    {#if myBookings.length > 0}
        <section class="grid gap-2" aria-labelledby="my-bookings">
            <h2 id="my-bookings" class="text-sm font-medium text-muted-foreground">
                Ihre Buchungen an diesem Tag
            </h2>
            <ul class="divide-y rounded-lg border">
                {#each myBookings as item (item.id)}
                    <li class="flex flex-wrap items-center gap-3 p-3">
                        <span class="font-medium tabular-nums"
                            >{item.starts_at}–{item.ends_at}</span
                        >
                        <span>{item.range}</span>
                        <span class="text-sm text-muted-foreground"
                            >{weaponTypeLabels[item.weapon_type]}{item.club_weapon
                                ? ' · Vereinswaffe'
                                : ''}</span
                        >
                        {#if item.guest_name}
                            <Badge variant="secondary">Gast: {item.guest_name}</Badge>
                        {/if}
                        {#if item.status !== 'booked'}
                            <Badge variant="outline">{statusLabels[item.status]}</Badge>
                        {/if}
                        {#if item.can_cancel}
                            <Button
                                variant="ghost"
                                size="sm"
                                class="ml-auto"
                                onclick={() => cancelBooking(item)}>Stornieren</Button
                            >
                        {/if}
                    </li>
                {/each}
            </ul>
        </section>
    {/if}

    {#if bookable}
        <section class="grid gap-3" aria-labelledby="book">
            <div>
                <h2 id="book" class="text-lg font-semibold">Bahn buchen</h2>
                <p class="text-sm text-muted-foreground">
                    Tippen Sie auf eine Zeit. Die Zahl zeigt die freien Bahnen.
                </p>
            </div>

            {#if day.supervision === 'none'}
                <p
                    class="rounded-lg border border-red-600/30 bg-red-50 p-3 text-sm dark:bg-red-950/30"
                >
                    Buchen ist erst möglich, wenn eine Standaufsicht eingetragen
                    ist. Ohne Aufsicht darf nicht geschossen werden (§ 27 WaffG).
                    {#if canSupervise}
                        Sie können die Aufsicht unten übernehmen.
                    {/if}
                </p>
            {/if}

            <BookingGrid
                {grid}
                selected={selectedRange
                    ? { rangeId: selectedRange.id, start: booking.starts_at }
                    : null}
                onselect={select}
            />

            {#if selectedRange}
                <form
                    class="grid gap-4 rounded-lg border bg-muted/30 p-4"
                    onsubmit={submitBooking}
                >
                    <div class="flex items-start justify-between gap-4">
                        <p class="font-medium">
                            {selectedRange.name}, ab {booking.starts_at} Uhr
                        </p>
                        <Button
                            variant="ghost"
                            size="icon"
                            type="button"
                            aria-label="Auswahl aufheben"
                            onclick={() => (selectedRange = null)}
                        >
                            <X class="size-4" />
                        </Button>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="ends_at">Bis</Label>
                            <select
                                id="ends_at"
                                bind:value={booking.ends_at}
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                            >
                                {#each endOptions as end (end)}
                                    <option value={end}>{end} Uhr</option>
                                {/each}
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="weapon_type">Waffenart</Label>
                            <select
                                id="weapon_type"
                                bind:value={booking.weapon_type}
                                required
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                            >
                                <option value="" disabled>Bitte wählen</option>
                                {#each allowedWeapons as type (type.value)}
                                    <option value={type.value}>{type.label}</option>
                                {/each}
                            </select>
                            <InputError message={booking.errors.weapon_type} />
                        </div>
                    </div>
                    <Label class="flex items-center gap-3 font-normal">
                        <Checkbox bind:checked={booking.club_weapon} />
                        Ich nutze eine Vereinswaffe
                    </Label>
                    {#if day.guests_allowed}
                        <div class="grid gap-2">
                            <Label class="flex items-center gap-3 font-normal">
                                <Checkbox bind:checked={forGuest} />
                                Für einen Gast buchen
                            </Label>
                            {#if forGuest}
                                <Input
                                    bind:value={booking.guest_name}
                                    placeholder="Vor- und Nachname des Gastes"
                                    aria-label="Name des Gastes"
                                />
                                <p class="text-xs text-muted-foreground">
                                    Der Gast schießt unter Ihrer Verantwortung und
                                    muss sich bei der Standaufsicht ausweisen.
                                </p>
                                <InputError message={booking.errors.guest_name} />
                            {/if}
                        </div>
                    {/if}
                    <InputError message={booking.errors.starts_at} />
                    <InputError message={booking.errors.range_id} />
                    <div>
                        <Button
                            type="submit"
                            disabled={booking.processing || !booking.weapon_type}
                        >
                            {#if booking.processing}<Spinner />{/if}
                            {booking.starts_at}–{booking.ends_at} Uhr buchen
                        </Button>
                    </div>
                </form>
            {/if}
        </section>
    {/if}

    <section class="grid gap-3" aria-labelledby="supervision">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 id="supervision" class="text-lg font-semibold">Standaufsicht</h2>
                <p class="text-sm text-muted-foreground">
                    Verantwortliche Aufsichtspersonen nach § 27 WaffG.
                </p>
            </div>
            {#if bookable && (canSupervise || isAdmin) && !supervising}
                <Button variant="outline" onclick={() => (supervising = true)}>
                    <ShieldCheck class="size-4" />
                    {isAdmin && !canSupervise
                        ? 'Aufsicht einteilen'
                        : 'Aufsicht übernehmen'}
                </Button>
            {/if}
        </div>

        {#if supervisions.length === 0}
            <p class="text-sm text-muted-foreground">Noch niemand eingetragen.</p>
        {:else}
            <ul class="divide-y rounded-lg border">
                {#each supervisions as entry (entry.id)}
                    <li class="flex flex-wrap items-center gap-3 p-3">
                        <ShieldCheck class="size-4 text-emerald-600" />
                        <span class="font-medium">{entry.name}</span>
                        <span class="text-sm text-muted-foreground tabular-nums"
                            >{entry.starts_at}–{entry.ends_at} Uhr · {entry.range ??
                                'ganze Anlage'}</span
                        >
                        {#if entry.can_remove && bookable}
                            <Button
                                variant="ghost"
                                size="sm"
                                class="ml-auto"
                                onclick={() => removeSupervision(entry)}
                                >{entry.mine ? 'Austragen' : 'Entfernen'}</Button
                            >
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}

        {#if supervising}
            <form
                class="grid gap-4 rounded-lg border bg-muted/30 p-4"
                onsubmit={submitSupervision}
            >
                <div class="grid gap-4 sm:grid-cols-2">
                    {#if isAdmin}
                        <div class="grid gap-2">
                            <Label for="sv-user">Aufsicht</Label>
                            <select
                                id="sv-user"
                                bind:value={supervision.user_id}
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                            >
                                {#if canSupervise}
                                    <option value="">Ich selbst</option>
                                {:else}
                                    <option value="" disabled>Bitte wählen</option>
                                {/if}
                                {#each supervisorCandidates.filter((c) => c.id !== page.props.auth.user.id) as candidate (candidate.id)}
                                    <option value={candidate.id}>{candidate.name}</option>
                                {/each}
                            </select>
                            {#if supervisorCandidates.length === 0}
                                <p class="text-xs text-muted-foreground">
                                    Niemand hat eine gültige Aufsichts-Qualifikation.
                                    Tragen Sie sie unter Verwaltung → Mitglieder ein.
                                </p>
                            {/if}
                            <InputError message={supervision.errors.user_id} />
                        </div>
                    {/if}
                    <div class="grid gap-2">
                        <Label for="sv-range">Bereich</Label>
                        <select
                            id="sv-range"
                            bind:value={supervision.range_id}
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                        >
                            <option value="">Ganze Anlage</option>
                            {#each grid as range (range.id)}
                                <option value={range.id}>{range.name}</option>
                            {/each}
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sv-from">Von</Label>
                        <select
                            id="sv-from"
                            bind:value={supervision.starts_at}
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                        >
                            {#each times.slice(0, -1) as time (time)}
                                <option value={time}>{time} Uhr</option>
                            {/each}
                        </select>
                    </div>
                    <div class="grid gap-2">
                        <Label for="sv-to">Bis</Label>
                        <select
                            id="sv-to"
                            bind:value={supervision.ends_at}
                            class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                        >
                            {#each times.filter((t) => t > supervision.starts_at) as time (time)}
                                <option value={time}>{time} Uhr</option>
                            {/each}
                        </select>
                    </div>
                </div>
                <InputError message={supervision.errors.starts_at} />
                <InputError message={supervision.errors.range_id} />
                <div class="flex gap-2">
                    <Button type="submit" disabled={supervision.processing}>
                        {#if supervision.processing}<Spinner />{/if}
                        Eintragen
                    </Button>
                    <Button
                        type="button"
                        variant="ghost"
                        onclick={() => (supervising = false)}>Abbrechen</Button
                    >
                </div>
            </form>
        {/if}
    </section>
    <p class="text-xs text-muted-foreground">Buchungsraster: {slotMinutes} Minuten</p>
</div>
