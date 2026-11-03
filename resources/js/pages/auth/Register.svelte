<script module lang="ts">
    export const layout = {
        title: 'Mitgliedschaft beantragen',
        description:
            'Legen Sie ein Konto an. Der Vorstand prüft Ihren Antrag; danach können Sie sich anmelden.',
    };
</script>

<script lang="ts">
    import { Form, Link } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import PasswordInput from '@/components/PasswordInput.svelte';
    import TextLink from '@/components/TextLink.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { toUrl } from '@/lib/utils';
    import { login, privacy } from '@/routes';
    import { store } from '@/routes/register';

    let { passwordRules }: { passwordRules: string } = $props();

    // Ohne name-Attribut: Der Haken geht nur über transform mit, als echter Boolean.
    let adult = $state(false);
</script>

<AppHead title="Mitgliedschaft beantragen" />

<Form
    {...store.form()}
    transform={(data) => ({ ...data, adult })}
    resetOnSuccess={['password', 'password_confirmation']}
    class="flex flex-col gap-6"
>
    {#snippet children({ errors, processing })}
        <div class="grid gap-6">
            <div class="grid gap-2">
                <Label for="name">Vor- und Nachname</Label>
                <Input id="name" name="name" required autocomplete="name" />
                <InputError message={errors.name} />
            </div>

            <div class="grid gap-2">
                <Label for="email">E-Mail-Adresse</Label>
                <Input
                    id="email"
                    type="email"
                    name="email"
                    required
                    autocomplete="email"
                    placeholder="name@beispiel.de"
                />
                <InputError message={errors.email} />
            </div>

            <div class="grid gap-2">
                <Label for="password">Passwort</Label>
                <PasswordInput
                    id="password"
                    name="password"
                    required
                    autocomplete="new-password"
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password} />
            </div>

            <div class="grid gap-2">
                <Label for="password_confirmation">Passwort bestätigen</Label>
                <PasswordInput
                    id="password_confirmation"
                    name="password_confirmation"
                    required
                    autocomplete="new-password"
                    passwordrules={passwordRules}
                />
                <InputError message={errors.password_confirmation} />
            </div>

            <div class="grid gap-2">
                <Label for="adult" class="flex items-start gap-3 font-normal">
                    <Checkbox id="adult" bind:checked={adult} />
                    <span>Ich bin volljährig (mindestens 18 Jahre).</span>
                </Label>
                <InputError message={errors.adult} />
            </div>

            <p class="text-sm text-muted-foreground">
                Mit dem Antrag speichert der Verein Ihren Namen, Ihre
                E-Mail-Adresse und Ihr Passwort (nur als Hash), um über die
                Aufnahme zu entscheiden. Nicht bearbeitete Anträge werden nach
                30 Tagen gelöscht. Mehr im
                <Link
                    href={toUrl(privacy())}
                    class="underline underline-offset-4"
                    >Datenschutzhinweis</Link
                >.
            </p>

            <Button
                type="submit"
                class="w-full"
                disabled={processing}
                data-test="register-button"
            >
                {#if processing}<Spinner />{/if}
                Mitgliedschaft beantragen
            </Button>
        </div>

        <p class="text-center text-sm text-muted-foreground">
            Schon Mitglied?
            <TextLink href={login()}>Anmelden</TextLink>
        </p>
    {/snippet}
</Form>
