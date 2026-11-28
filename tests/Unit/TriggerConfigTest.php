<?php

use Idei\Usim\Navigation\TriggerConfig;
use Idei\Usim\UI;

describe('TriggerConfig::fromArray', function () {
    it('hydrates all valid combinations, defaults, and unexpected types deterministically', function (array $input, array $expected) {
        $config = TriggerConfig::fromArray($input);

        expect($config)->toBeInstanceOf(TriggerConfig::class)
            ->and($config->label)->toBe($expected['label'])
            ->and($config->icon)->toBe($expected['icon'])
            ->and($config->image)->toBe($expected['image'])
            ->and($config->alt)->toBe($expected['alt'])
            ->and($config->style)->toBe($expected['style'])
            ->and($config->toArray())->toBe($expected);
    })->with([
        'empty array defaults' => [
            [],
            [
                'label' => null,
                'icon' => null,
                'image' => null,
                'alt' => null,
                'style' => 'default',
            ],
        ],
        'text and icon trigger' => [
            [
                'label' => 'Actions',
                'icon' => 'bolt',
                'style' => 'primary',
            ],
            [
                'label' => 'Actions',
                'icon' => 'bolt',
                'image' => null,
                'alt' => null,
                'style' => 'primary',
            ],
        ],
        'image trigger with alt and label' => [
            [
                'label' => 'Jane Doe',
                'image' => 'https://example.com/avatar.png',
                'alt' => 'Jane Avatar',
                'style' => 'rounded',
            ],
            [
                'label' => 'Jane Doe',
                'icon' => null,
                'image' => 'https://example.com/avatar.png',
                'alt' => 'Jane Avatar',
                'style' => 'rounded',
            ],
        ],
        'all fields populated plus extra unknown keys ignored' => [
            [
                'label' => 'Menu',
                'icon' => 'bars',
                'image' => '/img/logo.svg',
                'alt' => 'Brand',
                'style' => 'ghost',
                'unknown_key' => 'ignored',
                'nested' => ['foo' => 'bar'],
            ],
            [
                'label' => 'Menu',
                'icon' => 'bars',
                'image' => '/img/logo.svg',
                'alt' => 'Brand',
                'style' => 'ghost',
            ],
        ],
        'explicit nulls fall back to defaults' => [
            [
                'label' => null,
                'icon' => null,
                'image' => null,
                'alt' => null,
                'style' => null,
            ],
            [
                'label' => null,
                'icon' => null,
                'image' => null,
                'alt' => null,
                'style' => 'default',
            ],
        ],
        'empty strings are preserved as valid strings' => [
            [
                'label' => '',
                'icon' => '',
                'image' => '',
                'alt' => '',
                'style' => '',
            ],
            [
                'label' => '',
                'icon' => '',
                'image' => '',
                'alt' => '',
                'style' => '',
            ],
        ],
        'non-string scalar and compound values fall back safely' => [
            [
                'label' => 123,
                'icon' => true,
                'image' => ['not-a-string'],
                'alt' => 45.6,
                'style' => new stdClass,
            ],
            [
                'label' => null,
                'icon' => null,
                'image' => null,
                'alt' => null,
                'style' => 'default',
            ],
        ],
        'mixed valid strings and invalid types' => [
            [
                'label' => 'Settings',
                'icon' => false,
                'image' => '/icons/gear.png',
                'alt' => ['invalid'],
                'style' => 999,
            ],
            [
                'label' => 'Settings',
                'icon' => null,
                'image' => '/icons/gear.png',
                'alt' => null,
                'style' => 'default',
            ],
        ],
    ]);

    it('preserves round-trip equality with toArray across factory constructors', function (TriggerConfig $original) {
        $serialized = $original->toArray();
        $rehydrated = TriggerConfig::fromArray($serialized);

        expect($rehydrated)->toEqual($original)
            ->and($rehydrated->toArray())->toBe($serialized);
    })->with([
        'default make()' => [fn () => TriggerConfig::make()],
        'custom text/icon make()' => [fn () => TriggerConfig::make('Admin', 'shield', 'secondary')],
        'image() with defaults' => [fn () => TriggerConfig::image('https://example.com/a.jpg')],
        'image() with all arguments' => [fn () => TriggerConfig::image('/avatar.webp', 'Profile', 'Alice', 'outline')],
        'direct constructor with nulls' => [fn () => new TriggerConfig],
    ]);

    it('applies hydrated text and image triggers to MenuDropdown with proper fallbacks', function () {
        $textDropdown = UI::menuDropdown('trigger_cfg_text');
        TriggerConfig::fromArray([])->applyTo($textDropdown);
        $textJson = $textDropdown->toJson()[$textDropdown->getId()]['trigger'] ?? null;
        expect($textJson)->toBeArray();
        assert(is_array($textJson));

        expect($textJson['label'] ?? null)->toBe('☰')
            ->and($textJson['style'] ?? null)->toBe('default');

        $imageDropdown = UI::menuDropdown('trigger_cfg_img');
        TriggerConfig::fromArray(['image' => '/avatar.png', 'label' => 'Profile'])->applyTo($imageDropdown);
        $imageJson = $imageDropdown->toJson()[$imageDropdown->getId()]['trigger'] ?? null;
        expect($imageJson)->toBeArray();
        assert(is_array($imageJson));

        expect($imageJson['image'] ?? null)->toBe('/avatar.png')
            ->and($imageJson['alt'] ?? null)->toBe('Profile')
            ->and($imageJson['label'] ?? null)->toBe('Profile');

        $imageDefaultAltDropdown = UI::menuDropdown('trigger_cfg_img_default_alt');
        TriggerConfig::fromArray(['image' => '/anon.png'])->applyTo($imageDefaultAltDropdown);
        $imageDefaultAltJson = $imageDefaultAltDropdown->toJson()[$imageDefaultAltDropdown->getId()]['trigger'] ?? null;
        expect($imageDefaultAltJson)->toBeArray();
        assert(is_array($imageDefaultAltJson));

        expect($imageDefaultAltJson['image'] ?? null)->toBe('/anon.png')
            ->and($imageDefaultAltJson['alt'] ?? null)->toBe('User');
    });
});
