<?php

namespace App\Models;

use App\Enums\LlmRequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LlmRequest extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'menu_analysis_id',
        'provider',
        'model',
        'tier_position',
        'status',
        'image_count',
        'duration_ms',
        'prompt_tokens',
        'completion_tokens',
        'response_length',
        'finish_reason',
        'error_class',
        'error_message',
        'prompt_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => LlmRequestStatus::class,
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->created_at ??= now();
        });
    }

    public function menuAnalysis(): BelongsTo
    {
        return $this->belongsTo(MenuAnalysis::class);
    }

    public function prompt(): BelongsTo
    {
        return $this->belongsTo(Prompt::class);
    }
}
