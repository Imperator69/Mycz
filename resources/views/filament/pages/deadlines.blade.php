<x-filament-panels::page>
    <div class="mycz-filters">
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="days">
                <option value="30">Nächste 30 Tage</option>
                <option value="90">Nächste 90 Tage</option>
                <option value="180">Nächste 6 Monate</option>
                <option value="365">Nächste 12 Monate</option>
            </x-filament::input.select>
        </x-filament::input.wrapper>
        <x-filament::input.wrapper>
            <x-filament::input.select wire:model.live="category">
                <option value="">Alle Bereiche</option>
                @foreach ($categories as $c)
                    <option value="{{ $c }}">{{ $c }}</option>
                @endforeach
            </x-filament::input.select>
        </x-filament::input.wrapper>
    </div>

    @forelse ($groups as $label => $items)
        <x-filament::section :heading="$label" :icon="$label === 'Überfällig' ? 'heroicon-o-exclamation-circle' : 'heroicon-o-calendar'">
            @include('filament.partials.deadline-list', ['items' => $items])
        </x-filament::section>
    @empty
        <x-filament::section>
            <p class="text-sm text-gray-500">Keine Fristen im gewählten Zeitraum. Fristen entstehen automatisch aus Versicherungen, Verträgen, Mietverträgen (Befristung, Wertsicherung), Krediten (Zinsbindung), Wartungen und eigenen Erinnerungen.</p>
        </x-filament::section>
    @endforelse
</x-filament-panels::page>
