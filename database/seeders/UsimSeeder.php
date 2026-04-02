<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\UsimRoleSeeder;
use Database\Seeders\UsimUserSeeder;
use Database\Seeders\UsimLanguageSeeder;
use Database\Seeders\UsimTranslationSeeder;

class UsimSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsimRoleSeeder::class,
            UsimUserSeeder::class,
            UsimLanguageSeeder::class,
            UsimTranslationSeeder::class,
        ]);
    }
}
