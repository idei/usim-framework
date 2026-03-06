<?php

use App\UI\Screens\Demo\ModalDemo;

it('loads modal demo with expected base components', function () {
    $ui = uiScenario($this, ModalDemo::class, ['reset' => true]);

    $ui->component('lbl_instruction')->expect('type')->toBe('label');
    $ui->component('lbl_result')->expect('type')->toBe('label');
    $ui->component('lbl_result')->expect('text')->toBe('');

    $ui->component('btn_open_modal')->expect('type')->toBe('button');
    $ui->component('btn_open_modal')->expect('action')->toBe('open_confirmation');

    $ui->component('btn_error_dialog')->expect('action')->toBe('show_error_dialog');
    $ui->component('btn_timeout_dialog')->expect('action')->toBe('show_timeout_dialog');
    $ui->component('btn_timeout_no_button')->expect('action')->toBe('show_timeout_no_button');
    $ui->component('btn_show_settings')->expect('action')->toBe('show_settings_confirm');

    $ui->assertNoIssues();
});

it('opens confirmation modal and handles confirm action', function () {
    $ui = uiScenario($this, ModalDemo::class, ['reset' => true]);

    $openResponse = $ui->click('btn_open_modal');
    $openResponse->assertOk();

    // Modal content is returned as regular UI entries with parent="modal".
    expect(hasModalComponents($openResponse->json()))->toBeTrue();

    $confirmData = $ui->component('btn_confirm')->data();
    $confirmParams = $confirmData['parameters'] ?? [];

    $confirmResponse = $ui->click('btn_confirm', $confirmParams);
    $confirmResponse->assertOk();
    expect($confirmResponse->json('action'))->toBe('close_modal');

    $result = $ui->component('lbl_result');
    expect($result->data()['text'] ?? '')->toContain('Action confirmed');
    $result->expect('style')->toBe('success');

    $ui->assertNoIssues();
});

it('opens confirmation modal and handles cancel action', function () {
    $ui = uiScenario($this, ModalDemo::class, ['reset' => true]);

    $openResponse = $ui->click('btn_open_modal');
    $openResponse->assertOk();
    expect(hasModalComponents($openResponse->json()))->toBeTrue();

    $cancelData = $ui->component('btn_cancel')->data();
    $cancelParams = $cancelData['parameters'] ?? [];

    $cancelResponse = $ui->click('btn_cancel', $cancelParams);
    $cancelResponse->assertOk();
    expect($cancelResponse->json('action'))->toBe('close_modal');

    $result = $ui->component('lbl_result');
    expect($result->data()['text'] ?? '')->toContain('Action cancelled');
    $result->expect('style')->toBe('warning');

    $ui->assertNoIssues();
});

it('opens timeout modal without close button and exposes timeout metadata', function () {
    $ui = uiScenario($this, ModalDemo::class, ['reset' => true]);

    $response = $ui->click('btn_timeout_no_button');
    $response->assertOk();

    $payload = $response->json();
    expect(hasModalComponents($payload))->toBeTrue();

    $modalRoot = firstTimeoutModalComponent($payload);
    expect($modalRoot)->not->toBeNull();
    expect($modalRoot['_timeout'] ?? null)->toBe(5);
    expect($modalRoot['_time_unit'] ?? null)->toBe('seconds');
    expect($modalRoot['_timeout_action'] ?? null)->toBe('close_modal');

    // Timeout dialog configured without close button should not include modal confirm/cancel buttons.
    expect(modalPayloadHasNamedComponent($payload, 'btn_confirm'))->toBeFalse();
    expect(modalPayloadHasNamedComponent($payload, 'btn_cancel'))->toBeFalse();

    $ui->assertNoIssues();
});

if (!function_exists('hasModalComponents')) {
    function hasModalComponents(array $payload): bool
    {
        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['parent'] ?? null) === 'modal') {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('firstTimeoutModalComponent')) {
    /** @return array<string, mixed>|null */
    function firstTimeoutModalComponent(array $payload): ?array
    {
        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['parent'] ?? null) === 'modal' && isset($entry['_timeout'])) {
                return $entry;
            }
        }

        return null;
    }
}

if (!function_exists('modalPayloadHasNamedComponent')) {
    function modalPayloadHasNamedComponent(array $payload, string $name): bool
    {
        foreach ($payload as $entry) {
            if (!is_array($entry)) {
                continue;
            }

            if (($entry['parent'] ?? null) !== 'modal') {
                continue;
            }

            if (($entry['name'] ?? null) === $name) {
                return true;
            }
        }

        return false;
    }
}
