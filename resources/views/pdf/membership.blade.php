@extends('pdf.layout')

@section('title', 'Mitgliedschaftsbescheinigung')

@section('content')
<h1>Bescheinigung über die Mitgliedschaft</h1>
<p class="muted">zur Vorlage bei der Waffenbehörde (§ 14 Abs. 4 Satz 3 WaffG)</p>

<p style="margin-top:8mm">
    Hiermit bestätigen wir, dass
</p>
<table class="meta" style="margin: 3mm 0 3mm 8mm; width:auto">
    <tr><td style="width:38mm">Name</td><td><strong>{{ $user->name }}</strong></td></tr>
    @if($user->member_number)<tr><td>Mitgliedsnummer</td><td>{{ $user->member_number }}</td></tr>@endif
    <tr><td>Mitglied seit</td><td>{{ $user->member_since?->format('d.m.Y') ?? '________________' }}</td></tr>
</table>
<p>
    @if($user->active)
        derzeit Mitglied im Verein „{{ $club }}“ ist.
    @else
        Mitglied im Verein „{{ $club }}“ war. Die Mitgliedschaft ist derzeit nicht aktiv.
    @endif
</p>
<p>
    Der Verein ist Mitglied in einem anerkannten Schießsportverband und betreibt den Schießsport nach dessen
    Sportordnung.
</p>

<table class="sign">
    <tr>
        <td><div class="line">Ort, Datum ({{ $createdAt->format('d.m.Y') }})</div></td>
        <td><div class="line">Unterschrift Vorstand, Vereinsstempel</div></td>
    </tr>
</table>

<p class="note">
    Hinweis: Nach § 14 Abs. 4 Satz 3 WaffG genügt nach Ablauf von zehn Jahren seit der ersten Eintragung
    einer Schusswaffe in die Waffenbesitzkarte für das Fortbestehen des Bedürfnisses die Mitgliedschaft
    in einem Schießsportverein. Die Angaben beruhen auf der Mitgliederverwaltung des Vereins.
</p>
@endsection
