<?php

// @usim: feature="core", type="layout"

namespace App\UI\Layouts;

use Idei\Usim\Layout\WorkbenchLayout as BaseWorkbenchLayout;

/**
 * VS Code-inspired Workbench Layout.
 *
 * Declaratively defined via sibling `workbench-layout.layout.yaml`:
 * - Fixed 52px Activity Bar (`sidebar` / `activity_bar`, outside any Split)
 * - Left resizable/collapsible Split (`left_panel`, collapsed to 0px by default; hosts submenu trees or contextual screen panels)
 * - Center sticky header slot (`top_bar` / `header` / `toolbar`, hidden by default; auto height above `main`)
 * - Center main content slot (`main` / `content`)
 * - Center bottom resizable/collapsible vertical Split (`bottom_panel` / `bottom` / `panel` / `console` / `terminal`, collapsed to 0px by default)
 * - Right resizable/collapsible Split (`right_panel`, collapsed to 0px by default)
 * - Bottom Status Bar slot (`status_bar`, full width)
 */
class WorkbenchLayout extends BaseWorkbenchLayout
{
}
