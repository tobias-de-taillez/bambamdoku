<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { toUrl } from '@/lib/utils';
    import { enter } from '@/routes/range-office';
    import type { EntryMember, Option } from '@/types/range-office';

    let {
        members,
        weaponClasses,
        disciplines,
        canEnterLicensed,
        onclose,
    }: {
        members: EntryMember[];
        weaponClasses: Option[];
        disciplines: Option[];
        canEnterLicensed: boolean;
        onclose: () => void;
    } = $props();

    // svelte-ignore state_referenced_locally
    const form = useForm({
        user_id: null as number | null,
        guest_name: '',
        guest_adult: false,
        weapon_class: canEnterLicensed ? 'short' : 'exempt',
        weapon_owner: 'own' as string | null,
        discipline: '',
    });

    let asGuest = $state(false);
    let query = $state('');
    let confirmation = $state<{ name: string; time: string } | null>(null);
    let confirmationTimer: ReturnType<typeof setTimeout> | undefined;
    function focusSearch() {
        document.getElementById('entry-search')?.focus();
    }

    $effect(() => focusSearch());

    const matches = $derived.by(() => {
        const q = query.trim().toLowerCase();

        if (q.length === 0 || form.user_id !== null) {
            return [];
        }

        return members
            .filter(
                (m) =>
                    m.name.toLowerCase().includes(q) ||
                    (m.member_number ?? '').toLowerCase().startsWith(q),
            )
            .slice(0, 6);
    });

    function pick(member: EntryMember) {
        form.user_id = member.id;
        query = member.name;

        // Vorbelegung aus dem letzten Eintrag der Person
        if (member.last) {
            form.weapon_class =
                member.last.weapon_class !== 'exempt' && !canEnterLicensed
                    ? 'exempt'
                    : member.last.weapon_class;
            form.weapon_owner = member.last.weapon_owner ?? 'own';
            form.discipline = member.last.discipline ?? '';
        }
    }

    function reset() {
        form.reset();
        form.clearErrors();
        query = '';
        focusSearch();
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            user_id: asGuest ? null : data.user_id,
            guest_name: asGuest ? data.guest_name : null,
            weapon_owner: asGuest ? null : data.weapon_owner,
            discipline: asGuest || !data.discipline ? null : data.discipline,
        }));
        const name = asGuest ? form.guest_name.trim() : query;
        form.post(toUrl(enter()), {
            preserveScroll: true,
            onSuccess: () => {
                const now = new Date();
                confirmation = {
                    name,
                    time: `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`,
                };
                clearTimeout(confirmationTimer);
                confirmationTimer = setTimeout(() => (confirmation = null), 5000);
                reset();
            },
        });
    }

    const choice =
        'flex min-h-14 flex-1 items-center justify-center rounded-lg border-2 px-3 text-base font-medium transition-colors';
</script>

<form
    class="grid gap-5 rounded-xl border-2 border-foreground/15 bg-muted/40 p-4 md:p-5"
    onsubmit={submit}
>
    {#if confirmation}
        <div
            class="flex items-center gap-3 rounded-lg bg-emerald-600 px-4 py-3 text-white"
            role="status"
        >
            <CircleCheck class="size-8 shrink-0" />
            <div>
                <p class="text-lg font-semibold">{confirmation.name}</p>
                <p class="text-sm opacity-90">eingetragen um {confirmation.time} Uhr</p>
            </div>
        </div>
    {/if}

    <div class="flex gap-2" role="tablist" aria-label="Wer wird eingetragen?">
        <button
            type="button"
            role="tab"
            aria-selected={!asGuest}
            class="{choice} {!asGuest ? 'border-foreground bg-background' : 'border-transparent text-muted-foreground'}"
            onclick={() => (asGuest = false)}>Mitglied</button
        >
        <button
            type="button"
            role="tab"
            aria-selected={asGuest}
            class="{choice} {asGuest ? 'border-foreground bg-background' : 'border-transparent text-muted-foreground'}"
            onclick={() => (asGuest = true)}>Gast</button
        >
    </div>

    {#if asGuest}
        <div class="grid gap-3">
            <Input
                bind:value={form.guest_name}
                placeholder="Vor- und Nachname des Gastes"
                aria-label="Name des Gastes"
                class="h-14 text-lg"
                autocomplete="off"
            />
            <InputError message={form.errors.guest_name} />
            <Label class="flex min-h-11 items-center gap-3 text-base font-normal">
                <Checkbox bind:checked={form.guest_adult} class="size-6" />
                Gast ist volljährig (Ausweis gesehen)
            </Label>
            <InputError message={(form.errors as Record<string, string>).guest_adult} />
            <p class="text-sm text-muted-foreground">Minderjährige weiter auf Papier.</p>
        </div>
    {:else}
        <div class="relative">
            <Input
                id="entry-search"
                bind:value={query}
                oninput={() => (form.user_id = null)}
                placeholder="Name oder Mitgliedsnummer"
                aria-label="Mitglied suchen"
                autocomplete="off"
                class="h-14 text-lg"
            />
            {#if matches.length > 0}
                <ul
                    class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border bg-popover shadow-lg"
                >
                    {#each matches as m (m.id)}
                        <li>
                            <button
                                type="button"
                                class="flex min-h-14 w-full items-center justify-between px-4 text-left text-base hover:bg-muted"
                                onclick={() => pick(m)}
                            >
                                {m.name}
                                <span class="text-sm text-muted-foreground"
                                    >{m.member_number ?? ''}</span
                                >
                            </button>
                        </li>
                    {/each}
                </ul>
            {/if}
            <InputError message={form.errors.user_id} />
        </div>
    {/if}

    <fieldset class="grid gap-2">
        <legend class="mb-1 text-sm font-medium">Kategorie</legend>
        <div class="flex gap-2">
            {#each weaponClasses as c (c.value)}
                {@const blocked = c.value !== 'exempt' && !canEnterLicensed}
                <button
                    type="button"
                    disabled={blocked}
                    aria-pressed={form.weapon_class === c.value}
                    title={blocked ? 'Ihr Registereintrag umfasst nur erlaubnisfreie Waffen.' : ''}
                    class="{choice} {form.weapon_class === c.value
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-input bg-background'} disabled:opacity-40"
                    onclick={() => (form.weapon_class = c.value)}>{c.label}</button
                >
            {/each}
        </div>
        <InputError message={form.errors.weapon_class} />
    </fieldset>

    {#if !asGuest}
        <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">Waffe</legend>
            <div class="flex gap-2">
                {#each [{ value: 'own', label: 'eigene' }, { value: 'club', label: 'Vereinswaffe' }] as o (o.value)}
                    <button
                        type="button"
                        aria-pressed={form.weapon_owner === o.value}
                        class="{choice} {form.weapon_owner === o.value
                            ? 'border-foreground bg-foreground text-background'
                            : 'border-input bg-background'}"
                        onclick={() => (form.weapon_owner = o.value)}>{o.label}</button
                    >
                {/each}
            </div>
        </fieldset>

        {#if disciplines.length > 0}
            <div class="grid gap-2">
                <Label for="discipline">Disziplin</Label>
                <select
                    id="discipline"
                    bind:value={form.discipline}
                    class="h-14 rounded-lg border border-input bg-background px-3 text-base"
                >
                    <option value="" disabled>Bitte wählen</option>
                    {#each disciplines as d (d.value)}
                        <option value={d.value}>{d.label}</option>
                    {/each}
                </select>
                <InputError message={(form.errors as Record<string, string>).discipline} />
            </div>
        {/if}
    {/if}

    <InputError message={(form.errors as Record<string, string>).entry} />

    <div class="flex gap-2">
        <Button
            type="submit"
            class="h-14 flex-1 text-lg"
            disabled={form.processing || (!asGuest && form.user_id === null)}
        >
            {#if form.processing}<Spinner />{/if}
            Eintragen
        </Button>
        <Button type="button" variant="ghost" class="h-14" onclick={onclose}>Schließen</Button>
    </div>
</form>
