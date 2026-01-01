<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Artist model - minimal setup for bug reproduction
 *
 * The key is the HasOne relationship to ArtistData, used with
 * Group::make()->relationship('data') in the Filament form.
 */
class Artist extends Model
{
    protected $fillable = ['name'];

    /**
     * HasOne relationship to ArtistData.
     * This relationship is used with Group::make()->relationship('data')
     * which triggers the state type bugs in Filament.
     */
    public function data(): HasOne
    {
        return $this->hasOne(ArtistData::class);
    }
}
