<?php

namespace App\Models;

use Filament\Forms\Components\RichEditor\FileAttachmentProviders\SpatieMediaLibraryFileAttachmentProvider;
use Filament\Forms\Components\RichEditor\Models\Concerns\InteractsWithRichContent;
use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

/**
 * ArtistData model - related model for Artist
 *
 * Implements HasRichContent for RichEditor file attachment support.
 * This setup triggers Bug #3 (PR #18718) when saving without editing RichEditor.
 */
class ArtistData extends Model implements HasMedia, HasRichContent
{
    use InteractsWithMedia, InteractsWithRichContent;

    protected $table = 'artist_data';

    protected $fillable = [
        'artist_id',
        'bio',           // RichEditor content - triggers Bug #2/#3
        'press_release', // JSON column with FileUpload - triggers Bug #1
    ];

    protected $casts = [
        'press_release' => 'array',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Set up rich content for RichEditor with Spatie Media Library.
     *
     * BUG #3 (PR #18718): When RichEditor uses fileAttachmentProvider and
     * the form is saved WITHOUT editing the RichEditor field, the $rawState
     * parameter receives a string instead of ?array, causing a TypeError.
     */
    public function setUpRichContent(): void
    {
        $this->registerRichContent('bio')
            ->fileAttachmentProvider(
                SpatieMediaLibraryFileAttachmentProvider::make()
                    ->collection('bio-attachments')
            );
    }

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('bio-attachments')
            ->useDisk('public');
    }
}
