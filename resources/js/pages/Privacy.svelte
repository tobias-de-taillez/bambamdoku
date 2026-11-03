<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { home } from '@/routes';

    let {
        club,
        address,
        recordYears,
        guestMonths,
    }: {
        club: string;
        address: string | null;
        recordYears: number;
        guestMonths: number;
    } = $props();

    const guestPeriod = $derived(guestMonths === 1 ? '1 Monat' : `${guestMonths} Monaten`);

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
                Vereinswaffe, Schusszahl, ggf. Disziplin und wer den Termin eingetragen hat.
            </li>
            <li>
                Je Session am Stand: die Aufsicht, Uhrzeit von Start, Aktivierung, Erfassung und
                Abzeichnen sowie die Herkunft jeder Erfassung (eigenes Gerät, Kiosk oder Aufsicht).
                Lehnt die Aufsicht einen Eintrag ab, speichern wir den Grund und wer abgelehnt hat;
                zurückgezogene und abgelehnte Einträge bleiben mit diesem Vermerk erhalten.
            </li>
            <li>
                Gäste: Name, Kategorie und Schusszahl, keine Adresse. Die Volljährigkeit prüft die
                Aufsicht, gespeichert wird sie bei Gästen nicht.
            </li>
            <li>
                Kiosk-PIN (wenn Sie eine festlegen): nur als Hash, nicht im Klartext.
            </li>
            <li>
                Volljährigkeit bei Mitgliedern: nur der Vermerk, dass sie geprüft wurde, und wann –
                durch den Vorstand bei der Aufnahme oder durch die Aufsicht beim Aktivieren.
            </li>
            <li>
                Aufsichtspersonen: Registrierung nach § 10 Abs. 3 AWaffV mit Umfang und Gültigkeit.
            </li>
            <li>Bei Buchungen: gewählter Stand und Zeitfenster.</li>
            <li>
                Mitgliedsantrag (Konto selbst anlegen): Name, E-Mail-Adresse und Passwort (nur als
                Hash) sowie der Zeitpunkt des Antrags. Den Haken „volljährig“ speichert der Verein
                nicht; Identität und Volljährigkeit prüft der Vorstand bei der Aufnahme und vermerkt
                nur, dass geprüft wurde.
            </li>
        </ul>
        <p>
            <strong>Nicht gespeichert</strong> werden Waffendaten wie Seriennummer, Hersteller,
            Kaliber oder WBK-Nummer.
        </p>
    </section>

    <section class="space-y-2" aria-labelledby="privacy-application">
        <h2 id="privacy-application" class="text-base font-semibold">Mitgliedsantrag</h2>
        <p>
            Wenn der Verein es in den Einstellungen freigeschaltet hat, können Sie auf der
            Anmeldeseite selbst ein Konto anlegen und damit die Mitgliedschaft beantragen. Die
            Angaben brauchen wir, um über die Aufnahme zu entscheiden und Sie danach als Mitglied zu
            führen. Rechtsgrundlage ist nach unserer Einschätzung Art. 6 Abs. 1 lit. b DSGVO
            (Maßnahmen vor Abschluss der Mitgliedschaft auf Ihre Anfrage). Die Angaben sind
            freiwillig; ohne sie ist ein Online-Antrag nicht möglich.
        </p>
        <p>
            Den Antrag sieht nur der Vorstand. Bis zur Aufnahme können Sie sich nicht anmelden. Wird
            der Antrag abgelehnt, löschen wir das Konto sofort. Anträge, die nach 30 Tagen nicht
            bearbeitet sind, löscht das System automatisch. Nach der Aufnahme gelten die übrigen
            Abschnitte dieses Hinweises.
        </p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Wer sieht was</h2>
        <p>
            Mitglieder mit Konto sehen nur ihre eigenen Einträge. Die Standaufsicht sieht ihre
            Einträge des Tages. Vorstand und Schießwart sehen die gesamte Kladde, weil der Verein
            die Richtigkeit bestätigen muss.
        </p>
        <p>
            Am Kiosk (Tablet am Stand, über den Link der Session ohne Anmeldung) sieht jeder am
            Tablet Name und Mitgliedsnummer der Teilnehmer dieser Session, damit sie sich zum
            Erfassen auswählen können. Erfassen geht dort nur mit der eigenen Kiosk-PIN.
        </p>
        <p>
            Das Protokoll der Sessions (Start, Aktivierungen, Ablehnungen, Abzeichnen) sieht nur
            der Vorstand.
        </p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Wie lange</h2>
        <p>
            Einträge in der Kladde werden nicht bearbeitet, sondern höchstens mit Begründung
            storniert, damit der Nachweis nachvollziehbar bleibt. Die Fristen hat der Verein
            festgelegt:
        </p>
        <ul class="list-disc space-y-1 pl-5">
            <li>
                <strong>Kladde, Sessions und Buchungen: {recordYears} Jahre</strong> ab dem
                Schießtermin. Danach löscht das System sie automatisch, auch stornierte Einträge.
                Der Verband prüft für die Bescheinigung nach § 14 WaffG die letzten 24 Monate; die
                längere Frist deckt den Prüftermin und Nachfragen ab.
            </li>
            <li>
                <strong>Namen von Gästen: {guestPeriod}</strong> nach dem Termin. Danach steht in
                Kladde, Session, Buchung und Protokoll statt des Namens nur noch „Gast
                (anonymisiert)“, intern mit einer laufenden Nummer. Datum und Kategorie
                bleiben bis zum Ende der Frist der Kladde.
            </li>
            <li>
                Die täglichen Datensicherungen auf dem Server löscht das System nach 30 Tagen; bis
                dahin können gelöschte Angaben dort noch enthalten sein.
            </li>
        </ul>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Ihre Rechte</h2>
        <p>
            Sie können Auskunft über Ihre Daten verlangen und einen Auszug Ihrer Einträge erhalten,
            außerdem Berichtigung, Löschung oder Einschränkung der Verarbeitung, soweit keine
            gesetzliche Pflicht zur Aufbewahrung entgegensteht (Art. 15 bis 18 DSGVO). Einen
            Mitgliedsantrag können Sie jederzeit zurückziehen; er wird dann gelöscht. Wenden Sie
            sich dafür an den Vorstand. Sie können sich außerdem bei einer
            Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO).
        </p>
    </section>

    <p>
        <Link href={toUrl(home())} class="underline underline-offset-4"
            >{loggedIn ? 'Zurück zur Übersicht' : 'Zur Startseite'}</Link
        >
    </p>
</main>
