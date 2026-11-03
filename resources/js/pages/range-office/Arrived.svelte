<script lang="ts">
    import { Link } from '@inertiajs/svelte';
    import CircleAlert from '@lucide/svelte/icons/circle-alert';
    import CircleCheck from '@lucide/svelte/icons/circle-check';
    import Clock from '@lucide/svelte/icons/clock';
    import AppHead from '@/components/AppHead.svelte';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';
    import { dashboard } from '@/routes';

    let {
        state,
        booking,
    }: {
        state: 'arrived' | 'confirmed' | 'no_booking' | 'expired';
        booking: { range: string; starts_at: string; ends_at: string } | null;
    } = $props();
</script>

<AppHead title="Am Stand anmelden" />

<div class="flex min-h-[60vh] flex-col items-center justify-center gap-4 p-6 text-center">
    {#if state === 'arrived'}
        <Clock class="size-16 text-sky-600" />
        <h1 class="text-2xl font-semibold">Sie sind gemeldet</h1>
        <p class="max-w-sm text-muted-foreground">
            {booking?.range}, {booking?.starts_at}–{booking?.ends_at} Uhr. Die Standaufsicht
            sieht, dass Sie da sind, und aktiviert Sie für ihre Session. Danach tragen Sie unter
            „Heute schießen“ ein; in die Kladde kommt der Termin, wenn die Aufsicht abzeichnet.
        </p>
    {:else if state === 'confirmed'}
        <CircleCheck class="size-16 text-emerald-600" />
        <h1 class="text-2xl font-semibold">Für die Session aktiviert</h1>
        <p class="max-w-sm text-muted-foreground">
            {booking?.range}, {booking?.starts_at}–{booking?.ends_at} Uhr. Tragen Sie unter „Heute
            schießen“ ein, was Sie schießen. Gut Schuss!
        </p>
    {:else if state === 'no_booking'}
        <CircleAlert class="size-16 text-amber-600" />
        <h1 class="text-2xl font-semibold">Keine passende Buchung</h1>
        <p class="max-w-sm text-muted-foreground">
            Für Sie gibt es jetzt keine Buchung an diesem Schießtag. Bitte melden Sie sich
            bei der Standaufsicht, sie kann Sie direkt für ihre Session aktivieren.
        </p>
    {:else}
        <CircleAlert class="size-16 text-amber-600" />
        <h1 class="text-2xl font-semibold">Code abgelaufen</h1>
        <p class="max-w-sm text-muted-foreground">
            Der QR-Code wechselt alle paar Minuten. Bitte scannen Sie den aktuellen Code am
            Stand noch einmal.
        </p>
    {/if}
    <Button variant="outline" asChild>
        {#snippet children(props)}
            <Link href={toUrl(dashboard())} class={props.class}>Zur Übersicht</Link>
        {/snippet}
    </Button>
</div>
