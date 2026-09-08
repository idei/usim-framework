<?php

namespace Idei\Usim\Support\CodeModifier;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;
use RuntimeException;

/**
 * Reads a `.stub` class file and derives a neutral {@see StubClassSpec} from it.
 *
 * This is the single source of truth extraction point: whatever the stub declares
 * (traits, interfaces, fillable fields, casts, methods) is what gets reported, so
 * callers never need to hardcode a duplicate list of "things the stub contains".
 */
class StubClassAnalyzer
{
    /** Methods that are merged through a dedicated ClassModifier call, never copied verbatim. */
    private const EXCLUDED_METHODS = ['__construct', 'casts'];

    public function analyze(string $stubPath, string $namespacePlaceholderValue = 'App\\Models'): StubClassSpec
    {
        if (!file_exists($stubPath)) {
            throw new RuntimeException("Stub file not found: {$stubPath}");
        }

        $code = file_get_contents($stubPath);
        if ($code === false) {
            throw new RuntimeException("Unable to read stub file: {$stubPath}");
        }

        $code = str_replace('{{ namespace }}', $namespacePlaceholderValue, $code);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            throw new RuntimeException("Unable to parse stub file: {$stubPath}");
        }

        $classNode = $this->findClassNode($ast);
        if ($classNode === null) {
            throw new RuntimeException("No class declaration found in stub file: {$stubPath}");
        }

        $imports = $this->extractImports($ast);
        $importMap = $this->buildImportMap($imports);

        return new StubClassSpec(
            imports: $imports,
            traits: $this->resolveNames($this->extractTraits($classNode), $importMap),
            interfaces: $this->resolveNames($this->extractInterfaces($classNode), $importMap),
            fillable: $this->extractArrayPropertyValues($classNode, 'fillable'),
            casts: $this->extractCasts($classNode),
            methods: $this->extractMethods($classNode),
        );
    }

    /** @param array<int, Node> $ast */
    private function findClassNode(array $ast): ?Class_
    {
        foreach ($ast as $node) {
            if ($node instanceof Class_) {
                return $node;
            }

            if ($node instanceof Namespace_) {
                foreach ($node->stmts as $stmt) {
                    if ($stmt instanceof Class_) {
                        return $stmt;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, Node> $ast
     * @return string[]
     */
    private function extractImports(array $ast): array
    {
        $imports = [];

        foreach ($ast as $node) {
            $stmts = $node instanceof Namespace_ ? $node->stmts : [$node];

            foreach ($stmts as $stmt) {
                if ($stmt instanceof Use_) {
                    foreach ($stmt->uses as $use) {
                        $imports[] = $use->name->toString();
                    }
                }
            }
        }

        return array_values(array_unique($imports));
    }

    /**
     * @param string[] $imports
     * @return array<string, string> short class name => FQN
     */
    private function buildImportMap(array $imports): array
    {
        $map = [];
        foreach ($imports as $import) {
            $map[class_basename($import)] = $import;
        }

        return $map;
    }

    /**
     * @param string[] $names
     * @param array<string, string> $importMap
     * @return string[]
     */
    private function resolveNames(array $names, array $importMap): array
    {
        return array_map(static fn (string $name) => $importMap[$name] ?? $name, $names);
    }

    /** @return string[] */
    private function extractTraits(Class_ $classNode): array
    {
        $traits = [];

        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $trait) {
                    $traits[] = $trait->toString();
                }
            }
        }

        return $traits;
    }

    /** @return string[] */
    private function extractInterfaces(Class_ $classNode): array
    {
        return array_map(
            static fn (Node\Name $interface) => $interface->toString(),
            $classNode->implements
        );
    }

    /** @return string[] */
    private function extractArrayPropertyValues(Class_ $classNode, string $propertyName): array
    {
        foreach ($classNode->stmts as $stmt) {
            if (!$stmt instanceof Property || $stmt->props[0]->name->toString() !== $propertyName) {
                continue;
            }

            if (!$stmt->props[0]->default instanceof Array_) {
                return [];
            }

            $values = [];
            foreach ($stmt->props[0]->default->items as $item) {
                $value = $item->value;
                if ($value instanceof String_) {
                    $values[] = $value->value;
                }
            }

            return $values;
        }

        return [];
    }

    /** @return array<string, string> */
    private function extractCasts(Class_ $classNode): array
    {
        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof ClassMethod && $stmt->name->toString() === 'casts') {
                return $this->extractCastsFromMethodBody($stmt);
            }
        }

        foreach ($classNode->stmts as $stmt) {
            if ($stmt instanceof Property && $stmt->props[0]->name->toString() === 'casts') {
                return $this->extractCastsFromArrayExpr($stmt->props[0]->default);
            }
        }

        return [];
    }

    /** @return array<string, string> */
    private function extractCastsFromMethodBody(ClassMethod $method): array
    {
        if ($method->stmts === null) {
            return [];
        }

        foreach ($method->stmts as $methodStmt) {
            if ($methodStmt instanceof Return_ && $methodStmt->expr instanceof Array_) {
                return $this->extractCastsFromArrayExpr($methodStmt->expr);
            }
        }

        return [];
    }

    /** @return array<string, string> */
    private function extractCastsFromArrayExpr(?Node $arrayExpr): array
    {
        if (!$arrayExpr instanceof Array_) {
            return [];
        }

        $casts = [];
        foreach ($arrayExpr->items as $item) {
            $key = $item->key;
            $value = $item->value;
            if ($key instanceof String_ && $value instanceof String_) {
                $casts[$key->value] = $value->value;
            }
        }

        return $casts;
    }

    /** @return string[] */
    private function extractMethods(Class_ $classNode): array
    {
        $printer = new Standard();
        $methods = [];

        foreach ($classNode->stmts as $stmt) {
            if (!$stmt instanceof ClassMethod || !$stmt->isPublic()) {
                continue;
            }

            if (in_array($stmt->name->toString(), self::EXCLUDED_METHODS, true)) {
                continue;
            }

            $methods[] = $printer->prettyPrint([$stmt]);
        }

        return $methods;
    }
}
