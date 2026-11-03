<script module lang="ts">
    import { index } from '@/routes/admin/audit';

    export const layout = {
        breadcrumbs: [{ title: 'Protokoll', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, router } from '@inertiajs/svelte';
    import Search from '@lucide/svelte/icons/search';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import { Input } from '@/components/ui/input';
    import { toUrl } from '@/lib/utils';

    type Entry = {
        id: number;
        at: string;
        actor: string | null;
        action: string;
        summary: string;
    };

    let {
        entries,
        search,
    }: {
        entries: {
            data: Entry[];
            prev_page_url: string | null;
            next_page_url: string | null;
            from: number | null;
            to: number | null;
            total: number;
        };
        search: string;
    } = $props();

    // svelte-ignore state_referenced_locally
    let query = $state(search);
    let timer: ReturnType<typeof setTimeout> | undefined;

    function onInput() {
        clearTimeout(timer);
        timer = setTimeout(
            () =>
                router.get(
                    toUrl(index()),
                    { search: query || undefined },
                    { preserveState: true, replace: true },
                ),
            300,
        );
    }

    const formatter = new Intl.DateTimeFormat('de-DE', {
        dateStyle: 'medium',
        timeStyle: 'short',
    });

    const icons: Record<string, string> = {
        'booking.created': 'Buchung',
        'help_request.deleted': 'Rückmeldung',
        'booking.cancelled': 'Storno',
        'booking.checked_in': 'Aktiviert',
        'booking.no_show': 'Fehlt',
        'booking.reset': 'Korrektur',
        'booking.arrived': 'Ist da',
        'session.opened': 'Session',
        'session.signed': 'Abgezeichnet',
        'supervision.assigned': 'Aufsicht',
        'supervision.removed': 'Aufsicht',
        'qualification.registered': 'Register',
        'application.admitted': 'Aufnahme',
        'application.email_confirmed': 'E-Mail des Antrags bestätigt',
        'application.rejected': 'Antrag',
        'application.purged': 'Antrag',
        'user.created': 'Mitglied',
        'user.updated': 'Mitglied',
        'user.adult_confirmed': 'Volljährig',
        'user.kiosk_pin_set': 'Kiosk-PIN',
        'user.kiosk_pin_removed': 'Kiosk-PIN',
        'shooting_day.cancelled': 'Absage',
        'shooting_day.created': 'Termin',
        'shooting_day.updated': 'Termin',
        'shooting_day.reopened': 'Termin',
        'shooting_day.deleted': 'Termin',
        'shooting_day.imported': 'Import',
        'user.imported': 'Import',
        'range.created': 'Stand',
        'range.updated': 'Stand',
        'settings.updated': 'Einstellung',
        // Altlast: werden nicht mehr geschrieben, stehen aber in alten Einträgen
        'group.created': 'Gruppe',
        'group.updated': 'Gruppe',
        'group.deleted': 'Gruppe',
    };
</script>

<AppHead title="Protokoll" />

<div class="flex max-w-4xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Protokoll"
        description="Wer hat wann gebucht, bestätigt, storniert oder Aufsicht übernommen. Einträge lassen sich nicht ändern."
    />

    <div class="relative max-w-sm">
        <Search
            class="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground"
        />
        <Input
            type="search"
            bind:value={query}
            oninput={onInput}
            placeholder="Name, Datum oder Stand"
            class="pl-9"
            aria-label="Protokoll durchsuchen"
        />
    </div>

    {#if entries.data.length === 0}
        <p
            class="rounded-lg border border-dashed p-8 text-center text-sm text-muted-foreground"
        >
            Keine Einträge.
        </p>
    {:else}
        <ol class="divide-y rounded-lg border">
            {#each entries.data as entry (entry.id)}
                <li class="grid gap-1 p-3 text-sm sm:grid-cols-[9rem_6rem_1fr] sm:gap-3">
                    <time class="text-muted-foreground tabular-nums" datetime={entry.at}
                        >{formatter.format(new Date(entry.at))}</time
                    >
                    <span class="font-medium">{icons[entry.action] ?? entry.action}</span>
                    <span>
                        {entry.summary}
                        <span class="text-muted-foreground">· {entry.actor ?? 'System'}</span>
                    </span>
                </li>
            {/each}
        </ol>
        <div class="flex items-center justify-between text-sm text-muted-foreground">
            <span>{entries.from}–{entries.to} von {entries.total}</span>
            <div class="flex gap-4">
                {#if entries.prev_page_url}
                    <Link href={entries.prev_page_url} preserveScroll class="hover:text-foreground"
                        >Neuere</Link
                    >
                {/if}
                {#if entries.next_page_url}
                    <Link href={entries.next_page_url} preserveScroll class="hover:text-foreground"
                        >Ältere</Link
                    >
                {/if}
            </div>
        </div>
    {/if}
</div>
