@extends('pdf.layout')

@section('title', 'Auszug aus der Schießkladde')

@section('content')
<h1>Auszug aus der Schießkladde des Vereins</h1>
<p class="muted" style="margin-top:0">
    {{ $forInformation ? 'zur Information des Mitglieds – Nachweiswert erst mit Unterschrift und Stempel des Vorstands' : 'zur Vorlage beim Verband' }}
</p>
<table class="meta">
    <tr><td style="width:42mm">Verein</td><td>{{ $club }}@if($clubNumber) (Vereinsnummer {{ $clubNumber }})@endif</td></tr>
    @if($federation)<tr><td>Verband</td><td>{{ $federation }}</td></tr>@endif
    <tr><td>Mitglied</td><td><strong>{{ $member->name }}</strong>@if($member->member_number), Mitgliedsnummer {{ $member->member_number }}@endif</td></tr>
    <tr><td>Zeitraum</td><td>{{ $from->format('d.m.Y') }} bis {{ $to->format('d.m.Y') }} (Stichtag)</td></tr>
    <tr><td>Erstellt</td><td>{{ $createdAt->format('d.m.Y, H:i') }} Uhr von {{ $createdBy }}</td></tr>
</table>
@if($digitalSince)
    <p class="note" style="margin-top:3mm">Digitale Aufzeichnung seit {{ \Carbon\CarbonImmutable::parse($digitalSince)->format('d.m.Y') }}; frühere Termine siehe Papierkladde.</p>
@endif

<h2>Termine ({{ count($entries) }})</h2>
@if(count($entries) === 0)
    <p class="muted">Im Zeitraum sind keine Termine in der Kladde.</p>
@else
<table>
    <thead><tr><th>Datum</th><th>Kategorie</th><th>Waffe</th><th>Disziplin</th><th class="num">Schüsse</th><th>Erfasst über</th><th>Bestätigt von</th></tr></thead>
    <tbody>
    @foreach($entries as $e)
        <tr>
            <td>{{ \Carbon\CarbonImmutable::parse($e['date'])->format('d.m.Y') }}</td>
            <td>{{ $e['weapon_label'] }}</td>
            <td>{{ $e['weapon_owner'] ?? '–' }}</td>
            <td>{{ $e['discipline'] ?? '–' }}</td>
            <td class="num">{{ $e['shots'] ?? '–' }}</td>
            <td>{{ $e['via'] }}</td>
            <td>{{ $e['confirmed_by'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<h2>Anzahl Tage und Schüsse im Zeitraum</h2>
<table>
    <thead><tr><th>Kategorie</th><th class="num">Tage</th><th class="num">davon eigene Waffe</th><th class="num">Schüsse (nur erfasste)</th></tr></thead>
    <tbody>
    @foreach($totals as $t)
        <tr><td>{{ $t['label'] }}</td><td class="num">{{ $t['days'] }}</td><td class="num">{{ $t['own_days'] }}</td><td class="num">{{ $t['shots'] }}@if($t['shots_missing'] > 0)<br><span class="muted">davon ohne Angabe: {{ $t['shots_missing'] }}</span>@endif</td></tr>
    @endforeach
    </tbody>
</table>
<p class="muted" style="font-size:8pt">Die Schüsse sind nur eine Angabe; gezählt werden Tage.</p>

<p class="note">
    Jede Zeile ist von der genannten, im Aufsichtsregister geführten Aufsicht bestätigt (§ 10 Abs. 3 AWaffV){{ $switchover ? ': bis '.$switchover->format('d.m.Y').' durch Eintragen am Stand, seitdem durch Abzeichnen der vom Mitglied oder von der Aufsicht erfassten Einträge' : '' }}.
    Nachträge und Korrekturen des Vorstands sind mit Grund gekennzeichnet; aus Buchungen übernommene Zeilen ohne
    Bestätigung sind markiert. Ein Termin zählt je Tag und Kategorie einmal. Keine Bescheinigung, keine rechtliche
    Bewertung; bescheinigen muss der Verband.
</p>

<table class="sign">
    <tr>
        <td><div class="line">Ort, Datum</div></td>
        <td><div class="line">Richtigkeit bestätigt: Unterschrift Vorstand, Vereinsstempel</div></td>
    </tr>
</table>
@endsection
