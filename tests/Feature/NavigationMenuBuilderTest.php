<?php

use Idei\Usim\Components\MenuDropdown;
use Idei\Usim\UI;
use Idei\Usim\Navigation\Contracts\MenuProviderInterface;
use Idei\Usim\Navigation\MenuBuilder;
use Idei\Usim\Navigation\MenuItem;
use Idei\Usim\Navigation\TriggerConfig;
use Idei\Usim\Screen;
use Idei\Usim\Components\Container;
use Illuminate\Support\Facades\Gate;

class DummyScreenForNav extends Screen
{
    public static function getMenuLabel(): string
    {
        return 'Dummy Nav';
    }

    public static function getMenuIcon(): ?string
    {
        return '🚀';
    }

    public static function checkAccess(): array
    {
        return ['allowed' => true];
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
    }
}

class ForbiddenScreenForNav extends Screen
{
    public static function checkAccess(): array
    {
        return ['allowed' => false];
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
    }
}

class SampleMenuProvider implements MenuProviderInterface
{
    public function build(MenuBuilder $menu): void
    {
        $menu->link('From Provider', '/provider', '📦');
    }
}

it('builds a menu declaratively with links, actions, and submenus', function () {
    $builder = MenuBuilder::make('test_menu')
        ->trigger('☰ Options')
        ->position('bottom-right');

    $builder->link('Home', '/', '🏠');
    $builder->action('Profile', 'open_profile', ['id' => 1], '👤');
    $builder->separator();
    $builder->submenu('Tools', function (MenuBuilder $sub) {
        $sub->link('Settings', '/settings', '⚙️');
    }, '🛠️');

    $dropdown = $builder->render();

    expect($dropdown)->toBeInstanceOf(MenuDropdown::class);
    $config = $dropdown->toJson()[$dropdown->getId()];

    expect($config['trigger']['label'])->toBe('☰ Options');
    expect($config['position'])->toBe('bottom-right');
    expect($config['items'])->toHaveCount(4);
    expect($config['items'][0]['label'])->toBe('Home');
    expect($config['items'][0]['url'])->toBe('/');
    expect($config['items'][1]['label'])->toBe('Profile');
    expect($config['items'][1]['action'])->toBe('open_profile');
    expect($config['items'][2]['type'])->toBe('separator');
    expect($config['items'][3]['label'])->toBe('Tools');
    expect($config['items'][3]['submenu'])->toHaveCount(1);
    expect($config['items'][3]['submenu'][0]['label'])->toBe('Settings');
});

it('filters items conditionally using when and permissions', function () {
    Gate::define('admin.access', fn (?\App\Models\User $user = null) => false);
    Gate::define('user.access', fn (?\App\Models\User $user = null) => true);

    $builder = MenuBuilder::make('conditional_menu');
    $builder->link('Visible When', '/when-true', when: true);
    $builder->link('Hidden When', '/when-false', when: false);
    $builder->link('Visible Gate', '/gate-true')->can('user.access');
    $builder->link('Hidden Gate', '/gate-false')->can('admin.access');

    $dropdown = $builder->render();
    $config = $dropdown->toJson()[$dropdown->getId()];

    expect($config['items'])->toHaveCount(2);
    expect($config['items'][0]['label'])->toBe('Visible When');
    expect($config['items'][1]['label'])->toBe('Visible Gate');
});

it('evaluates screen checkAccess dynamically in MenuItem and MenuBuilder', function () {
    $builder = MenuBuilder::make('screen_menu');
    $builder->screen(DummyScreenForNav::class);
    $builder->screen(ForbiddenScreenForNav::class);

    $dropdown = $builder->render();
    $config = $dropdown->toJson()[$dropdown->getId()];

    expect($config['items'])->toHaveCount(1);
    expect($config['items'][0]['label'])->toBe('Dummy Nav');
    expect($config['items'][0]['icon'])->toBe('🚀');
});

it('supports modular composition with MenuProviderInterface', function () {
    $builder = MenuBuilder::make('provider_menu');
    $builder->link('Root Link', '/root');
    $builder->provider(new SampleMenuProvider());

    $dropdown = $builder->render();
    $config = $dropdown->toJson()[$dropdown->getId()];

    expect($config['items'])->toHaveCount(2);
    expect($config['items'][0]['label'])->toBe('Root Link');
    expect($config['items'][1]['label'])->toBe('From Provider');
});

it('configures trigger button using TriggerConfig DTO and fluent helpers', function () {
    // 1. Text trigger with icon and style
    $textBuilder = MenuBuilder::make('text_trigger')
        ->trigger('⚡ Admin', icon: 'bolt', style: 'primary');

    expect($textBuilder->getTrigger())->toBeInstanceOf(TriggerConfig::class);
    expect($textBuilder->getTrigger()?->label)->toBe('⚡ Admin');
    expect($textBuilder->getTrigger()?->icon)->toBe('bolt');
    expect($textBuilder->getTrigger()?->style)->toBe('primary');

    $dropdown1 = $textBuilder->render();
    $config1 = $dropdown1->toJson()[$dropdown1->getId()];
    expect($config1['trigger']['label'])->toBe('⚡ Admin');
    expect($config1['trigger']['icon'])->toBe('bolt');
    expect($config1['trigger']['style'])->toBe('primary');

    // 2. Image trigger via triggerImage helper
    $imgBuilder = MenuBuilder::make('image_trigger')
        ->triggerImage('https://example.com/avatar.jpg', alt: 'Profile', label: 'Jane Doe', style: 'rounded');

    expect($imgBuilder->getTrigger()?->image)->toBe('https://example.com/avatar.jpg');
    expect($imgBuilder->getTrigger()?->alt)->toBe('Profile');
    expect($imgBuilder->getTrigger()?->label)->toBe('Jane Doe');

    $dropdown2 = $imgBuilder->render();
    $config2 = $dropdown2->toJson()[$dropdown2->getId()];
    expect($config2['trigger']['image'])->toBe('https://example.com/avatar.jpg');
    expect($config2['trigger']['alt'])->toBe('Profile');
    expect($config2['trigger']['label'])->toBe('Jane Doe');

    // 3. Passing TriggerConfig DTO directly to trigger() and MenuDropdown::trigger()
    $customTrigger = TriggerConfig::image('https://example.com/logo.png', alt: 'Brand Logo');
    $dtoBuilder = MenuBuilder::make('dto_trigger')
        ->trigger($customTrigger);

    $dropdown3 = $dtoBuilder->render();
    $config3 = $dropdown3->toJson()[$dropdown3->getId()];
    expect($config3['trigger']['image'])->toBe('https://example.com/logo.png');
    expect($config3['trigger']['alt'])->toBe('Brand Logo');

    // Direct MenuDropdown delegation
    $directDropdown = UI::menuDropdown('direct_dropdown');
    $directDropdown->trigger(TriggerConfig::make('Direct Menu', 'compass'));
    $config4 = $directDropdown->toJson()[$directDropdown->getId()];
    expect($config4['trigger']['label'])->toBe('Direct Menu');
    expect($config4['trigger']['icon'])->toBe('compass');
});
