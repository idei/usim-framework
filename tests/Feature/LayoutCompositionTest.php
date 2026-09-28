<?php

use App\UI\Layouts\MainLayout;
use App\UI\Screens\Admin\AdminMenu;
use App\UI\Screens\Home;
use App\UI\Screens\Testing\CustomAdminFramedScreen;
use App\UI\Screens\Testing\CustomKioskScreen;
use App\UI\Screens\Testing\CustomSlugFramedScreen;
use Idei\Usim\Screen;

it('frames standard screens with MainLayout and main_menu_container slot', function () {
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    $mainMenuContainer = $ui->component('main_menu_container');
    expect($mainMenuContainer)->not->toBeNull();

    $contentContainer = $ui->component('content_container');
    expect($contentContainer)->not->toBeNull();

    // Contains the embedded menu
    $mainMenu = $ui->component('main_menu');
    expect($mainMenu)->not->toBeNull();
    expect($mainMenu->data()['type'])->toBe('menudropdown');

    // Contains home screen components inside content slot
    $welcome = $ui->component('welcome_usim');
    expect($welcome)->not->toBeNull();
});

it('renders screens without layout when layout is null (kiosk / standalone mode)', function () {
    expect(CustomKioskScreen::hasMenu())->toBeFalse();
    expect(CustomKioskScreen::getLayoutClass())->toBeNull();

    $ui = uiScenario($this, CustomKioskScreen::class, ['reset' => true]);

    // Should NOT have layout slots
    expect(fn () => $ui->component('main_menu_container'))->toThrow(\RuntimeException::class);
    expect(fn () => $ui->component('main_menu'))->toThrow(\RuntimeException::class);

    // Should have its own content
    $title = $ui->component('kiosk_title');
    expect($title)->not->toBeNull();
    expect($title->data()['text'])->toBe('Standalone Kiosk');
});

it('supports custom menu screen by class string in layout composition', function () {
    $ui = uiScenario($this, CustomAdminFramedScreen::class, ['reset' => true]);

    // Has layout slots
    expect($ui->component('main_menu_container'))->not->toBeNull();
    expect($ui->component('content_container'))->not->toBeNull();

    // Embedded menu is AdminMenu (has admin_menu dropdown, not standard main_menu)
    $adminMenu = $ui->component('admin_menu');
    expect($adminMenu)->not->toBeNull();
    expect($adminMenu->data()['type'])->toBe('menudropdown');
    expect($adminMenu->data()['trigger']['label'] ?? '')->toBe('⚡ Admin');

    // Screen content
    expect($ui->component('admin_screen_title')->data()['text'])->toBe('Admin Screen Content');
});

it('supports custom menu screen by route slug in layout composition', function () {
    $resolved = Screen::resolveScreenClassFromSlug('admin/admin-menu');
    expect($resolved)->toBe(AdminMenu::class);

    $ui = uiScenario($this, CustomSlugFramedScreen::class, ['reset' => true]);

    $adminMenu = $ui->component('admin_menu');
    expect($adminMenu)->not->toBeNull();
    expect($adminMenu->data()['trigger']['label'] ?? '')->toBe('⚡ Admin');
});

it('resolves screen slug bidirectionally', function () {
    expect(Screen::resolveScreenSlug(AdminMenu::class))->toBe('admin/admin-menu');
    expect(Screen::resolveScreenClassFromSlug('admin/admin-menu'))->toBe(AdminMenu::class);
    expect(Screen::resolveScreenSlug(Home::class))->toBe('home');
});
