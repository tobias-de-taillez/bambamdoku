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

    const steps = [
        {
            title: 'Bahn buchen',
            text: 'Schießtag aussuchen und eine freie Bahn buchen, am Handy oder zu Hause.',
        },
        {
            title: 'Aufsicht startet die Session',
            text: 'Am Stand startet die Standaufsicht die Session und aktiviert, wer heute schießt.',
        },
        {
            title: 'Selbst eintragen',
            text: 'Mitglieder tragen ihren Schießbesuch selbst ein: auf dem eigenen Handy oder am Kiosk mit eigener PIN. Gäste trägt die Aufsicht ein.',
        },
        {
            title: 'Aufsicht zeichnet ab',
            text: 'Erst wenn die Aufsicht abzeichnet, steht der Eintrag in der Schießkladde des Vereins.',
        },
    ];
</script>

<AppHead title="Startseite" />

<div class="flex min-h-svh flex-col bg-background text-foreground">
    <main class="mx-auto flex w-full max-w-2xl flex-1 flex-col gap-10 px-4 py-10 md:py-16">
        <header class="flex flex-col gap-4">
            <AppLogoIcon class="size-10 text-(--foreground) dark:text-white" />
            <div class="space-y-1">
                <h1 class="text-2xl font-semibold tracking-tight break-words">
                    {page.props.club}
                </h1>
                {#if address}
                    <p class="text-sm text-muted-foreground">{address}</p>
                {/if}
            </div>
            <p class="leading-relaxed">
                Bahnbuchung und digitale Schießkladde des Vereins: Schießtage planen, Bahnen
                buchen und jeden Schießbesuch am Stand erfassen.
            </p>
            <div class="flex flex-wrap gap-2">
                <Button asChild>
                    {#snippet children(props)}
                        <Link href={toUrl(login())} class={props.class}>Anmelden</Link>
                    {/snippet}
                </Button>
                {#if canRegister}
                    <Button variant="outline" asChild>
                        {#snippet children(props)}
                            <Link href={toUrl(register())} class={props.class}
                                >Mitgliedschaft beantragen</Link
                            >
                        {/snippet}
                    </Button>
                {/if}
            </div>
        </header>

        <section aria-labelledby="ablauf" class="flex flex-col gap-4">
            <h2 id="ablauf" class="text-lg font-semibold">So läuft ein Schießtag</h2>
            <ol class="grid gap-3">
                {#each steps as step, i (step.title)}
                    <li class="flex gap-3 rounded-lg border bg-card p-4 text-card-foreground">
                        <span
                            class="flex size-7 shrink-0 items-center justify-center rounded-full bg-primary text-sm font-medium text-primary-foreground"
                            aria-hidden="true">{i + 1}</span
                        >
                        <div class="space-y-1">
                            <h3 class="font-medium">{step.title}</h3>
                            <p class="text-sm text-muted-foreground">{step.text}</p>
                        </div>
                    </li>
                {/each}
            </ol>
            <p class="text-sm leading-relaxed text-muted-foreground">
                Aus der Kladde zieht der Verein den Auszug für den Schießsportverband. Bescheinigen
                muss der Verband.
            </p>
        </section>
    </main>

    <footer class="border-t">
        <div
            class="mx-auto flex w-full max-w-2xl flex-wrap items-center gap-x-4 gap-y-1 px-4 py-6 text-xs text-muted-foreground"
        >
            <Link href={toUrl(privacy())} class="underline-offset-4 hover:underline"
                >Datenschutzhinweis</Link
            >
            {#if page.props.hasImprint}
                <Link href={toUrl(imprint())} class="underline-offset-4 hover:underline"
                    >Impressum</Link
                >
            {/if}
            <span>Open Source (Apache-2.0), selbst betrieben vom Verein</span>
        </div>
    </footer>
</div>
