<?php

use App\Models\User;
use App\UI\Screens\Admin\UsersManager;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;

it('returns full edit-user modal payload when opening from users manager', function () {
    Role::findOrCreate('root');
    Role::findOrCreate('user');

    $root = User::factory()->create([
        'email_verified_at' => Carbon::now(),
    ]);
    $root->assignRole('root');

    $target = User::factory()->create([
        'name' => 'Modal Target User',
        'email' => 'modal-target@example.test',
        'email_verified_at' => null,
    ]);
    $target->assignRole('user');

    $this->actingAs($root);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->action('users_table', 'users_table_row_clicked', [
        'model_id' => $target->id,
    ]);

    $response->assertOk();

    $payload = $response->json();

    expect(findComponentByName($payload, 'register_dialog'))->not->toBeNull();
    expect(findComponentByName($payload, 'user_id'))->not->toBeNull();
    expect(findComponentByName($payload, 'name'))->not->toBeNull();
    expect(findComponentByName($payload, 'email'))->not->toBeNull();
    expect(findComponentByName($payload, 'roles'))->not->toBeNull();
    expect(findComponentByName($payload, 'send_reset_email'))->not->toBeNull();
    expect(findComponentByName($payload, 'send_verification_email'))->not->toBeNull();
    expect(findComponentByName($payload, 'register_buttons'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_cancel_register'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_submit_register'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_delete_user'))->not->toBeNull();
});
