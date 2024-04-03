<?php

namespace Filament\Actions\Testing;

use Closure;
use Exception;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\Contracts\HasActions;
use Filament\Actions\Exceptions\ActionNotResolvableException;
use Filament\Actions\MountableAction;
use Filament\Actions\StaticAction;
use Filament\Actions\Testing\Fixtures\TestAction;
use Illuminate\Support\Arr;
use Illuminate\Testing\Assert;
use Livewire\Features\SupportTesting\Testable;

use function Livewire\store;

/**
 * @method HasActions instance()
 *
 * @mixin Testable
 */
class TestsActions
{
    public function mountAction(): Closure
    {
        return function (string | TestAction | array $actions, array $arguments = []): static {
            /** @var array<array<string, mixed>> $actions */
            /** @phpstan-ignore-next-line */
            $actions = $this->parseNestedActions($actions, $arguments);

            foreach ($actions as $action) {
                $this->call(
                    'mountAction',
                    $action['name'],
                    $action['arguments'] ?? [],
                    $action['context'] ?? [],
                );
            }

            if (store($this->instance())->has('redirect')) {
                return $this;
            }

            $this->assertDispatched('sync-action-modals', id: $this->instance()->getId(), newActionNestingIndex: array_key_last($this->instance()->mountedActions));

            if (! count($this->instance()->mountedActions)) {
                return $this;
            }

            /** @phpstan-ignore-next-line */
            $this->assertActionMounted($actions);

            return $this;
        };
    }

    public function unmountAction(): Closure
    {
        return function (): static {
            $this->call('unmountAction');

            return $this;
        };
    }

    public function setActionData(): Closure
    {
        return function (array $data): static {
            foreach (Arr::dot($data, prepend: 'mountedActions.' . array_key_last($this->instance()->mountedActions) . '.data.') as $key => $value) {
                $this->set($key, $value);
            }

            return $this;
        };
    }

    public function assertActionDataSet(): Closure
    {
        return function (array $data): static {
            foreach (Arr::dot($data, prepend: 'mountedActions.' . array_key_last($this->instance()->mountedActions) . '.data.') as $key => $value) {
                $this->assertSet($key, $value);
            }

            return $this;
        };
    }

    public function callAction(): Closure
    {
        return function (string | TestAction | array $actions, array $data = [], array $arguments = []): static {
            /** @phpstan-ignore-next-line */
            $this->assertActionVisible($actions);

            /** @phpstan-ignore-next-line */
            $this->mountAction($actions, $arguments);

            if (! $this->instance()->getMountedAction()) {
                return $this;
            }

            /** @phpstan-ignore-next-line */
            $this->setActionData($data);

            /** @phpstan-ignore-next-line */
            $this->callMountedAction($arguments);

            return $this;
        };
    }

    public function callMountedAction(): Closure
    {
        return function (array $arguments = []): static {
            $action = $this->instance()->getMountedAction();

            if (! $action) {
                return $this;
            }

            $this->call('callMountedAction', $arguments);

            if (store($this->instance())->has('redirect')) {
                return $this;
            }

            if (blank($this->instance()->mountedActions)) {
                $this->assertDispatched('sync-action-modals', id: $this->instance()->getId(), newActionNestingIndex: null);
            }

            return $this;
        };
    }

    public function assertActionExists(): Closure
    {
        return function (string | TestAction | array $actions, ?Closure $checkActionUsing = null, ?Closure $generateMessageUsing = null): static {
            /** @var array<array<string, mixed>> $actions */
            /** @phpstan-ignore-next-line */
            $actions = $this->parseNestedActions($actions);

            $action = $this->instance()->getAction($actions);

            $livewireClass = $this->instance()::class;
            $prettyName = implode(' > ', Arr::pluck($actions, 'name'));

            Assert::assertInstanceOf(
                Action::class,
                $action,
                message: $generateMessageUsing ?
                    $generateMessageUsing($prettyName, $livewireClass) :
                    "Failed asserting that an action with name [{$prettyName}] exists on the [{$livewireClass}] component.",
            );

            if ($checkActionUsing) {
                Assert::assertTrue(
                    $checkActionUsing($action),
                    $generateMessageUsing ?
                        $generateMessageUsing($prettyName, $livewireClass) :
                        "Failed asserting that an action with the name [{$prettyName}] and provided configuration exists on the [{$livewireClass}] component.",
                );
            }

            return $this;
        };
    }

    public function assertActionDoesNotExist(): Closure
    {
        return function (string | TestAction | array $actions, ?Closure $checkActionUsing = null, ?Closure $generateMessageUsing = null): static {
            /** @var array<array<string, mixed>> $actions */
            /** @phpstan-ignore-next-line */
            $actions = $this->parseNestedActions($actions);

            try {
                $action = $this->instance()->getAction($actions);
            } catch (ActionNotResolvableException $exception) {
                Assert::assertNull(null);

                return $this;
            }

            $livewireClass = $this->instance()::class;
            $prettyName = implode(' > ', Arr::pluck($actions, 'name'));

            if (! $action) {
                Assert::assertNull($action);
            }

            if ($checkActionUsing) {
                Assert::assertFalse(
                    $checkActionUsing($action),
                    $generateMessageUsing ?
                        $generateMessageUsing($prettyName, $livewireClass) :
                        "Failed asserting that an action with the name [{$prettyName}] and provided configuration does not exist on the [{$livewireClass}] component.",
                );
            } else {
                Assert::assertNotInstanceOf(
                    Action::class,
                    $action,
                    message: $generateMessageUsing ?
                        $generateMessageUsing($prettyName, $livewireClass) :
                        "Failed asserting that an action with the name [{$prettyName}] does not exist on the [{$livewireClass}] component.",
                );
            }

            return $this;
        };
    }

    public function assertActionVisible(): Closure
    {
        return function (string | TestAction | array $actions): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->isVisible(),
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] is visible on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionHidden(): Closure
    {
        return function (string | TestAction | array $actions): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->isHidden(),
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] is hidden on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionEnabled(): Closure
    {
        return function (string | TestAction | array $actions): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->isEnabled(),
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] is enabled on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionDisabled(): Closure
    {
        return function (string | TestAction | array $actions): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->isDisabled(),
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] is disabled on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionHasIcon(): Closure
    {
        return function (string | TestAction | array $actions, string $icon): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->getIcon() === $icon,
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] has icon [{$icon}] on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionDoesNotHaveIcon(): Closure
    {
        return function (string | TestAction | array $actions, string $icon): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->getIcon() !== $icon,
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] does not have icon [{$icon}] on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionHasLabel(): Closure
    {
        return function (string | TestAction | array $actions, string $label): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->getLabel() === $label,
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] has label [{$label}] on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionDoesNotHaveLabel(): Closure
    {
        return function (string | TestAction | array $actions, string $label): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->getLabel() !== $label,
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] does not have label [{$label}] on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionHasColor(): Closure
    {
        return function (string | TestAction | array $actions, string | array $color): static {
            $colorName = is_string($color) ? $color : 'custom';

            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->getColor() === $colorName,
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] has color [{$color}] on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionDoesNotHaveColor(): Closure
    {
        return function (string | TestAction | array $actions, string | array $color): static {
            $colorName = is_string($color) ? $color : 'custom';

            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->getColor() !== $colorName,
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] does not have color [{$color}] on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionHasUrl(): Closure
    {
        return function (string | TestAction | array $actions, string $url): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->getUrl() === $url,
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] has URL [{$url}] on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionDoesNotHaveUrl(): Closure
    {
        return function (string | TestAction | array $actions, string $url): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->getUrl() !== $url,
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] does not have URL [{$url}] on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionShouldOpenUrlInNewTab(): Closure
    {
        return function (string | TestAction | array $actions): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => $action->shouldOpenUrlInNewTab(),
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] should open url in new tab on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionShouldNotOpenUrlInNewTab(): Closure
    {
        return function (string | TestAction | array $actions): static {
            $this->assertActionExists(
                $actions,
                checkActionUsing: fn (Action $action): bool => ! $action->shouldOpenUrlInNewTab(),
                generateMessageUsing: fn (string $prettyName, string $livewireClass): string => "Failed asserting that an action with name [{$prettyName}] should not open url in new tab on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function assertActionMounted(): Closure
    {
        return function (string | TestAction | array $actions = []): static {
            if (empty($actions)) {
                $this->assertNotSet('mountedActions', []);

                return $this;
            }

            /** @phpstan-ignore-next-line */
            $this->assertActionExists($actions);

            /** @var array<array<string, mixed>> $actions */
            /** @phpstan-ignore-next-line */
            $actions = $this->parseNestedActions($actions);

            $actionNestingIndexOffset = count($this->instance()->mountedActions) - count($actions);

            foreach ($actions as $actionNestingIndex => $action) {
                $actionNestingIndex += $actionNestingIndexOffset;

                $this->assertSet(
                    "mountedActions.{$actionNestingIndex}.name",
                    $action['name'],
                );

                $this->assertSet(
                    "mountedActions.{$actionNestingIndex}.arguments",
                    $action['arguments'] ?? [],
                );

                $this->assertSet(
                    "mountedActions.{$actionNestingIndex}.context",
                    $action['context'] ?? [],
                );
            }

            return $this;
        };
    }

    public function assertActionNotMounted(): Closure
    {
        return function (string | TestAction | array $actions = []): static {
            if (empty($actions)) {
                $this->assertSet('mountedActions', []);

                return $this;
            }

            /** @phpstan-ignore-next-line */
            $this->assertActionExists($actions);

            /** @var array<array<string, mixed>> $actions */
            /** @phpstan-ignore-next-line */
            $actions = $this->parseNestedActions($actions);

            $actionNestingIndexOffset = count($this->instance()->mountedActions) - count($actions);

            foreach ($actions as $actionNestingIndex => $action) {
                $actionNestingIndex += $actionNestingIndexOffset;

                $this->assertNotSet(
                    "mountedActions.{$actionNestingIndex}.name",
                    $action['name'],
                );

                $this->assertNotSet(
                    "mountedActions.{$actionNestingIndex}.arguments",
                    $action['arguments'] ?? [],
                );

                $this->assertNotSet(
                    "mountedActions.{$actionNestingIndex}.context",
                    $action['context'] ?? [],
                );
            }

            return $this;
        };
    }

    public function assertActionHalted(): Closure
    {
        return $this->assertActionMounted();
    }

    /**
     * @deprecated Use `assertActionHalted()` instead.
     */
    public function assertActionHeld(): Closure
    {
        return $this->assertActionHalted();
    }

    public function assertHasActionErrors(): Closure
    {
        return function (array $keys = []): static {
            $this->assertHasErrors(
                collect($keys)
                    ->mapWithKeys(function ($value, $key): array {
                        if (is_int($key)) {
                            return [$key => 'mountedActions.' . array_key_last($this->instance()->mountedActions) . '.data.' . $value];
                        }

                        return ['mountedActions.' . array_key_last($this->instance()->mountedActions) . '.data.' . $key => $value];
                    })
                    ->all(),
            );

            return $this;
        };
    }

    public function assertHasNoActionErrors(): Closure
    {
        return function (array $keys = []): static {
            $this->assertHasNoErrors(
                collect($keys)
                    ->mapWithKeys(function ($value, $key): array {
                        if (is_int($key)) {
                            return [$key => 'mountedActions.' . array_key_last($this->instance()->mountedActions) . '.data.' . $value];
                        }

                        return ['mountedActions.' . array_key_last($this->instance()->mountedActions) . '.data.' . $key => $value];
                    })
                    ->all(),
            );

            return $this;
        };
    }

    public function assertActionListInOrder(): Closure
    {
        return function (array $names, array $actions, string $actionType, string $actionClass): self {
            $livewireClass = $this->instance()::class;

            /** @var array<string> $names */
            /** @phpstan-ignore-next-line */
            $names = array_map(fn (string $name): string => $this->parseActionName($name), $names);
            $namesIndex = 0;

            $actions = array_reduce(
                $actions,
                function (array $carry, StaticAction | ActionGroup $action): array {
                    if ($action instanceof ActionGroup) {
                        return [
                            ...$carry,
                            ...$action->getFlatActions(),
                        ];
                    }

                    $carry[$action->getName()] = $action;

                    return $carry;
                },
                initial: [],
            );

            foreach ($actions as $actionName => $action) {
                if ($namesIndex === count($names)) {
                    break;
                }

                if ($names[$namesIndex] !== $actionName) {
                    continue;
                }

                Assert::assertInstanceOf(
                    $actionClass,
                    $action,
                    message: "Failed asserting that a {$actionType} action with name [{$actionName}] exists on the [{$livewireClass}] component.",
                );

                $namesIndex++;
            }

            Assert::assertEquals(
                count($names),
                $namesIndex,
                message: "Failed asserting that a {$actionType} actions with names [" . implode(', ', $names) . "] exist in order on the [{$livewireClass}] component.",
            );

            return $this;
        };
    }

    public function parseActionName(): Closure
    {
        return function (string $name): string {
            if (! class_exists($name)) {
                return $name;
            }

            if (! is_subclass_of($name, MountableAction::class)) {
                return $name;
            }

            return $name::getDefaultName();
        };
    }

    public function parseNestedActionName(): Closure
    {
        return function (string | array $name): array {
            if (is_string($name)) {
                $name = explode('.', $name);
            }

            foreach ($name as $actionNestingIndex => $actionName) {
                if (! class_exists($actionName)) {
                    continue;
                }

                if (! is_subclass_of($actionName, MountableAction::class)) {
                    continue;
                }

                $name[$actionNestingIndex] = $actionName::getDefaultName();
            }

            return $name;
        };
    }

    public function parseNestedActions(): Closure
    {
        return function (string | TestAction | array $actions, array $arguments = []): array {
            if (is_string($actions)) {
                $actions = str($actions)
                    ->explode('.')
                    ->map(fn (string $name): array => [
                        'name' => $name,
                    ])
                    ->all();
            } elseif (
                ($actions instanceof TestAction) ||
                array_key_exists('name', $actions)
            ) {
                $actions = [$actions];
            }

            $areArgumentsKeyedByActionName = false;

            foreach ($actions as $actionNestingIndex => $action) {
                if ($action instanceof TestAction) {
                    $action = $action->toArray();
                }

                $actionName = $action['name'] ?? throw new Exception("Action name at index [{$actionNestingIndex}] is not specified.");

                if (
                    class_exists($actionName) &&
                    is_subclass_of($actionName, MountableAction::class)
                ) {
                    $action['name'] = $actionName = $actionName::getDefaultName();
                }

                if (filled($arguments) && (! array_key_exists('arguments', $action))) {
                    if (array_key_exists($actionName, $arguments)) {
                        $action['arguments'] = $arguments[$actionName];

                        $areArgumentsKeyedByActionName = true;
                    } elseif (! $areArgumentsKeyedByActionName) {
                        $action['arguments'] = [
                            ...$arguments,
                            ...$action['arguments'] ?? [],
                        ];
                    }
                }

                if (($actionNestingIndex > 0) && filled($schemaComponent = $action['context']['schemaComponent'] ?? null)) {
                    $action['context']['schemaComponent'] = 'mountedActions.' . ($actionNestingIndex - 1) . ".data.{$schemaComponent}";
                }

                $actions[$actionNestingIndex] = $action;
            }

            return $actions;
        };
    }
}
