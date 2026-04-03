<?php

namespace Database\Seeders;

use Idei\Usim\Services\Support\TranslationService;
use Illuminate\Database\Seeder;

class UsimTranslationSeeder extends Seeder
{
    public function run(): void
    {
        /** @var TranslationService $translationService */
        $translationService = app(TranslationService::class);

        $translations = [
            'app.title' => [
                'group' => 'app',
                'description' => 'Generic application title',
                'values' => [
                    'en' => 'USIM Application',
                    'es' => 'Aplicacion USIM',
                ],
            ],
            'app.welcome' => [
                'group' => 'app',
                'description' => 'Generic welcome message',
                'values' => [
                    'en' => 'Welcome, :name',
                    'es' => 'Bienvenido, :name',
                ],
            ],
            'auth.login.title' => [
                'group' => 'auth',
                'description' => 'Login screen title',
                'values' => [
                    'en' => 'Sign in to your account',
                    'es' => 'Inicia sesion en tu cuenta',
                ],
            ],
            'auth.login.description' => [
                'group' => 'auth',
                'description' => 'Login screen support text',
                'values' => [
                    'en' => 'Enter your credentials to continue.',
                    'es' => 'Ingresa tus credenciales para continuar.',
                ],
            ],
            'ui.empty_state' => [
                'group' => 'ui',
                'description' => 'Generic empty state label',
                'values' => [
                    'en' => 'No records found',
                    'es' => 'No se encontraron registros',
                ],
            ],
            'media.logo' => [
                'group' => 'media',
                'description' => 'Example media key with optional URL and metadata',
                'values' => [
                    'en' => 'USIM logo',
                    'es' => 'Logo de USIM',
                ],
                'media' => [
                    'url' => '/vendor/idei/usim/images/logo.svg',
                    'meta' => [
                        'type' => 'image/svg+xml',
                        'alt' => 'USIM Logo',
                    ],
                ],
            ],
        ];

        foreach ($translations as $key => $definition) {
            $translationService->createOrUpdateKey($key, [
                'group' => $definition['group'] ?? null,
                'needs_review' => false,
                'is_active' => true,
            ]);

            $mediaUrl = $definition['media']['url'] ?? null;
            $mediaMeta = $definition['media']['meta'] ?? null;

            foreach (($definition['values'] ?? []) as $langCode => $textValue) {
                $translationService->upsertValue(
                    $key,
                    $langCode,
                    $textValue,
                    $mediaUrl,
                    $mediaMeta
                );
            }
        }
    }
}
