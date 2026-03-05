<?php

use Idei\Usim\Services\Support\UIStateManager;
use Idei\Usim\Services\UIChangesCollector;
use Illuminate\Support\Facades\Cache;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Str;
use Tests\TestCase;

if (!class_exists('UiMemoryRenderer')) {
    final class UiMemoryRenderer
    {
        private const RESERVED_KEYS = [
            'storage',
            'action',
            'redirect',
            'toast',
            'abort',
            'modal',
            'update_modal',
            'clear_uploaders',
            'set_uploader_existing_file',
        ];

        /** @var array<string, array<string, mixed>> */
        private array $componentsByKey = [];

        /** @var array<int, string> */
        private array $keyByInternalId = [];

        /** @var array<int, array<string, mixed>> */
        private array $issues = [];

        /** @var array<string, mixed> */
        private array $meta = [
            'storage' => [],
            'toasts' => [],
            'redirect' => null,
            'abort' => null,
            'action' => null,
            'update_modal' => null,
            'clear_uploaders' => [],
            'set_uploader_existing_file' => null,
        ];

        /** @var array<string, mixed> */
        private array $store = [];

        private bool $hasSnapshot = false;

        public function ingest(array $payload, string $mode = 'auto'): void
        {
            $resolvedMode = $this->resolveMode($mode);

            if ($resolvedMode === 'initial') {
                $this->applyInitial($payload);
                return;
            }

            $this->applyDelta($payload);
        }

        /** @return array<string, mixed>|null */
        public function componentByName(string $name): ?array
        {
            foreach ($this->componentsByKey as $component) {
                if (($component['name'] ?? null) === $name) {
                    return $component;
                }
            }

            return null;
        }

        /** @return array<int, array<string, mixed>> */
        public function issues(): array
        {
            return $this->issues;
        }

        public function usimStorage(): string
        {
            $current = $this->meta['storage']['usim'] ?? null;

            if ($this->store === [] && is_string($current) && $current !== '') {
                $decoded = $this->decodeUsimToStore($current);
                if (is_array($decoded)) {
                    $this->store = $decoded;
                }
            }

            $encoded = $this->encodeStoreToUsim();
            $this->meta['storage']['usim'] = $encoded;

            return $encoded;
        }

        /** @return array<string, mixed> */
        public function store(): array
        {
            return $this->store;
        }

        public function getStoreValue(string $key, mixed $default = null): mixed
        {
            return $this->store[$key] ?? $default;
        }

        public function mergeStore(array $values): void
        {
            foreach ($values as $key => $value) {
                if (!is_string($key)) {
                    continue;
                }

                $this->setStoreValue($key, $value);
            }
        }

        public function setStoreValue(string $key, mixed $value): void
        {
            if (!str_starts_with($key, 'store_')) {
                $this->issues[] = [
                    'type' => 'invalid_store_key',
                    'key' => $key,
                    'message' => 'Store key should start with store_.',
                ];
            }

            $this->store[$key] = $value;
            $this->meta['storage']['usim'] = $this->encodeStoreToUsim();
        }

        private function resolveMode(string $mode): string
        {
            if ($mode === 'initial' || $mode === 'delta') {
                return $mode;
            }

            if ($mode !== 'auto') {
                $this->issues[] = [
                    'type' => 'invalid_mode',
                    'mode' => $mode,
                    'message' => 'Unknown ingest mode. Falling back to auto.',
                ];
            }

            return $this->hasSnapshot ? 'delta' : 'initial';
        }

        private function applyInitial(array $payload): void
        {
            $this->componentsByKey = [];
            $this->keyByInternalId = [];
            $this->captureMeta($payload);

            foreach ($payload as $key => $value) {
                if ($this->isReservedKey((string) $key) || !is_array($value)) {
                    continue;
                }

                $this->setComponent((string) $key, $value);
            }

            $this->hasSnapshot = true;
        }

        private function applyDelta(array $payload): void
        {
            $this->captureMeta($payload);

            foreach ($payload as $key => $value) {
                $jsonKey = (string) $key;

                if ($this->isReservedKey($jsonKey) || !is_array($value)) {
                    continue;
                }

                if (isset($this->componentsByKey[$jsonKey])) {
                    $this->componentsByKey[$jsonKey] = $this->mergeComponent(
                        $this->componentsByKey[$jsonKey],
                        $value
                    );
                    $this->syncInternalId($jsonKey, $this->componentsByKey[$jsonKey]);
                    continue;
                }

                if ($this->looksLikeComponent($value)) {
                    $this->setComponent($jsonKey, $value);
                    continue;
                }

                $this->issues[] = [
                    'type' => 'missing_component',
                    'component' => $jsonKey,
                    'message' => 'Delta received for unknown component.',
                ];
            }

            $this->hasSnapshot = true;
        }

        private function isReservedKey(string $key): bool
        {
            return in_array($key, self::RESERVED_KEYS, true);
        }

        /** @param array<string, mixed> $payload */
        private function captureMeta(array $payload): void
        {
            if (isset($payload['storage']) && is_array($payload['storage'])) {
                $this->meta['storage'] = array_merge($this->meta['storage'], $payload['storage']);

                if (isset($payload['storage']['usim']) && is_string($payload['storage']['usim']) && $payload['storage']['usim'] !== '') {
                    $decoded = $this->decodeUsimToStore($payload['storage']['usim']);
                    if (is_array($decoded)) {
                        $this->store = $decoded;
                    }
                }
            }

            if (isset($payload['toast']) && is_array($payload['toast'])) {
                $this->meta['toasts'][] = $payload['toast'];
            }

            if (array_key_exists('redirect', $payload)) {
                $this->meta['redirect'] = $payload['redirect'];
            }

            if (array_key_exists('abort', $payload)) {
                $this->meta['abort'] = $payload['abort'];
            }

            if (array_key_exists('action', $payload)) {
                $this->meta['action'] = $payload['action'];
            }

            if (array_key_exists('update_modal', $payload)) {
                $this->meta['update_modal'] = $payload['update_modal'];
            }

            if (isset($payload['clear_uploaders']) && is_array($payload['clear_uploaders'])) {
                $this->meta['clear_uploaders'] = $payload['clear_uploaders'];
            }

            if (array_key_exists('set_uploader_existing_file', $payload)) {
                $this->meta['set_uploader_existing_file'] = $payload['set_uploader_existing_file'];
            }
        }

        /** @param array<string, mixed> $component */
        private function setComponent(string $jsonKey, array $component): void
        {
            $this->componentsByKey[$jsonKey] = $component;
            $this->syncInternalId($jsonKey, $component);
        }

        /** @param array<string, mixed> $component */
        private function syncInternalId(string $jsonKey, array $component): void
        {
            if (!isset($component['_id']) || !is_numeric($component['_id'])) {
                return;
            }

            $this->keyByInternalId[(int) $component['_id']] = $jsonKey;
        }

        /**
         * Merge a partial delta into the current component snapshot.
         *
         * @param array<string, mixed> $current
         * @param array<string, mixed> $delta
         * @return array<string, mixed>
         */
        private function mergeComponent(array $current, array $delta): array
        {
            foreach ($delta as $field => $value) {
                $current[$field] = $value;
            }

            return $current;
        }

        /** @param array<string, mixed> $component */
        private function looksLikeComponent(array $component): bool
        {
            return isset($component['type']) || isset($component['_id']) || isset($component['parent']);
        }

        /** @return array<string, mixed>|null */
        private function decodeUsimToStore(string $encrypted): ?array
        {
            try {
                $decrypted = decrypt($encrypted);
                $decoded = json_decode($decrypted, true);

                if (!is_array($decoded)) {
                    $this->issues[] = [
                        'type' => 'invalid_usim_json',
                        'message' => 'Decrypted usim is not a JSON object.',
                    ];
                    return null;
                }

                return $decoded;
            } catch (Throwable $e) {
                $this->issues[] = [
                    'type' => 'invalid_usim_payload',
                    'message' => 'Failed to decrypt usim payload: ' . $e->getMessage(),
                ];

                return null;
            }
        }

        private function encodeStoreToUsim(): string
        {
            return encrypt((string) json_encode($this->store));
        }
    }
}

if (!class_exists('UiScenario')) {
    final class UiScenario
    {
        private TestCase $test;

        private UiMemoryRenderer $memory;

        private string $clientId;

        private string $screenClass;

        /** @var array<string, mixed> */
        private array $screenQuery;

        private static bool $persistentCacheConfigured = false;

        private ?TestResponse $lastResponse = null;

        private function __construct(TestCase $test)
        {
            $this->test = $test;
            $this->memory = new UiMemoryRenderer();
            $this->clientId = (string) Str::uuid();
            $this->screenClass = '';
            $this->screenQuery = [];
        }

        public static function boot(TestCase $test, string $screenClass, array $query = []): self
        {
            $scenario = new self($test);
            $scenario->configurePersistentCacheForUiState();
            $scenario->screenClass = $screenClass;
            $scenario->screenQuery = array_filter(
                $query,
                static fn ($key): bool => (string) $key !== 'reset',
                ARRAY_FILTER_USE_KEY
            );

            $scenario->resetUiCollector();
            $response = $scenario
                ->testWithClientCookie()
                ->getJson(screenApiUrl($screenClass, $query));
            $response->assertOk();

            $scenario->ingest($response->json(), 'initial');

            return $scenario;
        }

        public function ingest(array $payload, string $mode = 'auto'): self
        {
            $this->memory->ingest($payload, $mode);
            return $this;
        }

        public function withStore(array $store): self
        {
            $this->memory->mergeStore($store);
            return $this;
        }

        public function setStore(string $key, mixed $value): self
        {
            $this->memory->setStoreValue($key, $value);
            return $this;
        }

        public function store(string $key, mixed $default = null): mixed
        {
            return $this->memory->getStoreValue($key, $default);
        }

        /** @return array<string, mixed> */
        public function allStore(): array
        {
            return $this->memory->store();
        }

        public function component(string $name): UiComponentRef
        {
            if ($this->memory->componentByName($name) === null) {
                throw new RuntimeException("Component not found by name: {$name}");
            }

            return new UiComponentRef($this, $name);
        }

        public function click(string $componentName, array $parameters = []): TestResponse
        {
            $component = $this->mustGetComponent($componentName);
            $action = $component['action'] ?? null;

            if (!is_string($action) || $action === '') {
                throw new RuntimeException("Component '{$componentName}' has no action configured.");
            }

            $componentId = $component['_id'] ?? null;
            if (!is_numeric($componentId)) {
                throw new RuntimeException("Component '{$componentName}' does not have a numeric _id.");
            }

            $payload = [
                'component_id' => (int) $componentId,
                'event' => 'click',
                'action' => $action,
                'parameters' => $parameters,
            ];

            $headers = [];
            $usimStorage = $this->memory->usimStorage();
            if ($usimStorage !== '') {
                $headers['X-USIM-Storage'] = $usimStorage;
                // Middleware supports body fallback for tests/environments where custom headers are not propagated.
                $payload['usim'] = $usimStorage;
            }

            $this->resetUiCollector();
            $response = $this
                ->testWithClientCookie()
                ->postJson('/api/ui-event', $payload, $headers);

            $this->lastResponse = $response;

            if ($response->isOk()) {
                $this->ingest($response->json(), 'auto');

                // Some backend flows persist state but emit no component delta for the triggering control.
                // In that case, refresh current screen snapshot to keep the in-memory model browser-like.
                $responseJson = $response->json();
                if (!is_array($responseJson) || !array_key_exists((string) $componentId, $responseJson)) {
                    $this->syncFromServer();
                }
            }

            return $response;
        }

        /** @return array<string, mixed> */
        public function componentData(string $name): array
        {
            return $this->mustGetComponent($name);
        }

        public function assertNoIssues(): self
        {
            expect($this->memory->issues())->toBe([]);
            return $this;
        }

        /** @return array<int, array<string, mixed>> */
        public function issues(): array
        {
            return $this->memory->issues();
        }

        private function mustGetComponent(string $name): array
        {
            $component = $this->memory->componentByName($name);

            if ($component === null) {
                throw new RuntimeException("Component not found by name: {$name}");
            }

            return $component;
        }

        private function testWithClientCookie(): TestCase
        {
            return $this->test->withCookie(UIStateManager::CLIENT_ID_COOKIE, encrypt($this->clientId));
        }

        private function resetUiCollector(): void
        {
            app()->instance(UIChangesCollector::class, new UIChangesCollector());
        }

        private function syncFromServer(): void
        {
            if ($this->screenClass === '') {
                return;
            }

            $this->resetUiCollector();
            $response = $this
                ->testWithClientCookie()
                ->getJson(screenApiUrl($this->screenClass, $this->screenQuery));

            if ($response->isOk()) {
                $this->ingest($response->json(), 'initial');
            }
        }

        private function configurePersistentCacheForUiState(): void
        {
            if (self::$persistentCacheConfigured) {
                return;
            }

            // UI diff flow depends on previous UI snapshot being available across requests.
            // In tests, CACHE_STORE=array is request-local; force a persistent driver for browser-like behavior.
            config(['cache.default' => 'file']);
            Cache::setDefaultDriver('file');
            self::$persistentCacheConfigured = true;
        }
    }
}

if (!class_exists('UiComponentRef')) {
    final class UiComponentRef
    {
        private UiScenario $scenario;

        private string $name;

        public function __construct(UiScenario $scenario, string $name)
        {
            $this->scenario = $scenario;
            $this->name = $name;
        }

        public function click(array $parameters = []): self
        {
            $this->scenario->click($this->name, $parameters)->assertOk();
            return $this;
        }

        public function expect(string $field)
        {
            $component = $this->scenario->componentData($this->name);
            return expect($component[$field] ?? null);
        }

        /** @return array<string, mixed> */
        public function data(): array
        {
            return $this->scenario->componentData($this->name);
        }
    }
}

if (!function_exists('uiScenario')) {
    function uiScenario(TestCase $test, string $screenClass, array $query = []): UiScenario
    {
        return UiScenario::boot($test, $screenClass, $query);
    }
}
