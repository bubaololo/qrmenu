<?php

namespace App\Models;

use Database\Factories\PromptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Prompt extends Model
{
    /** @use HasFactory<PromptFactory> */
    use HasFactory;

    protected $fillable = [
        'prompt_type_id',
        'name',
        'system_prompt',
        'user_prompt',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function promptType(): BelongsTo
    {
        return $this->belongsTo(PromptType::class);
    }

    /**
     * Activate this prompt and deactivate all others of the same type.
     */
    public function activate(): void
    {
        static::where('prompt_type_id', $this->prompt_type_id)->update(['is_active' => false]);
        $this->update(['is_active' => true]);
    }

    /**
     * Get the active prompt for a given type slug.
     */
    public static function activeForType(string $typeSlug): ?self
    {
        return static::query()
            ->whereHas('promptType', fn ($q) => $q->where('slug', $typeSlug))
            ->where('is_active', true)
            ->first();
    }
}
