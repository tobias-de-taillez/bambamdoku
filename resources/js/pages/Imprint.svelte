<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { home } from '@/routes';

    let { text }: { text: string } = $props();

    const loggedIn = $derived(Boolean(page.props.auth?.user));
</script>

<AppHead title="Impressum" />

<main class="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-10 text-sm leading-relaxed">
    <header class="space-y-1">
        <h1 class="text-2xl font-semibold tracking-tight">Impressum</h1>
        <p class="text-muted-foreground">{page.props.club}</p>
    </header>

    <p class="whitespace-pre-line">{text}</p>

    <p>
        <Link href={toUrl(home())} class="underline underline-offset-4"
            >{loggedIn ? 'Zurück zur Übersicht' : 'Zur Startseite'}</Link
        >
    </p>
</main>
