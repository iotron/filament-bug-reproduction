<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArtistResource\Pages;
use App\Models\Artist;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ArtistResource extends Resource
{
    protected static ?string $model = Artist::class;

    protected static ?string $navigationIcon = 'heroicon-o-user';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Basic Info')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                    ]),

                // BUG #18718: RichEditor in HTML mode (not JSON) with file attachments
                // Steps to reproduce:
                // 1. Create artist with some bio content (optionally add an image attachment)
                // 2. Edit the artist and change the name (don't touch bio)
                // 3. Save - TypeError will be thrown
                Forms\Components\Section::make('Biography (Bug #18718)')
                    ->description('RichEditor stored as HTML with Spatie Media attachments. Edit name only and save to trigger bug.')
                    ->schema([
                        Forms\Components\RichEditor::make('bio')
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('bio-attachments')
                            ->fileAttachmentsVisibility('public')
                            ->columnSpanFull(),
                    ]),

                // BUG #18727: Repeater with SpatieMediaLibraryFileUpload in JSON column
                // Steps to reproduce:
                // 1. Add a gallery item with an image
                // 2. Save the artist
                // 3. Reload/edit the artist - foreach() error will be thrown
                Forms\Components\Section::make('Gallery (Bug #18727)')
                    ->description('Repeater with SpatieMediaLibraryFileUpload in JSON column. Upload image, save, then reload to trigger bug.')
                    ->schema([
                        Forms\Components\Repeater::make('gallery')
                            ->schema([
                                Forms\Components\TextInput::make('caption')
                                    ->required(),
                                SpatieMediaLibraryFileUpload::make('image')
                                    ->collection('gallery_images')
                                    ->image()
                                    ->imageEditor()
                                    ->visibility('public'),
                            ])
                            ->columns(2)
                            ->collapsible()
                            ->defaultItems(0),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArtists::route('/'),
            'create' => Pages\CreateArtist::route('/create'),
            'edit' => Pages\EditArtist::route('/{record}/edit'),
        ];
    }
}
