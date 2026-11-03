<?php

namespace Tests\Feature;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\UI;
use Tests\TestCase;

enum FeatureTestStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
}

class HydrationTestScreen extends Screen
{
    public static ?string $layout = null;

    protected Button $target_btn;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->target_btn = UI::button('target_btn')->label('Initial');
        $container->add($this->target_btn);
    }

    public function onSelectUser(int $user_id, string $tab = 'profile'): void
    {
        $this->target_btn->label("User: {$user_id} | Tab: {$tab}");
    }

    public function onChangeStatus(FeatureTestStatus $status): void
    {
        $this->target_btn->label("Status: {$status->value}");
    }

    public function getUserProfile(int $user_id, string $format = 'json'): array
    {
        return [
            'id' => $user_id,
            'format' => $format,
        ];
    }
}

it('executes screen action with typed scalar and default parameters via POST /api/ui-event', function () {
    /** @var TestCase $this */
    $screen = Screen::make(HydrationTestScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(HydrationTestScreen::class, 'target_btn');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'select_user',
        'parameters' => [
            'user_id' => '123', // String coerced to integer
        ],
    ]);

    $response->assertOk();
    $changes = $response->json();
    expect($changes[(string) $btnId]['label'] ?? null)->toBe('User: 123 | Tab: profile');
});

it('returns HTTP 422 when required action parameter is missing during POST /api/ui-event', function () {
    /** @var TestCase $this */
    $screen = Screen::make(HydrationTestScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(HydrationTestScreen::class, 'target_btn');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'select_user',
        'parameters' => [
            // user_id is missing
            'tab' => 'settings',
        ],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.user_id'))->not->toBeNull();
});

it('returns HTTP 422 when action parameter cannot be coerced to expected type', function () {
    /** @var TestCase $this */
    $screen = Screen::make(HydrationTestScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(HydrationTestScreen::class, 'target_btn');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'select_user',
        'parameters' => [
            'user_id' => 'invalid_integer',
        ],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.user_id'))->not->toBeNull();
});

it('executes screen action with BackedEnum parameter via POST /api/ui-event', function () {
    /** @var TestCase $this */
    $screen = Screen::make(HydrationTestScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(HydrationTestScreen::class, 'target_btn');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'change_status',
        'parameters' => [
            'status' => 'suspended',
        ],
    ]);

    $response->assertOk();
    $changes = $response->json();
    expect($changes[(string) $btnId]['label'] ?? null)->toBe('Status: suspended');
});

it('executes screen query with typed parameters via GET /api/ui-data', function () {
    /** @var TestCase $this */
    $screen = Screen::make(HydrationTestScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(HydrationTestScreen::class, 'target_btn');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=user_profile&user_id=789&format=xml");

    $response->assertOk();
    expect($response->json())->toBe([
        'id' => 789,
        'format' => 'xml',
    ]);
});

it('returns HTTP 422 when required query parameter is missing via GET /api/ui-data', function () {
    /** @var TestCase $this */
    $screen = Screen::make(HydrationTestScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(HydrationTestScreen::class, 'target_btn');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=user_profile");

    $response->assertStatus(422);
    expect($response->json('errors.user_id'))->not->toBeNull();
});

