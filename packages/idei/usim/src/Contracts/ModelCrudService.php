<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Aggregated contract for generic Eloquent CRUD/query services.
 *
 * @template TModel of Model
 * @extends ModelCreateService<TModel>
 * @extends ModelQueryableService<TModel>
 * @extends ModelUpdateService<TModel>
 * @extends ModelDeleteService
 */
interface ModelCrudService extends ModelCreateService, ModelQueryableService, ModelUpdateService, ModelDeleteService
{
}
