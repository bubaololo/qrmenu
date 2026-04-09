<?php

namespace App\Models;

use App\Enums\PriceType;
use App\Models\Concerns\HasTranslations;
use Database\Factories\MenuItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class MenuItem extends Model
{
    /** @use HasFactory<MenuItemFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name', 'description'];

    protected $fillable = [
        'section_id',
        'starred',
        'price_type',
        'price_value',
        'price_min',
        'price_max',
        'price_unit',
        'price_original_text',
        'image_bbox',
        'image',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    /** Pending description translation value to be written after save */
    protected ?string $pendingDescription = null;

    protected static function booted(): void
    {
        static::saved(function (MenuItem $item) {
            $locale = $item->section?->menu?->source_locale ?? 'und';
            $usable = $locale && $locale !== 'mixed';

            if ($item->pendingName !== null && $usable) {
                $item->setTranslation('name', $locale, $item->pendingName, true);
                $item->pendingName = null;
            }

            if ($item->pendingDescription !== null && $usable) {
                $item->setTranslation('description', $locale, $item->pendingDescription, true);
                $item->pendingDescription = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'starred' => 'boolean',
            'price_type' => PriceType::class,
            'price_value' => 'decimal:2',
            'price_min' => 'decimal:2',
            'price_max' => 'decimal:2',
            'image_bbox' => 'array',
            'sort_order' => 'integer',
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->initialText('name');
    }

    public function setNameAttribute(?string $value): void
    {
        $this->pendingName = $value;
    }

    public function getDescriptionAttribute(): ?string
    {
        return $this->initialText('description');
    }

    public function setDescriptionAttribute(?string $value): void
    {
        $this->pendingDescription = $value;
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class, 'section_id');
    }

    public function optionGroups(): BelongsToMany
    {
        return $this->belongsToMany(MenuOptionGroup::class, 'menu_item_option_group', 'item_id', 'group_id')
            ->orderBy('sort_order');
    }

    public function variations(): BelongsToMany
    {
        return $this->optionGroups()->where('is_variation', true);
    }
}
