<script module lang="ts">
    import { show } from '@/routes/range-office';

    export const layout = {
        breadcrumbs: [{ title: 'Am Stand', href: show() }],
    };
</script>

<script lang="ts">
    import { router, usePoll } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import NotebookPen from '@lucide/svelte/icons/notebook-pen';
    import QrCodeIcon from '@lucide/svelte/icons/qr-code';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import UserX from '@lucide/svelte/icons/user-x';
    import AppHead from '@/components/AppHead.svelte';
    import QrCode from '@/components/booking/QrCode.svelte';
    import EntryForm from '@/components/range-office/EntryForm.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { formatLongDate, weaponTypeLabels } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { cancelEntry, checkIn, noShow, reset } from '@/routes/range-office';
    import type { EntryMember, Option, TodayEntry } from '@/types/range-office';

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
        supervisors: { name: string; starts_at: string; ends_at: string; range: string | null }[];
    };

    let {
        day,
        days = [],
        ranges = [],
        now = '',
        arrivalUrl = '',
        canEnter,
        canEnterLicensed,
        members,
        weaponClasses,
        disciplines,
        todayEntries,
    }: {
        day: Day | null;
        days?: { id: number; label: string }[];
        ranges?: OfficeRange[];
        now?: string;
        arrivalUrl?: string;
        canEnter: boolean;
        canEnterLicensed: boolean;
        members: EntryMember[];
        weaponClasses: Option[];
        disciplines: Option[];
        todayEntries: TodayEntry[];
    } = $props();

    usePoll(30000, { only: ['ranges', 'now', 'arrivalUrl', 'todayEntries'] });

    let entering = $state(false);
    let showQr = $state(false);
    let busy = $state<number | null>(null);

    // Check-in braucht ggf. Disziplin bzw. bei Gästen die Bestätigung „volljährig“.
    let confirming = $state<OfficeBooking | null>(null);
    let checkInDiscipline = $state('');
    let checkInAdult = $state(false);

    function startCheckIn(b: OfficeBooking) {
        if (b.guest || (!b.guest && disciplines.length > 0)) {
            confirming = b;
            checkInDiscipline = '';
            checkInAdult = false;

            return;
        }

        sendCheckIn(b);
    }

    function sendCheckIn(b: OfficeBooking) {
        busy = b.id;
        router.post(
            toUrl(checkIn(b.id)),
            {
                discipline: checkInDiscipline || null,
                guest_adult: checkInAdult,
            },
            {
                preserveScroll: true,
                onFinish: () => {
                    busy = null;
                    confirming = null;
                },
            },
        );
    }

    function act(route: { url: string }, id: number) {
        busy = id;
        router.post(route.url, {}, { preserveScroll: true, onFinish: () => (busy = null) });
    }

    // Storno mit Grund
    let cancelling = $state<number | null>(null);
    let cancelReason = $state('Fehleingabe');

    function sendCancel(entry: TodayEntry) {
        router.post(
            toUrl(cancelEntry(entry.id)),
            { reason: cancelReason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    cancelling = null;
                    cancelReason = 'Fehleingabe';
                },
            },
        );
    }

    const activeToday = $derived(todayEntries.filter((e) => e.active).length);

    function todayIso(): string {
        const d = new Date();

        return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
    }
</script>

<AppHead title="Am Stand" />

<div class="flex flex-col gap-5 p-4 md:p-6">
    <header class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold tracking-tight">
                Heute, {formatLongDate(day?.date ?? todayIso())}
            </h1>
            {#if day}
                <p class="text-muted-foreground tabular-nums">
                    {day.starts_at}–{day.ends_at} Uhr{day.title ? ` · ${day.title}` : ''} · Stand {now} Uhr
                </p>
                <ul class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                    {#each day.supervisors as s (s.name + s.starts_at)}
                        <li class="inline-flex items-center gap-1.5">
                            <ShieldCheck class="size-4 text-emerald-600" />
                            {s.name}
                            <span class="text-muted-foreground tabular-nums"
                                >{s.starts_at}–{s.ends_at}{s.range ? ` · ${s.range}` : ''}</span
                            >
                        </li>
                    {/each}
                </ul>
            {/if}
        </div>
        <div class="flex flex-wrap items-center gap-3">
            {#if days.length > 1 && day}
                <select
                    class="h-12 rounded-md border border-input bg-background px-3 text-sm"
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
                <div class="text-2xl font-semibold tabular-nums">{activeToday}</div>
                <div class="text-xs text-muted-foreground">heute eingetragen</div>
            </div>
            {#if day && arrivalUrl}
                <Button
                    size="lg"
                    variant="outline"
                    class="h-12"
                    onclick={() => (showQr = !showQr)}
                    aria-pressed={showQr}
                >
                    <QrCodeIcon class="size-5" />
                    QR-Code
                </Button>
            {/if}
            {#if canEnter}
                <Button size="lg" class="h-12 px-6 text-base" onclick={() => (entering = true)} disabled={entering}>
                    <NotebookPen class="size-5" />
                    Eintragen
                </Button>
            {/if}
        </div>
    </header>

    {#if !canEnter}
        <p
            class="flex gap-2 rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-sm dark:bg-amber-950/30"
        >
            <ShieldAlert class="mt-0.5 size-4 shrink-0" />
            Eintragen dürfen nur Personen mit gültiger Zeile im Aufsichtsregister (§ 10 Abs. 3
            AWaffV). Nachträge macht der Vorstand in der Kladde des Vereins.
        </p>
    {/if}

    {#if entering}
        <EntryForm
            {members}
            {weaponClasses}
            {disciplines}
            {canEnterLicensed}
            onclose={() => (entering = false)}
        />
    {/if}

    {#if showQr}
        <section
            class="flex flex-col items-center gap-3 rounded-xl border-2 border-foreground/10 bg-white p-6 text-center text-black md:flex-row md:text-left"
        >
            <QrCode value={arrivalUrl} />
            <div class="max-w-sm space-y-2">
                <h2 class="text-xl font-semibold">Am Stand melden</h2>
                <p>
                    Mitglieder mit Buchung scannen diesen Code. Sie erscheinen unten als
                    <strong>„ist da“</strong>; eingetragen sind sie erst, wenn Sie auf „Anwesend“ tippen.
                </p>
                <p class="text-sm text-neutral-600">Der Code wechselt alle 5 Minuten.</p>
            </div>
        </section>
    {/if}

    {#if confirming}
        <section class="grid gap-3 rounded-xl border-2 border-foreground/15 p-4" aria-live="polite">
            <p class="font-medium">{confirming.name}: Anwesenheit eintragen</p>
            {#if confirming.guest}
                <Label class="flex min-h-11 items-center gap-3 text-base font-normal">
                    <Checkbox bind:checked={checkInAdult} class="size-6" />
                    Gast ist volljährig (Ausweis gesehen)
                </Label>
            {:else if disciplines.length > 0}
                <select
                    bind:value={checkInDiscipline}
                    class="h-14 rounded-lg border border-input bg-background px-3 text-base"
                    aria-label="Disziplin"
                >
                    <option value="" disabled>Disziplin wählen</option>
                    {#each disciplines as d (d.value)}
                        <option value={d.value}>{d.label}</option>
                    {/each}
                </select>
            {/if}
            <div class="flex gap-2">
                <Button
                    class="h-14 flex-1 text-lg"
                    disabled={busy === confirming.id ||
                        (confirming.guest ? !checkInAdult : !checkInDiscipline)}
                    onclick={() => confirming && sendCheckIn(confirming)}
                >
                    <Check class="size-5" /> Eintragen
                </Button>
                <Button variant="ghost" class="h-14" onclick={() => (confirming = null)}>Abbrechen</Button>
            </div>
        </section>
    {/if}

    {#if day}
        <div class="grid gap-5 xl:grid-cols-2">
            {#each ranges as range (range.id)}
                <section class="flex flex-col rounded-xl border">
                    <header class="border-b px-4 py-3">
                        <h2 class="text-lg font-semibold">{range.name}</h2>
                        <p class="text-sm text-muted-foreground">
                            Gebucht{range.free_now !== null
                                ? ` · ${range.free_now} von ${range.lanes} Bahnen frei`
                                : ` · ${range.lanes} Bahnen`}
                        </p>
                    </header>
                    {#if range.bookings.length === 0}
                        <p class="p-4 text-sm text-muted-foreground">Keine Buchungen.</p>
                    {:else}
                        <ul class="divide-y">
                            {#each range.bookings as b (b.id)}
                                <li
                                    class="flex flex-wrap items-center gap-3 px-4 py-3 {b.now
                                        ? 'bg-sky-50 dark:bg-sky-950/30'
                                        : ''} {b.status === 'no_show' ? 'text-muted-foreground' : ''}"
                                >
                                    <span class="w-24 font-medium tabular-nums">{b.starts_at}–{b.ends_at}</span>
                                    <div class="min-w-0 flex-1">
                                        <p class="truncate font-medium">
                                            {b.name}
                                            {#if b.guest}<Badge variant="secondary" class="ml-1">Gast</Badge>{/if}
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
                                            class="rounded-full bg-sky-100 px-3 py-1 text-sm font-medium text-sky-800 dark:bg-sky-950 dark:text-sky-300"
                                            >ist da</span
                                        >
                                    {/if}
                                    {#if b.status === 'checked_in'}
                                        <span
                                            class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-sm font-medium text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300"
                                            ><Check class="size-4" /> eingetragen</span
                                        >
                                    {:else if b.status === 'no_show'}
                                        <span class="text-sm">nicht erschienen</span>
                                    {/if}
                                    {#if b.can_manage}
                                        <div class="flex gap-2">
                                            {#if b.status === 'booked'}
                                                <Button
                                                    size="lg"
                                                    class="h-12 min-w-32"
                                                    disabled={busy === b.id || !canEnter}
                                                    onclick={() => startCheckIn(b)}
                                                >
                                                    <Check class="size-5" /> Anwesend
                                                </Button>
                                                <Button
                                                    size="lg"
                                                    variant="ghost"
                                                    class="h-12"
                                                    disabled={busy === b.id}
                                                    aria-label="{b.name} nicht erschienen"
                                                    onclick={() => act(noShow(b.id), b.id)}
                                                >
                                                    <UserX class="size-5" />
                                                </Button>
                                            {:else if b.status === 'no_show'}
                                                <Button
                                                    size="lg"
                                                    variant="ghost"
                                                    class="h-12"
                                                    disabled={busy === b.id}
                                                    aria-label="„nicht erschienen“ für {b.name} zurücknehmen"
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
    {/if}

    <section class="grid gap-2" aria-labelledby="today">
        <h2 id="today" class="text-lg font-semibold">Heute eingetragen</h2>
        {#if todayEntries.length === 0}
            <p class="text-sm text-muted-foreground">Noch keine Einträge.</p>
        {:else}
            <ul class="divide-y rounded-xl border">
                {#each todayEntries as e (e.id)}
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 {e.active ? '' : 'text-muted-foreground'}">
                        <span class="w-14 tabular-nums">{e.time}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium {e.active ? '' : 'line-through'}">
                                {e.name}
                                {#if e.guest}<Badge variant="secondary" class="ml-1">Gast</Badge>{/if}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {e.weapon_class}{e.weapon_owner ? ` · ${e.weapon_owner}` : ''}{e.discipline
                                    ? ` · ${e.discipline}`
                                    : ''} · von {e.recorded_by}{e.active ? '' : ` · storniert: ${e.cancel_reason}`}
                            </p>
                        </div>
                        {#if e.can_cancel}
                            {#if cancelling === e.id}
                                <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                                    <Input
                                        bind:value={cancelReason}
                                        aria-label="Grund für das Storno"
                                        class="h-12 sm:w-56"
                                    />
                                    <Button
                                        variant="destructive"
                                        class="h-12"
                                        disabled={!cancelReason.trim()}
                                        onclick={() => sendCancel(e)}>Stornieren</Button
                                    >
                                    <Button variant="ghost" class="h-12" onclick={() => (cancelling = null)}
                                        >Abbrechen</Button
                                    >
                                </div>
                            {:else}
                                <Button variant="ghost" class="h-12" onclick={() => (cancelling = e.id)}
                                    >Storno</Button
                                >
                            {/if}
                        {/if}
                    </li>
                {/each}
            </ul>
        {/if}
        <p class="text-xs text-muted-foreground">
            Einträge werden nicht bearbeitet: Ein Fehler wird mit Grund storniert und neu
            eingetragen. Ab morgen kann nur noch der Vorstand stornieren.
        </p>
    </section>
</div>
