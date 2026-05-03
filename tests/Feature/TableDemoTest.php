<?php

use App\UI\Screens\Demo\TableDemo;
use App\UI\Screens\Demo\Support\TableDemoService;

it('loads table demo with expected table configuration', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);
        TableDemoService::reset();

        $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

        $table = $ui->component('users_table')->data();

        expect($table['type'] ?? null)->toBe('table');
        expect($table['title'] ?? null)->toBe(t('screen.demo.table_demo.users_table_title'));
        expect($table['pagination']['enabled'] ?? null)->toBeTrue();
        expect($table['pagination']['per_page'] ?? null)->toBe(10);
        expect($table['pagination']['current_page'] ?? null)->toBe(1);
        expect($table['pagination']['total_items'] ?? null)->toBe(25);
        expect($table['pagination']['total_pages'] ?? null)->toBe(3);
        expect($table['pagination']['can_next'] ?? null)->toBeTrue();
        expect($table['pagination']['can_prev'] ?? null)->toBeFalse();

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('includes edit_user actions in table payload', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);
        TableDemoService::reset();

        $response = getScreenJson($this, TableDemo::class, ['reset' => true]);
        $response->assertOk();

        $payload = $response->json();
        expect(uiPayloadContainsAction($payload, 'edit_user'))->toBeTrue();
    }

    app()->setLocale($originalLocale);
});

it('changes page and updates pagination flags', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);
        TableDemoService::reset();

        $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

        $response = $ui->action('users_table', 'change_page', ['page' => 3]);
        $response->assertOk();

        $table = $ui->component('users_table')->data();
        expect($table['pagination']['current_page'] ?? null)->toBe(3);
        expect($table['pagination']['can_prev'] ?? null)->toBeTrue();
        expect($table['pagination']['can_next'] ?? null)->toBeFalse();

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});

it('edits a user through table action and persists cache update', function () {
    $originalLocale = app()->getLocale();

    foreach (['en', 'es'] as $locale) {
        app()->setLocale($locale);
        TableDemoService::reset();

        $ui = uiScenario($this, TableDemo::class, ['reset' => true]);
        $targetUserId = 1;
        $originalUser = TableDemoService::find($targetUserId);
        expect($originalUser)->not->toBeNull();

        $response = $ui->action('users_table', 'edit_user', ['user_id' => $targetUserId]);
        $response->assertOk();

        $expectedName = ($originalUser['name'] ?? '') . ' (E)';

        $updatedUser = TableDemoService::find($targetUserId);
        expect($updatedUser)->not->toBeNull();
        expect($updatedUser['name'] ?? null)->toBe($expectedName);

        $ui->assertNoIssues();
    }

    app()->setLocale($originalLocale);
});
