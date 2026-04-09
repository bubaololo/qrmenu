<?php

namespace App\Http\Resources\Menus;

use App\Models\Menu;
use Illuminate\Http\Resources\JsonApi\JsonApiResource;

/**
 * @mixin Menu
 */
class MenuResource extends JsonApiResource
{
    /** @var array<int, string> */
    public $attributes = [
        'source_locale',
        'detected_date',
        'source_images_count',
        'is_active',
        'created_from_menu_id',
        'created_at',
    ];

    /** @var array<int, string> */
    public $relationships = [
        'restaurant',
        'sections',
    ];
}
