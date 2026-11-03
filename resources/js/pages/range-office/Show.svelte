<script module lang="ts">
    import { show } from '@/routes/range-office';

    export const layout = {
        breadcrumbs: [{ title: 'Am Stand', href: show() }],
    };
</script>

<script lang="ts">
    import { router, usePoll } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import MonitorSmartphone from '@lucide/svelte/icons/monitor-smartphone';
    import NotebookPen from '@lucide/svelte/icons/notebook-pen';
    import Play from '@lucide/svelte/icons/play';
    import QrCodeIcon from '@lucide/svelte/icons/qr-code';
    import RotateCcw from '@lucide/svelte/icons/rotate-ccw';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import Signature from '@lucide/svelte/icons/signature';
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
    import { checkIn, noShow, reset } from '@/routes/range-office';
    import {
        kiosk as kioskRoute,
        kioskHere as kioskHereRoute,
        member as addMemberRoute,
        reject as rejectRoute,
        remove as removeRoute,
        signOff as signOffRoute,
        start as startRoute,
        withdraw as withdrawRoute,
    } from '@/routes/range-office/session';
    import type {
        EntryMember,
        OpenSession,
        Option,
        Participant,
        SessionEntryRow,
        SignedSession,
    } from '@/types/range-office';

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
        in_session: boolean;
        adult_confirmed: boolean;
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
        session,
        signedSessions = [],
        rejectReasons = [],
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
        session: OpenSession | null;
        signedSessions?: SignedSession[];
        rejectReasons?: string[];
    } = $props();

    usePoll(30000, { only: ['ranges', 'now', 'arrivalUrl', 'session', 'signedSessions'] });

    let entering = $state(false);
    let enteringFor = $state<Participant | null>(null);
    let showQr = $state(false);
    let busy = $state<number | null>(null);

    // Aktivieren braucht bei Gästen „volljährig“ und bei Mitgliedern ohne Haken einmalig „volljährig geprüft“.
    let confirming = $state<OfficeBooking | null>(null);
    let checkInAdult = $state(false);

    function startCheckIn(b: OfficeBooking) {
        if (b.guest || !b.adult_confirmed) {
            confirming = b;
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
                guest_adult: b.guest && checkInAdult,
                adult_confirmed: !b.guest && checkInAdult,
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

    function post(url: string, data: Record<string, unknown> = {}, onSuccess?: () => void) {
        router.post(url, data as never, { preserveScroll: true, onSuccess: () => onSuccess?.() });
    }

    // Kiosk: Link für ein anderes Gerät oder dieses Gerät abmelden und Kiosk öffnen.
    // Das Token kommt nur einmal als Flash; die nächste Abfrage (Polling) bringt es nicht wieder.
    let showKiosk = $state(false);
    let kioskUrl = $state<string | null>(null);

    function issueKioskLink() {
        if (!session) {
            return;
        }

        router.post(
            toUrl(kioskRoute(session.id)),
            {},
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    kioskUrl = (page as { flash?: { kioskUrl?: string } }).flash?.kioskUrl ?? null;
                },
            },
        );
    }

    function kioskHere() {
        if (
            session &&
            confirm('Dieses Gerät wird abgemeldet und zeigt danach den Kiosk. Zurück zur Aufsicht über die normale Anmeldung.')
        ) {
            router.post(toUrl(kioskHereRoute(session.id)));
        }
    }

    // Ablehnen mit Grund (Schnellgründe oder eigener Text)
    let rejecting = $state<number | null>(null);
    let rejectReason = $state('');

    function sendReject(entry: SessionEntryRow) {
        post(toUrl(rejectRoute(entry.id)), { reason: rejectReason }, () => {
            rejecting = null;
            rejectReason = '';
        });
    }

    // Mitglied ohne Eintrag aktivieren (Suche)
    let addQuery = $state('');
    let addAdult = $state(false);
    const addMatches = $derived.by(() => {
        const q = addQuery.trim().toLowerCase();

        if (q.length === 0) {
            return [];
        }

        return members
            .filter((m) => m.name.toLowerCase().includes(q) || (m.member_number ?? '').toLowerCase().startsWith(q))
            .slice(0, 6);
    });

    function addMember(m: EntryMember) {
        if (!session) {
            return;
        }

        post(toUrl(addMemberRoute(session.id)), { user_id: m.id, adult_confirmed: addAdult }, () => {
            addQuery = '';
            addAdult = false;
        });
    }

    // Abzeichnen: Der Dialog friert die angezeigten Einträge ein; der Server vergleicht die IDs.
    type SignOffSnapshot = {
        time: string;
        rows: { participant: string; entry: SessionEntryRow }[];
        withoutEntries: string[];
    };
    let signing = $state<SignOffSnapshot | null>(null);

    function nowHHMM(): string {
        const d = new Date();

        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }

    function openSignOff() {
        if (!session) {
            return;
        }

        signOffError = null;

        signing = {
            time: nowHHMM(),
            rows: session.participants.flatMap((p) =>
                p.entries.filter((e) => e.status === 'pending').map((e) => ({ participant: p.name, entry: e })),
            ),
            withoutEntries: session.participants
                .filter((p) => !p.entries.some((e) => e.status === 'pending'))
                .map((p) => p.name),
        };
    }

    function sendSignOff() {
        if (!session || !signing) {
            return;
        }

        signOffError = null;
        router.post(
            toUrl(signOffRoute(session.id)),
            { entry_ids: signing.rows.map((r) => r.entry.id) },
            {
                preserveScroll: true,
                onSuccess: () => (signing = null),
                onError: (errors) => (signOffError = errors.entry_ids ?? errors.session ?? Object.values(errors)[0] ?? null),
            },
        );
    }

    let signOffError = $state<string | null>(null);

    const pendingCount = $derived(
        session ? session.participants.reduce((n, p) => n + p.entries.filter((e) => e.status === 'pending').length, 0) : 0,
    );

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
            {#if session}
                <div class="rounded-lg border px-4 py-2 text-center">
                    <div class="text-2xl font-semibold tabular-nums">{pendingCount}</div>
                    <div class="text-xs text-muted-foreground">offene Einträge</div>
                </div>
            {/if}
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
            {#if canEnter && session}
                <Button
                    size="lg"
                    class="h-12 px-6 text-base"
                    onclick={() => {
                        enteringFor = null;
                        entering = true;
                    }}
                    disabled={entering}
                >
                    <NotebookPen class="size-5" />
                    Eintragen
                </Button>
                <Button
                    size="lg"
                    variant="outline"
                    class="h-12"
                    onclick={() => (showKiosk = !showKiosk)}
                    aria-pressed={showKiosk}
                >
                    <MonitorSmartphone class="size-5" />
                    Kiosk
                </Button>
                <Button size="lg" variant="outline" class="h-12 px-6 text-base" onclick={openSignOff}>
                    <Signature class="size-5" />
                    Abzeichnen
                </Button>
            {:else if canEnter}
                <Button size="lg" class="h-12 px-6 text-base" onclick={() => post(toUrl(startRoute()))}>
                    <Play class="size-5" />
                    Session starten
                </Button>
            {/if}
        </div>
    </header>

    {#if !canEnter}
        <p
            class="flex gap-2 rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-sm dark:bg-amber-950/30"
        >
            <ShieldAlert class="mt-0.5 size-4 shrink-0" />
            Eine Session starten dürfen nur Personen mit gültiger Zeile im Aufsichtsregister (§ 10 Abs. 3
            AWaffV). Nachträge macht der Vorstand in der Kladde des Vereins.
        </p>
    {:else if !session}
        <p class="rounded-lg border p-4 text-sm text-muted-foreground">
            Starten Sie Ihre Session, aktivieren Sie die Schützen vor Ort und zeichnen Sie am Ende ab.
            In die Kladde kommt erst, was Sie abgezeichnet haben.
        </p>
    {:else}
        <p class="flex gap-2 rounded-lg border border-sky-500/40 bg-sky-50 p-4 text-sm dark:bg-sky-950/30">
            <ShieldCheck class="mt-0.5 size-4 shrink-0" />
            Session seit {session.started_at} Uhr. Vor dem Verlassen des Stands abzeichnen; nicht abgezeichnete
            Einträge verfallen um Mitternacht ohne Nachweis.
        </p>
    {/if}

    {#if entering && session}
        {#key enteringFor?.id ?? 0}
            <EntryForm
                {members}
                {weaponClasses}
                {disciplines}
                {canEnterLicensed}
                participant={enteringFor}
                onclose={() => {
                    entering = false;
                    enteringFor = null;
                }}
            />
        {/key}
    {/if}

    {#if signing}
        <section class="grid gap-3 rounded-xl border-2 border-foreground/30 p-4" aria-labelledby="sign-off-title">
            <h2 id="sign-off-title" class="text-lg font-semibold">Session abzeichnen</h2>
            {#if signing.rows.length === 0}
                <p class="text-sm text-muted-foreground">Keine offenen Einträge.</p>
            {:else}
                <ul class="divide-y rounded-lg border text-sm">
                    {#each signing.rows as r (r.entry.id)}
                        <li class="flex flex-wrap gap-x-3 px-3 py-2">
                            <span class="font-medium">{r.participant}</span>
                            <span class="text-muted-foreground"
                                >{r.entry.weapon_class}{r.entry.weapon_owner ? ` · ${r.entry.weapon_owner}` : ''}{r.entry
                                    .discipline
                                    ? ` · ${r.entry.discipline}`
                                    : ''} · {r.entry.shots ?? '–'} Schüsse · erfasst um {r.entry.time} über {r.entry
                                    .via}</span
                            >
                        </li>
                    {/each}
                </ul>
            {/if}
            {#if signing.withoutEntries.length > 0}
                <p class="text-sm">Ohne offenen Eintrag: {signing.withoutEntries.join(', ')}</p>
            {/if}
            <p class="font-medium">
                Die aufgeführten Personen haben heute bis {signing.time} Uhr unter meiner Aufsicht geschossen.
            </p>
            {#if signOffError}
                <p class="rounded-lg border border-destructive/40 p-3 text-sm text-destructive" role="alert">
                    {signOffError}
                    <Button variant="outline" class="ml-2 h-11" onclick={openSignOff}>Neu prüfen</Button>
                </p>
            {/if}
            <div class="flex gap-2">
                <Button class="h-14 flex-1 text-lg" onclick={sendSignOff}>
                    <Signature class="size-5" /> Bestätigen und abzeichnen
                </Button>
                <Button variant="ghost" class="h-14" onclick={() => (signing = null)}>Abbrechen</Button>
            </div>
        </section>
    {/if}

    {#if showKiosk && session}
        <section class="grid gap-4 rounded-xl border-2 border-foreground/15 p-4" aria-labelledby="kiosk-title">
            <div>
                <h2 id="kiosk-title" class="text-lg font-semibold">Kiosk</h2>
                <p class="text-sm text-muted-foreground">
                    Am Kiosk tragen sich Mitglieder Ihrer Session mit Namen und eigener PIN ein, ohne Anmeldung.
                    Gäste und Mitglieder ohne PIN erfassen Sie selbst. Der Kiosk endet mit dem Abzeichnen.
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                <Button variant="outline" class="h-12" onclick={issueKioskLink}>
                    <QrCodeIcon class="size-5" />
                    {kioskUrl ? 'Neuen Link erzeugen' : 'Link für ein anderes Gerät'}
                </Button>
                <Button variant="outline" class="h-12" onclick={kioskHere}>
                    <MonitorSmartphone class="size-5" />
                    Kiosk auf diesem Gerät
                </Button>
            </div>
            <p class="text-xs text-muted-foreground">
                Ein neuer Link ersetzt den alten; ein Gerät mit dem alten Link zeigt danach nichts mehr an.
            </p>
            {#if kioskUrl}
                <div
                    class="flex flex-col items-center gap-3 rounded-lg bg-white p-4 text-center text-black md:flex-row md:text-left"
                >
                    <QrCode value={kioskUrl} />
                    <div class="grid min-w-0 gap-2">
                        <p>Mit dem Tablet scannen oder den Link dort öffnen:</p>
                        <code class="break-all text-xs" data-test="kiosk-url">{kioskUrl}</code>
                    </div>
                </div>
            {/if}
        </section>
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
                    <strong>„ist da“</strong>; teilnehmen können sie erst, wenn Sie sie vor Ort aktivieren.
                </p>
                <p class="text-sm text-neutral-600">Der Code wechselt alle 5 Minuten.</p>
            </div>
        </section>
    {/if}

    {#if confirming}
        <section class="grid gap-3 rounded-xl border-2 border-foreground/15 p-4" aria-live="polite">
            <p class="font-medium">{confirming.name} aktivieren</p>
            <Label class="flex min-h-11 items-center gap-3 text-base font-normal">
                <Checkbox bind:checked={checkInAdult} class="size-6" />
                {confirming.guest ? 'Gast ist volljährig (Ausweis gesehen)' : 'volljährig geprüft (einmalig, Ausweis gesehen)'}
            </Label>
            <div class="flex gap-2">
                <Button
                    class="h-14 flex-1 text-lg"
                    disabled={busy === confirming.id || !checkInAdult}
                    onclick={() => confirming && sendCheckIn(confirming)}
                >
                    <Check class="size-5" /> Aktivieren
                </Button>
                <Button variant="ghost" class="h-14" onclick={() => (confirming = null)}>Abbrechen</Button>
            </div>
        </section>
    {/if}

    {#if day}
        {#if ranges.length === 0 && canEnter && !canEnterLicensed}
            <p class="rounded-lg border p-4 text-sm text-muted-foreground">
                Ihre Aufsicht heute gilt nur für Stände mit Kurz- oder Langwaffe. Dafür braucht es den
                Umfang erlaubnispflichtig im Aufsichtsregister; Sie sind nur für erlaubnisfreie Waffen
                registriert. Die Einteilung ändert der Vorstand.
            </p>
        {/if}
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
                                            ><Check class="size-4" /> aktiviert</span
                                        >
                                    {:else if b.status === 'no_show'}
                                        <span class="text-sm">nicht erschienen</span>
                                    {/if}
                                    {#if b.can_manage}
                                        <div class="flex gap-2">
                                            {#if session && !b.in_session}
                                                <Button
                                                    size="lg"
                                                    class="h-12 min-w-32"
                                                    disabled={busy === b.id || !canEnter}
                                                    onclick={() => startCheckIn(b)}
                                                >
                                                    <Check class="size-5" /> Aktivieren
                                                </Button>
                                            {/if}
                                            {#if b.status === 'booked'}
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

    {#if session}
        <section class="grid gap-3" aria-labelledby="participants">
            <h2 id="participants" class="text-lg font-semibold">Teilnehmer dieser Session</h2>
            <div class="relative max-w-xl">
                <Input
                    bind:value={addQuery}
                    placeholder="Mitglied ohne Buchung aktivieren: Name oder Nummer"
                    aria-label="Mitglied aktivieren"
                    autocomplete="off"
                    class="h-12"
                />
                {#if addMatches.length > 0}
                    <ul class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border bg-popover shadow-lg">
                        {#each addMatches as m (m.id)}
                            <li>
                                <button
                                    type="button"
                                    class="flex min-h-12 w-full items-center justify-between px-4 text-left hover:bg-muted disabled:opacity-50"
                                    disabled={!m.adult_confirmed && !addAdult}
                                    onclick={() => addMember(m)}
                                >
                                    {m.name}
                                    <span class="text-sm text-muted-foreground"
                                        >{m.adult_confirmed ? (m.member_number ?? '') : 'Haken „volljährig geprüft“ nötig'}</span
                                    >
                                </button>
                            </li>
                        {/each}
                    </ul>
                {/if}
                <Label class="mt-2 flex min-h-11 items-center gap-3 text-sm font-normal">
                    <Checkbox bind:checked={addAdult} class="size-5" />
                    volljährig geprüft (nur für Mitglieder ohne Haken, einmalig)
                </Label>
            </div>
            {#if session.participants.length === 0}
                <p class="text-sm text-muted-foreground">Noch niemand aktiviert.</p>
            {:else}
                <ul class="divide-y rounded-xl border">
                    {#each session.participants as p (p.id)}
                        <li class="grid gap-2 px-4 py-3">
                            <div class="flex flex-wrap items-center gap-3">
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">
                                        {p.name}
                                        {#if p.guest}<Badge variant="secondary" class="ml-1">Gast</Badge>{/if}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        aktiviert um {p.activated_at}{p.from_booking ? ' · aus Buchung' : ''}{p.member_number
                                            ? ` · Nr. ${p.member_number}`
                                            : ''}
                                    </p>
                                </div>
                                <Button
                                    variant="outline"
                                    class="h-12"
                                    onclick={() => {
                                        enteringFor = p;
                                        entering = true;
                                    }}>Erfassen</Button
                                >
                                {#if p.removable}
                                    <Button
                                        variant="ghost"
                                        class="h-12"
                                        aria-label="{p.name} aus der Session entfernen"
                                        onclick={() => post(toUrl(removeRoute(p.id)))}>Entfernen</Button
                                    >
                                {/if}
                            </div>
                            {#if p.entries.length > 0}
                                <ul class="grid gap-1 text-sm">
                                    {#each p.entries as e (e.id)}
                                        <li
                                            class="flex flex-wrap items-center gap-2 rounded-lg bg-muted/40 px-3 py-2 {e.status ===
                                            'pending'
                                                ? ''
                                                : 'text-muted-foreground'}"
                                        >
                                            <span class="w-12 tabular-nums">{e.time}</span>
                                            <span class="min-w-0 flex-1 {e.status === 'withdrawn' ? 'line-through' : ''}"
                                                >{e.weapon_class}{e.weapon_owner ? ` · ${e.weapon_owner}` : ''}{e.discipline
                                                    ? ` · ${e.discipline}`
                                                    : ''} · {e.shots ?? '–'} Schüsse · erfasst über {e.via}</span
                                            >
                                            <Badge variant={e.status === 'rejected' ? 'destructive' : 'secondary'}
                                                >{e.status_label}</Badge
                                            >
                                            {#if e.reject_reason}<span>Grund: {e.reject_reason}</span>{/if}
                                            {#if e.status === 'pending'}
                                                {#if e.can_withdraw}
                                                    <Button
                                                        variant="ghost"
                                                        class="h-11"
                                                        onclick={() => post(toUrl(withdrawRoute(e.id)))}
                                                        >Zurückziehen</Button
                                                    >
                                                {/if}
                                                {#if rejecting === e.id}
                                                    <div class="flex w-full flex-wrap gap-2">
                                                        {#each rejectReasons as r (r)}
                                                            <Button
                                                                variant="outline"
                                                                class="h-11"
                                                                aria-pressed={rejectReason === r}
                                                                onclick={() => (rejectReason = r)}>{r}</Button
                                                            >
                                                        {/each}
                                                        <Input
                                                            bind:value={rejectReason}
                                                            aria-label="Grund für die Ablehnung"
                                                            class="h-11 sm:w-56"
                                                        />
                                                        <Button
                                                            variant="destructive"
                                                            class="h-11"
                                                            disabled={rejectReason.trim().length < 3}
                                                            onclick={() => sendReject(e)}>Ablehnen</Button
                                                        >
                                                        <Button variant="ghost" class="h-11" onclick={() => (rejecting = null)}
                                                            >Abbrechen</Button
                                                        >
                                                    </div>
                                                {:else}
                                                    <Button
                                                        variant="ghost"
                                                        class="h-11"
                                                        onclick={() => {
                                                            rejecting = e.id;
                                                            rejectReason = '';
                                                        }}>Ablehnen</Button
                                                    >
                                                {/if}
                                            {/if}
                                        </li>
                                    {/each}
                                </ul>
                            {/if}
                        </li>
                    {/each}
                </ul>
            {/if}
            <p class="text-xs text-muted-foreground">
                Einträge werden nicht bearbeitet: zurückziehen oder ablehnen und neu erfassen. Nach dem Abzeichnen
                ändert nur noch der Vorstand.
            </p>
        </section>
    {/if}

    {#if signedSessions.length > 0}
        <section class="grid gap-2" aria-labelledby="signed">
            <h2 id="signed" class="text-lg font-semibold">Heute abgezeichnet</h2>
            <ul class="divide-y rounded-xl border text-sm">
                {#each signedSessions as s (s.id)}
                    <li class="px-4 py-2">
                        Session {s.started_at}–{s.signed_at} Uhr · {s.entries}
                        {s.entries === 1 ? 'Eintrag' : 'Einträge'}
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</div>
