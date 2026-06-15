<?php

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Enums\OptionGroupKind;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Menu-level library of variants and add-ons. Each group is defined once here
 * and reused across dishes via the per-dish picker in SectionsRelationManager.
 */
class OptionGroupsRelationManager extends RelationManager
{
    protected static string $relationship = 'optionGroups';

    protected static ?string $title = 'Варианты и добавки';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('kind')
                    ->label('Тип набора')
                    ->options([
                        OptionGroupKind::Variant->value => 'Вариант (выбор одного: горячий/холодный, размер)',
                        OptionGroupKind::Addon->value => 'Добавка (дополнения: доп. шот)',
                    ])
                    ->default(OptionGroupKind::Addon->value)
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('name')
                    ->label('Название')
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('type')
                    ->label('Подтип (напр. size, spice)')
                    ->maxLength(100),

                Toggle::make('required')
                    ->label('Обязательный'),

                Toggle::make('allow_multiple')
                    ->label('Можно выбрать несколько'),

                TextInput::make('min_select')
                    ->label('Мин. выбор')
                    ->numeric()
                    ->default(0),

                TextInput::make('max_select')
                    ->label('Макс. выбор')
                    ->numeric(),

                Repeater::make('options')
                    ->relationship('options')
                    ->label('Опции')
                    ->orderColumn('sort_order')
                    ->collapsible()
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Название опции')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('price_adjust')
                            ->label('Доплата')
                            ->numeric()
                            ->step(0.01)
                            ->default(0),

                        Toggle::make('is_default')
                            ->label('По умолчанию'),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->label('Название')
                    ->state(fn ($record): string => $record->name ?? '—'),

                TextColumn::make('kind')
                    ->label('Тип')
                    ->badge()
                    ->formatStateUsing(fn (OptionGroupKind $state): string => $state === OptionGroupKind::Variant ? 'Вариант' : 'Добавка')
                    ->color(fn (OptionGroupKind $state): string => $state === OptionGroupKind::Variant ? 'info' : 'gray'),

                TextColumn::make('options_count')
                    ->label('Опций')
                    ->counts('options'),

                TextColumn::make('items_count')
                    ->label('Блюд')
                    ->counts('items'),
            ])
            ->filters([
                SelectFilter::make('kind')
                    ->label('Тип')
                    ->options([
                        OptionGroupKind::Variant->value => 'Варианты',
                        OptionGroupKind::Addon->value => 'Добавки',
                    ]),
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
