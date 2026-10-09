<?php

namespace Idei\Usim\Testing;

use Idei\Usim\Components\Container;
use Idei\Usim\Screen;
use Idei\Usim\UIChangesCollector;
use PHPUnit\Framework\Assert;

/**
 * @property mixed $value
 */
class UsimExpectations
{
    /**
     * @internal Satisfies IDE static analysis for Pest expectation macros
     */
    public mixed $value = null;

    private static bool $registered = false;

    /**
     * Register USIM custom expectations with Pest.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        self::$registered = true;

        if (! function_exists('expect')) {
            return;
        }

        expect()->extend('toContainRedirect', function (?string $url = null) {
            /** @var mixed $target */
            $target = $this->value;

            $collector = match (true) {
                $target instanceof Screen => $target->getUiChanges(),
                $target instanceof ScreenTestHarness => $target->getChanges(),
                $target instanceof UIChangesCollector => $target,
                default => null,
            };

            if ($collector !== null) {
                $hasRedirect = $collector->hasRedirect($url);
                $actual = $collector->getRedirect();
            } elseif (is_array($target)) {
                $actual = is_string($target['redirect'] ?? null)
                    ? $target['redirect']
                    : (isset($target['navigate']) && is_array($target['navigate']) && isset($target['navigate']['url']) && is_string($target['navigate']['url']) ? $target['navigate']['url'] : null);
                $hasRedirect = $actual !== null && ($url === null || $actual === $url);
            } else {
                $hasRedirect = false;
                $actual = null;
            }

            if (! $hasRedirect) {
                $message = $url !== null
                    ? "Expected UI changes to contain redirect to [{$url}], but got ".($actual ? "[{$actual}]" : 'none').'.'
                    : 'Expected UI changes to contain a redirect, but none was recorded.';
                Assert::fail($message);
            }

            return $this;
        });

        expect()->extend('toContainToast', function (?string $message = null, ?string $type = null) {
            /** @var mixed $target */
            $target = $this->value;

            $collector = match (true) {
                $target instanceof Screen => $target->getUiChanges(),
                $target instanceof ScreenTestHarness => $target->getChanges(),
                $target instanceof UIChangesCollector => $target,
                default => null,
            };

            if ($collector !== null) {
                $hasToast = $collector->hasToast($message, $type);
                $toasts = $collector->getToasts();
            } elseif (is_array($target)) {
                $toasts = isset($target['toast']) && is_array($target['toast'])
                    ? (isset($target['toast']['message']) ? [$target['toast']] : array_values(array_filter($target['toast'], 'is_array')))
                    : [];
                $hasToast = false;
                foreach ($toasts as $t) {
                    $msg = isset($t['message']) && is_string($t['message']) ? $t['message'] : '';
                    $tType = isset($t['type']) && is_string($t['type']) ? $t['type'] : '';
                    $msgOk = $message === null || str_contains($msg, $message) || $msg === $message;
                    $typeOk = $type === null || $tType === $type;
                    if ($msgOk && $typeOk) {
                        $hasToast = true;
                        break;
                    }
                }
            } else {
                $hasToast = false;
                $toasts = [];
            }

            if (! $hasToast) {
                Assert::fail("Expected UI changes to contain toast with message [{$message}] and type [{$type}], but found: ".json_encode($toasts));
            }

            return $this;
        });

        expect()->extend('toContainModal', function (?string $modalClass = null) {
            /** @var mixed $target */
            $target = $this->value;

            $collector = match (true) {
                $target instanceof Screen => $target->getUiChanges(),
                $target instanceof ScreenTestHarness => $target->getChanges(),
                $target instanceof UIChangesCollector => $target,
                default => null,
            };

            if ($collector !== null) {
                $hasModal = $collector->hasModal($modalClass);
            } elseif (is_array($target)) {
                $modal = isset($target['modal']) && is_array($target['modal']) ? $target['modal'] : null;
                $registered = $modal['modal_class'] ?? $modal['class'] ?? null;
                $hasModal = $modal !== null && ($modalClass === null || (is_string($registered) && ($registered === $modalClass || str_ends_with($registered, $modalClass))));
            } else {
                $hasModal = false;
            }

            if (! $hasModal) {
                Assert::fail("Expected UI changes to contain modal [{$modalClass}].");
            }

            return $this;
        });

        expect()->extend('toContainModalClosed', function () {
            /** @var mixed $target */
            $target = $this->value;

            $collector = match (true) {
                $target instanceof Screen => $target->getUiChanges(),
                $target instanceof ScreenTestHarness => $target->getChanges(),
                $target instanceof UIChangesCollector => $target,
                default => null,
            };

            $isClosed = $collector !== null
                ? $collector->isModalClosed()
                : (is_array($target) && ($target['action'] ?? null) === 'close_modal');

            if (! $isClosed) {
                Assert::fail('Expected modal to be closed in UI changes.');
            }

            return $this;
        });

        expect()->extend('toHaveComponent', function (string $name) {
            /** @var mixed $target */
            $target = $this->value;

            $container = match (true) {
                $target instanceof Screen => $target->getContainer(),
                $target instanceof ScreenTestHarness => $target->getContainer(),
                $target instanceof Container => $target,
                default => null,
            };

            Assert::assertNotNull($container, 'Screen or Container is not initialized.');
            $component = $container->findByName($name);
            Assert::assertNotNull($component, "Component [{$name}] was not found in screen tree.");

            return $this;
        });

        expect()->extend('toHaveComponentValue', function (string $name, mixed $expected) {
            /** @var mixed $target */
            $target = $this->value;

            $container = match (true) {
                $target instanceof Screen => $target->getContainer(),
                $target instanceof ScreenTestHarness => $target->getContainer(),
                $target instanceof Container => $target,
                default => null,
            };

            Assert::assertNotNull($container, 'Screen or Container is not initialized.');
            $component = $container->findByName($name);
            Assert::assertNotNull($component, "Component [{$name}] was not found in screen tree.");

            $actual = method_exists($component, 'getValue') ? $component->getValue() : null;
            Assert::assertSame($expected, $actual, "Expected component [{$name}] value to be [".var_export($expected, true).'], got ['.var_export($actual, true).'].');

            return $this;
        });

        expect()->extend('toHaveComponentText', function (string $name, string $expected) {
            /** @var mixed $target */
            $target = $this->value;

            $container = match (true) {
                $target instanceof Screen => $target->getContainer(),
                $target instanceof ScreenTestHarness => $target->getContainer(),
                $target instanceof Container => $target,
                default => null,
            };

            Assert::assertNotNull($container, 'Screen or Container is not initialized.');
            $component = $container->findByName($name);
            Assert::assertNotNull($component, "Component [{$name}] was not found in screen tree.");

            $actual = method_exists($component, 'getText') ? $component->getText() : null;
            Assert::assertSame($expected, $actual, "Expected component [{$name}] text to be [{$expected}], got [".var_export($actual, true).'].');

            return $this;
        });
    }
}
