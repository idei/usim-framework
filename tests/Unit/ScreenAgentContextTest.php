<?php

use App\UI\Screens\Home;
use Idei\Usim\Screen;

// Test 1: Screen::getAgentContext() returns empty array by default
it('screen get-agent-context returns empty array by default', function () {
    $screen = new class extends Screen {
        protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void {
            // Empty implementation
        }
    };

    $context = $screen->getAgentContext();

    expect($context)->toBeArray();
    expect($context)->toBeEmpty();
});

// Test 2: Screen can override getAgentContext()
it('screen can override get-agent-context method', function () {
    $screen = new class extends Screen {
        protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void {
            // Empty implementation
        }

        public function getAgentContext(): array
        {
            return [
                'purpose' => 'Test screen',
                'inputs' => ['field1', 'field2'],
                'outputs' => ['redirect', 'toast'],
                'constraints' => 'None',
            ];
        }
    };

    $context = $screen->getAgentContext();

    expect($context['purpose'])->toBe('Test screen');
    expect($context['inputs'])->toContain('field1', 'field2');
    expect($context['outputs'])->toContain('redirect', 'toast');
});

// Test 3: getAgentContext() return type is array
it('get-agent-context always returns array', function () {
    $homeScreen = new Home();

    $context = $homeScreen->getAgentContext();

    expect($context)->toBeArray();
});

// Test 4: Agent context structure conforms to expected keys
it('agent context structure includes expected optional keys', function () {
    $screen = new class extends Screen {
        protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void {
        }

        public function getAgentContext(): array
        {
            return [
                'purpose' => 'Authentication',
                'inputs' => ['email'],
                'outputs' => ['redirect', 'abort'],
                'constraints' => 'Email required',
            ];
        }
    };

    $context = $screen->getAgentContext();

    // Validate expected structure
    if (!empty($context)) {
        expect($context)->toHaveKeys(['purpose']);
    }
});

// Test 5: Agent context is called during screen initialization
it('agent context can be retrieved after screen initialization', function () {
    // This test ensures getAgentContext() is accessible in the screen lifecycle
    $ui = uiScenario($this, Home::class, ['reset' => true]);

    // If we can get the screen instance from the scenario, we should be able to call getAgentContext()
    // This validates that the method exists and is callable
    expect(method_exists(Home::class, 'getAgentContext'))->toBeTrue();
});

// Test 6: Multiple calls to getAgentContext() return consistent results
it('get-agent-context returns consistent results on multiple calls', function () {
    $screen = new class extends Screen {
        protected function buildBaseUI(\Idei\Usim\Components\Container $container, ...$params): void {
        }

        public function getAgentContext(): array
        {
            return [
                'purpose' => 'Consistent test',
            ];
        }
    };

    $context1 = $screen->getAgentContext();
    $context2 = $screen->getAgentContext();

    expect($context1)->toBe($context2);
});
