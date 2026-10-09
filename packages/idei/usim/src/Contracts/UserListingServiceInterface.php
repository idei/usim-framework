<?php

namespace Idei\Usim\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * Contract for listing, searching, filtering, and paginating users.
 *
 * @template TUser of Model
 *
 * @extends ModelQueryableService<TUser>
 */
interface UserListingServiceInterface extends ModelQueryableService {}
