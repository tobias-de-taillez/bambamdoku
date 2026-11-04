<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import type { LastEntry, Option } from '@/types/range-office';

    /**
     * Erfassen für sich selbst: am eigenen Gerät (heute) und am Kiosk.
     * Mitglieder geben Kategorie, eigen/Verein und Schüsse immer an (E1).
     */
    let {
        action,
        weaponClasses,
        disciplines,
        canEnterLicensed,
        last = null,
        extra = {},
        submitLabel = 'Erfassen',
        onsuccess,
        onerror,
        oncancel,
    }: {
        action: string;
        weaponClasses: Option[];
        disciplines: Option[];
        canEnterLicensed: boolean;
        last?: LastEntry | null;
        /** zusätzliche Felder, z. B. Teilnehmer und PIN am Kiosk */
        extra?: Record<string, string | number>;
        submitLabel?: string;
        /** created = false bei „schon erfasst“ */
        onsuccess?: (created: boolean) => void;
        onerror?: (errors: Record<string, string>) => void;
        oncancel?: () => void;
    } = $props();

    function initialClass(): string {
        const wanted = last?.weapon_class ?? (canEnterLicensed ? 'short' : 'exempt');

        return wanted !== 'exempt' && !canEnterLicensed ? 'exempt' : wanted;
    }

    // svelte-ignore state_referenced_locally
    const form = useForm({
        weapon_class: initialClass(),
        weapon_owner: last?.weapon_owner ?? 'own',
        shots: (last?.shots ?? '') as string | number,
        discipline: last?.discipline ?? '',
    });

    const errors = $derived(form.errors as Record<string, string>);

    // Verliert die Aufsicht den Umfang erlaubnispflichtig (Wechsel, Register), springt die
    // Auswahl auf erlaubnisfrei, ohne das Formular neu aufzubauen: Eine Meldung bleibt stehen.
    $effect(() => {
        if (!canEnterLicensed && form.weapon_class !== 'exempt') {
            form.weapon_class = 'exempt';
        }
    });

    // Nie eine Kategorie absenden, die nicht als Knopf angezeigt wurde.
    const classShown = $derived(
        weaponClasses.some((c) => c.value === form.weapon_class),
    );

    function submit(event: SubmitEvent) {
        event.preventDefault();

        if (!classShown) {
            return;
        }

        form.transform((data) => ({
            ...data,
            ...extra,
            shots: data.shots === '' ? null : Number(data.shots),
            discipline: data.discipline || null,
        }));
        form.post(action, {
            preserveScroll: true,
            onSuccess: (page) => {
                const toast = (page as { flash?: { toast?: { type?: string } } })
                    .flash?.toast;
                onsuccess?.(toast?.type !== 'info');
            },
            onError: (e) => onerror?.(e as Record<string, string>),
        });
    }

    const choice =
        'flex min-h-14 min-w-0 items-center justify-center rounded-lg border-2 px-2 text-base font-medium transition-colors';
</script>

<form class="grid min-w-0 gap-5" onsubmit={submit}>
    <fieldset class="grid gap-2">
        <legend class="mb-1 text-sm font-medium">Kategorie</legend>
        <div class="grid grid-cols-3 gap-2">
            {#each weaponClasses as c (c.value)}
                {@const blocked = c.value !== 'exempt' && !canEnterLicensed}
                <button
                    type="button"
                    disabled={blocked}
                    aria-pressed={form.weapon_class === c.value}
                    title={blocked
                        ? 'Die Aufsicht dieser Session hat nur den Umfang erlaubnisfreie Waffen.'
                        : ''}
                    class="{choice} {form.weapon_class === c.value
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-input bg-background'} disabled:opacity-40"
                    onclick={() => (form.weapon_class = c.value)}
                    >{c.label}</button
                >
            {/each}
        </div>
        <InputError message={errors.weapon_class} />
    </fieldset>

    <fieldset class="grid gap-2">
        <legend class="mb-1 text-sm font-medium">Waffe</legend>
        <div class="grid grid-cols-2 gap-2">
            {#each [{ value: 'own', label: 'eigene' }, { value: 'club', label: 'Vereinswaffe' }] as o (o.value)}
                <button
                    type="button"
                    aria-pressed={form.weapon_owner === o.value}
                    class="{choice} {form.weapon_owner === o.value
                        ? 'border-foreground bg-foreground text-background'
                        : 'border-input bg-background'}"
                    onclick={() => (form.weapon_owner = o.value)}
                    >{o.label}</button
                >
            {/each}
        </div>
        <InputError message={errors.weapon_owner} />
    </fieldset>

    {#if disciplines.length > 0}
        <div class="grid gap-2">
            <Label for="self-discipline">Disziplin</Label>
            <select
                id="self-discipline"
                bind:value={form.discipline}
                class="h-14 rounded-lg border border-input bg-background px-3 text-base"
            >
                <option value="" disabled>Bitte wählen</option>
                {#each disciplines as d (d.value)}
                    <option value={d.value}>{d.label}</option>
                {/each}
            </select>
            <InputError message={errors.discipline} />
        </div>
    {/if}

    <div class="grid gap-2">
        <Label for="self-shots">Schüsse</Label>
        <Input
            id="self-shots"
            type="number"
            inputmode="numeric"
            min="1"
            max="999"
            required
            bind:value={form.shots}
            class="h-14 text-lg"
        />
        <InputError message={errors.shots} />
    </div>

    <InputError message={errors.entry} />

    <div class="flex gap-2">
        <Button
            type="submit"
            class="h-14 flex-1 text-lg"
            disabled={form.processing || form.shots === '' || !classShown}
        >
            {#if form.processing}<Spinner />{/if}
            {submitLabel}
        </Button>
        {#if oncancel}
            <Button
                type="button"
                variant="ghost"
                class="h-14 px-3"
                onclick={oncancel}>Abbrechen</Button
            >
        {/if}
    </div>
</form>
