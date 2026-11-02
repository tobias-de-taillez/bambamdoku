<script module lang="ts">
    export const layout = {
        title: 'Passwort zurücksetzen',
        description: 'Bitte geben Sie unten Ihr neues Passwort ein.',
    };
</script>

<script lang="ts">
    import { Form } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { update } from '@/routes/password';

    let {
        token,
        email,
        passwordRules,
    }: {
        token: string;
        email: string;
        passwordRules: string;
    } = $props();
</script>

<AppHead title="Passwort zurücksetzen" />

<Form
    {...update.form()}
    transform={(data) => ({ ...data, token, email })}
    resetOnSuccess={['password', 'password_confirmation']}
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="email">E-Mail-Adresse</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="email"
                    value={email}
                    class="mt-1 block w-full"
                    readonly
                />
                <InputError message={errors.email} class="mt-2" />
            </div>

            <div class="grid gap-2">
                <Label for="password">Passwort</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    placeholder="Passwort"
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Passwort bestätigen</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    autocomplete="new-password"
                    class="mt-1 block w-full"
                    placeholder="Passwort bestätigen"
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password_confirmation} />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                disabled={processing}
                data-test="reset-password-button"
            >
                {#if processing}<Spinner />{/if}
                Passwort zurücksetzen
            </Button>
        </div>
    {/snippet}
</Form>
