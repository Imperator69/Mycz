<x-filament-panels::page>
    @php($f = fn ($v) => eur($v))
    @php($pct = fn ($v) => $v === null ? '–' : number_format($v, 2, ',', '.').' %')
    @php($sign = fn ($v) => $v < 0 ? 'mycz-neg' : '')

    <div class="mycz-filters">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="year">
                @foreach ($years as $y)
                    <option value="{{ $y }}">{{ $y }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="property">
                <option value="">Gesamtportfolio</option>
                @foreach ($properties as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    <div class="mycz-grid-4">
        @foreach ([
            ['Einnahmen', $summary['income'], 'mycz-pos', 'netto '.$f($summary['income_net'])],
            ['Ausgaben', $summary['expense'], 'mycz-neg', 'netto '.$f($summary['expense_net'])],
            ['Ergebnis', $summary['result'], $summary['result'] >= 0 ? 'mycz-pos' : 'mycz-neg', 'ohne Tilgung, Investitionen, Kautionen'],
            ['Cashflow', $summary['cashflow'], $summary['cashflow'] >= 0 ? 'mycz-pos' : 'mycz-neg', 'alle Zahlungen'],
        ] as [$label, $value, $color, $hint])
            <x-filament::section>
                <div class="mycz-kpi-label">{{ $label }} {{ $year }}</div>
                <div class="mycz-kpi-value {{ $color }}">{{ $f($value) }}</div>
                <div class="mycz-kpi-hint">{{ $hint }}</div>
            </x-filament::section>
        @endforeach
    </div>

    @if ($kpis)
        <div class="mycz-grid-4">
            <x-filament::section>
                <div class="mycz-kpi-label">Bruttorendite</div>
                <div class="mycz-kpi-value">{{ $pct($kpis['gross_yield']) }}</div>
                <div class="mycz-kpi-hint">Nettomiete p.a. / Kaufpreis inkl. Nebenkosten</div>
            </x-filament::section>
            <x-filament::section>
                <div class="mycz-kpi-label">Nettorendite</div>
                <div class="mycz-kpi-value">{{ $pct($kpis['net_yield']) }}</div>
                <div class="mycz-kpi-hint">(Einnahmen - nicht umlagef. Kosten) / Investition</div>
            </x-filament::section>
            <x-filament::section>
                <div class="mycz-kpi-label">Restschuld</div>
                <div class="mycz-kpi-value">{{ $f($kpis['loan_balance']) }}</div>
                <div class="mycz-kpi-hint">Zinsen {{ $year }} lt. Plan: {{ $f($kpis['loan_interest']) }}</div>
            </x-filament::section>
            <x-filament::section>
                <div class="mycz-kpi-label">Offene Mieten</div>
                <div class="mycz-kpi-value {{ $kpis['open_rent'] > 0 ? 'mycz-neg' : '' }}">{{ $f($kpis['open_rent']) }}</div>
                <div class="mycz-kpi-hint">Soll-Miete p.a.: {{ $f($kpis['annual_rent_target']) }}</div>
            </x-filament::section>
        </div>
    @endif

    @livewire(\App\Filament\Widgets\IncomeExpenseChart::class, ['propertyIds' => $chartIds, 'filter' => (string) $year], key('chart-'.$year.'-'.($selected?->id ?? 'all')))

    <div class="mycz-grid-2">
        <x-filament::section heading="Nach Kategorie">
            <table class="mycz-table">
                <thead><tr><th>Kategorie</th><th class="num">Netto</th><th class="num">Brutto</th></tr></thead>
                <tbody>
                    @forelse ($summary['by_category'] as $c)
                        <tr>
                            <td>
                                <span @class(['font-medium', 'mycz-pos' => $c['type'] === 'income'])>{{ $c['category'] }}</span>
                                <span class="text-xs text-gray-500">({{ $c['count'] }})</span>
                            </td>
                            <td class="num">{{ $f($c['net']) }}</td>
                            <td class="num">{{ $f($c['gross']) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-gray-500">Keine Buchungen im Jahr {{ $year }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <p class="mycz-kpi-hint" style="margin-top: .75rem">Umlagefähige Ausgaben: {{ $f($summary['allocatable_expense']) }} · nicht umlagefähig: {{ $f($summary['non_allocatable_expense']) }}.</p>
        </x-filament::section>

        <x-filament::section heading="Jahresvergleich">
            <table class="mycz-table">
                <thead><tr><th>Jahr</th><th class="num">Einnahmen</th><th class="num">Ausgaben</th><th class="num">Ergebnis</th><th class="num">Ausgaben ggü. Vorjahr</th></tr></thead>
                <tbody>
                    @foreach ($comparison as $i => $y)
                        @php($prev = $comparison[$i - 1] ?? null)
                        <tr>
                            <td class="font-medium">{{ $y['year'] }}</td>
                            <td class="num">{{ $f($y['income']) }}</td>
                            <td class="num">{{ $f($y['expense']) }}</td>
                            <td class="num {{ $sign($y['result']) }}">{{ $f($y['result']) }}</td>
                            <td class="num text-gray-500">
                                @if ($prev && $prev['expense'] > 0)
                                    {{ ($y['expense'] >= $prev['expense'] ? '+' : '').number_format(($y['expense'] - $prev['expense']) / $prev['expense'] * 100, 1, ',', '.') }} %
                                @else
                                    –
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-filament::section>
    </div>

    @if ($portfolio)
        <x-filament::section heading="Objekte im Vergleich">
            <div class="overflow-x-auto">
                <table class="mycz-table">
                    <thead><tr>
                        <th>Objekt</th><th class="num">Einnahmen</th><th class="num">Ausgaben</th><th class="num">Ergebnis</th>
                        <th class="num">Brutto-Rendite</th><th class="num">Netto-Rendite</th><th class="num">Restschuld</th><th class="num">Offen</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($portfolio as $p)
                            <tr>
                                <td>
                                    <button type="button" wire:click="$set('property', {{ $p['property_id'] }})" class="font-medium text-primary-600 hover:underline">{{ $p['name'] }}</button>
                                    <div class="text-xs text-gray-500">{{ $p['type'] }} · {{ $p['status'] }}</div>
                                </td>
                                <td class="num">{{ $f($p['income']) }}</td>
                                <td class="num">{{ $f($p['expense']) }}</td>
                                <td class="num {{ $sign($p['result']) }}">{{ $f($p['result']) }}</td>
                                <td class="num">{{ $pct($p['gross_yield']) }}</td>
                                <td class="num">{{ $pct($p['net_yield']) }}</td>
                                <td class="num">{{ $f($p['loan_balance']) }}</td>
                                <td class="num {{ $p['open_rent'] > 0 ? 'mycz-neg' : '' }}">{{ $f($p['open_rent']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
