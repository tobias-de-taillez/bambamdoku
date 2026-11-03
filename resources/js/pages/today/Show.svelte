<script module lang="ts">
    import { show } from '@/routes/today';

    export const layout = {
        breadcrumbs: [{ title: 'Heute schießen', href: show() }],
    };
</script>

<script lang="ts">
    import { router, usePoll } from '@inertiajs/svelte';
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import SelfEntryForm from '@/components/range-office/SelfEntryForm.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';
    import { store, withdraw } from '@/routes/today';
    import type { LastEntry, Option, OwnEntryRow } from '@/types/range-office';

    /** Stand der Kladde-Zeile nach dem Abzeichnen: storniert oder korrigiert (K8) */
    type RecordState =
        | { state: 'cancelled'; reason: string | null; by: string | null }
        | {
              state: 'corrected';
              reason: string | null;
              by: string;
              weapon_class: string;
              weapon_owner: string | null;
              discipline: string | null;
              shots: number | null;
          };

    type TodayEntry = OwnEntryRow & { record: RecordState | null };

    type Participation = {
        open: boolean;
        signed: boolean;
        supervisor: string;
        activated_at: string;
        entries: TodayEntry[];
    };

    let {
        participation,
        last = null,
        canEnterLicensed = false,
        unregistered = false,
        weaponClasses = [],
        disciplines = [],
    }: {
        participation: Participation | null;
        last?: LastEntry | null;
        canEnterLicensed?: boolean;
        /** Aufsicht der Session nicht mehr im Aufsichtsregister: keine Einträge (V6) */
        unregistered?: boolean;
        weaponClasses?: Option[];
        disciplines?: Option[];
    } = $props();

    // Status, Ablehnungen, Stand im Register und – nach einer Aktivierung – Kategorien und Umfang der Aufsicht nachladen
    usePoll(30000, {
        only: [
            'participation',
            'canEnterLicensed',
            'unregistered',
            'last',
            'weaponClasses',
            'disciplines',
        ],
    });

    let formKey = $state(0);
    let confirmation = $state<string | null>(null);

    // Gesperrt: die alte Bestätigung nicht stehen lassen, falls der Vorstand wieder einträgt
    $effect(() => {
        if (unregistered) {
            confirmation = null;
        }
    });

    function nowHHMM(): string {
        const d = new Date();

        return `${String(d.getHours()).padStart(2, '0')}:${String(d.getMinutes()).padStart(2, '0')}`;
    }

    // Kategorie · Waffe · Disziplin · Schüsse, für Eintrag und Korrektur gleich
    function values(v: {
        weapon_class: string;
        weapon_owner: string | null;
        discipline: string | null;
        shots: number | null;
    }): string {
        return [v.weapon_class, v.weapon_owner, v.discipline, `${v.shots ?? '–'} Schüsse`]
            .filter(Boolean)
            .join(' · ');
    }

    function withdrawEntry(entry: TodayEntry) {
        router.post(toUrl(withdraw(entry.id)), {}, { preserveScroll: true });
    }
</script>

<AppHead title="Heute schießen" />

<div class="flex max-w-xl flex-col gap-6 p-4 md:p-6">
    <header>
        <h1 class="text-2xl font-semibold tracking-tight">Heute schießen</h1>
        {#if participation}
            <p class="flex items-center gap-1.5 text-muted-foreground">
                {#if participation.open && unregistered}
                    <ShieldAlert class="size-4 text-amber-700 dark:text-amber-400" />
                {:else}
                    <ShieldCheck class="size-4 text-emerald-600" />
                {/if}
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
    {:else if participation.open && unregistered}
        <div
            class="rounded-lg border border-amber-500/40 bg-amber-50 p-4 dark:bg-amber-950/30"
            role="status"
        >
            <h2 class="flex items-center gap-2 font-medium">
                <ShieldAlert class="size-4 text-amber-700 dark:text-amber-400" />
                Keine Einträge möglich
            </h2>
            <p class="mt-1 text-sm text-muted-foreground">
                Die Aufsicht dieser Session ist nicht mehr im Aufsichtsregister. Die
                Session nimmt keine Einträge an. Bitte bei einer anderen Aufsicht
                eintragen.
            </p>
        </div>
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
            <!-- Neu aufgebaut wird nur nach dem Erfassen. Den Umfang der Aufsicht setzt das Formular selbst um;
                 ein Neuaufbau bei geänderten Props verlöre die Meldung des Servers, weil Inertia die neue
                 Seite vor onError setzt (V6). -->
            {#key formKey}
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
                        class="grid gap-2 rounded-lg border px-3 py-2 {(e.status === 'pending' ||
                            e.status === 'signed') &&
                        e.record?.state !== 'cancelled'
                            ? ''
                            : 'text-muted-foreground'}"
                    >
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="tabular-nums">{e.time}</span>
                            <span
                                class="min-w-0 flex-1 {e.status === 'withdrawn' || e.record
                                    ? 'line-through'
                                    : ''}"
                                >{values(e)} · über {e.via}</span
                            >
                            <Badge
                                variant={e.status === 'rejected'
                                    ? 'destructive'
                                    : e.record?.state === 'cancelled'
                                      ? 'outline'
                                      : 'secondary'}>{e.status_label}</Badge
                            >
                        </div>
                        {#if e.record?.state === 'cancelled'}
                            <p class="text-sm">
                                Storniert{e.record.by ? ` von ${e.record.by}` : ''}{e.record.reason
                                    ? `: ${e.record.reason}`
                                    : ''}. Zählt nicht.
                            </p>
                        {:else if e.record?.state === 'corrected'}
                            <div class="flex flex-wrap items-center gap-2">
                                <Badge variant="outline">Korrektur</Badge>
                                <span class="min-w-0 flex-1">{values(e.record)}</span>
                            </div>
                            <p class="text-sm text-muted-foreground">
                                Korrigiert von {e.record.by}{e.record.reason ? `: ${e.record.reason}` : ''}.
                            </p>
                        {/if}
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
