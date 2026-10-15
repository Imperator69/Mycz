@if ($items->isEmpty())
    <p class="text-sm text-gray-500">Keine Fristen im gewählten Zeitraum.</p>
@else
    <ul class="divide-y divide-gray-100 dark:divide-white/5">
        @foreach ($items as $item)
            @php($d = (int) today()->diffInDays($item['date'], false))
            <li class="flex items-center gap-x-3 py-2">
                <span @class(['mycz-date', 'is-overdue' => $item['overdue'], 'is-soon' => ! $item['overdue'] && $d <= 14])>{{ $item['date']->format('d.m.Y') }}</span>
                <div class="min-w-0 flex-1">
                    @if ($item['url'])
                        <a href="{{ $item['url'] }}" class="text-sm font-medium text-gray-950 hover:underline dark:text-white">{{ $item['title'] }}</a>
                    @else
                        <span class="text-sm font-medium text-gray-950 dark:text-white">{{ $item['title'] }}</span>
                    @endif
                    <div class="text-xs text-gray-500">{{ $item['category'] }}{{ $item['property'] ? ' · '.$item['property'] : '' }}</div>
                </div>
                <span @class(['text-xs whitespace-nowrap', 'mycz-neg' => $item['overdue'], 'text-gray-500' => ! $item['overdue']])>
                    {{ $d === 0 ? 'heute' : ($d > 0 ? 'in '.$d.' Tagen' : 'seit '.abs($d).' Tagen') }}
                </span>
            </li>
        @endforeach
    </ul>
@endif
