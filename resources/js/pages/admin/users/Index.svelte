<script module lang="ts">
    import { index } from '@/routes/admin/users';

    export const layout = {
        breadcrumbs: [{ title: 'Mitglieder', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Check from '@lucide/svelte/icons/check';
    import Copy from '@lucide/svelte/icons/copy';
    import Plus from '@lucide/svelte/icons/plus';
    import Search from '@lucide/svelte/icons/search';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Badge } from '@/components/ui/badge';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { formatDate, roleLabels } from '@/lib/format';
    import { toUrl } from '@/lib/utils';
    import { create, edit, importMethod as importPage } from '@/routes/admin/users';
    import Upload from '@lucide/svelte/icons/upload';
    import type { AdminUser } from '@/types/admin';

    let {
        users,
        filters,
        invitation = null,
    }: {
        users: AdminUser[];
        filters: { search: string; status: string };
        invitation?: { name: string; url: string; mailed: boolean } | null;
    } = $props();

    // svelte-ignore state_referenced_locally
    let search = $state(filters.search);
    let copied = $state(false);

    const statuses = [
        { value: 'active', label: 'Aktiv' },
        { value: 'supervisors', label: 'Aufsichten' },
        { value: 'inactive', label: 'Deaktiviert' },
        { value: 'all', label: 'Alle' },
    ];

    function applyFilters(status = filters.status) {
        router.get(
            toUrl(index()),
            { search: search || undefined, status },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    }

    let timer: ReturnType<typeof setTimeout> | undefined;
    function onSearchInput() {
        clearTimeout(timer);
        timer = setTimeout(() => applyFilters(), 250);
    }

    async function copyInvitation() {
        if (!invitation) {
            return;
        }

        await navigator.clipboard.writeText(invitation.url);
        copied = true;
        setTimeout(() => (copied = false), 2000);
    }
</script>

<AppHead title="Mitglieder" />

<div class="flex flex-col gap-6 p-4 md:p-6">
    <div class="flex flex-wrap items-start justify-between gap-4">
        <Heading
            title="Mitglieder"
            description="Zugänge, Rollen und Aufsichts-Qualifikationen verwalten"
        />
        <div class="flex gap-2">
            <Button variant="outline" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(importPage())} class={props.class}>
                        <Upload class="size-4" />
                        Importieren
                    </Link>
                {/snippet}
            </Button>
            <Button asChild>
                {#snippet children(props)}
                    <Link href={toUrl(create())} class={props.class}>
                        <Plus class="size-4" />
                        Mitglied anlegen
                    </Link>
                {/snippet}
            </Button>
        </div>
    </div>

    {#if invitation}
        <div
            class="rounded-lg border border-emerald-600/30 bg-emerald-50 p-4 text-sm dark:bg-emerald-950/40"
            role="status"
        >
            <p class="font-medium">
                Einladungslink für {invitation.name}
                {invitation.mailed
                    ? 'wurde per E-Mail verschickt.'
                    : 'wurde erstellt.'}
            </p>
            <p class="mt-1 text-muted-foreground">
                Sie können den Link auch direkt weitergeben. Er ist 7 Tage
                gültig und funktioniert nur einmal.
            </p>
            <div class="mt-3 flex gap-2">
                <Input
                    readonly
                    value={invitation.url}
                    class="font-mono text-xs"
                    aria-label="Einladungslink"
                    onfocus={(event: FocusEvent) =>
                        (event.currentTarget as HTMLInputElement).select()}
                />
                <Button variant="outline" onclick={copyInvitation}>
                    {#if copied}
                        <Check class="size-4" /> Kopiert
                    {:else}
                        <Copy class="size-4" /> Kopieren
                    {/if}
                </Button>
            </div>
        </div>
    {/if}

    <div class="flex flex-wrap items-center gap-3">
        <div class="relative w-full max-w-sm">
            <Search
                class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
                type="search"
                placeholder="Name, E-Mail oder Mitgliedsnummer"
                class="pl-9"
                bind:value={search}
                oninput={onSearchInput}
                aria-label="Mitglieder durchsuchen"
            />
        </div>
        <div
            class="inline-flex rounded-lg bg-muted p-1"
            role="tablist"
            aria-label="Status"
        >
            {#each statuses as status (status.value)}
                <button
                    type="button"
                    role="tab"
                    aria-selected={filters.status === status.value}
                    class="rounded-md px-3 py-1.5 text-sm transition-colors {filters.status ===
                    status.value
                        ? 'bg-background font-medium shadow-sm'
                        : 'text-muted-foreground hover:text-foreground'}"
                    onclick={() => applyFilters(status.value)}
                >
                    {status.label}
                </button>
            {/each}
        </div>
    </div>

    {#if users.length === 0}
        <div
            class="rounded-lg border border-dashed p-10 text-center text-sm text-muted-foreground"
        >
            {filters.search
                ? `Keine Mitglieder für „${filters.search}“ gefunden.`
                : 'Keine Mitglieder in dieser Ansicht.'}
        </div>
    {:else}
        <div class="overflow-x-auto rounded-lg border">
            <table class="w-full text-sm">
                <thead class="bg-muted/50 text-left text-muted-foreground">
                    <tr>
                        <th class="px-4 py-2.5 font-medium">Name</th>
                        <th class="hidden px-4 py-2.5 font-medium md:table-cell"
                            >Mitgliedsnr.</th
                        >
                        <th class="px-4 py-2.5 font-medium">Rolle</th>
                        <th class="hidden px-4 py-2.5 font-medium lg:table-cell"
                            >Gruppen</th
                        >
                        <th class="hidden px-4 py-2.5 font-medium sm:table-cell"
                            >Standaufsicht</th
                        >
                    </tr>
                </thead>
                <tbody class="divide-y">
                    {#each users as user (user.id)}
                        <tr
                            class="hover:bg-muted/30 {user.active
                                ? ''
                                : 'text-muted-foreground'}"
                        >
                            <td class="px-4 py-3">
                                <Link
                                    href={toUrl(edit(user.id))}
                                    class="font-medium hover:underline"
                                >
                                    {user.name}
                                </Link>
                                <div class="text-xs text-muted-foreground">
                                    {user.email}
                                </div>
                                {#if !user.active}
                                    <Badge variant="outline" class="mt-1"
                                        >Deaktiviert</Badge
                                    >
                                {:else if !user.has_logged_in}
                                    <Badge variant="secondary" class="mt-1"
                                        >Einladung offen</Badge
                                    >
                                {/if}
                            </td>
                            <td
                                class="hidden px-4 py-3 tabular-nums md:table-cell"
                            >
                                {user.member_number ?? '–'}
                            </td>
                            <td class="px-4 py-3">
                                <Badge
                                    variant={user.role === 'admin'
                                        ? 'default'
                                        : 'outline'}
                                >
                                    {roleLabels[user.role]}
                                </Badge>
                            </td>
                            <td class="hidden px-4 py-3 lg:table-cell">
                                {user.groups.map((g) => g.name).join(', ') ||
                                    '–'}
                            </td>
                            <td class="hidden px-4 py-3 sm:table-cell">
                                {#if user.can_supervise}
                                    <span
                                        class="inline-flex items-center gap-1.5 text-emerald-700 dark:text-emerald-400"
                                    >
                                        <ShieldCheck class="size-4" />
                                        bis {formatDate(user.supervisor_until)}
                                    </span>
                                {:else if user.supervisor_until}
                                    <span class="text-destructive"
                                        >abgelaufen {formatDate(
                                            user.supervisor_until,
                                        )}</span
                                    >
                                {:else}
                                    <span class="text-muted-foreground">–</span>
                                {/if}
                            </td>
                        </tr>
                    {/each}
                </tbody>
            </table>
        </div>
        <p class="text-xs text-muted-foreground">
            {users.length}
            {users.length === 1 ? 'Eintrag' : 'Einträge'}
        </p>
    {/if}
</div>
