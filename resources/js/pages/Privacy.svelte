<script lang="ts">
    import { Link, page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';
    import { toUrl } from '@/lib/utils';
    import { home } from '@/routes';

    let {
        club,
        address,
        email,
        recordYears,
        guestMonths,
    }: {
        club: string;
        address: string | null;
        email: string | null;
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
        <p>
            {club}{address ? `, ${address}` : ''}, vertreten durch den Vorstand.
            {#if email}
                E-Mail: <a href={`mailto:${email}`} class="underline underline-offset-4">{email}</a>.
            {/if}
        </p>
        <p>Einen Datenschutzbeauftragten hat der Verein nicht bestellt.</p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Wozu</h2>
        <p>
            Der Verein muss einen Nachweis über die Häufigkeit der schießsportlichen Aktivitäten
            seiner Mitglieder führen (§ 15 Abs. 1 Nr. 7 b WaffG). Diese Nachweise sind Grundlage für
            Bescheinigungen des Schießsportverbands nach § 14 WaffG. Außerdem muss der Schießbetrieb
            beaufsichtigt werden (§ 27 WaffG, §§ 10, 11 AWaffV).
        </p>
        <p>
            Rechtsgrundlage für Kladde, Sessions und Aufsichtsregister ist nach unserer Einschätzung
            Art. 6 Abs. 1 lit. c DSGVO (rechtliche Pflicht des Vereins aus den genannten
            Vorschriften). Für Konto, Buchungen, Kiosk-PIN und Benachrichtigungen per E-Mail ist es
            Art. 6 Abs. 1 lit. b DSGVO (Ihre Mitgliedschaft im Verein).
        </p>
        <p>
            Wer am Stand schießt, muss in der Kladde erfasst werden; ohne die Angaben kann der
            Verein weder den Schießbetrieb zulassen noch Ihre Teilnahme bescheinigen. Ein Konto und
            eine E-Mail-Adresse sind freiwillig.
        </p>
    </section>

    <section class="space-y-2">
        <h2 class="text-base font-semibold">Welche Daten</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>Mitglieder: Name, Mitgliedsnummer, optional E-Mail-Adresse (nur für ein Konto).</li>
            <li>
                Konto: Passwort nur als Hash; wenn Sie sie einrichten, verschlüsselt das Geheimnis
                und die Wiederherstellungscodes der Zwei-Faktor-Anmeldung sowie der Schlüssel Ihres
                Kalender-Abos.
            </li>
            <li>
                Protokoll der Verwaltung: wer wann was angelegt, geändert, storniert oder gelöscht
                hat (z. B. Buchungen, Aufnahme von Mitgliedern, Einstellungen), mit den Namen der
                Beteiligten.
            </li>
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

    <section id="hilfe" class="space-y-2" aria-labelledby="privacy-help">
        <h2 id="privacy-help" class="text-base font-semibold">Hilfe-Meldungen („Report Bug / Request Feature“)</h2>
        <p>
            Über den Knopf „Report Bug / Request Feature“ unten rechts können Sie melden, was nicht funktioniert oder
            besser werden sollte. Erst beim Absenden speichern wir: Ihren Text, ein Bildschirmfoto der
            Seite (wenn Sie es nicht abwählen), den technischen Zustand der Seite (Adresse, angezeigte
            Daten, sichtbare Formulareingaben, Browser und Gerät, Sprache, Zeitzone, letzte
            Fehlermeldungen und aufgerufene Seiten), Zeitpunkt, bei Anmeldung Ihr Konto (Name und
            Rolle) sowie die gekürzte IP-Adresse. Passwörter, PINs, Schlüssel und Cookies werden nicht
            mitgeschickt bzw. vorher geschwärzt; das Bildschirmfoto kann aber alles zeigen, was auf
            der Seite zu sehen war.
        </p>
        <p>
            Die Meldungen sieht der Vorstand; der Betreuer der Software holt sie ab, um Fehler zu
            beheben. Seine Kopie gleicht er beim Abholen mit dem Server ab: Was dort gelöscht ist,
            verschwindet dabei auch aus der Kopie. Rechtsgrundlage ist nach unserer Einschätzung Art. 6 Abs. 1 lit. f DSGVO
            (berechtigtes Interesse an einer funktionierenden Software). Erledigte Meldungen löscht
            das System 90 Tage nach dem Erledigen, alle übrigen spätestens nach einem Jahr.
        </p>
    </section>

    <section class="space-y-2" aria-labelledby="privacy-technik">
        <h2 id="privacy-technik" class="text-base font-semibold">Beim Aufruf der Seite</h2>
        <p>
            Die Seite lädt keine Inhalte von fremden Servern: keine Schriften, keine Karten, keine
            Statistik- oder Werbedienste. Es gibt kein Tracking.
        </p>
        <p>
            Der Webserver unseres Hosters protokolliert jeden Aufruf (IP-Adresse, Zeitpunkt,
            aufgerufene Adresse, Browser). Das dient dem sicheren Betrieb und der Abwehr von
            Angriffen; Rechtsgrundlage ist Art. 6 Abs. 1 lit. f DSGVO (berechtigtes Interesse an
            einem sicheren Betrieb). Die Protokolle löscht der Hoster nach seinen Fristen.
        </p>
        <p>
            Zu jeder Sitzung, auch ohne Anmeldung (etwa am Kiosk oder auf der Anmeldeseite),
            speichern wir die IP-Adresse und den Browser. Nach 2 Stunden ohne Aufruf ist die Sitzung
            abgelaufen; das System löscht sie bei einem der nächsten Seitenaufrufe. Für den Schutz
            vor zu vielen Versuchen (Anmeldung, Passwort, Antrag, Hilfe-Meldung) merkt sich das
            System die IP-Adresse und bei der Anmeldung die eingegebene E-Mail-Adresse, höchstens
            zwei Stunden lang. Auch hier ist die Rechtsgrundlage Art. 6 Abs. 1 lit. f DSGVO (Schutz
            der Konten).
        </p>
        <p>
            Bei technischen Fehlern schreibt die Anwendung eine Meldung in ihr Fehlerprotokoll.
            Sie kann die aufgerufene Adresse und Angaben aus der betroffenen Anfrage enthalten. Das
            Protokoll dient der Fehlerbehebung (Art. 6 Abs. 1 lit. f DSGVO); das System löscht es
            nach 14 Tagen.
        </p>
        <p>Cookies und Speicher im Browser setzen wir nur, soweit sie für die Seite nötig sind:</p>
        <ul class="list-disc space-y-1 pl-5">
            <li><code>bambamdoku-session</code>: hält Ihre Anmeldung, 2 Stunden ohne Aktivität.</li>
            <li><code>XSRF-TOKEN</code>: schützt Formulare vor Missbrauch, 2 Stunden.</li>
            <li>
                <code>remember_web_…</code>: nur wenn Sie „Angemeldet bleiben“ wählen, bis zum
                Abmelden, höchstens 400 Tage.
            </li>
            <li>
                <code>appearance</code>: hell, dunkel oder System; als Cookie 1 Jahr, im
                Browserspeicher, bis Sie ihn löschen.
            </li>
            <li><code>sidebar_state</code>: ob die Seitenleiste offen ist, 7 Tage.</li>
        </ul>
        <p>
            Eine Einwilligung ist dafür nicht nötig (§ 25 Abs. 2 Nr. 2 TDDDG), weil diese Angaben
            für die von Ihnen genutzte Seite unbedingt erforderlich sind.
        </p>
    </section>

    <section class="space-y-2" aria-labelledby="privacy-recipients">
        <h2 id="privacy-recipients" class="text-base font-semibold">Empfänger</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>
                Hetzner Online GmbH, Industriestr. 25, 91710 Gunzenhausen: betreibt Server und
                E-Mail-Versand für den Verein als Auftragsverarbeiter (Art. 28 DSGVO).
            </li>
            <li>
                Der Betreuer der Software, der im Auftrag des Vereins Fehler behebt: sieht die
                Hilfe-Meldungen und bei der Fehlersuche die gespeicherten Daten.
            </li>
            <li>
                Schießsportverband und Waffenbehörde: erhalten einen Auszug Ihrer Einträge, wenn Sie
                eine Bescheinigung nach § 14 WaffG beantragen oder die Behörde den Nachweis
                verlangt.
            </li>
        </ul>
        <p>
            Wir übermitteln keine Daten in Länder außerhalb der EU und des EWR. Es gibt keine
            automatisierte Entscheidung und kein Profiling.
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
            Das Protokoll der Sessions (Start, Aktivierungen, Ablehnungen, Abzeichnen) und das
            Protokoll der Verwaltung sieht nur der Vorstand. Es macht Änderungen nachvollziehbar;
            Rechtsgrundlage ist Art. 6 Abs. 1 lit. c DSGVO, soweit es die Kladde betrifft, sonst
            lit. f (berechtigtes Interesse an einer nachvollziehbaren Vereinsverwaltung).
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
                Einträge im Protokoll der Verwaltung löscht es nach derselben Frist ab ihrem Datum.
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
            gesetzliche Pflicht zur Aufbewahrung entgegensteht (Art. 15 bis 18 DSGVO). Daten, die
            Sie für Ihre Mitgliedschaft selbst angegeben haben, erhalten Sie auf Wunsch in einem
            gängigen, maschinenlesbaren Format (Art. 20 DSGVO). Einen Mitgliedsantrag können Sie
            jederzeit zurückziehen; er wird dann gelöscht. Wenden Sie sich dafür an den
            Vorstand{email ? ` (${email})` : ''}. Sie können sich außerdem bei einer
            Datenschutz-Aufsichtsbehörde beschweren (Art. 77 DSGVO).
        </p>
    </section>

    <section
        class="space-y-2 rounded-md border p-4"
        aria-labelledby="privacy-objection"
        id="widerspruch"
    >
        <h2 id="privacy-objection" class="text-base font-semibold">Ihr Widerspruchsrecht</h2>
        <p>
            Soweit wir Daten auf Grundlage von Art. 6 Abs. 1 lit. f DSGVO verarbeiten
            (Server- und Fehlerprotokolle, Schutz vor Anmeldeversuchen, Protokoll der Verwaltung
            außerhalb der Kladde, Hilfe-Meldungen), können Sie aus
            Gründen, die sich aus Ihrer besonderen Situation ergeben, jederzeit widersprechen
            (Art. 21 Abs. 1 DSGVO). Wir verarbeiten die Daten dann nicht mehr, es sei denn, wir
            können zwingende schutzwürdige Gründe nachweisen, die Ihre Interessen überwiegen, oder
            die Verarbeitung dient der Geltendmachung, Ausübung oder Verteidigung von
            Rechtsansprüchen. Eine Hilfe-Meldung löschen wir auf Ihren Widerspruch hin sofort. Der
            Widerspruch ist formlos an den Vorstand möglich.
        </p>
    </section>

    <p>
        <Link href={toUrl(home())} class="underline underline-offset-4"
            >{loggedIn ? 'Zurück zur Übersicht' : 'Zur Startseite'}</Link
        >
    </p>
</main>
