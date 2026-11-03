<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { home } from '@/routes';

    let {
        club,
        address,
    }: {
        club: string;
        address: string | null;
    } = $props();

    const loggedIn = $derived(Boolean(page.props.auth?.user));
</script>

<AppHead title="Datenschutzhinweis" />

<main class="mx-auto flex max-w-2xl flex-col gap-6 px-4 py-10 text-sm leading-relaxed">
    <header class="space-y-1">
        <h1 class="text-2xl font-semibold tracking-tight">Datenschutzhinweis</h1>
        <p class="text-muted-foreground">Schießkladde und Standbetrieb · {club}</p>
    </header>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Verantwortlich</h2>
        <p>{club}{address ? `, ${address}` : ''}. Ansprechpartner ist der Vorstand.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Wozu</h2>
        <p>
            Der Verein muss einen Nachweis über die Häufigkeit der schießsportlichen Aktivitäten
            seiner Mitglieder führen (§ 15 Abs. 1 Nr. 7 b WaffG). Diese Nachweise sind Grundlage für
            Bescheinigungen des Schießsportverbands nach § 14 WaffG. Außerdem muss der Schießbetrieb
            beaufsichtigt werden (§ 27 WaffG, §§ 10, 11 AWaffV).
        </p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Welche Daten</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Mitglieder: Name, Mitgliedsnummer, optional E-Mail-Adresse (nur für ein Konto).</li>
            <li>
                Je Schießtermin: Datum, Kategorie (Kurz-, Langwaffe, erlaubnisfrei), eigene oder
                Vereinswaffe, ggf. Disziplin und wer den Termin eingetragen hat.
            </li>
            <li>Gäste: nur Name und Kategorie, keine Adresse.</li>
            <li>
                Aufsichtspersonen: Registrierung nach § 10 Abs. 3 AWaffV mit Umfang und Gültigkeit.
            </li>
            <li>Bei Buchungen: gewählter Stand und Zeitfenster.</li>
        </ul>
        <p>
            <strong>Nicht gespeichert</strong> werden Waffendaten wie Seriennummer, Hersteller,
            Kaliber oder WBK-Nummer.
        </p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Wer sieht was</h2>
        <p>
            Mitglieder mit Konto sehen nur ihre eigenen Einträge. Die Standaufsicht sieht ihre
            Einträge des Tages. Vorstand und Schießwart sehen die gesamte Kladde, weil der Verein
            die Richtigkeit bestätigen muss.
        </p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Wie lange</h2>
        <p>
            Einträge werden nicht gelöscht, sondern höchstens mit Begründung storniert, damit der
            Nachweis nachvollziehbar bleibt. Die Aufbewahrungsfristen legt der Verein fest.
        </p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Ihre Rechte</h2>
        <p>
            Sie können Auskunft über Ihre Daten verlangen und einen Auszug Ihrer Einträge erhalten.
            Wenden Sie sich dafür an den Vorstand.
        </p>
    </section>

    <p>
        <Link href={toUrl(home())} class="underline underline-offset-4"
            >{loggedIn ? 'Zurück zur Übersicht' : 'Zur Anmeldung'}</Link
        >
    </p>
</main>
