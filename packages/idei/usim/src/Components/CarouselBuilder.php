<?php

namespace Idei\Usim\Components;

/**
 * Builder for Carousel UI component.
 */
class CarouselBuilder extends UIComponent
{
    protected function getDefaultConfig(): array
    {
        return [
            'mode' => 'manual',
            'fullscreen' => false,
            'loop' => false,
            'show_prev' => true,
            'show_next' => true,
            'indicator_position' => 'bottom',
            'known_count' => null,
            'current_index' => 0,
            'items' => [],
            'current_media' => null,
            'autoplay' => [
                'enabled' => false,
                'timeout_ms' => 5000,
                'action' => 'carousel_tick',
            ],
            'next_action' => 'carousel_next',
            'prev_action' => 'carousel_prev',
        ];
    }

    public function mode(string $mode): static
    {
        $safeMode = strtolower($mode);
        if (!in_array($safeMode, ['manual', 'auto'], true)) {
            $safeMode = 'manual';
        }

        $this->setConfig('mode', $safeMode);

        $autoplay = $this->get('autoplay', []);
        $autoplay['enabled'] = $safeMode === 'auto';
        return $this->setConfig('autoplay', $autoplay);
    }

    public function modeManual(): static
    {
        return $this->mode('manual');
    }

    public function modeAuto(): static
    {
        return $this->mode('auto');
    }

    public function fullscreen(bool $fullscreen = true): static
    {
        return $this->setConfig('fullscreen', $fullscreen);
    }

    public function loop(bool $loop = true): static
    {
        return $this->setConfig('loop', $loop);
    }

    public function showPrev(bool $show = true): static
    {
        return $this->setConfig('show_prev', $show);
    }

    public function showNext(bool $show = true): static
    {
        return $this->setConfig('show_next', $show);
    }

    public function indicators(string $position): static
    {
        $safePosition = strtolower($position);
        if (!in_array($safePosition, ['top', 'bottom', 'none'], true)) {
            $safePosition = 'bottom';
        }

        return $this->setConfig('indicator_position', $safePosition);
    }

    public function indicatorsTop(): static
    {
        return $this->indicators('top');
    }

    public function indicatorsBottom(): static
    {
        return $this->indicators('bottom');
    }

    public function hideIndicators(): static
    {
        return $this->indicators('none');
    }

    public function knownCount(?int $count): static
    {
        if ($count !== null) {
            $count = max(0, $count);
        }

        return $this->setConfig('known_count', $count);
    }

    public function currentIndex(int $index): static
    {
        return $this->setConfig('current_index', max(0, $index));
    }

    public function items(array $items): static
    {
        return $this->setConfig('items', $items);
    }

    public function currentMedia(array $media): static
    {
        return $this->setConfig('current_media', $media);
    }

    public function autoplay(bool $enabled = true): static
    {
        $autoplay = $this->get('autoplay', []);
        $autoplay['enabled'] = $enabled;

        if ($enabled) {
            $this->setConfig('mode', 'auto');
        }

        return $this->setConfig('autoplay', $autoplay);
    }

    public function autoAction(string $action): static
    {
        $autoplay = $this->get('autoplay', []);
        $autoplay['action'] = $action;
        return $this->setConfig('autoplay', $autoplay);
    }

    public function autoTimeoutMs(int $timeoutMs): static
    {
        $autoplay = $this->get('autoplay', []);
        $autoplay['timeout_ms'] = max(1, $timeoutMs);
        return $this->setConfig('autoplay', $autoplay);
    }

    public function nextAction(string $action): static
    {
        return $this->setConfig('next_action', $action);
    }

    public function prevAction(string $action): static
    {
        return $this->setConfig('prev_action', $action);
    }
}
