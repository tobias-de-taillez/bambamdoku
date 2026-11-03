@extends('pdf.layout')

@section('title', 'Teilnahmenachweis aus der Schießkladde')

@section('content')
<h1>Teilnahmenachweis aus der Schießkladde</h1>
@if(! $available)
    <p>Teilnahmenachweis nicht verfügbar, solange kein Disziplinkatalog gewählt ist.</p>
@else
<p class="muted" style="margin-top:0">
    {{ $forInformation ? 'zur Information des Mitglieds – Nachweiswert erst mit Unterschrift und Stempel des Vorstands' : 'zur Vorlage beim Verband' }}
</p>
<table class="meta">
    <tr><td style="width:42mm">Verein</td><td>{{ $club }}@if($clubNumber) (Vereinsnummer {{ $clubNumber }})@endif</td></tr>
    @if($federation)<tr><td>Verband</td><td>{{ $federation }}</td></tr>@endif
    <tr><td>Mitglied</td><td><strong>{{ $member->name }}</strong>@if($member->member_number), Mitgliedsnummer {{ $member->member_number }}@endif</td></tr>
    <tr><td>Disziplin</td><td><strong>{{ $discipline }}</strong> {{ $disciplineName }}</td></tr>
    <tr><td>Zeitraum</td><td>{{ $from->format('d.m.Y') }} bis {{ $to->format('d.m.Y') }}</td></tr>
    <tr><td>Erstellt</td><td>{{ $createdAt->format('d.m.Y, H:i') }} Uhr von {{ $createdBy }}</td></tr>
</table>
@if($digitalSince)
    <p class="note" style="margin-top:3mm">Digitale Aufzeichnung seit {{ \Carbon\CarbonImmutable::parse($digitalSince)->format('d.m.Y') }}; frühere Termine siehe Papierkladde.</p>
@endif

<h2>Teilnahme an {{ $days }} {{ $days === 1 ? 'Tag' : 'Tagen' }}</h2>
@if(count($entries) === 0)
    <p class="muted">Im Zeitraum sind für diese Disziplin keine Termine in der Kladde.</p>
@else
<table>
    <thead><tr><th>Datum</th><th>Kategorie</th><th>Waffe</th><th class="num">Schüsse</th><th>Erfasst über</th><th>Bestätigt von</th></tr></thead>
    <tbody>
    @foreach($entries as $e)
        <tr>
            <td>{{ \Carbon\CarbonImmutable::parse($e['date'])->format('d.m.Y') }}</td>
            <td>{{ $e['weapon_label'] }}</td>
            <td>{{ $e['weapon_owner'] ?? '–' }}</td>
            <td class="num">{{ $e['shots'] ?? '–' }}</td>
            <td>{{ $e['via'] }}</td>
            <td>{{ $e['confirmed_by'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<p class="note">
    Der Vorstand bestätigt die Teilnahme an den genannten Tagen laut Schießkladde des Vereins. Keine Bescheinigung
    nach § 14 WaffG, keine rechtliche Bewertung; bescheinigen muss der Verband. Stornierte und nicht abgezeichnete
    Einträge fehlen, ebenso Termine ohne Angabe der Disziplin.
</p>

<table class="sign">
    <tr>
        <td><div class="line">Ort, Datum</div></td>
        <td><div class="line">Teilnahme bestätigt: Unterschrift Vorstand, Vereinsstempel</div></td>
    </tr>
</table>
@endif
@endsection
