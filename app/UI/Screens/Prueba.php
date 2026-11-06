<?php

namespace App\UI\Screens;

use Idei\Usim\Components\Button;
use Idei\Usim\Components\Input;
use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\Style;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Screen;
use Idei\Usim\UI;

class Prueba extends Screen
{
    private const string ErrorMessage = "Name with at least 6 characters is required.";
    public static Visibility $visibility = Visibility::PUBLIC;
    protected Button $button;
    protected NewLabel $label;
    protected Input $inputName;
    protected string $state_name = '';
    protected bool $state_buttonClicked = false;

    protected function startingUI(): Container
    {
        return UI::container(width: 400)
            ->card()
            ->padding(Spacing::px(16))
            ->add(
                $this->label
                    ->text("Enter your name below and click the button!")
                    ->style(Style::DEFAULT)
                    ->size(TextSize::LARGE)
            )->add(
                $this->inputName
                    ->placeholder("Your name...")
                    ->label("Name")
                    ->required()
                    ->value($this->state_name)
                    ->error(self::ErrorMessage)
                    ->onInput("textChanged")
            )->add(
                $this->button
                    ->label("Click me!")
                    ->disabled()
                    ->action("buttonClicked")
            );
    }

    protected function postLoadUI(): void
    {
        if (!$this->state_buttonClicked) {
            $this->inputName->value($this->state_name);
            $this->button->enabled(\strlen(trim($this->state_name)) > 5);
        }
    }

    public function buttonClicked(string $inputName = ''): void
    {
        $name = ! empty($inputName) ? $inputName : $this->state_name;
        $this->label->text("Hello $name!!")->style(Style::SUCCESS)->center();
        $this->inputName->disabled();
        $this->button->label("Clicked!")->disabled();
        $this->state_buttonClicked = true;
    }

    public function textChanged(string $inputName = ''): void
    {
        $inputLength = \strlen(\trim($inputName));
        $this->inputName->error($inputLength <= 5 ? self::ErrorMessage : "");
        $this->button->enabled($inputLength > 5);
        $this->state_name = $inputName;
    }
}
