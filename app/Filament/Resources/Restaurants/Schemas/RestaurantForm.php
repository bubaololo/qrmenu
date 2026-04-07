<?php

namespace App\Filament\Resources\Restaurants\Schemas;

use Filament\Forms\Components\FileUpload;
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

                TextInput::make('name_local')
                    ->label('Name (local)')
                    ->maxLength(255),

                TextInput::make('name_en')
                    ->label('Name (English)')
                    ->maxLength(255),

                TextInput::make('city')
                    ->maxLength(255),

                TextInput::make('country')
                    ->maxLength(255),

                TextInput::make('address_local')
                    ->label('Address (local)')
                    ->maxLength(500),

                TextInput::make('address_en')
                    ->label('Address (English)')
                    ->maxLength(500),

                TextInput::make('district')
                    ->maxLength(255),

                TextInput::make('province')
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),

                TextInput::make('phone2')
                    ->label('Phone 2')
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
                    ->options([
                        'en' => 'English',
                        'vi' => 'Vietnamese',
                        'zh' => 'Chinese',
                        'ja' => 'Japanese',
                        'th' => 'Thai',
                        'ko' => 'Korean',
                    ])
                    ->default('en'),
            ]);
    }
}
