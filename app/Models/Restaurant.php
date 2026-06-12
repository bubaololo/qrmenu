<?php

namespace App\Models;

use App\Casts\PointCast;
use App\Enums\RestaurantUserRole;
use Database\Factories\RestaurantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Restaurant extends Model
{
    /** @use HasFactory<RestaurantFactory> */
    use HasFactory;

    public static function boot(): void
    {
        parent::boot();

        static::creating(function (self $restaurant): void {
            if (empty($restaurant->uniqid)) {
                $restaurant->uniqid = Str::random(8);
            }
        });
    }

    /** @var array<int, string> */
    protected $appends = ['image_url', 'thumb_url', 'logo_url', 'logo_thumb_url'];

    protected $fillable = [
        'created_by_user_id',
        'name',
        'address',
        'city',
        'country',
        'phone',
        'currency',
        'primary_language',
        'opening_hours',
        'image',
        'logo',
        'google_maps_url',
        'coordinates',
    ];

    public function getImageUrlAttribute(): ?string
    {
        return $this->image
            ? Storage::disk(config('image.disk'))->url($this->image).$this->cacheBust()
            : null;
    }

    public function getThumbUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        $ext = pathinfo($this->image, PATHINFO_EXTENSION);
        $thumb = preg_replace('/\.'.$ext.'$/', '_thumb.'.$ext, $this->image);

        return Storage::disk(config('image.disk'))->url($thumb).$this->cacheBust();
    }

    public function getLogoUrlAttribute(): ?string
    {
        return $this->logo
            ? Storage::disk(config('image.disk'))->url($this->logo).$this->cacheBust()
            : null;
    }

    public function getLogoThumbUrlAttribute(): ?string
    {
        if (! $this->logo) {
            return null;
        }

        $ext = pathinfo($this->logo, PATHINFO_EXTENSION);
        $thumb = preg_replace('/\.'.$ext.'$/', '_thumb.'.$ext, $this->logo);

        return Storage::disk(config('image.disk'))->url($thumb).$this->cacheBust();
    }

    /**
     * Versioned query string so the nginx `immutable` cache invalidates
     * the moment an admin re-uploads a logo or hero image.
     */
    private function cacheBust(): string
    {
        return $this->updated_at ? '?v='.$this->updated_at->timestamp : '';
    }

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
            'coordinates' => PointCast::class,
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function menu(): HasOne
    {
        return $this->hasOne(Menu::class);
    }

    public function restaurantUsers(): HasMany
    {
        return $this->hasMany(RestaurantUser::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'restaurant_users')
            ->withPivot('role')
            ->withTimestamps()
            ->using(RestaurantUser::class);
    }

    public function owners(): BelongsToMany
    {
        return $this->users()->wherePivot('role', RestaurantUserRole::Owner->value);
    }

    public function zones(): HasMany
    {
        return $this->hasMany(Zone::class)->orderBy('sort_order');
    }
}
