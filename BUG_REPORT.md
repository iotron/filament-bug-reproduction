# Bug Report: State Type Errors in Group->relationship() with Filament v4

## Summary

When using form components inside a `Group::make()->relationship()`, combined with `Tabs` using `->persistTabInQueryString()`, the form throws type errors. This affects:

1. **FileUpload in Repeater**: `foreach() argument must be of type array|object, string given`
2. **RichEditor with HasRichContent**: `Argument #2 ($rawState) must be of type ?array, string given`

Both errors stem from state not being properly normalized when components are nested inside `Group->relationship()`.

## Environment

- **PHP Version**: 8.4.11
- **Laravel Version**: 11.47.0
- **Filament Version**: 4.3.1
- **Livewire Version**: 3.x
- **Database**: SQLite (also reproducible on MySQL)

## Reproduction Repository

https://github.com/iotron/filament-bug-reproduction

## Bug #1: FileUpload foreach() Error

### Setup

```php
// Model with JSON column
class ArtistData extends Model
{
    protected $casts = ['press_release' => 'array'];
}

// Form with Group->relationship + Repeater + FileUpload
Tabs::make('tabs')
    ->persistTabInQueryString()
    ->tabs([
        Tabs\Tab::make('Details')
            ->schema([
                Group::make()
                    ->relationship('data')
                    ->schema([
                        Repeater::make('press_release') // JSON column
                            ->schema([
                                FileUpload::make('image')
                                    ->directory('press-release'),
                            ]),
                    ]),
            ]),
    ]);
```

### Steps to Reproduce

1. Create artist with press_release data containing image as STRING:
   ```php
   $artist->data()->create([
       'press_release' => [
           ['title' => 'Test', 'image' => 'press-release/test.jpg']
       ]
   ]);
   ```

2. Navigate to edit page with tab query string:
   `http://localhost:8000/admin/artists/1/edit?tab=details::data::tab`

3. **Error thrown** during Livewire hydration

### Error

```
ErrorException: foreach() argument must be of type array|object, string given
at vendor/filament/forms/src/Components/BaseFileUpload.php:742
```

### Root Cause

`BaseFileUpload::getUploadedFiles()` expects state to be a UUID-keyed array like `['uuid-123' => 'path/to/file.jpg']`, but receives a plain string `'path/to/file.jpg'` from the JSON column.

---

## Bug #2: RichEditor $rawState Type Error (PR #18718)

### Setup

```php
// Model implementing HasRichContent
class ArtistData extends Model implements HasMedia, HasRichContent
{
    use InteractsWithMedia, InteractsWithRichContent;

    public function setUpRichContent(): void
    {
        $this->registerRichContent('bio')
            ->fileAttachmentProvider(
                SpatieMediaLibraryFileAttachmentProvider::make()
                    ->collection('bio-attachments')
            );
    }
}

// Form with RichEditor inside Group->relationship
Group::make()
    ->relationship('data')
    ->schema([
        RichEditor::make('bio'),
    ]);
```

### Steps to Reproduce

1. Create artist with bio content: `'<p>Test bio content</p>'`
2. Edit the artist
3. Change ONLY the name field (don't touch RichEditor)
4. Click "Save changes"
5. **Error thrown** during form save

### Error

```
TypeError: Filament\Forms\Components\RichEditor::{closure}():
Argument #2 ($rawState) must be of type ?array, string given
at vendor/filament/forms/src/Components/RichEditor.php:346
```

### Root Cause

When saving a form without editing the RichEditor field, the state normalization doesn't run. The `$rawState` parameter in the `afterStateHydrated` closure receives the HTML string from the database instead of the expected `?array`.

---

## Related PRs

- **PR #18718**: RichEditor type error fix (closed pending reproduction repo)
- **PR #18727**: Repeater StateCasts issue

## Potential Fixes

### Fix 1: FileUpload State Guard

In `BaseFileUpload::getUploadedFiles()`:

```php
$state = $this->getState();

if (! is_array($state)) {
    $state = filled($state) ? [Str::uuid()->toString() => $state] : [];
}
```

### Fix 2: RichEditor Type Flexibility

In `RichEditor.php` line 346:

```php
// Change from:
->afterStateHydrated(static function (RichEditor $component, $state, ?array $rawState): void {

// To:
->afterStateHydrated(static function (RichEditor $component, $state, mixed $rawState): void {
```

### Fix 3: Root Fix in Group->relationship()

The proper fix should be in how `Group::make()->relationship()` handles state hydration and dehydration for child components during Livewire update cycles.

## Additional Context

Both bugs require:
- `Tabs` with `->persistTabInQueryString()`
- Form components inside `Group::make()->relationship()`
- Existing data in the database
- Livewire update cycles (tab switching or form saving)
