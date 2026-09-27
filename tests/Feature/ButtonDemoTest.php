<?php

use App\UI\Screens\Demo\ButtonDemo;
use App\UI\Screens\Demo\Events\Left;
use App\UI\Screens\Demo\Events\Main;
use App\UI\Screens\Demo\Events\Right;
use Idei\Usim\Support\UIIdGenerator;

it('loads button demo screen with expected components', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);

    $buttonsContainer = $ui->component('buttons_container');
    $messageContainer = $ui->component('message_container');

    $buttonsContainer->expect('type')->toBe('container');
    $messageContainer->expect('type')->toBe('container');

    $ui->assertNoIssues();
});

it('embeds events demo and generates correct deterministic component contexts', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);

    // Select the "Events" demo (index 11)
    $response = $ui->click('button_11', [
        'id' => 'button_11',
        'index' => 11,
    ]);
    $response->assertOk();

    // Verify embedded Left input component has the context of Left::class
    $inputComp = $ui->component('input_text');
    $inputId = $inputComp->id();
    expect(UIIdGenerator::getContextFromId($inputId))->toBe(Left::class);

    // Verify embedded Right textarea component has the context of Right::class
    $textareaComp = $ui->component('textarea_output');
    $textareaId = $textareaComp->id();
    expect(UIIdGenerator::getContextFromId($textareaId))->toBe(Right::class);

    $ui->assertNoIssues();
});

it('handles input events on embedded Left screen successfully without 404', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);

    // Select "Events" demo
    $ui->click('button_11', [
        'id' => 'button_11',
        'index' => 11,
    ]);

    $inputComp = $ui->component('input_text');
    $inputId = $inputComp->id();

    // Trigger onCheckText via direct event
    $response = $this->postJson('/api/ui-event', [
        'component_id' => $inputId,
        'event' => 'input',
        'action' => 'check_text',
        'parameters' => [
            'value' => 'testing input events',
        ],
    ]);

    $response->assertOk();
    expect($response->json("{$inputId}.value"))->toBe('Testing Input Events');
});

it('dispatches cross-screen events via UsimEvent to update Right screen textarea', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);

    // Select "Events" demo
    $ui->click('button_11', [
        'id' => 'button_11',
        'index' => 11,
    ]);

    $inputComp = $ui->component('input_text');
    $inputId = $inputComp->id();

    $textareaComp = $ui->component('textarea_output');
    $textareaId = $textareaComp->id();

    // Trigger onSendText via enter event
    $response = $this->postJson('/api/ui-event', [
        'component_id' => $inputId,
        'event' => 'enter',
        'action' => 'send_text',
        'parameters' => [
            'value' => 'Mensaje desde Left',
        ],
    ]);

    $response->assertOk();
    // Input should be cleared
    expect($response->json("{$inputId}.value"))->toBe('');
    // Right textarea should receive the sent text
    expect($response->json("{$textareaId}.value"))->toBe('Mensaje desde Left');
});

it('persists and unwinds modals across F5 page refreshes when opened from embedded screen', function () {
    $ui = uiScenario($this, ButtonDemo::class, ['reset' => true]);

    // 1. Select the "ModalDemo" tab (index 7)
    $selectResponse = $ui->click('button_7', [
        'id' => 'button_7',
        'index' => 7,
    ]);
    $selectResponse->assertOk();

    // 2. Open confirmation modal from the embedded ModalDemo
    $openResponse = $ui->click('btn_open_modal');
    $openResponse->assertOk();
    expect(hasModalComponents($openResponse->json()))->toBeTrue();

    // Modal stack has 1 modal
    expect(Idei\Usim\Support\UIStateManager::getClientActiveModalStack())->toHaveCount(1);

    // 3. Simulate consecutive F5 refreshes on ButtonDemo
    for ($i = 0; $i < 3; $i++) {
        $f5Response = $this->getJson('/api/ui/demo/button-demo');
        $f5Response->assertOk();
        $f5Data = $f5Response->json();

        expect(hasModalComponents($f5Data))->toBeTrue();
        expect(modalPayloadHasNamedComponent($f5Data, 'idei_usim_modals_confirmdialog'))->toBeTrue();

        // Browser then requests menu fragment with parent=menu
        $menuResponse = $this->getJson('/api/ui/menu?parent=menu');
        $menuResponse->assertOk();
    }

    // 4. Click confirm button on the restored modal
    $f5Data = $this->getJson('/api/ui/demo/button-demo')->json();
    $confirmBtnId = null;
    $confirmParams = [];
    foreach ($f5Data as $id => $comp) {
        if (is_array($comp) && ($comp['name'] ?? null) === 'btn_confirm') {
            $confirmBtnId = (int) $id;
            $confirmParams = $comp['parameters'] ?? [];
            break;
        }
    }
    expect($confirmBtnId)->not->toBeNull();

    $confirmResponse = $this->postJson('/api/ui-event', [
        'component_id' => $confirmBtnId,
        'event' => 'click',
        'action' => 'handle_confirm',
        'parameters' => $confirmParams,
    ]);
    $confirmResponse->assertOk();
    expect($confirmResponse->json('action'))->toBe('close_modal');

    // Modal stack is now empty
    expect(Idei\Usim\Support\UIStateManager::getClientActiveModalStack())->toBeEmpty();

    // 5. Subsequent F5 refresh has no modals
    $f5FinalResponse = $this->getJson('/api/ui/demo/button-demo');
    $f5FinalResponse->assertOk();
    expect(hasModalComponents($f5FinalResponse->json()))->toBeFalse();
});
