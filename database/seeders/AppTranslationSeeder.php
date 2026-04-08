<?php

namespace Database\Seeders;

use Idei\Usim\Services\Support\TranslationService;
use Illuminate\Database\Seeder;

class AppTranslationSeeder extends Seeder
{
    public function run(): void
    {
        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);
        $basePath = database_path('translations');

        // Cargar archivo en JSON
        $enFile ="$basePath/en.json";
        if (file_exists($enFile)) {
            $enTranslations = json_decode(file_get_contents($enFile), true);
        } else {
            $this->command?->error("File not found: {$enFile}");
            return;
        }

        if (!\is_array($enTranslations)) {
            $this->command?->error("Invalid JSON content in: {$enFile}");
            return;
        }

        // Cargar archivo es JSON
        $esFile = "$basePath/es.json";
        if (file_exists($esFile)) {
            $esTranslations = json_decode(file_get_contents($esFile), true);
        } else {
            $this->command?->error("File not found: {$esFile}");
            return;
        }

        if (!\is_array($esTranslations)) {
            $this->command?->error("Invalid JSON content in: {$esFile}");
            return;
        }

        $translations = [];

        // Procesar y registrar traducciones
        foreach ($enTranslations as $key => $enValue) {
            $esValue = $esTranslations[$key] ?? $enValue;

            // Extraer grupo de la clave (primera parte antes del primer punto)
            $parts = \explode('.', $key);
            $group = \count($parts) >= 2 ? $parts[0] . '.' . $parts[1] : $parts[0];

            $translations[$key] = [
                'group' => $group,
                'description' => \ucfirst(\str_replace('_', ' ', \str_replace("$group.", '', $key))),
                'values' => [
                    'en' => $enValue,
                    'es' => $esValue,
                ],
            ];
        }

        // Guardar todas las traducciones usando el servicio
        foreach ($translations as $key => $data) {
            $translationService->createOrUpdateKey($key, [
                'group' => $data['group'],
                'is_active' => true,
            ]);

            foreach ($data['values'] as $langCode => $textValue) {
                $translationService->upsertValue(
                    $key,
                    $langCode,
                    \is_scalar($textValue) ? (string) $textValue : ''
                );
            }
        }

        $this->command?->info('App translations seeded successfully: ' . \count($translations) . ' keys registered.');
    }
}
