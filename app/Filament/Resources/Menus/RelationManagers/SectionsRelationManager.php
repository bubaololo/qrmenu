<?php

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Enums\OptionGroupKind;
use App\Enums\PriceType;
use App\Models\Menu;
use App\Models\MenuOptionGroup;
use App\Models\MenuOptionGroupOption;
use App\Services\ImageProcessor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class SectionsRelationManager extends RelationManager
{
    protected static string $relationship = 'sections';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Section Name')
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),

                Toggle::make('is_active')
                    ->label('Visible to guests')
                    ->default(true),

                Repeater::make('items')
                    ->relationship('items')
                    ->orderColumn('sort_order')
                    ->collapsible()
                    ->itemLabel(fn (array $state): string => $state['name'] ?? $state['price_original_text'] ?? 'Item')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('description')
                            ->label('Description')
                            ->maxLength(500)
                            ->columnSpanFull(),

                        FileUpload::make('image')
                            ->label('Photo')
                            ->image()
                            ->disk(config('image.disk'))
                            ->maxSize(51200)
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, $record, $livewire): string {
                                $processor = app(ImageProcessor::class);
                                $disk = config('image.disk');

                                if ($record?->image) {
                                    Storage::disk($disk)->delete($record->image);
                                    Storage::disk($disk)->delete($processor->thumbPath($record->image));
                                }

                                // Nest under the menu id (owner record), matching the
                                // API and crop pipelines: menu-items/{menu_id}/.
                                $menuId = $livewire->getOwnerRecord()->getKey();

                                [$mainPath] = $processor->processAndStore(
                                    $file->getRealPath(),
                                    config('image.paths.menu_items').'/'.$menuId,
                                    Str::uuid()->toString(),
                                );

                                return $mainPath;
                            })
                            ->deleteUploadedFileUsing(function (?string $file) {
                                if (! $file) {
                                    return;
                                }
                                $processor = app(ImageProcessor::class);
                                $disk = config('image.disk');
                                Storage::disk($disk)->delete($file);
                                Storage::disk($disk)->delete($processor->thumbPath($file));
                            })
                            ->columnSpanFull(),

                        Toggle::make('starred')
                            ->label('Starred'),

                        Toggle::make('is_visible')
                            ->label('Visible to guests')
                            ->default(true),

                        Toggle::make('is_orderable')
                            ->label('Available to order')
                            ->helperText('Untick to keep showing the item but block ordering (e.g. out of stock).')
                            ->default(true),

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
                            ->label('Price Unit')
                            ->maxLength(50),

                        TextInput::make('price_original_text')
                            ->label('Original Price Text')
                            ->maxLength(255),

                        Select::make('optionGroups')
                            ->label('Варианты и добавки')
                            ->helperText('Выберите готовые наборы из этого меню или создайте новый — они переиспользуются между блюдами.')
                            ->relationship(
                                name: 'optionGroups',
                                modifyQueryUsing: fn (Builder $query, RelationManager $livewire) => $query
                                    ->where('menu_id', $livewire->getOwnerRecord()->getKey()),
                            )
                            ->getOptionLabelFromRecordUsing(fn (MenuOptionGroup $record): string => self::optionGroupLabel($record))
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->createOptionForm(self::optionGroupFormSchema())
                            ->createOptionUsing(fn (array $data, RelationManager $livewire): int => self::createOptionGroup($livewire->getOwnerRecord(), $data))
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /** Display label for an option group in the picker: name + kind badge. */
    public static function optionGroupLabel(MenuOptionGroup $group): string
    {
        $kind = $group->kind === OptionGroupKind::Variant ? 'Вариант' : 'Добавка';

        return trim(($group->name ?? '—').' · '.$kind);
    }

    /**
     * Schema for creating a new variant/add-on group inline from a dish.
     *
     * @return array<int, Component>
     */
    public static function optionGroupFormSchema(): array
    {
        return [
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
                ->label('Опции')
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
        ];
    }

    /**
     * Persist a new menu-level option group (with its options) from the inline
     * create form and return its primary key so the dish picker attaches it.
     *
     * @param  array<string, mixed>  $data
     */
    public static function createOptionGroup(Menu $menu, array $data): int
    {
        $group = new MenuOptionGroup([
            'menu_id' => $menu->id,
            'kind' => $data['kind'],
            'type' => $data['type'] ?? null,
            'required' => $data['required'] ?? false,
            'allow_multiple' => $data['allow_multiple'] ?? false,
            'min_select' => $data['min_select'] ?? 0,
            'max_select' => $data['max_select'] ?? null,
        ]);
        $group->name = $data['name'] ?? null;
        $group->save();

        foreach (array_values($data['options'] ?? []) as $sortOrder => $optData) {
            $option = new MenuOptionGroupOption([
                'price_adjust' => $optData['price_adjust'] ?? 0,
                'is_default' => $optData['is_default'] ?? false,
                'sort_order' => $sortOrder,
            ]);
            $option->name = $optData['name'] ?? null;
            $group->options()->save($option);
        }

        return $group->getKey();
    }

    public function table(Table $table): Table
    {
        return $table
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')
                    ->label('#')
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn ($record) => $record->name ?? '—'),

                TextColumn::make('items_count')
                    ->label('Items')
                    ->counts('items'),

                ToggleColumn::make('is_active')
                    ->label('Visible'),
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
