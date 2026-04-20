<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Casts a PostgreSQL native point column to/from ['lat' => float, 'lng' => float].
 * Stored in DB as "(lat,lng)" — PostgreSQL geometric point type.
 */
class PointCast implements CastsAttributes
{
    /**
     * @param  array<string, mixed>  $attributes
     * @return array{lat: float, lng: float}|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?array
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value, '()');
        [$lat, $lng] = explode(',', $value);

        return ['lat' => (float) trim($lat), 'lng' => (float) trim($lng)];
    }

    /**
     * @param  array{lat: float, lng: float}|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return '('.$value['lat'].','.$value['lng'].')';
    }
}
