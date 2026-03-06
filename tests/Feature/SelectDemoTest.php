<?php

use App\UI\Screens\Demo\SelectDemo;

it('loads select demo with expected defaults', function () {
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
    $selCity->expect('placeholder')->toBe('First select a country');

    $selLanguages->expect('type')->toBe('select');
    $selLanguages->expect('searchable')->toBeTrue();
    $selLanguages->expect('multiple')->toBeFalse();
    $selLanguages->expect('value')->toBeNull();

    $chkEnableMultiple->expect('type')->toBe('checkbox');
    $chkEnableMultiple->expect('checked')->toBeFalse();

    $btnReset->expect('type')->toBe('button');
    $btnReset->expect('action')->toBe('reset_selections');

    $lblResult->expect('text')->toBe('Select options above to see results');
    $lblResult->expect('style')->toBe('default');

    $ui->assertNoIssues();
});

it('enables city select and updates result when selecting a country', function () {
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
    $selCity->expect('placeholder')->toBe('Choose a city...');

    $cityOptions = $selCity->data()['options'] ?? [];
    expect($cityOptions)->toHaveCount(4);
    expect($cityOptions[0]['value'] ?? null)->toBe('madrid');

    expect($lblResult->data()['text'] ?? '')->toContain('Country selected');
    expect($lblResult->data()['text'] ?? '')->toContain('Spain');
    $lblResult->expect('style')->toBe('success');

    $ui->assertNoIssues();
});

it('shows city info and appends selected language details', function () {
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
    expect($resultAfterCity)->toContain('New York');
    expect($resultAfterCity)->toContain('United States');
    expect($resultAfterCity)->toContain('EST');

    $ui->change('sel_languages', 'language_change', [
        'value' => 'en',
        'name' => 'sel_languages',
    ])->assertOk();

    $resultAfterSingleLanguage = $ui->component('lbl_result')->data()['text'] ?? '';
    expect($resultAfterSingleLanguage)->toContain('New York');
    expect($resultAfterSingleLanguage)->toContain('Language: English');

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
    expect($resultAfterMultipleLanguages)->toContain('Languages: English, Spanish');

    $ui->assertNoIssues();
});

it('resets all selections to initial-like state', function () {
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
    $selCity->expect('placeholder')->toBe('First select a country');

    $selLanguages = $ui->component('sel_languages');
    $selLanguages->expect('value')->toBeNull();
    $selLanguages->expect('multiple')->toBeFalse();
    $selLanguages->expect('placeholder')->toBe('Choose a language...');

    $ui->component('chk_enable_multiple')->expect('checked')->toBeFalse();

    $lblResult = $ui->component('lbl_result');
    $lblResult->expect('text')->toBe('All selections have been reset. Start over!');
    $lblResult->expect('style')->toBe('info');

    $ui->assertNoIssues();
});
