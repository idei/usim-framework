<?php

namespace App\Services\Movie;

use App\Models\Movie;
use Idei\Usim\Contracts\ItemList\BulkDeletable;
use Idei\Usim\Contracts\ItemList\Creatable;
use Idei\Usim\Contracts\ItemList\Deletable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\Contracts\ItemList\Updatable;

/**
 * Session-backed movie catalog service for the ItemList Demo.
 *
 * Demonstrates binding an ItemList component to granular CRUD contracts
 * (Listable, Creatable, Updatable, Deletable, BulkDeletable) without mutating
 * the shared read-only demo database.
 */
class SessionMovieCatalogService implements BulkDeletable, Creatable, Deletable, Listable, Updatable
{
    public const SESSION_KEY = 'usim.demo.movies_catalog';

    /**
     * @param  array<string, mixed>  $context
     * @return list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}>
     */
    public function list(string $search = '', array $context = []): array
    {
        $catalog = $this->getCatalog();
        $term = mb_strtolower(trim($search));

        if ($term === '') {
            return $catalog;
        }

        return array_values(array_filter(
            $catalog,
            static function (array $movie) use ($term): bool {
                $haystack = mb_strtolower(
                    $movie['label'].' '.$movie['subtitle'].' '.$movie['badge']
                );

                return str_contains($haystack, $term);
            }
        ));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}
     */
    public function create(string $label, array $payload = []): array
    {
        $catalog = $this->getCatalog();
        $year = (int) date('Y');

        $newMovie = [
            'id' => 'custom_'.(count($catalog) + 1).'_'.substr(md5($label.(string) microtime(true)), 0, 5),
            'label' => $label,
            'subtitle' => "Año {$year} • Agregada manualmente al catálogo",
            'icon' => '🍿',
            'badge' => 'Nueva',
            'badge_style' => 'success',
        ];

        array_unshift($catalog, $newMovie);
        $this->saveCatalog($catalog);

        return $newMovie;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}|null
     */
    public function update(int|string $id, string $label, array $payload = []): ?array
    {
        $catalog = $this->getCatalog();
        $targetId = (string) $id;
        $updated = null;

        foreach ($catalog as $index => $movie) {
            if ((string) $movie['id'] === $targetId) {
                $catalog[$index]['label'] = $label;
                $updated = $catalog[$index];
                break;
            }
        }

        if ($updated !== null) {
            $this->saveCatalog($catalog);
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
        $catalog = $this->getCatalog();
        $removeSet = array_map(static fn (int|string $id): string => (string) $id, $ids);

        $remaining = array_values(array_filter(
            $catalog,
            static fn (array $movie): bool => ! in_array((string) $movie['id'], $removeSet, true)
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
     * @return list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}>
     */
    private function getCatalog(): array
    {
        $stored = session()->get(self::SESSION_KEY);
        if (is_array($stored)) {
            /** @var list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}> $stored */
            return array_values($stored);
        }

        $seeded = $this->buildInitialCatalog();
        $this->saveCatalog($seeded);

        return $seeded;
    }

    /**
     * @param  list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}>  $catalog
     */
    private function saveCatalog(array $catalog): void
    {
        session()->put(self::SESSION_KEY, array_values($catalog));
    }

    /**
     * @return list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}>
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
                $items = [];
                foreach ($movies as $movie) {
                    $title = t((string) $movie->title);
                    $genreRaw = $movie->genre->name ?? 'genre.unknown';
                    $genre = t((string) $genreRaw);
                    $year = (string) $movie->release_year;
                    $cast = (string) $movie->cast_members;

                    $items[] = [
                        'id' => $movie->id,
                        'label' => "{$title} ({$year})",
                        'subtitle' => "Reparto: {$cast}",
                        'icon' => $this->resolveGenreIcon((string) $genreRaw),
                        'badge' => $genre,
                        'badge_style' => 'primary',
                    ];
                }

                return $items;
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
     * @return list<array{id: int|string, label: string, subtitle: string, icon: string, badge: string, badge_style: string}>
     */
    private function fallbackCatalog(): array
    {
        return [
            [
                'id' => 1,
                'label' => 'Fight Club (1999)',
                'subtitle' => 'Reparto: Brad Pitt, Edward Norton, Helena Bonham Carter',
                'icon' => '🎭',
                'badge' => 'Drama',
                'badge_style' => 'primary',
            ],
            [
                'id' => 2,
                'label' => 'Forrest Gump (1994)',
                'subtitle' => 'Reparto: Tom Hanks, Robin Wright, Gary Sinise',
                'icon' => '🎭',
                'badge' => 'Drama',
                'badge_style' => 'primary',
            ],
            [
                'id' => 3,
                'label' => 'Gladiator (2000)',
                'subtitle' => 'Reparto: Russell Crowe, Joaquin Phoenix, Connie Nielsen',
                'icon' => '💥',
                'badge' => 'Acción',
                'badge_style' => 'primary',
            ],
            [
                'id' => 4,
                'label' => 'Inception (2010)',
                'subtitle' => 'Reparto: Leonardo DiCaprio, Joseph Gordon-Levitt, Ellen Page',
                'icon' => '🚀',
                'badge' => 'Ciencia Ficción',
                'badge_style' => 'primary',
            ],
            [
                'id' => 5,
                'label' => 'Interstellar (2014)',
                'subtitle' => 'Reparto: Matthew McConaughey, Anne Hathaway, Jessica Chastain',
                'icon' => '🚀',
                'badge' => 'Ciencia Ficción',
                'badge_style' => 'primary',
            ],
            [
                'id' => 6,
                'label' => 'The Matrix (1999)',
                'subtitle' => 'Reparto: Keanu Reeves, Laurence Fishburne, Carrie-Anne Moss',
                'icon' => '🚀',
                'badge' => 'Ciencia Ficción',
                'badge_style' => 'primary',
            ],
        ];
    }
}
