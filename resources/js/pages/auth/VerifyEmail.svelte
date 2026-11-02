<script module lang="ts">
    export const layout = {
        title: 'E-Mail-Adresse bestätigen',
        description:
            'Bitte bestätigen Sie Ihre E-Mail-Adresse über den Link, den wir Ihnen soeben per E-Mail gesendet haben.',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Spinner } from '@/components/ui/spinner';
    import { logout } from '@/routes';
    import { send } from '@/routes/verification';

    let {
        status = '',
    }: {
        status?: string;
    } = $props();
</script>

<AppHead title="E-Mail-Adresse bestätigen" />

{#if status === 'verification-link-sent'}
    <div class="mb-4 text-center text-sm font-medium text-green-600">
        Ein neuer Bestätigungslink wurde an die bei der Registrierung angegebene
        E-Mail-Adresse gesendet.
    </div>
{/if}

<Form {...send.form()} class="space-y-6 text-center">
    {#snippet children({ processing })}
        <Button type="submit" disabled={processing} variant="secondary">
            {#if processing}<Spinner />{/if}
            Bestätigungs-E-Mail erneut senden
        </Button>

        <TextLink href={logout()} as="button" class="mx-auto block text-sm">
            Abmelden
        </TextLink>
    {/snippet}
</Form>
