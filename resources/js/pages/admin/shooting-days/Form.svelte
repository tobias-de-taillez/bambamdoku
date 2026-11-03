<script module lang="ts">
    import { index } from '@/routes/admin/shooting-days';

    export const layout = {
        breadcrumbs: [{ title: 'Schießtage', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, router, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { formatLongDate } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { destroy, store, update } from '@/routes/admin/shooting-days';
    import type { AdminShootingDay, RangeOption } from '@/types/admin';

    let {
        day,
        ranges,
        deletable = false,
    }: {
        day: AdminShootingDay | null;
        ranges: RangeOption[];
        deletable?: boolean;
    } = $props();

    let confirmDelete = $state(false);

    // svelte-ignore state_referenced_locally
    const editing = day !== null;

    function today(): string {
        const now = new Date();
        const pad = (n: number) => String(n).padStart(2, '0');

        return `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`;
    }

    // svelte-ignore state_referenced_locally
    const form = useForm({
        date: day?.date ?? today(),
        repeat: false,
        repeat_until: '',
        starts_at: day?.starts_at ?? '17:00',
        ends_at: day?.ends_at ?? '21:00',
        title: day?.title ?? '',
        notes: day?.notes ?? '',
        ranges: day?.ranges.map((r) => r.id) ?? ranges.map((r) => r.id),
        guests_allowed: day?.guests_allowed ?? true,
    });

    const occurrences = $derived.by(() => {
        if (!form.repeat || !form.repeat_until || !form.date) {
            return 1;
        }

        const start = new Date(form.date);
        const end = new Date(form.repeat_until);

        if (end <= start) {
            return 1;
        }

        return Math.floor((end.getTime() - start.getTime()) / (7 * 86400000)) + 1;
    });

    function toggleRange(id: number) {
        form.ranges = form.ranges.includes(id)
            ? form.ranges.filter((x) => x !== id)
            : [...form.ranges, id];
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            repeat_until: data.repeat && data.repeat_until ? data.repeat_until : null,
            title: data.title || null,
            notes: data.notes || null,
        }));

        if (day) {
            form.put(toUrl(update(day.id)));
        } else {
            form.post(toUrl(store()));
        }
    }

    // svelte-ignore state_referenced_locally
    const heading = day ? formatLongDate(day.date) : 'Schießtag anlegen';
</script>

<AppHead title={heading} />

<div class="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title={heading}
        description={editing
            ? 'Änderungen gelten nur für diesen Termin.'
            : 'Einzeltermin oder wöchentlich wiederholt'}
    />

    {#if ranges.length === 0}
        <p class="rounded-lg border border-amber-500/40 bg-amber-50 p-4 text-sm dark:bg-amber-950/30">
            Es sind noch keine Stände in Betrieb. Legen Sie zuerst unter
            <strong>Verwaltung → Stände</strong> mindestens einen Stand an.
        </p>
    {/if}

    <form class="flex flex-col gap-6" onsubmit={submit} novalidate>
        <div class="grid gap-4 sm:grid-cols-3">
            <div class="grid gap-2">
                <Label for="date">Datum</Label>
                <Input id="date" type="date" bind:value={form.date} required />
                <InputError message={form.errors.date} />
            </div>
            <div class="grid gap-2">
                <Label for="starts_at">Beginn</Label>
                <Input
                    id="starts_at"
                    type="time"
                    step="900"
                    bind:value={form.starts_at}
                    required
                />
                <InputError message={form.errors.starts_at} />
            </div>
            <div class="grid gap-2">
                <Label for="ends_at">Ende</Label>
                <Input
                    id="ends_at"
                    type="time"
                    step="900"
                    bind:value={form.ends_at}
                    required
                />
                <InputError message={form.errors.ends_at} />
            </div>
        </div>

        {#if !editing}
            <div class="grid gap-3 rounded-lg border p-4">
                <Label class="flex items-center gap-3 font-normal">
                    <Checkbox bind:checked={form.repeat} />
                    Jede Woche wiederholen
                </Label>
                {#if form.repeat}
                    <div class="grid gap-2 sm:max-w-56">
                        <Label for="repeat_until">bis einschließlich</Label>
                        <Input
                            id="repeat_until"
                            type="date"
                            bind:value={form.repeat_until}
                            min={form.date}
                        />
                        <InputError message={form.errors.repeat_until} />
                    </div>
                    <p class="text-sm text-muted-foreground">
                        Es werden <strong>{occurrences}</strong>
                        {occurrences === 1 ? 'Termin' : 'Termine'} angelegt.
                    </p>
                {/if}
            </div>
        {/if}

        <div class="grid gap-2">
            <Label for="title">Titel</Label>
            <Input
                id="title"
                bind:value={form.title}
                placeholder="optional, z. B. Training Jugend, Vereinsmeisterschaft"
            />
            <InputError message={form.errors.title} />
        </div>

        <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">Geöffnete Stände</legend>
            <div class="grid gap-2 sm:grid-cols-2">
                {#each ranges as range (range.id)}
                    <Label
                        class="flex items-center gap-3 rounded-md border px-3 py-2 font-normal"
                    >
                        <Checkbox
                            checked={form.ranges.includes(range.id)}
                            onclick={() => toggleRange(range.id)}
                        />
                        <span class="flex-1">{range.name}</span>
                        <span class="text-xs text-muted-foreground"
                            >{range.lanes} Bahnen</span
                        >
                    </Label>
                {/each}
            </div>
            <InputError message={form.errors.ranges} />
        </fieldset>

        <div class="grid gap-1 rounded-lg border p-4">
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox
                    bind:checked={form.guests_allowed}
                    aria-describedby="guests_allowed_hint"
                />
                Gastschützen erlaubt (nur für die Online-Buchung)
            </Label>
            <p id="guests_allowed_hint" class="pl-7 text-sm text-muted-foreground">
                Mitglieder können dann beim Buchen einen Gast mitbuchen.
            </p>
        </div>

        <div class="grid gap-2">
            <Label for="notes">Hinweise für Mitglieder</Label>
            <textarea
                id="notes"
                bind:value={form.notes}
                rows="3"
                class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                placeholder="optional, z. B. Stand 2 nur bis 19 Uhr"
            ></textarea>
            <InputError message={form.errors.notes} />
        </div>

        <div class="flex flex-wrap gap-3 border-t pt-6">
            <Button type="submit" disabled={form.processing || ranges.length === 0}>
                {#if form.processing}<Spinner />{/if}
                {editing
                    ? 'Speichern'
                    : occurrences > 1
                      ? `${occurrences} Termine anlegen`
                      : 'Anlegen'}
            </Button>
            <Button variant="ghost" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(index())} class={props.class}>Abbrechen</Link>
                {/snippet}
            </Button>
            {#if day && deletable}
                <div class="ml-auto flex items-center gap-2">
                    {#if confirmDelete}
                        <span class="text-sm">Diesen Termin löschen?</span>
                        <Button
                            type="button"
                            variant="destructive"
                            onclick={() => router.delete(toUrl(destroy(day.id)))}
                            >Löschen</Button
                        >
                        <Button
                            type="button"
                            variant="ghost"
                            onclick={() => (confirmDelete = false)}>Nein</Button
                        >
                    {:else}
                        <Button
                            type="button"
                            variant="ghost"
                            class="text-destructive"
                            onclick={() => (confirmDelete = true)}>Löschen</Button
                        >
                    {/if}
                </div>
            {/if}
        </div>
    </form>
</div>
