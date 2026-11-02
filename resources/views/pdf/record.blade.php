@extends('pdf.layout')

@section('title', 'Auszug aus der Schießkladde')

@section('content')
<h1>Auszug aus der Schießkladde</h1>
<table class="meta">
    <tr><td style="width:38mm">Mitglied</td><td><strong>{{ $member['name'] }}</strong></td></tr>
    @if($member['member_number'])<tr><td>Mitgliedsnummer</td><td>{{ $member['member_number'] }}</td></tr>@endif
    @if($member['member_since'])<tr><td>Mitglied seit</td><td>{{ \Carbon\CarbonImmutable::parse($member['member_since'])->format('d.m.Y') }}</td></tr>@endif
    <tr><td>Zeitraum</td><td>{{ $from->format('d.m.Y') }} bis {{ $to->format('d.m.Y') }}</td></tr>
    <tr><td>Erstellt</td><td>{{ $createdAt->format('d.m.Y, H:i') }} Uhr</td></tr>
</table>

<h2>Schießtermine ({{ count($entries) }})</h2>
@if(count($entries) === 0)
    <p class="muted">Im Zeitraum sind keine bestätigten Schießtermine erfasst.</p>
@else
<table>
    <thead><tr><th>Datum</th><th>Zeit</th><th>Stand</th><th>Waffenart</th><th>Waffe</th><th>Bestätigt von</th></tr></thead>
    <tbody>
    @foreach(collect($entries)->sortBy(fn ($e) => $e['date'].$e['starts_at']) as $e)
        <tr>
            <td>{{ \Carbon\CarbonImmutable::parse($e['date'])->format('d.m.Y') }}</td>
            <td>{{ $e['starts_at'] }}–{{ $e['ends_at'] }}</td>
            <td>{{ $e['range'] }}</td>
            <td>{{ $e['weapon_label'] }}</td>
            <td>{{ $e['club_weapon'] ? 'Vereinswaffe' : 'eigene' }}</td>
            <td>{{ $e['confirmed_by'] ?? '–' }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
@endif

<h2>Auswertung nach § 14 WaffG (Stand {{ \Carbon\CarbonImmutable::parse($acquisition['to'])->format('d.m.Y') }})</h2>
<table>
    <thead><tr><th>Prüfung</th><th>Ergebnis</th><th class="num">Status</th></tr></thead>
    <tbody>
    <tr>
        <td>Erwerb, § 14 Abs. 3 Nr. 2: letzte 12 Monate jeden Monat oder 18-mal (erlaubnispflichtige Waffen)</td>
        <td>{{ $acquisition['months_covered'] }} von 12 Monaten, {{ $acquisition['total'] }} Termine</td>
        <td class="num {{ $acquisition['fulfilled'] ? 'ok' : 'no' }}">{{ $acquisition['fulfilled'] ? 'erfüllt' : 'nicht erfüllt' }}</td>
    </tr>
    @foreach($possession as $p)
    <tr>
        <td>Besitz {{ $p['label'] }}, § 14 Abs. 4: 24 Monate, eigene Waffe, alle 3 Monate oder 6-mal in 12 Monaten</td>
        <td>{{ $p['quarters_covered'] }} von 8 Quartalen, bestes Jahr {{ $p['best_twelve_months'] }}-mal</td>
        <td class="num {{ $p['fulfilled'] ? 'ok' : 'no' }}">{{ $p['fulfilled'] ? 'erfüllt' : ($p['total'] === 0 ? 'keine Termine' : 'nicht erfüllt') }}</td>
    </tr>
    @endforeach
    </tbody>
</table>

<p class="note">
    Jeder Termin wurde am Schießtag von der eingeteilten Standaufsicht bestätigt (§ 27 WaffG).
    Die Auswertung dient der Orientierung; maßgeblich ist die Bescheinigung des Schießsportverbands
    nach § 14 Abs. 3 bzw. 4 WaffG. Ein Termin zählt je Tag einmal.
</p>

<table class="sign">
    <tr>
        <td><div class="line">Ort, Datum</div></td>
        <td><div class="line">Unterschrift Vorstand / Schießleitung, Vereinsstempel</div></td>
    </tr>
</table>
@endsection
