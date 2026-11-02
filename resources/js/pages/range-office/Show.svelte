<script module lang="ts">
    import { show } from '@/routes/range-office';

    export const layout = {
        breadcrumbs: [{ title: 'Standaufsicht heute', href: show() }],
    };
</script>

<script lang="ts">
    import { page, router, useForm, usePoll } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import QrCodeIcon from '@lucide/svelte/icons/qr-code';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import QrCode from '@/components/booking/QrCode.svelte';
    import UserPlus from '@lucide/svelte/icons/user-plus';
    import UserX from '@lucide/svelte/icons/user-x';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { formatLongDate, weaponTypeLabels } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import {
        checkIn,
        noShow,
        reset,
        walkIn,
    } from '@/routes/range-office';

    type OfficeBooking = {
        id: number;
        name: string;
        member_number: string | null;
        guest: boolean;
        host: string | null;
        starts_at: string;
        ends_at: string;
        weapon_type: string;
        club_weapon: boolean;
        status: 'booked' | 'checked_in' | 'no_show';
        arrived: boolean;
        now: boolean;
        can_manage: boolean;
    };

    type OfficeRange = {
        id: number;
        name: string;
        lanes: number;
        weapon_types: string[];
        present: number;
        free_now: number | null;
        bookings: OfficeBooking[];
    };

    type Day = {
        id: number;
        date: string;
        starts_at: string;
        ends_at: string;
        title: string | null;
        guests_allowed: boolean;
        supervisors: {
            name: string;
            starts_at: string;
            ends_at: string;
            range: string | null;
        }[];
    };

    let {
        day,
        days = [],
        ranges = [],
        slots = [],
        now = '',
        members = [],
        weaponTypes = [],
        arrivalUrl = '',
    }: {
        day: Day | null;
        days?: { id: number; label: string }[];
        ranges?: OfficeRange[];
        slots?: { start: string; end: string }[];
        now?: string;
        members?: { id: number; name: string; member_number: string | null }[];
        weaponTypes?: { value: string; label: string }[];
        arrivalUrl?: string;
    } = $props();

    usePoll(15000, { only: ['ranges', 'now', 'arrivalUrl'] });

    let showQr = $state(false);

    let busy = $state<number | null>(null);
    function act(route: { url: string }, id: number) {
        busy = id;
        router.post(route.url, {}, {
            preserveScroll: true,
            only: ['ranges', 'now'],
            onFinish: () => (busy = null),
        });
    }

    // ---- Spontan eintragen ----
    let adding = $state(false);
    let memberQuery = $state('');
    const currentSlot = $derived(
        slots.find((s) => s.start <= now && now < s.end) ?? slots[0],
    );
    const walk = useForm({
        range_id: 0,
        user_id: null as number | null,
        guest_name: '',
        host_id: null as number | null,
        starts_at: '',
        ends_at: '',
        weapon_type: '',
        club_weapon: false,
        check_in: true,
    });
    let asGuest = $state(false);

    const matches = $derived.by(() => {
        const q = memberQuery.trim().toLowerCase();

        if (q.length < 2) {
            return [];
        }

        return members
            .filter(
                (m) =>
                    m.name.toLowerCase().includes(q) ||
                    (m.member_number ?? '').toLowerCase() === q,
            )
            .slice(0, 6);
    });

    const walkRange = $derived(ranges.find((r) => r.id === walk.range_id));
    const walkWeapons = $derived(
        weaponTypes.filter((t) => walkRange?.weapon_types.includes(t.value)),
    );
    const endTimes = $derived(
        slots.filter((s) => s.end > walk.starts_at).map((s) => s.end),
    );

    function openWalkIn(range?: OfficeRange) {
        adding = true;
        walk.clearErrors();
        walk.range_id = range?.id ?? ranges[0]?.id ?? 0;
        walk.starts_at = currentSlot?.start ?? '';
        walk.ends_at = currentSlot?.end ?? '';
        const types = (range ?? ranges[0])?.weapon_types ?? [];
        walk.weapon_type = types.length === 1 ? types[0] : '';
        walk.user_id = null;
        walk.guest_name = '';
        memberQuery = '';
        asGuest = false;
    }

    function pick(member: { id: number; name: string }) {
        walk.user_id = member.id;
        memberQuery = member.name;
    }

    function submitWalkIn(event: SubmitEvent) {
        event.preventDefault();

        if (!day) {
            return;
        }

        walk.transform((data) => ({
            ...data,
            user_id: asGuest ? null : data.user_id,
            guest_name: asGuest ? data.guest_name : null,
        }));
        walk.post(toUrl(walkIn(day.id)), {
            preserveScroll: true,
            onSuccess: () => (adding = false),
        });
    }

    const totalPresent = $derived(ranges.reduce((sum, r) => sum + r.present, 0));
</script>

<AppHead title="Standaufsicht" />

{#if !day}
    <div class="flex max-w-xl flex-col gap-3 p-6">
        <h1 class="text-xl font-semibold">Standaufsicht</h1>
        <p class="text-muted-foreground">
            {page.props.auth.isAdmin
                ? 'Heute findet kein Schießtag statt.'
                : 'Heute sind Sie für keinen Schießtag als Aufsicht eingetragen. Die Standansicht erscheint hier, sobald Sie an einem Schießtag Aufsicht haben.'}
        </p>
    </div>
{:else}
    <div class="flex flex-col gap-5 p-4 md:p-6">
        <header class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-2xl font-semibold tracking-tight">
                    Heute, {formatLongDate(day.date)}
                </h1>
                <p class="text-muted-foreground tabular-nums">
                    {day.starts_at}–{day.ends_at} Uhr{day.title
                        ? ` · ${day.title}`
                        : ''} · Stand {now} Uhr
                </p>
                <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    {#each day.supervisors as s (s.name + s.starts_at)}
                        <li class="inline-flex items-center gap-1.5">
                            <ShieldCheck class="size-4 text-emerald-600" />
                            {s.name}
                            <span class="text-muted-foreground tabular-nums"
                                >{s.starts_at}–{s.ends_at}{s.range
                                    ? ` · ${s.range}`
                                    : ''}</span
                            >
                        </li>
                    {/each}
                </ul>
            </div>
            <div class="flex items-center gap-3">
                {#if days.length > 1}
                    <select
                        class="h-10 rounded-md border border-input bg-background px-3 text-sm"
                        value={day.id}
                        aria-label="Schießtag wählen"
                        onchange={(e) =>
                            router.get(
                                window.location.pathname,
                                { tag: (e.currentTarget as HTMLSelectElement).value },
                                { preserveScroll: true },
                            )}
                    >
                        {#each days as d (d.id)}
                            <option value={d.id}>{d.label}</option>
                        {/each}
                    </select>
                {/if}
                <div class="rounded-lg border px-4 py-2 text-center">
                    <div class="text-2xl font-semibold tabular-nums">
                        {totalPresent}
                    </div>
                    <div class="text-xs text-muted-foreground">am Stand</div>
                </div>
                <Button
                    size="lg"
                    variant="outline"
                    onclick={() => (showQr = !showQr)}
                    aria-pressed={showQr}
                >
                    <QrCodeIcon class="size-5" />
                    QR-Code
                </Button>
                <Button size="lg" onclick={() => openWalkIn()} disabled={adding}>
                    <UserPlus class="size-5" />
                    Schütze eintragen
                </Button>
            </div>
        </header>

        {#if showQr}
            <section
                class="flex flex-col items-center gap-3 rounded-xl border-2 border-foreground/10 bg-white p-6 text-center text-black md:flex-row md:text-left"
            >
                <QrCode value={arrivalUrl} />
                <div class="max-w-sm space-y-2">
                    <h2 class="text-xl font-semibold">Am Stand anmelden</h2>
                    <p>
                        Mitglieder scannen diesen Code mit dem Handy. Sie erscheinen dann
                        unten als <strong>„ist da“</strong> – Sie bestätigen mit einem Tipp
                        auf „Anwesend“.
                    </p>
                    <p class="text-sm text-neutral-600">
                        Der Code wechselt alle 5 Minuten, damit er nur vor Ort funktioniert.
                    </p>
                </div>
            </section>
        {/if}

        {#if adding}
            <form
                class="grid gap-4 rounded-xl border-2 border-foreground/10 bg-muted/40 p-4"
                onsubmit={submitWalkIn}
            >
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="grid gap-2">
                        <div class="flex gap-2">
                            <Button
                                type="button"
                                size="sm"
                                variant={asGuest ? 'ghost' : 'secondary'}
                                onclick={() => (asGuest = false)}>Mitglied</Button
                            >
                            <Button
                                type="button"
                                size="sm"
                                variant={asGuest ? 'secondary' : 'ghost'}
                                disabled={!day.guests_allowed}
                                onclick={() => (asGuest = true)}
                                >Gast{day.guests_allowed ? '' : ' (heute nicht erlaubt)'}</Button
                            >
                        </div>
                        {#if asGuest}
                            <Input
                                bind:value={walk.guest_name}
                                placeholder="Vor- und Nachname"
                                aria-label="Name des Gastes"
                                class="h-11"
                            />
                            <select
                                bind:value={walk.host_id}
                                class="h-11 rounded-md border border-input bg-background px-3 text-sm"
                                aria-label="Eingeladen von"
                            >
                                <option value={null}>Eingeladen von … (optional)</option>
                                {#each members as m (m.id)}
                                    <option value={m.id}>{m.name}</option>
                                {/each}
                            </select>
                            <InputError message={walk.errors.guest_name} />
                        {:else}
                            <div class="relative">
                                <Input
                                    bind:value={memberQuery}
                                    oninput={() => (walk.user_id = null)}
                                    placeholder="Name oder Mitgliedsnummer"
                                    aria-label="Mitglied suchen"
                                    autocomplete="off"
                                    class="h-11"
                                />
                                {#if matches.length > 0 && walk.user_id === null}
                                    <ul
                                        class="absolute z-20 mt-1 w-full overflow-hidden rounded-md border bg-popover shadow-md"
                                    >
                                        {#each matches as m (m.id)}
                                            <li>
                                                <button
                                                    type="button"
                                                    class="flex w-full items-center justify-between px-3 py-2.5 text-left hover:bg-muted"
                                                    onclick={() => pick(m)}
                                                >
                                                    {m.name}
                                                    <span class="text-xs text-muted-foreground"
                                                        >{m.member_number ?? ''}</span
                                                    >
                                                </button>
                                            </li>
                                        {/each}
                                    </ul>
                                {/if}
                            </div>
                            <InputError message={walk.errors.user_id} />
                        {/if}
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-1.5">
                            <Label for="walk-range">Stand</Label>
                            <select
                                id="walk-range"
                                bind:value={walk.range_id}
                                class="h-11 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {#each ranges as r (r.id)}
                                    <option value={r.id}>{r.name}</option>
                                {/each}
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="walk-weapon">Waffenart</Label>
                            <select
                                id="walk-weapon"
                                bind:value={walk.weapon_type}
                                class="h-11 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                <option value="" disabled>Bitte wählen</option>
                                {#each walkWeapons as t (t.value)}
                                    <option value={t.value}>{t.label}</option>
                                {/each}
                            </select>
                            <InputError message={walk.errors.weapon_type} />
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="walk-from">Von</Label>
                            <select
                                id="walk-from"
                                bind:value={walk.starts_at}
                                class="h-11 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {#each slots as s (s.start)}
                                    <option value={s.start}>{s.start} Uhr</option>
                                {/each}
                            </select>
                        </div>
                        <div class="grid gap-1.5">
                            <Label for="walk-to">Bis</Label>
                            <select
                                id="walk-to"
                                bind:value={walk.ends_at}
                                class="h-11 rounded-md border border-input bg-background px-3 text-sm"
                            >
                                {#each endTimes as end (end)}
                                    <option value={end}>{end} Uhr</option>
                                {/each}
                            </select>
                        </div>
                    </div>
                </div>
                <div class="flex flex-wrap items-center gap-x-6 gap-y-2">
                    <Label class="flex items-center gap-3 font-normal">
                        <Checkbox bind:checked={walk.club_weapon} />
                        Vereinswaffe
                    </Label>
                    <Label class="flex items-center gap-3 font-normal">
                        <Checkbox bind:checked={walk.check_in} />
                        Sofort als anwesend bestätigen
                    </Label>
                </div>
                <InputError message={walk.errors.starts_at} />
                <div class="flex gap-2">
                    <Button type="submit" size="lg" disabled={walk.processing}>
                        {#if walk.processing}<Spinner />{/if}
                        Eintragen
                    </Button>
                    <Button
                        type="button"
                        size="lg"
                        variant="ghost"
                        onclick={() => (adding = false)}>Abbrechen</Button
                    >
                </div>
            </form>
        {/if}

        {#if ranges.length === 0}
            <p class="text-muted-foreground">
                Sie haben heute für keinen Stand Aufsicht.
            </p>
        {/if}

        <div class="grid gap-5 xl:grid-cols-2">
            {#each ranges as range (range.id)}
                <section class="flex flex-col rounded-xl border">
                    <header
                        class="flex items-center justify-between gap-3 border-b px-4 py-3"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">{range.name}</h2>
                            <p class="text-sm text-muted-foreground">
                                {range.present} am Stand{range.free_now !== null
                                    ? ` · ${range.free_now} von ${range.lanes} Bahnen frei`
                                    : ` · ${range.lanes} Bahnen`}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            onclick={() => openWalkIn(range)}
                            aria-label="Schütze auf {range.name} eintragen"
                        >
                            <UserPlus class="size-4" />
                        </Button>
                    </header>
                    {#if range.bookings.length === 0}
                        <p class="p-4 text-sm text-muted-foreground">
                            Keine Buchungen.
                        </p>
                    {:else}
                        <ul class="divide-y">
                            {#each range.bookings as b (b.id)}
                                <li
                                    class="flex flex-wrap items-center gap-3 px-4 py-3 {b.now
                                        ? 'bg-sky-50 dark:bg-sky-950/30'
                                        : ''} {b.status === 'no_show'
                                        ? 'text-muted-foreground'
                                        : ''}"
                                >
                                    <span class="w-24 font-medium tabular-nums"
                                        >{b.starts_at}–{b.ends_at}</span
                                    >
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium">
                                            {b.name}
                                            {#if b.guest}
                                                <Badge variant="secondary" class="ml-1"
                                                    >Gast</Badge
                                                >
                                            {/if}
                                        </p>
                                        <p class="truncate text-sm text-muted-foreground">
                                            {weaponTypeLabels[b.weapon_type]}{b.club_weapon
                                                ? ' · Vereinswaffe'
                                                : ''}{b.host ? ` · eingeladen von ${b.host}` : ''}{b.member_number
                                                ? ` · Nr. ${b.member_number}`
                                                : ''}
                                        </p>
                                    </div>
                                    {#if b.status === 'booked' && b.arrived}
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-sky-100 px-3 py-1 text-sm font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-300"
                                            >ist da</span
                                        >
                                    {/if}
                                    {#if b.status === 'checked_in'}
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-sm font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                        >
                                            <Check class="size-4" /> Anwesend
                                        </span>
                                    {:else if b.status === 'no_show'}
                                        <span class="text-sm">Nicht erschienen</span>
                                    {/if}
                                    {#if b.can_manage}
                                        <div class="flex gap-2">
                                            {#if b.status === 'booked'}
                                                <Button
                                                    size="lg"
                                                    class="min-w-28"
                                                    disabled={busy === b.id}
                                                    onclick={() => act(checkIn(b.id), b.id)}
                                                >
                                                    <Check class="size-5" />
                                                    Anwesend
                                                </Button>
                                                <Button
                                                    size="lg"
                                                    variant="ghost"
                                                    disabled={busy === b.id}
                                                    aria-label="{b.name} nicht erschienen"
                                                    onclick={() => act(noShow(b.id), b.id)}
                                                >
                                                    <UserX class="size-5" />
                                                </Button>
                                            {:else}
                                                <Button
                                                    size="lg"
                                                    variant="ghost"
                                                    disabled={busy === b.id}
                                                    aria-label="Für {b.name} rückgängig machen"
                                                    onclick={() => act(reset(b.id), b.id)}
                                                >
                                                    <RotateCcw class="size-5" />
                                                </Button>
                                            {/if}
                                        </div>
                                    {/if}
                                </li>
                            {/each}
                        </ul>
                    {/if}
                </section>
            {/each}
        </div>
        <p class="text-xs text-muted-foreground">
            Die Ansicht aktualisiert sich alle 15 Sekunden. Erst „Anwesend“ trägt den
            Besuch in die Schießkladde ein.
        </p>
    </div>
{/if}
