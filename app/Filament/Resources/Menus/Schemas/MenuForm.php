<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Models\Restaurant;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class MenuForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('restaurant_id')
                    ->label('Restaurant')
                    ->options(fn () => Restaurant::query()
                        ->orderBy('id')
                        ->get()
                        ->mapWithKeys(fn ($r) => [$r->id => "#$r->id " . ($r->translate('name', $r->primary_language ?? 'und') ?? "Restaurant #{$r->id}")])
                        ->toArray()
                    )
                    ->searchable()
                    ->required(),

                DatePicker::make('detected_date')
                    ->label('Detected Date'),

                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Only one menu per restaurant can be active.'),
            ]);
    }
}
