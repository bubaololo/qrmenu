<?php

namespace App\Filament\Resources\Restaurants\Schemas;

use Filament\Forms\Components\FileUpload;
use Matriphe\ISO639\ISO639;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class RestaurantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image')
                    ->label('Logo / Cover Image')
                    ->image()
                    ->disk('public')
                    ->directory('restaurants')
                    ->maxSize(4096)
                    ->columnSpanFull(),

                Placeholder::make('name_note')
                    ->label('Name & Address')
                    ->content('Name and address are stored as translations and populated automatically from the Menu Analyzer.')
                    ->columnSpanFull(),

                TextInput::make('city')
                    ->maxLength(255),

                TextInput::make('country')
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),

                Select::make('currency')
                    ->options([
                        'USD' => 'USD',
                        'VND' => 'VND',
                        'EUR' => 'EUR',
                        'GBP' => 'GBP',
                        'JPY' => 'JPY',
                        'CNY' => 'CNY',
                        'THB' => 'THB',
                        'SGD' => 'SGD',
                    ])
                    ->default('USD'),

                Select::make('primary_language')
                    ->label('Primary Language')
                    ->options(function (): array {
                        $iso = new ISO639;
                        $options = [];
                        foreach ($iso->allLanguages() as $lang) {
                            if ($lang[0] !== '') {
                                $options[$lang[0]] = $lang[5] !== '' ? $lang[5] : $lang[4];
                            }
                        }
                        asort($options);

                        return $options;
                    })
                    ->searchable()
                    ->default('en'),
            ]);
    }
}
