<script module lang="ts">
    import { index } from '@/routes/admin/users';

    export const layout = {
        breadcrumbs: [{ title: 'Mitglieder', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import Download from '@lucide/svelte/icons/download';
    import TriangleAlert from '@lucide/svelte/icons/triangle-alert';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { formatDate } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import {
        importCancel,
        importPreview,
        importStore,
        importTemplate,
    } from '@/routes/admin/users';

    type Row = {
        line: number;
        name: string;
        email: string;
        member_number: string | null;
        member_since: string | null;
        supervisor_until: string | null;
        exists: boolean;
    };

    let {
        preview = null,
    }: {
        preview?: { rows: Row[]; errors: string[]; columns: string[] } | null;
    } = $props();

    const upload = useForm({ file: null as File | null });
    const confirm = useForm({ send_invitations: false });

    const newCount = $derived(preview?.rows.filter((r) => !r.exists).length ?? 0);
    const updateCount = $derived(preview?.rows.filter((r) => r.exists).length ?? 0);

    function submitFile(event: SubmitEvent) {
        event.preventDefault();
        upload.post(toUrl(importPreview()), { forceFormData: true });
    }
</script>

<AppHead title="Mitglieder importieren" />

<div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Mitglieder importieren"
        description="CSV-Datei aus Ihrer Vereinsverwaltung oder Tabellenkalkulation. Bestehende Mitglieder (gleiche E-Mail, sonst gleiche Mitgliedsnummer) werden aktualisiert, niemand wird gelöscht."
    />

    {#if !preview}
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
            <p class="text-sm text-muted-foreground">
                Erkannte Spalten: <strong>Name</strong> oder
                <strong>Vorname</strong> und <strong>Nachname</strong>,
                <strong>E-Mail</strong> oder <strong>Mitgliedsnummer</strong> (je Zeile
                mindestens eins davon), Eintritt. Mitglieder ohne E-Mail erhalten kein Konto
                und werden am Stand über Name oder Nummer eingetragen. Aufsichten pflegen
                Sie im Aufsichtsregister des Mitglieds.
                Trennzeichen Semikolon oder Komma, Datum als TT.MM.JJJJ.
            </p>
            <div class="flex flex-wrap gap-2">
                <Button type="submit" disabled={!upload.file || upload.processing}>
                    {#if upload.processing}<Spinner />{/if}
                    Datei prüfen
                </Button>
                <Button variant="ghost" asChild>
                    {#snippet children(props)}
                        <a href={toUrl(importTemplate())} class={props.class}
                            ><Download class="size-4" /> Vorlage herunterladen</a
                        >
                    {/snippet}
                </Button>
            </div>
        </form>
    {:else}
        {#if preview.errors.length > 0}
            <div
                class="rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-sm dark:bg-amber-950/30"
            >
                <p class="flex items-center gap-2 font-medium">
                    <TriangleAlert class="size-4" />
                    {preview.errors.length}
                    {preview.errors.length === 1 ? 'Zeile wird' : 'Zeilen werden'} übersprungen
                </p>
                <ul class="mt-2 list-disc pl-5 text-muted-foreground">
                    {#each preview.errors.slice(0, 20) as error (error)}
                        <li>{error}</li>
                    {/each}
                </ul>
            </div>
        {/if}

        {#if preview.rows.length > 0}
            <p class="text-sm">
                <strong>{newCount}</strong> neu, <strong>{updateCount}</strong> werden
                aktualisiert.
            </p>
            <div class="max-h-[28rem] overflow-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-muted text-left text-muted-foreground">
                        <tr>
                            <th class="px-3 py-2 font-medium">Zeile</th>
                            <th class="px-3 py-2 font-medium">Name</th>
                            <th class="px-3 py-2 font-medium">E-Mail</th>
                            <th class="px-3 py-2 font-medium">Nr.</th>
                            <th class="px-3 py-2 font-medium">Eintritt</th>
                            <th class="px-3 py-2 font-medium" title="Wird nicht übernommen – bitte im Aufsichtsregister eintragen">Aufsicht bis*</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each preview.rows as row (row.line)}
                            <tr>
                                <td class="px-3 py-2 text-muted-foreground tabular-nums">{row.line}</td>
                                <td class="px-3 py-2">{row.name}</td>
                                <td class="px-3 py-2"
                                    >{#if row.email}{row.email}{:else}<span class="text-muted-foreground">ohne Konto</span>{/if}</td
                                >
                                <td class="px-3 py-2 tabular-nums">{row.member_number ?? ''}</td>
                                <td class="px-3 py-2 tabular-nums">{formatDate(row.member_since)}</td>
                                <td class="px-3 py-2 tabular-nums">{formatDate(row.supervisor_until)}</td>
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
            <p class="text-xs text-muted-foreground">
                * „Aufsicht bis“ wird nicht übernommen. Aufsichten tragen Sie mit Umfang und
                Prüfung einzeln im Aufsichtsregister des Mitglieds ein (§ 10 Abs. 3 AWaffV).
            </p>
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox bind:checked={confirm.send_invitations} />
                Neue Mitglieder mit E-Mail-Adresse sofort einladen
            </Label>
        {:else}
            <p class="text-sm text-muted-foreground">Keine gültigen Zeilen gefunden.</p>
        {/if}

        <div class="flex flex-wrap gap-2 border-t pt-6">
            {#if preview.rows.length > 0}
                <Button
                    disabled={confirm.processing}
                    onclick={() => confirm.post(toUrl(importStore()))}
                >
                    {#if confirm.processing}<Spinner />{/if}
                    {preview.rows.length} Mitglieder übernehmen
                </Button>
            {/if}
            <Button variant="ghost" onclick={() => router.delete(toUrl(importCancel()))}
                >Andere Datei wählen</Button
            >
        </div>
    {/if}
</div>
