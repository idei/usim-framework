<?php

namespace Idei\Usim\Enums;

/**
 * Variant options for text-based UI components (e.g., labels, buttons)
 */
enum Variant: string
{
    case TEXT = 'text';
    case BADGE = 'badge';
    case CHIP = 'chip';
    case TAG = 'tag';
    case PILL = 'pill';
    case OUTLINED = 'outlined';

}
