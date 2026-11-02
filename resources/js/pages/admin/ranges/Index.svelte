<script module lang="ts">
    import { index } from '@/routes/admin/ranges';

    export const layout = {
        breadcrumbs: [{ title: 'Stände', href: index() }],
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Plus from '@lucide/svelte/icons/plus';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import { store, update } from '@/routes/admin/ranges';

    type Range = {
        id: number;
        name: string;
        lanes: number;
        weapon_types: string[];
        notes: string | null;
        position: number;
        active: boolean;
    };

    let {
        ranges,
        weaponTypes,
    }: {
        ranges: Range[];
        weaponTypes: { value: string; label: string }[];
    } = $props();

    const labels = $derived(
        Object.fromEntries(weaponTypes.map((t) => [t.value, t.label])),
    );

    // null = geschlossen, 0 = neuer Stand, sonst ID
    let editingId = $state<number | null>(null);

    const form = useForm({
        name: '',
        lanes: 5,
        weapon_types: [] as string[],
        notes: '',
        active: true,
    });

    function open(range: Range | null) {
        form.clearErrors();
        editingId = range?.id ?? 0;
        form.name = range?.name ?? '';
        form.lanes = range?.lanes ?? 5;
        form.weapon_types = range ? [...range.weapon_types] : [];
        form.notes = range?.notes ?? '';
        form.active = range?.active ?? true;
    }

    function toggleType(value: string) {
        form.weapon_types = form.weapon_types.includes(value)
            ? form.weapon_types.filter((t) => t !== value)
            : [...form.weapon_types, value];
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => (editingId = null),
        };

        if (editingId) {
            form.put(toUrl(update(editingId)), options);
        } else {
            form.post(toUrl(store()), options);
        }
    }
</script>

<AppHead title="Stände" />

{#snippet editor()}
    <form class="grid gap-4 rounded-lg border bg-muted/30 p-4" onsubmit={submit}>
        <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
            <div class="grid gap-2">
                <Label for="range-name">Bezeichnung</Label>
                <Input
                    id="range-name"
                    bind:value={form.name}
                    placeholder="z. B. 25 m Kurzwaffe"
                />
                <InputError message={form.errors.name} />
            </div>
            <div class="grid gap-2">
                <Label for="range-lanes">Bahnen</Label>
                <Input
                    id="range-lanes"
                    type="number"
                    min="1"
                    max="100"
                    bind:value={form.lanes}
                />
                <InputError message={form.errors.lanes} />
            </div>
        </div>
        <fieldset class="grid gap-2">
            <legend class="mb-1 text-sm font-medium">Zugelassene Waffenarten</legend>
            <div class="flex flex-wrap gap-2">
                {#each weaponTypes as type (type.value)}
                    <Label
                        class="flex items-center gap-2 rounded-md border bg-background px-3 py-2 font-normal"
                    >
                        <Checkbox
                            checked={form.weapon_types.includes(type.value)}
                            onclick={() => toggleType(type.value)}
                        />
                        {type.label}
                    </Label>
                {/each}
            </div>
            <InputError message={form.errors.weapon_types} />
        </fieldset>
        <div class="grid gap-2">
            <Label for="range-notes">Hinweis</Label>
            <Input
                id="range-notes"
                bind:value={form.notes}
                placeholder="optional, z. B. nur Kaliber bis .45"
            />
        </div>
        {#if editingId}
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox bind:checked={form.active} />
                Stand in Betrieb
            </Label>
        {/if}
        <div class="flex gap-2">
            <Button type="submit" disabled={form.processing}>Speichern</Button>
            <Button
                type="button"
                variant="ghost"
                onclick={() => (editingId = null)}>Abbrechen</Button
            >
        </div>
    </form>
{/snippet}

<div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Stände"
            description="Die Stände Ihrer Anlage mit Anzahl der Bahnen und zugelassenen Waffenarten."
        />
        {#if editingId === null}
            <Button onclick={() => open(null)}>
                <Plus class="size-4" />
                Stand anlegen
            </Button>
        {/if}
    </div>

    {#if editingId === 0}
        {@render editor()}
    {/if}

    {#if ranges.length === 0 && editingId === null}
        <p
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Noch keine Stände angelegt. Legen Sie zuerst Ihre Stände an, danach
            die Schießtage.
        </p>
    {:else}
        <ul class="grid gap-3">
            {#each ranges as range (range.id)}
                <li>
                    {#if editingId === range.id}
                        {@render editor()}
                    {:else}
                        <div
                            class="flex items-center gap-4 rounded-lg border p-4 {range.active
                                ? ''
                                : 'opacity-60'}"
                        >
                            <div
                                class="flex size-12 shrink-0 flex-col items-center justify-center rounded-md bg-muted text-center leading-none"
                            >
                                <span class="text-lg font-semibold tabular-nums"
                                    >{range.lanes}</span
                                >
                                <span class="text-[10px] text-muted-foreground"
                                    >Bahnen</span
                                >
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">
                                    {range.name}
                                    {#if !range.active}
                                        <Badge variant="outline" class="ml-2"
                                            >außer Betrieb</Badge
                                        >
                                    {/if}
                                </p>
                                <p class="text-sm text-muted-foreground">
                                    {range.weapon_types
                                        .map((t) => labels[t])
                                        .join(', ')}{range.notes
                                        ? ` · ${range.notes}`
                                        : ''}
                                </p>
                            </div>
                            <Button
                                variant="ghost"
                                size="icon"
                                aria-label="{range.name} bearbeiten"
                                onclick={() => open(range)}
                            >
                                <Pencil class="size-4" />
                            </Button>
                        </div>
                    {/if}
                </li>
            {/each}
        </ul>
    {/if}
</div>
