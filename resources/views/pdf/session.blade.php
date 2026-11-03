@extends('pdf.layout')

@section('title', 'Session-Protokoll')

@section('content')
<h1>Session-Protokoll</h1>
<p class="muted" style="margin-top:0">für den Vorstand – Ablauf der Session am Stand, keine Bescheinigung</p>
<table class="meta">
    <tr><td style="width:42mm">Datum</td><td><strong>{{ $session->shot_on->format('d.m.Y') }}</strong></td></tr>
    <tr><td>Aufsicht</td><td>{{ $session->supervisor->name }}</td></tr>
    <tr><td>Gestartet um</td><td>{{ $session->created_at->format('H:i') }} Uhr</td></tr>
    <tr><td>Abgezeichnet</td><td>
        @if($state === 'signed')
            um {{ $session->signed_at->format('H:i') }} Uhr
        @elseif($state === 'expired')
            <span class="no">verfallen, nicht abgezeichnet</span>
        @else
            offen, noch nicht abgezeichnet
        @endif
    </td></tr>
    <tr><td>Stand und Zeitfenster</td><td>
        @forelse($supervisions as $s)
            {{ $s['range'] }}, {{ $s['window'] }} Uhr{{ $loop->last ? '' : '; ' }}
        @empty
            keine Einteilung
        @endforelse
        <span class="muted">(laut Einteilung, zur Druckzeit gelesen)</span>
    </td></tr>
    <tr><td>Erstellt</td><td>{{ $createdAt->format('d.m.Y, H:i') }} Uhr von {{ $createdBy }}</td></tr>
</table>

<h2>Teilnehmer ({{ count($participants) }})</h2>
@forelse($participants as $p)
    <p style="margin:4mm 0 1mm">
        <strong>{{ $p['guest'] ? 'Gast: '.$p['name'] : $p['name'] }}</strong>@if($p['member_number']), Mitgliedsnummer {{ $p['member_number'] }}@endif
        <span class="muted">· aktiviert um {{ $p['activated_at'] }} Uhr · {{ $p['booking'] ? 'mit Buchung' : 'ohne Buchung' }}</span>
    </p>
    @if(count($p['entries']) === 0)
        <p class="muted" style="margin:0">keine Einträge</p>
    @else
    <table>
        <thead><tr><th>Erfasst um</th><th>Erfasst über</th><th>Disziplin</th><th>Kategorie</th><th>Waffe</th><th class="num">Schüsse</th><th>Status</th></tr></thead>
        <tbody>
        @foreach($p['entries'] as $e)
            <tr>
                <td>{{ $e['at'] }}</td>
                <td>{{ $e['via'] }}</td>
                <td>{{ $e['discipline'] ?? '–' }}</td>
                <td>{{ $e['weapon_label'] }}</td>
                <td>{{ $e['weapon_owner'] ?? '–' }}</td>
                <td class="num">{{ $e['shots'] ?? '–' }}</td>
                <td>
                    {{ $e['status'] }}
                    @if($e['rejected']) von {{ $e['rejected_by'] }}; Grund: {{ $e['reason'] }}@endif
                    @if($e['withdrawn']) <span class="muted">(zurückgezogen von {{ $e['entered_by'] ?? 'der erfassenden Person' }})</span>@endif
                    @if($e['merged']) <span class="muted">(ohne eigene Kladde-Zeile: gleiche Zeile am Tag schon vorhanden)</span>@endif
                </td>
            </tr>
        @endforeach
        </tbody>
    </table>
    @endif
@empty
    <p class="muted">Keine Teilnehmer.</p>
@endforelse

<h2>Abschluss</h2>
@if($state === 'signed')
    <p>Abgezeichnet von {{ $session->supervisor->name }} am {{ $session->signed_at->format('d.m.Y') }} um {{ $session->signed_at->format('H:i') }} Uhr, bestätigt bis {{ $session->signed_at->format('H:i') }} Uhr.</p>
@elseif($state === 'expired')
    <p>Verfallen, nicht abgezeichnet. Die offenen Einträge haben keinen Nachweiswert; was zählen soll, trägt der Vorstand mit Grund nach.</p>
@else
    <p>Die Session ist noch offen.</p>
@endif

<h2>Spätere Änderungen in der Kladde</h2>
@forelse($changes as $c)
    <p style="margin:1mm 0">{{ $c['at']?->format('d.m.Y, H:i') }} Uhr: {{ $c['text'] }}</p>
@empty
    <p class="muted">Keine Stornos oder Korrekturen.</p>
@endforelse

<p class="note">Enthält nur die Teilnehmer dieser Session. Abgelehnte und zurückgezogene Einträge stehen nicht in der Kladde und zählen nicht.</p>
@endsection
