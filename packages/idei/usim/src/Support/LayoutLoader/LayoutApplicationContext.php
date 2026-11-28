<?php

declare(strict_types=1);

namespace Idei\Usim\Support\LayoutLoader;

/**
 * Immutable context threaded through recursive container layout applications.
 */
final readonly class LayoutApplicationContext
{
    /**
     * @param  list<string>  $visitedPaths
     */
    public function __construct(
        public ?string $callingContext = null,
        public bool $autoTranslate = false,
        public ?string $baseDir = null,
        public array $visitedPaths = [],
    ) {}

    public function withAutoTranslate(bool $autoTranslate): self
    {
        return new self(
            callingContext: $this->callingContext,
            autoTranslate: $autoTranslate,
            baseDir: $this->baseDir,
            visitedPaths: $this->visitedPaths,
        );
    }

    public function withBaseDir(?string $baseDir): self
    {
        return new self(
            callingContext: $this->callingContext,
            autoTranslate: $this->autoTranslate,
            baseDir: $baseDir,
            visitedPaths: $this->visitedPaths,
        );
    }

    /**
     * @param  list<string>  $visitedPaths
     */
    public function withVisitedPaths(array $visitedPaths): self
    {
        return new self(
            callingContext: $this->callingContext,
            autoTranslate: $this->autoTranslate,
            baseDir: $this->baseDir,
            visitedPaths: $visitedPaths,
        );
    }

    public function withVisitedPath(string $resolvedPath): self
    {
        return $this->withVisitedPaths([...$this->visitedPaths, $resolvedPath]);
    }
}
