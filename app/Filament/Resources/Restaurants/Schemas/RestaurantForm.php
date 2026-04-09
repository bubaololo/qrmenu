<?php

namespace App\Filament\Resources\Restaurants\Schemas;

use App\Services\ImageProcessor;
use Filament\Forms\Components\FileUpload;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
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
                            'restaurants',
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
