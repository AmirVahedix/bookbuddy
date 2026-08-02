<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SummaryResource\Pages;
use App\Models\Summary;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SummaryResource extends Resource
{
    protected static ?string $model = Summary::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('book_id')
                    ->relationship('book', 'title')
                    ->required()
                    ->searchable()
                    ->preload(),
                Forms\Components\Select::make('book_section_id')
                    ->relationship('bookSection', 'title')
                    ->searchable()
                    ->preload(),
                Forms\Components\TextInput::make('target_pages')
                    ->helperText('JSON array or list of target pages (e.g. [1, 5])'),
                Forms\Components\Textarea::make('prompt_used')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('generated_summary')
                    ->required()
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('tokens_used')
                    ->numeric(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('Summary Metadata')
                    ->schema([
                        TextEntry::make('book.user.name')
                            ->label('User'),
                        TextEntry::make('book.title')
                            ->label('Book'),
                        TextEntry::make('bookSection.title')
                            ->label('Section')
                            ->placeholder('N/A'),
                        TextEntry::make('target_pages')
                            ->label('Target Pages')
                            ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : ($state ?? 'N/A')),
                        TextEntry::make('tokens_used')
                            ->label('Tokens Used'),
                        TextEntry::make('created_at')
                            ->dateTime(),
                    ])->columns(3),
                Section::make('Content')
                    ->schema([
                        TextEntry::make('prompt_used')
                            ->label('Prompt Used')
                            ->columnSpanFull(),
                        TextEntry::make('generated_summary')
                            ->label('Generated Summary')
                            ->markdown()
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->sortable(),
                Tables\Columns\TextColumn::make('book.user.name')
                    ->label('User')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('book.title')
                    ->label('Book')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('bookSection.title')
                    ->label('Section')
                    ->placeholder('N/A')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('target_pages')
                    ->label('Target Pages')
                    ->formatStateUsing(fn ($state) => is_array($state) ? implode(', ', $state) : ($state ?? '-')),
                Tables\Columns\TextColumn::make('tokens_used')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
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
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSummaries::route('/'),
            'create' => Pages\CreateSummary::route('/create'),
            'view' => Pages\ViewSummary::route('/{record}'),
            'edit' => Pages\EditSummary::route('/{record}/edit'),
        ];
    }
}
