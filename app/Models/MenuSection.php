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

    protected $fillable = [
        'menu_id',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
        ];
    }

    /**
     * Returns the initial (source) name translation for display purposes.
     */
    public function getNameAttribute(): ?string
    {
        return $this->translations()
            ->where('field', 'name')
            ->where('is_initial', true)
            ->value('value');
    }

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class, 'section_id')->orderBy('sort_order');
    }
}
