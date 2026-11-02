<script module lang="ts">
    import { dashboard } from '@/routes';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Übersicht',
                href: dashboard(),
            },
        ],
    };
</script>

<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
</script>

<AppHead title="Übersicht" />

<div class="flex flex-1 flex-col gap-2 p-4">
    <h1 class="text-xl font-semibold">Hallo, {page.props.auth.user.name}</h1>
    <p class="text-muted-foreground">
        bambamdoku läuft. Schießtage, Standbuchung und Schießbuch folgen.
    </p>
</div>
