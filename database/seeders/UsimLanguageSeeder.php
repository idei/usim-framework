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
        // $translationService->upsertLanguage('fr', 'French', 'Français', true, false);
        // $translationService->upsertLanguage('de', 'German', 'Deutsch', true, false);
        // $translationService->upsertLanguage('zh', 'Chinese', '中文', true, false);
        // $translationService->upsertLanguage('ja', 'Japanese', '日本語', true, false);
        // $translationService->upsertLanguage('pt', 'Portuguese', 'Português', true, false);
    }
}
