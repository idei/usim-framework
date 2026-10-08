<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\ComponentIdGeneratorInterface;

/**
 * Static facade / adapter for UI component ID generation.
 *
 * Forwards calls to the scoped ComponentIdGeneratorInterface in the Laravel service container.
 */
class UIIdGenerator
{
    private static ?ComponentIdGeneratorInterface $fallbackInstance = null;

    private static function getGenerator(): ComponentIdGeneratorInterface
    {
        if (function_exists('app') && app()->bound(ComponentIdGeneratorInterface::class)) {
            return app(ComponentIdGeneratorInterface::class);
        }

        if (self::$fallbackInstance === null) {
            self::$fallbackInstance = new ComponentIdGenerator;
        }

        return self::$fallbackInstance;
    }

    public static function pushCurrentContext(string $context): void
    {
        self::getGenerator()->pushCurrentContext($context);
    }

    public static function popCurrentContext(): ?string
    {
        return self::getGenerator()->popCurrentContext();
    }

    public static function getCurrentContext(): ?string
    {
        return self::getGenerator()->getCurrentContext();
    }

    public static function generate(string $context): int
    {
        return self::getGenerator()->generate($context);
    }

    public static function generateFromName(string $context, string $name): int
    {
        return self::getGenerator()->generateFromName($context, $name);
    }

    /**
     * @return array<string, mixed>
     */
    public static function getContextInfo(string $context): array
    {
        return self::getGenerator()->getContextInfo($context);
    }

    public static function reserveContextId(string $context, int $id): void
    {
        self::getGenerator()->reserveContextId($context, $id);
    }

    public static function getContextFromId(int $id): ?string
    {
        return self::getGenerator()->getContextFromId($id);
    }

    public static function getContextOffset(string $context): int
    {
        return self::getGenerator()->getContextOffset($context);
    }

    public static function reset(): void
    {
        self::getGenerator()->reset();
        self::$fallbackInstance = null;
    }
}
