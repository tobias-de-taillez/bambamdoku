<script module lang="ts">
    import { index } from '@/routes/admin/shooting-days';

    export const layout = {
        breadcrumbs: [{ title: 'Schießtage', href: index() }],
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
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { formatDate, formatWeekday } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import {
        importCancel,
        importPreview,
        importStore,
        importTemplate,
    } from '@/routes/admin/shooting-days';

    type Row = {
        line: number;
        date: string;
        starts_at: string;
        ends_at: string;
        title: string | null;
        notes: string | null;
        guests_allowed: boolean;
        range_names: string[];
    };

    let {
        preview = null,
        ranges = [],
    }: {
        preview?: { rows: Row[]; errors: string[]; skipped: number } | null;
        ranges?: string[];
    } = $props();

    const upload = useForm({ file: null as File | null });
    const confirm = useForm({});

    const confirmLabel = $derived.by(() => {
        if (!preview) {
            return '';
        }

        const count = preview.rows.length;
        const label = `${count} ${count === 1 ? 'Schießtag' : 'Schießtage'} anlegen`;

        return preview.skipped > 0
            ? `${label} (${preview.skipped} ${preview.skipped === 1 ? 'Zeile' : 'Zeilen'} übersprungen)`
            : label;
    });

    function submitFile(event: SubmitEvent) {
        event.preventDefault();
        upload.post(toUrl(importPreview()), { forceFormData: true });
    }
</script>

<AppHead title="Schießtage importieren" />

<div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Schießtage importieren"
        description="Viele Termine auf einmal aus einer CSV-Datei, z. B. dem Jahresplan aus der Tabellenkalkulation. Jede Zeile wird geprüft wie im Formular; erst nach Ihrer Bestätigung wird etwas angelegt."
    />

    {#if !preview}
        <form class="grid gap-4 rounded-lg border p-4" onsubmit={submitFile}>
            <div class="grid gap-2">
                <Label for="file">CSV-Datei</Label>
                <input
                    id="file"
                    type="file"
                    accept=".csv,text/csv,text/plain"
                    class="min-w-0 text-sm file:mr-3 file:rounded-md file:border file:border-input file:bg-background file:px-3 file:py-1.5 file:text-sm"
                    onchange={(e) =>
                        (upload.file =
                            (e.currentTarget as HTMLInputElement).files?.[0] ??
                            null)}
                />
                <InputError message={upload.errors.file} />
            </div>
            <div class="flex flex-wrap gap-2">
                <Button
                    type="submit"
                    disabled={!upload.file || upload.processing}
                >
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

        <section class="grid gap-3 text-sm" aria-labelledby="columns">
            <h3 id="columns" class="font-medium">Spalten der Datei</h3>
            <p class="text-muted-foreground">
                Die erste Zeile enthält die Spaltennamen, Groß- und
                Kleinschreibung egal. Trennzeichen Semikolon oder Komma. Die
                Vorlage enthält zwei Beispielzeilen.
            </p>
            <dl
                class="grid gap-x-6 gap-y-2 rounded-lg border p-4 sm:grid-cols-[10rem_1fr]"
            >
                <dt class="font-medium">Datum</dt>
                <dd class="text-muted-foreground">
                    TT.MM.JJJJ oder JJJJ-MM-TT, ab heute und höchstens ein Jahr
                    im Voraus.
                </dd>
                <dt class="font-medium">Beginn, Ende</dt>
                <dd class="text-muted-foreground">
                    Uhrzeit als HH:MM, z. B. 10:00. Das Ende liegt nach dem
                    Beginn.
                </dd>
                <dt class="font-medium">Titel</dt>
                <dd class="text-muted-foreground">
                    optional, z. B. Training Jugend
                </dd>
                <dt class="font-medium">Stände</dt>
                <dd class="text-muted-foreground">
                    Namen der Stände wie in der Standverwaltung, mehrere mit „|“
                    getrennt. Leer = alle Stände in Betrieb.
                    {#if ranges.length > 0}
                        Ihre Stände: {ranges.join(', ')}.
                    {:else}
                        Es ist noch kein Stand in Betrieb.
                    {/if}
                </dd>
                <dt class="font-medium">Gäste erlaubt</dt>
                <dd class="text-muted-foreground">ja oder nein, leer = ja</dd>
                <dt class="font-medium">Hinweise</dt>
                <dd class="text-muted-foreground">
                    optional, Hinweise für Mitglieder
                </dd>
            </dl>
            <p class="text-muted-foreground">
                Termine, die sich mit einem bestehenden Schießtag oder einer
                anderen Zeile auf demselben Stand überschneiden, werden
                übersprungen.
            </p>
        </section>
    {:else}
        {#if preview.errors.length > 0}
            <div
                class="rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-sm dark:bg-amber-950/30"
            >
                <p class="flex items-center gap-2 font-medium">
                    <TriangleAlert class="size-4" />
                    {#if preview.skipped === 0}
                        Die Datei lässt sich so nicht übernehmen
                    {:else}
                        {preview.skipped}
                        {preview.skipped === 1 ? 'Zeile wird' : 'Zeilen werden'} übersprungen
                    {/if}
                </p>
                <ul
                    class="mt-2 max-h-64 list-disc overflow-auto pl-5 text-muted-foreground"
                >
                    {#each preview.errors as error, i (i)}
                        <li>{error}</li>
                    {/each}
                </ul>
            </div>
        {/if}

        {#if preview.rows.length > 0}
            <div class="max-h-[28rem] overflow-auto rounded-lg border">
                <table class="w-full text-sm">
                    <thead
                        class="sticky top-0 bg-muted text-left text-muted-foreground"
                    >
                        <tr>
                            <th class="px-3 py-2 font-medium">Zeile</th>
                            <th class="px-3 py-2 font-medium">Datum</th>
                            <th class="px-3 py-2 font-medium">Zeit</th>
                            <th class="px-3 py-2 font-medium">Titel</th>
                            <th class="px-3 py-2 font-medium">Stände</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y">
                        {#each preview.rows as row (row.line)}
                            <tr>
                                <td
                                    class="px-3 py-2 text-muted-foreground tabular-nums"
                                    >{row.line}</td
                                >
                                <td class="px-3 py-2 whitespace-nowrap tabular-nums"
                                    >{formatWeekday(row.date)}
                                    {formatDate(row.date)}</td
                                >
                                <td class="px-3 py-2 whitespace-nowrap tabular-nums"
                                    >{row.starts_at}–{row.ends_at}</td
                                >
                                <td class="px-3 py-2">
                                    {row.title ?? ''}
                                    {#if !row.guests_allowed}
                                        <Badge variant="outline">ohne Gäste</Badge>
                                    {/if}
                                </td>
                                <td class="px-3 py-2"
                                    >{row.range_names.join(', ')}</td
                                >
                            </tr>
                        {/each}
                    </tbody>
                </table>
            </div>
        {:else}
            <p class="text-sm text-muted-foreground">
                Keine gültigen Zeilen gefunden. Bitte die Datei korrigieren und
                erneut hochladen.
            </p>
        {/if}

        <div class="flex flex-wrap gap-2 border-t pt-6">
            {#if preview.rows.length > 0}
                <Button
                    disabled={confirm.processing}
                    onclick={() => confirm.post(toUrl(importStore()))}
                >
                    {#if confirm.processing}<Spinner />{/if}
                    {confirmLabel}
                </Button>
            {/if}
            <Button
                variant="ghost"
                onclick={() => router.delete(toUrl(importCancel()))}
                >Abbrechen</Button
            >
        </div>
    {/if}
</div>
