<?php

namespace Tests\Unit;

use App\Models\User;
use Idei\Usim\Components\Container;
use Idei\Usim\Http\Requests\UIRequest;
use Idei\Usim\Screen;
use Idei\Usim\Support\MethodParameterResolver;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

enum ResolverTestStatusEnum: string
{
    case Active = 'active';
    case Pending = 'pending';
}

enum ResolverTestPriorityEnum: int
{
    case Low = 1;
    case High = 2;
}

class ResolverDummyRequest extends UIRequest
{
    public function rules(): array
    {
        return [];
    }
}

class ResolverTargetScreen extends Screen
{
    public static ?string $layout = null;

    public static function authorize(): bool
    {
        return true;
    }

    protected function buildBaseUI(Container $container, ...$params): void {}

    public function noParams(): void {}

    public function legacyParams(array $params): void {}

    public function typedScalars(int $user_id, float $amount, bool $is_active, string $name): void {}

    public function withDefaults(string $tab = 'general', ?int $limit = null): void {}

    public function withEnum(ResolverTestStatusEnum $status, ?ResolverTestPriorityEnum $priority = null): void {}

    public function withModel(User $user): void {}

    public function hybrid(ResolverDummyRequest $request, int $user_id): void {}

    public function camelCase(int $targetUserId): void {}
}

beforeEach(function () {
    $this->resolver = new MethodParameterResolver();
    $this->screen = Screen::make(ResolverTargetScreen::class);
});

it('resolves empty array for method with no parameters', function () {
    $method = new ReflectionMethod($this->screen, 'noParams');
    $args = $this->resolver->resolve($method, $this->screen);

    expect($args)->toBe([]);
});

it('resolves legacy array parameter correctly', function () {
    $method = new ReflectionMethod($this->screen, 'legacyParams');
    $payload = ['foo' => 'bar', 'count' => 10];
    $args = $this->resolver->resolve($method, $this->screen, parameters: $payload);

    expect($args)->toHaveCount(1)
        ->and($args[0])->toBe($payload);
});

it('hydrates and casts native scalar parameters', function () {
    $method = new ReflectionMethod($this->screen, 'typedScalars');
    $payload = [
        'user_id' => '42',
        'amount' => '19.99',
        'is_active' => 'true',
        'name' => 'Alice',
    ];

    $args = $this->resolver->resolve($method, $this->screen, parameters: $payload);

    expect($args)->toBe([42, 19.99, true, 'Alice']);
});

it('applies default and nullable values when parameters are omitted', function () {
    $method = new ReflectionMethod($this->screen, 'withDefaults');
    $args = $this->resolver->resolve($method, $this->screen, parameters: []);

    expect($args)->toBe(['general', null]);
});

it('resolves parameters from URL query string and incoming storage', function () {
    $method = new ReflectionMethod($this->screen, 'typedScalars');
    $args = $this->resolver->resolve(
        reflectionMethod: $method,
        screen: $this->screen,
        parameters: ['user_id' => 10],
        queryParams: ['amount' => 5.5, 'is_active' => false],
        incomingStorage: ['store_name' => 'Bob']
    );

    expect($args)->toBe([10, 5.5, false, 'Bob']);
});

it('hydrates string and int BackedEnums', function () {
    $method = new ReflectionMethod($this->screen, 'withEnum');
    $args = $this->resolver->resolve($method, $this->screen, parameters: [
        'status' => 'active',
        'priority' => '2', // string from HTTP coerced to int enum
    ]);

    expect($args[0])->toBe(ResolverTestStatusEnum::Active)
        ->and($args[1])->toBe(ResolverTestPriorityEnum::High);
});

it('throws ValidationException when enum value is invalid', function () {
    $method = new ReflectionMethod($this->screen, 'withEnum');
    $this->resolver->resolve($method, $this->screen, parameters: [
        'status' => 'invalid_status',
    ]);
})->throws(ValidationException::class);

it('hydrates Eloquent model by ID candidate', function () {
    /** @var User $user */
    $user = User::factory()->create();

    $method = new ReflectionMethod($this->screen, 'withModel');
    $args = $this->resolver->resolve($method, $this->screen, parameters: [
        'user_id' => $user->id,
    ]);

    expect($args[0])->toBeInstanceOf(User::class)
        ->and($args[0]->id)->toBe($user->id);
});

it('resolves camelCase and snake_case variations interchangeably', function () {
    $method = new ReflectionMethod($this->screen, 'camelCase');
    $args = $this->resolver->resolve($method, $this->screen, parameters: [
        'target_user_id' => 99,
    ]);

    expect($args)->toBe([99]);
});

it('resolves UIRequest alongside typed action parameters in hybrid signatures', function () {
    $method = new ReflectionMethod($this->screen, 'hybrid');
    $args = $this->resolver->resolve($method, $this->screen, parameters: [
        'user_id' => 77,
    ]);

    expect($args[0])->toBeInstanceOf(ResolverDummyRequest::class)
        ->and($args[1])->toBe(77);
});

it('throws ValidationException for missing required parameters', function () {
    $method = new ReflectionMethod($this->screen, 'typedScalars');
    $this->resolver->resolve($method, $this->screen, parameters: [
        'user_id' => 1,
        // amount, is_active, name missing
    ]);
})->throws(ValidationException::class);

it('throws ValidationException when integer parameter is not numeric', function () {
    $method = new ReflectionMethod($this->screen, 'typedScalars');
    $this->resolver->resolve($method, $this->screen, parameters: [
        'user_id' => 'not_a_number',
        'amount' => 10.0,
        'is_active' => true,
        'name' => 'Test',
    ]);
})->throws(ValidationException::class);

