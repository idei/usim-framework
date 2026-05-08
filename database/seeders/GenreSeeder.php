<?php

namespace Database\Seeders;

use App\Models\Genre;
use Idei\Usim\Support\TranslationService;
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

        $translationService = app(TranslationService::class);

        foreach ($genres as $name) {
            $translationKey = 'genre.' . str()->slug($name);
            Genre::firstOrCreate(['name' => $translationKey]);
            $translationService->upsertFallbackValue($translationKey, $name);
        }
    }
}
