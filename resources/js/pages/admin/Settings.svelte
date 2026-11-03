<script module lang="ts">
    import { edit } from '@/routes/admin/settings';

    export const layout = {
        breadcrumbs: [{ title: 'Vereinseinstellungen', href: edit() }],
    };
</script>

<script lang="ts">
    import { router, useForm } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import Heading from '@/components/Heading.svelte';
    import InputError from '@/components/InputError.svelte';
    import { Button } from '@/components/ui/button';
    import { Input } from '@/components/ui/input';
    import { Label } from '@/components/ui/label';
    import { Spinner } from '@/components/ui/spinner';
    import { toUrl } from '@/lib/utils';
    import { backup, backupDownload, testMail, update } from '@/routes/admin/settings';

    type Settings = {
        name: string;
        address: string | null;
        federation: string | null;
        club_number: string | null;
        imprint: string | null;
        discipline_catalog: string | null;
        digital_since: string | null;
        slot_minutes: number;
        cancel_minutes_before: number;
        booking_horizon_days: number;
    };

    let {
        settings,
        catalogs,
        mail,
        backups,
    }: {
        settings: Settings;
        catalogs: { value: string; label: string }[];
        mail: { mailer: string; delivers: boolean; from: string | null };
        backups: { name: string; size: number; created_at: string }[] | null;
    } = $props();

    const dateTime = new Intl.DateTimeFormat('de-DE', { dateStyle: 'medium', timeStyle: 'short' });

    let mailError = $state<string | null>(null);
    let sending = $state(false);

    function sendTestMail() {
        mailError = null;
        sending = true;
        router.post(toUrl(testMail()), {}, {
            preserveScroll: true,
            onError: (errors) => (mailError = errors.mail ?? null),
            onFinish: () => (sending = false),
        });
    }

    // svelte-ignore state_referenced_locally
    const form = useForm({
        name: settings.name,
        address: settings.address ?? '',
        federation: settings.federation ?? '',
        club_number: settings.club_number ?? '',
        digital_since: settings.digital_since ?? '',
        imprint: settings.imprint ?? '',
        discipline_catalog: settings.discipline_catalog ?? '',
        slot_minutes: settings.slot_minutes,
        cancel_hours: Math.round(settings.cancel_minutes_before / 60),
        booking_horizon_days: settings.booking_horizon_days,
    });

    function submit(event: SubmitEvent) {
        event.preventDefault();
        form.transform((data) => ({
            name: data.name,
            address: data.address || null,
            federation: data.federation || null,
            club_number: data.club_number || null,
            digital_since: data.digital_since || null,
            imprint: data.imprint.trim() || null,
            discipline_catalog: data.discipline_catalog || null,
            slot_minutes: Number(data.slot_minutes),
            cancel_minutes_before: Number(data.cancel_hours) * 60,
            booking_horizon_days: Number(data.booking_horizon_days),
        }));
        form.put(toUrl(update()), { preserveScroll: true });
    }
</script>

<AppHead title="Vereinseinstellungen" />

<div class="flex max-w-2xl flex-col gap-6 p-4 md:p-6">
    <Heading
        title="Vereinseinstellungen"
        description="Gelten für alle Mitglieder. Name, Anschrift, Verband und Vereinsnummer stehen im Kopf des Auszugs aus der Kladde."
    />

    <form class="grid gap-6" onsubmit={submit}>
        <fieldset class="grid gap-4">
            <legend class="mb-2 text-sm font-medium">Verein</legend>
            <div class="grid gap-2">
                <Label for="name">Vereinsname</Label>
                <Input id="name" bind:value={form.name} required />
                <InputError message={form.errors.name} />
            </div>
            <div class="grid gap-2">
                <Label for="address">Anschrift</Label>
                <Input
                    id="address"
                    bind:value={form.address}
                    placeholder="Straße, PLZ Ort"
                />
                <InputError message={form.errors.address} />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="grid gap-2">
                    <Label for="federation">Verband</Label>
                    <Input id="federation" bind:value={form.federation} placeholder="z. B. BSSB, DSB" />
                    <InputError message={form.errors.federation} />
                </div>
                <div class="grid gap-2">
                    <Label for="club_number">Vereinsnummer</Label>
                    <Input id="club_number" bind:value={form.club_number} placeholder="beim Verband" />
                    <InputError message={form.errors.club_number} />
                </div>
            </div>
            <div class="grid gap-2">
                <Label for="digital_since">Kladde digital seit</Label>
                <Input id="digital_since" type="date" bind:value={form.digital_since} class="sm:w-48" />
                <p class="text-xs text-muted-foreground">
                    Steht im Auszug als Hinweis: Frühere Termine stehen in der Papierkladde.
                </p>
                <InputError message={form.errors.digital_since} />
            </div>
            <div class="grid gap-2">
                <Label for="discipline_catalog">Disziplinkatalog des Verbands</Label>
                <select
                    id="discipline_catalog"
                    bind:value={form.discipline_catalog}
                    aria-describedby="discipline-catalog-hint"
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none sm:w-72"
                >
                    <option value="">Keiner – Disziplin wird nicht abgefragt</option>
                    {#each catalogs as catalog (catalog.value)}
                        <option value={catalog.value}>{catalog.label}</option>
                    {/each}
                </select>
                <p id="discipline-catalog-hint" class="text-xs text-muted-foreground">
                    {#if catalogs.length === 0}
                        Noch kein Katalog hinterlegt. Die Nummern kommen aus der Sportordnung des Verbands und werden
                        per Update ergänzt, nicht geraten. Ohne Katalog gibt es keinen Teilnahmenachweis je Disziplin.
                    {:else}
                        Mit Katalog ist die Disziplin beim Eintragen Pflicht.
                    {/if}
                </p>
                <InputError message={form.errors.discipline_catalog} />
            </div>
            <div class="grid gap-2">
                <Label for="imprint">Impressum</Label>
                <textarea
                    id="imprint"
                    bind:value={form.imprint}
                    rows="6"
                    aria-describedby="imprint-hint"
                    class="rounded-md border border-input bg-background px-3 py-2 text-sm shadow-sm focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                    placeholder="Verein, Anschrift, vertreten durch, Kontakt, Registereintrag"
                ></textarea>
                <p id="imprint-hint" class="text-xs text-muted-foreground">
                    Erscheint als öffentliche Seite „Impressum“, verlinkt auf der Anmeldung. Leer lassen, wenn der
                    Verein das Impressum woanders führt.
                </p>
                <InputError message={form.errors.imprint} />
            </div>
        </fieldset>

        <fieldset class="grid gap-4 sm:grid-cols-3">
            <legend class="mb-2 text-sm font-medium">Buchung</legend>
            <div class="grid gap-2">
                <Label for="slot">Raster</Label>
                <select
                    id="slot"
                    bind:value={form.slot_minutes}
                    class="h-9 rounded-md border border-input bg-background px-3 text-sm shadow-sm"
                >
                    {#each [15, 30, 60, 90, 120] as minutes (minutes)}
                        <option value={minutes}>{minutes} Minuten</option>
                    {/each}
                </select>
                <InputError message={form.errors.slot_minutes} />
            </div>
            <div class="grid gap-2">
                <Label for="cancel">Stornieren bis</Label>
                <div class="flex items-center gap-2">
                    <Input
                        id="cancel"
                        type="number"
                        min="0"
                        max="168"
                        bind:value={form.cancel_hours}
                        class="w-20"
                    />
                    <span class="text-sm text-muted-foreground">Std. vorher</span>
                </div>
                <InputError
                    message={(form.errors as Record<string, string>)
                        .cancel_minutes_before}
                />
            </div>
            <div class="grid gap-2">
                <Label for="horizon">Buchbar</Label>
                <div class="flex items-center gap-2">
                    <Input
                        id="horizon"
                        type="number"
                        min="1"
                        max="365"
                        bind:value={form.booking_horizon_days}
                        class="w-20"
                    />
                    <span class="text-sm text-muted-foreground">Tage im Voraus</span>
                </div>
                <InputError message={form.errors.booking_horizon_days} />
            </div>
        </fieldset>
        <p class="-mt-2 text-xs text-muted-foreground">
            Ein neues Raster gilt für neue Buchungen. Bestehende Buchungen bleiben
            unverändert.
        </p>

        <div class="border-t pt-6">
            <Button type="submit" disabled={form.processing}>
                {#if form.processing}<Spinner />{/if}
                Speichern
            </Button>
        </div>
    </form>

    <section class="grid gap-3 rounded-lg border p-4">
        <h2 class="font-medium">E-Mail-Versand</h2>
        {#if mail.delivers}
            <p class="text-sm">
                Mails werden über <strong>{mail.mailer}</strong> verschickt{mail.from
                    ? ` (Absender ${mail.from})`
                    : ''}.
            </p>
        {:else}
            <p
                class="rounded-md border border-amber-500/40 bg-amber-50 p-3 text-sm dark:bg-amber-950/30"
            >
                Der Mailversand ist nicht eingerichtet (<code>{mail.mailer}</code>). Einladungen,
                Absagen, E-Mail-Bestätigungen und Links für „Passwort vergessen“ werden nur ins
                Log geschrieben. Tragen Sie die SMTP-Daten
                Ihres Hosters in der <code>.env</code> ein (<code>MAIL_MAILER=smtp</code>,
                <code>MAIL_HOST</code>, <code>MAIL_USERNAME</code>, <code>MAIL_PASSWORD</code>,
                <code>MAIL_FROM_ADDRESS</code>).
            </p>
        {/if}
        <div>
            <Button variant="outline" onclick={sendTestMail} disabled={sending}>
                {#if sending}<Spinner />{/if}
                Testmail an mich senden
            </Button>
        </div>
        <InputError message={mailError ?? undefined} />
    </section>

    {#if backups !== null}
        <section class="grid gap-3 rounded-lg border p-4">
            <h2 class="font-medium">Datensicherung</h2>
            <p class="text-sm text-muted-foreground">
                Die Datenbank wird jede Nacht gesichert; Sicherungen bleiben 30 Tage. Laden Sie
                ab und zu eine Sicherung herunter und bewahren Sie sie außerhalb des Servers auf.
            </p>
            {#if backups.length > 0}
                <ul class="divide-y rounded-md border text-sm">
                    {#each backups as file (file.name)}
                        <li class="flex items-center justify-between gap-3 px-3 py-2">
                            <span class="tabular-nums">{dateTime.format(new Date(file.created_at))}</span>
                            <span class="text-muted-foreground tabular-nums"
                                >{(file.size / 1024 / 1024).toFixed(1)} MB</span
                            >
                            <a
                                href={toUrl(backupDownload(file.name))}
                                class="underline-offset-4 hover:underline">Herunterladen</a
                            >
                        </li>
                    {/each}
                </ul>
            {:else}
                <p class="text-sm">Noch keine Sicherung vorhanden.</p>
            {/if}
            <div>
                <Button
                    variant="outline"
                    onclick={() => router.post(toUrl(backup()), {}, { preserveScroll: true })}
                    >Jetzt sichern</Button
                >
            </div>
        </section>
    {/if}
</div>
