<?php

namespace Database\Seeders;

use Idei\Usim\Support\TranslationService;
use Illuminate\Database\Seeder;

class UsimLanguageSeeder extends Seeder
{
    public function run(): void
    {
        $translationService = app(TranslationService::class);

        $translationService->upsertLanguage('en', 'English', 'English', true, true);
        $translationService->upsertLanguage('es', 'Spanish', 'Espanol', true, false);
        $translationService->upsertLanguage('it', 'Italian', 'Italiano', false, false);
        $translationService->upsertLanguage('fr', 'French', 'Français', false, false);
        $translationService->upsertLanguage('de', 'German', 'Deutsch', false, false);
        $translationService->upsertLanguage('zh', 'Chinese', '中文', false, false);
        $translationService->upsertLanguage('ja', 'Japanese', '日本語', false, false);
        $translationService->upsertLanguage('pt', 'Portuguese', 'Português', false, false);
    }
}
