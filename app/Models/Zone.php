<?php

namespace App\Models;

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

    /** @var array<int, string> */
    protected $appends = ['image_url', 'thumb_url'];

    protected $fillable = [
        'restaurant_id',
        'name',
        'color',
        'sort_order',
        'is_active',
        'image',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
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

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function tables(): HasMany
    {
        return $this->hasMany(DiningTable::class)->orderBy('sort_order');
    }
}
