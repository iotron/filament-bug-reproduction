<?php

namespace App\Models;

use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * ArtistData model - related model for Artist
 * NOW MATCHING JETPAX: HasMedia + HasRichContent to test if this triggers the bug!
 */
class ArtistData extends Model implements HasMedia, HasRichContent
{
    use InteractsWithMedia, InteractsWithRichContent;

    protected $table = 'artist_data';

    protected $fillable = [
        'artist_id',
        'bio',
        'press_release',
        'gallery_items',
    ];

    protected $casts = [
        'press_release' => 'array',
        'gallery_items' => 'array',
    ];

    /**
     * Columns that contain media in JSON format - matching JetPax
     */
    public const MEDIA_JSON_COLUMNS = [
        'press_release',
        'gallery_items',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Set up rich content - only register 'bio' as rich content.
     * press_release and gallery_items are NOT rich content.
     */
    public function setUpRichContent(): void
    {
        $this->registerRichContent('bio');
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('press_release_images')
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
