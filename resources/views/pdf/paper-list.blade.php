@extends('pdf.layout')

@section('title', 'Papier-Ersatzliste')

@section('content')
<h1>Papier-Ersatzliste Schießkladde</h1>
<p class="muted" style="margin-top:0">Nur wenn am Stand nicht digital eingetragen werden kann. Der Vorstand überträgt die Liste als Nachtrag.</p>
<table class="meta">
    <tr><td style="width:42mm">Datum</td><td>________________________</td></tr>
    <tr><td>Aufsicht (Name)</td><td>________________________________________</td></tr>
    <tr><td>Grund</td><td>☐ kein Netz &nbsp; ☐ Server nicht erreichbar &nbsp; ☐ Gerät defekt &nbsp; ☐ ____________</td></tr>
</table>

<table style="margin-top:6mm">
    <thead>
        <tr>
            <th style="width:6mm">#</th>
            <th>Name oder Mitgliedsnummer (Gast: „Gast“ + Name)</th>
            <th style="width:30mm">Kategorie<br><span class="muted">K / L / frei</span></th>
            <th style="width:24mm">Waffe<br><span class="muted">eigen / Verein</span></th>
            <th style="width:28mm">Disziplin</th>
            <th style="width:16mm">volljährig<br><span class="muted">(Gast)</span></th>
        </tr>
    </thead>
    <tbody>
    @for($i = 1; $i <= 14; $i++)
        <tr style="height:8.5mm"><td class="muted">{{ $i }}</td><td></td><td></td><td></td><td></td><td></td></tr>
    @endfor
    </tbody>
</table>

<table class="sign">
    <tr>
        <td><div class="line">Unterschrift Aufsicht</div></td>
        <td><div class="line">Übertragen am / von (Vorstand)</div></td>
    </tr>
</table>
<p class="note">Minderjährige weiter wie bisher auf Papier mit Einwilligung (§ 27 Abs. 3 WaffG). Mitglieder tragen sich ein, die Aufsicht zeichnet die Liste ab; der Vorstand überträgt sie als Nachtrag.</p>
@endsection
