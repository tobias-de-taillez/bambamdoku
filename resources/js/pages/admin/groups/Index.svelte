<script module lang="ts">
    import { index } from '@/routes/admin/groups';

    export const layout = {
        breadcrumbs: [{ title: 'Gruppen', href: index() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import Pencil from '@lucide/svelte/icons/pencil';
    import Trash2 from '@lucide/svelte/icons/trash-2';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { toUrl } from '@/lib/utils';
    import { destroy, store, update } from '@/routes/admin/groups';

    type Group = {
        id: number;
        name: string;
        description: string | null;
        users_count: number;
    };

    let { groups }: { groups: Group[] } = $props();

    const createForm = useForm({ name: '', description: '' });
    const editForm = useForm({ name: '', description: '' });
    let editingId = $state<number | null>(null);
    let confirmingId = $state<number | null>(null);

    function create(event: SubmitEvent) {
        event.preventDefault();
        createForm.post(toUrl(store()), {
            preserveScroll: true,
            onSuccess: () => createForm.reset(),
        });
    }

    function startEdit(group: Group) {
        editingId = group.id;
        editForm.name = group.name;
        editForm.description = group.description ?? '';
        editForm.clearErrors();
    }

    function save(event: SubmitEvent, group: Group) {
        event.preventDefault();
        editForm.put(toUrl(update(group.id)), {
            preserveScroll: true,
            onSuccess: () => (editingId = null),
        });
    }

    function remove(group: Group) {
        router.delete(toUrl(destroy(group.id)), {
            preserveScroll: true,
            onFinish: () => (confirmingId = null),
        });
    }
</script>

<AppHead title="Gruppen" />

<div class="flex max-w-3xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Gruppen"
        description="Gruppen steuern, wer an welchen Schießtagen buchen darf, z. B. Jugend, Bogen oder Großkaliber."
    />

    <form
        class="grid gap-3 rounded-lg border p-4 sm:grid-cols-[1fr_2fr_auto] sm:items-end"
        onsubmit={create}
    >
        <div class="grid gap-2">
            <Label for="new-name">Neue Gruppe</Label>
            <Input id="new-name" bind:value={createForm.name} placeholder="Name" />
            <InputError message={createForm.errors.name} />
        </div>
        <div class="grid gap-2">
            <Label for="new-description">Beschreibung</Label>
            <Input
                id="new-description"
                bind:value={createForm.description}
                placeholder="optional"
            />
        </div>
        <Button type="submit" disabled={createForm.processing}>Anlegen</Button>
    </form>

    {#if groups.length === 0}
        <p
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Noch keine Gruppen. Ohne Gruppen sind Schießtage für alle Mitglieder
            offen.
        </p>
    {:else}
        <ul class="divide-y rounded-lg border">
            {#each groups as group (group.id)}
                <li class="p-4">
                    {#if editingId === group.id}
                        <form
                            class="grid gap-3 sm:grid-cols-[1fr_2fr_auto_auto] sm:items-start"
                            onsubmit={(event) => save(event, group)}
                        >
                            <div class="grid gap-1">
                                <Input
                                    bind:value={editForm.name}
                                    aria-label="Name"
                                />
                                <InputError message={editForm.errors.name} />
                            </div>
                            <Input
                                bind:value={editForm.description}
                                aria-label="Beschreibung"
                            />
                            <Button type="submit" disabled={editForm.processing}
                                >Speichern</Button
                            >
                            <Button
                                type="button"
                                variant="ghost"
                                onclick={() => (editingId = null)}
                                >Abbrechen</Button
                            >
                        </form>
                    {:else}
                        <div class="flex items-center gap-4">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{group.name}</p>
                                <p class="text-sm text-muted-foreground">
                                    {group.users_count}
                                    {group.users_count === 1
                                        ? 'Mitglied'
                                        : 'Mitglieder'}{group.description
                                        ? ` · ${group.description}`
                                        : ''}
                                </p>
                            </div>
                            {#if confirmingId === group.id}
                                <span class="text-sm">Wirklich löschen?</span>
                                <Button
                                    variant="destructive"
                                    size="sm"
                                    onclick={() => remove(group)}>Löschen</Button
                                >
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onclick={() => (confirmingId = null)}
                                    >Nein</Button
                                >
                            {:else}
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="{group.name} bearbeiten"
                                    onclick={() => startEdit(group)}
                                >
                                    <Pencil class="size-4" />
                                </Button>
                                <Button
                                    variant="ghost"
                                    size="icon"
                                    aria-label="{group.name} löschen"
                                    onclick={() => (confirmingId = group.id)}
                                >
                                    <Trash2 class="size-4" />
                                </Button>
                            {/if}
                        </div>
                    {/if}
                </li>
            {/each}
        </ul>
    {/if}
</div>
