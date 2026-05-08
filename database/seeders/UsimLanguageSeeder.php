<?php

namespace Database\Seeders;

use Idei\Usim\Support\TranslationService;
use Illuminate\Database\Seeder;

class UsimLanguageSeeder extends Seeder
{
    public function run(): void
    {
        $translationService = app(TranslationService::class);

        $translationService->upsertLanguage('en', 'English', 'English', true);
        $translationService->upsertLanguage('es', 'Spanish', 'Espanol', true);
        $translationService->upsertLanguage('it', 'Italian', 'Italiano', true);
        $translationService->upsertLanguage('fr', 'French', 'Français', false);
        $translationService->upsertLanguage('de', 'German', 'Deutsch', false);
        $translationService->upsertLanguage('zh', 'Chinese', '中文', false);
        $translationService->upsertLanguage('ja', 'Japanese', '日本語', false);
        $translationService->upsertLanguage('pt', 'Portuguese', 'Português', false);
    }
}
