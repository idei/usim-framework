<?php
namespace App\UI\Screens\Demo;

use Idei\Usim\UIBuilder;
use Idei\Usim\Screen;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Components\Button;
use Idei\Usim\Components\Checkbox;

class CheckboxDemo extends Screen
{
    protected Label $lbl_instruction;
    protected Checkbox $chk_javascript;
    protected Checkbox $chk_python;
    protected Button $btn_submit;
    protected Label $lbl_result;

    /**
     * Build the checkbox demo UI
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->title(t('screen.demo.checkbox_demo.title'))
            ->maxWidth('500px')
            ->centerHorizontal()
            ->padding('20px')
            ->shadow(2);

        // Instruction label
        $container->add(
            UIBuilder::label('lbl_instruction')
                ->text(t('screen.demo.checkbox_demo.instruction'))
                ->style('info')
        );

        // JavaScript checkbox with onChange handler
        $container->add(
            UIBuilder::checkbox('chk_javascript')
                ->label(t('screen.demo.checkbox_demo.options.javascript'))
                ->checked(false)
                ->onChange('try_change_javascript') // ← Handler for validation
        );

        // Python checkbox with onChange handler
        $container->add(
            UIBuilder::checkbox('chk_python')
                ->label(t('screen.demo.checkbox_demo.options.python'))
                ->checked(false)
                ->onChange('try_change_python') // ← Handler for validation
        );

        // Submit button
        $container->add(
            UIBuilder::button('btn_submit')
                ->label(t('screen.demo.checkbox_demo.actions.submit'))
                ->action('submit_selection')
                ->style('primary')
        );

        // Result label
        $container->add(
            UIBuilder::label('lbl_result')
                ->text(t('screen.demo.checkbox_demo.result.initial'))
                ->style('secondary')
        );
    }

    public function postLoadUI(): void
    {
        $this->chk_javascript->checked(false);
        $this->chk_python->checked(false);
        $this->lbl_result->text(t('screen.demo.checkbox_demo.result.initial'))->style('secondary');
    }

    /**
     * Handle JavaScript checkbox change attempt
     * Backend validates and confirms or rejects the change
     */
    public function onTryChangeJavascript(array $params): void
    {
        $wantsChecked = $params['checked'] ?? false;

        // // Example validation: you could check any condition here
        // // For now, we'll allow the change
        // $this->store_js_checked = $wantsChecked;

        // Update the checkbox with the confirmed state
        $this->chk_javascript->checked($wantsChecked);

        // Show feedback
        if ($wantsChecked) {
            $this->lbl_result
                ->text(t('screen.demo.checkbox_demo.result.javascript_selected'))
                ->style('success');
        } else {
            $this->lbl_result
                ->text(t('screen.demo.checkbox_demo.result.javascript_deselected'))
                ->style('info');
        }
    }

    /**
     * Handle Python checkbox change attempt
     * Backend validates and confirms or rejects the change
     */
    public function onTryChangePython(array $params): void
    {
        $wantsChecked = $params['checked'] ?? false;
        $jsChecked    = $this->chk_javascript->isChecked();

        // Example validation: only allow Python if JavaScript is also selected
        // if ($wantsChecked && !$this->store_js_checked) {
        if ($wantsChecked && ! $jsChecked) {
                                               // ❌ REJECT: Don't allow Python without JavaScript
            $this->chk_python->checked(false); // Keep it unchecked
            $this->lbl_result
                ->text(t('screen.demo.checkbox_demo.validation.python_requires_javascript'))
                ->style('danger');
            $this->toast(t('screen.demo.checkbox_demo.validation.python_requires_javascript'), type: 'error');
            return;
        }

        // ✅ APPROVE: Allow the change
        // $this->store_py_checked = $wantsChecked;
        $this->chk_python->checked($wantsChecked);

        if ($wantsChecked) {
            $this->lbl_result
                ->text(t('screen.demo.checkbox_demo.result.python_selected'))
                ->style('success');
        } else {
            $this->lbl_result
                ->text(t('screen.demo.checkbox_demo.result.python_deselected'))
                ->style('info');
        }
    }

    /**
     * Handle form submission
     * Reads checkbox states from frontend parameters
     */
    public function onSubmitSelection(array $params): void
    {
        // Get checkbox states from frontend parameters (sent by collectContextValues)
        $jsChecked = $params['chk_javascript'] ?? false;
        $pyChecked = $params['chk_python'] ?? false;

        // Build selections array
        $selections = [];

        if ($jsChecked) {
            $selections[] = t('screen.demo.checkbox_demo.options.javascript');
        }
        if ($pyChecked) {
            $selections[] = t('screen.demo.checkbox_demo.options.python');
        }

        // Validate minimum selection
        if (empty($selections)) {
            $this->lbl_result
                ->text(t('screen.demo.checkbox_demo.validation.minimum_one'))
                ->style('danger');
            return;
        }

        // Success message
        $languagesList = implode(', ', $selections);
        $this->lbl_result
            ->text(t('screen.demo.checkbox_demo.result.submitted', ['languages' => $languagesList]))
            ->style('success');

        $this->toast(t('screen.demo.checkbox_demo.result.submitted_toast', ['languages' => $languagesList]), type: 'success');
    }
}
