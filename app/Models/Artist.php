<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Artist model - reproduces Filament bug #18727
 *
 * Bug trigger: Group::make()->relationship('data') with Repeater inside
 * pointing to JSON columns on the related ArtistData model
 */
class Artist extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $fillable = [
        'name',
    ];

    /**
     * Relationship to ArtistData - this is key to reproducing the bug!
     * When used with Group::make()->relationship('data'), the Repeater
     * inside fails to apply StateCasts to JSON columns.
     */
    public function data(): HasOne
    {
        return $this->hasOne(ArtistData::class);
    }

    public function registerMediaCollections(): void
    {
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
