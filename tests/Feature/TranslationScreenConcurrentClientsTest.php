<?php

use App\UI\Screens\Admin\TranlateManager;
use Idei\Usim\Support\UIStateManager;
use Illuminate\Support\Str;
use Tests\TestCase;

it('isolates translation screen state across concurrent browser clients', function () {
    /** @var TestCase $test */
    $test = $this;

    $loginResult = $test->loginAs('admin');
    $loginResponse = $loginResult['response'];
    $loginResponse->assertOk();
    expect($loginResponse->json('toast.type'))->toBe('success');

    $clientCount = concurrentClientCount();
    $clients = [];
    $expectedSearchComponentId = null;
    $expectedLanguageFilterComponentId = null;

    for ($index = 1; $index <= $clientCount; $index++) {
        $clientId = (string) Str::uuid();

        $initial = $test
            ->withSession([UIStateManager::CLIENT_ID_COOKIE => $clientId])
            ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $clientId)
            ->getJson(screenApiUrl(TranlateManager::class, ['reset' => true]));

        $initial->assertOk();

        $searchComponent = findComponentByName($initial->json(), 'search_translations');
        expect($searchComponent)->not->toBeNull();

        $searchComponentId = (int) ($searchComponent['_id'] ?? 0);
        expect($searchComponentId)->toBeGreaterThan(0);

        $languageFilter = findComponentByName($initial->json(), 'language_filter');
        expect($languageFilter)->not->toBeNull();

        $languageFilterComponentId = (int) ($languageFilter['_id'] ?? 0);
        expect($languageFilterComponentId)->toBeGreaterThan(0);

        if ($expectedSearchComponentId === null) {
            $expectedSearchComponentId = $searchComponentId;
        }

        if ($expectedLanguageFilterComponentId === null) {
            $expectedLanguageFilterComponentId = $languageFilterComponentId;
        }

        // Same screen/component should keep deterministic IDs for all clients.
        expect($searchComponentId)->toBe($expectedSearchComponentId);
        expect($languageFilterComponentId)->toBe($expectedLanguageFilterComponentId);

        $languageFilterValue = pickLanguageFilterValue($languageFilter, $index);

        $clients[] = [
            'client_id' => $clientId,
            'search_component_id' => $searchComponentId,
            'language_filter_component_id' => $languageFilterComponentId,
            'search_value' => "client_{$index}_" . Str::lower(Str::random(6)),
            'language_filter_value' => $languageFilterValue,
        ];
    }

    foreach ($clients as $client) {
        $inputResponse = $test
            ->withSession([UIStateManager::CLIENT_ID_COOKIE => $client['client_id']])
            ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $client['client_id'])
            ->postJson('/api/ui-event', [
                'component_id' => $client['search_component_id'],
                'event' => 'input',
                'action' => 'search_translations',
                'parameters' => [
                    'value' => $client['search_value'],
                ],
            ]);

        $inputResponse->assertOk();

        $actionResponse = $test
            ->withSession([UIStateManager::CLIENT_ID_COOKIE => $client['client_id']])
            ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $client['client_id'])
            ->postJson('/api/ui-event', [
                'component_id' => $client['language_filter_component_id'],
                'event' => 'action',
                'action' => 'language_filter_change',
                'parameters' => [
                    'value' => $client['language_filter_value'],
                ],
            ]);

        $actionResponse->assertOk();
    }

    foreach ($clients as $client) {
        $reload = $test
            ->withSession([UIStateManager::CLIENT_ID_COOKIE => $client['client_id']])
            ->withCookie(UIStateManager::CLIENT_ID_COOKIE, $client['client_id'])
            ->getJson(screenApiUrl(TranlateManager::class));

        $reload->assertOk();

        $searchComponent = findComponentByName($reload->json(), 'search_translations');
        expect($searchComponent)->not->toBeNull();
        expect((string) ($searchComponent['value'] ?? ''))->toBe($client['search_value']);

        $languageFilter = findComponentByName($reload->json(), 'language_filter');
        expect($languageFilter)->not->toBeNull();
        expect((string) ($languageFilter['value'] ?? 'all'))->toBe($client['language_filter_value']);
    }
});

function concurrentClientCount(): int
{
    $configured = (int) env('USIM_CONCURRENT_CLIENTS', 2);

    return max(2, min(100, $configured));
}

function pickLanguageFilterValue(array $languageFilter, int $index): string
{
    $options = $languageFilter['options'] ?? [];

    if (!is_array($options) || count($options) === 0) {
        return 'all';
    }

    $values = [];

    foreach ($options as $option) {
        if (!is_array($option)) {
            continue;
        }

        $value = (string) ($option['value'] ?? '');
        if ($value !== '') {
            $values[] = $value;
        }
    }

    $values = array_values(array_unique($values));

    if (count($values) === 0) {
        return 'all';
    }

    // Prefer non-default values when available so each client exercises a real action path.
    $preferred = array_values(array_filter($values, static fn (string $value): bool => $value !== 'all'));
    $pool = count($preferred) > 0 ? $preferred : $values;
    $position = ($index - 1) % count($pool);

    return $pool[$position];
}
