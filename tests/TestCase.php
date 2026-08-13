<?php

namespace Tests;

use Tests\Traits\UsimTestHelpers;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;
/**
 * @method TestResponse postJson(string $uri, array $data = [], array $headers = [], int $options = 0)
 */
abstract class TestCase extends BaseTestCase
{
    use UsimTestHelpers;
    use RefreshDatabase;
}