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
 * ArtistData model - stores data in HasOne relationship with Artist
 *
 * Key patterns that trigger bugs:
 * - Implements HasRichContent for RichEditor (Bug #2)
 * - Has JSON column 'press_release' cast to array (Bug #1)
 */
class ArtistData extends Model implements HasMedia, HasRichContent
{
    use InteractsWithMedia, InteractsWithRichContent;

    protected $table = 'artist_data';

    protected $fillable = [
        'artist_id',
        'bio',           // RichEditor content - Bug #2
        'press_release', // JSON with FileUpload paths - Bug #1
    ];

    protected $casts = [
        'press_release' => 'array',
    ];

    public function artist(): BelongsTo
    {
        return $this->belongsTo(Artist::class);
    }

    /**
     * Register 'bio' as rich content for RichEditor with fileAttachmentProvider.
     * BUG #2: When saving without editing RichEditor, $rawState receives string
     * instead of ?array, causing TypeError.
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
