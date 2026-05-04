<?php

namespace App\Filament\Resources\Menus\Tables;

use App\Models\Menu;
use App\Models\Restaurant;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->query(fn () => Menu::query()->with('restaurant'))
            ->columns([
                TextColumn::make('restaurant_name')
                    ->label('Restaurant')
                    ->placeholder('—')
                    ->state(fn ($record) => $record->restaurant?->translate('name', $record->restaurant->primary_language ?? 'und') ?? "Restaurant #{$record->restaurant_id}"),

                TextColumn::make('detected_date')
                    ->label('Date')
                    ->date()
                    ->sortable(),

                TextColumn::make('source_images_count')
                    ->label('Images')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('restaurant_id')
                    ->label('Restaurant')
                    ->options(fn () => Restaurant::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($r) => [$r->id => "#$r->id ".($r->translate('name', $r->primary_language ?? 'und') ?? "Restaurant #{$r->id}")])
                        ->toArray()
                    ),
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
}
