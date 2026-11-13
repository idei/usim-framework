<?php

use Idei\Usim\Enums\Align;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Support\UsimIconRegistry;
use Idei\Usim\UI;
use Illuminate\Support\Facades\File;

enum TestSampleIcon: string
{
    case SETTINGS = 'settings';
    case USER = 'user';
}

it('builds Button and NewLabel with agnostic icon config and omits legacy icon properties', function () {
    $button = UI::button('btn_save')
        ->label('Guardar')
        ->icon('check', Align::RIGHT)
        ->size(TextSize::SMALL)
        ->style('primary');

    $buttonJson = $button->toJson()[$button->getId()];

    expect($buttonJson['icon'])->toBe('check')
        ->and($buttonJson['icon_position'])->toBe('right')
        ->and($buttonJson['size'])->toBe('small')
        ->and($buttonJson)->not->toHaveKey('icon_color')
        ->and($buttonJson)->not->toHaveKey('icon_size')
        ->and($buttonJson)->not->toHaveKey('icon_only');

    $label = UI::newLabel('lbl_user')
        ->text('Perfil')
        ->icon(TestSampleIcon::USER, Align::LEFT);

    $labelJson = $label->toJson()[$label->getId()];

    expect($labelJson['icon'])->toBe('user')
        ->and($labelJson['icon_position'])->toBe('left')
        ->and($labelJson)->not->toHaveKey('icon_color')
        ->and($labelJson)->not->toHaveKey('icon_size')
        ->and($labelJson)->not->toHaveKey('icon_only');
});

it('supports emoji and BackedEnum icons cleanly on Button and NewLabel', function () {
    $button = UI::button('btn_settings')->icon(TestSampleIcon::SETTINGS);
    $label = UI::newLabel('lbl_rocket')->text('Launch')->icon('🚀');

    $buttonJson = $button->toJson()[$button->getId()];
    $labelJson = $label->toJson()[$label->getId()];

    expect($buttonJson['icon'])->toBe('settings')
        ->and($buttonJson['label'])->toBe('')
        ->and($labelJson['icon'])->toBe('🚀')
        ->and($labelJson['icon_position'])->toBe('left');
});

it('discovers icons with user-override and alias priority in UsimIconRegistry', function () {
    $userIconsDir = public_path('__test_user_icons');
    $vendorIconsDir = public_path('__test_vendor_icons');

    try {
        File::ensureDirectoryExists($userIconsDir.'/brand');
        File::ensureDirectoryExists($vendorIconsDir);

        File::put($vendorIconsDir.'/settings.svg', '<svg viewBox="0 0 24 24"></svg>');
        File::put($vendorIconsDir.'/check.svg', '<svg viewBox="0 0 24 24"></svg>');

        // User overrides settings with custom PNG and adds a nested PNG + dual SVG/PNG
        File::put($userIconsDir.'/settings.png', 'png-bytes');
        File::put($userIconsDir.'/brand/logo.png', 'png-bytes');
        File::put($userIconsDir.'/badge.svg', '<svg viewBox="0 0 24 24"></svg>');
        File::put($userIconsDir.'/badge.png', 'png-bytes');

        config([
            'usim.icons.paths' => ['__test_user_icons', '__test_vendor_icons'],
            'usim.icons.aliases' => ['aliased-icon' => '/custom/path/icon.svg'],
        ]);

        $manifest = app(UsimIconRegistry::class)->getManifest();

        expect($manifest['check'])->toBe('/__test_vendor_icons/check.svg')
            ->and($manifest['settings'])->toBe('/__test_user_icons/settings.png')
            ->and($manifest['brand/logo'])->toBe('/__test_user_icons/brand/logo.png')
            ->and($manifest['badge'])->toBe('/__test_user_icons/badge.svg')
            ->and($manifest['badge.png'])->toBe('/__test_user_icons/badge.png')
            ->and($manifest['aliased-icon'])->toBe('/custom/path/icon.svg');
    } finally {
        File::deleteDirectory($userIconsDir);
        File::deleteDirectory($vendorIconsDir);
    }
});

it('publishes standard icon library with vendor:publish tag=usim-icons', function () {
    $publishedDir = public_path('vendor/idei/usim/icons');
    $existedBefore = File::isDirectory($publishedDir);
    $backupDir = sys_get_temp_dir().'/usim_icons_backup_'.uniqid();

    try {
        if ($existedBefore) {
            File::copyDirectory($publishedDir, $backupDir);
            File::deleteDirectory($publishedDir);
        }

        $this->artisan('vendor:publish', [
            '--tag' => 'usim-icons',
            '--force' => true,
        ])->assertSuccessful();

        expect(File::exists($publishedDir.'/settings.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/check.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/user.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/sun.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/moon.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/sparkles.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/bot.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/key.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/shield-check.svg'))->toBeTrue()
            ->and(File::exists($publishedDir.'/grip-vertical.svg'))->toBeTrue();

        $manifest = app(UsimIconRegistry::class)->getManifest();
        expect($manifest['settings'] ?? null)->toBe('/vendor/idei/usim/icons/settings.svg')
            ->and($manifest['sparkles'] ?? null)->toBe('/vendor/idei/usim/icons/sparkles.svg')
            ->and($manifest['bot'] ?? null)->toBe('/vendor/idei/usim/icons/bot.svg');
    } finally {
        File::deleteDirectory($publishedDir);
        if ($existedBefore && File::isDirectory($backupDir)) {
            File::copyDirectory($backupDir, $publishedDir);
            File::deleteDirectory($backupDir);
        }
    }
});

it('injects USIM_ICONS manifest into app shell view', function () {
    $response = $this->get('/home');
    $response->assertOk();
    $response->assertSee('window.USIM_ICONS =', false);
});

