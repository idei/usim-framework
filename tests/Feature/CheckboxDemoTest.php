<?php

use App\UI\Screens\Demo\CheckboxDemo;

it('loads checkbox demo with expected defaults', function () {
    $ui = uiScenario($this, CheckboxDemo::class, ['reset' => true]);

    $chkJavascript = $ui->component('chk_javascript');
    $chkPython = $ui->component('chk_python');
    $btnSubmit = $ui->component('btn_submit');
    $lblResult = $ui->component('lbl_result');

    $chkJavascript->expect('type')->toBe('checkbox');
    $chkJavascript->expect('checked')->toBeFalse();

    $chkPython->expect('type')->toBe('checkbox');
    $chkPython->expect('checked')->toBeFalse();

    $btnSubmit->expect('type')->toBe('button');
    $btnSubmit->expect('action')->toBe('submit_selection');

    $lblResult->expect('text')->toBe('Make your selection above');
    $lblResult->expect('style')->toBe('secondary');

    $ui->assertNoIssues();
});

it('rejects selecting python when javascript is not selected', function () {
    $ui = uiScenario($this, CheckboxDemo::class, ['reset' => true]);

    $response = $ui->change(
        componentName: 'chk_python',
        action: 'try_change_python',
        parameters: ['checked' => true, 'name' => 'chk_python'],
        includeStorageHeader: false
    );

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('error');
    expect($response->json('toast.message'))->toBe('You must select JavaScript first before selecting Python!');

    $ui->component('chk_python')->expect('checked')->toBeFalse();
    $ui->component('lbl_result')->expect('text')->toBe('❌ You must select JavaScript first before selecting Python!');
    $ui->component('lbl_result')->expect('style')->toBe('danger');

    $ui->assertNoIssues();
});

it('allows selecting python after javascript is selected', function () {
    $ui = uiScenario($this, CheckboxDemo::class, ['reset' => true]);

    $responseJs = $ui->change(
        componentName: 'chk_javascript',
        action: 'try_change_javascript',
        parameters: ['checked' => true, 'name' => 'chk_javascript'],
        includeStorageHeader: false
    );
    $responseJs->assertOk();

    $responsePy = $ui->change(
        componentName: 'chk_python',
        action: 'try_change_python',
        parameters: ['checked' => true, 'name' => 'chk_python'],
        includeStorageHeader: false
    );
    $responsePy->assertOk();

    $ui->component('chk_javascript')->expect('checked')->toBeTrue();
    $ui->component('chk_python')->expect('checked')->toBeTrue();
    $ui->component('lbl_result')->expect('text')->toBe('✅ Python selected!');
    $ui->component('lbl_result')->expect('style')->toBe('success');

    $ui->assertNoIssues();
});

it('submits selected languages and shows success feedback', function () {
    $ui = uiScenario($this, CheckboxDemo::class, ['reset' => true]);

    $response = $ui->click('btn_submit', [
        'chk_javascript' => true,
        'chk_python' => true,
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');
    expect($response->json('toast.message'))->toBe('Submitted! Your selections: JavaScript, Python');

    $ui->component('lbl_result')->expect('text')->toBe('✅ Submitted! Your selections: JavaScript, Python');
    $ui->component('lbl_result')->expect('style')->toBe('success');

    $ui->assertNoIssues();
});
