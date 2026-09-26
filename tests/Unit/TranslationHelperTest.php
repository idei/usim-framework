<?php

use App\UI\Screens\Demo\Events\Main;
use Idei\Usim\Support\Translation\TranslationResolver;

it('resolves translations for nested files beyond 3 directory levels', function () {
    expect(t('screen.demo.events.main.menu_title', [], 'en'))->toBe('Events');
    expect(t('screen.demo.events.main.icon', [], 'en'))->toBe('📣');

    expect(t('screen.demo.events.main.menu_title', [], 'es'))->toBe('Eventos');
    expect(t('screen.demo.events.main.icon', [], 'es'))->toBe('📣');
});

it('resolves screen menu label and icon for deeply nested screens', function () {
    app()->setLocale('en');
    expect(Main::getMenuLabel())->toBe('Events');
    expect(Main::getMenuIcon())->toBe('📣');

    app()->setLocale('es');
    expect(Main::getMenuLabel())->toBe('Eventos');
    expect(Main::getMenuIcon())->toBe('📣');
});

it('correctly builds translation candidate paths', function () {
    $candidates = TranslationResolver::buildCandidates('screen.demo.events.main.menu_title');

    expect($candidates)->toContain('screen.demo.events.main.menu_title');
    expect($candidates)->toContain('screen/demo.events.main.menu_title');
    expect($candidates)->toContain('screen/demo/events.main.menu_title');
    expect($candidates)->toContain('screen/demo/events/main.menu_title');
    expect($candidates)->toContain('screen/demo/events/main/menu_title.value');
});

it('correctly builds package fallback candidate for usim keys', function () {
    $candidates = TranslationResolver::buildCandidates('usim.dialog.button.ok');

    expect($candidates)->toContain('usim::dialog/button.ok');
});

it('returns the raw key as last resort fallback when not found', function () {
    expect(t('non.existent.translation.key.xyz'))->toBe('non.existent.translation.key.xyz');
});
