<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\MenuVariationOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One choice within a {@see MenuVariation}. `price` is ABSOLUTE — the full
 * price of the dish when this choice is selected.
 */
class MenuVariationOption extends Model
{
    /** @use HasFactory<MenuVariationOptionFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name'];

    protected $fillable = [
        'variation_id',
        'price',
        'is_default',
        'sort_order',
    ];

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (MenuVariationOption $option) {
            $locale = $option->variation?->menu?->source_locale;
            if ($option->pendingName !== null && $locale !== null) {
                $option->setTranslation('name', $locale, $option->pendingName, true);
                $option->pendingName = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'is_default' => 'boolean',
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

    public function variation(): BelongsTo
    {
        return $this->belongsTo(MenuVariation::class, 'variation_id');
    }
}
