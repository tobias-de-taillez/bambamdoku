<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { Button } from '@/components/ui/button';
    import { toUrl } from '@/lib/utils';
    import { imprint, login, privacy, register } from '@/routes';

    let {
        address = null,
        canRegister = false,
    }: {
        address?: string | null;
        canRegister?: boolean;
    } = $props();

    // Aufgenommen mit den Demo-Daten: npm run screenshots:startseite
    const demos = [
        {
            name: 'kiosk',
            width: 672,
            height: 498,
            alt: 'Kiosk-Tablet am Stand: ein Mitglied wählt Kategorie und Waffe, trägt die Schüsse ein und tippt auf „Erfassen“.',
            caption:
                'Am Stand tragen Mitglieder selbst ein, was sie schießen: am Kiosk-Tablet mit eigener PIN oder am eigenen Handy. Die Aufsicht zeichnet am Ende ab.',
        },
        {
            name: 'planung',
            width: 896,
            height: 666,
            alt: 'Ansicht des Vorstands: Liste der kommenden Schießtage mit Uhrzeit, Ständen, eingeteilter Aufsicht und Zahl der Buchungen.',
            caption:
                'Der Vorstand plant die Schießtage und sieht bei jedem Termin, wer Aufsicht hat und wie viele gebucht haben.',
        },
    ];
</script>

<AppHead title="Startseite" />

<div class="flex min-h-svh flex-col bg-background text-foreground">
    <main class="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-14 px-4 py-10 md:py-16">
        <header class="flex flex-col gap-6">
            <div class="flex items-center gap-4">
                <AppLogoIcon class="size-12 shrink-0 text-(--foreground) dark:text-white" />
                <div class="min-w-0">
                    <h1 class="text-3xl font-semibold tracking-tight break-words">
                        {page.props.club}
                    </h1>
                    {#if address}
                        <p class="text-muted-foreground">{address}</p>
                    {/if}
                </div>
            </div>
            <p class="max-w-prose text-lg leading-relaxed">
                Hier buchen Mitglieder ihre Bahn und führen die Schießkladde des Vereins.
            </p>
            <div class="flex flex-wrap gap-3">
                <Button size="lg" asChild>
                    {#snippet children(props)}
                        <Link href={toUrl(login())} class={props.class}>Anmelden</Link>
                    {/snippet}
                </Button>
                {#if canRegister}
                    <Button size="lg" variant="outline" asChild>
                        {#snippet children(props)}
                            <Link href={toUrl(register())} class={props.class}
                                >Mitgliedschaft beantragen</Link
                            >
                        {/snippet}
                    </Button>
                {/if}
            </div>
        </header>

        <section aria-labelledby="demo" class="flex flex-col gap-8">
            <div class="space-y-1">
                <h2 id="demo" class="text-xl font-semibold tracking-tight">So sieht es aus</h2>
                <p class="text-sm text-muted-foreground">Bilder mit Demo-Daten.</p>
            </div>
            {#each demos as demo, i (demo.name)}
                <figure class="flex flex-col gap-3">
                    <div
                        class="overflow-hidden rounded-xl border bg-card shadow-[0_8px_24px_-12px_rgb(0_0_0/0.25)]"
                    >
                        <img
                            src={`/images/startseite/${demo.name}-hell.webp`}
                            width={demo.width}
                            height={demo.height}
                            alt={demo.alt}
                            loading={i === 0 ? 'eager' : 'lazy'}
                            decoding="async"
                            class="block h-auto w-full dark:hidden"
                        />
                        <img
                            src={`/images/startseite/${demo.name}-dunkel.webp`}
                            width={demo.width}
                            height={demo.height}
                            alt={demo.alt}
                            loading={i === 0 ? 'eager' : 'lazy'}
                            decoding="async"
                            class="hidden h-auto w-full dark:block"
                        />
                    </div>
                    <figcaption class="max-w-prose text-sm leading-relaxed text-muted-foreground">
                        {demo.caption}
                    </figcaption>
                </figure>
            {/each}
        </section>
    </main>

    <footer class="border-t">
        <div
            class="mx-auto flex w-full max-w-3xl flex-wrap items-center gap-x-5 gap-y-1 px-4 py-6 text-sm text-muted-foreground"
        >
            <Link href={toUrl(privacy())} class="underline-offset-4 hover:underline"
                >Datenschutzhinweis</Link
            >
            {#if page.props.hasImprint}
                <Link href={toUrl(imprint())} class="underline-offset-4 hover:underline"
                    >Impressum</Link
                >
            {/if}
            <span>bambamdoku ist Open Source</span>
        </div>
    </footer>
</div>
