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
    <thead><tr><th>Datum</th><th>Kategorie</th><th>Waffe</th><th>Disziplin</th><th>Eingetragen von</th><th></th></tr></thead>
    <tbody>
    @foreach($entries as $e)
        <tr>
            <td>{{ \Carbon\CarbonImmutable::parse($e['date'])->format('d.m.Y') }}</td>
            <td>{{ $e['weapon_label'] }}</td>
            <td>{{ $e['weapon_owner'] ?? '–' }}</td>
            <td>{{ $e['discipline'] ?? '–' }}</td>
            <td>{{ $e['recorded_by'] }}</td>
            <td>{{ $e['late'] ? 'Nachtrag' : '' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<h2>Anzahl Tage im Zeitraum</h2>
<table>
    <thead><tr><th>Kategorie</th><th class="num">alle</th><th class="num">davon eigene Waffe</th></tr></thead>
    <tbody>
    @foreach($totals as $t)
        <tr><td>{{ $t['label'] }}</td><td class="num">{{ $t['days'] }}</td><td class="num">{{ $t['own_days'] }}</td></tr>
    @endforeach
    </tbody>
</table>

<p class="note">
    Jeder Eintrag wurde am Stand von einer im Aufsichtsregister des Vereins geführten Aufsichtsperson
    (§ 10 Abs. 3 AWaffV) angelegt oder ist als Nachtrag mit Begründung gekennzeichnet. Ein Termin zählt je Tag
    und Kategorie einmal. Dieser Auszug ist keine Bescheinigung und keine rechtliche Bewertung; bescheinigen
    muss der Schießsportverband (§ 14 Abs. 3 und 4 WaffG).
</p>

<table class="sign">
    <tr>
        <td><div class="line">Ort, Datum</div></td>
        <td><div class="line">Richtigkeit bestätigt: Unterschrift Vorstand, Vereinsstempel</div></td>
    </tr>
</table>
@endsection
