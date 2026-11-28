<?php

declare(strict_types=1);

namespace Idei\Usim\Screens;

use Idei\Usim\Concerns\InteractsWithMasterDetail;
use Idei\Usim\Screen;

/**
 * Convenience abstract base class for Master-Detail USIM Screens.
 *
 * Concrete screens may either extend `MasterDetailScreen` directly or extend
 * `Screen` and compose `use InteractsWithMasterDetail;`.
 */
abstract class MasterDetailScreen extends Screen
{
    use InteractsWithMasterDetail;

    public static ?string $layout = 'workbench';
}
