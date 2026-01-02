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
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;

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

                        // Tab 2: About - Group->relationship with RichEditor
                        // BUG #2: $rawState type error when saving without editing
                        Tabs\Tab::make('About')
                            ->schema([
                                Group::make()
                                    ->relationship('data')
                                    ->schema([
                                        RichEditor::make('bio')
                                            ->label('Biography')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        // Tab 3: Press Release - ANOTHER Group->relationship
                        // BUG #1: foreach() error when image stored as string
                        // Multiple tabs with Group->relationship creates the conflict
                        Tabs\Tab::make('Press Release')
                            ->schema([
                                Group::make()
                                    ->relationship('data')
                                    ->schema([
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

                        // Tab 4: Extra tab with Group->relationship to trigger Bug #1
                        // Having 3+ tabs with Group->relationship('data') creates the state conflict
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
                                                    ->maxLength(255),
                                            ])
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
