<?php

namespace Idei\Usim\Enums;

/**
 * Screen visibility options
 */
enum Visibility: string
{
    case PUBLIC = 'public';
    case AUTHENTICATED = 'authenticated';
    case GUEST = 'guest';
}
