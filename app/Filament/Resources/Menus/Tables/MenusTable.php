<?php

namespace App\Filament\Resources\Menus\Tables;

use App\Actions\CloneMenuAction;
use App\Models\Menu;
use App\Models\Restaurant;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class MenusTable
{
    public static function configure(Table $table): Table
    {
        return $table
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

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

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
                        ->mapWithKeys(fn ($r) => [$r->id => "#$r->id " . ($r->translate('name', $r->primary_language ?? 'und') ?? "Restaurant #{$r->id}")])
                        ->toArray()
                    ),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Set Active')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->hidden(fn (Menu $record): bool => $record->is_active)
                    ->action(function (Menu $record): void {
                        $record->activate();
                        Notification::make()->title('Menu activated')->success()->send();
                    }),

                Action::make('clone')
                    ->label('Clone')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->action(function (Menu $record): void {
                        $clone = app(CloneMenuAction::class)->handle($record);
                        Notification::make()->title("Cloned as Menu #{$clone->id}")->success()->send();
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
