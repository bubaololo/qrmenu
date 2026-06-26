<?php

namespace App\Filament\Resources\Restaurants\Pages\Concerns;

use App\Casts\PointCast;

trait NormalizesCoordinates
{
    /**
     * Collapse a partially/empty coordinates pair to null so {@see PointCast}
     * never serializes an invalid "(,)" point. Both lat and lng are required to
     * form a point; otherwise the column is cleared.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function normalizeCoordinates(array $data): array
    {
        $lat = $data['coordinates']['lat'] ?? null;
        $lng = $data['coordinates']['lng'] ?? null;

        if ($lat === null || $lat === '' || $lng === null || $lng === '') {
            $data['coordinates'] = null;

            return $data;
        }

        $data['coordinates'] = ['lat' => (float) $lat, 'lng' => (float) $lng];

        return $data;
    }
}
