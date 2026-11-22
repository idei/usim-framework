<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Input;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;

/**
 * Interactive Demo for Declarative Screen Slot Projections (`slots:` in `.layout.yaml`).
 *
 * A single Screen class projects:
 * - Filters panel into `left_panel` (`open: true`, `size: 270px`, `fallback: inline_top`)
 * - Inspector panel into `right_panel` (`open: false`, `size: 340px`, `fallback: ignore`)
 * - Live counter into `status_bar` (hiding default `lbl_status_message` while active)
 * - Quick action button into `status_bar_right` (preserving dev `btn_reset`)
 */
class SlotProjectionDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected NewLabel $lbl_active_summary;

    protected Input $input_filter;

    protected NewLabel $lbl_inspector_title;

    protected NewLabel $lbl_inspector_body;

    protected Input $input_note;

    protected NewLabel $lbl_status_counter;

    protected int $state_ping_count = 0;

    protected string $state_last_filter = '';

    protected function postLoadUI(): void
    {
        if ($this->state_last_filter !== '') {
            $this->lbl_active_summary->text(
                $this->t('.summary.filtered', ['query' => $this->state_last_filter])
            );
        }
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onApplyFilter(array $params): void
    {
        $raw = $params['input_filter'] ?? $this->input_filter->get('value');
        $query = is_string($raw) ? trim($raw) : '';
        $this->state_last_filter = $query;

        $summary = $query !== ''
            ? $this->t('.summary.filtered', ['query' => $query])
            : $this->t('.summary.cleared');

        $this->lbl_active_summary->text($summary);
        $this->lbl_status_counter->text($summary);
    }

    public function onToggleLeftPanel(): void
    {
        $this->toggleSlot('left_panel');
    }

    public function onCloseLeftPanel(): void
    {
        $this->closeSlot('left_panel');
    }

    public function onOpenInspector(): void
    {
        $subject = $this->state_last_filter !== '' ? $this->state_last_filter : 'Default Selection';
        $this->lbl_inspector_title->text($this->t('.inspector.heading', ['subject' => $subject]));
        $this->lbl_inspector_body->text($this->t('.inspector.body', ['subject' => $subject]));
        $this->openSlot('right_panel');
    }

    public function onCloseRightPanel(): void
    {
        $this->closeSlot('right_panel');
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public function onSaveNote(array $params): void
    {
        $raw = $params['input_note'] ?? $this->input_note->get('value');
        $note = is_string($raw) && trim($raw) !== '' ? trim($raw) : 'OK';

        $this->lbl_inspector_body->text($this->t('.inspector.note_saved', ['note' => $note]));
        $this->lbl_status_counter->text($this->t('.status.note_saved', ['note' => $note]));
        $this->toast($this->t('.inspector.note_saved', ['note' => $note]), 'success');
    }

    public function onPingStatus(): void
    {
        $this->state_ping_count++;
        $this->lbl_status_counter->text(
            $this->t('.status.pinged', ['count' => $this->state_ping_count])
        );
    }
}
