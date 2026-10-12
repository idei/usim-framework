<?php

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Label;
use Idei\Usim\Screen;
use Idei\Usim\Testing\ScreenTestHarness;
use Idei\Usim\UI;

// Screen fixture demonstrating Paradigm B: Pure Auto-Wiring (no UI::factory calls)
class AutoWiringDemoScreen extends Screen
{
    protected Label $lbl_header;
    protected Input $user_input;
    protected Button $submit_btn;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $container
            ->add($this->lbl_header->text('Welcome'))
            ->add($this->user_input->placeholder('Enter text')->value('initial_value'))
            ->add($this->submit_btn->label('Submit')->action('handle_submit'));
    }

    public function onSubmitAction(array $params): void
    {
        $this->lbl_header->text('Submitted!');
    }
}

// Screen fixture demonstrating Paradigm A: Auto-Naming unnamed assignments
class AutoNamingDemoScreen extends Screen
{
    protected Label $lbl_instruction;
    protected Input $txt_query;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->lbl_instruction = UI::label()
            ->text('Instructions here')
            ->style('info');

        $this->txt_query = UI::input()
            ->placeholder('Search...')
            ->value('query');

        $container
            ->add($this->lbl_instruction)
            ->add($this->txt_query);
    }
}

// Screen fixture demonstrating Fail-Fast naming mismatch detection
class MismatchedNamingDemoScreen extends Screen
{
    protected Label $lbl_title;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->lbl_title = UI::label('totally_different_name')
            ->text('Hello');

        $container->add($this->lbl_title);
    }
}

// Screen fixture demonstrating camelCase property with snake_case component
class CamelCasePropertyDemoScreen extends Screen
{
    protected Label $userGreetingLabel;

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $this->userGreetingLabel = UI::label('user_greeting_label')
            ->text('Hello User');

        $container->add($this->userGreetingLabel);
    }
}

it('auto-wires uninitialized component properties before buildBaseUI runs', function () {
    $harness = ScreenTestHarness::for(AutoWiringDemoScreen::class);
    $harness->render();

    $harness->assertHasComponent('lbl_header')
        ->assertHasComponent('user_input')
        ->assertHasComponent('submit_btn');

    $header = $harness->getComponent('lbl_header');
    expect($header->get('text'))->toBe('Welcome');

    $input = $harness->getComponent('user_input');
    expect($input->get('value'))->toBe('initial_value');

    $button = $harness->getComponent('submit_btn');
    expect($button->get('action'))->toBe('handle_submit');
});

it('auto-names unnamed components assigned to properties during buildBaseUI', function () {
    $harness = ScreenTestHarness::for(AutoNamingDemoScreen::class);
    $harness->render();

    $harness->assertHasComponent('lbl_instruction')
        ->assertHasComponent('txt_query');

    $instruction = $harness->getComponent('lbl_instruction');
    expect($instruction->getName())->toBe('lbl_instruction');
    expect($instruction->get('text'))->toBe('Instructions here');
    expect($instruction->get('style'))->toBe('info');

    $query = $harness->getComponent('txt_query');
    expect($query->getName())->toBe('txt_query');
    expect($query->get('value'))->toBe('query');
});

it('throws LogicException when property name and explicit component name mismatch', function () {
    $harness = ScreenTestHarness::for(MismatchedNamingDemoScreen::class);

    expect(fn () => $harness->render())
        ->toThrow(LogicException::class, "Naming mismatch in Screen");
});

it('accepts camelCase property names that match snake_case component names', function () {
    $harness = ScreenTestHarness::for(CamelCasePropertyDemoScreen::class);
    $harness->render();

    $harness->assertHasComponent('user_greeting_label');
    $component = $harness->getComponent('user_greeting_label');
    expect($component->get('text'))->toBe('Hello User');
});

it('allows event handlers to manipulate auto-wired components during actions', function () {
    $harness = ScreenTestHarness::for(AutoWiringDemoScreen::class);
    $harness->render();

    $harness->call('submit_action');

    $header = $harness->getComponent('lbl_header');
    expect($header->get('text'))->toBe('Submitted!');
});
