<?php

namespace Tests\Feature;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Idei\Usim\Support\UIIdGenerator;
use Idei\Usim\UI;
use Tests\TestCase;

class UIRequestFeatureSaveRequest extends UIRequest
{
    protected array $map = [
        'code' => 'input_code',
        'title' => 'input_title',
    ];

    public function rules(): array
    {
        return [
            'code' => 'required|digits:4',
            'title' => 'required|string|min:3',
        ];
    }
}

class UIRequestFeatureHeadlessRequest extends UIRequest
{
    protected bool $autoBindComponentErrors = false;

    public function rules(): array
    {
        return [
            'api_token' => 'required|min:8',
        ];
    }
}

class UIRequestFeatureQueryRequest extends UIRequest
{
    public function rules(): array
    {
        return [
            'filter_id' => 'required|integer',
        ];
    }
}

class UIRequestFeatureDeleteActionRequest extends UIRequest
{
    public function rules(): array
    {
        return [
            'post_id' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'post_id.required' => 'El ID del post es obligatorio.',
            'post_id.integer' => 'El ID del post debe ser un número entero.',
        ];
    }
}

class UIRequestFeatureScreen extends Screen
{
    public static ?string $layout = null;

    public bool $saveExecuted = false;
    public ?array $savedData = null;

    protected Input $input_code;
    protected Input $input_title;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(
            $this->input_code->label('Código')
        );
        $container->add(
            $this->input_title->label('Título')
        );
        $container->add(
            UI::button('btn_save')->action('submit_save')
        );
    }

    public function onSubmitSave(UIRequestFeatureSaveRequest $request): void
    {
        $this->saveExecuted = true;
        $this->savedData = $request->validated();
        $this->toast('Guardado con éxito', 'success');
    }

    public function onSubmitHeadless(UIRequestFeatureHeadlessRequest $request): void
    {
        $this->toast('Headless success', 'success');
    }

    public function onDeletePost(UIRequestFeatureDeleteActionRequest $request): void
    {
        $this->toast('Post eliminado: '.$request->validated('post_id'), 'success');
    }

    public function getLookup(UIRequestFeatureQueryRequest $request): array
    {
        return [
            'found_id' => (int) $request->filter_id,
            'result' => 'OK',
        ];
    }
}

it('executes screen action when POST /api/ui-event passes UIRequest validation', function () {
    /** @var TestCase $this */
    $screen = Screen::make(UIRequestFeatureScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(UIRequestFeatureScreen::class, 'btn_save');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'submit_save',
        'parameters' => [
            'input_code' => '9876',
            'input_title' => 'Test Item',
        ],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('success');
    expect($response->json('toast.message'))->toBe('Guardado con éxito');
});

it('auto-binds component errors and returns UI diff on POST /api/ui-event validation failure', function () {
    /** @var TestCase $this */
    $screen = Screen::make(UIRequestFeatureScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(UIRequestFeatureScreen::class, 'btn_save');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'submit_save',
        'parameters' => [
            'input_code' => 'abc', // invalid digits:4
            'input_title' => 'X',   // invalid min:3
        ],
    ]);

    // UIEventController returns 200 with component diff
    $response->assertOk();

    $inputId = UIIdGenerator::generateFromName(UIRequestFeatureScreen::class, 'input_code');
    $diff = $response->json();

    expect($diff)->toHaveKey((string) $inputId);
    expect($diff[(string) $inputId])->toHaveKey('error');
    expect($diff[(string) $inputId]['error'])->not->toBeEmpty();

    // Verify toast error notification was triggered
    expect($diff)->toHaveKey('toast');
    expect($diff['toast']['type'])->toBe('error');
});

it('returns 422 JSON when autoBindComponentErrors is false on POST /api/ui-event', function () {
    /** @var TestCase $this */
    $screen = Screen::make(UIRequestFeatureScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(UIRequestFeatureScreen::class, 'btn_save');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'submit_headless',
        'parameters' => [
            'api_token' => 'short', // invalid min:8
        ],
    ]);

    $response->assertStatus(422);
    expect($response->json('errors.api_token'))->toBeArray();
});

it('returns data when GET /api/ui-data passes UIRequest validation', function () {
    /** @var TestCase $this */
    $screen = Screen::make(UIRequestFeatureScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(UIRequestFeatureScreen::class, 'btn_save');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=lookup&parameters[filter_id]=42");

    $response->assertOk();
    expect($response->json())->toBe([
        'found_id' => 42,
        'result' => 'OK',
    ]);
});

it('returns HTTP 422 with structured JSON errors when GET /api/ui-data fails UIRequest validation', function () {
    /** @var TestCase $this */
    $screen = Screen::make(UIRequestFeatureScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(UIRequestFeatureScreen::class, 'btn_save');

    $response = $this->getJson("/api/ui-data?component_id={$btnId}&action=lookup&parameters[filter_id]=not-a-number");

    $response->assertStatus(422);
    expect($response->json())->toHaveKeys(['message', 'errors']);
    expect($response->json('errors.filter_id'))->toBeArray();
});

it('projects first unbound validation error in toast when action request has no matching visual component', function () {
    /** @var TestCase $this */
    $screen = Screen::make(UIRequestFeatureScreen::class);
    $screen->render();

    $btnId = UIIdGenerator::generateFromName(UIRequestFeatureScreen::class, 'btn_save');

    $response = $this->postJson('/api/ui-event', [
        'component_id' => $btnId,
        'event' => 'click',
        'action' => 'delete_post',
        'parameters' => [],
    ]);

    $response->assertOk();
    expect($response->json('toast.type'))->toBe('error');
    expect($response->json('toast.message'))->toBe('El ID del post es obligatorio.');
});


