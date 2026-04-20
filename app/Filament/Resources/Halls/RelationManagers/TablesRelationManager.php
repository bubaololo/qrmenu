<?php

namespace App\Filament\Resources\Halls\RelationManagers;

use App\Enums\DiningTableShape;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TablesRelationManager extends RelationManager
{
    protected static string $relationship = 'tables';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('number')->required()->integer()->minValue(1),

            TextInput::make('capacity')->integer()->default(4)->minValue(1)->maxValue(100),

            Select::make('shape')
                ->options(collect(DiningTableShape::cases())->mapWithKeys(
                    fn (DiningTableShape $s) => [$s->value => ucfirst(str_replace('_', ' ', $s->value))]
                ))
                ->default(DiningTableShape::Square->value),

            TextInput::make('rotation')->numeric()->default(0)->minValue(0)->maxValue(360),

            TextInput::make('sort_order')->label('Sort Order')->integer()->default(0)->minValue(0),

            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')->sortable(),
                TextColumn::make('capacity')->label('Seats')->sortable(),
                TextColumn::make('shape')->badge(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('sort_order')->label('Order')->sortable(),
            ])
            ->defaultSort('sort_order')
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()])]);
    }
}
