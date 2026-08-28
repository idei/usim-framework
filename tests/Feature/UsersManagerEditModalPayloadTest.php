<?php

use App\UI\Screens\Admin\UsersManager;

it('returns edit-user modal when root selects a user for editing', function () {

    /** @var \Tests\TestCase $this */
    $rootUser = $this->createRootUser();
    $targetUser = $this->createDefaultUser();

    $this->actingAs($rootUser);

    $ui = uiScenario($this, UsersManager::class, ['reset' => true]);

    $response = $ui->action('users_table', 'users_table_row_clicked', [
        'model_id' => $targetUser->id,
    ]);

    $response->assertOk();

    $payload = $response->json();

    expect(findComponentByName($payload, 'edit_user_dialog'))->not->toBeNull();
    expect(findComponentByName($payload, 'user_id'))->not->toBeNull();
    expect(findComponentByName($payload, 'name'))->not->toBeNull();
    expect(findComponentByName($payload, 'email'))->not->toBeNull();
    expect(findComponentByName($payload, 'roles'))->not->toBeNull();
    expect(findComponentByName($payload, 'send_reset_email'))->not->toBeNull();
    expect(findComponentByName($payload, 'send_verification_email'))->not->toBeNull();
    expect(findComponentByName($payload, 'edit_user_buttons'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_cancel_register'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_submit_register'))->not->toBeNull();
    expect(findComponentByName($payload, 'btn_delete_user'))->not->toBeNull();
});
