<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Artist model with fields that reproduce Filament bugs:
 * - bio: RichEditor field stored as HTML (not JSON) - reproduces #18718
 * - gallery: JSON column with Repeater containing FileUpload - reproduces #18727
 */
class Artist extends Model
{
    protected $fillable = [
        'name',
        'bio',
        'gallery',
    ];

    protected $casts = [
        'gallery' => 'array',
    ];
}
