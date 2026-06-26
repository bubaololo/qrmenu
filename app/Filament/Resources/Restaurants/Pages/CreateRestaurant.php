<?php

namespace App\Filament\Resources\Restaurants\Pages;

use App\Filament\Resources\Restaurants\Pages\Concerns\NormalizesCoordinates;
use App\Filament\Resources\Restaurants\RestaurantResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRestaurant extends CreateRecord
{
    use NormalizesCoordinates;

    protected static string $resource = RestaurantResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by_user_id'] ??= auth()->id();

        return $this->normalizeCoordinates($data);
    }
}
