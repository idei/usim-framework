<?php

namespace Idei\Usim\Enums;

/**
 * Visual appearance options for container components.
 *
 * Expresses structural emphasis and hierarchy without coupling the backend
 * to concrete colors or CSS rules.
 */
enum ContainerAppearance: string
{
    case PLAIN = 'plain';
    case SUBTLE = 'subtle';
    case CARD = 'card';
    case ELEVATED = 'elevated';
    case HIGHLIGHT = 'highlight';
    case CONTRAST = 'contrast';
}
