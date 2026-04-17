<?php

namespace App\Models;

use App\Enums\MenuAnalysisStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class MenuAnalysis extends Model
{
    protected $fillable = [
        'uuid',
        'restaurant_id',
        'user_id',
        'status',
        'image_count',
        'image_paths',
        'original_image_paths',
        'image_disk',
        'vision_model',
        'result_menu_id',
        'result_menu_data',
        'result_item_count',
        'error_message',
        'started_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => MenuAnalysisStatus::class,
            'image_paths' => 'array',
            'original_image_paths' => 'array',
            'result_menu_data' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            $model->uuid ??= Str::uuid()->toString();
        });
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function resultMenu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'result_menu_id');
    }

    public function llmRequests(): HasMany
    {
        return $this->hasMany(LlmRequest::class);
    }

    public function markProcessing(): void
    {
        $this->update([
            'status' => MenuAnalysisStatus::Processing,
            'started_at' => now(),
        ]);
    }

    public function markCompleted(?Menu $menu, array $menuData, int $itemCount): void
    {
        $this->update([
            'status' => MenuAnalysisStatus::Completed,
            'result_menu_id' => $menu?->id,
            'result_menu_data' => $menuData,
            'result_item_count' => $itemCount,
            'completed_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => MenuAnalysisStatus::Failed,
            'error_message' => $error,
            'completed_at' => now(),
        ]);
    }
}
