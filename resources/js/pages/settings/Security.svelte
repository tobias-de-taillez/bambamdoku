<script module lang="ts">
    import { edit } from '@/routes/security';

    export const layout = {
        breadcrumbs: [
            {
                title: 'Sicherheit',
                href: edit(),
            },
        ],
    };
</script>

<script lang="ts">
    import {
        Form ,
        page,
        router,
        useForm,
    } from '@inertiajs/svelte';
    import SecurityController from '@/actions/App/Http/Controllers/Settings/SecurityController';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import { Button } from '@/components/ui/button';
    import { Label } from '@/components/ui/label';
    import ManageTwoFactor from '@/components/ManageTwoFactor.svelte';
    import { Input } from '@/components/ui/input';
    import { toUrl } from '@/lib/utils';
    import {
        destroy as destroyPin,
        update as updatePin,
    } from '@/routes/kiosk-pin';
    const canManageTwoFactor = $derived(Boolean(page.props.canManageTwoFactor));
    const requiresConfirmation = $derived(
        Boolean(page.props.requiresConfirmation),
    );
    const twoFactorEnabled = $derived(Boolean(page.props.twoFactorEnabled));

    let {
        passwordRules,
        hasKioskPin = false,
    }: { passwordRules: string; hasKioskPin?: boolean } = $props();

    // Kiosk-PIN (E2): 4 bis 6 Ziffern, gespeichert nur als Hash.
    const pinForm = useForm({ pin: '', pin_confirmation: '' });

    function savePin(event: SubmitEvent) {
        event.preventDefault();
        pinForm.put(toUrl(updatePin()), {
            preserveScroll: true,
            onFinish: () => {
                pinForm.pin = '';
                pinForm.pin_confirmation = '';
            },
        });
    }

    function removePin() {
        router.delete(toUrl(destroyPin()), { preserveScroll: true });
    }
</script>

<AppHead title="Sicherheit" />

<h1 class="sr-only">Sicherheit</h1>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Passwort ändern"
        description="Verwenden Sie ein langes, zufälliges Passwort, um Ihr Konto zu schützen."
    />

    <Form
        {...SecurityController.update.form()}
        class="space-y-6"
        options={{ preserveScroll: true }}
        resetOnSuccess
        resetOnError={['password', 'password_confirmation', 'current_password']}
    >
        {#snippet children({ errors, processing })}
            <div class="grid gap-2">
                <Label for="current_password">Aktuelles Passwort</Label>
                <PasswordInput
                    id="current_password"
                    name="current_password"
                    class="mt-1 block w-full"
                    autocomplete="current-password"
                    placeholder="Aktuelles Passwort"
                />
                <InputError message={errors.current_password} />
            </div>

            <div class="grid gap-2">
                <Label for="password">Neues Passwort</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Neues Passwort"
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Passwort bestätigen</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    class="mt-1 block w-full"
                    autocomplete="new-password"
                    placeholder="Passwort bestätigen"
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password_confirmation} />
            </div>

            <div class="flex items-center gap-4">
                <Button
                    type="submit"
                    disabled={processing}
                    data-test="update-password-button"
                >
                    Speichern
                </Button>
            </div>
        {/snippet}
    </Form>
</div>

<div class="space-y-6">
    <Heading
        variant="small"
        title="Kiosk-PIN"
        description="Mit dieser PIN tragen Sie sich am Kiosk-Tablet am Stand selbst ein, ohne Anmeldung. Ohne PIN trägt Sie die Aufsicht ein."
    />

    <p class="text-sm" data-test="kiosk-pin-status">
        {hasKioskPin ? 'Eine Kiosk-PIN ist gesetzt.' : 'Noch keine Kiosk-PIN gesetzt.'}
    </p>

    <form class="space-y-6" onsubmit={savePin}>
        <div class="grid gap-2">
            <Label for="kiosk_pin">{hasKioskPin ? 'Neue PIN' : 'PIN'} (4 bis 6 Ziffern)</Label>
            <Input
                id="kiosk_pin"
                type="password"
                inputmode="numeric"
                autocomplete="off"
                maxlength={6}
                bind:value={pinForm.pin}
            />
            <InputError message={pinForm.errors.pin} />
        </div>
        <div class="grid gap-2">
            <Label for="kiosk_pin_confirmation">PIN bestätigen</Label>
            <Input
                id="kiosk_pin_confirmation"
                type="password"
                inputmode="numeric"
                autocomplete="off"
                maxlength={6}
                bind:value={pinForm.pin_confirmation}
            />
        </div>
        <div class="flex items-center gap-4">
            <Button type="submit" disabled={pinForm.processing}>PIN speichern</Button>
            {#if hasKioskPin}
                <Button type="button" variant="ghost" onclick={removePin}>PIN löschen</Button>
            {/if}
        </div>
    </form>
</div>

<ManageTwoFactor
    {canManageTwoFactor}
    {requiresConfirmation}
    {twoFactorEnabled}
/>

