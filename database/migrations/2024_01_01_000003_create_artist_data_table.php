<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Migration for artist_data table - stores related data for Artist
 *
 * This table is used to reproduce Filament bugs with Group->relationship()
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artist_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('artist_id')->constrained()->cascadeOnDelete();
            $table->text('bio')->nullable();        // RichEditor content
            $table->json('press_release')->nullable(); // Repeater with FileUpload
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artist_data');
    }
};
