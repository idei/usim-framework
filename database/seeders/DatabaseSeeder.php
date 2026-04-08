<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            // USIM framework seeders
            UsimLanguageSeeder::class,
            UsimTranslationSeeder::class,
            UsimRoleSeeder::class,
            UsimUserSeeder::class,

            // App-specific seeders
            RoleSeeder::class,
            UserSeeder::class,
            AppTranslationSeeder::class,
        ]);
    }
}
