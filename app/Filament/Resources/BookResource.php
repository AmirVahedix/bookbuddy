<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BookResource\Pages;
use App\Filament\Resources\BookResource\RelationManagers;
use App\Models\Book;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class BookResource extends Resource
{
    protected static ?string $model = Book::class;

    protected static ?string $navigationIcon = 'heroicon-o-book-open';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('author')
                    ->maxLength(255),
                Forms\Components\Select::make('file_type')
                    ->options([
                        'pdf' => 'PDF',
                        'epub' => 'EPUB',
                    ])
                    ->required(),
                Forms\Components\Select::make('reading_status')
                    ->options([
                        'planned_for_future' => 'Planned For Future',
                        'currently_reading' => 'Currently Reading',
                        'finished' => 'Finished',
                    ])
                    ->required()
                    ->default('currently_reading'),
                Forms\Components\TextInput::make('total_pages')
                    ->numeric(),
                Forms\Components\TextInput::make('current_page')
                    ->required()
                    ->numeric()
                    ->default(0),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Creator')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('author')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('file_type')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('reading_status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_pages')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('current_page')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('file_type')
                    ->options([
                        'pdf' => 'PDF',
                        'epub' => 'EPUB',
                    ]),
                Tables\Filters\SelectFilter::make('reading_status')
                    ->options([
                        'planned_for_future' => 'Planned For Future',
                        'currently_reading' => 'Currently Reading',
                        'finished' => 'Finished',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\SectionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBooks::route('/'),
            'create' => Pages\CreateBook::route('/create'),
            'edit' => Pages\EditBook::route('/{record}/edit'),
        ];
    }
}
