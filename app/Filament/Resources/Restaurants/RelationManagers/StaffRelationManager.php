<?php

namespace App\Filament\Resources\Restaurants\RelationManagers;

use App\Enums\RestaurantUserRole;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StaffRelationManager extends RelationManager
{
    protected static string $relationship = 'restaurantUsers';

    protected static ?string $title = 'Staff';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('User')
                    ->options(fn () => User::query()
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn ($u) => [$u->id => ($u->name ?? $u->email)])
                        ->toArray()
                    )
                    ->searchable()
                    ->required(),

                Select::make('role')
                    ->options([
                        RestaurantUserRole::Owner->value => 'Owner',
                        RestaurantUserRole::Waiter->value => 'Waiter',
                    ])
                    ->default(RestaurantUserRole::Waiter->value)
                    ->required(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),

                TextColumn::make('role')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => $state instanceof RestaurantUserRole ? $state->value : (string) $state)
                    ->color(fn ($state): string => match ($state instanceof RestaurantUserRole ? $state->value : (string) $state) {
                        'owner' => 'warning',
                        default => 'gray',
                    }),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
