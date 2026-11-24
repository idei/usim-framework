<?php

namespace App\Services\Movie;

use Idei\Usim\Contracts\CardGrid\CardListable;
use Idei\Usim\DTOs\CardActionDTO;
use Idei\Usim\DTOs\CardItemDTO;
use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;
use Idei\Usim\Enums\Style;

/**
 * Demo CardListable service powering CardGridDemo.
 *
 * Demonstrates server-side search, multi-criteria filtering (select, multi_select,
 * chips, toggle), sorting, pagination, rich card slots (SVG poster artwork, eyebrow,
 * badges, highlight metric, attributes, tags), and per-card actions including overflow.
 */
class MovieCardGridService implements CardListable
{
    public const FAVORITES_SESSION_KEY = 'usim.demo.card_grid.favorites';

    public function query(CardQuery $query): CardPageResult
    {
        $favorites = $this->getFavoriteIds();
        $records = $this->catalog();

        // 1. Search
        $search = mb_strtolower(trim($query->search));
        if ($search !== '') {
            $records = array_values(array_filter(
                $records,
                static function (array $movie) use ($search): bool {
                    $haystack = mb_strtolower(implode(' ', [
                        $movie['title'],
                        $movie['director'],
                        $movie['genre_label'],
                        $movie['synopsis'],
                        (string) $movie['year'],
                        implode(' ', $movie['tags']),
                    ]));

                    return str_contains($haystack, $search);
                }
            ));
        }

        // 2. Integrated Toolbar Filters
        $genre = $query->filter('genre');
        if (is_string($genre) && $genre !== '') {
            $records = array_values(array_filter(
                $records,
                static fn (array $m): bool => $m['genre'] === $genre
            ));
        }

        $decade = $query->filter('decade');
        if (is_string($decade) && $decade !== '') {
            $decadeStart = (int) $decade;
            $records = array_values(array_filter(
                $records,
                static fn (array $m): bool => $m['year'] >= $decadeStart && $m['year'] < ($decadeStart + 10)
            ));
        }

        $tags = $query->filter('tags');
        if (is_array($tags) && $tags !== []) {
            $wanted = array_map(static fn (mixed $v): string => is_scalar($v) ? (string) $v : '', $tags);
            $records = array_values(array_filter(
                $records,
                static fn (array $m): bool => count(array_intersect($m['tags'], $wanted)) > 0
            ));
        }

        $featuredOnly = $query->filter('featured');
        if ($featuredOnly === true || $featuredOnly === 'true' || $featuredOnly === '1' || $featuredOnly === 1) {
            $records = array_values(array_filter(
                $records,
                static fn (array $m): bool => $m['featured'] === true
            ));
        }

        // 3. Sorting
        $sortBy = $query->sortBy ?? 'title';
        $direction = $query->sortDirection === 'desc' ? -1 : 1;

        usort($records, static function (array $a, array $b) use ($sortBy, $direction): int {
            $cmp = match ($sortBy) {
                'year' => $a['year'] <=> $b['year'],
                'rating' => $a['rating'] <=> $b['rating'],
                'runtime' => $a['runtime'] <=> $b['runtime'],
                default => strcasecmp($a['title'], $b['title']),
            };

            return $cmp * $direction;
        });

        // 4. Pagination
        $total = count($records);
        $slice = array_slice($records, $query->offset(), $query->perPage);
        $cards = array_map(
            fn (array $movie): CardItemDTO => $this->toCardDTO($movie, in_array($movie['id'], $favorites, true)),
            $slice
        );

        return CardPageResult::make(
            items: $cards,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
        );
    }

    /**
     * Toggle a movie ID in the session favorites list.
     */
    public function toggleFavorite(int $id): bool
    {
        $favorites = $this->getFavoriteIds();
        if (in_array($id, $favorites, true)) {
            $favorites = array_values(array_filter($favorites, static fn (int $fav): bool => $fav !== $id));
            session()->put(self::FAVORITES_SESSION_KEY, $favorites);

            return false;
        }

        $favorites[] = $id;
        session()->put(self::FAVORITES_SESSION_KEY, array_values(array_unique($favorites)));

        return true;
    }

    /**
     * Find a raw movie record by ID.
     *
     * @return array{
     *   id: int,
     *   title: string,
     *   director: string,
     *   year: int,
     *   runtime: int,
     *   rating: float,
     *   genre: string,
     *   genre_label: string,
     *   icon: string,
     *   synopsis: string,
     *   tags: list<string>,
     *   featured: bool,
     *   style: Style,
     *   colors: array{0: string, 1: string}
     * }|null
     */
    public function findById(int $id): ?array
    {
        foreach ($this->catalog() as $movie) {
            if ($movie['id'] === $id) {
                return $movie;
            }
        }

        return null;
    }

    /**
     * Build a minimal surgical patch payload reflecting a movie's updated favorite state.
     *
     * @return array<string, mixed>|null
     */
    public function buildFavoritePatch(int $id, bool $isFavorite): ?array
    {
        $movie = $this->findById($id);
        if ($movie === null) {
            return null;
        }

        return [
            'badge' => $isFavorite ? '★ Favorita' : ($movie['featured'] ? 'Destacada' : $movie['genre_label']),
            'badge_style' => $isFavorite ? Style::WARNING : ($movie['featured'] ? Style::PRIMARY : Style::SECONDARY),
            'footer_text' => $isFavorite ? 'En tu lista' : "{$movie['runtime']} min",
            'actions' => $this->buildCardActions($movie['id'], $isFavorite),
        ];
    }

    /**
     * @return list<CardActionDTO>
     */
    private function buildCardActions(int $movieId, bool $isFavorite): array
    {
        return [
            CardActionDTO::make(
                label: 'Ver detalle',
                action: 'view_movie',
                parameters: ['movie_id' => $movieId],
                icon: 'eye',
                style: Style::PRIMARY,
                variant: 'solid',
            ),
            CardActionDTO::make(
                label: $isFavorite ? 'Quitar' : 'Favorita',
                action: 'toggle_favorite',
                parameters: ['movie_id' => $movieId],
                icon: 'heart',
                style: $isFavorite ? Style::WARNING : Style::DEFAULT,
                variant: 'outline',
            ),
            CardActionDTO::make(
                label: 'Compartir enlace',
                action: 'share_movie',
                parameters: ['movie_id' => $movieId],
                icon: 'share',
                style: Style::DEFAULT,
            ),
            CardActionDTO::make(
                label: 'Reportar ficha',
                action: 'report_movie',
                parameters: ['movie_id' => $movieId],
                icon: 'warning',
                style: Style::DANGER,
            ),
        ];
    }

    /**
     * @return list<int>
     */
    public function getFavoriteIds(): array
    {
        $stored = session()->get(self::FAVORITES_SESSION_KEY, [1, 4]);
        if (! is_array($stored)) {
            return [];
        }

        $ids = [];
        foreach ($stored as $val) {
            if (is_numeric($val)) {
                $ids[] = (int) $val;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * @param  array{
     *   id: int,
     *   title: string,
     *   director: string,
     *   year: int,
     *   runtime: int,
     *   rating: float,
     *   genre: string,
     *   genre_label: string,
     *   icon: string,
     *   synopsis: string,
     *   tags: list<string>,
     *   featured: bool,
     *   style: Style,
     *   colors: array{0: string, 1: string}
     * }  $movie
     */
    private function toCardDTO(array $movie, bool $isFavorite): CardItemDTO
    {
        return CardItemDTO::make(
            id: $movie['id'],
            title: $movie['title'],
            subtitle: "Dir. {$movie['director']}",
            eyebrow: "{$movie['genre_label']} • {$movie['year']}",
            description: $movie['synopsis'],
            image: $this->buildPosterDataUri($movie['title'], $movie['icon'], $movie['colors'][0], $movie['colors'][1]),
            imageAlt: "Poster de {$movie['title']}",
            icon: $movie['icon'],
            badge: $isFavorite ? '★ Favorita' : ($movie['featured'] ? 'Destacada' : $movie['genre_label']),
            badgeStyle: $isFavorite ? Style::WARNING : ($movie['featured'] ? Style::PRIMARY : Style::SECONDARY),
            metric: sprintf('★ %.1f', $movie['rating']),
            metricLabel: '/ 10 IMDb',
            metricStyle: $movie['rating'] >= 8.7 ? Style::SUCCESS : Style::INFO,
            attributes: [
                ['icon' => 'calendar', 'label' => 'Año', 'value' => (string) $movie['year']],
                ['icon' => 'clock', 'label' => 'Duración', 'value' => "{$movie['runtime']} min"],
            ],
            tags: $movie['tags'],
            footerText: $isFavorite ? 'En tu lista' : "{$movie['runtime']} min",
            actions: $this->buildCardActions($movie['id'], $isFavorite),
            clickAction: 'view_movie',
            clickParameters: ['movie_id' => $movie['id']],
            style: $movie['style'],
            featured: $movie['featured'],
            meta: [
                'genre' => $movie['genre'],
                'year' => $movie['year'],
                'rating' => $movie['rating'],
                'favorite' => $isFavorite,
            ],
        );
    }

    private function buildPosterDataUri(string $title, string $emoji, string $c1, string $c2): string
    {
        $safeTitle = htmlspecialchars(mb_strimwidth($title, 0, 26, '…'), ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $svg = <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 640 360">
  <defs>
    <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="{$c1}"/>
      <stop offset="100%" stop-color="{$c2}"/>
    </linearGradient>
  </defs>
  <rect width="640" height="360" fill="url(#g)"/>
  <circle cx="540" cy="60" r="140" fill="#ffffff" fill-opacity="0.08"/>
  <circle cx="90" cy="320" r="110" fill="#000000" fill-opacity="0.14"/>
  <text x="320" y="175" text-anchor="middle" font-size="74" font-family="system-ui, sans-serif">{$emoji}</text>
  <text x="320" y="250" text-anchor="middle" fill="#ffffff" fill-opacity="0.92" font-size="24" font-weight="700" font-family="system-ui, -apple-system, sans-serif">{$safeTitle}</text>
</svg>
SVG;

        return 'data:image/svg+xml;charset=UTF-8,'.rawurlencode($svg);
    }

    /**
     * @return list<array{
     *   id: int,
     *   title: string,
     *   director: string,
     *   year: int,
     *   runtime: int,
     *   rating: float,
     *   genre: string,
     *   genre_label: string,
     *   icon: string,
     *   synopsis: string,
     *   tags: list<string>,
     *   featured: bool,
     *   style: Style,
     *   colors: array{0: string, 1: string}
     * }>
     */
    private function catalog(): array
    {
        return [
            [
                'id' => 1,
                'title' => 'Interstellar',
                'director' => 'Christopher Nolan',
                'year' => 2014,
                'runtime' => 169,
                'rating' => 8.7,
                'genre' => 'sci-fi',
                'genre_label' => 'Ciencia Ficción',
                'icon' => '🚀',
                'synopsis' => 'Un grupo de exploradores viaja a través de un agujero de gusano en el espacio para asegurar la supervivencia de la humanidad.',
                'tags' => ['IMAX', 'Espacio', 'Premiada', '4K'],
                'featured' => true,
                'style' => Style::PRIMARY,
                'colors' => ['#0f2027', '#2c5364'],
            ],
            [
                'id' => 2,
                'title' => 'Inception',
                'director' => 'Christopher Nolan',
                'year' => 2010,
                'runtime' => 148,
                'rating' => 8.8,
                'genre' => 'sci-fi',
                'genre_label' => 'Ciencia Ficción',
                'icon' => '🌀',
                'synopsis' => 'Un ladrón experto en extraer secretos del subconsciente recibe la misión inversa: implantar una idea en la mente de un empresario.',
                'tags' => ['Thriller', 'Premiada', '4K'],
                'featured' => true,
                'style' => Style::INFO,
                'colors' => ['#1c2841', '#3b6998'],
            ],
            [
                'id' => 3,
                'title' => 'The Matrix',
                'director' => 'Lana & Lilly Wachowski',
                'year' => 1999,
                'runtime' => 136,
                'rating' => 8.7,
                'genre' => 'sci-fi',
                'genre_label' => 'Ciencia Ficción',
                'icon' => '🕶️',
                'synopsis' => 'Un programador descubre que la realidad que conoce es una simulación creada por máquinas y se une a la rebelión humana.',
                'tags' => ['Clásico', 'Cyberpunk', '4K'],
                'featured' => false,
                'style' => Style::SUCCESS,
                'colors' => ['#0b2e13', '#1d6f42'],
            ],
            [
                'id' => 4,
                'title' => 'Dune: Parte Dos',
                'director' => 'Denis Villeneuve',
                'year' => 2024,
                'runtime' => 166,
                'rating' => 8.6,
                'genre' => 'sci-fi',
                'genre_label' => 'Ciencia Ficción',
                'icon' => '🏜️',
                'synopsis' => 'Paul Atreides se une a los Fremen en Arrakis mientras emprende un viaje espiritual y marcial hacia su destino.',
                'tags' => ['IMAX', 'Estreno', 'Espacio', '4K'],
                'featured' => true,
                'style' => Style::WARNING,
                'colors' => ['#6f3b11', '#c77d2b'],
            ],
            [
                'id' => 5,
                'title' => 'El Padrino',
                'director' => 'Francis Ford Coppola',
                'year' => 1972,
                'runtime' => 175,
                'rating' => 9.2,
                'genre' => 'drama',
                'genre_label' => 'Drama',
                'icon' => '🎩',
                'synopsis' => 'El envejecido patriarca de una dinastía del crimen organizado transfiere el control de su imperio clandestino a su hijo menor.',
                'tags' => ['Clásico', 'Premiada'],
                'featured' => true,
                'style' => Style::DEFAULT,
                'colors' => ['#2b1b17', '#5c3a2e'],
            ],
            [
                'id' => 6,
                'title' => 'Parásitos',
                'director' => 'Bong Joon-ho',
                'year' => 2019,
                'runtime' => 132,
                'rating' => 8.5,
                'genre' => 'thriller',
                'genre_label' => 'Thriller',
                'icon' => '🏠',
                'synopsis' => 'La codicia y la discriminación de clase amenazan la simbiosis recién formada entre la adinerada familia Park y el clan Kim.',
                'tags' => ['Premiada', 'Thriller'],
                'featured' => false,
                'style' => Style::DANGER,
                'colors' => ['#3d131a', '#7c2535'],
            ],
            [
                'id' => 7,
                'title' => 'El Viaje de Chihiro',
                'director' => 'Hayao Miyazaki',
                'year' => 2001,
                'runtime' => 125,
                'rating' => 8.6,
                'genre' => 'animation',
                'genre_label' => 'Animación',
                'icon' => '🐉',
                'synopsis' => 'Durante la mudanza de su familia, una niña de diez años entra en un mundo gobernado por dioses, brujas y espíritus.',
                'tags' => ['Premiada', 'Clásico', 'Familiar'],
                'featured' => true,
                'style' => Style::INFO,
                'colors' => ['#1a4a5e', '#388e9c'],
            ],
            [
                'id' => 8,
                'title' => 'Spider-Man: Into the Spider-Verse',
                'director' => 'Persichetti, Ramsey & Rothman',
                'year' => 2018,
                'runtime' => 117,
                'rating' => 8.4,
                'genre' => 'animation',
                'genre_label' => 'Animación',
                'icon' => '🕷️',
                'synopsis' => 'Miles Morales se convierte en el Spider-Man de su universo y cruza caminos con contrapartes de otras dimensiones.',
                'tags' => ['Premiada', 'Familiar', '4K'],
                'featured' => false,
                'style' => Style::DANGER,
                'colors' => ['#7b112c', '#1c3b70'],
            ],
            [
                'id' => 9,
                'title' => 'Gladiator',
                'director' => 'Ridley Scott',
                'year' => 2000,
                'runtime' => 155,
                'rating' => 8.5,
                'genre' => 'action',
                'genre_label' => 'Acción',
                'icon' => '⚔️',
                'synopsis' => 'Un general romano traicionado busca justicia contra el corrupto emperador que asesinó a su familia y lo condenó a la esclavitud.',
                'tags' => ['Clásico', 'Premiada'],
                'featured' => false,
                'style' => Style::WARNING,
                'colors' => ['#4a3519', '#946b2d'],
            ],
            [
                'id' => 10,
                'title' => 'Mad Max: Fury Road',
                'director' => 'George Miller',
                'year' => 2015,
                'runtime' => 120,
                'rating' => 8.1,
                'genre' => 'action',
                'genre_label' => 'Acción',
                'icon' => '🔥',
                'synopsis' => 'En un páramo postapocalíptico, una mujer se rebela contra un tirano en busca de su tierra natal con ayuda de un vagabundo.',
                'tags' => ['IMAX', 'Premiada', '4K'],
                'featured' => false,
                'style' => Style::DANGER,
                'colors' => ['#7c2d12', '#c2410c'],
            ],
            [
                'id' => 11,
                'title' => 'The Dark Knight',
                'director' => 'Christopher Nolan',
                'year' => 2008,
                'runtime' => 152,
                'rating' => 9.0,
                'genre' => 'action',
                'genre_label' => 'Acción',
                'icon' => '🦇',
                'synopsis' => 'Cuando el Joker desata el caos en Gotham, Batman enfrenta una de las mayores pruebas psicológicas y físicas de su lucha.',
                'tags' => ['IMAX', 'Premiada', 'Clásico'],
                'featured' => true,
                'style' => Style::PRIMARY,
                'colors' => ['#111827', '#374151'],
            ],
            [
                'id' => 12,
                'title' => 'Arrival',
                'director' => 'Denis Villeneuve',
                'year' => 2016,
                'runtime' => 116,
                'rating' => 7.9,
                'genre' => 'sci-fi',
                'genre_label' => 'Ciencia Ficción',
                'icon' => '🛸',
                'synopsis' => 'Una lingüista trabaja con el ejército para comunicarse con visitantes extraterrestres tras la llegada de doce naves.',
                'tags' => ['Espacio', 'Premiada'],
                'featured' => false,
                'style' => Style::INFO,
                'colors' => ['#27374d', '#526d82'],
            ],
            [
                'id' => 13,
                'title' => 'Whiplash',
                'director' => 'Damien Chazelle',
                'year' => 2014,
                'runtime' => 106,
                'rating' => 8.5,
                'genre' => 'drama',
                'genre_label' => 'Drama',
                'icon' => '🥁',
                'synopsis' => 'Un joven y prometedor baterista de jazz se inscribe en un conservatorio donde un exigente instructor lo lleva al límite.',
                'tags' => ['Premiada', 'Música'],
                'featured' => false,
                'style' => Style::WARNING,
                'colors' => ['#3b2f12', '#8f6b1e'],
            ],
            [
                'id' => 14,
                'title' => 'Seven',
                'director' => 'David Fincher',
                'year' => 1995,
                'runtime' => 127,
                'rating' => 8.6,
                'genre' => 'thriller',
                'genre_label' => 'Thriller',
                'icon' => '🕵️',
                'synopsis' => 'Dos detectives, un novato y un veterano, persiguen a un asesino serial que utiliza los siete pecados capitales como motivo.',
                'tags' => ['Clásico', 'Thriller'],
                'featured' => false,
                'style' => Style::SECONDARY,
                'colors' => ['#1f2933', '#3e4c59'],
            ],
            [
                'id' => 15,
                'title' => 'Blade Runner 2049',
                'director' => 'Denis Villeneuve',
                'year' => 2017,
                'runtime' => 164,
                'rating' => 8.0,
                'genre' => 'sci-fi',
                'genre_label' => 'Ciencia Ficción',
                'icon' => '🌆',
                'synopsis' => 'Un nuevo blade runner descubre un secreto largamente enterrado que amenaza con sumir en el caos lo que queda de la sociedad.',
                'tags' => ['Cyberpunk', 'IMAX', '4K'],
                'featured' => false,
                'style' => Style::WARNING,
                'colors' => ['#5c2a18', '#b5542c'],
            ],
            [
                'id' => 16,
                'title' => 'Forrest Gump',
                'director' => 'Robert Zemeckis',
                'year' => 1994,
                'runtime' => 142,
                'rating' => 8.8,
                'genre' => 'drama',
                'genre_label' => 'Drama',
                'icon' => '🏃',
                'synopsis' => 'Las décadas de historia estadounidense se despliegan desde la perspectiva de un hombre de Alabama con un gran corazón.',
                'tags' => ['Clásico', 'Premiada', 'Familiar'],
                'featured' => false,
                'style' => Style::SUCCESS,
                'colors' => ['#1c4532', '#2f855a'],
            ],
            [
                'id' => 17,
                'title' => 'Oppenheimer',
                'director' => 'Christopher Nolan',
                'year' => 2023,
                'runtime' => 180,
                'rating' => 8.3,
                'genre' => 'drama',
                'genre_label' => 'Drama',
                'icon' => '⚛️',
                'synopsis' => 'La historia del físico teórico J. Robert Oppenheimer y su papel decisivo en el desarrollo del Proyecto Manhattan.',
                'tags' => ['IMAX', 'Estreno', 'Premiada'],
                'featured' => true,
                'style' => Style::DANGER,
                'colors' => ['#451a03', '#b45309'],
            ],
            [
                'id' => 18,
                'title' => 'Coco',
                'director' => 'Lee Unkrich',
                'year' => 2017,
                'runtime' => 105,
                'rating' => 8.4,
                'genre' => 'animation',
                'genre_label' => 'Animación',
                'icon' => '🎸',
                'synopsis' => 'El aspirante a músico Miguel viaja a la Tierra de los Muertos para descubrir la verdadera historia detrás de su familia.',
                'tags' => ['Premiada', 'Familiar', 'Música'],
                'featured' => false,
                'style' => Style::PRIMARY,
                'colors' => ['#3b0764', '#9333ea'],
            ],
        ];
    }
}
