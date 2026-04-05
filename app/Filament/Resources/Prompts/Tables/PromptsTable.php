<?php

namespace App\Filament\Resources\Prompts\Tables;

use App\Models\Prompt;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PromptsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('promptType.name')
                    ->label('Type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Updated')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('prompt_type_id')
            ->filters([
                SelectFilter::make('prompt_type_id')
                    ->label('Type')
                    ->relationship('promptType', 'name'),
            ])
            ->recordActions([
                Action::make('activate')
                    ->label('Set Active')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->hidden(fn (Prompt $record): bool => $record->is_active)
                    ->action(function (Prompt $record): void {
                        $record->activate();
                        Notification::make()->title('Prompt activated')->success()->send();
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
