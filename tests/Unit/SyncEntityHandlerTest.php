<?php

use Idei\Usim\Contracts\DeviceSyncResult;
use Idei\Usim\Contracts\LangSyncResult;
use Idei\Usim\Contracts\RoleSyncResult;
use Idei\Usim\Contracts\SyncEntityHandlerInterface;
use Idei\Usim\Contracts\SyncResultInterface;
use Idei\Usim\Contracts\UserSyncResult;
use Illuminate\Console\Command;
use Illuminate\Console\OutputStyle;
use Tests\TestCase;

it('supports custom sync handlers registered via tag adhering to Open/Closed Principle', function () {
    $customHandler = new class implements SyncEntityHandlerInterface
    {
        public bool $executed = false;

        public function getIdentifier(): string
        {
            return 'custom_module';
        }

        public function getDescription(): string
        {
            return 'custom module entities';
        }

        public function getAliases(): array
        {
            return ['custom_alias'];
        }

        public function getOrder(): int
        {
            return 999;
        }

        public function sync(Command|OutputStyle|null $output = null): SyncResultInterface
        {
            $this->executed = true;

            if ($output instanceof Command) {
                $output->info('Custom module synchronized successfully.');
            }

            return new UserSyncResult(usersCreated: 42);
        }
    };

    app()->tag([get_class($customHandler)], 'usim.sync_handlers');
    app()->instance(get_class($customHandler), $customHandler);

    /** @var TestCase $this */
    $this->artisan('usim:sync custom_module')
        ->expectsOutput('Custom module synchronized successfully.')
        ->assertSuccessful();

    expect($customHandler->executed)->toBeTrue();
});

it('exposes typed immutable properties and backwards-compatible array access on all sync DTOs', function () {
    // 1. UserSyncResult
    $userResult = new UserSyncResult(usersCreated: 5, usersUpdated: 2, usersDeleted: 1);
    expect($userResult->usersCreated)->toBe(5)
        ->and($userResult->isSuccess())->toBeTrue()
        ->and($userResult->isSkipped())->toBeFalse()
        ->and($userResult['users_created'])->toBe(5)
        ->and($userResult['users_updated'])->toBe(2)
        ->and($userResult['users_deleted'])->toBe(1);

    // 2. RoleSyncResult
    $roleResult = new RoleSyncResult(permissionsCreated: 10, rolesCreated: 3, rolesUpdated: 1);
    expect($roleResult->permissionsCreated)->toBe(10)
        ->and($roleResult['permissions_created'])->toBe(10)
        ->and($roleResult['roles_created'])->toBe(3);

    // 3. DeviceSyncResult
    $deviceResult = new DeviceSyncResult(synced: ['tablet-1', 'pos-2']);
    expect($deviceResult->synced)->toHaveCount(2)
        ->and($deviceResult['synced'])->toEqual(['tablet-1', 'pos-2']);

    // 4. LangSyncResult
    $langResult = new LangSyncResult(languagesCreated: 2, languagesUpdated: 1);
    expect($langResult->languagesCreated)->toBe(2)
        ->and($langResult['languages_created'])->toBe(2)
        ->and($langResult['languages_updated'])->toBe(1);
});
