<?php

namespace App\Filament\Resources\DiningTables\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DiningTablesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID')->sortable(),

                TextColumn::make('hall.name')
                    ->label('Hall')
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('number')->sortable(),

                TextColumn::make('capacity')->label('Seats')->sortable(),

                TextColumn::make('shape')->badge(),

                IconColumn::make('is_active')->label('Active')->boolean(),

                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->defaultSort('hall_id')
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()]),
            ]);
    }
}
