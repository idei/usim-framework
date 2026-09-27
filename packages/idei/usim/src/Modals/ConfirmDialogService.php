<?php

namespace Idei\Usim\Modals;

use Idei\Usim\Contracts\UIModal;
use Idei\Usim\Screen;

/**
 * Confirm Dialog Service (Legacy Adapter)
 *
 * Backward-compatible helper service to generate modal dialogs.
 * Delegates execution to the Screen-based ConfirmDialog implementation.
 */
class ConfirmDialogService implements UIModal
{
    /**
     * Open a modal dialog.
     *
     * @param  mixed  ...$params
     */
    public static function open(...$params): void
    {
        /** @var Screen|null $caller */
        $caller = null;
        if (isset($params[0]) && $params[0] instanceof Screen) {
            /** @var Screen $caller */
            $caller = array_shift($params);
        } elseif (isset($params['caller']) && $params['caller'] instanceof Screen) {
            /** @var Screen $caller */
            $caller = $params['caller'];
            unset($params['caller']);
        }

        ConfirmDialog::open($caller, ...$params);
    }
}
