<?php

namespace Idei\Usim\Layout;

/**
 * Built-in VS Code-inspired Workbench Layout.
 *
 * Declaratively defined via sibling `workbench-layout.layout.yaml`:
 * - Fixed 52px Activity Bar (`sidebar` / `activity_bar` / `menu`, outside any Split)
 * - Left resizable/collapsible Split (`left_panel` / `left` / `explorer`, collapsed to 0px by default)
 * - Center sticky header slot (`top_bar` / `header` / `toolbar`, hidden by default; auto height above `main`)
 * - Center main content slot (`main` / `content`)
 * - Center bottom resizable/collapsible vertical Split (`bottom_panel` / `bottom` / `panel` / `console` / `terminal`, collapsed to 0px by default)
 * - Right resizable/collapsible Split (`right_panel` / `right` / `inspector`, collapsed to 0px by default)
 * - Bottom Status Bar slot (`status_bar`, full width)
 *
 * Applications may use this class directly (or via `$layout = 'workbench'`) or extend it
 * in `App\UI\Layouts\WorkbenchLayout` with an optional sibling `workbench-layout.layout.yaml` override.
 */
class WorkbenchLayout extends AbstractLayout {}
