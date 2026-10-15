<?php

namespace Tests\Feature;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\UI;
use Tests\TestCase;

class MockDataScreen extends Screen
{
    public static ?string $layout = null;

    protected Button $action_btn;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->action_btn = UI::button('action_btn')->label('Original Label');
        $container->add($this->action_btn);
    }

    public function getCategoryList(array $params = []): array
    {
        return [
            'categories' => ['tech', 'news'],
            'requested_by' => $params['author'] ?? 'anonymous',
        ];
    }

    public function getPlainText(): string
    {
        return 'Hello from USIM query';
    }

    public function getSvgBadge(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10"/></svg>';
    }

    public function getHtmlFragment(): string
    {
        return '<div class="alert">Info Message</div>';
    }

    public function getSamplePng(): string
    {
        // 1x1 transparent PNG
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    }

    public function getMutateComponentTree(): array
    {
        // Attempting to mutate UI in GET query
        $this->action_btn->label('Mutated Label');

        return ['mutated' => true];
    }
}

class MockUnauthorizedScreen extends Screen
{
    public static ?string $layout = null;

    public static function authorize(): bool
    {
        return false;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(UI::button('restricted_btn'));
    }

    public function getRestrictedData(): array
    {
        return ['secret' => true];
    }
}

it('fetches JSON data from screen query method via GET /api/ui-data', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=category_list&parameters[author]=carlos");

    $response->assertOk();
    $response->assertHeader('Content-Type', 'application/json');
    expect($response->json())->toBe([
        'categories' => ['tech', 'news'],
        'requested_by' => 'carlos',
    ]);
});

it('supports JSON string encoded in parameters query parameter', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');
    $paramJson = urlencode(json_encode(['author' => 'maria']));

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=category_list&parameters={$paramJson}");

    $response->assertOk();
    expect($response->json('requested_by'))->toBe('maria');
});

it('resolves action names flexibly across getPascalCase, snake_case and get_ prefixes', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');

    // snake_case -> getCategoryList
    $res1 = $this->getJson("/api/ui-data?component_id={$btnId}&action=category_list");
    $res1->assertOk();

    // get_snake_case -> getCategoryList
    $res2 = $this->getJson("/api/ui-data?component_id={$btnId}&action=get_category_list");
    $res2->assertOk();

    // getCategoryList directly -> getCategoryList
    $res3 = $this->getJson("/api/ui-data?component_id={$btnId}&action=getCategoryList");
    $res3->assertOk();
});

it('returns plain text with text/plain Content-Type', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');

    $response = $this->get("/api/ui-data?component_id={$btnId}&action=plain_text");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/plain');
    expect($response->getContent())->toBe('Hello from USIM query');
});

it('returns SVG image with image/svg+xml Content-Type', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');

    $response = $this->get("/api/ui-data?component_id={$btnId}&action=svg_badge");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/svg+xml');
});

it('returns HTML with text/html Content-Type', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');

    $response = $this->get("/api/ui-data?component_id={$btnId}&action=html_fragment");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('text/html');
});

it('detects binary PNG buffer and returns image/png Content-Type', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');

    $response = $this->get("/api/ui-data?component_id={$btnId}&action=sample_png");

    $response->assertOk();
    expect($response->headers->get('Content-Type'))->toContain('image/png');
});

it('prevents component tree mutations in GET queries and returns 422', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=mutate_component_tree");

    $response->assertStatus(422);
    expect($response->json('error'))->toBe('Read-only lifecycle violation');
    expect($response->json('message'))->toContain('Component tree mutations are not permitted inside GET query methods');
});

it('returns 404 when query action is not implemented on screen', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockDataScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockDataScreen::class, 'action_btn');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=unknown_data_source");

    $response->assertStatus(404);
    expect($response->json('error'))->toContain("Query handler for action 'unknown_data_source' not implemented");
});

it('returns 404 when component_id cannot be mapped to a screen', function () {
    /** @var TestCase $this */
    $response = $this->getJson('/api/ui-data?component_id=99999999&action=test');

    $response->assertStatus(404);
    expect($response->json('error'))->toBe('Screen not found for this component');
});

it('returns 403 when screen authorization check fails', function () {
    /** @var TestCase $this */
    $screen = Screen::make(MockUnauthorizedScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(MockUnauthorizedScreen::class, 'restricted_btn');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=restricted_data");

    $response->assertStatus(403);
});
