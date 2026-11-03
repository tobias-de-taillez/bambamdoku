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
        {{ $user->member_since ? 'seit dem '.$user->member_since->format('d.m.Y').' ' : '' }}Mitglied im Verein „{{ $club }}“
        und am Ausstellungstag ({{ $createdAt->format('d.m.Y') }}) Mitglied ist.
    @else
        Mitglied im Verein „{{ $club }}“ war. Am Ausstellungstag ({{ $createdAt->format('d.m.Y') }}) ist die
        Mitgliedschaft nicht aktiv.
    @endif
</p>
<p>
    @if($federation)
        Der Verein gehört dem anerkannten Schießsportverband {{ $federation }}
        an{{ $clubNumber ? ' (Vereinsnummer '.$clubNumber.')' : '' }} und betreibt den Schießsport nach dessen
        Sportordnung (§ 14 Abs. 2 WaffG).
    @else
        Der Verein ist Mitglied in einem anerkannten Schießsportverband und betreibt den Schießsport nach dessen
        Sportordnung (§ 14 Abs. 2 WaffG).
    @endif
</p>
<p class="muted">Erstellt am {{ $createdAt->format('d.m.Y') }} von {{ $createdBy }}. Die Bescheinigung enthält keine Schießtermine.</p>

<table class="sign">
    <tr>
        <td><div class="line">Ort, Datum ({{ $createdAt->format('d.m.Y') }})</div></td>
        <td><div class="line">Unterschrift Vorstand, Vereinsstempel</div></td>
    </tr>
</table>

<p class="note">
    Hinweis: Nach § 14 Abs. 4 Satz 3 WaffG genügt nach Ablauf von zehn Jahren seit der ersten Eintragung
    einer Schusswaffe in die Waffenbesitzkarte für das Fortbestehen des Bedürfnisses die Mitgliedschaft
    in einem Schießsportverein. Ob die zehn Jahre erreicht sind, prüft die Behörde; dem Verein liegen dazu keine
    Angaben vor. Die Angaben beruhen auf der Mitgliederverwaltung des Vereins.
</p>
@endsection
