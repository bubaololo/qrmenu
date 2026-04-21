<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ZoneFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Zone extends Model
{
    /** @use HasFactory<ZoneFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var array<int, string> */
    protected $appends = ['name', 'image_url', 'thumb_url'];

    protected $fillable = [
        'restaurant_id',
        'color',
        'sort_order',
        'is_active',
        'image',
    ];

    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (Zone $zone) {
            if ($zone->pendingName !== null) {
                $zone->setTranslation('name', 'und', $zone->pendingName, true);
                $zone->pendingName = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getNameAttribute(): ?string
    {
        return $this->localizedText('name');
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? Storage::disk(config('image.disk'))->url($this->image)
            : null;
    }

    public function getThumbUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        $ext = pathinfo($this->image, PATHINFO_EXTENSION);
        $thumb = preg_replace('/\.'.$ext.'$/', '_thumb.'.$ext, $this->image);

        return Storage::disk(config('image.disk'))->url($thumb);
    }

    public function setNameAttribute(?string $value): void
    {
        $this->pendingName = $value;
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('sort_order');
    }
}
