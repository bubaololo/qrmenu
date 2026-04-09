<?php

namespace App\Filament\Resources\Menus\RelationManagers;

use App\Enums\PriceType;
use App\Services\ImageProcessor;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Filament\Forms\Components\Select;
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
                TextInput::make('name')
                    ->label('Section Name')
                    ->maxLength(255)
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->label('Sort Order')
                    ->numeric()
                    ->default(0),

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
                            ->maxSize(10240)
                            ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, $record): string {
                                $processor = app(ImageProcessor::class);
                                $disk = config('image.disk');

                                if ($record?->image) {
                                    Storage::disk($disk)->delete($record->image);
                                    Storage::disk($disk)->delete($processor->thumbPath($record->image));
                                }

                                [$mainPath] = $processor->processAndStore(
                                    $file->getRealPath(),
                                    'menu-items',
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

                    ]),

                Repeater::make('optionGroups')
                    ->relationship('optionGroups')
                    ->orderColumn('sort_order')
                    ->collapsible()
                    ->label('Option Groups (shared across items in this section)')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('name')
                            ->label('Group Name')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        Toggle::make('is_variation')
                            ->label('Is Variation (mutually exclusive choices)'),

                        Toggle::make('required')
                            ->label('Required'),

                        Toggle::make('allow_multiple')
                            ->label('Allow Multiple'),

                        TextInput::make('type')
                            ->label('Type (e.g. size, spice)')
                            ->maxLength(100),

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
                                TextInput::make('name')
                                    ->label('Option Name')
                                    ->maxLength(255)
                                    ->columnSpanFull(),

                                TextInput::make('price_adjust')
                                    ->label('Price Adjust')
                                    ->numeric()
                                    ->step(0.01)
                                    ->default(0),

                                Toggle::make('is_default')
                                    ->label('Default'),
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

                TextColumn::make('name')
                    ->label('Name')
                    ->state(fn ($record) => $record->name ?? '—'),

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
