<?php

namespace App\Filament\Resources\Restaurants\Pages;

use App\Filament\Resources\Restaurants\Pages\Concerns\NormalizesCoordinates;
use App\Filament\Resources\Restaurants\RestaurantResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRestaurant extends EditRecord
{
    use NormalizesCoordinates;

    protected static string $resource = RestaurantResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->normalizeCoordinates($data);
    }
}
