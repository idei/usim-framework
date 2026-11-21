<?php

// @usim: feature="core", type="layout"

namespace App\UI\Layouts;

use Idei\Usim\Layout\AbstractLayout;

/**
 * VS Code-inspired Workbench Layout.
 *
 * Declaratively defined via sibling `workbench-layout.layout.yaml`:
 * - Fixed 52px Activity Bar (`sidebar` / `activity_bar`, outside any Split)
 * - Left resizable/collapsible Split (`left_panel`, collapsed to 0px by default; hosts submenu trees or contextual screen panels)
 * - Center main content slot (`main` / `content`)
 * - Right resizable/collapsible Split (`right_panel`, collapsed to 0px by default)
 * - Bottom Status Bar slot (`status_bar`, full width)
 */
class WorkbenchLayout extends AbstractLayout
{
}
