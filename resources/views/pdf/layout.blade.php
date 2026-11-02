<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>@yield('title')</title>
<style>
    @page { margin: 22mm 18mm 20mm 18mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #111; line-height: 1.4; }
    h1 { font-size: 15pt; margin: 0 0 2mm; }
    h2 { font-size: 11pt; margin: 6mm 0 2mm; }
    .club { font-size: 9pt; color: #555; border-bottom: 0.4pt solid #999; padding-bottom: 2mm; margin-bottom: 5mm; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; font-size: 8.5pt; color: #444; border-bottom: 0.6pt solid #333; padding: 1.5mm 1.5mm; }
    td { border-bottom: 0.3pt solid #ccc; padding: 1.4mm 1.5mm; vertical-align: top; }
    .meta td { border: none; padding: 0.6mm 0; }
    .muted { color: #666; }
    .ok { color: #116329; font-weight: bold; }
    .no { color: #9a1c1c; font-weight: bold; }
    .note { font-size: 8pt; color: #555; margin-top: 5mm; }
    .sign { margin-top: 16mm; width: 100%; }
    .sign td { border: none; padding-top: 0; width: 50%; }
    .line { border-top: 0.5pt solid #333; padding-top: 1mm; font-size: 8pt; color: #444; }
    .num { text-align: right; }
</style>
</head>
<body>
<div class="club">{{ $club }}@if($clubAddress) · {{ $clubAddress }}@endif</div>
@yield('content')
</body>
</html>
