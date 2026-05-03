<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated contract for generic Eloquent CRUD/query services.
 *
 * @template TModel of Model
 * @extends ModelCreateService<TModel>
 * @extends ModelReadService<TModel>
 * @extends ModelUpdateService<TModel>
 * @extends ModelDeleteService
 * @extends ModelSearchService<TModel>
 * @extends ModelFilterService<TModel>
 * @extends ModelSortService<TModel>
 * @extends ModelStructureService
 */
interface ModelCrudService extends ModelCreateService, ModelReadService, ModelUpdateService, ModelDeleteService, ModelSearchService, ModelFilterService, ModelSortService, ModelStructureService
{
}
