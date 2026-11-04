<?php

namespace Tests\Feature;

use App\Models\User;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\UI;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TypedBuildBaseUIScreen extends Screen
{
    public static ?string $layout = null;

    protected Label $info_label;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, User $user, int $tab = 1): void
    {
        $this->info_label = UI::label('info_label')->text("User: {$user->name} | Tab: {$tab}");
        $container->add($this->info_label);
    }

    protected function postLoadUI(User $user): void
    {
        if (isset($this->info_label)) {
            $this->info_label->text("PostLoad: {$user->name}");
        }
    }
}

class NavigationSourceScreen extends Screen
{
    public static ?string $layout = null;

    protected Button $nav_btn;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->nav_btn = UI::button('nav_btn')->label('Go to User');
        $container->add($this->nav_btn);
    }

    public function onGoToUser(int $user_id): void
    {
        $user = User::findOrFail($user_id);
        $this->navigate(TypedBuildBaseUIScreen::class, ['user' => $user, 'tab' => 3]);
    }
}

class ProtectedTestScreen extends Screen
{
    public static ?string $layout = null;

    public static function authorize(): bool
    {
        return false; // Requires login
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::label('protected_label')->text('Secret'));
    }
}

uses(RefreshDatabase::class);

it('hydrates Eloquent model and scalar parameters directly into buildBaseUI', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['name' => 'Alice']);

    $screen = Screen::make(TypedBuildBaseUIScreen::class);
    $screen->render(
        queryParams: ['user' => $user->id, 'tab' => '2'],
        buildParams: ['user' => $user, 'tab' => 2]
    );

    $label = $screen->getContainer()?->findByName('info_label');
    expect($label)->toBeInstanceOf(Label::class);
    // postLoadUI also runs during render and updates to "PostLoad: Alice"
    expect($label->get('text'))->toBe('PostLoad: Alice');
});

it('hydrates buildBaseUI parameters from URL query string when buildParams is empty', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['name' => 'Bob']);

    $screen = Screen::make(TypedBuildBaseUIScreen::class);
    $screen->render(
        queryParams: ['user_id' => (string) $user->id, 'tab' => '5']
    );

    $label = $screen->getContainer()?->findByName('info_label');
    expect($label)->toBeInstanceOf(Label::class);
    expect($label->get('text'))->toBe('PostLoad: Bob');
});

it('normalizes Eloquent models and enums into scalar query string when navigating', function () {
    /** @var TestCase $this */
    $user = User::factory()->create(['name' => 'Charlie']);

    $screen = Screen::make(NavigationSourceScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(NavigationSourceScreen::class, 'nav_btn');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'go_to_user',
        'parameters' => [
            'user_id' => $user->id,
        ],
    ]);

    $response->assertOk();
    $data = $response->json();

    expect($data['navigate'] ?? null)->not->toBeNull();
    $navUrl = $data['navigate']['url'] ?? '';

    // The navigate URL should include the normalized user id (?user=...&tab=3)
    expect($navUrl)->toContain('user='.$user->id);
    expect($navUrl)->toContain('tab=3');
});

it('preserves intended URL with query string when unauthenticated user accesses protected screen', function () {
    /** @var TestCase $this */
    $response = $this->getJson('/api/ui/admin/users-manager?post_id=42&view=full');

    $response->assertOk();
    $data = $response->json();

    expect($data['redirect'] ?? null)->not->toBeNull();
    $redirect = $data['redirect'];

    expect($redirect)->toBe(url('/auth/login'));
    expect(session('url.intended'))->toBe('/admin/users-manager?post_id=42&view=full');
});

it('redirects to intended URL upon successful login when redirect parameter is present', function () {
    /** @var TestCase $this */
    app(\Idei\Usim\Support\RoleAndPermissionSyncService::class)->sync();

    $user = User::factory()->create([
        'email' => 'authuser@example.com',
        'password' => bcrypt('secret123'),
    ]);

    $loginScreen = Screen::make(\App\UI\Screens\Auth\Login::class);
    $loginScreen->render(
        queryParams: ['redirect' => '/publish_post?post_id=99']
    );

    $loginBtnId = UIIdGenerator::generateFromName(\App\UI\Screens\Auth\Login::class, 'btn_submit_login');

    $response = $this->postJson('/api/ui-event?redirect='.urlencode('/publish_post?post_id=99'), [
        'component_id' => $loginBtnId,
        'event' => 'click',
        'action' => 'submit_login',
        'parameters' => [
            'login_email' => 'authuser@example.com',
            'login_password' => 'secret123',
            'login_remember' => false,
        ],
    ]);

    $response->assertOk();
    $changes = $response->json();

    expect($changes['redirect'] ?? null)->toBe('/publish_post?post_id=99');
});
