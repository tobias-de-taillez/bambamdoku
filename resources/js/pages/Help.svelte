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
            <li><a class="hover:underline" href="#aufsicht">2. Für Standaufsichten: eintragen</a></li>
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
                <strong>Bahn buchen:</strong> unter <em>Schießtage</em> einen Tag wählen und auf eine
                Zeit tippen. Die Zahl zeigt die freien Bahnen. Ohne eingeteilte Standaufsicht ist
                Buchen nicht möglich.
            </li>
            <li>
                <strong>Am Stand:</strong> Nennen Sie der Aufsicht Ihren Namen oder Ihre
                Mitgliedsnummer. Erst ihr Eintrag zählt als Schießtermin – eine Buchung allein nicht.
            </li>
            <li>
                <strong>Schießkladde:</strong> zeigt Ihre eingetragenen Termine und die Anzahl Tage
                je Kategorie. Für den Bedürfnisnachweis braucht der Verband den vom Vorstand
                unterschriebenen Auszug – den erhalten Sie beim Vorstand.
            </li>
        </ul>
    </section>

    <section id="aufsicht" class="space-y-3">
        <h2 class="text-lg font-semibold">2. Für Standaufsichten: eintragen</h2>
        <p>
            Eintragen dürfen nur Personen mit gültigem Eintrag im Aufsichtsregister. Für Kurz- und
            Langwaffen braucht der Registereintrag den Umfang „erlaubnispflichtige Waffen“.
        </p>
        <ol class="list-decimal space-y-1 pl-5">
            <li>Öffnen Sie <em>Am Stand</em> und tippen Sie auf <strong>Eintragen</strong>.</li>
            <li>
                Tippen Sie zwei, drei Buchstaben des Namens oder die Mitgliedsnummer und wählen Sie
                die Person aus. Kategorie und Waffe sind aus dem letzten Termin vorbelegt.
            </li>
            <li>Passt alles, tippen Sie <strong>Eintragen</strong>. Die grüne Karte bestätigt Name und Uhrzeit.</li>
        </ol>
        <ul class="list-disc space-y-1 pl-5">
            <li>
                <strong>Gäste:</strong> Reiter <em>Gast</em>, Name eintragen, Volljährigkeit am
                Ausweis prüfen und bestätigen. Minderjährige weiter auf Papier.
            </li>
            <li>
                <strong>Mitglieder mit Buchung</strong> stehen in der Liste ihres Standes; dort
                trägt „Anwesend“ sie ein. Wer den QR-Code scannt, erscheint als „ist da“.
            </li>
            <li>
                <strong>Sich selbst</strong> trägt niemand ein – das macht eine zweite Aufsicht.
            </li>
            <li>
                Doppelt getippt? Kein Problem: Es entsteht kein zweiter Eintrag, die App meldet
                „heute schon eingetragen“.
            </li>
        </ul>
    </section>

    <section id="korrigieren" class="space-y-3">
        <h2 class="text-lg font-semibold">3. Fehler korrigieren</h2>
        <p>
            Einträge werden nicht bearbeitet. Unter <em>Heute eingetragen</em> stornieren Sie einen
            eigenen Eintrag mit Grund (z. B. „Fehleingabe“) und tragen dann richtig neu ein. Ab dem
            Folgetag kann nur noch der Vorstand stornieren.
        </p>
    </section>

    <section id="ausfall" class="space-y-3">
        <h2 class="text-lg font-semibold">4. Wenn das Netz ausfällt</h2>
        <p>
            Führen Sie die <strong>Papier-Ersatzliste</strong> (beim Vorstand bzw. in der Kladde
            des Vereins als PDF). Der Vorstand trägt sie später als Nachtrag mit Grund ein.
        </p>
    </section>

    {#if isAdmin}
        <section id="vorstand" class="space-y-3">
            <h2 class="text-lg font-semibold">5. Für den Vorstand</h2>
            <ul class="list-disc space-y-1 pl-5">
                <li>
                    <strong>Mitglieder</strong> anlegen – mit oder ohne E-Mail. Ohne E-Mail gibt es
                    kein Konto; eingetragen werden können sie trotzdem.
                </li>
                <li>
                    <strong>Aufsichtsregister:</strong> beim Mitglied „Prüfung eintragen“ mit Umfang,
                    gültig bis und Prüfdatum. Entzug ebenfalls als neue Zeile. Die eigene
                    Registrierung trägt ein anderes Vorstandsmitglied ein.
                </li>
                <li>
                    <strong>Kladde des Vereins:</strong> alle Einträge mit Stornos, CSV-Export,
                    <em>Nachtrag</em> (mit Grund, z. B. aus der Papierliste) und je Mitglied der
                    <em>Auszug zur Vorlage beim Verband</em> – ausdrucken, unterschreiben, stempeln.
                </li>
                <li>
                    <strong>Einstellungen:</strong> Vereinsname und Anschrift für den Auszug,
                    Mailversand prüfen, Datensicherungen herunterladen.
                </li>
            </ul>
        </section>
    {/if}
</article>
