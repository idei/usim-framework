<?php

use App\UI\Screens\Demo\SelectDemo;

it('loads select demo screen with expected components and dynamic query actions', function () {
    /** @var \Tests\TestCase $this */
    $ui = uiScenario($this, SelectDemo::class, ['reset' => true]);

    $selCountry = $ui->component('sel_country');
    $selState = $ui->component('sel_state');
    $selCity = $ui->component('sel_city');
    $selLanguages = $ui->component('sel_languages');
    $chkMultiple = $ui->component('chk_enable_multiple');
    $btnReset = $ui->component('btn_reset');

    $selCountry->expect('type')->toBe('select');
    $selCountry->expect('action')->toBe('countries');
    $selCountry->expect('on_change')->toBe('country_change');

    $selState->expect('type')->toBe('select');
    $selState->expect('disabled')->toBe(true);
    $selState->expect('on_change')->toBe('state_change');

    $selCity->expect('type')->toBe('select');
    $selCity->expect('disabled')->toBe(true);
    $selCity->expect('on_change')->toBe('city_change');

    $selLanguages->expect('type')->toBe('select');
    $selLanguages->expect('action')->toBe('languages');
    $selLanguages->expect('on_change')->toBe('language_change');

    $chkMultiple->expect('type')->toBe('checkbox');
    $btnReset->expect('type')->toBe('button');
    $btnReset->expect('action')->toBe('reset_selections');

    $ui->assertNoIssues();
});

it('executes getCountries query method and returns array of options', function () {
    $screen = new SelectDemo();
    $countries = $screen->getCountries();

    expect($countries)->toBeArray()->not->toBeEmpty();
    expect($countries[0])->toHaveKeys(['value', 'label']);
});

it('executes getStates query method and returns array of states for a country', function () {
    $screen = new SelectDemo();
    $states = $screen->getStates(['country' => 'us']);

    expect($states)->toBeArray()->not->toBeEmpty();
    expect($states[0])->toHaveKeys(['value', 'label']);
});

it('executes getCities query method and returns array of cities for country and state', function () {
    $screen = new SelectDemo();
    $cities = $screen->getCities(['country' => 'us', 'state' => 'California']);

    expect($cities)->toBeArray()->not->toBeEmpty();
    expect($cities[0])->toHaveKeys(['value', 'label']);
});

it('executes getLanguages query method and returns array of languages', function () {
    $screen = new SelectDemo();
    $languages = $screen->getLanguages();

    expect($languages)->toBeArray()->not->toBeEmpty();
    expect($languages[0])->toHaveKeys(['value', 'label']);
});

it('handles 3-level cascading selection country -> state -> city', function () {
    /** @var \Tests\TestCase $this */
    $ui = uiScenario($this, SelectDemo::class, ['reset' => true]);

    // 1. Select Country (us)
    $response = $ui->change('sel_country', 'country_change', ['value' => 'us']);
    $response->assertOk();

    $selState = $ui->component('sel_state');
    $selState->expect('disabled')->toBe(false);
    expect($selState->data()['options'])->toBeArray()->not->toBeEmpty();

    $selCity = $ui->component('sel_city');
    $selCity->expect('disabled')->toBe(true);

    // 2. Select State (California)
    $response = $ui->change('sel_state', 'state_change', ['value' => 'California']);
    $response->assertOk();

    $selCity = $ui->component('sel_city');
    $selCity->expect('disabled')->toBe(false);
    expect($selCity->data()['options'])->toBeArray()->not->toBeEmpty();

    // 3. Select City (Los Angeles)
    $response = $ui->change('sel_city', 'city_change', ['value' => 'Los Angeles']);
    $response->assertOk();

    $lblResult = $ui->component('lbl_result');
    expect($lblResult->data()['text'])->toContain('US');
    expect($lblResult->data()['text'])->toContain('California');
    expect($lblResult->data()['text'])->toContain('Los Angeles');

    $ui->assertNoIssues();
});

it('toggles multiple languages selection mode', function () {
    /** @var \Tests\TestCase $this */
    $ui = uiScenario($this, SelectDemo::class, ['reset' => true]);

    $response = $ui->change('chk_enable_multiple', 'toggle_multiple_languages', ['checked' => true]);
    $response->assertOk();

    $selLanguages = $ui->component('sel_languages');
    $selLanguages->expect('multiple')->toBe(true);

    $ui->assertNoIssues();
});

it('resets selections back to default initial state', function () {
    /** @var \Tests\TestCase $this */
    $ui = uiScenario($this, SelectDemo::class, ['reset' => true]);

    $ui->change('sel_country', 'country_change', ['value' => 'us']);
    $ui->change('sel_state', 'state_change', ['value' => 'California']);

    $response = $ui->click('btn_reset');
    $response->assertOk();

    $selState = $ui->component('sel_state');
    $selState->expect('disabled')->toBe(true);

    $selCity = $ui->component('sel_city');
    $selCity->expect('disabled')->toBe(true);

    $ui->assertNoIssues();
});
