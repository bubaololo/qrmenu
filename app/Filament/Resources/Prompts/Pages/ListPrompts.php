<?php

namespace App\Filament\Resources\Prompts\Pages;

use App\Console\Commands\PromptsExport;
use App\Console\Commands\PromptsImport;
use App\Filament\Resources\Prompts\PromptResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListPrompts extends ListRecords
{
    protected static string $resource = PromptResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('import')
                ->label('Import from JSON')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Import Prompts')
                ->modalDescription('This will create or update prompts from database/prompts/*.json files.')
                ->action(function (): void {
                    Artisan::call(PromptsImport::class);
                    Notification::make()->title('Prompts imported successfully')->success()->send();
                }),

            Action::make('export')
                ->label('Export to JSON')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->action(function (): void {
                    Artisan::call(PromptsExport::class);
                    Notification::make()->title('Prompts exported to database/prompts/')->success()->send();
                }),

            CreateAction::make(),
        ];
    }
}
