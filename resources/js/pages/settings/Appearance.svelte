<script module lang="ts">
    import { edit as editAppearance } from '@/routes/appearance';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Darstellung',
                href: editAppearance(),
            },
        ],
    };
</script>

<script lang="ts">
    import AppearanceTabs from '@/components/AppearanceTabs.svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
</script>

<AppHead title="Darstellung" />

<h1 class="sr-only">Darstellung</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Darstellung"
        description="Passen Sie die Darstellung der Anwendung an."
    />
    <AppearanceTabs />
</div>
