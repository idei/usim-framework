<?php

declare(strict_types=1);

namespace Idei\Usim\Concerns;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\ItemList;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Components\Select;
use Idei\Usim\Contracts\ItemList\Deletable as ItemListDeletable;
use Idei\Usim\Contracts\ItemList\Listable;
use Idei\Usim\Contracts\MasterDetail\CreatableDetail;
use Idei\Usim\Contracts\MasterDetail\DeletableDetail;
use Idei\Usim\Contracts\MasterDetail\Detailable;
use Idei\Usim\Contracts\MasterDetail\UpdatableDetail;
use Idei\Usim\DTOs\ListItemDTO;
use Idei\Usim\Enums\DialogType;
use Idei\Usim\Modals\ConfirmDialog;
use Idei\Usim\Screen;

/**
 * Reusable Octane-safe Master-Detail state machine for USIM Screens.
 *
 * Coordinates:
 * - Left-panel `ItemList` single selection (`$lst_items`)
 * - Central panel empty state (`$cnt_empty_state`) vs detail container (`$cnt_detail_form`)
 * - Direct-edit, create-new, reset/cancel, and confirmed deletion workflows
 * - Post-mutation synchronization of `ItemList` items and active selection without ghost state
 *
 * @mixin Screen
 */
trait InteractsWithMasterDetail
{
    protected ItemList $lst_items;

    protected Container $cnt_empty_state;

    protected Container $cnt_detail_form;

    protected NewLabel $lbl_detail_heading;

    /**
     * Current Master-Detail mode ('empty' | 'viewing' | 'editing' | 'creating').
     */
    protected string $state_detail_mode = 'empty';

    /**
     * Currently active entity identifier, or null when empty / creating.
     */
    protected int|string|null $state_selected_id = null;

    /**
     * Return the domain service backing this Master-Detail screen.
     *
     * @return Detailable<object>
     */
    abstract protected function detailService(): Detailable;

    /**
     * Hydrate the central detail components from the resolved entity DTO.
     */
    abstract protected function hydrateDetailFields(object $dto): void;

    /**
     * Reset detail form inputs to their blank/default values when entering create mode.
     */
    protected function resetDetailFields(): void
    {
        // Overridden by screens whose service implements CreatableDetail.
    }

    /**
     * Clear validation error messages across detail form components.
     */
    protected function clearDetailValidationErrors(): void
    {
        // Overridden by editable screens to clear field errors on selection/reset.
    }

    /**
     * Optional hook to populate relational option components (such as 1:N Select dropdowns).
     */
    protected function initializeDetailRelations(): void
    {
        // Overridden by screens with 1:N BelongsTo Select components.
    }

    /**
     * Synchronize relation options and panel visibility on initial load or full reload.
     */
    protected function postLoadUI(): void
    {
        $this->initializeDetailRelations();

        if ($this->state_detail_mode === 'creating' && $this->supportsCreateDetail()) {
            $this->lst_items->select([]);
            $this->syncMasterDetailVisibility();

            return;
        }

        if ($this->state_selected_id !== null) {
            $dto = $this->detailService()->findDetail($this->state_selected_id);
            if ($dto !== null) {
                $this->lst_items->select([$this->state_selected_id]);
                $this->hydrateDetailFields($dto);
                $this->state_detail_mode = $this->supportsUpdateDetail() ? 'editing' : 'viewing';
                $this->syncMasterDetailVisibility($dto);

                return;
            }
        }

        $this->enterEmptyDetailState();
    }

    /**
     * Handle `on_select` from the left-panel ItemList.
     *
     * @param  array<string, mixed>  $params
     */
    public function onItemSelected(array $params): void
    {
        $selectedId = $this->extractFirstSelectedId($params['selected'] ?? []);
        if ($selectedId === null) {
            $this->enterEmptyDetailState();

            return;
        }

        $dto = $this->detailService()->findDetail($selectedId);
        if ($dto === null) {
            $this->lst_items->refreshFromService();
            $this->enterEmptyDetailState();
            $this->toast($this->t('.messages.not_found'), 'warning');

            return;
        }

        $this->state_selected_id = $selectedId;
        $this->state_detail_mode = $this->supportsUpdateDetail() ? 'editing' : 'viewing';
        $this->lst_items->select([$selectedId]);
        $this->clearDetailValidationErrors();
        $this->initializeDetailRelations();
        $this->hydrateDetailFields($dto);
        $this->syncMasterDetailVisibility($dto);
    }

    /**
     * Enter create mode in the central detail form (`+ New` button).
     */
    public function onStartCreate(): void
    {
        if (! $this->supportsCreateDetail()) {
            return;
        }

        $this->state_selected_id = null;
        $this->state_detail_mode = 'creating';
        $this->lst_items->select([]);
        $this->clearDetailValidationErrors();
        $this->initializeDetailRelations();
        $this->resetDetailFields();
        $this->syncMasterDetailVisibility();
    }

    /**
     * Discard unsaved changes (reloading the active item) or cancel create mode.
     */
    public function onCancelDetail(): void
    {
        $this->clearDetailValidationErrors();

        if ($this->state_detail_mode === 'creating' || $this->state_selected_id === null) {
            $this->enterEmptyDetailState();

            return;
        }

        $dto = $this->detailService()->findDetail($this->state_selected_id);
        if ($dto === null) {
            $this->enterEmptyDetailState();

            return;
        }

        $this->hydrateDetailFields($dto);
        $this->syncMasterDetailVisibility($dto);
        $this->toast($this->t('.messages.changes_discarded'), 'info');
    }

    /**
     * Persist a validated DTO (creating when in `creating` mode, updating when in `editing` mode).
     */
    protected function persistDetailDto(object $dto): void
    {
        $service = $this->detailService();

        if ($this->state_detail_mode === 'creating') {
            if (! ($service instanceof CreatableDetail)) {
                return;
            }

            $created = $service->createDetail($dto);
            $createdId = $this->extractEntityId($created);

            $this->lst_items->refreshFromService();
            $this->clearDetailValidationErrors();

            if ($createdId !== null) {
                $this->state_selected_id = $createdId;
                $this->state_detail_mode = $this->supportsUpdateDetail() ? 'editing' : 'viewing';
                $this->lst_items->select([$createdId]);
                $this->hydrateDetailFields($created);
                $this->syncMasterDetailVisibility($created);
            } else {
                $this->enterEmptyDetailState();
            }

            $this->toast($this->t('.messages.created'), 'success');

            return;
        }

        if ($this->state_selected_id === null || ! ($service instanceof UpdatableDetail)) {
            return;
        }

        $updated = $service->updateDetail($this->state_selected_id, $dto);
        if ($updated === null) {
            $this->lst_items->refreshFromService();
            $this->enterEmptyDetailState();
            $this->toast($this->t('.messages.not_found'), 'warning');

            return;
        }

        $updatedId = $this->extractEntityId($updated) ?? $this->state_selected_id;
        $this->state_selected_id = $updatedId;
        $this->lst_items->refreshFromService()->select([$updatedId]);
        $this->clearDetailValidationErrors();
        $this->hydrateDetailFields($updated);
        $this->syncMasterDetailVisibility($updated);

        $this->toast($this->t('.messages.updated'), 'success');
    }

    /**
     * Open confirmation modal before deleting the currently selected item.
     */
    public function onRequestDelete(): void
    {
        if ($this->state_selected_id === null || ! $this->supportsDeleteDetail()) {
            return;
        }

        $item = $this->lst_items->findItem($this->state_selected_id);
        $label = is_array($item) && isset($item['label']) && is_string($item['label'])
            ? $item['label']
            : (string) $this->state_selected_id;

        ConfirmDialog::open(
            caller: $this,
            type: DialogType::CONFIRM,
            title: $this->t('.confirm_delete.title'),
            message: $this->t('.confirm_delete.message', ['item' => $label]),
            confirmAction: 'confirm_delete',
            cancelAction: 'cancel_delete',
        );
    }

    /**
     * Execute deletion after user confirms in ConfirmDialog.
     */
    public function onConfirmDelete(): void
    {
        $this->closeModal();

        $targetId = $this->state_selected_id;
        if ($targetId === null) {
            return;
        }

        $deleted = $this->performServiceDelete($targetId);
        $this->lst_items->refreshFromService();

        if (! $deleted) {
            $this->toast($this->t('.messages.not_found'), 'warning');
            $this->enterEmptyDetailState();

            return;
        }

        $this->enterEmptyDetailState();
        $this->toast($this->t('.messages.deleted'), 'success');
    }

    /**
     * Close ConfirmDialog when deletion is cancelled.
     */
    public function onCancelDelete(): void
    {
        $this->closeModal();
    }

    /**
     * Populate a `Select` component's options from any `Listable` service.
     */
    protected function loadSelectOptionsFromService(Select $select, Listable $service): void
    {
        /** @var list<array<string, mixed>> $options */
        $options = array_values(
            $service->list()
                ->map(static fn (ListItemDTO $item): array => [
                    'value' => (string) $item->id,
                    'label' => $item->label,
                ])
                ->all()
        );

        $select->options($options);
    }

    /**
     * Resolve a human-readable heading label for the given detail DTO.
     */
    protected function resolveDetailItemTitle(object $dto): string
    {
        foreach (['name', 'title', 'label', 'code', 'sku', 'email'] as $candidate) {
            if (property_exists($dto, $candidate)) {
                /** @var mixed $val */
                $val = $dto->{$candidate};
                if (is_scalar($val) && trim((string) $val) !== '') {
                    return trim((string) $val);
                }
            }
        }

        $id = $this->extractEntityId($dto) ?? $this->state_selected_id;

        return $id !== null ? '#'.$id : '';
    }

    protected function enterEmptyDetailState(): void
    {
        $this->state_selected_id = null;
        $this->state_detail_mode = 'empty';
        $this->lst_items->select([]);
        $this->clearDetailValidationErrors();
        $this->syncMasterDetailVisibility();
    }

    protected function syncMasterDetailVisibility(?object $dto = null): void
    {
        $isEmpty = $this->state_detail_mode === 'empty';

        $this->cnt_empty_state->visible($isEmpty);
        $this->cnt_detail_form->visible(! $isEmpty);

        $deleteButton = $this->resolveOptionalButton('btn_delete');
        if ($deleteButton !== null) {
            $canDeleteCurrent = ! $isEmpty
                && $this->state_detail_mode !== 'creating'
                && $this->state_selected_id !== null
                && $this->supportsDeleteDetail();
            $deleteButton->visible($canDeleteCurrent);
        }

        if ($isEmpty) {
            $this->lbl_detail_heading->text($this->t('.detail.empty_heading'));

            return;
        }

        if ($this->state_detail_mode === 'creating') {
            $this->lbl_detail_heading->text($this->t('.detail.creating_heading'));

            return;
        }

        $itemTitle = $dto !== null ? $this->resolveDetailItemTitle($dto) : ('#'.($this->state_selected_id ?? ''));
        $headingKey = $this->state_detail_mode === 'editing'
            ? '.detail.editing_heading'
            : '.detail.viewing_heading';

        $this->lbl_detail_heading->text($this->t($headingKey, ['item' => $itemTitle]));
    }

    protected function supportsCreateDetail(): bool
    {
        return $this->detailService() instanceof CreatableDetail;
    }

    protected function supportsUpdateDetail(): bool
    {
        return $this->detailService() instanceof UpdatableDetail;
    }

    protected function supportsDeleteDetail(): bool
    {
        $service = $this->detailService();

        return ($service instanceof DeletableDetail) || ($service instanceof ItemListDeletable);
    }

    private function performServiceDelete(int|string $id): bool
    {
        $service = $this->detailService();

        if ($service instanceof DeletableDetail) {
            return $service->deleteDetail($id);
        }

        if ($service instanceof ItemListDeletable) {
            return $service->delete($id);
        }

        return false;
    }

    private function resolveOptionalButton(string $propertyName): ?Button
    {
        if (! property_exists($this, $propertyName)) {
            return null;
        }

        $reflection = new \ReflectionProperty($this, $propertyName);
        if (! $reflection->isInitialized($this)) {
            return null;
        }

        $value = $reflection->getValue($this);

        return $value instanceof Button ? $value : null;
    }

    private function extractFirstSelectedId(mixed $rawSelected): int|string|null
    {
        if (is_int($rawSelected) || is_string($rawSelected)) {
            return $rawSelected !== '' ? $rawSelected : null;
        }

        if (! is_array($rawSelected)) {
            return null;
        }

        foreach ($rawSelected as $candidate) {
            if (is_int($candidate) || (is_string($candidate) && $candidate !== '')) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractEntityId(object $dto): int|string|null
    {
        if (! property_exists($dto, 'id')) {
            return null;
        }

        /** @var mixed $rawId */
        $rawId = $dto->id;

        if (is_int($rawId)) {
            return $rawId;
        }

        if (is_string($rawId) && trim($rawId) !== '') {
            return $rawId;
        }

        return null;
    }
}
