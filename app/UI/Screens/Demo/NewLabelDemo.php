<?php

namespace App\UI\Screens\Demo;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\NewLabel;
use Idei\Usim\Enums\Align;
use Idei\Usim\Enums\FontWeight;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Style;
use Idei\Usim\Enums\TextSize;
use Idei\Usim\Enums\Variant;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Size;
use Idei\Usim\ValueObjects\Spacing;

class NewLabelDemo extends Screen
{
    public static Visibility $visibility = Visibility::PUBLIC;

    protected NewLabel $lbl_interactive;
    protected int $store_click_count = 0;
    protected string $store_current_style = 'primary';
    protected string $store_current_variant = 'pill';

    public static function getMenuLabel(): string
    {
        return 'NewLabel Demo';
    }

    public static function getMenuIcon(): ?string
    {
        return '🏷️';
    }

    protected function buildBaseUI(Container $container, ...$params): void
    {
        $wrapper = UI::container('wrapper')
            ->card()
            ->maxWidth(Size::px(960))
            ->centerHorizontal()
            ->padding(Spacing::px(16))
            ->gap(Spacing::px(20));

        // Header
        $wrapper->add(
            UI::newLabel('demo_title')
                ->text('Demostración de NewLabel')
                ->size(TextSize::XL)
                ->bold()
                ->primary()
                ->center()
                ->block()
        );

        $wrapper->add(
            UI::newLabel('demo_subtitle')
                ->text('Componente moderno de etiquetas con enums tipados, estilos, variantes, tipografía y efectos visuales.')
                ->size(TextSize::SMALL)
                ->muted()
                ->center()
                ->block()
        );

        // 1. Estilos (Styles)
        $wrapper->add($this->buildSectionHeader('1. Estilos semánticos (Style Enum)'));
        $stylesRow = UI::container('styles_row')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(8))
            ->plain();

        $stylesRow->add(UI::newLabel()->text('Default')->style(Style::DEFAULT)->pill());
        $stylesRow->add(UI::newLabel()->text('Primary')->style(Style::PRIMARY)->pill());
        $stylesRow->add(UI::newLabel()->text('Secondary')->style(Style::SECONDARY)->pill());
        $stylesRow->add(UI::newLabel()->text('Success')->style(Style::SUCCESS)->pill());
        $stylesRow->add(UI::newLabel()->text('Warning')->style(Style::WARNING)->pill());
        $stylesRow->add(UI::newLabel()->text('Danger')->style(Style::DANGER)->pill());
        $stylesRow->add(UI::newLabel()->text('Info')->style(Style::INFO)->pill());
        $stylesRow->add(UI::newLabel()->text('Muted')->style(Style::MUTED)->pill());
        $wrapper->add($stylesRow);

        // 2. Variantes (Variants)
        $wrapper->add($this->buildSectionHeader('2. Variantes de presentación (Variant Enum)'));
        $variantsRow = UI::container('variants_row')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(8))
            ->plain();

        $variantsRow->add(UI::newLabel()->text('Texto Normal')->variant(Variant::TEXT)->info());
        $variantsRow->add(UI::newLabel()->text('Badge')->variant(Variant::BADGE)->primary());
        $variantsRow->add(UI::newLabel()->text('Chip')->variant(Variant::CHIP)->success());
        $variantsRow->add(UI::newLabel()->text('Tag')->variant(Variant::TAG)->warning());
        $variantsRow->add(UI::newLabel()->text('Pill')->variant(Variant::PILL)->danger());
        $variantsRow->add(UI::newLabel()->text('Outlined')->variant(Variant::OUTLINED)->primary());
        $wrapper->add($variantsRow);

        // 3. Tamaños (Sizes)
        $wrapper->add($this->buildSectionHeader('3. Escala de tamaños (TextSize Enum)'));
        $sizesRow = UI::container('sizes_row')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(10))
            ->plain();

        $sizesRow->add(UI::newLabel()->text('XS')->size(TextSize::XS)->badge()->secondary());
        $sizesRow->add(UI::newLabel()->text('Small')->size(TextSize::SMALL)->badge()->secondary());
        $sizesRow->add(UI::newLabel()->text('Medium')->size(TextSize::MEDIUM)->badge()->secondary());
        $sizesRow->add(UI::newLabel()->text('Large')->size(TextSize::LARGE)->badge()->secondary());
        $sizesRow->add(UI::newLabel()->text('XL')->size(TextSize::XL)->badge()->secondary());
        $wrapper->add($sizesRow);

        // 4. Tipografía y Transformaciones
        $wrapper->add($this->buildSectionHeader('4. Tipografía y Transformaciones'));
        $typographyRow = UI::container('typography_row')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(8))
            ->plain();

        $typographyRow->add(UI::newLabel()->text('Texto Light')->fontWeight(FontWeight::LIGHT));
        $typographyRow->add(UI::newLabel()->text('Texto Normal')->fontWeight(FontWeight::NORMAL));
        $typographyRow->add(UI::newLabel()->text('Texto Medium')->fontWeight(FontWeight::MEDIUM));
        $typographyRow->add(UI::newLabel()->text('Texto Semibold')->fontWeight(FontWeight::SEMIBOLD));
        $typographyRow->add(UI::newLabel()->text('Texto Bold')->bold()->primary());
        $typographyRow->add(UI::newLabel()->text('mayúsculas')->uppercase()->tag()->info());
        $typographyRow->add(UI::newLabel()->text('CAPITALIZADO')->capitalize()->tag()->success());
        $wrapper->add($typographyRow);

        // 5. Iconos y Efectos
        $wrapper->add($this->buildSectionHeader('5. Iconos y Efectos visuales'));
        $effectsRow = UI::container('effects_row')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(10))
            ->plain();

        $effectsRow->add(UI::newLabel()->text('Con Icono Izq')->icon('🚀', Align::LEFT)->pill()->primary());
        $effectsRow->add(UI::newLabel()->text('Con Icono Der')->icon('⭐', Align::RIGHT)->pill()->warning());
        $effectsRow->add(UI::newLabel()->text('Con Sombra')->shadow()->chip()->success());
        $effectsRow->add(UI::newLabel()->text('Con Glow')->glow()->chip()->info());
        $wrapper->add($effectsRow);

        // 6. Markdown
        $wrapper->add($this->buildSectionHeader('6. Soporte Markdown'));
        $mdContainer = UI::container('md_container')
            ->plain()
            ->padding(Spacing::px(8))
            ->width(Size::full());

        $mdContainer->add(
            UI::newLabel('md_sample')
                ->markdown()
                ->text("Este texto es procesado con **Markdown**: soporta `código en línea`, *cursiva*, y [enlaces seguros](#).")
                ->block()
        );
        $wrapper->add($mdContainer);

        // 7. Sección Interactiva Reactiva
        $wrapper->add($this->buildSectionHeader('7. Reactividad en tiempo real'));
        $interactiveCard = UI::container('interactive_card')
            ->card()
            ->gap(Spacing::px(12))
            ->padding(Spacing::px(14));

        $this->lbl_interactive = UI::newLabel('lbl_interactive')
            ->text('Estado inicial: 0 clics')
            ->style(Style::PRIMARY)
            ->variant(Variant::PILL)
            ->size(TextSize::LARGE)
            ->icon('⚡', Align::LEFT)
            ->bold()
            ->center()
            ->block();

        $interactiveCard->add($this->lbl_interactive);

        $controlsRow = UI::container('controls_row')
            ->layout(LayoutType::HORIZONTAL)
            ->gap(Spacing::px(8))
            ->centerContent()
            ->plain();

        $controlsRow->add(
            UI::button('btn_success')
                ->label('Cambiar a Éxito')
                ->action('change_state', ['style' => 'success', 'variant' => 'chip', 'icon' => '✅'])
                ->style('success')
        );

        $controlsRow->add(
            UI::button('btn_warning')
                ->label('Cambiar a Advertencia')
                ->action('change_state', ['style' => 'warning', 'variant' => 'tag', 'icon' => '⚠️'])
                ->style('warning')
        );

        $controlsRow->add(
            UI::button('btn_danger')
                ->label('Cambiar a Peligro')
                ->action('change_state', ['style' => 'danger', 'variant' => 'badge', 'icon' => '🔥'])
                ->style('danger')
        );

        $controlsRow->add(
            UI::button('btn_reset')
                ->label('Reiniciar')
                ->action('reset_interactive')
                ->style('secondary')
        );

        $interactiveCard->add($controlsRow);
        $wrapper->add($interactiveCard);

        $container->add($wrapper);
    }

    private function buildSectionHeader(string $title): NewLabel
    {
        return UI::newLabel()
            ->text($title)
            ->size(TextSize::MEDIUM)
            ->bold()
            ->muted()
            ->block();
    }

    /**
     * Maneja el cambio de estado interactivo del NewLabel.
     *
     * @param array<string, mixed> $params
     */
    public function onChangeState(array $params): void
    {
        $this->store_click_count++;
        $style = (string) ($params['style'] ?? 'primary');
        $variant = (string) ($params['variant'] ?? 'pill');
        $icon = (string) ($params['icon'] ?? '⚡');

        $this->store_current_style = $style;
        $this->store_current_variant = $variant;

        $this->lbl_interactive
            ->text("Actualizado a {$style} ({$this->store_click_count} clics)")
            ->style($style)
            ->variant($variant)
            ->icon($icon, Align::LEFT);

        $this->toast("NewLabel actualizado a estilo '{$style}'", 'info');
    }

    /**
     * Reinicia el estado interactivo del NewLabel.
     */
    public function onResetInteractive(): void
    {
        $this->store_click_count = 0;
        $this->store_current_style = 'primary';
        $this->store_current_variant = 'pill';

        $this->lbl_interactive
            ->text('Estado inicial: 0 clics')
            ->style(Style::PRIMARY)
            ->variant(Variant::PILL)
            ->icon('⚡', Align::LEFT);

        $this->toast('Estado interactivo reiniciado', 'success');
    }
}
