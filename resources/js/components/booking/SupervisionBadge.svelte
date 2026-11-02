<script lang="ts">
    import ShieldAlert from '@lucide/svelte/icons/shield-alert';
    import ShieldCheck from '@lucide/svelte/icons/shield-check';
    import ShieldHalf from '@lucide/svelte/icons/shield-half';
    import type { SupervisionState } from '@/types/booking';

    let {
        state,
        names = [],
    }: { state: SupervisionState; names?: string[] } = $props();
</script>

{#if state === 'full'}
    <span
        class="inline-flex items-center gap-1.5 text-sm text-emerald-700 dark:text-emerald-400"
    >
        <ShieldCheck class="size-4 shrink-0" />
        Aufsicht: {names.join(', ')}
    </span>
{:else if state === 'partial'}
    <span
        class="inline-flex items-center gap-1.5 text-sm text-amber-700 dark:text-amber-400"
    >
        <ShieldHalf class="size-4 shrink-0" />
        Aufsicht nur zeitweise ({names.join(', ')})
    </span>
{:else}
    <span
        class="inline-flex items-center gap-1.5 text-sm text-red-700 dark:text-red-400"
    >
        <ShieldAlert class="size-4 shrink-0" />
        Noch keine Standaufsicht
    </span>
{/if}
