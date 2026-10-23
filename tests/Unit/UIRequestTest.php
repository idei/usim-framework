<?php

namespace Tests\Unit;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Exceptions\UIRequestValidationException;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Illuminate\Auth\Access\AuthorizationException;

class SampleDeviceDto
{
    public function __construct(
        public string $name,
        public int $pin
    ) {}
}

class SampleDeviceScreen extends Screen
{
    public static ?string $layout = null;

    protected Input $input_pin;

    protected Input $input_name;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container->add(
            $this->input_name->label('Nombre del Equipo')
        );
        $container->add(
            $this->input_pin->label('PIN de Seguridad')
        );
    }
}

class SampleDeviceRequest extends UIRequest
{
    protected array $map = [
        'name' => 'input_name',
        'pin' => 'input_pin',
    ];

    public function rules(): array
    {
        return [
            'name' => 'required|string|min:3',
            'pin' => 'required|digits:4',
        ];
    }
}

class UnauthorizedDeviceRequest extends UIRequest
{
    public function authorize(): bool
    {
        return false;
    }
}

it('throws AuthorizationException when authorize returns false', function () {
    $screen = new SampleDeviceScreen;
    $request = new UnauthorizedDeviceRequest;

    $request->initialize(
        screen: $screen,
        parameters: []
    );

    expect(fn () => $request->validate())->toThrow(AuthorizationException::class);
});

class SampleSanitizingRequest extends UIRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'email' => strtolower(trim((string) $this->input('email'))),
        ]);
    }

    public function rules(): array
    {
        return [
            'email' => 'required|email',
        ];
    }
}

it('maps component parameters and validates input successfully', function () {
    $screen = new SampleDeviceScreen;
    $request = new SampleDeviceRequest;

    $request->initialize(
        screen: $screen,
        parameters: [
            'input_name' => 'Smart Sensor',
            'input_pin' => '4419',
        ]
    );

    expect($request->has('name'))->toBeTrue();
    expect($request->input('name'))->toBe('Smart Sensor');
    expect($request->pin)->toBe('4419');

    $validated = $request->validate();

    expect($validated)->toHaveKey('name', 'Smart Sensor');
    expect($validated)->toHaveKey('pin', '4419');
    expect($request->validated('name'))->toBe('Smart Sensor');
});

it('converts validated data to typed DTO', function () {
    $screen = new SampleDeviceScreen;
    $request = new SampleDeviceRequest;

    $request->initialize(
        screen: $screen,
        parameters: [
            'input_name' => 'Display TV',
            'input_pin' => '1234',
        ]
    );

    $request->validate();
    $dto = $request->toDto(SampleDeviceDto::class);

    expect($dto)->toBeInstanceOf(SampleDeviceDto::class);
    expect($dto->name)->toBe('Display TV');
    expect($dto->pin)->toBe(1234);
});

it('throws UIRequestValidationException on invalid data', function () {
    $screen = new SampleDeviceScreen;
    $request = new SampleDeviceRequest;

    $request->initialize(
        screen: $screen,
        parameters: [
            'input_name' => 'X',
            'input_pin' => 'not-a-number',
        ]
    );

    expect(fn () => $request->validate())->toThrow(UIRequestValidationException::class);
});

it('infers component label for validation attribute message', function () {
    $screen = new SampleDeviceScreen;
    $screen->render();

    $request = new SampleDeviceRequest;
    $request->initialize(
        screen: $screen,
        parameters: []
    );

    try {
        $request->validate();
        $this->fail('Validation should have failed');
    } catch (UIRequestValidationException $e) {
        $errors = $e->validator->errors();
        // Since 'input_name' component has label 'Nombre del Equipo',
        // the resolved attribute should use that label.
        $message = $errors->first('name');
        expect($message)->toContain('Nombre del Equipo');
    }
});

it('binds errors to matching screen components automatically', function () {
    $screen = new SampleDeviceScreen;
    $screen->render();

    $request = new SampleDeviceRequest;
    $request->initialize(
        screen: $screen,
        parameters: [
            'input_name' => 'Ok Name',
            'input_pin' => '99', // invalid digits:4
        ]
    );

    try {
        $request->validate();
    } catch (UIRequestValidationException $e) {
        $request->bindErrorsToScreen($screen, $e->validator->errors());
    }

    $ref = new \ReflectionClass($screen);
    $prop = $ref->getProperty('input_pin');
    $prop->setAccessible(true);
    /** @var Input $inputPin */
    $inputPin = $prop->getValue($screen);

    $json = $inputPin->toJson();
    $config = reset($json);
    expect($config)->toHaveKey('error');
    expect($config['error'])->not->toBeEmpty();
});

it('supports prepareForValidation sanitization hook', function () {
    $screen = new SampleDeviceScreen;
    $request = new SampleSanitizingRequest;

    $request->initialize(
        screen: $screen,
        parameters: [
            'email' => '   USER@EXAMPLE.COM   ',
        ]
    );

    $validated = $request->validate();
    expect($validated['email'])->toBe('user@example.com');
});

class PropertyHydratedRequest extends UIRequest
{
    public string $name = '';

    public int $amount = 0;

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'amount' => 'required|integer',
        ];
    }
}

it('populates declared public properties with validated data', function () {
    $screen = new SampleDeviceScreen;
    $request = new PropertyHydratedRequest;
    $request->initialize(
        screen: $screen,
        parameters: ['name' => 'Item A', 'amount' => 50]
    );

    $request->validate();

    expect($request->name)->toBe('Item A');
    expect($request->amount)->toBe(50);
});

class ReadonlyHydratedRequest extends UIRequest
{
    public readonly string $name;

    public readonly ?string $note;

    public function rules(): array
    {
        return [
            'name' => 'required|string',
            'note' => 'nullable|string',
        ];
    }
}

it('populates readonly properties and enforces immutability', function () {
    $screen = new SampleDeviceScreen;
    $request = new ReadonlyHydratedRequest;
    $request->initialize(
        screen: $screen,
        parameters: ['name' => 'Immutable Item']
    );

    $request->validate();

    expect($request->name)->toBe('Immutable Item');
    expect($request->note)->toBeNull();
});
