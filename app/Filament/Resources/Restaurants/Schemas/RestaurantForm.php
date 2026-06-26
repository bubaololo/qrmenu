<?php

namespace App\Filament\Resources\Restaurants\Schemas;

use App\Services\ImageProcessor;
use App\Support\LanguageOptions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

class RestaurantForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('image')
                    ->label('Cover image (banner)')
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
                            config('image.paths.restaurants'),
                            Str::uuid()->toString(),
                            'banner',
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

                FileUpload::make('logo')
                    ->label('Logo')
                    ->image()
                    ->disk(config('image.disk'))
                    ->maxSize(10240)
                    ->saveUploadedFileUsing(function (TemporaryUploadedFile $file, $record): string {
                        $processor = app(ImageProcessor::class);
                        $disk = config('image.disk');

                        if ($record?->logo) {
                            Storage::disk($disk)->delete($record->logo);
                            Storage::disk($disk)->delete($processor->thumbPath($record->logo));
                        }

                        [$mainPath] = $processor->processAndStore(
                            $file->getRealPath(),
                            config('image.paths.logos'),
                            Str::uuid()->toString(),
                            'logo',
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

                TextInput::make('name')
                    ->maxLength(255),

                Select::make('created_by_user_id')
                    ->label('Owner')
                    ->relationship('creator', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Textarea::make('address')
                    ->rows(2)
                    ->columnSpanFull(),

                TextInput::make('city')
                    ->maxLength(255),

                TextInput::make('country')
                    ->maxLength(255),

                TextInput::make('phone')
                    ->tel()
                    ->maxLength(50),

                TextInput::make('google_maps_url')
                    ->label('Google Maps URL')
                    ->url()
                    ->maxLength(255),

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
                    ->options(LanguageOptions::all())
                    ->searchable()
                    ->default('en'),

                TextInput::make('max_languages')
                    ->label('Max additional languages')
                    ->numeric()
                    ->minValue(0)
                    ->helperText('Empty = unlimited additional languages'),

                Fieldset::make('Coordinates')
                    ->schema([
                        TextInput::make('coordinates.lat')
                            ->label('Latitude')
                            ->numeric(),
                        TextInput::make('coordinates.lng')
                            ->label('Longitude')
                            ->numeric(),
                    ]),

                Section::make('Opening hours')
                    ->schema([
                        Toggle::make('opening_hours.is_24_7')
                            ->label('Open 24/7'),

                        Textarea::make('opening_hours.raw_text')
                            ->label('Raw text')
                            ->rows(2)
                            ->columnSpanFull(),

                        Repeater::make('opening_hours.periods')
                            ->label('Periods')
                            ->schema([
                                CheckboxList::make('days')
                                    ->options([
                                        'mon' => 'Mon',
                                        'tue' => 'Tue',
                                        'wed' => 'Wed',
                                        'thu' => 'Thu',
                                        'fri' => 'Fri',
                                        'sat' => 'Sat',
                                        'sun' => 'Sun',
                                    ])
                                    ->columns(7)
                                    ->columnSpanFull(),
                                TimePicker::make('open')
                                    ->seconds(false)
                                    ->format('H:i'),
                                TimePicker::make('close')
                                    ->seconds(false)
                                    ->format('H:i'),
                            ])
                            ->columns(2)
                            ->defaultItems(0)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
