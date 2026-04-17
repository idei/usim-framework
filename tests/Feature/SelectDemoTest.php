<?php

use App\UI\Screens\Demo\SelectDemo;

it('loads select demo with expected defaults', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, SelectDemo::class, ['reset' => true]);

        $selCountry = $ui->component('sel_country');
        $selCity = $ui->component('sel_city');
        $selLanguages = $ui->component('sel_languages');
        $chkEnableMultiple = $ui->component('chk_enable_multiple');
        $btnReset = $ui->component('btn_reset');
        $lblResult = $ui->component('lbl_result');

        $selCountry->expect('type')->toBe('select');
        $selCountry->expect('required')->toBeTrue();
        $selCountry->expect('value')->toBeNull();
        $selCountry->expect('options')->toHaveCount(5);

        $selCity->expect('type')->toBe('select');
        $selCity->expect('disabled')->toBeTrue();
        $selCity->expect('value')->toBeNull();
        $selCity->expect('options')->toBe([]);
        $selCity->expect('placeholder')->toBe(t('screen.demo.select_demo.city.placeholder.select_country_first'));

        $selLanguages->expect('type')->toBe('select');
        $selLanguages->expect('searchable')->toBeTrue();
        $selLanguages->expect('multiple')->toBeFalse();
        $selLanguages->expect('value')->toBeNull();

        $chkEnableMultiple->expect('type')->toBe('checkbox');
        $chkEnableMultiple->expect('checked')->toBeFalse();

        $btnReset->expect('type')->toBe('button');
        $btnReset->expect('action')->toBe('reset_selections');

        $lblResult->expect('text')->toBe(t('screen.demo.select_demo.result.initial'));
        $lblResult->expect('style')->toBe('default');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('enables city select and updates result when selecting a country', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, SelectDemo::class, ['reset' => true]);

        $response = $ui->change('sel_country', 'country_change', [
            'value' => 'es',
            'name' => 'sel_country',
        ]);

        $response->assertOk();

        $selCity = $ui->component('sel_city');
        $lblResult = $ui->component('lbl_result');

        $selCity->expect('disabled')->toBeFalse();
        $selCity->expect('value')->toBeNull();
        $selCity->expect('placeholder')->toBe(t('screen.demo.select_demo.city.placeholder.choose_city'));

        $cityOptions = $selCity->data()['options'] ?? [];
        expect($cityOptions)->toHaveCount(4);
        expect($cityOptions[0]['value'] ?? null)->toBe('madrid');

        expect($lblResult->data()['text'] ?? '')->not->toBe('');
        $lblResult->expect('style')->toBe('success');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('shows city info and appends selected language details', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, SelectDemo::class, ['reset' => true]);

        $ui->change('sel_country', 'country_change', [
            'value' => 'us',
            'name' => 'sel_country',
        ])->assertOk();

        $ui->change('sel_city', 'city_change', [
            'value' => 'ny',
            'name' => 'sel_city',
        ])->assertOk();

        $resultAfterCity = $ui->component('lbl_result')->data()['text'] ?? '';
        expect($resultAfterCity)->not->toBe('');
        $ui->component('lbl_result')->expect('style')->toBe('success');

        $ui->change('sel_languages', 'language_change', [
            'value' => 'en',
            'name' => 'sel_languages',
        ])->assertOk();

        $resultAfterSingleLanguage = $ui->component('lbl_result')->data()['text'] ?? '';
        if (str_contains($resultAfterCity, '📍')) {
            expect($resultAfterSingleLanguage)->toContain('Language: English');
        } else {
            expect($resultAfterSingleLanguage)->toBe($resultAfterCity);
        }

        $ui->change(
            componentName: 'chk_enable_multiple',
            action: 'toggle_multiple_languages',
            parameters: ['checked' => true, 'name' => 'chk_enable_multiple'],
            includeStorageHeader: false
        )->assertOk();

        $selLanguages = $ui->component('sel_languages');
        $selLanguages->expect('multiple')->toBeTrue();
        $selLanguages->expect('max_selections')->toBe(3);
        $selLanguages->expect('value')->toBe([]);

        $ui->change('sel_languages', 'language_change', [
            'value' => ['en', 'es'],
            'name' => 'sel_languages',
        ])->assertOk();

        $resultAfterMultipleLanguages = $ui->component('lbl_result')->data()['text'] ?? '';
        if (str_contains($resultAfterCity, '📍')) {
            expect($resultAfterMultipleLanguages)->toContain('Languages: English, Spanish');
        } else {
            expect($resultAfterMultipleLanguages)->toBe($resultAfterSingleLanguage);
        }

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('resets all selections to initial-like state', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, SelectDemo::class, ['reset' => true]);

        $ui->change('sel_country', 'country_change', [
            'value' => 'jp',
            'name' => 'sel_country',
        ])->assertOk();

        $ui->change('sel_city', 'city_change', [
            'value' => 'tokyo',
            'name' => 'sel_city',
        ])->assertOk();

        $ui->change(
            componentName: 'chk_enable_multiple',
            action: 'toggle_multiple_languages',
            parameters: ['checked' => true, 'name' => 'chk_enable_multiple'],
            includeStorageHeader: false
        )->assertOk();

        $response = $ui->click('btn_reset');

        $response->assertOk();

        $ui->component('sel_country')->expect('value')->toBeNull();

        $selCity = $ui->component('sel_city');
        $selCity->expect('disabled')->toBeTrue();
        $selCity->expect('options')->toBe([]);
        $selCity->expect('value')->toBeNull();
        $selCity->expect('placeholder')->toBe(t('screen.demo.select_demo.city.placeholder.select_country_first'));

        $selLanguages = $ui->component('sel_languages');
        $selLanguages->expect('value')->toBeNull();
        $selLanguages->expect('multiple')->toBeFalse();
        $selLanguages->expect('placeholder')->toBe(t('screen.demo.select_demo.languages.placeholder.single'));

        $ui->component('chk_enable_multiple')->expect('checked')->toBeFalse();

        $lblResult = $ui->component('lbl_result');
        $lblResult->expect('text')->toBe(t('screen.demo.select_demo.result.reset_done'));
        $lblResult->expect('style')->toBe('info');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});
