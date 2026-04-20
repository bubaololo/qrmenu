<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\HallFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class Hall extends Model
{
    /** @use HasFactory<HallFactory> */
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

    /** Pending translation value to be written after save */
    protected ?string $pendingName = null;

    protected static function booted(): void
    {
        static::saved(function (Hall $hall) {
            if ($hall->pendingName !== null) {
                $hall->setTranslation('name', 'und', $hall->pendingName, true);
                $hall->pendingName = null;
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
