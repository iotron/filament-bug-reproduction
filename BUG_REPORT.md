# Bug Report: State Overwrites in Multiple Group->relationship() Components

## Summary

When using multiple `Group::make()->relationship()` components accessing the same HasOne relationship across different tabs, subsequent Groups overwrite state normalization performed by earlier Groups, causing type errors in components like FileUpload and RichEditor.

## Environment

- **PHP**: 8.4.11
- **Laravel**: 11.47.0
- **Filament**: 4.4.0
- **Livewire**: 3.x

## Reproduction Repository

https://github.com/iotron/filament-bug-reproduction

## Reproduction Pattern

The bug requires **3+ tabs** with `Group->relationship('data')` accessing the same HasOne relationship:

```php
Tabs::make('tabs')
    ->persistTabInQueryString()
    ->tabs([
        Tabs\Tab::make('Basic Info')
            ->schema([TextInput::make('name')]),

        Tabs\Tab::make('About')
            ->schema([
                Group::make()->relationship('data')
                    ->schema([RichEditor::make('bio')]),
            ]),

        Tabs\Tab::make('Press Release')
            ->schema([
                Group::make()->relationship('data')
                    ->schema([
                        Repeater::make('press_release')
                            ->schema([
                                TextInput::make('title'),
                                FileUpload::make('image'),
                            ]),
                    ]),
            ]),

        Tabs\Tab::make('Gallery')  // 3rd Group->relationship triggers the bug
            ->schema([
                Group::make()->relationship('data')
                    ->schema([
                        Repeater::make('gallery_items')
                            ->schema([TextInput::make('caption')]),
                    ]),
            ]),
    ]);
```

## Errors Encountered

**Bug #1**: `foreach() argument must be of type array|object, string given` at `BaseFileUpload.php:742`

**Bug #2**: `Argument #2 ($rawState) must be of type ?array, string given` at `RichEditor.php:346`

## Root Cause

In `EntanglesStateWithSingularRelationship::relationship()`, the `loadStateFromRelationshipsUsing` callback calls `fillFromRelationship()` for **every** Group component:

```php
$this->loadStateFromRelationshipsUsing(static function ($component): void {
    $component->clearCachedExistingRecord();
    $component->fillFromRelationship();  // Called for EVERY Group
});
```

This causes:
1. Group 2 fills state and hydrates (normalizes FileUpload to UUID-keyed array)
2. Group 3 fills state - **overwrites** Group 2's normalized state with raw DB values
3. Group 4 fills state - **overwrites** Group 3's normalized state
4. FileUpload now has raw string instead of UUID-keyed array → `foreach()` error

## Proposed Fix

Modify `loadStateFromRelationshipsUsing` to coordinate multiple Groups (mirroring the existing `saveRelationshipsBeforeChildrenUsing` pattern):

```php
$this->loadStateFromRelationshipsUsing(static function (Component | CanEntangleWithSingularRelationships $component): void {
    $component->clearCachedExistingRecord();

    // Find all layout components using this same relationship
    $componentsWithThisRelationship = [];

    $findComponentsWithThisRelationship = function (Schema $schema) use ($component, &$componentsWithThisRelationship, &$findComponentsWithThisRelationship): void {
        foreach ($schema->getComponents(withActions: false, withHidden: true) as $childComponent) {
            if (
                ($childComponent->getModel() === $component->getModel()) &&
                ($childComponent->getRecord() === $component->getRecord()) &&
                ($childComponent instanceof CanEntangleWithSingularRelationships) &&
                ($childComponent->getRelationshipName() === $component->getRelationshipName()) &&
                ($childComponent->hasRelationship())
            ) {
                $componentsWithThisRelationship[] = $childComponent;
                continue;
            }

            foreach ($childComponent->getChildSchemas() as $schema) {
                $findComponentsWithThisRelationship($schema);
            }
        }
    };

    $findComponentsWithThisRelationship($component->getRootContainer());

    $isFirstComponent = blank($componentsWithThisRelationship) || (Arr::first($componentsWithThisRelationship) === $component);

    if ($isFirstComponent) {
        // First component fills from relationship (sets raw state + hydrates)
        $component->fillFromRelationship();
    } else {
        // State already set by first component - just hydrate (applies StateCasts)
        $hydratedDefaultState = null;
        $component->getChildSchema()->hydrateState($hydratedDefaultState, shouldCallHydrationHooks: false);
    }
});
```

## Why This Works

- **No method signature changes** - `fillFromRelationship()` remains unchanged
- **No new helper methods** - uses existing `hydrateState()` which already applies StateCasts
- **Mirrors existing pattern** - same coordination logic as `saveRelationshipsBeforeChildrenUsing`
- First component fills state from relationship, subsequent components only hydrate their child schemas

## Verification

After applying the fix, both bugs are resolved:
- Edit page loads without `foreach()` error
- Save without editing RichEditor succeeds without `$rawState` type error

## Related PRs (Closed Without Merge)

- PR #18718: RichEditor $rawState type error
- PR #18727: Repeater StateCasts / FileUpload foreach() error
