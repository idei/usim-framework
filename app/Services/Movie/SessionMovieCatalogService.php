<?php

namespace App\Services\Movie;

use App\Models\Movie;
use Idei\Usim\Contracts\ItemList\BulkDeletable;
use Idei\Usim\Contracts\ItemList\Creatable;
use Idei\Usim\Contracts\ItemList\Deletable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\Contracts\ItemList\Updatable;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\Style;
use Illuminate\Support\Collection;

/**
 * Session-backed movie catalog service for the ItemList Demo.
 *
 * Demonstrates binding an ItemList component to granular CRUD contracts
 * (Listable, Creatable, Updatable, Deletable, BulkDeletable) using typed
 * ListItemDTO presentation rows without mutating the shared demo database.
 */
class SessionMovieCatalogService implements BulkDeletable, Creatable, Deletable, Listable, Updatable
{
    public const SESSION_KEY = 'usim.demo.movies_catalog';

    /**
     * @param  array<string, mixed>  $context
     * @return Collection<int, ListItemDTO>
     */
    public function list(string $search = '', array $context = []): Collection
    {
        $catalog = $this->getCatalog()->map(
            static fn (array $row): ListItemDTO => ListItemDTO::fromArray($row)
        );
        $term = mb_strtolower(trim($search));

        if ($term === '') {
            return $catalog->values();
        }

        return $catalog->filter(
            static function (ListItemDTO $movie) use ($term): bool {
                $haystack = mb_strtolower(
                    $movie->label.' '.($movie->subtitle ?? '').' '.($movie->badge ?? '')
                );

                return str_contains($haystack, $term);
            }
        )->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function create(string $label, array $payload = []): ListItemDTO
    {
        $catalog = $this->getCatalog()->all();
        $year = (int) date('Y');

        $dto = ListItemDTO::make(
            id: 'custom_'.(count($catalog) + 1).'_'.substr(md5($label.(string) microtime(true)), 0, 5),
            label: $label,
            subtitle: "Año {$year} • Agregada manualmente al catálogo",
            icon: '🍿',
            badge: 'Nueva',
            badgeStyle: Style::SUCCESS,
            style: Style::SUCCESS,
        );

        array_unshift($catalog, $dto->toArray());
        $this->saveCatalog(array_values($catalog));

        return $dto;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function update(int|string $id, string $label, array $payload = []): ?ListItemDTO
    {
        $catalog = $this->getCatalog()->all();
        $targetId = (string) $id;
        $updated = null;

        foreach ($catalog as $index => $movie) {
            $movieId = $movie['id'] ?? null;
            if ((is_int($movieId) || is_string($movieId)) && (string) $movieId === $targetId) {
                $dto = ListItemDTO::fromArray(array_merge($movie, ['label' => $label]));
                $catalog[$index] = $dto->toArray();
                $updated = $dto;
                break;
            }
        }

        if ($updated !== null) {
            $this->saveCatalog(array_values($catalog));
        }

        return $updated;
    }

    public function delete(int|string $id): bool
    {
        return $this->deleteMany([$id]) > 0;
    }

    /**
     * @param  list<int|string>  $ids
     */
    public function deleteMany(array $ids): int
    {
        $catalog = $this->getCatalog()->all();
        $removeSet = array_map(static fn (int|string $id): string => (string) $id, $ids);

        $remaining = array_values(array_filter(
            $catalog,
            static function (array $movie) use ($removeSet): bool {
                $movieId = $movie['id'] ?? null;

                return ! (is_int($movieId) || is_string($movieId)) || ! in_array((string) $movieId, $removeSet, true);
            }
        ));

        $deletedCount = count($catalog) - count($remaining);
        if ($deletedCount > 0) {
            $this->saveCatalog($remaining);
        }

        return $deletedCount;
    }

    /**
     * Reset session catalog back to its initial seed state.
     */
    public function reset(): void
    {
        session()->forget(self::SESSION_KEY);
        $this->saveCatalog($this->buildInitialCatalog());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function getCatalog(): Collection
    {
        $stored = session()->get(self::SESSION_KEY);
        if (is_array($stored)) {
            /** @var list<array<string, mixed>> $normalized */
            $normalized = array_values(array_filter($stored, static fn (mixed $item): bool => is_array($item)));

            return collect($normalized);
        }

        $seeded = $this->buildInitialCatalog();
        $this->saveCatalog($seeded);

        return collect($seeded);
    }

    /**
     * @param  list<array<string, mixed>>  $catalog
     */
    private function saveCatalog(array $catalog): void
    {
        session()->put(self::SESSION_KEY, array_values($catalog));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function buildInitialCatalog(): array
    {
        try {
            /** @var MovieListingService $service */
            $service = app(MovieListingService::class);
            $result = $service->paginate(page: 1, perPage: 20, sortField: 'title', sortDirection: 'asc');
            /** @var list<Movie> $movies */
            $movies = array_values($result['items']);

            if ($movies !== []) {
                return array_map(function (Movie $movie): array {
                    $title = t((string) $movie->title);
                    $genreRaw = $movie->genre->name ?? 'genre.unknown';
                    $genre = t((string) $genreRaw);
                    $year = (string) $movie->release_year;
                    $cast = (string) $movie->cast_members;

                    return ListItemDTO::make(
                        id: $movie->id,
                        label: "{$title} ({$year})",
                        subtitle: "Reparto: {$cast}",
                        icon: $this->resolveGenreIcon((string) $genreRaw),
                        badge: $genre,
                        badgeStyle: Style::PRIMARY,
                    )->toArray();
                }, $movies);
            }
        } catch (\Throwable) {
            // Fall back to static demo items when database is not seeded/available.
        }

        return $this->fallbackCatalog();
    }

    private function resolveGenreIcon(string $genreKey): string
    {
        return match ($genreKey) {
            'genre.sci-fi' => '🚀',
            'genre.action' => '💥',
            'genre.crime' => '🕵️',
            'genre.drama' => '🎭',
            'genre.fantasy' => '🧙',
            'genre.animation' => '🎨',
            'genre.romance' => '❤️',
            'genre.thriller' => '🔪',
            default => '🎬',
        };
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function fallbackCatalog(): array
    {
        return array_map(
            static fn (ListItemDTO $dto): array => $dto->toArray(),
            [
                ListItemDTO::make(
                    id: 1,
                    label: 'Fight Club (1999)',
                    subtitle: 'Reparto: Brad Pitt, Edward Norton, Helena Bonham Carter',
                    icon: '🎭',
                    badge: 'Drama',
                    badgeStyle: Style::PRIMARY,
                ),
                ListItemDTO::make(
                    id: 2,
                    label: 'Forrest Gump (1994)',
                    subtitle: 'Reparto: Tom Hanks, Robin Wright, Gary Sinise',
                    icon: '🎭',
                    badge: 'Drama',
                    badgeStyle: Style::PRIMARY,
                ),
                ListItemDTO::make(
                    id: 3,
                    label: 'Gladiator (2000)',
                    subtitle: 'Reparto: Russell Crowe, Joaquin Phoenix, Connie Nielsen',
                    icon: '💥',
                    badge: 'Acción',
                    badgeStyle: Style::PRIMARY,
                ),
                ListItemDTO::make(
                    id: 4,
                    label: 'Inception (2010)',
                    subtitle: 'Reparto: Leonardo DiCaprio, Joseph Gordon-Levitt, Ellen Page',
                    icon: '🚀',
                    badge: 'Ciencia Ficción',
                    badgeStyle: Style::PRIMARY,
                ),
                ListItemDTO::make(
                    id: 5,
                    label: 'Interstellar (2014)',
                    subtitle: 'Reparto: Matthew McConaughey, Anne Hathaway, Jessica Chastain',
                    icon: '🚀',
                    badge: 'Ciencia Ficción',
                    badgeStyle: Style::PRIMARY,
                ),
                ListItemDTO::make(
                    id: 6,
                    label: 'The Matrix (1999)',
                    subtitle: 'Reparto: Keanu Reeves, Laurence Fishburne, Carrie-Anne Moss',
                    icon: '🚀',
                    badge: 'Ciencia Ficción',
                    badgeStyle: Style::PRIMARY,
                ),
            ]
        );
    }
}
