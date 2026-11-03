<script module lang="ts">
    import { index } from '@/routes/admin/users';

    export const layout = {
        breadcrumbs: [{ title: 'Mitglieder', href: index() }],
    };
</script>

<script lang="ts">
    import { Link, page, router, useForm } from '@inertiajs/svelte';
    import Mail from '@lucide/svelte/icons/mail';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Checkbox } from '@/components/ui/checkbox';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { toUrl } from '@/lib/utils';
    import { certificate, memberDisciplinePdf, memberPdf } from '@/routes/admin/record';
    import {
        invite,
        register as registerRoute,
        store,
        update,
    } from '@/routes/admin/users';
    import { destroy as destroyKioskPin } from '@/routes/admin/users/kiosk-pin';
    import { formatDate } from '@/lib/format';
    import Download from '@lucide/svelte/icons/download';
    import type { AdminUser, RoleOption } from '@/types/admin';
    import type { DisciplinePrint } from '@/types/record';

    let {
        user,
        roles,
        register = [],
        scopes = [],
        catalog = false,
        disciplines = [],
    }: {
        catalog?: boolean;
        disciplines?: DisciplinePrint[];
        user: AdminUser | null;
        roles: RoleOption[];
        register?: {
            id: number;
            scope: string;
            scope_label: string;
            valid_until: string | null;
            checked_on: string;
            checked_by: string | null;
            note: string | null;
        }[];
        scopes?: { value: string; label: string }[];
    } = $props();

    function isoToday(): string {
        const d = new Date();
        const pad = (n: number) => String(n).padStart(2, '0');

        return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
    }

    let addingRegister = $state(false);
    const registerForm = useForm({
        scope: 'licensed',
        valid_until: '',
        checked_on: isoToday(),
        note: '',
        confirmed: false,
    });

    function submitRegister(event: SubmitEvent) {
        event.preventDefault();

        if (!user) {
            return;
        }

        registerForm.post(toUrl(registerRoute(user.id)), {
            preserveScroll: true,
            onSuccess: () => {
                addingRegister = false;
                registerForm.reset();
            },
        });
    }

    // svelte-ignore state_referenced_locally
    const editing = user !== null;
    // svelte-ignore state_referenced_locally
    const isSelf = user?.id === page.props.auth.user.id;

    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: user?.name ?? '',
        email: user?.email ?? '',
        role: user?.role ?? 'member',
        member_number: user?.member_number ?? '',
        member_since: user?.member_since ?? '',
        active: user?.active ?? true,
        send_invitation: true,
        adult_confirmed: false,
    });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            email: data.email || null,
            member_number: data.member_number || null,
            member_since: data.member_since || null,
        }));

        if (user) {
            form.put(toUrl(update(user.id)));
        } else {
            form.post(toUrl(store()));
        }
    }

    function resendInvitation() {
        if (user) {
            router.post(toUrl(invite(user.id)));
        }
    }

    // svelte-ignore state_referenced_locally
    const title = editing ? (user?.name ?? "") : "Mitglied anlegen";
</script>

<AppHead {title} />

<div class="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
    <Heading
        {title}
        description={editing
            ? 'Stammdaten, Rolle und Aufsichts-Qualifikation'
            : 'Das Mitglied bekommt einen Einladungslink und legt sein Passwort selbst fest.'}
    />

    {#if user}
        <div class="flex flex-wrap gap-2">
            <Button variant="outline" size="sm" asChild>
                {#snippet children(props)}
                    <a href={toUrl(memberPdf(user.id))} class={props.class}
                        ><Download class="size-4" /> Auszug Schießkladde</a
                    >
                {/snippet}
            </Button>
            <Button variant="outline" size="sm" asChild>
                {#snippet children(props)}
                    <a href={toUrl(certificate(user.id))} class={props.class}
                        ><Download class="size-4" /> Mitgliedschaftsbescheinigung</a
                    >
                {/snippet}
            </Button>
            {#if catalog}
                {#each disciplines as d (d.key)}
                    <Button variant="outline" size="sm" asChild>
                        {#snippet children(props)}
                            <a href={toUrl(memberDisciplinePdf(user.id, { query: { discipline: d.key } }))} class={props.class}
                                ><Download class="size-4" /> Teilnahmenachweis {d.label}</a
                            >
                        {/snippet}
                    </Button>
                {/each}
            {/if}
        </div>
        {#if !catalog}
            <p class="text-xs text-muted-foreground">
                Teilnahmenachweis je Disziplin: nicht verfügbar, solange kein Disziplinkatalog gewählt ist.
            </p>
        {/if}
    {/if}

    <form class="flex flex-col gap-6" onsubmit={submit} novalidate>
        <div class="grid gap-4 sm:grid-cols-2">
            <div class="grid gap-2">
                <Label for="name">Name</Label>
                <Input
                    id="name"
                    bind:value={form.name}
                    required
                    autocomplete="off"
                />
                <InputError message={form.errors.name} />
            </div>
            <div class="grid gap-2">
                <Label for="email"
                    >E-Mail-Adresse <span class="font-normal text-muted-foreground">(optional, für ein Konto)</span></Label
                >
                <Input
                    id="email"
                    type="email"
                    bind:value={form.email}
                    autocomplete="off"
                />
                <InputError message={form.errors.email} />
            </div>
            <div class="grid gap-2">
                <Label for="member_number">Mitgliedsnummer</Label>
                <Input
                    id="member_number"
                    bind:value={form.member_number}
                    placeholder="optional"
                />
                <InputError message={form.errors.member_number} />
            </div>
            <div class="grid gap-2">
                <Label for="member_since">Mitglied seit</Label>
                <Input
                    id="member_since"
                    type="date"
                    bind:value={form.member_since}
                />
                <InputError message={form.errors.member_since} />
            </div>
            <div class="grid gap-2">
                <Label for="role">Rolle</Label>
                <select
                    id="role"
                    bind:value={form.role}
                    disabled={isSelf}
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none disabled:opacity-50"
                >
                    {#each roles as role (role.value)}
                        <option value={role.value}>{role.label}</option>
                    {/each}
                </select>
                <InputError message={form.errors.role} />
            </div>
        </div>

        {#if !editing}
            <p class="rounded-lg border p-4 text-sm text-muted-foreground">
                Ist die Person Standaufsicht? Tragen Sie sie nach dem Anlegen im
                <strong>Aufsichtsregister</strong> ein (§ 10 Abs. 3 AWaffV).
            </p>
        {/if}

        {#if !user?.adult_confirmed}
            <Label class="flex items-start gap-3 font-normal">
                <Checkbox bind:checked={form.adult_confirmed} />
                <span>
                    Volljährig geprüft
                    <span class="block text-muted-foreground"
                        >Ausweis gesehen; wird einmal gespeichert und danach nicht
                        mehr abgefragt.</span
                    >
                </span>
            </Label>
        {:else}
            <p class="text-sm text-muted-foreground">Volljährigkeit geprüft.</p>
        {/if}

        {#if editing}
            <Label class="flex items-center gap-3 font-normal">
                <Checkbox
                    bind:checked={form.active}
                    disabled={isSelf}
                />
                Zugang aktiv
                <span class="text-muted-foreground"
                    >– deaktivierte Mitglieder können sich nicht anmelden</span
                >
            </Label>
        {:else}
            {#if form.email}
                <Label class="flex items-center gap-3 font-normal">
                    <Checkbox bind:checked={form.send_invitation} />
                    Einladung sofort per E-Mail senden
                </Label>
            {:else}
                <p class="text-sm text-muted-foreground">
                    Ohne E-Mail-Adresse bekommt das Mitglied kein Konto. Die Aufsicht trägt es
                    am Stand per Name oder Mitgliedsnummer ein; den Auszug erzeugt der Vorstand.
                </p>
            {/if}
        {/if}

        <div class="flex flex-wrap items-center gap-3 border-t pt-6">
            <Button type="submit" disabled={form.processing}>
                {#if form.processing}<Spinner />{/if}
                {editing ? 'Speichern' : form.email ? 'Anlegen und einladen' : 'Anlegen'}
            </Button>
            <Button variant="ghost" asChild>
                {#snippet children(props)}
                    <Link href={toUrl(index())} class={props.class}
                        >Abbrechen</Link
                    >
                {/snippet}
            </Button>
            {#if editing && user && user.email && user.active}
                <Button
                    type="button"
                    variant="outline"
                    class="ml-auto"
                    onclick={resendInvitation}
                >
                    <Mail class="size-4" />
                    {user.has_logged_in
                        ? 'Link zum Passwort setzen'
                        : 'Einladung erneut senden'}
                </Button>
            {/if}
        </div>
    </form>

    {#if user?.has_kiosk_pin}
        <section class="flex flex-wrap items-center justify-between gap-3 rounded-lg border p-4" aria-labelledby="kiosk-pin">
            <div>
                <h2 id="kiosk-pin" class="font-medium">Kiosk-PIN</h2>
                <p class="text-sm text-muted-foreground">
                    Gesetzt. Sehen kann sie niemand; löschen Sie sie, wenn das Mitglied sie vergessen hat oder sie
                    bekannt geworden ist.
                </p>
            </div>
            <Button
                variant="outline"
                onclick={() =>
                    user &&
                    confirm(`Kiosk-PIN von ${user.name} löschen?`) &&
                    router.delete(toUrl(destroyKioskPin(user.id)), { preserveScroll: true })}>PIN löschen</Button
            >
        </section>
    {/if}

    {#if user}
        <section class="grid gap-3 rounded-lg border p-4" aria-labelledby="register">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="register" class="font-medium">Aufsichtsregister</h2>
                    <p class="text-sm text-muted-foreground">
                        Registrierung als Aufsichtsperson nach § 10 Abs. 3 AWaffV. Jede
                        Prüfung und jeder Entzug ist eine neue Zeile; es gilt die jüngste.
                    </p>
                </div>
                {#if !addingRegister && !isSelf}
                    <Button variant="outline" onclick={() => (addingRegister = true)}
                        >Prüfung eintragen</Button
                    >
                {/if}
            </div>

            {#if isSelf}
                <p class="text-sm text-muted-foreground">
                    Die eigene Registrierung trägt ein anderes Vorstandsmitglied ein (Vier-Augen-Prinzip).
                </p>
            {/if}

            {#if addingRegister}
                <form class="grid gap-4 rounded-md bg-muted/40 p-4" onsubmit={submitRegister}>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <div class="grid gap-2">
                            <Label for="reg-scope">Umfang</Label>
                            <select
                                id="reg-scope"
                                bind:value={registerForm.scope}
                                class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                            >
                                {#each scopes as scope (scope.value)}
                                    <option value={scope.value}>{scope.label}</option>
                                {/each}
                            </select>
                            <InputError message={registerForm.errors.scope} />
                        </div>
                        <div class="grid gap-2">
                            <Label for="reg-checked">Geprüft am</Label>
                            <Input id="reg-checked" type="date" bind:value={registerForm.checked_on} />
                            <InputError message={registerForm.errors.checked_on} />
                        </div>
                        {#if registerForm.scope !== 'revoked'}
                            <div class="grid gap-2">
                                <Label for="reg-until">Gültig bis</Label>
                                <Input id="reg-until" type="date" bind:value={registerForm.valid_until} />
                                <InputError message={registerForm.errors.valid_until} />
                            </div>
                        {/if}
                    </div>
                    <div class="grid gap-2">
                        <Label for="reg-note">Vermerk</Label>
                        <Input
                            id="reg-note"
                            bind:value={registerForm.note}
                            placeholder="z. B. Lehrgang Standaufsicht DSB, Sachkunde nach § 7 WaffG"
                        />
                    </div>
                    {#if registerForm.scope !== 'revoked'}
                        <Label class="flex items-start gap-3 font-normal">
                            <Checkbox bind:checked={registerForm.confirmed} class="mt-0.5" />
                            <span
                                >Ich habe geprüft: Die Person ist volljährig und hat die nötige
                                Sachkunde{registerForm.scope === 'licensed'
                                    ? ' einschließlich Waffensachkunde'
                                    : ''}. (Wird nicht gespeichert.)</span
                            >
                        </Label>
                        <InputError message={registerForm.errors.confirmed} />
                    {/if}
                    <div class="flex gap-2">
                        <Button type="submit" disabled={registerForm.processing}>Eintragen</Button>
                        <Button type="button" variant="ghost" onclick={() => (addingRegister = false)}
                            >Abbrechen</Button
                        >
                    </div>
                </form>
            {/if}

            {#if register.length === 0}
                <p class="text-sm text-muted-foreground">Nicht im Register.</p>
            {:else}
                <ol class="divide-y rounded-md border text-sm">
                    {#each register as row, i (row.id)}
                        <li class="grid gap-0.5 px-3 py-2 {i > 0 ? 'text-muted-foreground' : ''}">
                            <span>
                                <strong class="font-medium">{row.scope_label}</strong
                                >{row.valid_until ? `, gültig bis ${formatDate(row.valid_until)}` : ''}
                                {#if i === 0}<span class="ml-1 text-xs text-emerald-700 dark:text-emerald-400"
                                        >aktuell</span
                                    >{/if}
                            </span>
                            <span class="text-xs text-muted-foreground"
                                >geprüft am {formatDate(row.checked_on)}{row.checked_by
                                    ? ` von ${row.checked_by}`
                                    : ''}{row.note ? ` · ${row.note}` : ''}</span
                            >
                        </li>
                    {/each}
                </ol>
            {/if}
        </section>
    {/if}
</div>
