<?php

use Idei\Usim\Services\Support\UIStateManager;
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

        private ?string $rawUsim = null;

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
            // Browser-like behavior: replay the exact encrypted payload returned by backend.
            if (is_string($this->rawUsim) && $this->rawUsim !== '') {
                return $this->rawUsim;
            }

            return '';
        }

        public function opaqueUsim(): string
        {
            return is_string($this->rawUsim) ? $this->rawUsim : '';
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
                $jsonKey = $this->resolveComponentKey((string) $key);

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

        private function resolveComponentKey(string $incomingKey): string
        {
            if (is_numeric($incomingKey)) {
                $mappedKey = $this->keyByInternalId[(int) $incomingKey] ?? null;
                if (is_string($mappedKey) && $mappedKey !== '') {
                    return $mappedKey;
                }
            }

            return $incomingKey;
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
                    $this->rawUsim = $payload['storage']['usim'];
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

    }
}

if (!class_exists('UiScenario')) {
    final class UiScenario
    {
        private const STORAGE_HEADER = 'header';
        private const STORAGE_BODY = 'body';
        private const STORAGE_NONE = 'none';

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

        public function opaqueUsim(): string
        {
            return $this->memory->opaqueUsim();
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

            return $this->sendUiEvent(
                componentId: (int) $componentId,
                event: 'click',
                action: $action,
                parameters: $parameters,
                storageMode: self::STORAGE_HEADER,
                syncComponentId: (int) $componentId
            );
        }

        public function input(string $componentName, string $action, array $parameters = []): TestResponse
        {
            $componentId = $this->mustGetComponentId($componentName);

            return $this->sendUiEvent(
                componentId: $componentId,
                event: 'input',
                action: $action,
                parameters: $parameters,
                storageMode: self::STORAGE_BODY,
                syncComponentId: $componentId
            );
        }

        public function change(
            string $componentName,
            string $action,
            array $parameters = [],
            bool $includeStorageHeader = true
        ): TestResponse {
            $componentId = $this->mustGetComponentId($componentName);

            return $this->sendUiEvent(
                componentId: $componentId,
                event: 'change',
                action: $action,
                parameters: $parameters,
                storageMode: $includeStorageHeader ? self::STORAGE_HEADER : self::STORAGE_NONE,
                syncComponentId: $componentId
            );
        }

        public function action(
            string $componentName,
            string $action,
            array $parameters = [],
            bool $includeStorageHeader = true
        ): TestResponse {
            $componentId = $this->mustGetComponentId($componentName);

            return $this->sendUiEvent(
                componentId: $componentId,
                event: 'action',
                action: $action,
                parameters: $parameters,
                storageMode: $includeStorageHeader ? self::STORAGE_HEADER : self::STORAGE_NONE,
                syncComponentId: $componentId
            );
        }

        public function timeout(int $callerServiceId, string $action, array $parameters = []): TestResponse
        {
            return $this->sendUiEvent(
                componentId: $callerServiceId,
                event: 'timeout',
                action: $action,
                parameters: $parameters,
                storageMode: self::STORAGE_HEADER,
                syncComponentId: null
            );
        }

        private function sendUiEvent(
            int $componentId,
            string $event,
            string $action,
            array $parameters,
            string $storageMode,
            ?int $syncComponentId
        ): TestResponse {

            $payload = [
                'component_id' => $componentId,
                'event' => $event,
                'action' => $action,
                'parameters' => $parameters,
            ];

            $headers = [];
            $usimStorage = $this->memory->usimStorage();
            if ($usimStorage !== '' && $storageMode === self::STORAGE_HEADER) {
                // In test client, encrypted header transport can be unreliable; use body fallback accepted by middleware.
                $payload['usim'] = $usimStorage;
            }

            if ($usimStorage !== '' && $storageMode === self::STORAGE_BODY) {
                // Input events in ui-renderer send storage in body field "storage".
                $payload['storage'] = $usimStorage;
            }

            $response = $this
                ->testWithClientCookie()
                ->postJson('/api/ui-event', $payload, $headers);

            $this->lastResponse = $response;

            if ($response->isOk()) {
                $this->ingest($response->json(), 'auto');

                // Some backend flows return only meta keys (e.g. storage) with no component deltas.
                // In that case, refresh current screen snapshot to keep the in-memory model browser-like.
                $responseJson = $response->json();
                if ($syncComponentId !== null && !$this->hasUiComponents($responseJson)) {
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

        private function mustGetComponentId(string $name): int
        {
            $component = $this->mustGetComponent($name);
            $componentId = $component['_id'] ?? null;

            if (!is_numeric($componentId)) {
                throw new RuntimeException("Component '{$name}' does not have a numeric _id.");
            }

            return (int) $componentId;
        }

        private function testWithClientCookie(): TestCase
        {
            return $this->test->withCookie(UIStateManager::CLIENT_ID_COOKIE, $this->clientId);
        }

        private function syncFromServer(): void
        {
            if ($this->screenClass === '') {
                return;
            }

            $headers = [];
            $usimStorage = $this->memory->usimStorage();
            if ($usimStorage !== '') {
                $headers['X-USIM-Storage'] = $usimStorage;
            }

            $response = $this
                ->testWithClientCookie()
                ->getJson(screenApiUrl($this->screenClass, $this->screenQuery), $headers);

            if ($response->isOk()) {
                $payload = $response->json();
                if ($this->hasUiComponents($payload)) {
                    $this->ingest($payload, 'initial');
                }
            }
        }

        private function hasUiComponents(mixed $payload): bool
        {
            if (!is_array($payload)) {
                return false;
            }

            foreach ($payload as $value) {
                if (!is_array($value)) {
                    continue;
                }

                if (isset($value['type']) && isset($value['_id'])) {
                    return true;
                }
            }

            return false;
        }

        private function configurePersistentCacheForUiState(): void
        {
            if (self::$persistentCacheConfigured) {
                return;
            }

            // UI diff flow depends on previous UI snapshot being available across requests.
            // In tests, CACHE_STORE/SESSION_DRIVER=array are request-local; force persistent drivers for browser-like behavior.
            config([
                'cache.default' => 'file',
                'session.driver' => 'file',
            ]);
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
