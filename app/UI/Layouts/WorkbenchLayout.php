<?php

// @usim: feature="core", type="layout"

namespace App\UI\Layouts;

use Idei\Usim\Layout\AbstractLayout;

/**
 * VS Code-inspired Workbench Layout.
 *
 * Declaratively defined via sibling `workbench-layout.layout.yaml`:
 * - Left resizable/collapsible Split (`sidebar`, collapses to 52px Activity Bar)
 * - Center main content slot (`main` / `content`)
 * - Right resizable/collapsible Split (`right_panel`, collapsed to 0px by default)
 * - Bottom Status Bar slot (`status_bar`)
 */
class WorkbenchLayout extends AbstractLayout
{
}
