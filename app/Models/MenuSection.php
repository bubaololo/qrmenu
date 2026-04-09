<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\MenuSectionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MenuSection extends Model
{
    /** @use HasFactory<MenuSectionFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name'];

    protected $fillable = [
        'menu_id',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (MenuSection $section) {
            $locale = $section->menu?->source_locale ?? 'und';
            if ($section->pendingName !== null && $locale && $locale !== 'mixed') {
                $section->setTranslation('name', $locale, $section->pendingName, true);
                $section->pendingName = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->localizedText('name');
    }

    public function setNameAttribute(?string $value): void
    {
        $this->pendingName = $value;
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'section_id')->orderBy('sort_order');
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(MenuOptionGroup::class, 'section_id')->orderBy('sort_order');
    }
}
