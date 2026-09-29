<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Select;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

/**
 * User Form Modal Screen
 *
 * Demonstrates a complex, interactive Screen functioning as a modal dialog.
 * Receives initial state from the caller, collects input from the user,
 * and returns validated data to the caller Screen via returnToCaller().
 */
class UserFormModal extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    public static ?string $layout = null;

    public protected(set) int|string|null $parent = 'modal';

    protected Input $input_user_name;

    protected Input $input_user_email;

    protected Select $select_user_role;

    /**
     * Build the modal screen UI structure.
     *
     * @param  mixed  ...$params  Initialization parameters passed by caller
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $initialName = '';
        $initialEmail = '';
        $initialRole = 'editor';

        if (isset($params[0]) && is_array($params[0])) {
            $initialName = isset($params[0]['initial_name']) && is_string($params[0]['initial_name'])
                ? $params[0]['initial_name']
                : '';
            $initialEmail = isset($params[0]['initial_email']) && is_string($params[0]['initial_email'])
                ? $params[0]['initial_email']
                : '';
            $initialRole = isset($params[0]['initial_role']) && is_string($params[0]['initial_role'])
                ? $params[0]['initial_role']
                : 'editor';
        }

        $container
            ->title(t('screen.demo.modal_demo.user_modal.title'))
            ->maxWidth(Size::px(520))
            ->centerHorizontal()
            ->plain()
            ->shadow(2)
            ->padding(Spacing::px(24))
            ->gap(Spacing::px(16));

        $container->add(
            UI::label('lbl_modal_desc')
                ->text(t('screen.demo.modal_demo.user_modal.description'))
                ->style('secondary')
        );

        $container->add(
            UI::input('input_user_name')
                ->label(t('screen.demo.modal_demo.user_modal.name_label'))
                ->placeholder(t('screen.demo.modal_demo.user_modal.name_placeholder'))
                ->value($initialName)
                ->width(Size::full())
        );

        $container->add(
            UI::input('input_user_email')
                ->label(t('screen.demo.modal_demo.user_modal.email_label'))
                ->placeholder(t('screen.demo.modal_demo.user_modal.email_placeholder'))
                ->value($initialEmail)
                ->width(Size::full())
        );

        $container->add(
            UI::select('select_user_role')
                ->label(t('screen.demo.modal_demo.user_modal.role_label'))
                ->options([
                    ['value' => 'admin', 'label' => 'Admin'],
                    ['value' => 'editor', 'label' => 'Editor'],
                    ['value' => 'viewer', 'label' => 'Viewer'],
                ])
                ->value($initialRole)
                ->width(Size::full())
        );

        $buttons = UI::container('modal_buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->centerContent()
            ->gap(Spacing::px(12))
            ->plain()
            ->shadow(false)
            ->add(
                UI::button('btn_cancel_modal')
                    ->label(t('screen.demo.modal_demo.user_modal.cancel'))
                    ->style('secondary')
                    ->action('close_modal')
            )->add(
                UI::button('btn_save_modal')
                    ->label(t('screen.demo.modal_demo.user_modal.submit'))
                    ->style('primary')
                    ->action('submit_modal_data')
            );

        $container->add($buttons);
    }

    /**
     * Handle form submission, validate input, and return data to caller screen.
     *
     * @param  array<string, mixed>  $params
     */
    public function onSubmitModalData(array $params): void
    {
        $rawName = $params['input_user_name'] ?? '';
        $name = trim(is_scalar($rawName) || $rawName instanceof \Stringable ? (string) $rawName : '');

        $rawEmail = $params['input_user_email'] ?? '';
        $email = trim(is_scalar($rawEmail) || $rawEmail instanceof \Stringable ? (string) $rawEmail : '');

        $rawRole = $params['select_user_role'] ?? 'editor';
        $role = trim(is_scalar($rawRole) || $rawRole instanceof \Stringable ? (string) $rawRole : 'editor');

        if ($name === '') {
            $this->input_user_name->error(t('screen.demo.modal_demo.user_modal.name_required'));
            $this->toast(t('screen.demo.modal_demo.user_modal.name_required'), 'error');

            return;
        }

        $this->returnToCaller([
            'name' => $name,
            'email' => $email,
            'role' => $role,
        ]);
    }
}
