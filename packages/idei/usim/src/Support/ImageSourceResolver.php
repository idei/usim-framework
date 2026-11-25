<?php

namespace Idei\Usim\Support;

use Idei\Usim\Contracts\ImagePlaceholderGeneratorInterface;
use Idei\Usim\Enums\AspectRatio;
use Idei\Usim\Upload\UploadService;
use Idei\Usim\ValueObjects\ImagePlaceholder;
use InvalidArgumentException;

/**
 * Shared image storage URL resolution and placeholder generation helper
 * used by `Image`, `ItemCard`, and `CardGrid` for consistent image pipelines.
 */
final class ImageSourceResolver
{
    /**
     * Validate and normalize a relative storage directory path (e.g. `uploads/images`).
     */
    public static function normalizeStorageDirectory(?string $directory): ?string
    {
        if ($directory === null) {
            return null;
        }

        $normalized = trim($directory, '/');
        if ($normalized === '' || in_array('..', explode('/', $normalized), true)) {
            throw new InvalidArgumentException('Image storage directory must be a non-empty relative path.');
        }

        return $normalized;
    }

    /**
     * Resolve a potentially relative image filename against a configured USIM upload directory.
     *
     * Absolute URLs, root-relative URLs, and data URIs are returned unchanged.
     */
    public static function resolveStoredSource(string $src, ?string $storagePath): string
    {
        if ($storagePath === null || self::isAbsoluteSource($src)) {
            return $src;
        }

        $filename = trim($src, '/');
        if ($filename === '' || in_array('..', explode('/', $filename), true)) {
            throw new InvalidArgumentException('Image source must be a filename relative to its storage directory.');
        }

        return UploadService::fileUrl("{$storagePath}/{$filename}");
    }

    /**
     * Check whether an image source is already an absolute URL, root-relative path, or URI scheme.
     */
    public static function isAbsoluteSource(string $src): bool
    {
        return str_starts_with($src, '/')
            || preg_match('/^[a-z][a-z0-9+.-]*:/i', $src) === 1;
    }

    /**
     * Normalize an aspect ratio value (enum or `W:H` / `W/H` string) into a canonical string.
     */
    public static function normalizeAspectRatio(AspectRatio|string|null $aspect): ?string
    {
        if ($aspect instanceof AspectRatio) {
            return $aspect->value;
        }

        if ($aspect === null) {
            return null;
        }

        $trimmed = trim($aspect);

        return $trimmed === '' ? null : $trimmed;
    }

    /**
     * Determine whether global USIM placeholder generation is enabled in config.
     */
    public static function isGlobalPlaceholderEnabled(): bool
    {
        return (bool) config('usim.images.placeholder.enabled', true);
    }

    /**
     * Generate a placeholder image source via the configured ImagePlaceholderGeneratorInterface.
     */
    public static function generatePlaceholder(
        int|string $id,
        ?string $caption = null,
        AspectRatio|string|null $aspectRatio = null,
        ?int $configuredWidth = null,
        ?int $configuredHeight = null,
        ?string $backgroundColor = null,
        ?string $textColor = null,
    ): string {
        $settings = self::placeholderSettings();
        [$width, $height] = self::resolveDimensions(
            $aspectRatio,
            $configuredWidth,
            $configuredHeight,
            $settings
        );

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException('Image placeholder dimensions must be positive integers.');
        }

        return app(ImagePlaceholderGeneratorInterface::class)->generate(new ImagePlaceholder(
            id: (string) $id,
            width: $width,
            height: $height,
            caption: $caption,
            backgroundColor: self::stringSetting(
                $backgroundColor,
                $settings['background_color'] ?? null,
                '#234878'
            ),
            textColor: self::stringSetting(
                $textColor,
                $settings['text_color'] ?? null,
                '#d5d9df'
            ),
            seedPrefix: self::stringSetting($settings['seed_prefix'] ?? null, null, '123'),
        ));
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array{0: int, 1: int}
     */
    private static function resolveDimensions(
        AspectRatio|string|null $aspectRatio,
        ?int $configuredWidth,
        ?int $configuredHeight,
        array $settings
    ): array {
        if ($aspectRatio instanceof AspectRatio) {
            if ($configuredWidth === null && $configuredHeight === null) {
                $configuredWidth = self::positiveSetting($settings, 'width', 640);
            }

            return $aspectRatio->dimensionsFor($configuredWidth, $configuredHeight);
        }

        if (is_string($aspectRatio) && trim($aspectRatio) !== '') {
            $normalized = str_replace('/', ':', trim($aspectRatio));
            $enum = AspectRatio::tryFrom($normalized);
            if ($enum !== null) {
                if ($configuredWidth === null && $configuredHeight === null) {
                    $configuredWidth = self::positiveSetting($settings, 'width', 640);
                }

                return $enum->dimensionsFor($configuredWidth, $configuredHeight);
            }

            if (preg_match('/^([1-9][0-9]*)\s*:\s*([1-9][0-9]*)$/', $normalized, $matches) === 1) {
                $ratioW = (int) $matches[1];
                $ratioH = (int) $matches[2];
                $ratio = $ratioW / $ratioH;

                if ($configuredWidth === null && $configuredHeight === null) {
                    $width = self::positiveSetting($settings, 'width', 640);
                    $height = max(1, (int) round($width / $ratio));

                    return [$width, $height];
                }

                if ($configuredWidth === null) {
                    return [max(1, (int) round($configuredHeight * $ratio)), $configuredHeight];
                }

                if ($configuredHeight === null) {
                    return [$configuredWidth, max(1, (int) round($configuredWidth / $ratio))];
                }

                return [$configuredWidth, $configuredHeight];
            }
        }

        $width = $configuredWidth ?? self::positiveSetting($settings, 'width', 640);
        $height = $configuredHeight ?? self::positiveSetting($settings, 'height', 360);

        return [$width, $height];
    }

    /**
     * @return array<string, mixed>
     */
    private static function placeholderSettings(): array
    {
        $settings = config('usim.images.placeholder', []);
        if (! is_array($settings)) {
            throw new InvalidArgumentException('USIM image placeholder settings must be an array.');
        }

        $stringKeyedSettings = [];
        foreach ($settings as $key => $value) {
            if (! is_string($key)) {
                throw new InvalidArgumentException('USIM image placeholder settings must use string keys.');
            }
            $stringKeyedSettings[$key] = $value;
        }

        return $stringKeyedSettings;
    }

    /**
     * @param  array<string, mixed>  $settings
     */
    private static function positiveSetting(array $settings, string $key, int $default): int
    {
        $value = $settings[$key] ?? $default;
        if (! is_int($value) || $value <= 0) {
            throw new InvalidArgumentException("USIM image placeholder setting [{$key}] must be a positive integer.");
        }

        return $value;
    }

    private static function stringSetting(mixed $value, mixed $fallback, string $default): string
    {
        $setting = $value ?? $fallback ?? $default;
        if (! is_string($setting)) {
            throw new InvalidArgumentException('USIM image placeholder text and color settings must be strings.');
        }

        return $setting;
    }
}
