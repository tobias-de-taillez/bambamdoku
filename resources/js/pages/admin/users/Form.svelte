<script module lang="ts">
    import { index } from '@/routes/admin/users';

    export const layout = {
        breadcrumbs: [{ title: 'Mitglieder', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, page, router, useForm } from '@inertiajs/svelte';
    import Mail from '@lucide/svelte/icons/mail';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { toUrl } from '@/lib/utils';
    import { certificate, memberPdf } from '@/routes/admin/record';
    import { invite, store, update } from '@/routes/admin/users';
    import Download from '@lucide/svelte/icons/download';
    import type { AdminUser, GroupOption, RoleOption } from '@/types/admin';

    let {
        user,
        groups,
        roles,
    }: {
        user: AdminUser | null;
        groups: GroupOption[];
        roles: RoleOption[];
    } = $props();

    // svelte-ignore state_referenced_locally
    const editing = user !== null;
    // svelte-ignore state_referenced_locally
    const isSelf = user?.id === page.props.auth.user.id;

    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? 'member',
        member_number: user?.member_number ?? '',
        supervisor_until: user?.supervisor_until ?? '',
        member_since: user?.member_since ?? '',
        active: user?.active ?? true,
        groups: user?.groups.map((g) => g.id) ?? ([] as number[]),
        send_invitation: true,
    });

    function toggleGroup(id: number, checked: boolean) {
        form.groups = checked
            ? [...form.groups, id]
            : form.groups.filter((g) => g !== id);
    }

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            member_number: data.member_number || null,
            supervisor_until: data.supervisor_until || null,
            member_since: data.member_since || null,
        }));

        if (user) {
            form.put(toUrl(update(user.id)));
        } else {
            form.post(toUrl(store()));
        }
    }

    function resendInvitation() {
        if (user) {
            router.post(toUrl(invite(user.id)));
        }
    }

    // svelte-ignore state_referenced_locally
    const title = editing ? (user?.name ?? "") : "Mitglied anlegen";
</script>

<AppHead {title} />

<div class="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
    <Heading
        {title}
        description={editing
            ? 'Stammdaten, Rolle und Aufsichts-Qualifikation'
            : 'Das Mitglied bekommt einen Einladungslink und legt sein Passwort selbst fest.'}
    />

    {#if user}
        <div class="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" asChild>
                {#snippet children(props)}
                    <a href={toUrl(memberPdf(user.id))} class={props.class}
                        ><Download class="size-4" /> Auszug Schießkladde</a
                    >
                {/snippet}
            </Button>
            <Button variant="outline" size="sm" asChild>
                {#snippet children(props)}
                    <a href={toUrl(certificate(user.id))} class={props.class}
                        ><Download class="size-4" /> Mitgliedschaftsbescheinigung</a
                    >
                {/snippet}
            </Button>
        </div>
    {/if}

    <form class="flex flex-col gap-6" onsubmit={submit} novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    bind:value={form.name}
                    required
                    autocomplete="off"
                />
                <InputError message={form.errors.name} />
            </div>
            <div class="grid gap-2">
                <Label for="email">E-Mail-Adresse</Label>
                <Input
                    id="email"
                    type="email"
                    bind:value={form.email}
                    required
                    autocomplete="off"
                />
                <InputError message={form.errors.email} />
            </div>
            <div class="grid gap-2">
                <Label for="member_number">Mitgliedsnummer</Label>
                <Input
                    id="member_number"
                    bind:value={form.member_number}
                    placeholder="optional"
                />
                <InputError message={form.errors.member_number} />
            </div>
            <div class="grid gap-2">
                <Label for="member_since">Mitglied seit</Label>
                <Input
                    id="member_since"
                    type="date"
                    bind:value={form.member_since}
                />
                <InputError message={form.errors.member_since} />
            </div>
            <div class="grid gap-2">
                <Label for="role">Rolle</Label>
                <select
                    id="role"
                    bind:value={form.role}
                    disabled={isSelf}
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                >
                    {#each roles as role (role.value)}
                        <option value={role.value}>{role.label}</option>
                    {/each}
                </select>
                <InputError message={form.errors.role} />
            </div>
        </div>

        <fieldset class="grid gap-2 rounded-lg border p-4">
            <legend class="px-1 text-sm font-medium">Standaufsicht</legend>
            <p class="text-sm text-muted-foreground">
                Nur Mitglieder mit gültiger Qualifikation können als
                verantwortliche Aufsichtsperson (§ 27 WaffG, § 10 AWaffV)
                eingeteilt werden.
            </p>
            <Label for="supervisor_until" class="mt-2">Qualifikation gültig bis</Label>
            <Input
                id="supervisor_until"
                type="date"
                bind:value={form.supervisor_until}
                class="max-w-48"
            />
            <InputError message={form.errors.supervisor_until} />
        </fieldset>

        {#if groups.length > 0}
            <fieldset class="grid gap-3">
                <legend class="mb-2 text-sm font-medium">Gruppen</legend>
                <div class="grid gap-2 sm:grid-cols-2">
                    {#each groups as group (group.id)}
                        <Label
                            class="flex items-center gap-3 rounded-md border px-3 py-2 font-normal"
                        >
                            <Checkbox
                                checked={form.groups.includes(group.id)}
                                onclick={() =>
                                    toggleGroup(
                                        group.id,
                                        !form.groups.includes(group.id),
                                    )}
                            />
                            {group.name}
                        </Label>
                    {/each}
                </div>
                <InputError message={form.errors.groups} />
            </fieldset>
        {/if}

        {#if editing}
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox
                    bind:checked={form.active}
                    disabled={isSelf}
                />
                Zugang aktiv
                <span class="text-muted-foreground"
                    >– deaktivierte Mitglieder können sich nicht anmelden</span
                >
            </Label>
        {:else}
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox bind:checked={form.send_invitation} />
                Einladung sofort per E-Mail senden
            </Label>
        {/if}

        <div class="flex flex-wrap items-center gap-3 border-t pt-6">
            <Button type="submit" disabled={form.processing}>
                {#if form.processing}<Spinner />{/if}
                {editing ? 'Speichern' : 'Anlegen und einladen'}
            </Button>
            <Button variant="ghost" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(index())} class={props.class}
                        >Abbrechen</Link
                    >
                {/snippet}
            </Button>
            {#if editing && user && !user.has_logged_in && user.active}
                <Button
                    type="button"
                    variant="outline"
                    class="ml-auto"
                    onclick={resendInvitation}
                >
                    <Mail class="size-4" />
                    Einladung erneut senden
                </Button>
            {/if}
        </div>
    </form>
</div>
