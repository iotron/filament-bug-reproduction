# Filament Bug Reproduction Repository

This repository provides a minimal reproduction for the following Filament 4 bugs:

## Bug 1: RichEditor Type Error (PR #18718)

**Issue:** `TypeError: Argument #2 ($rawState) must be of type ?array, string given`

**Related Issues:**
- https://github.com/filamentphp/filament/issues/17472
- https://github.com/filamentphp/filament/issues/18017
- https://github.com/filamentphp/filament/pull/18718

**Reproduction Steps:**
1. Create an Artist record with some HTML content in the `bio` RichEditor field
2. Optionally add an image attachment to the RichEditor
3. Save the record
4. Edit the record and change ANY other field (e.g., `name`), but do NOT touch the RichEditor
5. Save the form
6. **Expected:** Form saves successfully
7. **Actual:** TypeError is thrown

## Bug 2: Repeater StateCasts for JSON Columns (PR #18727)

**Issue:** `foreach() argument must be of type array|object, string given` in SpatieMediaLibraryFileUpload inside Repeater with JSON column

**Related Issues:**
- https://github.com/filamentphp/filament/issues/18726
- https://github.com/filamentphp/filament/pull/18727

**Reproduction Steps:**
1. Create an Artist record
2. Add an item to the `gallery` Repeater field
3. Upload an image using the SpatieMediaLibraryFileUpload component
4. Save the record
5. Reload/edit the record
6. **Expected:** Form loads with the uploaded image displayed
7. **Actual:** foreach() error is thrown because FileUpload receives raw string instead of UUID-keyed array

## Installation

```bash
# Clone the repository
git clone https://github.com/iotron/filament-bug-reproduction.git
cd filament-bug-reproduction

# Install dependencies
composer install
npm install && npm run build

# Setup environment
cp .env.example .env
php artisan key:generate

# Setup database (uses SQLite by default)
touch database/database.sqlite
php artisan migrate

# Create storage link for media
php artisan storage:link

# Create admin user
php artisan make:filament-user

# Run the server
php artisan serve
```

## Project Structure

```
app/
├── Models/
│   └── Artist.php              # Model with HasMedia trait, bio (text) and gallery (json)
├── Filament/
│   └── Resources/
│       └── ArtistResource.php  # Resource with RichEditor and Repeater+SpatieMediaLibraryFileUpload
database/
└── migrations/
    ├── create_artists_table.php
    └── create_media_table.php  # Spatie Media Library table
```

## Dependencies

- PHP 8.2+
- Laravel 11.x
- Filament 4.x
- Spatie Laravel Media Library 11.x
- Filament Spatie Media Library Plugin 4.x
- SQLite (default) or any database
