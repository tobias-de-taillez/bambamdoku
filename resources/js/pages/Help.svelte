<script module lang="ts">
    import { help } from '@/routes';

    export const layout = {
        breadcrumbs: [{ title: 'Anleitung', href: help() }],
    };
</script>

<script lang="ts">
    import { page } from '@inertiajs/svelte';
    import AppHead from '@/components/AppHead.svelte';

    const isAdmin = $derived(page.props.auth.isAdmin);
</script>

<AppHead title="Anleitung" />

<article class="mx-auto flex max-w-2xl flex-col gap-8 p-4 text-[15px] leading-relaxed md:p-6">
    <header class="space-y-1">
        <h1 class="text-2xl font-semibold tracking-tight">Anleitung</h1>
        <p class="text-muted-foreground">Das Wichtigste für Mitglieder, Aufsichten und Vorstand.</p>
    </header>

    <nav aria-label="Inhalt" class="rounded-lg border p-4 text-sm">
        <ol class="grid gap-1">
            <li><a class="hover:underline" href="#mitglieder">1. Für Mitglieder</a></li>
            <li><a class="hover:underline" href="#aufsicht">2. Für Standaufsichten: Session führen</a></li>
            <li><a class="hover:underline" href="#korrigieren">3. Fehler korrigieren</a></li>
            <li><a class="hover:underline" href="#ausfall">4. Wenn das Netz ausfällt</a></li>
            {#if isAdmin}
                <li><a class="hover:underline" href="#vorstand">5. Für den Vorstand</a></li>
            {/if}
        </ol>
    </nav>

    <section id="mitglieder" class="space-y-3">
        <h2 class="text-lg font-semibold">1. Für Mitglieder</h2>
        <ul class="list-disc space-y-1 pl-5">
            <li>
                <strong>Mitglied werden:</strong> auf der Anmeldeseite „Mitgliedschaft beantragen“, sofern der
                Verein das freigeschaltet hat. Anmelden können Sie sich, sobald der Vorstand Sie aufgenommen hat.
            </li>
            <li>
                <strong>Bahn buchen:</strong> unter <em>Schießtage</em> einen Tag wählen und auf eine
                Zeit tippen. Die Zahl zeigt die freien Bahnen. Ohne eingeteilte Standaufsicht ist
                Buchen nicht möglich. Eine Buchung allein zählt nicht als Schießtermin.
            </li>
            <li>
                <strong>Am Stand:</strong> Die Aufsicht aktiviert Sie für ihre Session. Danach erscheint auf der
                Übersicht die Karte <em>Heute schießen</em>: Disziplin, Schüsse, Kategorie und eigene oder
                Vereinswaffe eintragen – je Disziplin ein Eintrag.
            </li>
            <li>
                <strong>Ohne Handy:</strong> am Kiosk-Tablet der Aufsicht Ihren Namen antippen und Ihre PIN
                eingeben. Die PIN legen Sie unter <em>Einstellungen → Sicherheit</em> fest. Ohne PIN trägt die
                Aufsicht für Sie ein.
            </li>
            <li>
                <strong>Gezählt</strong> wird ein Eintrag erst, wenn die Aufsicht die Session abzeichnet.
            </li>
            <li>
                <strong>Schießkladde:</strong> zeigt Ihre Termine mit Schüssen und wer sie bestätigt hat. Für den
                Bedürfnisnachweis braucht der Verband den vom Vorstand unterschriebenen Auszug – den erhalten
                Sie beim Vorstand.
            </li>
        </ul>
    </section>

    <section id="aufsicht" class="space-y-3">
        <h2 class="text-lg font-semibold">2. Für Standaufsichten: Session führen</h2>
        <p>
            Eine Session führen dürfen nur Personen mit gültigem Eintrag im Aufsichtsregister. Für Kurz- und
            Langwaffen braucht der Registereintrag den Umfang „erlaubnispflichtige Waffen“.
        </p>
        <ol class="list-decimal space-y-1 pl-5">
            <li>Öffnen Sie <em>Am Stand</em> und tippen Sie auf <strong>Session starten</strong>.</li>
            <li>
                Aktivieren Sie, wer vor Ihnen steht: aus der Buchungsliste Ihres Standes, über die Suche (Name
                oder Mitgliedsnummer) oder als Gast. Beim ersten Mal bestätigen Sie einmalig „volljährig
                geprüft“.
            </li>
            <li>
                Die Mitglieder tragen selbst ein, am Handy oder am Kiosk. Für Gäste und Mitglieder ohne Handy
                oder PIN tragen Sie ein.
            </li>
            <li>
                Prüfen Sie die Einträge – jeder zeigt Uhrzeit und Herkunft (Handy, Kiosk, Aufsicht). Was nicht
                stimmt, lehnen Sie mit Grund ab.
            </li>
            <li>
                <strong>Vor dem Verlassen des Stands abzeichnen.</strong> Erst dann stehen die Einträge in der
                Kladde. Eine nicht abgezeichnete Session verfällt um Mitternacht ohne Nachweis.
            </li>
        </ol>
        <ul class="list-disc space-y-1 pl-5">
            <li>
                <strong>Kiosk:</strong> „Kiosk“ zeigt einen Link bzw. QR-Code für ein Tablet oder macht dieses
                Gerät zum Kiosk (es wird dabei nur auf diesem Gerät abgemeldet). Der Kiosk endet mit dem
                Abzeichnen.
            </li>
            <li><strong>Gäste:</strong> Volljährigkeit am Ausweis prüfen. Minderjährige weiter auf Papier.</li>
            <li>
                <strong>Wer beaufsichtigt, schießt nicht.</strong> Ihre eigenen Termine trägt eine andere
                Aufsicht in deren Session ein, nachdem Sie Ihre abgezeichnet haben.
            </li>
            <li>
                Wechsel der Aufsicht: abzeichnen, die nächste Aufsicht startet ihre eigene Session und aktiviert
                die Verbliebenen neu.
            </li>
            <li>Doppelt getippt? Kein Problem: Es entsteht kein zweiter Eintrag.</li>
        </ul>
    </section>

    <section id="korrigieren" class="space-y-3">
        <h2 class="text-lg font-semibold">3. Fehler korrigieren</h2>
        <p>
            Einträge werden nicht bearbeitet. Vor dem Abzeichnen zieht das Mitglied einen eigenen Eintrag
            zurück und erfasst ihn neu; die Aufsicht lehnt falsche Einträge mit Grund ab. Nach dem Abzeichnen
            ändert nur noch der Vorstand – als Storno oder Korrektur mit Grund und Passwort, nie an eigenen
            Einträgen.
        </p>
    </section>

    <section id="ausfall" class="space-y-3">
        <h2 class="text-lg font-semibold">4. Wenn das Netz ausfällt</h2>
        <p>
            Führen Sie die <strong>Papier-Ersatzliste</strong> (beim Vorstand bzw. in der Kladde des Vereins als
            PDF): Mitglieder tragen sich ein, die Aufsicht zeichnet die Liste ab. Der Vorstand überträgt sie
            später als Nachtrag mit Grund.
        </p>
    </section>

    {#if isAdmin}
        <section id="vorstand" class="space-y-3">
            <h2 class="text-lg font-semibold">5. Für den Vorstand</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    <strong>Anträge:</strong> unter <em>Mitglieder → Anträge</em> Identität und Volljährigkeit
                    prüfen, dann aufnehmen oder ablehnen. Die Registrierung schalten Sie in den Einstellungen
                    frei.
                </li>
                <li>
                    <strong>Mitglieder</strong> anlegen – mit oder ohne E-Mail. Ohne E-Mail gibt es kein Konto;
                    aktivieren und eintragen kann die Aufsicht sie trotzdem. Viele auf einmal übernehmen Sie unter
                    <em>Mitglieder → Importieren</em> aus einer CSV-Datei (Vorlage zum Herunterladen); dort lässt
                    sich auch die Standaufsicht gleich ins Register eintragen.
                </li>
                <li>
                    <strong>Schießtage</strong> einzeln anlegen oder wiederholen: jede Woche, alle zwei Wochen,
                    monatlich am selben Wochentag (etwa jeden 1. und 3. Samstag) oder am selben Tag im Monat.
                    Viele Termine auf einmal: <em>Schießtage → Importieren</em> mit einer CSV-Datei.
                </li>
                <li>
                    <strong>Aufsichtsregister:</strong> beim Mitglied „Prüfung eintragen“ mit Umfang, gültig bis
                    und Prüfdatum. Entzug ebenfalls als neue Zeile. Die eigene Registrierung trägt ein anderes
                    Vorstandsmitglied ein.
                </li>
                <li>
                    <strong>Kladde des Vereins:</strong> alle Einträge mit Stornos und Korrekturen, CSV-Export,
                    <em>Nachtrag</em> (mit Grund, z. B. aus der Papierliste), verfallene Sessions und je Mitglied
                    der <em>Auszug zur Vorlage beim Verband</em> sowie die Mitgliedschaftsbescheinigung –
                    ausdrucken, unterschreiben, stempeln. Storno, Korrektur und Nachtrag verlangen Ihr Passwort;
                    eigene Einträge ändert ein anderes Vorstandsmitglied.
                </li>
                <li>
                    <strong>Einstellungen:</strong> Vereinsname, Anschrift, Verband und Vereinsnummer für die
                    Ausdrucke, Disziplinkatalog, Registrierung, Mailversand prüfen, Datensicherungen
                    herunterladen.
                </li>
            </ul>
        </section>
    {/if}
</article>
