<script module lang="ts">
    import { index } from '@/routes/admin/shooting-days';

    export const layout = {
        breadcrumbs: [{ title: 'Schießtage', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Plus from '@lucide/svelte/icons/plus';
    import Repeat from '@lucide/svelte/icons/repeat';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { formatDate, formatMonth, formatWeekday } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { cancel, create, edit, reopen } from '@/routes/admin/shooting-days';
    import { show as showDay } from '@/routes/shooting-days';
    import type { AdminShootingDay } from '@/types/admin';

    type Row = AdminShootingDay & { booked: number; supervisors: string[] };

    let { days, past }: { days: Row[]; past: boolean } = $props();

    let cancellingId = $state<number | null>(null);
    let reason = $state('');

    const months = $derived.by(() => {
        const groups: { month: string; days: Row[] }[] = [];

        for (const day of days) {
            const month = formatMonth(day.date);
            const last = groups.at(-1);

            if (last?.month === month) {
                last.days.push(day);
            } else {
                groups.push({ month, days: [day] });
            }
        }

        return groups;
    });

    function confirmCancel(day: Row) {
        router.post(
            toUrl(cancel(day.id)),
            { reason },
            {
                preserveScroll: true,
                onSuccess: () => {
                    cancellingId = null;
                    reason = '';
                },
            },
        );
    }
</script>

<AppHead title="Schießtage" />

<div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Schießtage"
            description="Termine, an denen die Anlage geöffnet ist. Wiederkehrende Termine legen Sie in einem Schritt an."
        />
        <Button asChild>
            {#snippet children(props)}
                <Link href={toUrl(create())} class={props.class}>
                    <Plus class="size-4" />
                    Schießtag anlegen
                </Link>
            {/snippet}
        </Button>
    </div>

    <div class="inline-flex w-fit rounded-lg bg-muted p-1" role="tablist">
        {#each [{ value: false, label: 'Kommende' }, { value: true, label: 'Vergangene' }] as tab (tab.label)}
            <Link
                href={toUrl(index({ query: tab.value ? { past: 1 } : {} }))}
                role="tab"
                aria-selected={past === tab.value}
                preserveScroll
                class="rounded-md px-3 py-1.5 text-sm {past === tab.value
                    ? 'bg-background font-medium shadow-sm'
                    : 'text-muted-foreground hover:text-foreground'}"
            >
                {tab.label}
            </Link>
        {/each}
    </div>

    {#if days.length === 0}
        <p
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            {past
                ? 'Noch keine vergangenen Schießtage.'
                : 'Keine kommenden Schießtage. Legen Sie einen Termin an, damit Mitglieder buchen können.'}
        </p>
    {/if}

    {#each months as group (group.month)}
        <section class="grid gap-2">
            <h3 class="text-sm font-medium text-muted-foreground">
                {group.month}
            </h3>
            <ul class="divide-y rounded-lg border">
                {#each group.days as day (day.id)}
                    <li class="flex flex-wrap items-center gap-4 p-3 sm:p-4">
                        <div
                            class="flex w-12 shrink-0 flex-col items-center leading-tight {day.cancelled
                                ? 'text-muted-foreground line-through'
                                : ''}"
                        >
                            <span class="text-xs text-muted-foreground uppercase"
                                >{formatWeekday(day.date)}</span
                            >
                            <span class="text-lg font-semibold tabular-nums"
                                >{day.date.slice(8, 10)}.</span
                            >
                        </div>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">
                                <Link
                                    href={toUrl(edit(day.id))}
                                    class="hover:underline"
                                >
                                    {day.title || 'Schießtag'}
                                </Link>
                                <span
                                    class="ml-1 font-normal text-muted-foreground tabular-nums"
                                    >{day.starts_at}–{day.ends_at} Uhr</span
                                >
                                {#if day.series}
                                    <Repeat
                                        class="ml-1 inline size-3.5 text-muted-foreground"
                                        aria-label="Serientermin"
                                    />
                                {/if}
                            </p>
                            <p class="text-sm text-muted-foreground">
                                {day.ranges.map((r) => r.name).join(', ')}
                            </p>
                            {#if !day.cancelled}
                                <p class="mt-1 flex flex-wrap items-center gap-x-3 text-sm">
                                    {#if day.supervisors.length > 0}
                                        <span
                                            class="inline-flex items-center gap-1 text-emerald-700 dark:text-emerald-400"
                                            ><ShieldCheck class="size-3.5" />
                                            {day.supervisors.join(', ')}</span
                                        >
                                    {:else}
                                        <span
                                            class="inline-flex items-center gap-1 text-red-700 dark:text-red-400"
                                            ><ShieldAlert class="size-3.5" /> keine Aufsicht</span
                                        >
                                    {/if}
                                    <span class="text-muted-foreground"
                                        >{day.booked}
                                        {day.booked === 1 ? 'Buchung' : 'Buchungen'}</span
                                    >
                                    <Link
                                        href={toUrl(showDay(day.id))}
                                        class="text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                                        >Tagesansicht</Link
                                    >
                                </p>
                            {/if}
                            <div class="mt-1 flex flex-wrap gap-1">
                                {#if day.cancelled}
                                    <Badge variant="destructive"
                                        >Abgesagt{day.cancel_reason
                                            ? `: ${day.cancel_reason}`
                                            : ''}</Badge
                                    >
                                {/if}
                                {#if day.restricted_to_groups}
                                    {#each day.groups as g (g.id)}
                                        <Badge variant="secondary">{g.name}</Badge>
                                    {/each}
                                {/if}
                                {#if !day.guests_allowed}
                                    <Badge variant="outline">ohne Gäste</Badge>
                                {/if}
                            </div>
                        </div>
                        {#if !past}
                            {#if cancellingId === day.id}
                                <div class="flex w-full flex-wrap gap-2 sm:w-auto">
                                    <Input
                                        bind:value={reason}
                                        placeholder="Grund (optional)"
                                        class="sm:w-56"
                                        aria-label="Grund der Absage"
                                    />
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onclick={() => confirmCancel(day)}
                                        >Absagen</Button
                                    >
                                    <Button
                                        variant="ghost"
                                        size="sm"
                                        onclick={() => (cancellingId = null)}
                                        >Abbrechen</Button
                                    >
                                </div>
                            {:else if day.cancelled}
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onclick={() =>
                                        router.post(toUrl(reopen(day.id)), {}, { preserveScroll: true })}
                                    >Wieder öffnen</Button
                                >
                            {:else}
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onclick={() => {
                                        cancellingId = day.id;
                                        reason = '';
                                    }}>Absagen</Button
                                >
                            {/if}
                        {/if}
                    </li>
                {/each}
            </ul>
        </section>
    {/each}

    {#if past && days.length > 0}
        <p class="text-xs text-muted-foreground">
            Zuletzt: {formatDate(days.at(-1)?.date)} – es werden die letzten 120
            Termine gezeigt.
        </p>
    {/if}
</div>
