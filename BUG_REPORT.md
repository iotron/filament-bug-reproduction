# Bug Report: State Type Errors in Group->relationship() with Filament v4

## Summary

When using form components inside `Group::make()->relationship()` across **multiple tabs** with `Tabs->persistTabInQueryString()`, the form throws type errors:

1. **Bug #1 (PR #18727)**: FileUpload `foreach()` error when image stored as string in JSON
2. **Bug #2 (PR #18718)**: RichEditor `$rawState` type error when saving without editing

## Environment

- **PHP Version**: 8.4.11
- **Laravel Version**: 11.47.0
- **Filament Version**: 4.3.1 → **4.4.0** (tested both)
- **Livewire Version**: 3.x
- **Database**: SQLite

## Reproduction Repository

https://github.com/iotron/filament-bug-reproduction

## Critical Structure Pattern

Both bugs require **3+ tabs with `Group->relationship('data')`** accessing the same HasOne relationship:

```php
Tabs::make('tabs')
    ->persistTabInQueryString()
    ->tabs([
        Tabs\Tab::make('Basic Info')
            ->schema([
                TextInput::make('name'),
            ]),

        // Tab 2: About - Group->relationship with RichEditor
        // BUG #2: $rawState type error when saving without editing
        Tabs\Tab::make('About')
            ->schema([
                Group::make()
                    ->relationship('data')
                    ->schema([
                        RichEditor::make('bio')
                            ->label('Biography'),
                    ]),
            ]),

        // Tab 3: Press Release - Group->relationship with FileUpload
        // BUG #1: foreach() error when image stored as string
        Tabs\Tab::make('Press Release')
            ->schema([
                Group::make()
                    ->relationship('data')
                    ->schema([
                        Repeater::make('press_release')
                            ->schema([
                                TextInput::make('title'),
                                TextInput::make('link'),
                                FileUpload::make('image'),
                            ]),
                    ]),
            ]),

        // Tab 4: Gallery - 3rd Group->relationship (triggers Bug #1)
        // Having 3+ tabs with Group->relationship creates state conflict
        Tabs\Tab::make('Gallery')
            ->schema([
                Group::make()
                    ->relationship('data')
                    ->schema([
                        Repeater::make('gallery_items')
                            ->schema([
                                TextInput::make('caption'),
                            ]),
                    ]),
            ]),
    ]);
```

---

## Bug #1: FileUpload foreach() Error (PR #18727)

### Requirements

- Model with JSON column cast to array
- FileUpload inside Repeater storing image path as **string** (not UUID-keyed array)
- Multiple tabs with `Group->relationship('data')`

### Steps to Reproduce

1. Insert data with image as plain string:
   ```sql
   UPDATE artist_data SET press_release = '[{"title":"Test","link":"https://example.com","image":"press-release/test.jpg"}]'
   ```

2. Navigate to edit page: `http://localhost:8000/admin/artists/1/edit`

3. **Error on page load**

### Error

```
ErrorException: foreach() argument must be of type array|object, string given
at vendor/filament/forms/src/Components/BaseFileUpload.php:742
```

---

## Bug #2: RichEditor $rawState Type Error (PR #18718)

### Requirements

- Model implements `HasRichContent` with `fileAttachmentProvider`:
  ```php
  public function setUpRichContent(): void
  {
      $this->registerRichContent('bio')
          ->fileAttachmentProvider(
              SpatieMediaLibraryFileAttachmentProvider::make()
                  ->collection('bio-attachments')
          );
  }
  ```

### Steps to Reproduce

1. Ensure bio field has content: `<p>Test content</p>`
2. Navigate to edit page
3. Edit **only** the name field (don't touch RichEditor)
4. Click "Save changes"
5. **Error on save**

### Error

```
TypeError: Filament\Forms\Components\RichEditor::{closure}():
Argument #2 ($rawState) must be of type ?array, string given
at vendor/filament/forms/src/Components/RichEditor.php:346
```

---

## Root Cause Analysis

### The Problem

When multiple `Group::make()->relationship('data')` components access the same HasOne relationship:

1. **Each Group independently calls `fillFromRelationship()`** during form initialization
2. **Each fill OVERWRITES the entire Livewire state** at the shared state path (`data`)
3. **This undoes state normalization** performed by previous Groups

### Detailed Flow (Before Fix)

```
1. Group 2 (About) fills:
   - Sets data.data = {bio: "<p>Test</p>", press_release: [{image: "test.jpg"}], ...}
   - Hydrates RichEditor, normalizes bio state ✓

2. Group 3 (Press Release) fills:
   - Sets data.data = {bio: "<p>Test</p>", press_release: [{image: "test.jpg"}], ...}
   - OVERWRITES all state including RichEditor's normalized state
   - Hydrates FileUpload, normalizes image to {uuid: "test.jpg"} ✓

3. Group 4 (Gallery) fills:
   - Sets data.data = {bio: "<p>Test</p>", press_release: [{image: "test.jpg"}], ...}
   - OVERWRITES all state including FileUpload's normalized state ✗
   - FileUpload state is now back to string "test.jpg" instead of {uuid: "test.jpg"}

4. Livewire calls getUploadedFiles() on FileUpload:
   - getRawState() returns "test.jpg" (string)
   - foreach($this->getRawState() ?? []) fails with type error
```

### Why 3+ Groups Are Required

With only 2 Groups, the last Group's normalization persists. With 3+ Groups, the third Group's fill overwrites the second Group's normalized state before Livewire accesses it

## Verification Status

### Filament v4.3.1

| Bug | Status | Notes |
|-----|--------|-------|
| Bug #1 (PR #18727) | **CONFIRMED** | FileUpload foreach() error triggers with 3+ tabs using Group->relationship |
| Bug #2 (PR #18718) | **CONFIRMED** | RichEditor $rawState error triggers on save without editing |

### Filament v4.4.0 (Latest - Released Dec 30, 2025)

| Bug | Status | Notes |
|-----|--------|-------|
| Bug #1 (PR #18727) | **STILL EXISTS** | `foreach() argument must be of type array\|object, string given` at BaseFileUpload.php:742 |
| Bug #2 (PR #18718) | **STILL EXISTS** | `$rawState must be of type ?array, string given` at RichEditor.php:346 |

**Key Finding**: Bug #1 requires **3+ tabs** with `Group->relationship('data')` to trigger the state conflict.

---

## PR Status

Both PRs were **closed without being merged** (`merged_at: null`):

- **PR #18718**: RichEditor $rawState type error - Closed
- **PR #18727**: Repeater StateCasts / FileUpload foreach() error - Closed

The v4.4.0 release changelog does not include fixes for these issues.

---

## Recommended Fix Location

### Analysis

Both bugs stem from **Livewire state hydration issues** when multiple `Group::make()->relationship()` components access the same HasOne relationship across different tabs. Components in inactive tabs receive raw string state instead of properly normalized arrays.

### Where Should the Fix Go?

**Option A: Individual Components (Current PR Approach)**
- PR #18718: Add type handling in `RichEditor::setUp()`
- PR #18727: Add type handling in `BaseFileUpload::getUploadedFiles()`
- ✅ Minimal change, localized fix
- ❌ Doesn't address root cause - other components may have similar issues

**Option B: Group/EntanglesStateWithSingularRelationship Trait**
- Ensure state normalization in `EntanglesStateWithSingularRelationship` trait
- Properly sync state when multiple Groups access same relationship
- ✅ Fixes root cause
- ❌ More complex, higher risk

### Recommendation

**Both approaches should be applied:**

1. **Immediate defensive fix** - Components should handle unexpected string state gracefully (PRs #18718, #18727 should be reopened)
2. **Root cause fix** - `EntanglesStateWithSingularRelationship` should ensure child components always receive properly typed state

---

## Proposed Root Cause Fix (Option B)

The fix modifies `EntanglesStateWithSingularRelationship` trait to coordinate multiple Groups sharing the same relationship, mirroring the existing save logic coordination.

### Fix Location

`vendor/filament/schemas/src/Components/Concerns/EntanglesStateWithSingularRelationship.php`

### Key Changes

#### 1. Coordinate `loadStateFromRelationshipsUsing` (mirror save logic)

Replace the existing `loadStateFromRelationshipsUsing` callback in the `relationship()` method:

```php
$this->loadStateFromRelationshipsUsing(static function (Component | CanEntangleWithSingularRelationships $component): void {
    $component->clearCachedExistingRecord();

    // Find all layout components using this same relationship - mirror the save logic
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

    // First component sets raw state from model
    // Other components just hydrate their child schemas (state already set)
    $component->fillFromRelationship(shouldSetRawState: $isFirstComponent);
});
```

#### 2. Update `fillFromRelationship()` method

Add `$shouldSetRawState` parameter to control whether to set raw state or just hydrate:

```php
public function fillFromRelationship(bool $shouldSetRawState = true): void
{
    $record = $this->getCachedExistingRecord();

    if (! $record) {
        $this->getChildSchema()->fill(shouldCallHydrationHooks: false, shouldFillStateWithNull: false);
        return;
    }

    $data = $this->mutateRelationshipDataBeforeFill(
        $this->getStateFromRelatedRecord($record),
    );

    if ($shouldSetRawState) {
        $this->getChildSchema()->fill($data, shouldCallHydrationHooks: false, shouldFillStateWithNull: false);
    } else {
        // State is already set by another component with the same relationship
        // Just hydrate this component's child schema without setting raw state again
        $hydratedDefaultState = null;
        $this->getChildSchema()->hydrateState($hydratedDefaultState, shouldCallHydrationHooks: false);
    }

    // Ensure child component state is normalized through StateCasts
    $this->normalizeChildComponentState($this->getChildSchema());
}
```

#### 3. Add `normalizeChildComponentState()` helper method

This recursively applies StateCasts to ensure proper type normalization:

```php
/**
 * Recursively normalize child component state through their StateCasts.
 * This ensures components like FileUpload and RichEditor receive properly typed state.
 */
protected function normalizeChildComponentState(Schema $schema): void
{
    foreach ($schema->getComponents(withActions: false, withHidden: true) as $component) {
        if ($component->hasStatePath()) {
            $stateCasts = $component->getStateCasts();

            if (filled($stateCasts)) {
                $rawState = $component->getRawState();

                foreach ($stateCasts as $stateCast) {
                    $rawState = $stateCast->set($rawState);
                }

                $component->rawState($rawState);
            }
        }

        foreach ($component->getChildSchemas(withHidden: true) as $childSchema) {
            $this->normalizeChildComponentState($childSchema);
        }
    }
}
```

### How the Fix Works

**Before Fix (Problem):**
```
Group 2 fills → normalizes state ✓
Group 3 fills → OVERWRITES Group 2's normalized state, normalizes its own ✓
Group 4 fills → OVERWRITES Group 3's normalized state ✗
Result: Group 3's FileUpload has raw string state instead of normalized array
```

**After Fix (Solution):**
```
Group 2 fills (isFirstComponent=true) → sets raw state, hydrates, normalizes ✓
Group 3 fills (isFirstComponent=false) → only hydrates (state already set), normalizes ✓
Group 4 fills (isFirstComponent=false) → only hydrates (state already set), normalizes ✓
Result: All Groups have properly normalized state, no overwrites
```

The fix mirrors the existing save logic coordination pattern where all Groups using the same relationship are found, and only the first one performs the main operation.

---

## Verification Results

After applying the root cause fix:

| Bug | Status | Notes |
|-----|--------|-------|
| Bug #1 (PR #18727) | **FIXED** | FileUpload page loads without foreach() error |
| Bug #2 (PR #18718) | **FIXED** | RichEditor saves without $rawState type error |

**Test Procedure:**
1. Applied fix to `EntanglesStateWithSingularRelationship.php`
2. Cleared Laravel logs: `truncate -s 0 storage/logs/laravel.log`
3. Navigated to edit page with 3+ tabs using `Group->relationship('data')`
4. Edited name field only, clicked "Save changes"
5. Zero errors in logs

---

## Related PRs

- **PR #18718**: RichEditor $rawState type error (Closed, not merged)
- **PR #18727**: Repeater StateCasts / FileUpload foreach() error (Closed, not merged)

---

## Recommendation

The root cause fix in `EntanglesStateWithSingularRelationship` trait should be submitted as a new PR to Filament. This fix:

1. **Addresses the root cause** rather than individual component symptoms
2. **Mirrors existing patterns** - uses the same coordination logic as the save operation
3. **Prevents future issues** - other components with StateCasts will also benefit
4. **Minimal risk** - only changes behavior when multiple Groups share the same relationship
