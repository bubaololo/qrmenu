<?php

namespace App\Filament\Resources\Halls\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class HallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),

                ColorColumn::make('color')->label('Color'),

                TextColumn::make('name')
                    ->placeholder('—')
                    ->searchable()
                    ->state(fn ($record) => $record->localizedText('name') ?? "Hall #{$record->id}"),

                TextColumn::make('restaurant.name')
                    ->label('Restaurant')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('tables_count')
                    ->label('Tables')
                    ->counts('tables')
                    ->sortable(),

                IconColumn::make('is_active')->label('Active')->boolean(),

                TextColumn::make('sort_order')->label('Order')->sortable(),

                TextColumn::make('created_at')->label('Created')->since()->sortable(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
