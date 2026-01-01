# Bug Report: State Type Errors in Group->relationship() with JSON Columns

## Summary

When using form components (`FileUpload`, `RichEditor`) inside a `Repeater` that is wrapped in a `Group::make()->relationship()`, combined with `Tabs` using `->persistTabInQueryString()`, the form throws type errors during Livewire update cycles. This affects multiple components:

1. **FileUpload**: `foreach() argument must be of type array|object, string given`
2. **RichEditor**: `Argument #2 ($rawState) must be of type ?array, string given`

Both errors stem from the same root cause: state not being properly cast/normalized when components are nested inside `Group->relationship()` with JSON columns.

## Environment

- **PHP Version**: 8.4.11
- **Laravel Version**: 11.47.0
- **Filament Version**: 4.3.1
- **Livewire Version**: 3.x
- **Database**: SQLite (also reproducible on MySQL)

## Reproduction Repository

https://github.com/[your-username]/filament-bug-reproduction

## Steps to Reproduce

### 1. Database Setup

Create two related models:

```php
// Artist model
class Artist extends Model
{
    protected $fillable = ['name'];

    public function data(): HasOne
    {
        return $this->hasOne(ArtistData::class);
    }
}

// ArtistData model
class ArtistData extends Model
{
    protected $fillable = ['artist_id', 'bio', 'press_release'];

    protected $casts = [
        'press_release' => 'array',  // JSON column
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }
}
```

Migration for `artist_data`:
```php
Schema::create('artist_data', function (Blueprint $table) {
    $table->id();
    $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
    $table->longText('bio')->nullable();
    $table->json('press_release')->nullable();  // Stores repeater data
    $table->timestamps();
});
```

### 2. Filament Resource Form

```php
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;

class ArtistResource extends Resource
{
    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('tabs')
                    ->persistTabInQueryString()  // <-- Required to trigger bug
                    ->columnSpanFull()
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make('Basic Info')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        Tabs\Tab::make('Press Release')
                            ->schema([
                                Group::make()
                                    ->relationship('data')  // <-- HasOne relationship
                                    ->schema([
                                        RichEditor::make('bio')
                                            ->label('Biography')
                                            ->columnSpanFull(),

                                        Repeater::make('press_release')  // <-- JSON column
                                            ->label('Press Releases')
                                            ->defaultItems(0)
                                            ->schema([
                                                TextInput::make('title')
                                                    ->required()
                                                    ->maxLength(255),
                                                TextInput::make('link')
                                                    ->url()
                                                    ->required()
                                                    ->maxLength(255),
                                                FileUpload::make('image')  // <-- Triggers the bug
                                                    ->image()
                                                    ->directory('press-release')
                                                    ->maxSize(2048),
                                            ])
                                            ->columns(3)
                                            ->collapsible(),
                                    ]),
                            ]),
                    ]),
            ]);
    }
}
```

### 3. Create Test Data

Create an artist with press release data containing an uploaded image:

```php
$artist = Artist::create(['name' => 'Test Artist']);

$artist->data()->create([
    'bio' => '<p>Test bio</p>',
    'press_release' => [
        [
            'title' => 'Test Press Release',
            'link' => 'https://example.com',
            'image' => 'press-release/test-image.jpg',  // String path stored in JSON
        ],
    ],
]);
```

### 4. Trigger the Bug

1. Navigate to the artist edit page
2. The page loads on "Basic Info" tab (first tab)
3. Click on "Press Release" tab
4. **Error is thrown** during the Livewire update cycle

## Expected Behavior

The form should properly hydrate the `FileUpload` component state from the JSON column data and display the existing image without errors.

## Actual Behavior

### Error 1: FileUpload foreach() Error

When switching tabs or during Livewire updates:

```
foreach() argument must be of type array|object, string given
```

**Stack Trace:**
```
Filament\Forms\Components\BaseFileUpload::getUploadedFiles()
vendor/filament/forms/src/Components/BaseFileUpload.php:742

The relevant code:
foreach ($state as $fileKey => $file) {
    // $state is a string like "press-release/test-image.jpg"
    // but it should be an array like ['uuid-key' => 'press-release/test-image.jpg']
}
```

### Error 2: RichEditor Type Error

When saving the form:

```
TypeError: Filament\Forms\Components\RichEditor::{closure}():
Argument #2 ($rawState) must be of type ?array, string given
```

**Stack Trace:**
```
Filament\Forms\Components\RichEditor::{closure:setUp():346}()
vendor/filament/forms/src/Components/RichEditor.php:346

Called from:
vendor/filament/support/src/Concerns/EvaluatesClosures.php:36
```

The RichEditor's `setUp()` closure expects `$rawState` to be `?array` but receives a string when the component is inside a `Group->relationship()` that loads data from a related model.

## Root Cause Analysis

The issue appears to be in how form components handle state when:

1. The component is inside a `Group::make()->relationship()`
2. The related model has JSON columns or complex data types
3. `Tabs` with `->persistTabInQueryString()` triggers Livewire updates when switching tabs
4. During save operations, the state is not properly normalized

During Livewire update cycles, components receive raw values from the database/JSON columns instead of the properly transformed state they expect.

### Problem Location 1: FileUpload

In `BaseFileUpload.php` around line 742:

```php
public function getUploadedFiles(): array
{
    $state = $this->getState();

    // $state is expected to be: ['uuid-123' => 'press-release/image.jpg']
    // But it receives: 'press-release/image.jpg' (just a string)

    foreach ($state as $fileKey => $file) {  // <-- Fails here
        // ...
    }
}
```

### Problem Location 2: RichEditor

In `RichEditor.php` around line 346, the `setUp()` closure:

```php
->afterStateHydrated(static function (RichEditor $component, $state, ?array $rawState): void {
    // $rawState is expected to be ?array
    // But it receives a string (the HTML content from the bio column)
    // This happens because the component is inside Group->relationship()
    // and the state resolution passes the wrong type
});
```

The issue is that when RichEditor is inside `Group->relationship()`, the `$rawState` parameter receives the string value of the field instead of `null` or an array.

## Potential Fix Locations

### Fix 1: FileUpload State Guard

In `BaseFileUpload::getUploadedFiles()`, add a guard to handle string state gracefully:

```php
public function getUploadedFiles(): array
{
    $state = $this->getState();

    // Guard against non-array state
    if (! is_array($state)) {
        $state = filled($state) ? [Str::uuid()->toString() => $state] : [];
    }

    foreach ($state as $fileKey => $file) {
        // ...
    }
}
```

### Fix 2: RichEditor Type Flexibility

In `RichEditor.php` line 346, change the type hint to accept mixed:

```php
// Before:
->afterStateHydrated(static function (RichEditor $component, $state, ?array $rawState): void {

// After:
->afterStateHydrated(static function (RichEditor $component, $state, mixed $rawState): void {
    // Handle case where $rawState is a string
    if (is_string($rawState)) {
        $rawState = null;
    }
```

### Fix 3: Group->relationship() State Resolution

The root fix should be in how `Group::make()->relationship()` resolves and passes state to child components during Livewire updates. The state should be properly normalized before being passed to component closures.

## Workaround

Currently, no known workaround exists without modifying Filament source code.

## Related Issues

- PR #18727 (Repeater StateCasts) - Related to FileUpload state issues
- PR #18718 (RichEditor type error) - Directly related to RichEditor bug documented here

## Additional Context

The bugs occur when:
- `->persistTabInQueryString()` is enabled on Tabs
- Form components are inside a `Group::make()->relationship()`
- The related model has data stored in JSON columns or as strings
- Livewire update cycles occur (tab switching, form saving)

### FileUpload Specific:
- FileUpload is inside a Repeater with JSON column storage
- Image paths are stored as strings in JSON

### RichEditor Specific:
- RichEditor is inside `Group->relationship()`
- The `$rawState` parameter receives string instead of expected `?array`
- Error occurs during form save operation

Without `->persistTabInQueryString()`, the bugs may trigger less frequently but the underlying issue remains.
