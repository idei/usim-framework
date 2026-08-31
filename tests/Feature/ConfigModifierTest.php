<?php

use Idei\Usim\Support\CodeModifier\ConfigModifier;

it('updates boolean and nested string values in a config file', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_config_') . '.php';

    $initialContent = <<<'PHP'
<?php

return [
    'column_names' => [
        'team_foreign_key' => 'team_id',
    ],
    'teams' => false,
];
PHP;

    file_put_contents($tempFile, $initialContent);

    $success = ConfigModifier::update($tempFile, [
        'teams' => true,
        'column_names.team_foreign_key' => 'usim_unit_id',
    ]);

    expect($success)->toBeTrue();

    $loadedConfig = require $tempFile;

    expect($loadedConfig['teams'])->toBeTrue();
    expect($loadedConfig['column_names']['team_foreign_key'])->toBe('usim_unit_id');

    @unlink($tempFile);
});

it('inserts new nested keys if they do not exist', function () {
    $tempFile = tempnam(sys_get_temp_dir(), 'test_config_') . '.php';

    $initialContent = <<<'PHP'
<?php

return [
    'existing' => 'value',
];
PHP;

    file_put_contents($tempFile, $initialContent);

    ConfigModifier::update($tempFile, [
        'teams' => true,
        'nested.deep.key' => 'custom_value',
        'number' => 42,
        'nullable' => null,
    ]);

    $loadedConfig = require $tempFile;

    expect($loadedConfig['existing'])->toBe('value');
    expect($loadedConfig['teams'])->toBeTrue();
    expect($loadedConfig['nested']['deep']['key'])->toBe('custom_value');
    expect($loadedConfig['number'])->toBe(42);
    expect($loadedConfig['nullable'])->toBeNull();

    @unlink($tempFile);
});

