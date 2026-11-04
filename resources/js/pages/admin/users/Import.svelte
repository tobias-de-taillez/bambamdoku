<script module lang="ts">
    import { index } from '@/routes/admin/users';

    export const layout = {
        breadcrumbs: [{ title: 'Mitglieder', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, router, useForm } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import Copy from '@lucide/svelte/icons/copy';
    import Download from '@lucide/svelte/icons/download';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { formatDate } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import {
        importCancel,
        importMethod as importPage,
        importPreview,
        importStore,
        importTemplate,
    } from '@/routes/admin/users';

    type Scope = 'licensed' | 'exempt';

    type Row = {
        line: number;
        name: string;
        email: string;
        member_number: string | null;
        member_since: string | null;
        adult: boolean | null;
        scope: Scope | null;
        valid_until: string | null;
        checked_on: string | null;
        note: string | null;
        exists: boolean;
        matched_by: 'email' | 'number' | null;
        current_name: string | null;
        current_email: string | null;
        current_member_number: string | null;
        current_member_since: string | null;
        registers: boolean;
        current_scope: Scope | 'revoked' | null;
        current_until: string | null;
        current_checked_on: string | null;
        adult_confirmed: boolean;
        adult_sets: boolean;
        hints: string[];
        register_hint: string | null;
    };

    type Result = {
        created: number;
        updated: number;
        skipped: number;
        registered: number;
        invited: boolean;
        // Nur true, wenn jede Einladung wirklich per E-Mail hinausging
        mailed: boolean;
        // Einladungen, bei denen das Senden mit einem Fehler abbrach
        failed: number;
        invitations: { name: string; email: string; url: string; mailed: boolean }[];
        uninvited: number;
    };

    let {
        preview = null,
        confirmation = null,
        result = null,
        mailDelivers = false,
    }: {
        preview?: { rows: Row[]; errors: string[]; columns: string[] } | null;
        // Text der Bestätigung vom Server, gleiche Regeln wie beim Übernehmen
        confirmation?: string | null;
        result?: Result | null;
        mailDelivers?: boolean;
    } = $props();

    // Wörter wie in der CSV-Datei
    // Wie viele Einladungen wirklich per E-Mail hinausgingen
    const sentCount = $derived(
        result ? result.invitations.filter((invitation) => invitation.mailed).length : 0,
    );

    const scopeLabels: Record<Scope, string> = {
        licensed: 'erlaubnispflichtig',
        exempt: 'erlaubnisfrei',
    };

    const columns = [
        {
            name: 'Name oder Vorname und Nachname',
            text: 'Pflicht. „Name“ zusammen mit „Vorname“ gilt als Nachname.',
        },
        {
            name: 'E-Mail',
            text: 'Für ein Konto mit Anmeldung. Ohne E-Mail kein Konto; die Aufsicht trägt das Mitglied am Stand über Name oder Nummer ein.',
        },
        {
            name: 'Mitgliedsnummer',
            text: 'Je Zeile E-Mail oder Mitgliedsnummer, damit sich das Mitglied wiederfinden lässt. Ohne passende E-Mail muss der Name zur Nummer passen; Groß- und Kleinschreibung, ä/ae, ß/ss, Bindestriche, Titel und Reihenfolge sind egal. Führende Nullen (0042) bleiben, auch wenn Excel sie entfernt hat.',
        },
        {
            name: 'Eintritt',
            text: 'TT.MM.JJJJ, heute oder früher.',
        },
        {
            name: 'Volljährig geprüft',
            text: 'ja oder nein. Leer: bei bestehenden Mitgliedern keine Änderung, bei neuen nein. Einmal gesetzt, wird es nicht zurückgenommen; beim Übernehmen bestätigen Sie die Prüfung.',
        },
        {
            name: 'Aufsicht',
            text: 'erlaubnispflichtig (auch erlaubnispflichtige Waffen) oder erlaubnisfrei (nur erlaubnisfreie Waffen). Trägt die Person ins Aufsichtsregister ein. Leer: Register bleibt, wie es ist. Entziehen und nach einem Entzug erneut eintragen geht nur einzeln beim Mitglied.',
        },
        {
            name: 'Aufsicht gültig bis',
            text: 'TT.MM.JJJJ. Pflicht mit Aufsicht, nicht vor „geprüft am“.',
        },
        {
            name: 'Aufsicht geprüft am',
            text: 'TT.MM.JJJJ. Pflicht mit Aufsicht, heute oder früher. Ein neuer Eintrag nicht vor der jüngsten Zeile im Register; bei unveränderter Aufsicht wird nichts eingetragen.',
        },
        {
            name: 'Aufsicht Bemerkung',
            text: 'Freiwillig, z. B. Lehrgang Standaufsicht DSB. Höchstens 255 Zeichen.',
        },
    ];

    const upload = useForm({ file: null as File | null });
    const confirmForm = useForm({ send_invitations: false, confirmed: false });

    const rows = $derived(preview?.rows ?? []);
    const errors = $derived(preview?.errors ?? []);
    const newCount = $derived(rows.filter((r) => !r.exists).length);
    const updateCount = $derived(rows.filter((r) => r.exists).length);
    const registerCount = $derived(rows.filter((r) => r.registers).length);
    const invitable = $derived(rows.filter((r) => !r.exists && r.email).length);

    // Bisheriger Stand im Aufsichtsregister, z. B. „erlaubnisfrei bis 31.12.2027“
    function registerState(row: Row): string {
        if (row.current_scope === 'revoked') {
            return `entzogen am ${formatDate(row.current_checked_on)}`;
        }

        return row.current_scope
            ? `${scopeLabels[row.current_scope]} bis ${formatDate(row.current_until)}`
            : '';
    }

    // Ein Feld ändert sich nur, wenn die Datei einen Wert hat und er vom bisherigen abweicht.
    function changes(row: Row, value: string | null, current: string | null): boolean {
        return row.exists && value !== null && value !== '' && value !== current;
    }

    let copied = $state<string | null>(null);

    function submitFile(event: SubmitEvent) {
        event.preventDefault();
        upload.post(toUrl(importPreview()), { forceFormData: true });
    }

    function submitImport() {
        confirmForm.post(toUrl(importStore()), { preserveScroll: true });
    }

    async function copyLink(url: string) {
        await navigator.clipboard.writeText(url);
        copied = url;
        setTimeout(() => (copied = null), 2000);
    }
</script>

<AppHead title="Mitglieder importieren" />

<div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Mitglieder importieren"
        description="CSV-Datei aus Ihrer Vereinsverwaltung oder Tabellenkalkulation. Bestehende Mitglieder (gleiche E-Mail, sonst gleiche Mitgliedsnummer und gleicher Name) werden aktualisiert, niemand wird gelöscht."
    />

    {#if result}
        <div
            class="rounded-lg border border-emerald-600/30 bg-emerald-50 p-4 text-sm dark:bg-emerald-950/40"
            role="status"
        >
            <p class="font-medium">Import abgeschlossen</p>
            <p class="mt-1">
                {result.created} neu angelegt, {result.updated} aktualisiert{result.registered >
                0
                    ? `, ${result.registered} im Aufsichtsregister eingetragen`
                    : ''}.
            </p>
            {#if result.skipped > 0}
                <p class="mt-1 text-muted-foreground">
                    {result.skipped}
                    {result.skipped === 1 ? 'Zeile' : 'Zeilen'} ausgelassen, weil sich die
                    Daten seit der Vorschau geändert haben.
                </p>
            {/if}
        </div>

        {#if result.invitations.length > 0}
            <section class="grid gap-3 rounded-lg border p-4" aria-labelledby="invitations">
                <div>
                    <h2 id="invitations" class="font-medium">Einladungslinks</h2>
                    <p class="text-sm text-muted-foreground">
                        {#if result.mailed}
                            Die Einladungen wurden per E-Mail verschickt. Sie können die
                            Links auch direkt weitergeben.
                        {:else if result.failed > 0 && sentCount > 0}
                            {sentCount} von {result.invitations.length} Einladungen wurden per
                            E-Mail verschickt. Bei {result.failed} ist der Versand
                            fehlgeschlagen; diese Links sind markiert. Bitte geben Sie sie
                            selbst weiter.
                        {:else if result.failed > 0}
                            Der Versand per E-Mail ist fehlgeschlagen, es wurde nichts
                            verschickt. Bitte geben Sie die Links selbst weiter.
                        {:else}
                            Der Mailversand ist nicht eingerichtet, es wurde nichts
                            verschickt. Bitte geben Sie die Links selbst weiter.
                        {/if}
                        Jeder Link ist 7 Tage gültig und funktioniert nur einmal.
                    </p>
                </div>
                <ul class="grid gap-3">
                    {#each result.invitations as invitation (invitation.url)}
                        <li class="grid gap-1.5">
                            <div class="flex flex-wrap items-center gap-2 text-sm">
                                <span
                                    >{invitation.name}
                                    <span class="text-muted-foreground">({invitation.email})</span
                                    ></span
                                >
                                {#if !invitation.mailed && sentCount > 0}
                                    <Badge variant="outline">nicht verschickt</Badge>
                                {/if}
                            </div>
                            <div class="flex gap-2">
                                <Input
                                    readonly
                                    value={invitation.url}
                                    class="font-mono text-xs"
                                    aria-label="Einladungslink für {invitation.name}"
                                    onfocus={(event: FocusEvent) =>
                                        (event.currentTarget as HTMLInputElement).select()}
                                />
                                <Button variant="outline" onclick={() => copyLink(invitation.url)}>
                                    {#if copied === invitation.url}
                                        <Check class="size-4" /> Kopiert
                                    {:else}
                                        <Copy class="size-4" /> Kopieren
                                    {/if}
                                </Button>
                            </div>
                        </li>
                    {/each}
                </ul>
            </section>
        {/if}

        {#if result.uninvited > 0}
            <p class="text-sm text-muted-foreground">
                {result.uninvited}
                {result.uninvited === 1
                    ? 'neues Mitglied mit E-Mail ist'
                    : 'neue Mitglieder mit E-Mail sind'} noch nicht eingeladen. Die Einladung
                senden Sie beim Mitglied.
            </p>
        {/if}

        <div class="flex flex-wrap gap-2 border-t pt-6">
            <Button asChild>
                {#snippet children(props)}
                    <Link href={toUrl(index())} class={props.class}>Zur Mitgliederliste</Link>
                {/snippet}
            </Button>
            <Button variant="ghost" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(importPage())} class={props.class}
                        >Weitere Datei importieren</Link
                    >
                {/snippet}
            </Button>
        </div>
    {:else if !preview}
        <form class="grid gap-4 rounded-lg border p-4" onsubmit={submitFile}>
            <div class="grid gap-2">
                <Label for="file">CSV-Datei</Label>
                <input
                    id="file"
                    type="file"
                    accept=".csv,text/csv,text/plain"
                    class="text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm"
                    onchange={(e) =>
                        (upload.file =
                            (e.currentTarget as HTMLInputElement).files?.[0] ?? null)}
                />
                <InputError message={upload.errors.file} />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button type="submit" disabled={!upload.file || upload.processing}>
                    {#if upload.processing}<Spinner />{/if}
                    Datei prüfen
                </Button>
                <Button variant="ghost" asChild>
                    {#snippet children(props)}
                        <a href={toUrl(importTemplate())} class={props.class}
                            ><Download class="size-4" /> Vorlage (CSV)</a
                        >
                    {/snippet}
                </Button>
            </div>
        </form>

        <section class="grid gap-3" aria-labelledby="columns">
            <div>
                <h2 id="columns" class="font-medium">Spalten</h2>
                <p class="text-sm text-muted-foreground">
                    Erste Zeile mit den Spaltennamen, Groß- und Kleinschreibung egal. Trennzeichen
                    Semikolon oder Komma. Nicht erkannte Spalten werden ignoriert. Passwörter
                    und Rollen werden nie importiert, offene Anträge nehmen Sie einzeln auf.
                </p>
            </div>
            <dl class="divide-y rounded-lg border text-sm">
                {#each columns as column (column.name)}
                    <div class="grid gap-1 px-3 py-2 sm:grid-cols-[13rem_1fr] sm:gap-4">
                        <dt class="font-medium">{column.name}</dt>
                        <dd class="text-muted-foreground">{column.text}</dd>
                    </div>
                {/each}
            </dl>
            <p class="text-sm text-muted-foreground">
                Ihre eigene Registrierung als Aufsicht trägt ein anderes Vorstandsmitglied ein
                (Vier-Augen-Prinzip). Die Beispielzeilen der Vorlage bitte löschen oder ersetzen.
            </p>
        </section>
    {:else}
        {#if errors.length > 0}
            <div
                class="rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-sm dark:bg-amber-950/30"
            >
                <p class="flex items-center gap-2 font-medium">
                    <TriangleAlert class="size-4" />
                    {#if rows.length === 0}
                        Nichts wird übernommen
                    {:else}
                        {errors.length}
                        {errors.length === 1 ? 'Zeile wird' : 'Zeilen werden'} nicht übernommen
                    {/if}
                </p>
                <ul class="mt-2 max-h-60 list-disc overflow-auto pl-5 text-muted-foreground">
                    {#each errors as error (error)}
                        <li>{error}</li>
                    {/each}
                </ul>
            </div>
        {/if}

        {#if rows.length > 0}
            <p class="text-sm">
                <strong>{newCount}</strong> neu, <strong>{updateCount}</strong> werden
                aktualisiert{#if registerCount > 0}, <strong>{registerCount}</strong> neu im
                    Aufsichtsregister{/if}.
            </p>
            <div class="max-h-[28rem] overflow-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-muted text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Zeile</th>
                            <th class="px-3 py-2 font-medium">Name</th>
                            <th class="px-3 py-2 font-medium">Nr.</th>
                            <th class="px-3 py-2 font-medium">Eintritt</th>
                            <th class="px-3 py-2 font-medium">Volljährig</th>
                            <th class="px-3 py-2 font-medium">Aufsicht</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each rows as row (row.line)}
                            <tr>
                                <td class="px-3 py-2 text-muted-foreground tabular-nums"
                                    >{row.line}</td
                                >
                                <td class="px-3 py-2">
                                    <span class:font-medium={changes(row, row.name, row.current_name)}
                                        >{row.name}</span
                                    >
                                    {#if changes(row, row.name, row.current_name)}
                                        <div class="text-xs text-amber-700 dark:text-amber-400">
                                            bisher: {row.current_name}
                                        </div>
                                    {/if}
                                    <div class="text-xs text-muted-foreground">
                                        {#if row.exists && row.email && !row.current_email}
                                            <span
                                                class="font-medium text-amber-700 dark:text-amber-400"
                                                >E-Mail neu: {row.email}</span
                                            >
                                        {:else}
                                            {row.email || row.current_email || 'ohne Konto'}
                                        {/if}
                                    </div>
                                </td>
                                <td class="px-3 py-2 tabular-nums">
                                    <span
                                        class:font-medium={changes(
                                            row,
                                            row.member_number,
                                            row.current_member_number,
                                        )}
                                        >{row.member_number ?? row.current_member_number ?? ''}</span
                                    >
                                    {#if changes(row, row.member_number, row.current_member_number)}
                                        <div class="text-xs text-amber-700 dark:text-amber-400">
                                            bisher: {row.current_member_number ?? '–'}
                                        </div>
                                    {/if}
                                    {#each row.hints as hint (hint)}
                                        <div class="text-xs text-muted-foreground">{hint}</div>
                                    {/each}
                                </td>
                                <td class="px-3 py-2 tabular-nums">
                                    <span
                                        class:font-medium={changes(
                                            row,
                                            row.member_since,
                                            row.current_member_since,
                                        )}
                                        >{formatDate(
                                            row.member_since ?? row.current_member_since,
                                        )}</span
                                    >
                                    {#if changes(row, row.member_since, row.current_member_since)}
                                        <div class="text-xs text-amber-700 dark:text-amber-400">
                                            bisher: {formatDate(row.current_member_since) || '–'}
                                        </div>
                                    {/if}
                                </td>
                                <td class="px-3 py-2">
                                    {#if row.adult_confirmed}
                                        ja
                                    {:else if row.adult_sets}
                                        ja <span class="text-xs text-muted-foreground">(neu)</span>
                                    {:else}
                                        <span class="text-muted-foreground">nein</span>
                                    {/if}
                                </td>
                                <td class="px-3 py-2">
                                    {#if row.scope}
                                        <span class="inline-flex items-center gap-1.5">
                                            <ShieldCheck class="size-4 shrink-0" />
                                            {scopeLabels[row.scope]} bis {formatDate(
                                                row.valid_until,
                                            )}
                                        </span>
                                        <div class="text-xs text-muted-foreground">
                                            {#if !row.registers}
                                                unverändert
                                            {:else}
                                                geprüft am {formatDate(row.checked_on)}
                                            {/if}
                                        </div>
                                        {#if row.register_hint}
                                            <div class="text-xs text-amber-700 dark:text-amber-400">
                                                {row.register_hint}
                                            </div>
                                        {/if}
                                        {#if row.registers && row.current_scope}
                                            <div class="text-xs text-amber-700 dark:text-amber-400">
                                                bisher: {registerState(row)}
                                            </div>
                                        {/if}
                                        {#if row.note}
                                            <div
                                                class="text-xs whitespace-pre-line text-muted-foreground"
                                            >
                                                {row.note}
                                            </div>
                                        {/if}
                                    {:else}
                                        <span class="text-muted-foreground">–</span>
                                        {#if row.current_scope}
                                            <div class="text-xs text-muted-foreground">
                                                bleibt: {registerState(row)}
                                            </div>
                                        {/if}
                                    {/if}
                                </td>
                                <td class="px-3 py-2">
                                    <Badge variant={row.exists ? 'outline' : 'secondary'}
                                        >{row.exists ? 'Update' : 'Neu'}</Badge
                                    >
                                </td>
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>

            <div class="grid gap-3">
                {#if invitable > 0}
                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox bind:checked={confirmForm.send_invitations} class="mt-0.5" />
                        <span>
                            Einladungen an neue Mitglieder mit E-Mail senden
                            {#if !mailDelivers}
                                <span class="block text-muted-foreground"
                                    >Der Mailversand ist nicht eingerichtet. Sie bekommen die
                                    Einladungslinks nach dem Import zum Weitergeben.</span
                                >
                            {/if}
                        </span>
                    </Label>
                {/if}
                {#if confirmation}
                    <Label class="flex items-start gap-3 font-normal">
                        <Checkbox bind:checked={confirmForm.confirmed} class="mt-0.5" />
                        <span>{confirmation}</span>
                    </Label>
                    <InputError message={confirmForm.errors.confirmed} />
                {/if}
            </div>
        {:else}
            <p class="text-sm text-muted-foreground">Keine gültigen Zeilen gefunden.</p>
        {/if}

        <div class="flex flex-wrap gap-2 border-t pt-6">
            {#if rows.length > 0}
                <Button disabled={confirmForm.processing} onclick={submitImport}>
                    {#if confirmForm.processing}<Spinner />{/if}
                    {rows.length}
                    {rows.length === 1 ? 'Mitglied' : 'Mitglieder'} übernehmen{errors.length >
                    0
                        ? `, ${errors.length} ${errors.length === 1 ? 'Zeile' : 'Zeilen'} mit Fehlern auslassen`
                        : ''}
                </Button>
            {/if}
            <Button variant="ghost" onclick={() => router.delete(toUrl(importCancel()))}
                >Andere Datei wählen</Button
            >
        </div>
    {/if}
</div>
