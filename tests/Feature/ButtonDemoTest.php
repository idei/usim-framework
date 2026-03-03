<?php

use App\UI\Screens\Demo\ButtonDemo;
use Idei\Usim\Services\Support\UIIdGenerator;
use Idei\Usim\Services\UIChangesCollector;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

function findComponentByName(array $payload, string $name): ?array
{
    foreach ($payload as $key => $component) {
        if (!is_array($component)) {
            continue;
        }

        if (($component['name'] ?? null) === $name) {
            return $component;
        }
    }

    return null;
}

function startButtonDemoSession(TestCase $test): array
{
    app()->forgetScopedInstances();

    $screen = $test->getJson('/api/ui/demo/button-demo?reset=true');
    $screen->assertOk();

    $button = findComponentByName($screen->json(), 'btn_toggle');
    expect($button)->not->toBeNull();

    return [
        'component_id' => (int) $button['_id'],
        'usim' => (string) ($screen->json('storage.usim') ?? ''),
    ];
}

function buttonClick(TestCase $test, int $componentId, string $usimStorage, ?bool $storeState = null): TestResponse
{
    app()->forgetScopedInstances();

    $payload = [
        'component_id' => $componentId,
        'event' => 'click',
        'action' => 'toggle_label',
        'parameters' => [],
    ];

    if ($usimStorage !== '') {
        $payload['usim'] = $usimStorage;
    }

    if ($storeState !== null) {
        $payload['storage'] = [
            'store_state' => $storeState,
        ];
    }

    return $test->postJson('/api/ui-event', $payload);
}

function usimFromState(bool $state): string
{
    return encrypt(json_encode([
        'store_state' => $state,
    ]));
}

function assertButtonState(TestResponse $response, int $componentId, string $label, string $style): void
{
    $updatedButton = $response->json((string) $componentId);
    expect($updatedButton)->toBeArray();
    expect($updatedButton['label'] ?? null)->toBe($label);
    expect($updatedButton['style'] ?? null)->toBe($style);
}

it('loads button demo screen with btn_toggle component', function () {
    /** @var \Tests\TestCase $this */
    $response = $this->getJson('/api/ui/demo/button-demo?reset=true');

    $response->assertOk();

    $button = findComponentByName($response->json(), 'btn_toggle');
    expect($button)->not->toBeNull();
    expect($button['type'])->toBe('button');
    expect($button['label'])->toBe('Click Me!');
    expect($button['action'])->toBe('toggle_label');
});

it('toggles button label to clicked when toggle_label event is sent', function () {
    /** @var \Tests\TestCase $this */
    $session = startButtonDemoSession($this);
    $componentId = $session['component_id'];

    $response = buttonClick($this, $componentId, $session['usim'], false);

    $response->assertOk();

    assertButtonState($response, $componentId, 'Clicked! 🎉', 'success');
});

it('alternates button content on consecutive clicks', function () {
    $componentId = UIIdGenerator::generateFromName(ButtonDemo::class, 'btn_toggle');

    app()->forgetScopedInstances();
    $service = app(ButtonDemo::class);
    $service->initializeEventContext(['store_state' => false]);
    $service->onToggleLabel([]);
    $service->finalizeEventContext(reload: true);
    $result1 = app(UIChangesCollector::class)->all();
    expect($result1[(string) $componentId]['label'] ?? null)->toBe('Clicked! 🎉');
    expect($result1[(string) $componentId]['style'] ?? null)->toBe('success');

    app()->forgetScopedInstances();
    $service = app(ButtonDemo::class);
    $service->initializeEventContext(['store_state' => true]);
    $service->onToggleLabel([]);
    $service->finalizeEventContext(reload: true);
    $result2 = app(UIChangesCollector::class)->all();
    expect($result2[(string) $componentId]['label'] ?? null)->toBe('Click Me!');
    expect($result2[(string) $componentId]['style'] ?? null)->toBe('primary');

    app()->forgetScopedInstances();
    $service = app(ButtonDemo::class);
    $service->initializeEventContext(['store_state' => false]);
    $service->onToggleLabel([]);
    $service->finalizeEventContext(reload: true);
    $result3 = app(UIChangesCollector::class)->all();
    expect($result3[(string) $componentId]['label'] ?? null)->toBe('Clicked! 🎉');
    expect($result3[(string) $componentId]['style'] ?? null)->toBe('success');
});
