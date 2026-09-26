<?php

namespace Idei\Usim\Modals;

use Idei\Usim\Components\Container;
use Idei\Usim\Components\Label;
use Idei\Usim\Enums\LayoutType;
use Idei\Usim\Enums\Visibility;
use Idei\Usim\Modals\Dialog\ConfirmDialogConfig;
use Idei\Usim\Screen;
use Idei\Usim\UI;
use Idei\Usim\ValueObjects\Spacing;
use Idei\Usim\Contracts\ModalInterface;

/**
 * Confirm Dialog Screen
 *
 * Server-driven UI Screen for modal dialogs (info, confirm, warning, error, success, choice, timeout).
 * Implements the Screen lifecycle and adheres to SOLID and Clean Code principles.
 */
class ConfirmDialog extends Screen implements ModalInterface
{
    /**
     * Modal dialogs render inside the 'modal' layer.
     */
    public protected(set) int|string|null $parent = 'modal';

    /**
     * Dialog modals do not display the top navigation menu.
     */
    public static bool $hasMenu = false;

    /**
     * Dialogs can be triggered in both public and authenticated contexts.
     */
    public static Visibility $visibility = Visibility::PUBLIC;

    /**
     * Build the screen UI structure into the given container.
     *
     * @param  mixed  ...$params  Configuration parameters or ConfirmDialogConfig instance
     */
    protected function buildBaseUI(Container $container, ...$params): void
    {
        $config = $this->resolveConfig($params);

        $this->configureContainer($container);
        $this->buildContent($container, $config);

        if ($config->shouldRenderButtons()) {
            $this->buildButtonsSection($container, $config);
        }
    }

    /**
     * Open the modal dialog by building its UI structure and registering it with the UI collector.
     *
     * @param  Screen  $caller  The screen that is calling this method
     * @param  mixed ...$params  Configuration parameters or ConfirmDialogConfig instance
     */
    public static function open(Screen $caller, mixed ...$params): void
    {
        $screen = app(self::class);
        $callerServiceId = $caller->getScreenComponentId();
        $params = array_merge(['callerServiceId' => $callerServiceId],$params);
        $payload = $screen->buildDialogPayload(...$params);
        $screen->uiChanges()->add($payload);
    }

    /**
     * Build the raw dialog UI payload, attaching timeout metadata if applicable.
     *
     * @return array<int|string, array<string, mixed>>
     */
    public function buildDialogPayload(mixed ...$params): array
    {
        $config = $this->resolveConfig($params);

        $container = UI::container('confirm_dialog')
            ->parent($this->parent ?? 'modal');

        $this->buildBaseUI($container, $config);

        $payload = $container->toJson();

        if ($config->isTimeout()) {
            $rootId = $container->getId();
            if (isset($payload[$rootId])) {
                $payload[$rootId] = array_merge($payload[$rootId], $config->getTimeoutMetadata());
            }
        }

        return $payload;
    }

    /**
     * Attach timeout metadata to screen diff responses when operating in Screen lifecycle.
     *
     * @return array<int|string, array<string, mixed>>
     */
    protected function buildDiffResponse(bool $reload = false): array
    {
        $diff = parent::buildDiffResponse($reload);
        $config = $this->resolveConfig([]);

        if ($config->isTimeout() && isset($this->container)) {
            $rootId = $this->container->getId();
            if (isset($diff[$rootId])) {
                $diff[$rootId] = array_merge($diff[$rootId], $config->getTimeoutMetadata());
            }
        }

        return $diff;
    }

    /**
     * Resolve dialog configuration from explicit arguments or request query params.
     *
     * @param  array<int|string, mixed>  $params
     */
    protected function resolveConfig(array $params): ConfirmDialogConfig
    {
        if (! empty($params)) {
            return ConfirmDialogConfig::from(...$params);
        }

        if (! empty($this->queryParams)) {
            return ConfirmDialogConfig::from($this->queryParams);
        }

        return ConfirmDialogConfig::from();
    }

    /**
     * Configure modal layout and styling on the root container.
     */
    protected function configureContainer(Container $container): void
    {
        $container
            ->parent($this->parent ?? 'modal')
            ->layout(LayoutType::VERTICAL)
            ->plain()
            ->gap(Spacing::px(8))
            ->centerContent();
    }

    /**
     * Build and attach dialog body content (icon, title, message, and optional countdown).
     */
    protected function buildContent(Container $container, ConfirmDialogConfig $config): void
    {
        $container->add($this->buildIcon($config));
        $container->add($this->buildTitle($config));
        $container->add($this->buildMessage($config));

        if ($config->hasCountdown()) {
            $container->add($this->buildCountdown($config));
        }
    }

    protected function buildIcon(ConfirmDialogConfig $config): Label
    {
        return UI::label('icon')
            ->text($config->icon)
            ->fontSize('48');
    }

    protected function buildTitle(ConfirmDialogConfig $config): Label
    {
        return UI::label('title')
            ->text($config->title)
            ->style('h3');
    }

    protected function buildMessage(ConfirmDialogConfig $config): Label
    {
        return UI::label('message')
            ->text($config->message)
            ->markdown();
    }

    protected function buildCountdown(ConfirmDialogConfig $config): Label
    {
        return UI::label('countdown')
            ->text($config->getFormattedCountdown())
            ->style('h2');
    }

    /**
     * Build buttons container and add action buttons based on dialog configuration.
     */
    protected function buildButtonsSection(Container $container, ConfirmDialogConfig $config): void
    {
        $buttonsContainer = UI::container('buttons')
            ->layout(LayoutType::HORIZONTAL)
            ->plain()
            ->gap(Spacing::px(15))
            ->centerContent();

        if ($config->isChoice()) {
            $this->buildChoiceButtons($buttonsContainer, $config);
        } else {
            $this->buildStandardButtons($buttonsContainer, $config);
        }

        $container->add($buttonsContainer);
    }

    /**
     * Summary of getString
     *
     * @param array<string, mixed> $array
     * @param string $key
     * @param string $default
     *
     * @return string
     */
    private function getString(array $array, string $key, string $default = ''): string
    {
        return isset($array[$key]) && \is_string($array[$key]) ? $array[$key] : $default;
    }

    /**
     * Build custom buttons for CHOICE dialog type.
     */
    protected function buildChoiceButtons(Container $buttonsContainer, ConfirmDialogConfig $config): void
    {
        $callerContext = ['_caller_service_id' => $config->callerServiceId];

        foreach ($config->buttons ?? [] as $button) {
            $label = $this->getString($button, 'label', 'Button');
            $style = $this->getString($button, 'style', 'secondary');
            $action = $this->getString($button, 'action', '');
            $params = is_array($button['params'] ?? null) ? $button['params'] : [];

            $snakeLabel = preg_replace('/[^a-zA-Z0-9_]+/', '_', str_replace(' ', '_', $label));
            $snakeLabel = \is_string($snakeLabel) ? $snakeLabel : 'button';
            $buttonName = 'btn_'.strtolower($snakeLabel);

            $buttonsContainer->add(
                UI::button($buttonName)
                    ->label($label)
                    ->style($style)
                    ->action($action, array_merge($params, $callerContext))
            );
        }
    }

    /**
     * Build standard Cancel and Confirm buttons.
     */
    protected function buildStandardButtons(Container $buttonsContainer, ConfirmDialogConfig $config): void
    {
        $callerContext = ['_caller_service_id' => $config->callerServiceId];

        if ($config->hasCancelButton()) {
            $buttonsContainer->add(
                UI::button('btn_cancel')
                    ->label($config->cancelLabel)
                    ->style('secondary')
                    ->action($config->cancelAction, $callerContext)
            );
        }

        $buttonsContainer->add(
            UI::button('btn_confirm')
                ->label($config->confirmLabel)
                ->style($config->getConfirmButtonStyle())
                ->action($config->confirmAction, array_merge($config->confirmParams, $callerContext))
        );
    }
}

