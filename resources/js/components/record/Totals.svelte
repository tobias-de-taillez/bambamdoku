<script lang="ts">
    import type { RecordTotal } from '@/types/record';

    let { totals }: { totals: RecordTotal[] } = $props();
</script>

<div class="grid gap-3 sm:grid-cols-3">
    {#each totals as t (t.class)}
        <div class="rounded-lg border p-4">
            <p class="text-sm text-muted-foreground">{t.label}</p>
            <p class="mt-1 text-2xl font-semibold tabular-nums">
                {t.days} <span class="text-sm font-normal text-muted-foreground">Tage</span>
            </p>
            {#if t.class !== 'exempt'}
                <p class="text-sm text-muted-foreground tabular-nums">
                    davon {t.own_days} mit eigener Waffe
                </p>
            {/if}
        </div>
    {/each}
</div>
