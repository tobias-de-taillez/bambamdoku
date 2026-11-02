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
    import { cancel, late, memberPdf, paperList } from '@/routes/admin/record';
    import Printer from '@lucide/svelte/icons/printer';
    import type { RecordEntry } from '@/types/record';

    type Entry = RecordEntry & {
        user_id: number | null;
        name: string;
        member_number: string | null;
        guest: boolean;
        cancelled_by: string | null;
    };
    type Member = { id: number; name: string; member_number: string | null; active: boolean };
    type Option = { value: string; label: string };

    let {
        entries,
        members,
        weaponClasses,
        disciplines,
        filters,
    }: {
        entries: Entry[];
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
            ['Datum', 'Name', 'Mitgliedsnr.', 'Gast', 'Kategorie', 'Waffe', 'Disziplin', 'Eingetragen von', 'Herkunft', 'Grund Nachtrag', 'Status', 'Storno-Grund', 'Storniert von'],
            ...entries.map((e) => [
                formatDate(e.date),
                e.name,
                e.member_number ?? '',
                e.guest ? 'ja' : 'nein',
                e.weapon_label,
                e.weapon_owner ?? '',
                e.discipline ?? '',
                e.recorded_by,
                e.late ? 'Nachtrag' : 'Stand',
                e.late_reason ?? '',
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

    // ---- Nachtrag ----
    let adding = $state(false);
    let memberFilter = $state('');
    const lateForm = useForm({
        members: [] as number[],
        date: '',
        weapon_class: 'short',
        weapon_owner: 'own',
        discipline: '',
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
        lateForm.transform((d) => ({ ...d, discipline: d.discipline || null }));
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
                    Für Termine, die nicht am Stand eingetragen wurden, z. B. aus der
                    Papier-Ersatzliste. Wird im Auszug als Nachtrag gekennzeichnet.
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
                        <th class="hidden px-4 py-2.5 font-medium lg:table-cell">Eingetragen von</th>
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
                                {#if !e.active}
                                    <span class="block text-xs">storniert: {e.cancel_reason}{e.cancelled_by ? ` (${e.cancelled_by})` : ''}</span>
                                {/if}
                            </td>
                            <td class="px-4 py-2.5">
                                {e.weapon_label}{e.weapon_owner ? ` · ${e.weapon_owner}` : ''}{e.discipline ? ` · ${e.discipline}` : ''}
                            </td>
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell">{e.recorded_by}</td>
                            <td class="px-4 py-2.5 text-right">
                                {#if e.active}
                                    {#if cancelling === e.id}
                                        <div class="flex justify-end gap-2">
                                            <Input bind:value={reason} placeholder="Grund" class="h-8 w-48" aria-label="Grund für das Storno" />
                                            <Button size="sm" variant="destructive" disabled={!reason.trim()} onclick={() => sendCancel(e)}>Storno</Button>
                                            <Button size="sm" variant="ghost" onclick={() => (cancelling = null)}>Abbrechen</Button>
                                        </div>
                                    {:else}
                                        <Button size="sm" variant="ghost" onclick={() => ((cancelling = e.id), (reason = ''))}>Storno</Button>
                                    {/if}
                                {/if}
                            </td>
                        </tr>
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
</div>
