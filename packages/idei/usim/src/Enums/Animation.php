<?php

namespace Idei\Usim\Enums;

/**
 * Animation options for UI components
 */
enum Animation: string
{
    case NONE = 'none';
    case FADE = 'fade';
    case SLIDE = 'slide';
    case BOUNCE = 'bounce';
    case PULSE = 'pulse';
}
