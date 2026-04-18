<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\RestaurantUserRole;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function restaurantUsers(): HasMany
    {
        return $this->hasMany(RestaurantUser::class);
    }

    public function restaurants(): BelongsToMany
    {
        return $this->belongsToMany(Restaurant::class, 'restaurant_users')
            ->withPivot('role')
            ->withTimestamps()
            ->using(RestaurantUser::class);
    }

    public function ownedRestaurants(): BelongsToMany
    {
        return $this->restaurants()->wherePivot('role', RestaurantUserRole::Owner->value);
    }

    public function isAdmin(): bool
    {
        if ($this->email === null) {
            return false;
        }

        $allowed = array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('ADMIN_EMAILS', '')),
        )));

        return in_array($this->email, $allowed, true);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return app()->isLocal() || $this->isAdmin();
    }
}
