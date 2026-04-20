<?php

namespace App\Filament\Resources\Halls\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class HallForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->required()
                ->maxLength(255),

            ColorPicker::make('color')
                ->default('#6B7280'),

            TextInput::make('sort_order')
                ->label('Sort Order')
                ->integer()
                ->default(0)
                ->minValue(0),

            Toggle::make('is_active')
                ->label('Active')
                ->default(true),
        ]);
    }
}
