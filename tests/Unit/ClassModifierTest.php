<?php

use Idei\Usim\Support\CodeModifier\ClassModifier;
use PhpParser\ParserFactory;

function removeDirectoryRecursively(string $dir): void
{
    if (! is_dir($dir)) {
        return;
    }

    $items = scandir($dir);
    if ($items === false) {
        return;
    }

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $dir.DIRECTORY_SEPARATOR.$item;
        if (is_dir($path)) {
            removeDirectoryRecursively($path);
        } else {
            @unlink($path);
        }
    }

    @rmdir($dir);
}

function assertValidPhp(string $code): void
{
    $parser = (new ParserFactory)->createForNewestSupportedVersion();
    $ast = $parser->parse($code);

    expect($ast)->toBeArray();
}

describe('ClassModifier', function () {
    beforeEach(function () {
        $this->tempDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'usim_class_modifier_'.bin2hex(random_bytes(8));
        mkdir($this->tempDir, 0777, true);
    });

    afterEach(function () {
        removeDirectoryRecursively($this->tempDir);
    });

    $writeFixture = function (string $filename, string $content): string {
        $path = $this->tempDir.DIRECTORY_SEPARATOR.$filename;
        file_put_contents($path, $content);

        return $path;
    };

    describe('addTraitToClass', function () use ($writeFixture) {
        it('does nothing when target file does not exist', function () {
            $missing = $this->tempDir.DIRECTORY_SEPARATOR.'Missing.php';

            ClassModifier::addTraitToClass($missing, 'Sample', 'App\\Traits\\Auditable');

            expect(file_exists($missing))->toBeFalse();
        });

        it('adds trait import and trait use inside a namespaced class and is idempotent', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'NamespacedModel.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use ExistingTrait;
}
PHP);

            ClassModifier::addTraitToClass($file, 'User', 'Spatie\\Permission\\Traits\\HasRoles');
            $firstPass = file_get_contents($file);

            assertValidPhp($firstPass);
            expect($firstPass)
                ->toContain('use Spatie\\Permission\\Traits\\HasRoles;')
                ->toContain('use Illuminate\\Database\\Eloquent\\Model;')
                ->toContain('use HasRoles;')
                ->toContain('use ExistingTrait;');

            // Idempotency: running twice must not duplicate import or trait usage
            ClassModifier::addTraitToClass($file, 'User', 'Spatie\\Permission\\Traits\\HasRoles');
            $secondPass = file_get_contents($file);

            expect($secondPass)->toBe($firstPass)
                ->and(substr_count($secondPass, 'use Spatie\\Permission\\Traits\\HasRoles;'))->toBe(1)
                ->and(substr_count($secondPass, 'use HasRoles;'))->toBe(1);
        });

        it('adds root import and trait use when file has no namespace and is idempotent', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'GlobalClass.php', <<<'PHP'
<?php

use App\Support\OtherDep;

class GlobalModel
{
    public string $name = 'test';
}
PHP);

            ClassModifier::addTraitToClass($file, 'GlobalModel', 'App\\Traits\\Trackable');
            $firstPass = file_get_contents($file);

            assertValidPhp($firstPass);
            expect($firstPass)
                ->toContain('use App\\Traits\\Trackable;')
                ->toContain('use App\\Support\\OtherDep;')
                ->toContain('use Trackable;');

            ClassModifier::addTraitToClass($file, 'GlobalModel', 'App\\Traits\\Trackable');
            $secondPass = file_get_contents($file);

            expect($secondPass)->toBe($firstPass)
                ->and(substr_count($secondPass, 'use App\\Traits\\Trackable;'))->toBe(1)
                ->and(substr_count($secondPass, 'use Trackable;'))->toBe(1);
        });

        it('does not add trait to non-matching or anonymous classes', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'OtherClass.php', <<<'PHP'
<?php

namespace App\Models;

class Account
{
    public function makeHelper(): object
    {
        return new class {};
    }
}
PHP);

            ClassModifier::addTraitToClass($file, 'User', 'App\\Traits\\Auditable');
            $result = file_get_contents($file);

            assertValidPhp($result);
            expect($result)->not->toContain('use Auditable;');
        });
    });

    describe('addInterface', function () use ($writeFixture) {
        it('does nothing when target file does not exist', function () {
            $missing = $this->tempDir.DIRECTORY_SEPARATOR.'MissingInterface.php';

            ClassModifier::addInterface($missing, 'User', 'App\\Contracts\\AuditableInterface');

            expect(file_exists($missing))->toBeFalse();
        });

        it('adds interface import and implements clause in a namespaced class and is idempotent', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'UserInterface.php', <<<'PHP'
<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;

class User implements MustVerifyEmail
{
}
PHP);

            ClassModifier::addInterface($file, 'User', 'Idei\\Usim\\Contracts\\UsimUserInterface');
            $firstPass = file_get_contents($file);

            assertValidPhp($firstPass);
            expect($firstPass)
                ->toContain('use Idei\\Usim\\Contracts\\UsimUserInterface;')
                ->toContain('implements MustVerifyEmail, UsimUserInterface');

            ClassModifier::addInterface($file, 'User', 'Idei\\Usim\\Contracts\\UsimUserInterface');
            $secondPass = file_get_contents($file);

            expect($secondPass)->toBe($firstPass)
                ->and(substr_count($secondPass, 'use Idei\\Usim\\Contracts\\UsimUserInterface;'))->toBe(1)
                ->and(substr_count($secondPass, 'UsimUserInterface'))->toBe(2); // 1 in use, 1 in implements
        });

        it('does not duplicate interface when already implemented by FQN', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'FqnImplemented.php', <<<'PHP'
<?php

namespace App\Models;

class User implements \Idei\Usim\Contracts\UsimUserInterface
{
}
PHP);

            ClassModifier::addInterface($file, 'User', 'Idei\\Usim\\Contracts\\UsimUserInterface');
            $result = file_get_contents($file);

            assertValidPhp($result);
            expect($result)->not->toContain(', UsimUserInterface');
        });

        it('adds root import and implements clause when file has no namespace and is idempotent', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'NoNamespaceInterface.php', <<<'PHP'
<?php

use App\Contracts\ExistingContract;

class PlainEntity
{
}
PHP);

            ClassModifier::addInterface($file, 'PlainEntity', 'App\\Contracts\\Exportable');
            $firstPass = file_get_contents($file);

            assertValidPhp($firstPass);
            expect($firstPass)
                ->toContain('use App\\Contracts\\Exportable;')
                ->toContain('class PlainEntity implements Exportable');

            ClassModifier::addInterface($file, 'PlainEntity', 'App\\Contracts\\Exportable');
            $secondPass = file_get_contents($file);

            expect($secondPass)->toBe($firstPass)
                ->and(substr_count($secondPass, 'use App\\Contracts\\Exportable;'))->toBe(1);
        });

        it('does not add interface to non-matching or anonymous classes', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'DifferentClass.php', <<<'PHP'
<?php

namespace App\Models;

class Device
{
    public function anon(): object
    {
        return new class {};
    }
}
PHP);

            ClassModifier::addInterface($file, 'User', 'App\\Contracts\\Exportable');
            $result = file_get_contents($file);

            assertValidPhp($result);
            expect($result)->not->toContain('implements Exportable');
        });
    });

    describe('addImport', function () use ($writeFixture) {
        it('does nothing when target file does not exist', function () {
            $missing = $this->tempDir.DIRECTORY_SEPARATOR.'MissingImport.php';

            ClassModifier::addImport($missing, 'App\\Support\\Helper');

            expect(file_exists($missing))->toBeFalse();
        });

        it('inserts import into namespace without existing imports and after last existing import', function () use ($writeFixture) {
            // Case 1: Namespace with no prior imports
            $noImportsFile = $writeFixture->call($this, 'NsNoImports.php', <<<'PHP'
<?php

namespace App\Services;

class ReportService
{
}
PHP);

            ClassModifier::addImport($noImportsFile, 'App\\Models\\User');
            $first = file_get_contents($noImportsFile);

            assertValidPhp($first);
            expect($first)->toContain("namespace App\\Services;\n\nuse App\\Models\\User;\nclass ReportService");

            // Case 2: Namespace with existing imports -> inserts right after the last use statement
            ClassModifier::addImport($noImportsFile, 'App\\Models\\Device');
            $second = file_get_contents($noImportsFile);

            assertValidPhp($second);
            expect(strpos($second, 'use App\\Models\\User;'))
                ->toBeLessThan(strpos($second, 'use App\\Models\\Device;'))
                ->and(strpos($second, 'use App\\Models\\Device;'))
                ->toBeLessThan(strpos($second, 'class ReportService'));

            // Idempotency
            ClassModifier::addImport($noImportsFile, 'App\\Models\\Device');
            $third = file_get_contents($noImportsFile);

            expect($third)->toBe($second)
                ->and(substr_count($third, 'use App\\Models\\Device;'))->toBe(1);
        });

        it('inserts root imports when file has no namespace with and without prior imports and is idempotent', function () use ($writeFixture) {
            $rootFile = $writeFixture->call($this, 'RootImports.php', <<<'PHP'
<?php

class GlobalService
{
}
PHP);

            // First import when none exist
            ClassModifier::addImport($rootFile, 'App\\Support\\FirstHelper');
            $first = file_get_contents($rootFile);

            assertValidPhp($first);
            expect($first)->toContain('use App\\Support\\FirstHelper;');

            // Second import appended after existing root use
            ClassModifier::addImport($rootFile, 'App\\Support\\SecondHelper');
            $second = file_get_contents($rootFile);

            assertValidPhp($second);
            expect(strpos($second, 'use App\\Support\\FirstHelper;'))
                ->toBeLessThan(strpos($second, 'use App\\Support\\SecondHelper;'))
                ->and(strpos($second, 'use App\\Support\\SecondHelper;'))
                ->toBeLessThan(strpos($second, 'class GlobalService'));

            // Idempotency
            ClassModifier::addImport($rootFile, 'App\\Support\\SecondHelper');
            $third = file_get_contents($rootFile);

            expect($third)->toBe($second)
                ->and(substr_count($third, 'use App\\Support\\SecondHelper;'))->toBe(1);
        });
    });

    describe('addPropertyArrayValue', function () use ($writeFixture) {
        it('does nothing when target file does not exist', function () {
            $missing = $this->tempDir.DIRECTORY_SEPARATOR.'MissingProp.php';

            ClassModifier::addPropertyArrayValue($missing, 'User', 'fillable', 'phone');

            expect(file_exists($missing))->toBeFalse();
        });

        it('appends string values to empty, inline, and multiline array properties and is idempotent', function (string $propertyDeclaration) use ($writeFixture) {
            $file = $writeFixture->call($this, 'PropVariants.php', <<<PHP
<?php

namespace App\Models;

class User
{
    {$propertyDeclaration}
}
PHP);

            ClassModifier::addPropertyArrayValue($file, 'User', 'fillable', 'terms_accepted_at');
            $firstPass = file_get_contents($file);

            assertValidPhp($firstPass);
            expect($firstPass)->toContain("'terms_accepted_at'");

            // Idempotency
            ClassModifier::addPropertyArrayValue($file, 'User', 'fillable', 'terms_accepted_at');
            $secondPass = file_get_contents($file);

            expect($secondPass)->toBe($firstPass)
                ->and(substr_count($secondPass, "'terms_accepted_at'"))->toBe(1);
        })->with([
            'empty array' => ['protected $fillable = [];'],
            'inline array' => ["protected \$fillable = ['name', 'email'];"],
            'multiline array with non-string entry' => ["protected \$fillable = [\n        'name',\n        42,\n    ];"],
        ]);

        it('leaves non-array properties, unrelated properties, and non-matching classes untouched', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'PropEdges.php', <<<'PHP'
<?php

namespace App\Models;

class User
{
    protected $hidden = ['password'];
    protected $fillable = null;
    public function helper(): object
    {
        return new class {
            protected $fillable = [];
        };
    }
}

class OtherModel
{
    protected $fillable = ['title'];
}
PHP);

            ClassModifier::addPropertyArrayValue($file, 'User', 'fillable', 'phone');
            $result = file_get_contents($file);

            assertValidPhp($result);
            expect($result)->not->toContain("'phone'")
                ->and($result)->toContain("protected \$hidden = ['password'];")
                ->and($result)->toContain('protected $fillable = null;');
        });
    });

    describe('addCast', function () use ($writeFixture) {
        it('does nothing when target file does not exist', function () {
            $missing = $this->tempDir.DIRECTORY_SEPARATOR.'MissingCast.php';

            ClassModifier::addCast($missing, 'User', 'terms_accepted_at', 'datetime');

            expect(file_exists($missing))->toBeFalse();
        });

        it('adds casts to a casts() method across empty and multiline formats and is idempotent', function (string $returnExpr) use ($writeFixture) {
            $file = $writeFixture->call($this, 'CastsMethod.php', <<<PHP
<?php

namespace App\Models;

class User
{
    protected function casts(): array
    {
        \$ignored = true;
        return {$returnExpr};
    }
}
PHP);

            ClassModifier::addCast($file, 'User', 'terms_accepted_at', 'datetime');
            $firstPass = file_get_contents($file);

            assertValidPhp($firstPass);
            expect($firstPass)->toContain("'terms_accepted_at' => 'datetime'");

            // Idempotency (same key must not be duplicated)
            ClassModifier::addCast($file, 'User', 'terms_accepted_at', 'datetime');
            $secondPass = file_get_contents($file);

            expect($secondPass)->toBe($firstPass)
                ->and(substr_count($secondPass, "'terms_accepted_at'"))->toBe(1);
        })->with([
            'empty return array' => ['[]'],
            'multiline return array with existing and non-string keys' => ["[\n            'email_verified_at' => 'datetime',\n            0 => 'boolean',\n        ]"],
        ]);

        it('adds casts to a $casts property across empty and multiline formats and is idempotent', function (string $propertyDecl) use ($writeFixture) {
            $file = $writeFixture->call($this, 'CastsProperty.php', <<<PHP
<?php

namespace App\Models;

class User
{
    {$propertyDecl}
}
PHP);

            ClassModifier::addCast($file, 'User', 'is_active', 'boolean');
            $firstPass = file_get_contents($file);

            assertValidPhp($firstPass);
            expect($firstPass)->toContain("'is_active' => 'boolean'");

            // Idempotency
            ClassModifier::addCast($file, 'User', 'is_active', 'boolean');
            $secondPass = file_get_contents($file);

            expect($secondPass)->toBe($firstPass)
                ->and(substr_count($secondPass, "'is_active'"))->toBe(1);
        })->with([
            'empty property array' => ['protected $casts = [];'],
            'multiline property array with existing and non-string keys' => ["protected \$casts = [\n        'email_verified_at' => 'datetime',\n        1 => 'string',\n    ];"],
        ]);

        it('handles abstract casts() method, non-array $casts property, and non-matching classes safely', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'CastsEdgeCases.php', <<<'PHP'
<?php

namespace App\Models;

abstract class BaseUser
{
    abstract protected function casts(): array;

    protected $casts = null;

    public function anon(): object
    {
        return new class {
            protected $casts = [];
        };
    }
}

class DifferentModel
{
    protected $casts = ['existing' => 'string'];
}
PHP);

            ClassModifier::addCast($file, 'BaseUser', 'terms_accepted_at', 'datetime');
            $result = file_get_contents($file);

            assertValidPhp($result);
            expect($result)->not->toContain("'terms_accepted_at'");
        });
    });

    describe('addMethodToClass', function () use ($writeFixture) {
        it('does nothing when target file does not exist', function () {
            $missing = $this->tempDir.DIRECTORY_SEPARATOR.'MissingMethod.php';

            ClassModifier::addMethodToClass($missing, 'User', 'public function ping(): string { return "pong"; }');

            expect(file_exists($missing))->toBeFalse();
        });

        it('does not modify file when methodCode contains no class methods', function (string $nonMethodCode) use ($writeFixture) {
            $initial = <<<'PHP'
<?php

namespace App\Models;

class User
{
    public string $name = 'Alice';
}
PHP;
            $file = $writeFixture->call($this, 'NoMethodsInput.php', $initial);

            ClassModifier::addMethodToClass($file, 'User', $nonMethodCode);

            expect(file_get_contents($file))->toBe($initial);
        })->with([
            'empty string' => [''],
            'property only' => ['public string $extra = "nope";'],
            'constant only' => ['public const FOO = 1;'],
        ]);

        it('adds single and multiple methods to a class and is idempotent', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'UserMethods.php', <<<'PHP'
<?php

namespace App\Models;

class User
{
    public function existingMethod(): bool
    {
        return true;
    }
}
PHP);

            $methodsCode = <<<'PHP'
public function isRoot(): bool
{
    return false;
}

public function displayName(): string
{
    return 'User';
}
PHP;

            ClassModifier::addMethodToClass($file, 'User', $methodsCode);
            $firstPass = file_get_contents($file);

            assertValidPhp($firstPass);
            expect($firstPass)
                ->toContain('function existingMethod')
                ->toContain('function isRoot')
                ->toContain('function displayName');

            // Idempotency: adding the same methods again does not duplicate them
            ClassModifier::addMethodToClass($file, 'User', $methodsCode);
            $secondPass = file_get_contents($file);

            expect($secondPass)->toBe($firstPass)
                ->and(substr_count($secondPass, 'function isRoot'))->toBe(1)
                ->and(substr_count($secondPass, 'function displayName'))->toBe(1);
        });

        it('adds method to a class without namespace and skips non-matching or anonymous classes', function () use ($writeFixture) {
            $file = $writeFixture->call($this, 'GlobalMethods.php', <<<'PHP'
<?php

class TargetClass
{
    public function makeAnon(): object
    {
        return new class {};
    }
}

class UntouchedClass
{
}
PHP);

            ClassModifier::addMethodToClass($file, 'TargetClass', 'public function greet(): string { return "hi"; }');
            $result = file_get_contents($file);

            assertValidPhp($result);
            expect(substr_count($result, 'function greet'))->toBe(1);

            // Calling with a class name that does not exist in the file leaves TargetClass & UntouchedClass without the new method
            ClassModifier::addMethodToClass($file, 'NonExistentClass', 'public function extra(): void {}');
            $afterMismatch = file_get_contents($file);

            assertValidPhp($afterMismatch);
            expect($afterMismatch)->not->toContain('function extra');
        });
    });
});
