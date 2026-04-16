<?php

namespace App\Filament\Pages;

use App\Enums\MenuAnalysisStatus;
use App\Enums\RestaurantUserRole;
use App\Jobs\AnalyzeMenuJob;
use App\Models\MenuAnalysis;
use App\Models\RestaurantUser;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;

class MenuAnalyzer extends Page
{
    /**
     * Laravel/Filament file validation uses kilobytes; 10240 KB = 10 MB per file.
     */
    private const MAX_FILE_SIZE_KILOBYTES = 10240;

    protected string $view = 'filament.pages.menu-analyzer';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var array<int, array{paths: array<int, string>, menu: array<string, mixed>|null, error: string|null}> */
    public array $results = [];

    public ?int $pendingAnalysisId = null;

    public static function getNavigationLabel(): string
    {
        return 'Menu Analyzer';
    }

    public static function getNavigationIcon(): string|\BackedEnum|null
    {
        return 'heroicon-o-document-text';
    }

    public function getTitle(): string|Htmlable
    {
        return 'Menu Analyzer';
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    /** @return array<string, string> */
    public static function visionModels(): array
    {
        return [
            'gemini' => 'Gemini 2.5 Flash',
            'google/gemma-4-26b-a4b-it:free' => 'Gemma 4 26B (free)',
            'google/gemma-4-26b-a4b-it' => 'Gemma 4 26B',
            'google/gemma-4-31b-it:free' => 'Gemma 4 31B (free)',
            'google/gemma-4-31b-it' => 'Gemma 4 31B',
            'qwen/qwen3.6-plus' => 'Qwen 3.6 Plus',
            'opengvlab/internvl3-78b' => 'InternVL3 78B',
            'rekaai/reka-edge' => 'Reka Edge',
            'arcee-ai/spotlight' => 'Arcee Spotlight',
            'meta-llama/llama-4-maverick' => 'Llama 4 Maverick',
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Select::make('vision_model')
                        ->label('Vision Model')
                        ->options(self::visionModels())
                        ->default('gemini')
                        ->selectablePlaceholder(false),

                    Select::make('restaurant_id')
                        ->label('Restaurant')
                        ->helperText('Select a restaurant to save the analyzed menu.')
                        ->options(function (): array {
                            $userId = auth()->id();

                            return RestaurantUser::query()
                                ->where('user_id', $userId)
                                ->where('role', RestaurantUserRole::Owner->value)
                                ->with('restaurant')
                                ->get()
                                ->mapWithKeys(fn ($ru) => [
                                    $ru->restaurant_id => $ru->restaurant->translate('name', $ru->restaurant->primary_language ?? 'und') ?? "Restaurant #{$ru->restaurant_id}",
                                ])
                                ->toArray();
                        })
                        ->searchable()
                        ->placeholder('— skip saving —'),

                    FileUpload::make('images')
                        ->label('Menu Images')
                        ->helperText('Up to 10 MB per image.')
                        ->image()
                        ->multiple()
                        ->disk('public')
                        ->directory('menu-analyzer-uploads')
                        ->imagePreviewHeight('180')
                        ->panelLayout('grid')
                        ->reorderable()
                        ->maxSize(self::MAX_FILE_SIZE_KILOBYTES)
                        ->columnSpanFull(),
                ])
                    ->livewireSubmitHandler('analyze')
                    ->footer([
                        Actions::make([
                            Action::make('analyze')
                                ->label('Analyze')
                                ->icon('heroicon-o-magnifying-glass')
                                ->submit('analyze'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function analyze(): void
    {
        $state = $this->form->getState();
        $files = array_values(array_filter($state['images'] ?? []));

        if (empty($files)) {
            Notification::make()->title('Upload at least one image')->warning()->send();

            return;
        }

        $restaurantId = $state['restaurant_id'] ?? null;
        $this->results = [];

        $visionModel = $state['vision_model'] ?? null;

        $analysis = MenuAnalysis::create([
            'restaurant_id' => $restaurantId,
            'user_id' => auth()->id(),
            'image_count' => count($files),
            'image_paths' => $files,
            'image_disk' => 'public',
            'vision_model' => $visionModel !== 'gemini' ? $visionModel : null,
        ]);

        AnalyzeMenuJob::dispatch($analysis);

        $this->pendingAnalysisId = $analysis->id;

        Notification::make()
            ->title(count($files).' image(s) submitted for analysis')
            ->body('Processing in background...')
            ->info()
            ->send();
    }

    public function checkAnalysisStatus(): void
    {
        if ($this->pendingAnalysisId === null) {
            return;
        }

        $analysis = MenuAnalysis::find($this->pendingAnalysisId);
        if ($analysis === null) {
            $this->pendingAnalysisId = null;

            return;
        }

        if ($analysis->status === MenuAnalysisStatus::Completed) {
            $this->results = [[
                'paths' => $analysis->image_paths,
                'menu' => $analysis->result_menu_data,
                'error' => null,
            ]];
            $this->pendingAnalysisId = null;

            $total = $analysis->result_item_count ?? 0;

            Notification::make()
                ->title($analysis->image_count.' image(s) processed, '.$total.' items found')
                ->success()
                ->send();
        } elseif ($analysis->status === MenuAnalysisStatus::Failed) {
            $this->results = [[
                'paths' => $analysis->image_paths,
                'menu' => null,
                'error' => $analysis->error_message,
            ]];
            $this->pendingAnalysisId = null;

            Notification::make()
                ->title('Analysis failed')
                ->body($analysis->error_message)
                ->danger()
                ->send();
        }
    }
}
