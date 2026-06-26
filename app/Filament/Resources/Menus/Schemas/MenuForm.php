<?php

namespace App\Filament\Resources\Menus\Schemas;

use App\Models\Menu;
use App\Models\Restaurant;
use App\Support\LanguageOptions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                        ->mapWithKeys(fn ($r) => [$r->id => "#$r->id ".($r->name ?? "Restaurant #{$r->id}")])
                        ->toArray()
                    )
                    ->searchable()
                    ->required()
                    ->unique(table: 'menus', column: 'restaurant_id', ignoreRecord: true)
                    ->validationMessages([
                        'unique' => 'This restaurant already has a menu.',
                    ]),

                Select::make('source_locale')
                    ->label('Source language')
                    ->options(LanguageOptions::all())
                    ->searchable(),

                DatePicker::make('detected_date')
                    ->label('Detected Date'),

                TextInput::make('source_images_count')
                    ->label('Images')
                    ->numeric()
                    ->minValue(0)
                    ->default(0),

                Select::make('created_from_menu_id')
                    ->label('Cloned from menu')
                    ->options(fn (?Menu $record) => Menu::query()
                        ->when($record, fn ($q) => $q->whereKeyNot($record->getKey()))
                        ->orderBy('id')
                        ->with('restaurant')
                        ->get()
                        ->mapWithKeys(fn (Menu $m) => [
                            $m->id => "#$m->id ".($m->restaurant?->name ?? "Restaurant #{$m->restaurant_id}"),
                        ])
                        ->toArray()
                    )
                    ->searchable(),
            ]);
    }
}
