<?php

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

if (!function_exists('screenApiUrl')) {
    function screenApiUrl(string $screenClass, array $query = []): string
    {
        $path = '/api/ui' . $screenClass::getRoutePath();

        if (empty($query)) {
            return $path;
        }

        return $path . '?' . http_build_query($query);
    }
}

if (!function_exists('getScreenJson')) {
    function getScreenJson(TestCase $test, string $screenClass, array $query = []): TestResponse
    {
        return $test->getJson(screenApiUrl($screenClass, $query));
    }
}

if (!function_exists('findComponentByName')) {
    function findComponentByName(array $payload, string $name): ?array
    {
        foreach ($payload as $component) {
            if (!is_array($component)) {
                continue;
            }

            if (($component['name'] ?? null) === $name) {
                return $component;
            }
        }

        return null;
    }
}

if (!function_exists('firstUiComponentFromPayload')) {
    function firstUiComponentFromPayload(array $payload): ?array
    {
        $reservedKeys = ['storage', 'action', 'redirect', 'toast', 'abort', 'modal', 'update_modal'];

        foreach ($payload as $key => $value) {
            if (in_array((string) $key, $reservedKeys, true)) {
                continue;
            }

            if (is_array($value) && isset($value['type'], $value['parent'], $value['_id'])) {
                return $value;
            }
        }

        return null;
    }
}

if (!function_exists('serviceRootComponentId')) {
    function serviceRootComponentId(array $payload): int
    {
        foreach ($payload as $id => $component) {
            if (!is_array($component)) {
                continue;
            }

            if (($component['type'] ?? null) === 'container' && is_numeric($id)) {
                return (int) $id;
            }
        }

        throw new RuntimeException('Service root component id not found in UI payload.');
    }
}
