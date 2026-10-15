<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Jahresbericht {{ $year }}</title>
<style>
    @page { margin: 22mm 16mm; }
    body { font-family: DejaVu Sans, sans-serif; font-size: 9.5pt; color: #1f2933; }
    h1 { font-size: 17pt; margin: 0 0 2mm; }
    h2 { font-size: 12pt; margin: 7mm 0 2mm; border-bottom: 1px solid #cbd2d9; padding-bottom: 1mm; }
    .muted { color: #616e7c; }
    table { width: 100%; border-collapse: collapse; }
    th, td { padding: 1.4mm 1.6mm; text-align: left; vertical-align: top; }
    th { background: #f0f4f8; font-weight: bold; }
    tr:nth-child(even) td { background: #fafbfc; }
    .r { text-align: right; white-space: nowrap; }
    .kpis td { width: 25%; border: 1px solid #e4e7eb; padding: 2.5mm; }
    .kpis .v { font-size: 13pt; font-weight: bold; }
    .neg { color: #b91c1c; }
    .total td { font-weight: bold; border-top: 1px solid #9aa5b1; background: #fff; }
</style>
</head>
<body>
@php($f = fn ($v) => number_format((float) $v, 2, ',', '.').' €')
<h1>Jahresbericht {{ $year }}</h1>
<div class="muted">
    {{ $property ? $property->name.' · '.$property->address() : 'Gesamtportfolio' }} · erstellt am {{ now()->format('d.m.Y') }}
</div>

<h2>Überblick</h2>
<table class="kpis">
    <tr>
        <td>Einnahmen<br><span class="v">{{ $f($summary['income']) }}</span></td>
        <td>Ausgaben<br><span class="v">{{ $f($summary['expense']) }}</span></td>
        <td>Ergebnis<br><span class="v {{ $summary['result'] < 0 ? 'neg' : '' }}">{{ $f($summary['result']) }}</span></td>
        <td>Cashflow<br><span class="v {{ $summary['cashflow'] < 0 ? 'neg' : '' }}">{{ $f($summary['cashflow']) }}</span></td>
    </tr>
    @if ($kpis)
    <tr>
        <td>Bruttorendite<br><span class="v">{{ $kpis['gross_yield'] !== null ? number_format($kpis['gross_yield'], 2, ',', '.').' %' : '–' }}</span></td>
        <td>Nettorendite<br><span class="v">{{ $kpis['net_yield'] !== null ? number_format($kpis['net_yield'], 2, ',', '.').' %' : '–' }}</span></td>
        <td>Restschuld Kredite<br><span class="v">{{ $f($kpis['loan_balance']) }}</span></td>
        <td>Kreditzinsen {{ $year }} (Plan)<br><span class="v">{{ $f($kpis['loan_interest']) }}</span></td>
    </tr>
    @endif
</table>
<p class="muted">Ergebnis = Einnahmen minus Ausgaben ohne Tilgung, Investitionen und Kautionen. Umlagefähige Ausgaben: {{ $f($summary['allocatable_expense']) }}, nicht umlagefähig: {{ $f($summary['non_allocatable_expense']) }}.</p>

<h2>Nach Kategorie</h2>
<table>
    <tr><th>Typ</th><th>Kategorie</th><th class="r">Netto</th><th class="r">USt</th><th class="r">Brutto</th></tr>
    @foreach ($summary['by_category'] as $c)
        <tr>
            <td>{{ $c['type'] === 'income' ? 'Einnahme' : 'Ausgabe' }}</td>
            <td>{{ $c['category'] }}</td>
            <td class="r">{{ $f($c['net']) }}</td>
            <td class="r">{{ $f($c['vat']) }}</td>
            <td class="r">{{ $f($c['gross']) }}</td>
        </tr>
    @endforeach
</table>

<h2>Monatsverlauf</h2>
<table>
    <tr><th>Monat</th><th class="r">Einnahmen</th><th class="r">Ausgaben</th><th class="r">Saldo</th></tr>
    @foreach ($monthly as $m)
        <tr>
            <td>{{ \Illuminate\Support\Carbon::create($year, $m['month'], 1)->translatedFormat('F') }}</td>
            <td class="r">{{ $f($m['income']) }}</td>
            <td class="r">{{ $f($m['expense']) }}</td>
            <td class="r {{ $m['income'] - $m['expense'] < 0 ? 'neg' : '' }}">{{ $f($m['income'] - $m['expense']) }}</td>
        </tr>
    @endforeach
</table>

<h2>Jahresvergleich</h2>
<table>
    <tr><th>Jahr</th><th class="r">Einnahmen</th><th class="r">Ausgaben</th><th class="r">Ergebnis</th><th class="r">Cashflow</th></tr>
    @foreach ($comparison as $y)
        <tr><td>{{ $y['year'] }}</td><td class="r">{{ $f($y['income']) }}</td><td class="r">{{ $f($y['expense']) }}</td><td class="r">{{ $f($y['result']) }}</td><td class="r">{{ $f($y['cashflow']) }}</td></tr>
    @endforeach
</table>

@if ($portfolio)
<h2>Objekte</h2>
<table>
    <tr><th>Objekt</th><th class="r">Einnahmen</th><th class="r">Ausgaben</th><th class="r">Ergebnis</th><th class="r">Brutto-Rendite</th><th class="r">Restschuld</th></tr>
    @foreach ($portfolio as $p)
        <tr>
            <td>{{ $p['name'] }} <span class="muted">({{ $p['type'] }})</span></td>
            <td class="r">{{ $f($p['income']) }}</td>
            <td class="r">{{ $f($p['expense']) }}</td>
            <td class="r">{{ $f($p['result']) }}</td>
            <td class="r">{{ $p['gross_yield'] !== null ? number_format($p['gross_yield'], 2, ',', '.').' %' : '–' }}</td>
            <td class="r">{{ $f($p['loan_balance']) }}</td>
        </tr>
    @endforeach
</table>
@endif

<h2>Buchungen {{ $year }}</h2>
<table>
    <tr><th>Datum</th><th>Objekt</th><th>Kategorie</th><th>Beschreibung</th><th class="r">Brutto</th></tr>
    @foreach ($transactions as $t)
        <tr>
            <td>{{ $t->booked_at->format('d.m.Y') }}</td>
            <td>{{ $t->property?->name ?? 'Allgemein' }}</td>
            <td>{{ $t->category?->name }}</td>
            <td>{{ $t->description }}</td>
            <td class="r {{ $t->type->value === 'expense' ? 'neg' : '' }}">{{ $t->type->value === 'expense' ? '-' : '' }}{{ $f($t->amount_gross) }}</td>
        </tr>
    @endforeach
</table>
</body>
</html>
