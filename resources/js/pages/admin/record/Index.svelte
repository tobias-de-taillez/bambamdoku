<script module lang="ts">
    import { index } from '@/routes/admin/record';

    export const layout = {
        breadcrumbs: [{ title: 'Kladde des Vereins', href: index() }],
    };
</script>

<script lang="ts">
    import { page, router, useForm } from '@inertiajs/svelte';
    import Download from '@lucide/svelte/icons/download';
    import FilePlus from '@lucide/svelte/icons/file-plus';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { formatDate, formatWeekday } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { cancel, correct, late, memberPdf, paperList, sessionPdf } from '@/routes/admin/record';
    import Printer from '@lucide/svelte/icons/printer';
    import type { RecordEntry } from '@/types/record';

    type Entry = RecordEntry & {
        user_id: number | null;
        name: string;
        member_number: string | null;
        guest: boolean;
        cancelled_by: string | null;
        recorded_by_id: number;
        corrected_by_entry: number | null;
    };
    type SessionRow = {
        id: number;
        date: string;
        supervisor: string;
        started_at: string;
        signed_at: string | null;
        state: 'open' | 'signed' | 'expired';
        participants: number;
        pending: number;
        signed: number;
    };
    type Dropped = {
        id: number;
        date: string;
        name: string;
        member_number: string | null;
        weapon_label: string;
        discipline: string | null;
        shots: number | null;
        via: string;
        status: string;
        rejected: boolean;
        reason: string | null;
        by: string | null;
        supervisor: string;
    };
    type Member = { id: number; name: string; member_number: string | null; active: boolean };
    type Option = { value: string; label: string };

    let {
        entries,
        sessions,
        dropped,
        members,
        weaponClasses,
        disciplines,
        filters,
    }: {
        entries: Entry[];
        sessions: SessionRow[];
        dropped: Dropped[];
        members: Member[];
        weaponClasses: Option[];
        disciplines: Option[];
        filters: { from: string; to: string; user: number | null };
    } = $props();

    // svelte-ignore state_referenced_locally
    let from = $state(filters.from);
    // svelte-ignore state_referenced_locally
    let to = $state(filters.to);
    // svelte-ignore state_referenced_locally
    let user = $state<number | null>(filters.user);
    let showCancelled = $state(true);

    function apply(event: SubmitEvent) {
        event.preventDefault();
        router.get(toUrl(index()), { from, to, user: user ?? undefined }, { preserveState: true, preserveScroll: true });
    }

    const visible = $derived(showCancelled ? entries : entries.filter((e) => e.active));
    const selected = $derived(members.find((m) => m.id === filters.user));

    // ---- CSV (alle Einträge, Stornos mit Vermerk) ----
    function exportCsv() {
        const rows = [
            ['Datum', 'Name', 'Mitgliedsnr.', 'Gast', 'Kategorie', 'Waffe', 'Disziplin', 'Schüsse', 'Erfasst über', 'Bestätigt von', 'Herkunft', 'Grund Nachtrag/Korrektur', 'korrigiert Zeile', 'Status', 'Storno-Grund', 'Storniert von'],
            ...entries.map((e) => [
                formatDate(e.date),
                e.name,
                e.member_number ?? '',
                e.guest ? 'ja' : 'nein',
                e.weapon_label,
                e.weapon_owner ?? '',
                e.discipline ?? '',
                e.shots === null ? '' : String(e.shots),
                e.via,
                e.confirmed_by,
                e.correction ? 'Korrektur' : e.late ? 'Nachtrag' : 'Stand/Session',
                e.late_reason ?? '',
                e.corrects_id === null ? '' : String(e.corrects_id),
                e.active ? 'gültig' : 'storniert',
                e.cancel_reason ?? '',
                e.cancelled_by ?? '',
            ]),
        ];
        // Werte, die mit = + - @ beginnen, würde Excel als Formel ausführen.
        const cell = (v: string) => `"${(/^[=+\-@\t\r]/.test(v) ? `'${v}` : v).replaceAll('"', '""')}"`;
        const csv = rows.map((r) => r.map((v) => cell(String(v))).join(';')).join('\r\n');
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `schiesskladde-${from}-bis-${to}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    // ---- Storno ----
    let cancelling = $state<number | null>(null);
    let reason = $state('');

    function sendCancel(entry: Entry) {
        router.post(
            toUrl(cancel(entry.id)),
            { reason },
            { preserveScroll: true, onSuccess: () => ((cancelling = null), (reason = '')) },
        );
    }

    // ---- Korrektur: nur Kategorie, eigen/Verein, Disziplin, Schüsse ----
    let correcting = $state<Entry | null>(null);
    const correctForm = useForm({
        weapon_class: 'short',
        weapon_owner: 'own' as string | null,
        discipline: '',
        shots: '' as string | number,
        reason: '',
    });

    function startCorrect(entry: Entry) {
        cancelling = null;
        correcting = entry;
        correctForm.clearErrors();
        correctForm.weapon_class = entry.weapon_class;
        correctForm.weapon_owner = entry.guest ? null : entry.club_weapon ? 'club' : 'own';
        correctForm.discipline = disciplines.find((d) => d.label === entry.discipline)?.value ?? '';
        correctForm.shots = entry.shots ?? '';
        correctForm.reason = '';
    }

    // Kurz/Lang nur, wenn die Ursprungszeile schon Kurz/Lang war
    const correctClasses = $derived(
        correcting?.weapon_class === 'exempt' ? weaponClasses.filter((c) => c.value === 'exempt') : weaponClasses,
    );

    function submitCorrect(event: SubmitEvent) {
        event.preventDefault();

        if (!correcting) {
            return;
        }

        const id = correcting.id;
        correctForm.transform((d) => ({
            ...d,
            discipline: d.discipline || null,
            shots: d.shots === '' ? null : Number(d.shots),
        }));
        correctForm.post(toUrl(correct(id)), {
            preserveScroll: true,
            onSuccess: () => {
                correcting = null;
                correctForm.reset();
            },
        });
    }

    // Nie an Zeilen mit eigener Person als Mitglied oder Bestätiger (E3)
    const mayChange = (e: Entry) =>
        e.active && e.user_id !== page.props.auth.user.id && e.recorded_by_id !== page.props.auth.user.id;

    const sessionStates = { open: 'offen', signed: 'abgezeichnet', expired: 'verfallen, nicht abgezeichnet' };
    const expiredCount = $derived(sessions.filter((s) => s.state === 'expired').length);

    // ---- Nachtrag ----
    let adding = $state(false);
    let memberFilter = $state('');
    const lateForm = useForm({
        members: [] as number[],
        date: '',
        weapon_class: 'short',
        weapon_owner: 'own',
        discipline: '',
        shots: '' as string | number,
        reason: '',
    });
    // Niemand trägt sich selbst ein – auch nicht als Nachtrag.
    const filteredMembers = $derived(
        members.filter(
            (m) =>
                m.id !== page.props.auth.user.id &&
                (memberFilter.trim() === '' ||
                m.name.toLowerCase().includes(memberFilter.toLowerCase()) ||
                    (m.member_number ?? '').startsWith(memberFilter)),
        ),
    );

    function toggleMember(id: number) {
        lateForm.members = lateForm.members.includes(id)
            ? lateForm.members.filter((m) => m !== id)
            : [...lateForm.members, id];
    }

    function submitLate(event: SubmitEvent) {
        event.preventDefault();
        lateForm.transform((d) => ({
            ...d,
            discipline: d.discipline || null,
            shots: d.shots === '' ? null : Number(d.shots),
        }));
        lateForm.post(toUrl(late()), {
            preserveScroll: true,
            onSuccess: () => {
                adding = false;
                lateForm.reset();
            },
        });
    }
</script>

<AppHead title="Kladde des Vereins" />

<div class="flex max-w-6xl flex-col gap-6 p-4 md:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Schießkladde des Vereins"
            description="Alle Einträge inklusive Gästen und Stornos. Grundlage für den Auszug zur Vorlage beim Verband."
        />
        <div class="flex gap-2">
            <Button variant="ghost" asChild>
                {#snippet children(props)}
                    <a href={toUrl(paperList())} class={props.class}
                        ><Printer class="size-4" /> Papier-Ersatzliste</a
                    >
                {/snippet}
            </Button>
            {#if !adding}
                <Button variant="outline" onclick={() => (adding = true)}>
                    <FilePlus class="size-4" /> Nachtrag
                </Button>
            {/if}
        </div>
    </div>

    {#if adding}
        <form class="grid gap-4 rounded-lg border bg-muted/30 p-4" onsubmit={submitLate}>
            <div>
                <h2 class="font-medium">Nachtrag</h2>
                <p class="text-sm text-muted-foreground">
                    Für Termine, die nicht am Stand abgezeichnet wurden, z. B. aus der
                    Papier-Ersatzliste oder einer verfallenen Session. Wird im Auszug als Nachtrag
                    gekennzeichnet. Vor dem Speichern fragt die App einmal nach Ihrem Passwort.
                </p>
            </div>
            <div class="grid gap-4 md:grid-cols-[1fr_1fr]">
                <div class="grid content-start gap-2">
                    <Label for="late-filter">Mitglieder ({lateForm.members.length} gewählt)</Label>
                    <Input id="late-filter" bind:value={memberFilter} placeholder="Name oder Nummer filtern" />
                    <div class="max-h-56 overflow-auto rounded-md border bg-background">
                        {#each filteredMembers as m (m.id)}
                            <Label class="flex items-center gap-3 border-b px-3 py-2 font-normal last:border-0">
                                <Checkbox
                                    checked={lateForm.members.includes(m.id)}
                                    onclick={() => toggleMember(m.id)}
                                />
                                {m.name}
                                <span class="ml-auto text-xs text-muted-foreground">{m.member_number ?? ''}{m.active ? '' : ' · deaktiviert'}</span>
                            </Label>
                        {/each}
                    </div>
                    <InputError message={lateForm.errors.members} />
                </div>
                <div class="grid content-start gap-3">
                    <div class="grid gap-2">
                        <Label for="late-date">Datum</Label>
                        <Input id="late-date" type="date" bind:value={lateForm.date} />
                        <InputError message={lateForm.errors.date} />
                    </div>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <Label for="late-class">Kategorie</Label>
                            <select id="late-class" bind:value={lateForm.weapon_class} class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                                {#each weaponClasses as c (c.value)}<option value={c.value}>{c.label}</option>{/each}
                            </select>
                        </div>
                        <div class="grid gap-2">
                            <Label for="late-owner">Waffe</Label>
                            <select id="late-owner" bind:value={lateForm.weapon_owner} class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                                <option value="own">eigene</option>
                                <option value="club">Vereinswaffe</option>
                            </select>
                        </div>
                    </div>
                    {#if disciplines.length > 0}
                        <div class="grid gap-2">
                            <Label for="late-discipline">Disziplin</Label>
                            <select id="late-discipline" bind:value={lateForm.discipline} class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                                <option value="" disabled>Bitte wählen</option>
                                {#each disciplines as d (d.value)}<option value={d.value}>{d.label}</option>{/each}
                            </select>
                            <InputError message={(lateForm.errors as Record<string, string>).discipline} />
                        </div>
                    {/if}
                    <div class="grid gap-2">
                        <Label for="late-shots">Schüsse (optional)</Label>
                        <Input id="late-shots" type="number" min="1" max="999" inputmode="numeric" bind:value={lateForm.shots} class="w-32" />
                        <InputError message={(lateForm.errors as Record<string, string>).shots} />
                    </div>
                    <div class="grid gap-2">
                        <Label for="late-reason">Grund mit Beleg</Label>
                        <Input id="late-reason" bind:value={lateForm.reason} placeholder="z. B. Papierliste vom 03.11., Aufsicht: H. Schießwart" />
                        <InputError message={lateForm.errors.reason} />
                    </div>
                </div>
            </div>
            <div class="flex gap-2">
                <Button type="submit" disabled={lateForm.processing || lateForm.members.length === 0}>
                    {lateForm.members.length} nachtragen
                </Button>
                <Button type="button" variant="ghost" onclick={() => (adding = false)}>Abbrechen</Button>
            </div>
        </form>
    {/if}

    <form class="flex flex-wrap items-end gap-3" onsubmit={apply}>
        <div class="grid gap-1">
            <Label for="from" class="text-xs">Von</Label>
            <Input id="from" type="date" bind:value={from} class="w-40" />
        </div>
        <div class="grid gap-1">
            <Label for="to" class="text-xs">Bis</Label>
            <Input id="to" type="date" bind:value={to} class="w-40" />
        </div>
        <div class="grid gap-1">
            <Label for="member" class="text-xs">Mitglied</Label>
            <select id="member" bind:value={user} class="h-9 w-56 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                <option value={null}>Alle</option>
                {#each members as m (m.id)}<option value={m.id}>{m.name}</option>{/each}
            </select>
        </div>
        <Button type="submit" variant="outline">Anzeigen</Button>
        <Label class="flex items-center gap-2 font-normal">
            <Checkbox bind:checked={showCancelled} /> Stornos zeigen
        </Label>
        <div class="ml-auto flex gap-2">
            <Button type="button" variant="outline" onclick={exportCsv} disabled={entries.length === 0}>
                <Download class="size-4" /> CSV
            </Button>
            {#if selected}
                <Button asChild>
                    {#snippet children(props)}
                        <a href={toUrl(memberPdf(selected.id, { query: { from: filters.from, to: filters.to } }))} class={props.class}>
                            <Download class="size-4" /> Auszug {selected.name}
                        </a>
                    {/snippet}
                </Button>
            {/if}
        </div>
    </form>

    {#if visible.length === 0}
        <p class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground">
            Keine Einträge im Zeitraum.
        </p>
    {:else}
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Datum</th>
                        <th class="px-4 py-2.5 font-medium">Schütze</th>
                        <th class="px-4 py-2.5 font-medium">Kategorie</th>
                        <th class="px-4 py-2.5 text-right font-medium">Schüsse</th>
                        <th class="hidden px-4 py-2.5 font-medium lg:table-cell">Bestätigt von</th>
                        <th class="px-4 py-2.5"></th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    {#each visible as e (e.id)}
                        <tr class={e.active ? '' : 'text-muted-foreground'}>
                            <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                                <span class="text-muted-foreground">{formatWeekday(e.date)}</span>
                                {formatDate(e.date)}
                            </td>
                            <td class="px-4 py-2.5">
                                <span class={e.active ? '' : 'line-through'}>{e.name}</span>
                                {#if e.guest}<Badge variant="secondary" class="ml-1">Gast</Badge>{/if}
                                {#if e.late}<Badge variant="outline" class="ml-1" title={e.late_reason ?? ''}>Nachtrag</Badge>{/if}
                                {#if e.correction}<Badge variant="outline" class="ml-1" title={e.late_reason ?? ''}>Korrektur</Badge>{/if}
                                {#if e.correction}
                                    <span class="block text-xs text-muted-foreground">korrigiert Zeile #{e.corrects_id}: {e.late_reason}</span>
                                {/if}
                                {#if !e.active}
                                    <span class="block text-xs">storniert: {e.cancel_reason}{e.cancelled_by ? ` (${e.cancelled_by})` : ''}{e.corrected_by_entry ? `, neu: Zeile #${e.corrected_by_entry}` : ''}</span>
                                {/if}
                            </td>
                            <td class="px-4 py-2.5">
                                {e.weapon_label}{e.weapon_owner ? ` · ${e.weapon_owner}` : ''}{e.discipline ? ` · ${e.discipline}` : ''}
                            </td>
                            <td class="px-4 py-2.5 text-right tabular-nums">{e.shots ?? '–'}</td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">
                                {e.confirmed_by}
                                <span class="block text-xs">erfasst über {e.via}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                {#if mayChange(e)}
                                    {#if cancelling === e.id}
                                        <div class="flex justify-end gap-2">
                                            <Input bind:value={reason} placeholder="Grund" class="h-8 w-48" aria-label="Grund für das Storno" />
                                            <Button size="sm" variant="destructive" disabled={!reason.trim()} onclick={() => sendCancel(e)}>Storno</Button>
                                            <Button size="sm" variant="ghost" onclick={() => (cancelling = null)}>Abbrechen</Button>
                                        </div>
                                    {:else}
                                        <div class="flex justify-end gap-1">
                                            <Button size="sm" variant="ghost" onclick={() => startCorrect(e)}>Korrektur</Button>
                                            <Button size="sm" variant="ghost" onclick={() => ((cancelling = e.id), (correcting = null), (reason = ''))}>Storno</Button>
                                        </div>
                                    {/if}
                                {/if}
                            </td>
                        </tr>
                        {#if correcting?.id === e.id}
                            <tr class="bg-muted/30">
                                <td colspan="6" class="px-4 py-3">
                                    <form class="grid gap-3" onsubmit={submitCorrect}>
                                        <p class="text-sm text-muted-foreground">
                                            Korrektur: Die Zeile wird storniert und mit den neuen Angaben neu angelegt.
                                            Person und Datum bleiben; dafür Storno und Nachtrag.
                                        </p>
                                        <div class="flex flex-wrap items-end gap-3">
                                            <div class="grid gap-1">
                                                <Label for="correct-class" class="text-xs">Kategorie</Label>
                                                <select id="correct-class" bind:value={correctForm.weapon_class} class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                                                    {#each correctClasses as c (c.value)}<option value={c.value}>{c.label}</option>{/each}
                                                </select>
                                            </div>
                                            {#if !e.guest}
                                                <div class="grid gap-1">
                                                    <Label for="correct-owner" class="text-xs">Waffe</Label>
                                                    <select id="correct-owner" bind:value={correctForm.weapon_owner} class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                                                        <option value="own">eigene</option>
                                                        <option value="club">Vereinswaffe</option>
                                                    </select>
                                                </div>
                                                {#if disciplines.length > 0}
                                                    <div class="grid gap-1">
                                                        <Label for="correct-discipline" class="text-xs">Disziplin</Label>
                                                        <select id="correct-discipline" bind:value={correctForm.discipline} class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm">
                                                            <option value="" disabled>Bitte wählen</option>
                                                            {#each disciplines as d (d.value)}<option value={d.value}>{d.label}</option>{/each}
                                                        </select>
                                                    </div>
                                                {/if}
                                            {/if}
                                            <div class="grid gap-1">
                                                <Label for="correct-shots" class="text-xs">Schüsse</Label>
                                                <Input id="correct-shots" type="number" min="1" max="999" inputmode="numeric" bind:value={correctForm.shots} class="w-24" />
                                            </div>
                                            <div class="grid min-w-56 flex-1 gap-1">
                                                <Label for="correct-reason" class="text-xs">Grund</Label>
                                                <Input id="correct-reason" bind:value={correctForm.reason} placeholder="z. B. Kategorie falsch erfasst, laut Aufsicht" />
                                            </div>
                                            <Button type="submit" size="sm" disabled={correctForm.processing || correctForm.reason.trim().length < 5}>Korrigieren</Button>
                                            <Button type="button" size="sm" variant="ghost" onclick={() => (correcting = null)}>Abbrechen</Button>
                                        </div>
                                        {#each Object.values(correctForm.errors) as message (message)}
                                            <InputError {message} />
                                        {/each}
                                    </form>
                                </td>
                            </tr>
                        {/if}
                    {/each}
                </tbody>
            </table>
        </div>
        <p class="text-xs text-muted-foreground">
            {entries.filter((e) => e.active).length} gültige Einträge{entries.some((e) => !e.active)
                ? `, ${entries.filter((e) => !e.active).length} storniert`
                : ''}
        </p>
    {/if}

    <section class="grid gap-3" aria-labelledby="sessions-heading">
        <div>
            <h2 id="sessions-heading" class="text-lg font-semibold">Sessions im Zeitraum</h2>
            <p class="text-sm text-muted-foreground">
                {expiredCount > 0
                    ? `${expiredCount} verfallen: nicht am Session-Tag abgezeichnet, ohne Nachweis. Was zählen soll, kommt als Nachtrag mit Grund.`
                    : 'Keine verfallenen Sessions im Zeitraum.'}
            </p>
        </div>
        {#if sessions.length === 0}
            <p class="text-sm text-muted-foreground">Keine Sessions im Zeitraum.</p>
        {:else}
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Datum</th>
                            <th class="px-4 py-2.5 font-medium">Aufsicht</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                            <th class="px-4 py-2.5 text-right font-medium">Teilnehmer</th>
                            <th class="px-4 py-2.5 text-right font-medium">Einträge</th>
                            <th class="px-4 py-2.5"><span class="sr-only">Protokoll</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each sessions as s (s.id)}
                            <tr>
                                <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">{formatDate(s.date)}</td>
                                <td class="px-4 py-2.5">{s.supervisor}</td>
                                <td class="px-4 py-2.5">
                                    <Badge variant={s.state === 'expired' ? 'destructive' : s.state === 'signed' ? 'secondary' : 'outline'}>{sessionStates[s.state]}</Badge>
                                    <span class="ml-1 text-xs text-muted-foreground">ab {s.started_at}{s.signed_at ? `, bis ${s.signed_at}` : ''}</span>
                                </td>
                                <td class="px-4 py-2.5 text-right tabular-nums">{s.participants}</td>
                                <td class="px-4 py-2.5 text-right tabular-nums">
                                    {s.signed} abgezeichnet{s.pending > 0 ? `, ${s.pending} offen` : ''}
                                </td>
                                <td class="px-4 py-2.5 text-right">
                                    <Button size="sm" variant="ghost" asChild>
                                        {#snippet children(props)}
                                            <a href={toUrl(sessionPdf(s.id))} class={props.class} aria-label={`Protokoll der Session am ${formatDate(s.date)}, ${s.supervisor}`}
                                                ><Printer class="size-4" /> Protokoll</a
                                            >
                                        {/snippet}
                                    </Button>
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>

    <section class="grid gap-3" aria-labelledby="dropped-heading">
        <div>
            <h2 id="dropped-heading" class="text-lg font-semibold">Abgelehnt und zurückgezogen</h2>
            <p class="text-sm text-muted-foreground">Diese Einträge stehen nicht in der Kladde und zählen nicht.</p>
        </div>
        {#if dropped.length === 0}
            <p class="text-sm text-muted-foreground">Keine im Zeitraum.</p>
        {:else}
            <div class="overflow-x-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="bg-muted/50 text-left text-muted-foreground">
                        <tr>
                            <th class="px-4 py-2.5 font-medium">Datum</th>
                            <th class="px-4 py-2.5 font-medium">Schütze</th>
                            <th class="px-4 py-2.5 font-medium">Eintrag</th>
                            <th class="px-4 py-2.5 font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each dropped as d (d.id)}
                            <tr>
                                <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">{formatDate(d.date)}</td>
                                <td class="px-4 py-2.5">{d.name}<span class="block text-xs text-muted-foreground">Aufsicht: {d.supervisor}</span></td>
                                <td class="px-4 py-2.5">
                                    {d.weapon_label}{d.discipline ? ` · ${d.discipline}` : ''} · {d.shots ?? '–'} Schuss
                                    <span class="block text-xs text-muted-foreground">erfasst über {d.via}</span>
                                </td>
                                <td class="px-4 py-2.5">
                                    {d.status}{d.by ? ` von ${d.by}` : ''}
                                    {#if d.rejected && d.reason}<span class="block text-xs text-muted-foreground">Grund: {d.reason}</span>{/if}
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {/if}
    </section>
</div>
