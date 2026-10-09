<?php

use Idei\Usim\Contracts\UsimTranslatorInterface;
use Idei\Usim\Support\Translation\TranslationResolver;
use Idei\Usim\Support\TranslationService;
use Idei\Usim\Support\UsimTranslator;

it('resolves translations through UsimTranslator using Laravel translator', function () {
    $translator = app(UsimTranslatorInterface::class);

    expect($translator)->toBeInstanceOf(UsimTranslator::class);
    expect($translator->get('screen.demo.events.main.menu_title', [], 'en'))->toBe('Events');
    expect($translator->get('screen.demo.events.main.menu_title', [], 'es'))->toBe('Eventos');
});

it('resolves translations with placeholders through UsimTranslator', function () {
    $translator = app(UsimTranslatorInterface::class);

    // Test with replace parameters
    $result = $translator->get('non.existent.key', ['name' => 'Alice']);
    expect($result)->toBe('non.existent.key');
});

it('correctly checks translation existence via has()', function () {
    $translator = app(UsimTranslatorInterface::class);

    expect($translator->has('screen.demo.events.main.menu_title', 'en'))->toBeTrue();
    expect($translator->has('screen.demo.events.main.menu_title', 'es'))->toBeTrue();
    expect($translator->has('definitely.non.existent.key.xyz', 'en'))->toBeFalse();
});

it('gets and sets locale through UsimTranslator', function () {
    $translator = app(UsimTranslatorInterface::class);

    $originalLocale = $translator->getLocale();

    $translator->setLocale('fr');
    expect($translator->getLocale())->toBe('fr');
    expect(app()->getLocale())->toBe('fr');

    $translator->setLocale($originalLocale);
    expect($translator->getLocale())->toBe($originalLocale);
});

it('falls back to database TranslationService when Laravel translator does not have the key', function () {
    $mockDbService = Mockery::mock(TranslationService::class);
    $mockDbService->shouldReceive('safeGetValue')
        ->with('custom.db.key', ['user' => 'Bob'], 'es')
        ->once()
        ->andReturn('Valor DB para Bob');

    $laravelTranslator = app('translator');
    $translator = new UsimTranslator($laravelTranslator, $mockDbService);

    expect($translator->get('custom.db.key', ['user' => 'Bob'], 'es'))->toBe('Valor DB para Bob');
    expect($translator->has('custom.db.key', 'es'))->toBeFalse(); // mock doesn't mock getEntry unless asked
});

it('registers missing key in TranslationService when not found anywhere', function () {
    $mockDbService = Mockery::mock(TranslationService::class);
    $mockDbService->shouldReceive('safeGetValue')
        ->with('missing.unregistered.key', [], null)
        ->once()
        ->andReturn(null);
    $mockDbService->shouldReceive('registerMissingKey')
        ->with('missing.unregistered.key')
        ->once();

    $laravelTranslator = app('translator');
    $translator = new UsimTranslator($laravelTranslator, $mockDbService);

    expect($translator->get('missing.unregistered.key'))->toBe('missing.unregistered.key');
});

it('allows global helper t() to be fully mocked via UsimTranslatorInterface in container', function () {
    $mock = Mockery::mock(UsimTranslatorInterface::class);
    $mock->shouldReceive('get')
        ->with('mocked.screen.title', ['item' => 'Phone'], 'es')
        ->once()
        ->andReturn('Título Simulado (Phone)');

    app()->instance(UsimTranslatorInterface::class, $mock);

    expect(t('mocked.screen.title', ['item' => 'Phone'], 'es'))->toBe('Título Simulado (Phone)');
});

it('delegates TranslationResolver::resolve and has to UsimTranslatorInterface', function () {
    $mock = Mockery::mock(UsimTranslatorInterface::class);
    $mock->shouldReceive('get')
        ->with('resolver.test.key', [], 'en')
        ->once()
        ->andReturn('Resolved via Mock');
    $mock->shouldReceive('has')
        ->with('resolver.test.key', 'en')
        ->once()
        ->andReturn(true);

    app()->instance(UsimTranslatorInterface::class, $mock);

    expect(TranslationResolver::resolve('resolver.test.key', [], 'en'))->toBe('Resolved via Mock');
    expect(TranslationResolver::has('resolver.test.key', 'en'))->toBeTrue();
});
