<?php

namespace Idei\Usim\Contracts\CardGrid;

use Idei\Usim\DTOs\CardPageResult;
use Idei\Usim\DTOs\CardQuery;

/**
 * Contract for services that supply paginated, searchable, sortable,
 * and filterable card items for a CardGrid component.
 */
interface CardListable
{
    /**
     * Execute a read-only query and return a paginated page of cards.
     */
    public function query(CardQuery $query): CardPageResult;
}
