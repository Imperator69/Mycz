<x-filament-widgets::widget>
    <x-filament::section heading="Nächste Fristen" icon="heroicon-o-calendar-days">
        <x-slot name="headerEnd">
            <x-filament::link :href="\App\Filament\Pages\Deadlines::getUrl()" size="sm">Alle Fristen</x-filament::link>
        </x-slot>
        @include('filament.partials.deadline-list', ['items' => $items])
    </x-filament::section>
</x-filament-widgets::widget>
