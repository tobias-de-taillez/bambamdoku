<script lang="ts">
    import type { GridRange, GridSlot } from '@/types/booking';

    let {
        grid,
        selected = null,
        disabled = false,
        onselect,
    }: {
        grid: GridRange[];
        selected?: { rangeId: number; start: string } | null;
        disabled?: boolean;
        onselect: (range: GridRange, slot: GridSlot) => void;
    } = $props();

    const times = $derived(grid[0]?.slots.map((s) => s.start) ?? []);

    function tone(range: GridRange, slot: GridSlot): string {
        if (slot.past) {
            return 'text-muted-foreground/60';
        }

        if (!slot.supervised) {
            return 'bg-[repeating-linear-gradient(135deg,transparent,transparent_4px,color-mix(in_oklab,var(--color-foreground)_12%,transparent)_4px,color-mix(in_oklab,var(--color-foreground)_12%,transparent)_8px)] text-muted-foreground';
        }

        if (slot.free === 0) {
            return 'bg-foreground/10 text-muted-foreground';
        }

        if (slot.free <= Math.max(1, Math.floor(range.lanes / 4))) {
            return 'bg-amber-100 text-amber-900 hover:bg-amber-200 dark:bg-amber-950 dark:text-amber-200';
        }

        return 'bg-emerald-100 text-emerald-900 hover:bg-emerald-200 dark:bg-emerald-950 dark:text-emerald-200';
    }

    function label(range: GridRange, slot: GridSlot): string {
        if (slot.past) {
            return `${range.name}, ${slot.start} Uhr: vorbei`;
        }

        if (!slot.supervised) {
            return `${range.name}, ${slot.start} Uhr: keine Aufsicht`;
        }

        if (slot.free === 0) {
            return `${range.name}, ${slot.start} Uhr: belegt`;
        }

        return `${range.name}, ${slot.start} Uhr: ${slot.free} von ${range.lanes} Bahnen frei`;
    }
</script>

<div class="overflow-x-auto rounded-lg border">
    <table class="w-full border-separate border-spacing-1 text-sm">
        <thead>
            <tr>
                <th
                    class="sticky left-0 z-10 bg-background px-2 py-1 text-left text-xs font-medium text-muted-foreground"
                    >Stand</th
                >
                {#each times as time (time)}
                    <th
                        class="min-w-14 px-1 py-1 text-center text-xs font-medium text-muted-foreground tabular-nums"
                        >{time}</th
                    >
                {/each}
            </tr>
        </thead>
        <tbody>
            {#each grid as range (range.id)}
                <tr>
                    <th
                        scope="row"
                        class="sticky left-0 z-10 bg-background px-2 py-1 text-left align-middle font-medium"
                    >
                        <span class="block max-w-40 truncate">{range.name}</span>
                        <span class="block text-xs font-normal text-muted-foreground"
                            >{range.lanes} Bahnen</span
                        >
                    </th>
                    {#each range.slots as slot (slot.start)}
                        {@const isSelected =
                            selected?.rangeId === range.id &&
                            selected?.start === slot.start}
                        <td class="p-0">
                            <button
                                type="button"
                                class="h-11 w-full rounded-md text-center font-medium tabular-nums transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:cursor-not-allowed {tone(
                                    range,
                                    slot,
                                )} {isSelected
                                    ? 'ring-2 ring-foreground ring-offset-1 ring-offset-background'
                                    : ''}"
                                disabled={disabled ||
                                    slot.past ||
                                    !slot.supervised ||
                                    slot.free === 0}
                                aria-label={label(range, slot)}
                                aria-pressed={isSelected}
                                title={label(range, slot)}
                                onclick={() => onselect(range, slot)}
                            >
                                {slot.past ? '·' : slot.supervised ? slot.free : '–'}
                            </button>
                        </td>
                    {/each}
                </tr>
            {/each}
        </tbody>
    </table>
</div>
<div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
    <span class="inline-flex items-center gap-1.5"
        ><span class="size-3 rounded-sm bg-emerald-200 dark:bg-emerald-900"></span>
        freie Bahnen</span
    >
    <span class="inline-flex items-center gap-1.5"
        ><span class="size-3 rounded-sm bg-amber-200 dark:bg-amber-900"></span> fast
        voll</span
    >
    <span class="inline-flex items-center gap-1.5"
        ><span class="size-3 rounded-sm bg-foreground/15"></span> belegt</span
    >
    <span class="inline-flex items-center gap-1.5"
        ><span
            class="size-3 rounded-sm border bg-[repeating-linear-gradient(135deg,transparent,transparent_2px,color-mix(in_oklab,var(--color-foreground)_25%,transparent)_2px,color-mix(in_oklab,var(--color-foreground)_25%,transparent)_4px)]"
        ></span> keine Aufsicht</span
    >
</div>
