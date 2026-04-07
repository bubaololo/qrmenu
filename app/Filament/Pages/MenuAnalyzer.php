<?php

namespace App\Filament\Pages;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Enums\RestaurantUserRole;
use App\Models\RestaurantUser;
use App\Support\MenuJson;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Throwable;

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

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
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
                                    $ru->restaurant_id => $ru->restaurant->name_local ?? "Restaurant #{$ru->restaurant_id}",
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
        $action = app(AnalyzeMenuImageAction::class);

        try {
            $raw = $action->handle($files);
            /** @var array<string, mixed> $menu */
            $menu = MenuJson::decodeMenuFromLlmText($raw);

            $this->results[] = [
                'paths' => $files,
                'menu' => $menu,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $this->results[] = [
                'paths' => $files,
                'menu' => null,
                'error' => $e->getMessage(),
            ];
        }

        if ($restaurantId !== null && ($this->results[0]['menu'] ?? null) !== null) {
            try {
                $saved = app(SaveMenuAnalysisAction::class)->handle($this->results[0]['menu'], (int) $restaurantId, count($files));
                Notification::make()->title("Saved as Menu #{$saved->id}")->success()->send();
            } catch (Throwable $e) {
                Notification::make()->title('Not saved: '.$e->getMessage())->warning()->send();
            }
        }

        $total = collect($this->results)->sum(fn ($r) => $r['menu'] !== null ? MenuJson::dishCount($r['menu']) : 0);

        Notification::make()
            ->title(count($files).' image(s) processed, '.$total.' items found')
            ->success()
            ->send();
    }
}
