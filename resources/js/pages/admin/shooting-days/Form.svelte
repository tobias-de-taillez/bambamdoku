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
    import { formatDate, formatLongDate, formatWeekday } from '@/lib/format';
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

    type Pattern = 'none' | 'weekly' | 'biweekly' | 'monthly_weekday' | 'monthly_day';
    type Week = '1' | '2' | '3' | '4' | 'last';

    const WEEKS: Week[] = ['1', '2', '3', '4', 'last'];
    const PREVIEW_LIMIT = 6;
    const weekdayName = new Intl.DateTimeFormat('de-DE', { weekday: 'long' });

    let confirmDelete = $state(false);

    // svelte-ignore state_referenced_locally
    const editing = day !== null;

    const pad = (n: number) => String(n).padStart(2, '0');

    function iso(date: Date): string {
        return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
    }

    function today(): string {
        return iso(new Date());
    }

    function inOneYear(): string {
        const now = new Date();

        return iso(new Date(now.getFullYear() + 1, now.getMonth(), now.getDate()));
    }

    /** JJJJ-MM-TT als lokales Datum; nie über UTC, sonst verrutscht der Tag. */
    function parseIso(value: string): Date | null {
        const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

        if (!match) {
            return null;
        }

        const [year, month, date] = [Number(match[1]), Number(match[2]) - 1, Number(match[3])];
        const result = new Date(year, month, date);

        return result.getMonth() === month && result.getDate() === date ? result : null;
    }

    function daysInMonth(year: number, month: number): number {
        return new Date(year, month + 1, 0).getDate();
    }

    function weekOfMonth(date: Date): number {
        return Math.floor((date.getDate() - 1) / 7) + 1;
    }

    function isLastOfMonth(date: Date): boolean {
        return date.getDate() + 7 > daysInMonth(date.getFullYear(), date.getMonth());
    }

    function defaultWeek(value: string): Week {
        const date = parseIso(value);
        const week = date ? weekOfMonth(date) : 1;

        return week > 4 ? 'last' : (String(week) as Week);
    }

    function matchesWeeks(date: Date, weeks: Week[]): boolean {
        return (
            weeks.includes(String(weekOfMonth(date)) as Week) ||
            (weeks.includes('last') && isLastOfMonth(date))
        );
    }

    /**
     * Dieselben Regeln wie App\Domain\Recurrence – nur für die Vorschau, maßgeblich ist der Server.
     */
    function seriesDates(startValue: string, pattern: Pattern, untilValue: string, weeks: Week[]): string[] {
        const start = parseIso(startValue);

        if (!start) {
            return [];
        }

        const until = parseIso(untilValue);

        if (pattern === 'none' || !until || until < start) {
            return [iso(start)];
        }

        const dates: Date[] = [];

        if (pattern === 'weekly' || pattern === 'biweekly') {
            const step = pattern === 'weekly' ? 7 : 14;

            for (let i = 0; i < 1000; i++) {
                const date = new Date(start.getFullYear(), start.getMonth(), start.getDate() + i * step);

                if (date > until) {
                    break;
                }

                dates.push(date);
            }
        } else {
            for (let offset = 0; offset < 240; offset++) {
                const first = new Date(start.getFullYear(), start.getMonth() + offset, 1);

                if (first > until) {
                    break;
                }

                const [year, month] = [first.getFullYear(), first.getMonth()];
                const length = daysInMonth(year, month);
                const days: number[] = [];

                if (pattern === 'monthly_day') {
                    // Monate ohne diesen Tag (z. B. den 31.) fallen aus.
                    if (start.getDate() <= length) {
                        days.push(start.getDate());
                    }
                } else {
                    const firstMatch = 1 + ((start.getDay() - first.getDay() + 7) % 7);

                    for (const week of weeks) {
                        days.push(
                            week === 'last'
                                ? firstMatch + 7 * Math.floor((length - firstMatch) / 7)
                                : firstMatch + 7 * (Number(week) - 1),
                        );
                    }
                }

                for (const d of days) {
                    const date = new Date(year, month, d);

                    if (date >= start && date <= until) {
                        dates.push(date);
                    }
                }
            }
        }

        return [...new Set(dates.map(iso))].sort();
    }

    // svelte-ignore state_referenced_locally
    const form = useForm({
        date: day?.date ?? today(),
        repeat: 'none' as Pattern,
        repeat_until: '',
        repeat_weeks: [defaultWeek(day?.date ?? today())] as Week[],
        starts_at: day?.starts_at ?? '17:00',
        ends_at: day?.ends_at ?? '21:00',
        title: day?.title ?? '',
        notes: day?.notes ?? '',
        ranges: day?.ranges.map((r) => r.id) ?? ranges.map((r) => r.id),
        guests_allowed: day?.guests_allowed ?? true,
    });

    const start = $derived(parseIso(form.date));
    const weekday = $derived(start ? weekdayName.format(start) : 'Wochentag');
    const startMismatch = $derived(
        form.repeat === 'monthly_weekday' && start !== null && !matchesWeeks(start, form.repeat_weeks),
    );
    /** Wortgleich mit der Meldung des Servers (Recurrence::mismatchMessage). */
    const mismatchMessage = $derived.by(() => {
        if (!start) {
            return '';
        }

        const week = weekOfMonth(start);
        const position =
            week > 4 ? 'letzte' : isLastOfMonth(start) ? `${week}. und letzte` : `${week}.`;

        return `Der ${formatDate(form.date)} ist der ${position} ${weekday} im Monat. Bitte diese Woche ankreuzen oder ein anderes Startdatum wählen.`;
    });
    const dates = $derived(
        editing ? [] : seriesDates(form.date, form.repeat, form.repeat_until, form.repeat_weeks),
    );
    const occurrences = $derived(startMismatch ? 0 : dates.length);

    function formatDay(value: string): string {
        return `${formatWeekday(value)} ${formatDate(value)}`;
    }

    /** Neues Startdatum: dessen Woche im Monat vorschlagen. */
    function resetWeeks() {
        form.repeat_weeks = [defaultWeek(form.date)];
    }

    function toggleWeek(week: Week) {
        const next = form.repeat_weeks.includes(week)
            ? form.repeat_weeks.filter((w) => w !== week)
            : [...form.repeat_weeks, week];

        form.repeat_weeks = WEEKS.filter((w) => next.includes(w));
    }

    function toggleRange(id: number) {
        form.ranges = form.ranges.includes(id)
            ? form.ranges.filter((x) => x !== id)
            : [...form.ranges, id];
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            repeat_until: data.repeat !== 'none' && data.repeat_until ? data.repeat_until : null,
            repeat_weeks: data.repeat === 'monthly_weekday' ? data.repeat_weeks : [],
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
            : 'Einzeltermin oder wiederkehrend'}
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
                <Input
                    id="date"
                    type="date"
                    bind:value={form.date}
                    onchange={resetWeeks}
                    required
                />
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
            <div class="grid gap-4 rounded-lg border p-4">
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="grid min-w-0 gap-2">
                        <Label for="repeat">Wiederholen</Label>
                        <select
                            id="repeat"
                            bind:value={form.repeat}
                            class="h-9 w-full min-w-0 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                        >
                            <option value="none">nicht wiederholen</option>
                            <option value="weekly">jede Woche</option>
                            <option value="biweekly">alle zwei Wochen</option>
                            <option value="monthly_weekday"
                                >monatlich am selben Wochentag{start
                                    ? ` (${weekday})`
                                    : ''}</option
                            >
                            <option value="monthly_day"
                                >monatlich am selben Tag{start
                                    ? ` (am ${start.getDate()}.)`
                                    : ''}</option
                            >
                        </select>
                        <InputError message={form.errors.repeat} />
                    </div>
                    {#if form.repeat !== 'none'}
                        <div class="grid min-w-0 gap-2">
                            <Label for="repeat_until">bis einschließlich</Label>
                            <Input
                                id="repeat_until"
                                type="date"
                                bind:value={form.repeat_until}
                                min={form.date}
                                max={inOneYear()}
                                aria-describedby="repeat_until_hint"
                            />
                            <p id="repeat_until_hint" class="text-xs text-muted-foreground">
                                höchstens ein Jahr im Voraus
                            </p>
                            <InputError message={form.errors.repeat_until} />
                        </div>
                    {/if}
                </div>

                {#if form.repeat === 'monthly_weekday'}
                    <fieldset class="grid gap-2">
                        <legend class="mb-1 text-sm font-medium">Welche {weekday}e im Monat?</legend>
                        <div class="flex flex-wrap gap-2">
                            {#each WEEKS as week (week)}
                                <Label
                                    class="flex items-center gap-2 rounded-md border px-3 py-2 font-normal"
                                >
                                    <Checkbox
                                        checked={form.repeat_weeks.includes(week)}
                                        onclick={() => toggleWeek(week)}
                                    />
                                    {week === 'last' ? 'letzter' : `${week}.`}
                                    {weekday}
                                </Label>
                            {/each}
                        </div>
                        {#if start && isLastOfMonth(start) && weekOfMonth(start) <= 4}
                            <p class="text-sm text-muted-foreground">
                                Der {formatDate(form.date)} ist auch der letzte {weekday} im Monat.
                                Mit „letzter“ liegt der Termin immer am Monatsende, auch in Monaten
                                mit fünf {weekday}en.
                            </p>
                        {/if}
                        <InputError
                            message={startMismatch ? mismatchMessage : form.errors.repeat_weeks}
                        />
                    </fieldset>
                {/if}

                {#if form.repeat === 'monthly_day' && start && start.getDate() > 28}
                    <p class="text-sm text-muted-foreground">
                        Monate ohne einen {start.getDate()}. werden übersprungen{start.getDate() === 31
                            ? ' (Februar, April, Juni, September, November)'
                            : ' (Februar)'}.
                    </p>
                {/if}

                {#if form.repeat !== 'none' && form.repeat_until && occurrences > 0}
                    <div class="grid gap-1 text-sm" aria-live="polite">
                        <p>
                            {#if occurrences === 1}
                                Es wird <strong>1</strong> Termin angelegt:
                            {:else}
                                Es werden <strong>{occurrences}</strong> Termine angelegt:
                            {/if}
                        </p>
                        <ul class="flex flex-wrap gap-x-3 gap-y-1 text-muted-foreground tabular-nums">
                            {#each dates.slice(0, PREVIEW_LIMIT) as date (date)}
                                <li>{formatDay(date)}</li>
                            {/each}
                            {#if dates.length > PREVIEW_LIMIT}
                                <li aria-hidden="true">…</li>
                                <li>{formatDay(dates[dates.length - 1])}</li>
                            {/if}
                        </ul>
                    </div>
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
