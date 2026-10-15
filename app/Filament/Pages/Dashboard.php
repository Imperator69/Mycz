<?php

namespace App\Filament\Pages;

use Filament\Pages\Dashboard as BaseDashboard;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationGroup = 'Übersicht';

    protected static ?string $title = 'Übersicht';

    protected static ?int $navigationSort = 0;

    public function getColumns(): int|string|array
    {
        return 2;
    }
}
