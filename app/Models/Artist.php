<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Artist model with fields that reproduce Filament bugs:
 * - bio: RichEditor field stored as HTML (not JSON) with file attachments - reproduces #18718
 * - gallery: JSON column with Repeater containing SpatieMediaLibraryFileUpload - reproduces #18727
 */
class Artist extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
        'bio',
        'gallery',
    ];

    protected $casts = [
        'gallery' => 'array',
    ];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bio_attachments')
            ->useDisk('public');

        $this->addMediaCollection('gallery_images')
            ->useDisk('public');
    }

    public function registerMediaConversions(?Media $media = null): void
    {
        $this->addMediaConversion('thumb')
            ->width(300)
            ->height(300)
            ->sharpen(10);
    }
}
