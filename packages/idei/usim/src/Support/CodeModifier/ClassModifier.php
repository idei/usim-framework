<?php

namespace Idei\Usim\Support\CodeModifier;

use PhpParser\Node;
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

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if (!$ast) {
            return;
        }

        $traitShort = class_basename($traitFQN);

        $namespaceFound = false;

        $traverser = new NodeTraverser();

        $traverser->addVisitor(
            new class ($className, $traitFQN, $traitShort, $namespaceFound) extends NodeVisitorAbstract {

            public function __construct(
            private $className,
            private $traitFQN,
            private $traitShort,
            private &$namespaceFound
            ) {}

            public function enterNode(Node $node)
            {
                // Detectar namespace
                if ($node instanceof Namespace_) {
                    $this->namespaceFound = true;

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
                if ($node instanceof Class_ && $node->name->toString() === $this->className) {

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

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if (!$ast) {
            return;
        }

        $interfaceShort = class_basename($interfaceFQN);

        $namespaceFound = false;

        $traverser = new NodeTraverser();

        $traverser->addVisitor(
            new class ($className, $interfaceFQN, $interfaceShort, $namespaceFound) extends NodeVisitorAbstract {

            public function __construct(
            private $className,
            private $interfaceFQN,
            private $interfaceShort,
            private &$namespaceFound
            ) {}

            public function enterNode(Node $node)
            {
                // Manejo de namespace (imports)
                if ($node instanceof Namespace_) {
                    $this->namespaceFound = true;

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
                && $node->name->toString() === $this->className
                ) {

                    $hasInterface = false;

                    foreach ($node->implements as $impl) {
                        if (
                            $impl->toString() === $this->interfaceShort ||
                            $impl->toString() === $this->interfaceFQN
                        ) {
                            $hasInterface = true;
                        }
                    }

                    if (!$hasInterface) {
                        $node->implements[] = new \PhpParser\Node\Name($this->interfaceShort);
                    }
                }
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
}
