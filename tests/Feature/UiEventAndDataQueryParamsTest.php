<?php

namespace Tests\Feature;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\UI;
use Tests\TestCase;

class QueryParamsActionRequest extends UIRequest
{
    public function rules(): array
    {
        return [];
    }
}

class QueryParamsMockScreen extends Screen
{
    public static ?string $layout = null;

    protected Button $submit_btn;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->submit_btn = UI::button('submit_btn')->label('Submit');
        $container->add($this->submit_btn);
    }

    public function onSubmitWithRequest(QueryParamsActionRequest $request): void
    {
        $this->submit_btn->label('Received: '.$request->query('department').'|'.$this->query('period'));
    }

    public function onSubmitPlain(array $params = []): void
    {
        $this->submit_btn->label('Query: '.$this->query('tag').'|'.($this->getQueryParams()['sort'] ?? 'none'));
    }

    public function getFilterData(QueryParamsActionRequest $request): array
    {
        return [
            'query_department' => $request->query('department'),
            'screen_query_dept' => $this->query('department'),
            'screen_query_params' => $this->getQueryParams(),
            'input_status' => $request->input('status'),
            'query_status' => $request->query('status'),
        ];
    }
}

it('passes URL query string to Screen and UIRequest during POST /api/ui-event', function () {
    /** @var TestCase $this */
    $screen = Screen::make(QueryParamsMockScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(QueryParamsMockScreen::class, 'submit_btn');

    $response = $this->postJson("/api/ui-event?department=finance&period=2026-Q1", [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'submit_with_request',
        'parameters' => [],
    ]);

    $response->assertOk();
    $changes = $response->json();
    expect($changes[(string) $btnId]['label'] ?? null)->toBe('Received: finance|2026-Q1');
});

it('passes query_params payload in JSON body to Screen during POST /api/ui-event', function () {
    /** @var TestCase $this */
    $screen = Screen::make(QueryParamsMockScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(QueryParamsMockScreen::class, 'submit_btn');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'submit_plain',
        'parameters' => [],
        'query_params' => [
            'tag' => 'tech',
            'sort' => 'desc',
        ],
    ]);

    $response->assertOk();
    $changes = $response->json();
    expect($changes[(string) $btnId]['label'] ?? null)->toBe('Query: tech|desc');
});

it('merges body query_params and URL query parameters with URL taking precedence during POST /api/ui-event', function () {
    /** @var TestCase $this */
    $screen = Screen::make(QueryParamsMockScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(QueryParamsMockScreen::class, 'submit_btn');

    $response = $this->postJson('/api/ui-event?department=accounting&period=2026-Q2', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'submit_with_request',
        'parameters' => [],
        'query_params' => [
            'department' => 'marketing', // overridden by URL
            'period' => '2025-Q4',      // overridden by URL
        ],
    ]);

    $response->assertOk();
    $changes = $response->json();
    expect($changes[(string) $btnId]['label'] ?? null)->toBe('Received: accounting|2026-Q2');
});

it('passes URL query string to Screen and UIRequest during GET /api/ui-data and strips internal routing keys', function () {
    /** @var TestCase $this */
    $screen = Screen::make(QueryParamsMockScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(QueryParamsMockScreen::class, 'submit_btn');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=filter_data&department=engineering&status=archived&parameters[status]=active");

    $response->assertOk();
    $data = $response->json();

    expect($data['query_department'])->toBe('engineering')
        ->and($data['screen_query_dept'])->toBe('engineering')
        ->and($data['input_status'])->toBe('active')
        ->and($data['query_status'])->toBe('archived')
        ->and(array_key_exists('component_id', $data['screen_query_params']))->toBeFalse()
        ->and(array_key_exists('action', $data['screen_query_params']))->toBeFalse()
        ->and(array_key_exists('parameters', $data['screen_query_params']))->toBeFalse();
});

