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
2. Save the record
3. Edit the record and change ANY other field (e.g., `name`), but do NOT touch the RichEditor
4. Save the form
5. **Expected:** Form saves successfully
6. **Actual:** TypeError is thrown

## Bug 2: Repeater StateCasts for JSON Columns (PR #18727)

**Issue:** `foreach() argument must be of type array|object, string given` in FileUpload inside Repeater with JSON column

**Related Issues:**
- https://github.com/filamentphp/filament/issues/18726
- https://github.com/filamentphp/filament/pull/18727

**Reproduction Steps:**
1. Create an Artist record
2. Add an item to the `gallery` Repeater field
3. Upload an image in the FileUpload component
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

# Create admin user
php artisan make:filament-user

# Run the server
php artisan serve
```

## Project Structure

```
app/
├── Models/
│   └── Artist.php          # Model with bio (text) and gallery (json) columns
├── Filament/
│   └── Resources/
│       └── ArtistResource.php  # Resource demonstrating both bugs
database/
└── migrations/
    └── create_artists_table.php
```

## Environment

- PHP 8.2+
- Laravel 11.x
- Filament 4.x
- SQLite (default) or any database
