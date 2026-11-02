<script module lang="ts">
    export const layout = {
        title: 'Willkommen',
        description: 'Legen Sie Ihr Passwort fest, um Ihren Zugang zu aktivieren.',
    };
</script>

<script lang="ts">
    import { useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';

    let {
        name,
        email,
        action,
        passwordRules,
    }: {
        name: string;
        email: string;
        action: string;
        passwordRules: string;
    } = $props();

    const form = useForm({ password: '', password_confirmation: '' });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.post(action, {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    }
</script>

<AppHead title="Einladung annehmen" />

<form class="grid gap-6" onsubmit={submit}>
    <p class="text-center text-sm">Hallo {name}!</p>
    <div class="grid gap-2">
        <Label for="email">E-Mail-Adresse</Label>
        <Input id="email" type="email" value={email} readonly autocomplete="username" />
    </div>
    <div class="grid gap-2">
        <Label for="password">Neues Passwort</Label>
        <Input
            id="password"
            type="password"
            bind:value={form.password}
            autocomplete="new-password"
            required
        />
        <InputError message={form.errors.password} />
    </div>
    <div class="grid gap-2">
        <Label for="password_confirmation">Passwort wiederholen</Label>
        <Input
            id="password_confirmation"
            type="password"
            bind:value={form.password_confirmation}
            autocomplete="new-password"
            required
        />
    </div>
    <Button type="submit" class="w-full" disabled={form.processing}>
        {#if form.processing}<Spinner />{/if}
        Passwort festlegen und anmelden
    </Button>
</form>
