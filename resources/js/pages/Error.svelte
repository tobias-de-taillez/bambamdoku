<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';

    let { status }: { status: number } = $props();

    const texts: Record<number, { title: string; description: string }> = {
        403: {
            title: 'Kein Zugriff',
            description: 'Für diese Seite fehlen Ihnen die Rechte.',
        },
        404: {
            title: 'Seite nicht gefunden',
            description: 'Diese Adresse gibt es nicht (mehr). Vielleicht ist der Link veraltet.',
        },
        429: {
            title: 'Zu viele Anfragen',
            description: 'Bitte warten Sie einen Moment und versuchen Sie es dann noch einmal.',
        },
        500: {
            title: 'Da ist etwas schiefgegangen',
            description: 'Ein Fehler auf dem Server. Bitte versuchen Sie es gleich noch einmal.',
        },
    };

    const text = $derived(texts[status] ?? texts[500]);
    const loggedIn = $derived(Boolean(page.props.auth?.user));
</script>

<AppHead title={text.title} />

<main class="mx-auto flex min-h-svh max-w-xl flex-col justify-center gap-4 px-4 py-10">
    <p class="text-sm font-medium text-muted-foreground">Fehler {status}</p>
    <h1 class="text-2xl font-semibold tracking-tight">{text.title}</h1>
    <p>{text.description}</p>
    <p class="text-sm text-muted-foreground">
        Wenn das nicht sein sollte: Über „Report Bug / Request Feature“ unten rechts können Sie es uns melden.
    </p>
    <p>
        <a href={loggedIn ? '/dashboard' : '/'} class="underline underline-offset-4"
            >{loggedIn ? 'Zur Übersicht' : 'Zur Startseite'}</a
        >
    </p>
    <p class="text-xs text-muted-foreground">
        <a href="/datenschutz" class="underline-offset-4 hover:underline">Datenschutzhinweis</a>
        {#if page.props.hasImprint}
            · <a href="/impressum" class="underline-offset-4 hover:underline">Impressum</a>
        {/if}
    </p>
</main>
