<?php

namespace Tests\Support;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class LifecycleTestScreen extends Screen
{
    public const ROUTE_PATH = '/lifecycle-test';

    public static ?string $layout = null;

    protected string $store_user = 'initial_user';

    protected int $state_counter = 10;

    public bool $actionExecuted = false;

    /**
     * @var array<string, mixed>
     */
    public array $receivedActionParams = [];

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(
            UI::label('lbl_counter')
                ->text("Count: {$this->state_counter}")
        );

        $container->add(
            UI::button('btn_increment')
                ->action('increment')
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onIncrement(array $params = []): void
    {
        $this->actionExecuted = true;
        $this->receivedActionParams = $params;
        $step = $params['step'] ?? 1;
        $this->state_counter += is_numeric($step) ? (int) $step : 1;
    }

    /**
     * @return array<string, mixed>
     */
    public function getAgentContext(): array
    {
        return ['unit_test' => true];
    }
}
