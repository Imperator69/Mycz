<?php

use Database\Seeders\CategorySeeder;
use Illuminate\Database\Migrations\Migration;

/** Standardkategorien auch in Produktion ohne Seeder-Aufruf anlegen. */
return new class extends Migration
{
    public function up(): void
    {
        (new CategorySeeder)->run();
    }

    public function down(): void
    {
        //
    }
};
