<script module lang="ts">
    import { index } from '@/routes/admin/record';

    export const layout = {
        breadcrumbs: [{ title: 'Schießkladde des Vereins', href: index() }],
    };
</script>

<script lang="ts">
    import { router } from '@inertiajs/svelte';
    import Download from '@lucide/svelte/icons/download';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { formatDate, formatWeekday } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { memberPdf } from '@/routes/admin/record';
    import type { RecordEntry } from '@/types/record';

    type Entry = RecordEntry & {
        user_id: number | null;
        name: string;
        member_number: string | null;
        guest: boolean;
        host: string | null;
    };

    let {
        entries,
        members,
        filters,
    }: {
        entries: Entry[];
        members: { id: number; name: string; member_number: string | null }[];
        filters: { from: string; to: string; user: number | null };
    } = $props();

    // svelte-ignore state_referenced_locally
    let from = $state(filters.from);
    // svelte-ignore state_referenced_locally
    let to = $state(filters.to);
    // svelte-ignore state_referenced_locally
    let user = $state<number | null>(filters.user);

    function apply(event: SubmitEvent) {
        event.preventDefault();
        router.get(
            toUrl(index()),
            { from, to, user: user ?? undefined },
            { preserveState: true, preserveScroll: true },
        );
    }

    function exportCsv() {
        const rows = [
            ['Datum', 'Von', 'Bis', 'Name', 'Mitgliedsnr.', 'Gast', 'Eingeladen von', 'Stand', 'Waffenart', 'Waffe', 'Bestätigt von'],
            ...entries.map((e) => [
                formatDate(e.date),
                e.starts_at,
                e.ends_at,
                e.name,
                e.member_number ?? '',
                e.guest ? 'ja' : 'nein',
                e.host ?? '',
                e.range,
                e.weapon_label,
                e.club_weapon ? 'Vereinswaffe' : 'eigene',
                e.confirmed_by ?? '',
            ]),
        ];
        const csv = rows
            .map((r) => r.map((v) => `"${String(v).replaceAll('"', '""')}"`).join(';'))
            .join('\r\n');
        const blob = new Blob(['﻿' + csv], { type: 'text/csv;charset=utf-8' });
        const link = document.createElement('a');
        link.href = URL.createObjectURL(blob);
        link.download = `schiesskladde-${from}-bis-${to}.csv`;
        link.click();
        URL.revokeObjectURL(link.href);
    }

    const selected = $derived(members.find((m) => m.id === filters.user));
</script>

<AppHead title="Schießkladde des Vereins" />

<div class="flex max-w-6xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Schießkladde des Vereins"
        description="Alle von der Standaufsicht bestätigten Besuche, auch von Gästen. Grundlage für Bescheinigungen nach § 14 WaffG."
    />

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
            <select
                id="member"
                bind:value={user}
                class="h-9 w-56 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
            >
                <option value={null}>Alle</option>
                {#each members as m (m.id)}
                    <option value={m.id}>{m.name}</option>
                {/each}
            </select>
        </div>
        <Button type="submit" variant="outline">Anzeigen</Button>
        <div class="ml-auto flex gap-2">
            <Button type="button" variant="outline" onclick={exportCsv} disabled={entries.length === 0}>
                <Download class="size-4" /> CSV
            </Button>
            {#if selected}
                <Button asChild>
                    {#snippet children(props)}
                        <a
                            href={toUrl(memberPdf(selected.id))}
                            class={props.class}
                        >
                            <Download class="size-4" /> Auszug {selected.name}
                        </a>
                    {/snippet}
                </Button>
            {/if}
        </div>
    </form>

    {#if entries.length === 0}
        <p
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Keine bestätigten Besuche im Zeitraum.
        </p>
    {:else}
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Datum</th>
                        <th class="px-4 py-2.5 font-medium">Zeit</th>
                        <th class="px-4 py-2.5 font-medium">Schütze</th>
                        <th class="px-4 py-2.5 font-medium">Stand</th>
                        <th class="px-4 py-2.5 font-medium">Waffe</th>
                        <th class="hidden px-4 py-2.5 font-medium lg:table-cell">Bestätigt von</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    {#each entries as e (e.id)}
                        <tr>
                            <td class="px-4 py-2.5 whitespace-nowrap tabular-nums">
                                <span class="text-muted-foreground">{formatWeekday(e.date)}</span>
                                {formatDate(e.date)}
                            </td>
                            <td class="px-4 py-2.5 tabular-nums">{e.starts_at}–{e.ends_at}</td>
                            <td class="px-4 py-2.5">
                                {e.name}
                                {#if e.guest}
                                    <Badge variant="secondary" class="ml-1">Gast</Badge>
                                    {#if e.host}
                                        <span class="block text-xs text-muted-foreground"
                                            >eingeladen von {e.host}</span
                                        >
                                    {/if}
                                {:else if e.member_number}
                                    <span class="text-xs text-muted-foreground">· {e.member_number}</span>
                                {/if}
                            </td>
                            <td class="px-4 py-2.5">{e.range}</td>
                            <td class="px-4 py-2.5"
                                >{e.weapon_label}<span class="text-muted-foreground"
                                    >{e.club_weapon ? ' · Verein' : ' · eigene'}</span
                                ></td
                            >
                            <td class="hidden px-4 py-2.5 text-muted-foreground lg:table-cell"
                                >{e.confirmed_by ?? '–'}</td
                            >
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        <p class="text-xs text-muted-foreground">{entries.length} Einträge</p>
    {/if}
</div>
