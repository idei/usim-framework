<?php

use App\Models\User;
use App\UI\Screens\Demo\TableDemo;

it('loads table demo with expected table configuration', function () {
    User::factory()->count(15)->create();

    $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

    $table = $ui->component('users_table')->data();

    expect($table['type'] ?? null)->toBe('table');
    expect($table['title'] ?? null)->toBe('Users Table');
    expect($table['pagination']['enabled'] ?? null)->toBeTrue();
    expect($table['pagination']['per_page'] ?? null)->toBe(10);
    expect($table['pagination']['current_page'] ?? null)->toBe(1);
    expect($table['pagination']['total_items'] ?? null)->toBe(15);
    expect($table['pagination']['total_pages'] ?? null)->toBe(2);
    expect($table['pagination']['can_next'] ?? null)->toBeTrue();
    expect($table['pagination']['can_prev'] ?? null)->toBeFalse();

    $ui->assertNoIssues();
});

it('includes edit_user actions in table payload', function () {
    User::factory()->count(5)->create();

    $response = getScreenJson($this, TableDemo::class, ['reset' => true]);
    $response->assertOk();

    $payload = $response->json();
    expect(payloadContainsAction($payload, 'edit_user'))->toBeTrue();
});

it('changes page and updates pagination flags', function () {
    User::factory()->count(15)->create();

    $ui = uiScenario($this, TableDemo::class, ['reset' => true]);

    $response = $ui->action('users_table', 'change_page', ['page' => 2]);
    $response->assertOk();

    $table = $ui->component('users_table')->data();
    expect($table['pagination']['current_page'] ?? null)->toBe(2);
    expect($table['pagination']['can_prev'] ?? null)->toBeTrue();
    expect($table['pagination']['can_next'] ?? null)->toBeFalse();

    $ui->assertNoIssues();
});

it('edits a user through table action and persists database update', function () {
    User::factory()->count(12)->create();

    $ui = uiScenario($this, TableDemo::class, ['reset' => true]);
    $user = User::query()->firstOrFail();

    $response = $ui->action('users_table', 'edit_user', ['user_id' => $user->id]);
    $response->assertOk();

    $user->refresh();
    expect($user->name)->toContain('(E)');
    expect(payloadContainsText($response->json(), '(E)'))->toBeTrue();

    $ui->assertNoIssues();
});

if (!function_exists('payloadContainsAction')) {
    function payloadContainsAction(mixed $value, string $action): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if (($value['action'] ?? null) === $action) {
            return true;
        }

        foreach ($value as $child) {
            if (payloadContainsAction($child, $action)) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('payloadContainsText')) {
    function payloadContainsText(mixed $value, string $needle): bool
    {
        if (is_string($value)) {
            return str_contains($value, $needle);
        }

        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $child) {
            if (payloadContainsText($child, $needle)) {
                return true;
            }
        }

        return false;
    }
}
