<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use App\Filament\Resources\Halls\HallResource;
use App\Models\Hall;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HallsRelationManager extends RelationManager
{
    protected static string $relationship = 'halls';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            ColorPicker::make('color')->default('#6B7280'),
            TextInput::make('sort_order')->label('Sort Order')->integer()->default(0)->minValue(0),
            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                ColorColumn::make('color'),
                TextColumn::make('name')
                    ->placeholder('—')
                    ->state(fn (Hall $record) => $record->localizedText('name') ?? "Hall #{$record->id}"),
                TextColumn::make('tables_count')->label('Tables')->counts('tables')->sortable(),
                IconColumn::make('is_active')->label('Active')->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordUrl(fn (Hall $record): string => HallResource::getUrl('edit', ['record' => $record]))
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
