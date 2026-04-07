<?php

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Enums\PriceType;
use App\Enums\VariationType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name_local')
                    ->label('Name (local)')
                    ->required()
                    ->maxLength(255),

                TextInput::make('name_en')
                    ->label('Name (English)')
                    ->maxLength(255),

                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),

                Repeater::make('items')
                    ->relationship('items')
                    ->orderColumn('sort_order')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name_local')
                            ->label('Name (local)')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('name_en')
                            ->label('Name (English)')
                            ->maxLength(255),

                        Textarea::make('description_local')
                            ->label('Description (local)')
                            ->rows(2),

                        Textarea::make('description_en')
                            ->label('Description (English)')
                            ->rows(2),

                        FileUpload::make('image')
                            ->label('Photo')
                            ->image()
                            ->disk('public')
                            ->directory('menu-items')
                            ->maxSize(4096)
                            ->columnSpanFull(),

                        Toggle::make('starred')
                            ->label('Starred'),

                        Select::make('price_type')
                            ->label('Price Type')
                            ->options([
                                PriceType::Fixed->value => 'Fixed',
                                PriceType::Range->value => 'Range',
                                PriceType::From->value => 'From',
                                PriceType::Variable->value => 'Variable',
                            ])
                            ->default(PriceType::Fixed->value)
                            ->required(),

                        TextInput::make('price_value')
                            ->label('Price')
                            ->numeric()
                            ->step(0.01),

                        TextInput::make('price_min')
                            ->label('Price Min')
                            ->numeric()
                            ->step(0.01),

                        TextInput::make('price_max')
                            ->label('Price Max')
                            ->numeric()
                            ->step(0.01),

                        TextInput::make('price_unit')
                            ->label('Price Unit (local)')
                            ->maxLength(50),

                        TextInput::make('price_original_text')
                            ->label('Original Price Text')
                            ->maxLength(255),

                        Repeater::make('variations')
                            ->relationship('variations')
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->columnSpanFull()
                            ->schema([
                                Select::make('type')
                                    ->options([
                                        VariationType::Portion->value => 'Portion',
                                        VariationType::Size->value => 'Size',
                                        VariationType::SpiceLevel->value => 'Spice Level',
                                        VariationType::Sauce->value => 'Sauce',
                                        VariationType::Base->value => 'Base',
                                        VariationType::Flavor->value => 'Flavor',
                                        VariationType::Unit->value => 'Unit',
                                    ])
                                    ->default(VariationType::Portion->value)
                                    ->required(),

                                TextInput::make('name_local')
                                    ->label('Name (local)')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('name_en')
                                    ->label('Name (English)')
                                    ->maxLength(255),

                                Toggle::make('required')
                                    ->label('Required'),

                                Toggle::make('allow_multiple')
                                    ->label('Allow Multiple'),

                                Repeater::make('options')
                                    ->relationship('options')
                                    ->orderColumn('sort_order')
                                    ->collapsible()
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('name_local')
                                            ->label('Name (local)')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('name_en')
                                            ->label('Name (English)')
                                            ->maxLength(255),

                                        TextInput::make('price_adjust')
                                            ->label('Price Adjust')
                                            ->numeric()
                                            ->step(0.01)
                                            ->default(0),

                                        Toggle::make('is_default')
                                            ->label('Default'),
                                    ]),
                            ]),

                        Repeater::make('optionGroups')
                            ->relationship('optionGroups')
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->columnSpanFull()
                            ->schema([
                                TextInput::make('name_local')
                                    ->label('Name (local)')
                                    ->required()
                                    ->maxLength(255),

                                TextInput::make('name_en')
                                    ->label('Name (English)')
                                    ->maxLength(255),

                                TextInput::make('min_select')
                                    ->label('Min Select')
                                    ->numeric()
                                    ->default(0),

                                TextInput::make('max_select')
                                    ->label('Max Select')
                                    ->numeric(),

                                Repeater::make('options')
                                    ->relationship('options')
                                    ->orderColumn('sort_order')
                                    ->collapsible()
                                    ->columnSpanFull()
                                    ->schema([
                                        TextInput::make('name_local')
                                            ->label('Name (local)')
                                            ->required()
                                            ->maxLength(255),

                                        TextInput::make('name_en')
                                            ->label('Name (English)')
                                            ->maxLength(255),

                                        TextInput::make('price_adjust')
                                            ->label('Price Adjust')
                                            ->numeric()
                                            ->step(0.01)
                                            ->default(0),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('name_local')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('name_en')
                    ->label('English Name'),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),
            ])
            ->defaultSort('sort_order')
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
