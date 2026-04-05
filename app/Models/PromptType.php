<?php

namespace App\Models;

use Database\Factories\PromptTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PromptType extends Model
{
    /** @use HasFactory<PromptTypeFactory> */
    use HasFactory;

    protected $fillable = ['slug', 'name'];

    public function prompts(): HasMany
    {
        return $this->hasMany(Prompt::class);
    }

    public function activePrompt(): HasMany
    {
        return $this->hasMany(Prompt::class)->where('is_active', true);
    }
}
