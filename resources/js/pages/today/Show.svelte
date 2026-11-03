<script module lang="ts">
    import { show } from '@/routes/today';

    export const layout = {
        breadcrumbs: [{ title: 'Heute schießen', href: show() }],
    };
</script>

<script lang="ts">
    import { router, usePoll } from '@inertiajs/svelte';
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import SelfEntryForm from '@/components/range-office/SelfEntryForm.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';
    import { store, withdraw } from '@/routes/today';
    import type { LastEntry, Option, OwnEntryRow } from '@/types/range-office';

    type Participation = {
        open: boolean;
        signed: boolean;
        supervisor: string;
        activated_at: string;
        entries: OwnEntryRow[];
    };

    let {
        participation,
        last = null,
        canEnterLicensed = false,
        weaponClasses = [],
        disciplines = [],
    }: {
        participation: Participation | null;
        last?: LastEntry | null;
        canEnterLicensed?: boolean;
        weaponClasses?: Option[];
        disciplines?: Option[];
    } = $props();

    // Status, Ablehnungen und – nach einer Aktivierung – Kategorien und Umfang der Aufsicht nachladen
    usePoll(30000, {
        only: ['participation', 'canEnterLicensed', 'last', 'weaponClasses', 'disciplines'],
    });

    let formKey = $state(0);
    let confirmation = $state<string | null>(null);

    function nowHHMM(): string {
        const d = new Date();

        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }

    function withdrawEntry(entry: OwnEntryRow) {
        router.post(toUrl(withdraw(entry.id)), {}, { preserveScroll: true });
    }
</script>

<AppHead title="Heute schießen" />

<div class="flex max-w-xl flex-col gap-6 p-4 md:p-6">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight">Heute schießen</h1>
        {#if participation}
            <p class="flex items-center gap-1.5 text-muted-foreground">
                <ShieldCheck class="size-4 text-emerald-600" />
                Aufsicht {participation.supervisor} · aktiviert um {participation.activated_at}
                Uhr
            </p>
        {/if}
    </header>

    {#if !participation}
        <p class="rounded-lg border p-4 text-sm text-muted-foreground">
            Sie sind heute noch nicht aktiviert. Die Aufsicht aktiviert Sie vor Ort
            am Stand; danach erfassen Sie hier Ihre Schüsse.
        </p>
    {:else if participation.open}
        {#if confirmation}
            <div
                class="flex items-center gap-3 rounded-lg bg-emerald-600 px-4 py-3 text-white"
                role="status"
            >
                <CircleCheck class="size-8 shrink-0" />
                <p>
                    Erfasst um {confirmation} Uhr. Es zählt, sobald die Aufsicht die Session
                    abzeichnet.
                </p>
            </div>
        {/if}
        <section
            class="rounded-xl border-2 border-foreground/15 bg-muted/40 p-3 sm:p-4"
            aria-label="Neuer Eintrag"
        >
            <!-- neu aufbauen, wenn eine andere Aufsicht mit anderem Umfang aktiviert -->
            {#key `${formKey}-${canEnterLicensed}`}
                <SelfEntryForm
                    action={toUrl(store())}
                    {weaponClasses}
                    {disciplines}
                    {canEnterLicensed}
                    {last}
                    onsuccess={(created) => {
                        confirmation = created ? nowHHMM() : null;
                        formKey++;
                    }}
                />
            {/key}
        </section>
        <p class="text-sm text-muted-foreground">
            Mehrere Disziplinen ergeben mehrere Einträge. Einträge werden nicht
            bearbeitet: zurückziehen und neu erfassen.
        </p>
    {:else}
        <p class="rounded-lg border p-4 text-sm text-muted-foreground">
            {participation.signed
                ? 'Die Session ist abgezeichnet. Neue Einträge gehen erst nach einer neuen Aktivierung.'
                : 'Die Session ist nicht mehr offen.'}
        </p>
    {/if}

    {#if participation && participation.entries.length > 0}
        <section class="grid gap-2" aria-labelledby="own-entries">
            <h2 id="own-entries" class="text-lg font-semibold">Ihre Einträge</h2>
            <ul class="grid gap-2">
                {#each participation.entries as e (e.id)}
                    <li
                        class="grid gap-2 rounded-lg border px-3 py-2 {e.status === 'pending' ||
                        e.status === 'signed'
                            ? ''
                            : 'text-muted-foreground'}"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">{e.time}</span>
                            <span
                                class="min-w-0 flex-1 {e.status === 'withdrawn' ? 'line-through' : ''}"
                                >{e.weapon_class}{e.weapon_owner ? ` · ${e.weapon_owner}` : ''}{e.discipline
                                    ? ` · ${e.discipline}`
                                    : ''} · {e.shots ?? '–'} Schüsse · über {e.via}</span
                            >
                            <Badge variant={e.status === 'rejected' ? 'destructive' : 'secondary'}
                                >{e.status_label}</Badge
                            >
                        </div>
                        {#if e.reject_reason}
                            <p class="text-sm text-destructive">
                                Abgelehnt: {e.reject_reason}. Bitte neu erfassen.
                            </p>
                        {/if}
                        {#if e.can_withdraw}
                            <Button
                                variant="outline"
                                class="h-12 justify-self-start"
                                onclick={() => withdrawEntry(e)}>Zurückziehen</Button
                            >
                        {/if}
                    </li>
                {/each}
            </ul>
        </section>
    {/if}
</div>
