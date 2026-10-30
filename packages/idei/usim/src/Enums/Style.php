<?php

namespace Idei\Usim\Enums;

/**
 * Style options for text-based UI components (e.g., labels, buttons)
 */
enum Style: string
{
    case DEFAULT = 'default';
    case PRIMARY = 'primary';
    case SECONDARY = 'secondary';
    case SUCCESS = 'success';
    case DANGER = 'danger';
    case WARNING = 'warning';
    case INFO = 'info';
    case MUTED = 'muted';
}
