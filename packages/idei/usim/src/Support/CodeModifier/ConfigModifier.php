<?php

namespace Idei\Usim\Support\CodeModifier;

use PhpParser\Node;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\DNumber;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class ConfigModifier
{
    /**
     * Sets a single configuration key-value pair in a PHP config file.
     * Supports dot-notation for nested keys (e.g. 'column_names.team_foreign_key').
     */
    public static function set(string $filePath, string $keyPath, mixed $value): bool
    {
        return self::update($filePath, [$keyPath => $value]);
    }

    /**
     * Updates multiple configuration key-value pairs in a PHP config file.
     *
     * @param string $filePath
     * @param array<string, mixed> $values
     * @return bool
     */
    public static function update(string $filePath, array $values): bool
    {
        if (!file_exists($filePath)) {
            return false;
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            return false;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        if ($ast === null) {
            return false;
        }

        $returnNode = null;
        foreach ($ast as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                $returnNode = $stmt;
                break;
            }
        }

        if ($returnNode === null) {
            return false;
        }

        /** @var Array_ $configArray */
        $configArray = $returnNode->expr;

        foreach ($values as $keyPath => $val) {
            $keys = explode('.', $keyPath);
            self::setNestedValue($configArray, $keys, $val);
        }

        $printer = new Standard();
        $newCode = $printer->prettyPrintFile($ast);

        return file_put_contents($filePath, $newCode) !== false;
    }

    /**
     * @param Array_ $arrayNode
     * @param array<int, string> $keys
     * @param mixed $value
     */
    private static function setNestedValue(Array_ $arrayNode, array $keys, mixed $value): void
    {
        if (empty($keys)) {
            return;
        }

        $currentKey = array_shift($keys);

        // Check if item with current key exists
        $matchingItem = null;
        foreach ($arrayNode->items as $item) {
            if ($item->key !== null) {
                $keyName = self::extractKeyName($item->key);
                if ($keyName === $currentKey) {
                    $matchingItem = $item;
                    break;
                }
            }
        }

        if (empty($keys)) {
            // Leaf node: set the value
            $valueNode = self::valueToNode($value);
            if ($matchingItem !== null) {
                $matchingItem->value = $valueNode;
            } else {
                $arrayNode->items[] = new ArrayItem(
                    value: $valueNode,
                    key: new String_($currentKey)
                );
            }
            return;
        }

        // Intermediate node: must be an Array_
        if ($matchingItem !== null) {
            if (!($matchingItem->value instanceof Array_)) {
                $matchingItem->value = new Array_([], ['kind' => Array_::KIND_SHORT]);
            }
            /** @var Array_ $subArray */
            $subArray = $matchingItem->value;
            self::setNestedValue($subArray, $keys, $value);
        } else {
            $subArray = new Array_([], ['kind' => Array_::KIND_SHORT]);
            $arrayNode->items[] = new ArrayItem(
                value: $subArray,
                key: new String_($currentKey)
            );
            self::setNestedValue($subArray, $keys, $value);
        }
    }

    private static function extractKeyName(Node $keyNode): ?string
    {
        if ($keyNode instanceof String_) {
            return $keyNode->value;
        }

        if ($keyNode instanceof LNumber) {
            return (string) $keyNode->value;
        }

        if ($keyNode instanceof Node\Identifier) {
            return $keyNode->name;
        }

        return null;
    }

    private static function valueToNode(mixed $value): Node\Expr
    {
        if (is_bool($value)) {
            return new ConstFetch(new Name($value ? 'true' : 'false'));
        }

        if (is_null($value)) {
            return new ConstFetch(new Name('null'));
        }

        if (is_string($value)) {
            return new String_($value);
        }

        if (is_int($value)) {
            return new LNumber($value);
        }

        if (is_float($value)) {
            return new DNumber($value);
        }

        if (is_array($value)) {
            $items = [];
            $isAssoc = !array_is_list($value);

            foreach ($value as $k => $v) {
                $valNode = self::valueToNode($v);
                $keyNode = $isAssoc ? new String_((string) $k) : null;
                $items[] = new ArrayItem($valNode, $keyNode);
            }

            return new Array_($items, ['kind' => Array_::KIND_SHORT]);
        }

        return new String_($value instanceof \Stringable ? (string) $value : '');
    }
}
