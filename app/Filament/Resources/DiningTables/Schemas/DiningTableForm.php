<?php

namespace App\Filament\Resources\DiningTables\Schemas;

use App\Enums\DiningTableShape;
use App\Models\Zone;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class DiningTableForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('zone_id')
                ->label('Hall')
                ->options(Zone::query()->get()->pluck('name', 'id'))
                ->searchable()
                ->required(),

            TextInput::make('number')
                ->required()
                ->integer()
                ->minValue(1),

            TextInput::make('capacity')
                ->integer()
                ->default(4)
                ->minValue(1)
                ->maxValue(100),

            Select::make('shape')
                ->options(collect(DiningTableShape::cases())->mapWithKeys(
                    fn (DiningTableShape $s) => [$s->value => ucfirst(str_replace('_', ' ', $s->value))]
                ))
                ->default(DiningTableShape::Square->value),

            TextInput::make('x')->numeric()->nullable(),
            TextInput::make('y')->numeric()->nullable(),
            TextInput::make('width')->numeric()->nullable(),
            TextInput::make('height')->numeric()->nullable(),

            TextInput::make('rotation')
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->maxValue(360),

            TextInput::make('sort_order')
                ->label('Sort Order')
                ->integer()
                ->default(0)
                ->minValue(0),

            Toggle::make('is_active')->label('Active')->default(true),
        ]);
    }
}
