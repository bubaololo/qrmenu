<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\MenuOptionGroupOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuOptionGroupOption extends Model
{
    /** @use HasFactory<MenuOptionGroupOptionFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name'];

    protected $fillable = [
        'group_id',
        'price_adjust',
        'is_default',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (MenuOptionGroupOption $option) {
            $locale = $option->group?->section?->menu?->source_locale ?? 'und';
            if ($option->pendingName !== null && $locale && $locale !== 'mixed') {
                $option->setTranslation('name', $locale, $option->pendingName, true);
                $option->pendingName = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price_adjust' => 'decimal:2',
            'is_default' => 'boolean',
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

    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuOptionGroup::class, 'group_id');
    }
}
