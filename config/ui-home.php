<?php

return [
    'images' => [
        'base_path' => 'images/ui/home',
    ],

    'hero' => [
        'welcome_key' => 'home.hero.welcome',
        'subtitle_key' => 'home.hero.subtitle',
    ],

    'features' => [
        'title_key' => 'home.features.title',
        'gap' => '20px',
        'cards' => [
            [
                'name' => 'components_card',
                'title_key' => 'home.cards.components.title',
                'description_key' => 'home.cards.components.description',
                'image' => 'modern_components.png',
                'image_position' => 'top',
                'image_alt_key' => 'home.cards.components.image_alt',
                'theme' => 'primary',
                'elevation' => 'medium',
                'actions' => [
                    [
                        'label_key' => 'home.actions.view_demos',
                        'action' => 'view_demos',
                        'parameters' => [],
                        'style' => 'primary',
                    ],
                ],
            ],
            [
                'name' => 'easy_card',
                'title_key' => 'home.cards.easy.title',
                'description_key' => 'home.cards.easy.description',
                'image' => 'easy_to_use.png',
                'image_position' => 'top',
                'image_alt_key' => 'home.cards.easy.image_alt',
                'theme' => 'success',
                'elevation' => 'medium',
                'actions' => [
                    [
                        'label_key' => 'home.actions.view_code',
                        'action' => 'view_code',
                        'parameters' => [],
                        'style' => 'success',
                    ],
                ],
            ],
            [
                'name' => 'custom_card',
                'title_key' => 'home.cards.custom.title',
                'description_key' => 'home.cards.custom.description',
                'image' => 'customizable.png',
                'image_position' => 'top',
                'image_alt_key' => 'home.cards.custom.image_alt',
                'theme' => 'warning',
                'elevation' => 'medium',
                'actions' => [
                    [
                        'label_key' => 'home.actions.customize',
                        'action' => 'customize',
                        'parameters' => [],
                        'style' => 'warning',
                    ],
                ],
            ],
        ],
    ],

    'getting_started' => [
        'title_key' => 'home.getting_started.title',
        'cards' => [
            [
                'name' => 'getting_started_card',
                'title_key' => 'home.cards.getting_started.title',
                'description_key' => 'home.cards.getting_started.description',
                'image' => 'explore_demos.png',
                'image_position' => 'top',
                'image_alt_key' => 'home.cards.getting_started.image_alt',
                'style' => 'elevated',
                'size' => 'large',
                'theme' => 'primary',
                'elevation' => 'medium',
                'actions' => [
                    [
                        'label_key' => 'home.actions.view_all_demos',
                        'action' => 'view_all_demos',
                        'parameters' => [],
                        'style' => 'primary',
                    ],
                    [
                        'label_key' => 'home.actions.view_docs',
                        'action' => 'view_docs',
                        'parameters' => [],
                        'style' => 'info',
                    ],
                ],
            ],
        ],
    ],
];
