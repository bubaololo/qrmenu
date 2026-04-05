<?php

namespace App\Filament\Pages;

use App\Actions\AnalyzeMenuImageAction;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Storage;
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

    /** @var array<int, array{paths: array<int, string>, items: array<mixed>, error: string|null}> */
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

        $this->results = [];
        $action = app(AnalyzeMenuImageAction::class);

        $publicUrls = array_map(
            fn (string $p) => Storage::disk('public')->url($p),
            $files,
        );

        try {
            $raw = $action->handle($publicUrls);
            $clean = trim(preg_replace('/^```json\s*|\s*```$/s', '', $raw));
            $items = json_decode($clean, true) ?? [];

            $this->results[] = [
                'paths' => $files,
                'items' => $items,
                'error' => null,
            ];
        } catch (Throwable $e) {
            $this->results[] = [
                'paths' => $files,
                'items' => [],
                'error' => $e->getMessage(),
            ];
        }

        $total = collect($this->results)->sum(fn ($r) => count($r['items']));

        Notification::make()
            ->title(count($files).' image(s) processed, '.$total.' items found')
            ->success()
            ->send();
    }
}
