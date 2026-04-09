<?php

namespace App\Models;

use App\Enums\RestaurantUserRole;
use App\Models\Concerns\HasTranslations;
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

    use HasTranslations;

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
    protected $appends = ['name', 'address', 'image_url', 'thumb_url'];

    protected $fillable = [
        'created_by_user_id',
        'city',
        'country',
        'phone',
        'currency',
        'primary_language',
        'opening_hours',
        'image',
    ];

    public function getNameAttribute(): ?string
    {
        return $this->localizedText('name');
    }

    public function getAddressAttribute(): ?string
    {
        return $this->localizedText('address');
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

    protected function casts(): array
    {
        return [
            'opening_hours' => 'array',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function menus(): HasMany
    {
        return $this->hasMany(Menu::class);
    }

    public function activeMenu(): HasOne
    {
        return $this->hasOne(Menu::class)->where('is_active', true);
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
}
