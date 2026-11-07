<?php

namespace Tests\Unit;

use App\UI\Screens\Demo\ButtonDemo;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Idei\Usim\Support\Translation\TranslationResolver;

class DummyTestScreen extends Screen
{
    public function getRelativeText(): string
    {
        return t('.sample_key');
    }

    public function getMethodTText(): string
    {
        return $this->t('.sample_key');
    }

    public function getClosureText(): string
    {
        $fn = function (): string {
            return t('.inside_closure');
        };

        return $fn();
    }
}

class DummyTestRequest extends UIRequest
{
    public function getRelativeRuleMessage(): string
    {
        return t('.validation.required');
    }

    public function getMethodTMessage(): string
    {
        return $this->t('.validation.required');
    }
}

beforeEach(function () {
    TranslationResolver::clearCache();
});

afterEach(function () {
    TranslationResolver::clearCache();
});

it('resolves relative key with dot notation from Screen class', function () {
    $resolvedKey = TranslationResolver::resolveKey('.title', ButtonDemo::class);
    expect($resolvedKey)->toBe('screens.demo.button_demo.title');

    TranslationResolver::setContextClass(ButtonDemo::class);
    expect(t('.title'))->toBe(t('screens.demo.button_demo.title'));
});

it('detects caller class and resolves prefix automatically from Screen method', function () {
    $screen = new DummyTestScreen;
    $key = $screen->getRelativeText();

    expect($key)->toBe('screens.dummy_test_screen.sample_key');
});

it('resolves relative key via $this->t() method on Screen', function () {
    $screen = new DummyTestScreen;
    $key = $screen->getMethodTText();

    expect($key)->toBe('screens.dummy_test_screen.sample_key');
});

it('resolves relative key inside closure executed within Screen method', function () {
    $screen = new DummyTestScreen;
    $key = $screen->getClosureText();

    expect($key)->toBe('screens.dummy_test_screen.inside_closure');
});

it('resolves relative key from UIRequest class and method', function () {
    $request = new DummyTestRequest;
    expect($request->getRelativeRuleMessage())->toBe('requests.dummy_test_request.validation.required');
    expect($request->getMethodTMessage())->toBe('requests.dummy_test_request.validation.required');
});

it('resolves real screen translations using relative keys with $this->t()', function () {
    $screen = new ButtonDemo;

    $originalLocale = app()->getLocale();

    app()->setLocale('es');
    expect($screen->t('.title'))->toBe('Demo de Botón - Haz Clic');
    expect($screen->t('.default'))->toBe('Haz clic');
    expect($screen->t('.clicked'))->toBe('Clic hecho');

    app()->setLocale('en');
    expect($screen->t('.title'))->toBe('Button Demo - Click Me!');
    expect($screen->t('.default'))->toBe('Click Me!');
    expect($screen->t('.clicked'))->toBe('Clicked!');

    app()->setLocale($originalLocale);
});

it('leaves absolute keys unaffected', function () {
    expect(TranslationResolver::resolveKey('screens.demo.button_demo.title'))->toBe('screens.demo.button_demo.title');
    expect(TranslationResolver::resolveKey('validation.required'))->toBe('validation.required');
});

it('allows explicit context class override', function () {
    TranslationResolver::setContextClass(ButtonDemo::class);
    expect(TranslationResolver::resolveKey('.title'))->toBe('screens.demo.button_demo.title');
});
