<?php

namespace Idei\Usim\Components;

/**
 * Builder for Timer UI component
 *
 * Non-visual, autonomous timer component that triggers backend actions
 * after a specified delay or periodically at a fixed interval.
 *
 * Useful for polling (e.g. device pairing, status checks), scheduled notifications,
 * auto-refreshing sections, and timeout redirects.
 */
class Timer extends UIComponent
{
    /**
     * @return array<string, mixed>
     */
    protected function getDefaultConfig(): array
    {
        return [
            'action' => null,
            'interval' => 5000,
            'repeat' => false,
            'enabled' => true,
            'immediate' => false,
            'parameters' => [],
        ];
    }

    /**
     * Set the backend action name to execute on timer expiration
     */
    public function action(string $action): static
    {
        return $this->setConfig('action', $action);
    }

    /**
     * Set the timer interval in milliseconds
     */
    public function interval(int $milliseconds): static
    {
        return $this->setConfig('interval', max(1, $milliseconds));
    }

    /**
     * Configure timer to repeat periodically at the specified interval (polling mode)
     */
    public function every(int $milliseconds): static
    {
        $this->interval($milliseconds);
        return $this->repeat(true);
    }

    /**
     * Configure timer to fire once after the specified delay (one-shot mode)
     */
    public function after(int $milliseconds): static
    {
        $this->interval($milliseconds);
        return $this->repeat(false);
    }

    /**
     * Configure whether the timer repeats periodically or fires only once
     */
    public function repeat(bool $repeat = true): static
    {
        return $this->setConfig('repeat', $repeat);
    }

    /**
     * Configure the timer to execute only once
     */
    public function once(): static
    {
        return $this->repeat(false);
    }

    /**
     * Enable or disable the timer
     */
    public function enabled(bool $enabled = true): static
    {
        return $this->setConfig('enabled', $enabled);
    }

    /**
     * Start / resume the timer
     */
    public function start(): static
    {
        return $this->enabled(true);
    }

    /**
     * Stop / pause the timer
     */
    public function stop(): static
    {
        return $this->enabled(false);
    }

    /**
     * Whether the timer should trigger its action immediately upon mounting
     */
    public function immediate(bool $immediate = true): static
    {
        return $this->setConfig('immediate', $immediate);
    }

    /**
     * Set event parameters sent to the backend action handler
     *
     * @param array<string, mixed> $parameters
     */
    public function parameters(array $parameters): static
    {
        return $this->setConfig('parameters', $parameters);
    }

    /**
     * Set a single parameter sent to the backend action handler
     */
    public function param(string $key, mixed $value): static
    {
        /** @var array<string, mixed> $params */
        $params = $this->get('parameters', []);
        $params[$key] = $value;
        return $this->parameters($params);
    }
}

