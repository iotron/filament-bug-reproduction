<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArtistResource\Pages;
use App\Models\Artist;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;

class ArtistResource extends Resource
{
    protected static ?string $model = Artist::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('tabs')
                    ->persistTabInQueryString()
                    ->columnSpanFull()
                    ->contained(false)
                    ->tabs([
                        Tabs\Tab::make('Basic Info')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        // BUG #18727: This is the exact pattern that triggers the bug!
                        // Group with relationship('data') containing Repeater with JSON column
                        Tabs\Tab::make('Press Release')
                            ->schema([
                                Group::make()
                                    ->relationship('data')
                                    ->schema([
                                        RichEditor::make('bio')
                                            ->label('Biography')
                                            ->columnSpanFull(),

                                        Repeater::make('press_release')
                                            ->label('Press Releases')
                                            ->defaultItems(0)
                                            ->schema([
                                                TextInput::make('title')
                                                    ->required()
                                                    ->maxLength(255),
                                                TextInput::make('link')
                                                    ->url()
                                                    ->required()
                                                    ->maxLength(255),
                                                FileUpload::make('image')
                                                    ->multiple(false)
                                                    ->image()
                                                    ->maxFiles(1)
                                                    ->imageEditor()
                                                    ->imageCropAspectRatio('16:9')
                                                    ->directory('press-release')
                                                    ->maxSize(2048),
                                            ])
                                            ->columns(3)
                                            ->collapsible(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Gallery')
                            ->schema([
                                Group::make()
                                    ->relationship('data')
                                    ->schema([
                                        Repeater::make('gallery_items')
                                            ->label('Gallery Items')
                                            ->defaultItems(0)
                                            ->schema([
                                                TextInput::make('caption')
                                                    ->required(),
                                                SpatieMediaLibraryFileUpload::make('image')
                                                    ->collection('gallery_images')
                                                    ->image(),
                                            ])
                                            ->columns(2)
                                            ->collapsible(),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
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
