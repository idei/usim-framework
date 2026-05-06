<?php

namespace App\Services\Movie;

use App\Models\Movie;
use Idei\Usim\Support\EloquentListingService;

/**
 * Read-only service for movies demo. No write operations are supported.
 */
class MovieListingService extends EloquentListingService
{
	protected string $modelClass = Movie::class;

	protected array $with = ['genre'];

	/**
	 * @return array<string, string>
	 */
	protected function searchableFields(): array
	{
		return [
			'title' => 'title',
			'cast_members' => 'cast_members',
			'genre_name' => 'genre.name',
		];
	}

	/**
	 * @return array<string, array{path: string, operator?: string, cast?: 'int'|'float'|'bool'|'string'}>
	 */
	protected function filterableFields(): array
	{
		return [
			'genre_name' => [
				'path' => 'genre.name',
				'operator' => 'like'
			],
			'release_year' => [
				'path' => 'release_year',
				'operator' => '=',
				'cast' => 'int'
			],
		];
	}

	/**
	 * @return array<string, string>
	 */
	protected function sortableFields(): array
	{
		return [
			'title' => 'title',
			'release_year' => 'release_year',
			'genre_name' => 'genre.name',
			'created_at' => 'created_at',
		];
	}

	/**
	 * @return array{field: string, direction: 'asc'|'desc'}
	 */
	protected function defaultSort(): array
	{
		return [
			'field' => 'title',
			'direction' => 'asc',
		];
	}
}
