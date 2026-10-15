<?php

namespace App\Providers;

use Filament\Forms\Components\DatePicker;
use Filament\Infolists\Infolist;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        Table::$defaultCurrency = 'EUR';
        Table::$defaultDateDisplayFormat = 'd.m.Y';
        Table::$defaultDateTimeDisplayFormat = 'd.m.Y H:i';
        Table::$defaultNumberLocale = 'de_AT';
        Infolist::$defaultCurrency = 'EUR';
        Infolist::$defaultDateDisplayFormat = 'd.m.Y';
        Infolist::$defaultDateTimeDisplayFormat = 'd.m.Y H:i';
        Infolist::$defaultNumberLocale = 'de_AT';

        DatePicker::configureUsing(fn (DatePicker $picker) => $picker->native(false)->displayFormat('d.m.Y')->firstDayOfWeek(1));
    }
}
