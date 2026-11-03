<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import type { Snippet } from 'svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';
    import { toUrl } from '@/lib/utils';
    import { home, imprint, privacy } from '@/routes';

    let {
        title = '',
        description = '',
        children,
    }: {
        title?: string;
        description?: string;
        children?: Snippet;
    } = $props();
</script>

<div
    class="flex min-h-svh flex-col items-center justify-center gap-6 bg-background p-6 md:p-10"
>
    <div class="w-full max-w-sm">
        <div class="flex flex-col gap-8">
            <div class="flex flex-col items-center gap-4">
                <Link
                    href={home()}
                    class="flex flex-col items-center gap-2 font-medium"
                >
                    <div
                        class="mb-1 flex h-9 w-9 items-center justify-center rounded-md"
                    >
                        <AppLogoIcon
                            class="size-9 text-(--foreground) dark:text-white"
                        />
                    </div>
                    <span class="sr-only">Zur Startseite</span>
                </Link>
                <div class="space-y-2 text-center">
                    <h1 class="text-xl font-medium">{title}</h1>
                    <p class="text-center text-sm text-muted-foreground">
                        {description}
                    </p>
                </div>
            </div>
            {@render children?.()}
            <p class="text-center text-xs text-muted-foreground">
                <Link href={toUrl(home())} class="underline-offset-4 hover:underline"
                    >Startseite</Link
                >
                ·
                <Link href={toUrl(privacy())} class="underline-offset-4 hover:underline"
                    >Datenschutzhinweis</Link
                >
                {#if page.props.hasImprint}
                    ·
                    <Link href={toUrl(imprint())} class="underline-offset-4 hover:underline"
                        >Impressum</Link
                    >
                {/if}
            </p>
        </div>
    </div>
</div>
