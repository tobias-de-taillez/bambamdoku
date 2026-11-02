<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppLogoIcon from '@/components/AppLogoIcon.svelte';

    const name = $derived(page.props.name);
    const club = $derived(page.props.club);
</script>

<div
    class="flex aspect-square size-8 items-center justify-center rounded-md bg-sidebar-primary text-sidebar-primary-foreground"
>
    <AppLogoIcon class="size-5" />
</div>
<div class="ml-1 grid flex-1 text-left text-sm">
    <span class="truncate leading-tight font-semibold">{club || name}</span>
    {#if club}
        <span class="truncate text-xs leading-tight text-muted-foreground">{name}</span>
    {/if}
</div>
