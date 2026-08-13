<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated contract for model read/query capabilities.
 *
 * @template TModel of Model
 * @extends ModelReadService<TModel>
 * @extends ModelSearchService<TModel>
 * @extends ModelFilterService<TModel>
 * @extends ModelSortService<TModel>
 */
interface ModelQueryableService extends ModelReadService, ModelSearchService, ModelFilterService, ModelSortService, ModelStructureService
{
}
