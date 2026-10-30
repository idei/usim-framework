<?php

namespace Idei\Usim\Enums;

/**
 * Text transform options for text-based UI components (e.g., labels, buttons)
 */
enum TextTransform: string
{
    case NONE = 'none';
    case UPPERCASE = 'uppercase';
    case LOWERCASE = 'lowercase';
    case CAPITALIZE = 'capitalize';
}
