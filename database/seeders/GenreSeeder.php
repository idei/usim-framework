<?php

namespace Database\Seeders;

use App\Models\Genre;
use Illuminate\Database\Seeder;

class GenreSeeder extends Seeder
{
    public function run(): void
    {
        $genres = [
            'Drama',
            'Crime',
            'Action',
            'Sci-Fi',
            'Fantasy',
            'Animation',
            'Adventure',
            'Romance',
            'Thriller',
        ];

        foreach ($genres as $name) {
            Genre::firstOrCreate(['name' => $name]);
        }
    }
}
