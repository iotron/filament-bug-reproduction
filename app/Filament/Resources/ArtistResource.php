<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArtistResource\Pages;
use App\Models\Artist;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
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

/**
 * ArtistResource - Minimal setup to reproduce 3 Filament bugs
 *
 * BUG #1: FileUpload foreach() error
 *   - FileUpload inside Repeater with JSON column storage
 *   - Image path stored as string instead of UUID-keyed array
 *   - Error: "foreach() argument must be of type array|object, string given"
 *
 * BUG #2/#3: RichEditor $rawState type error (PR #18718)
 *   - RichEditor inside Group->relationship()
 *   - Model implements HasRichContent with fileAttachmentProvider
 *   - Saving form WITHOUT editing RichEditor triggers the error
 *   - Error: "Argument #2 ($rawState) must be of type ?array, string given"
 */
class ArtistResource extends Resource
{
    protected static ?string $model = Artist::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('tabs')
                    ->persistTabInQueryString() // Required to trigger bugs
                    ->columnSpanFull()
                    ->contained(false)
                    ->tabs([
                        // Tab 1: Basic Info (no relationship)
                        Tabs\Tab::make('Basic Info')
                            ->schema([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(255),
                            ]),

                        // Tab 2: Contains Group->relationship() which triggers bugs
                        Tabs\Tab::make('Details')
                            ->schema([
                                Group::make()
                                    ->relationship('data') // HasOne relationship
                                    ->schema([
                                        // BUG #2/#3: RichEditor with HasRichContent model
                                        // Saving without editing triggers $rawState type error
                                        RichEditor::make('bio')
                                            ->label('Biography')
                                            ->columnSpanFull(),

                                        // BUG #1: FileUpload in Repeater with JSON column
                                        // Image stored as string triggers foreach() error
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
                                                    ->image()
                                                    ->directory('press-release')
                                                    ->maxSize(2048),
                                            ])
                                            ->columns(3)
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
                TextColumn::make('name')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
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
