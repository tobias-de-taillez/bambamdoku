<script module lang="ts">
    export const layout = {
        title: 'Passwort bestätigen',
        description:
            'Dies ist ein geschützter Bereich. Bitte bestätigen Sie Ihr Passwort, um fortzufahren.',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { store } from '@/routes/password/confirm';
    import {
        index as confirmOptions,
        store as confirmStore,
    } from '@/actions/Laravel/Passkeys/Http/Controllers/PasskeyConfirmationController';
    import PasskeyVerify from '@/components/PasskeyVerify.svelte';
</script>

<AppHead title="Passwort bestätigen" />

<PasskeyVerify
    routes={{
        options: confirmOptions(),
        submit: confirmStore(),
    }}
    label="Mit Passkey bestätigen"
    loadingLabel="Wird bestätigt …"
    separator="Oder mit Passwort bestätigen"
/>

<Form {...store.form()} resetOnSuccess>
    {#snippet children({ errors, processing })}
        <div class="space-y-6">
            <div class="grid gap-2">
                <Label for="password">Passwort</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    required
                    autocomplete="current-password"
                />
                <InputError message={errors.password} />
            </div>

            <div class="flex items-center">
                <Button
                    type="submit"
                    class="w-full"
                    disabled={processing}
                    data-test="confirm-password-button"
                >
                    {#if processing}<Spinner />{/if}
                    Passwort bestätigen
                </Button>
            </div>
        </div>
    {/snippet}
</Form>
