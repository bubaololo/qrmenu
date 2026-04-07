<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use App\Actions\CloneMenuAction;
use App\Filament\Resources\Menus\MenuResource;
use App\Models\Menu;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MenusRelationManager extends RelationManager
{
    protected static string $relationship = 'menus';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                DatePicker::make('detected_date')
                    ->label('Detected Date'),

                Toggle::make('is_active')
                    ->label('Active'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),

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
            ->recordUrl(fn (Menu $record): string => MenuResource::getUrl('edit', ['record' => $record]))
            ->headerActions([
                CreateAction::make(),
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
