<?php

namespace Idei\Usim\Support\CodeModifier;

use PhpParser\Node;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\UseUse;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\PrettyPrinter\Standard;

class ClassModifier
{
    public static function addTraitToClass(
        string $filePath,
        string $className,
        string $traitFQN
    ): void {
        if (!file_exists($filePath)) {
            return;
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            return;
        }

        /** @var array<int, Node> $ast */

        $traitShort = class_basename($traitFQN);

        $namespaceFound = false;
        foreach ($ast as $node) {
            if ($node instanceof Namespace_) {
                $namespaceFound = true;
                break;
            }
        }

        $traverser = new NodeTraverser();

        $traverser->addVisitor(
            new class ($className, $traitFQN, $traitShort) extends NodeVisitorAbstract {

            public function __construct(
            private string $className,
            private string $traitFQN,
            private string $traitShort
            ) {}

            public function enterNode(Node $node): ?Node
            {
                // Detectar namespace
                if ($node instanceof Namespace_) {
                    $hasImport = false;

                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof Use_) {
                            foreach ($stmt->uses as $use) {
                                if ($use->name->toString() === $this->traitFQN) {
                                    $hasImport = true;
                                }
                            }
                        }
                    }

                    if (!$hasImport) {
                        array_unshift($node->stmts, new Use_([
                            new UseUse(new Node\Name($this->traitFQN))
                        ]));
                    }
                }

                // Agregar trait a la clase
                if (
                    $node instanceof Class_
                    && $node->name instanceof Identifier
                    && $node->name->toString() === $this->className
                ) {

                    $hasTrait = false;

                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof TraitUse) {
                            foreach ($stmt->traits as $trait) {
                                if ($trait->toString() === $this->traitShort) {
                                    $hasTrait = true;
                                }
                            }
                        }
                    }

                    if (!$hasTrait) {
                        array_unshift(
                            $node->stmts,
                            new TraitUse([new Node\Name($this->traitShort)])
                        );
                    }
                }

                return null;
            }
            }
        );

        $ast = $traverser->traverse($ast);

        // Caso sin namespace → agregar use al root
        if (!$namespaceFound) {
            $hasImport = false;

            foreach ($ast as $node) {
                if ($node instanceof Use_) {
                    foreach ($node->uses as $use) {
                        if ($use->name->toString() === $traitFQN) {
                            $hasImport = true;
                        }
                    }
                }
            }

            if (!$hasImport) {
                array_unshift($ast, new Use_([
                    new UseUse(new Node\Name($traitFQN))
                ]));
            }
        }

        $printer = new Standard();
        file_put_contents($filePath, $printer->prettyPrintFile($ast));
    }

    public static function addInterface(
        string $filePath,
        string $className,
        string $interfaceFQN
    ): void {
        if (!file_exists($filePath)) {
            return;
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            return;
        }

        /** @var array<int, Node> $ast */

        $interfaceShort = class_basename($interfaceFQN);

        $namespaceFound = false;
        foreach ($ast as $node) {
            if ($node instanceof Namespace_) {
                $namespaceFound = true;
                break;
            }
        }

        $traverser = new NodeTraverser();

        $traverser->addVisitor(
            new class ($className, $interfaceFQN, $interfaceShort) extends NodeVisitorAbstract {

            public function __construct(
            private string $className,
            private string $interfaceFQN,
            private string $interfaceShort
            ) {}

            public function enterNode(Node $node): ?Node
            {
                // Manejo de namespace (imports)
                if ($node instanceof Namespace_) {
                    $hasImport = false;

                    foreach ($node->stmts as $stmt) {
                        if ($stmt instanceof Use_) {
                            foreach ($stmt->uses as $use) {
                                if ($use->name->toString() === $this->interfaceFQN) {
                                    $hasImport = true;
                                }
                            }
                        }
                    }

                    if (!$hasImport) {
                        array_unshift($node->stmts, new Use_([
                            new UseUse(new Node\Name($this->interfaceFQN))
                        ]));
                    }
                }

                // Agregar interface a la clase
                if (
                    $node instanceof Class_
                    && $node->name instanceof Identifier
                    && $node->name->toString() === $this->className
                ) {

                    $hasInterface = false;

                    foreach ($node->implements as $impl) {
                        if (
                            (
                                $impl->toString() === $this->interfaceShort ||
                                $impl->toString() === $this->interfaceFQN
                            )
                        ) {
                            $hasInterface = true;
                        }
                    }

                    if (!$hasInterface) {
                        $node->implements[] = new \PhpParser\Node\Name($this->interfaceShort);
                    }
                }

                return null;
            }
            }
        );

        $ast = $traverser->traverse($ast);

        // Caso sin namespace → agregar import en root
        if (!$namespaceFound) {
            $hasImport = false;

            foreach ($ast as $node) {
                if ($node instanceof Use_) {
                    foreach ($node->uses as $use) {
                        if ($use->name->toString() === $interfaceFQN) {
                            $hasImport = true;
                        }
                    }
                }
            }

            if (!$hasImport) {
                array_unshift($ast, new Use_([
                    new UseUse(new Node\Name($interfaceFQN))
                ]));
            }
        }

        $printer = new Standard();
        file_put_contents($filePath, $printer->prettyPrintFile($ast));
    }

    public static function addPropertyArrayValue(
        string $filePath,
        string $className,
        string $propertyName,
        string $value
    ): void {
        if (!file_exists($filePath)) {
            return;
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            return;
        }

        /** @var array<int, Node> $ast */

        $traverser = new NodeTraverser();

        $traverser->addVisitor(
            new class ($className, $propertyName, $value) extends NodeVisitorAbstract {

            public function __construct(
            private string $className,
            private string $propertyName,
            private string $value
            ) {}

            public function enterNode(Node $node): ?Node
            {
                if (
                    $node instanceof Class_
                    && $node->name instanceof Identifier
                    && $node->name->toString() === $this->className
                ) {

                    foreach ($node->stmts as $stmt) {

                        if ($stmt instanceof \PhpParser\Node\Stmt\Property) {

                            if (
                                $stmt->props[0]->name->toString() === $this->propertyName
                            ) {

                                $prop = $stmt->props[0];

                                if ($prop->default instanceof \PhpParser\Node\Expr\Array_) {

                                    foreach ($prop->default->items as $item) {
                                        if ($item->value instanceof \PhpParser\Node\Scalar\String_) {
                                            if ($item->value->value === $this->value) {
                                                return null;
                                            }
                                        }
                                    }

                                    $prop->default->items[] = new \PhpParser\Node\Expr\ArrayItem(
                                        new \PhpParser\Node\Scalar\String_($this->value)
                                    );
                                }
                            }
                        }
                    }
                }

                return null;
            }
            }
        );

        $ast = $traverser->traverse($ast);

        $printer = new Standard();
        file_put_contents($filePath, $printer->prettyPrintFile($ast));
    }

    public static function addCast(
        string $filePath,
        string $className,
        string $field,
        string $type
    ): void {
        if (!file_exists($filePath)) {
            return;
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            return;
        }

        /** @var array<int, Node> $ast */

        $traverser = new NodeTraverser();

        $traverser->addVisitor(
            new class ($className, $field, $type) extends NodeVisitorAbstract {

            public function __construct(
            private string $className,
            private string $field,
            private string $type
            ) {}

            public function enterNode(Node $node): ?Node
            {
                if (
                    $node instanceof Class_
                    && $node->name instanceof Identifier
                    && $node->name->toString() === $this->className
                ) {

                    foreach ($node->stmts as $stmt) {

                        // Caso 1: método casts()
                        if (
                        $stmt instanceof \PhpParser\Node\Stmt\ClassMethod
                        && $stmt->name->toString() === 'casts'
                        ) {

                            if ($stmt->stmts === null) {
                                continue;
                            }

                            foreach ($stmt->stmts as $methodStmt) {

                                if (
                                $methodStmt instanceof \PhpParser\Node\Stmt\Return_
                                && $methodStmt->expr instanceof \PhpParser\Node\Expr\Array_
                                ) {

                                    foreach ($methodStmt->expr->items as $item) {
                                        if ($item->key instanceof \PhpParser\Node\Scalar\String_) {
                                            if ($item->key->value === $this->field) {
                                                return null;
                                            }
                                        }
                                    }

                                    $methodStmt->expr->items[] = new \PhpParser\Node\Expr\ArrayItem(
                                        new \PhpParser\Node\Scalar\String_($this->type),
                                        new \PhpParser\Node\Scalar\String_($this->field)
                                    );
                                }
                            }
                        }

                        // Caso 2: propiedad $casts
                        if (
                        $stmt instanceof \PhpParser\Node\Stmt\Property
                        && $stmt->props[0]->name->toString() === 'casts'
                        ) {

                            $prop = $stmt->props[0];

                            if ($prop->default instanceof \PhpParser\Node\Expr\Array_) {

                                foreach ($prop->default->items as $item) {
                                    if ($item->key instanceof \PhpParser\Node\Scalar\String_) {
                                        if ($item->key->value === $this->field) {
                                            return null;
                                        }
                                    }
                                }

                                $prop->default->items[] = new \PhpParser\Node\Expr\ArrayItem(
                                    new \PhpParser\Node\Scalar\String_($this->type),
                                    new \PhpParser\Node\Scalar\String_($this->field)
                                );
                            }
                        }
                    }
                }

                return null;
            }
            }
        );

        $ast = $traverser->traverse($ast);

        $printer = new Standard();
        file_put_contents($filePath, $printer->prettyPrintFile($ast));
    }
}
