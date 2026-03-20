<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Database\Seeders\UsimRoleSeeder;
use Database\Seeders\UsimUserSeeder;

class UsimSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsimRoleSeeder::class,
            UsimUserSeeder::class,
        ]);
    }
}
