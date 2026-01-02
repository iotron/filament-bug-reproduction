# Bug Report: State Type Errors in Group->relationship() with Filament v4

## Summary

When using form components inside `Group::make()->relationship()` across **multiple tabs** with `Tabs->persistTabInQueryString()`, the form throws type errors:

1. **Bug #1 (PR #18727)**: FileUpload `foreach()` error when image stored as string in JSON
2. **Bug #2 (PR #18718)**: RichEditor `$rawState` type error when saving without editing

## Environment

- **PHP Version**: 8.4.11
- **Laravel Version**: 11.47.0
- **Filament Version**: 4.3.1
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

## Root Cause

Both bugs stem from Livewire state hydration issues when:
1. Multiple tabs each have `Group->relationship('data')` accessing the same HasOne relationship
2. `persistTabInQueryString()` causes Livewire update cycles during tab switching
3. State normalization doesn't run properly for child components in inactive tabs

## Verification Status

| Bug | Status | Notes |
|-----|--------|-------|
| Bug #1 (PR #18727) | **CONFIRMED** | FileUpload foreach() error triggers with 3+ tabs using Group->relationship |
| Bug #2 (PR #18718) | **CONFIRMED** | RichEditor $rawState error triggers on save without editing |

**Key Finding**: Bug #1 requires **3+ tabs** with `Group->relationship('data')` to trigger the state conflict.

## Related PRs

- **PR #18718**: RichEditor $rawState type error
- **PR #18727**: Repeater StateCasts / FileUpload foreach() error
