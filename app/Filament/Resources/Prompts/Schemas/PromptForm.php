<?php

namespace App\Filament\Resources\Prompts\Schemas;

use App\Models\PromptType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class PromptForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('prompt_type_id')
                    ->label('Type')
                    ->relationship('promptType', 'name')
                    ->options(PromptType::query()->pluck('name', 'id'))
                    ->required(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Only one prompt per type can be active.'),

                Textarea::make('system_prompt')
                    ->label('System Prompt')
                    ->rows(4)
                    ->nullable()
                    ->columnSpanFull(),

                Textarea::make('user_prompt')
                    ->label('User Prompt')
                    ->rows(6)
                    ->required()
                    ->columnSpanFull(),
            ]);
    }
}
