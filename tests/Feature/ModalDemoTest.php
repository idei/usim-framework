<?php

use App\UI\Screens\Demo\ModalDemo;

it('loads modal demo with expected base components', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

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
        $ui->component('btn_open_user_modal')->expect('type')->toBe('button');
        $ui->component('btn_open_user_modal')->expect('action')->toBe('open_user_modal');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('opens confirmation modal and handles confirm action', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

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
        $result->expect('text')->toBe(t('screen.demo.modal_demo.result.confirmed', ['type' => 'demo_action']));
        $result->expect('style')->toBe('success');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('opens confirmation modal and handles cancel action', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

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
        $result->expect('text')->toBe(t('screen.demo.modal_demo.result.cancelled'));
        $result->expect('style')->toBe('warning');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('opens timeout modal without close button and exposes timeout metadata', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

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
    }

    app()->setLocale($originalLocale);
});

it('opens screen as modal and returns data to caller via returnToCaller', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, ModalDemo::class, ['reset' => true]);

        $openResponse = $ui->click('btn_open_user_modal');
        $openResponse->assertOk();
        expect(hasModalComponents($openResponse->json()))->toBeTrue();

        $ui->component('input_user_name')->expect('type')->toBe('input');
        $ui->component('input_user_email')->expect('type')->toBe('input');
        $ui->component('select_user_role')->expect('type')->toBe('select');

        $saveResponse = $ui->action('btn_save_modal', 'submit_modal_data', [
            'input_user_name' => 'Carlos Gardel',
            'input_user_email' => 'carlos@tango.com',
            'select_user_role' => 'admin',
        ]);
        $saveResponse->assertOk();
        expect($saveResponse->json('action'))->toBe('close_modal');

        $result = $ui->component('lbl_result');
        $result->expect('text')->toBe(t('screen.demo.modal_demo.result.user_saved', [
            'name' => 'Carlos Gardel',
            'role' => 'admin',
            'email' => 'carlos@tango.com',
        ]));
        $result->expect('style')->toBe('success');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('opens screen as modal and closes when cancel is clicked', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, ModalDemo::class, ['reset' => true]);

        $openResponse = $ui->click('btn_open_user_modal');
        $openResponse->assertOk();
        expect(hasModalComponents($openResponse->json()))->toBeTrue();

        $cancelResponse = $ui->click('btn_cancel_modal');
        $cancelResponse->assertOk();
        expect($cancelResponse->json('action'))->toBe('close_modal');

        $result = $ui->component('lbl_result');
        $result->expect('text')->toBe('');

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('persists and restores active modal when refreshing screen (F5) and preserves caller return flow', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);

        $ui = uiScenario($this, ModalDemo::class, ['reset' => true]);

        // 1. Open the user form modal
        $openResponse = $ui->click('btn_open_user_modal');
        $openResponse->assertOk();
        expect(hasModalComponents($openResponse->json()))->toBeTrue();

        // 2. Simulate multiple consecutive F5 browser refreshes (each reload requests screen + menu)
        for ($i = 0; $i < 3; $i++) {
            $refreshResponse = $this->getJson('/api/ui/demo/modal-demo');
            $refreshResponse->assertOk();
            $refreshData = $refreshResponse->json();

            // Assert both caller screen and modal components are present on each F5
            expect(hasModalComponents($refreshData))->toBeTrue();
            expect(modalPayloadHasNamedComponent($refreshData, 'app_ui_screens_demo_userformmodal'))->toBeTrue();

            // Browser then requests menu fragment with parent=menu
            $menuResponse = $this->getJson('/api/ui/menu?parent=menu');
            $menuResponse->assertOk();
        }

        // 3. User submits data on the restored modal
        $saveResponse = $ui->action('btn_save_modal', 'submit_modal_data', [
            'input_user_name' => 'Carlos Gardel',
            'input_user_email' => 'carlos@tango.com',
            'select_user_role' => 'admin',
        ]);
        $saveResponse->assertOk();
        expect($saveResponse->json('action'))->toBe('close_modal');

        // Caller state is updated
        $result = $ui->component('lbl_result');
        $result->expect('text')->toBe(t('screen.demo.modal_demo.result.user_saved', [
            'name' => 'Carlos Gardel',
            'role' => 'admin',
            'email' => 'carlos@tango.com',
        ]));
        $result->expect('style')->toBe('success');

        // 4. Another F5 refresh after modal was closed: modal should NOT be reopened
        $afterCloseRefresh = $this->getJson('/api/ui/demo/modal-demo');
        $afterCloseRefresh->assertOk();
        expect(hasModalComponents($afterCloseRefresh->json()))->toBeFalse();

        // 5. Open modal again and verify that ?reset=1 clears the active modal
        $ui->click('btn_open_user_modal');
        $resetRefresh = $this->getJson('/api/ui/demo/modal-demo?reset=1');
        $resetRefresh->assertOk();
        expect(hasModalComponents($resetRefresh->json()))->toBeFalse();

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});
