<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\MenuOptionGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuOptionGroup extends Model
{
    /** @use HasFactory<MenuOptionGroupFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name'];

    protected $fillable = [
        'section_id',
        'type',
        'is_variation',
        'required',
        'allow_multiple',
        'min_select',
        'max_select',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (MenuOptionGroup $group) {
            $locale = $group->section?->menu?->source_locale ?? 'und';
            if ($group->pendingName !== null && $locale && $locale !== 'mixed') {
                $group->setTranslation('name', $locale, $group->pendingName, true);
                $group->pendingName = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'is_variation' => 'boolean',
            'required' => 'boolean',
            'allow_multiple' => 'boolean',
            'min_select' => 'integer',
            'max_select' => 'integer',
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

    public function section(): BelongsTo
    {
        return $this->belongsTo(MenuSection::class, 'section_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(MenuOptionGroupOption::class, 'group_id')->orderBy('sort_order');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(MenuItem::class, 'menu_item_option_group', 'group_id', 'item_id');
    }
}
