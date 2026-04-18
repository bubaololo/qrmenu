<?php

namespace App\Http\Controllers;

use App\Actions\AnalyzeMenuImageAction;
use App\Actions\SaveMenuAnalysisAction;
use App\Filament\Pages\MenuAnalyzer;
use App\Http\Resources\MenuAnalysisResource;
use App\Jobs\AnalyzeMenuJob;
use App\Llm\GeminiVisionProvider;
use App\Llm\OpenRouterProvider;
use App\Models\MenuAnalysis;
use App\Models\Restaurant;
use App\Services\ImagePreflightApplier;
use App\Services\ImagePreflightService;
use App\Services\ImagePreprocessor;
use App\Support\MenuJson;
use App\Support\PreflightResult;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MenuAnalysisController extends Controller
{
    /**
     * Upload menu photos and start (or run inline) an analysis.
     *
     * Default is async: returns `202 Accepted` with `data.id = <uuid>` and
     * `data.attributes.status = "pending"`. The caller then polls
     * `GET /v1/menu-analyses/{uuid}` until the status reaches a terminal value —
     * there is no webhook or push channel.
     *
     * Pass `sync=1` to run the analysis inline (useful for dev/testing) — the
     * request blocks until the LLM cascade returns and responds `200` with the
     * full menu inline. Can take tens of seconds to minutes; not recommended
     * from a UI.
     */
    public function store(
        Request $request,
        AnalyzeMenuImageAction $action,
        ImagePreprocessor $preprocessor,
        ImagePreflightService $preflightService,
        ImagePreflightApplier $preflightApplier,
    ): MenuAnalysisResource|JsonResponse {
        $request->validate([
            'images' => ['required', 'array', 'min:1'],
            'images.*' => ['required', 'image', 'max:10240'],
            'restaurant_id' => ['nullable', 'integer', 'exists:restaurants,id'],
            'model' => ['nullable', 'string', 'in:'.implode(',', array_keys(MenuAnalyzer::visionModels()))],
            'sync' => ['nullable', 'boolean'],
        ]);

        $disk = config('image.originals_disk');
        $storage = Storage::disk($disk);
        $originalPaths = [];
        $totalSizeKb = 0;
        foreach ($request->file('images') as $file) {
            $originalPaths[] = $file->store('menu-analyzer-uploads', $disk);
            $totalSizeKb += (int) round($file->getSize() / 1024);
        }

        Log::channel('llm')->info('Upload received', [
            'image_count' => count($originalPaths),
            'disk' => $disk,
            'total_kb' => $totalSizeKb,
        ]);

        // Preflight: detect rotation + content bbox per image, apply to originals on disk
        $fullPaths = array_map(fn ($p) => $storage->path($p), $originalPaths);
        $preflights = $preflightService->analyzeMany($fullPaths);

        foreach ($fullPaths as $fullPath) {
            $preflightApplier->apply($fullPath, $preflights[$fullPath] ?? PreflightResult::noop());
        }

        Log::channel('llm')->info('Preflight stage complete', [
            'image_count' => count($originalPaths),
            'results' => array_map(fn ($r) => [
                'rotation_cw' => $r->rotationCw,
                'has_crop' => $r->contentBbox !== null,
                'quality' => $r->quality,
            ], array_values($preflights)),
        ]);

        // Preprocess: trim, deskew, contrast, resize, WebP
        $preprocessedPaths = $this->preprocessImages($originalPaths, $disk, $preprocessor);

        $restaurantId = $request->integer('restaurant_id') ?: null;

        if ($restaurantId !== null) {
            $restaurant = Restaurant::findOrFail($restaurantId);
            Gate::authorize('update', $restaurant);
        }

        // Async mode (default)
        if (! $request->boolean('sync')) {
            $analysis = MenuAnalysis::create([
                'restaurant_id' => $restaurantId,
                'user_id' => auth()->id(),
                'image_count' => count($preprocessedPaths),
                'image_paths' => $preprocessedPaths,
                'original_image_paths' => $originalPaths,
                'image_disk' => $disk,
                'vision_model' => $request->input('model'),
            ]);

            AnalyzeMenuJob::dispatch($analysis);

            return response()->json([
                'data' => [
                    'type' => 'menu-analyses',
                    'id' => $analysis->uuid,
                    'attributes' => [
                        'status' => 'pending',
                        'image_count' => count($preprocessedPaths),
                    ],
                ],
            ], 202);
        }

        // Sync mode (?sync=1) — legacy inline execution for dev/testing
        $provider = $request->input('model', 'gemini') === 'gemini'
            ? app(GeminiVisionProvider::class)
            : app()->makeWith(OpenRouterProvider::class, ['openRouterModel' => $request->input('model')]);

        try {
            $llmStarted = microtime(true);
            $raw = $action->handle($preprocessedPaths, $disk, $provider);
            $llmDurationMs = (int) round((microtime(true) - $llmStarted) * 1000);

            /** @var array<string, mixed> $menu */
            $menu = MenuJson::decodeMenuFromLlmText($raw);
        } finally {
            Storage::disk($disk)->delete($preprocessedPaths);
            Storage::disk($disk)->delete($originalPaths);
        }

        $savedMenuId = null;

        if ($restaurantId !== null) {
            $savedMenu = app(SaveMenuAnalysisAction::class)->handle($menu, $restaurantId, count($preprocessedPaths));
            $savedMenuId = $savedMenu->id;
        }

        return new MenuAnalysisResource([
            'id' => Str::uuid()->toString(),
            'image_count' => count($preprocessedPaths),
            'menu' => $menu,
            'item_count' => MenuJson::dishCount($menu),
            'llm_raw_text' => $raw,
            'llm_duration_ms' => $llmDurationMs,
            'analyzed_at' => now()->toIso8601String(),
            'saved_menu_id' => $savedMenuId,
            'saved_restaurant_id' => $restaurantId,
        ]);
    }

    /**
     * Preprocess uploaded images: auto-orient, trim, deskew, contrast, resize, WebP.
     *
     * @param  string[]  $originalPaths
     * @return string[] Paths to preprocessed files on the same disk
     */
    private function preprocessImages(array $originalPaths, string $disk, ImagePreprocessor $preprocessor): array
    {
        $storage = Storage::disk($disk);
        $preprocessedPaths = [];

        foreach ($originalPaths as $originalPath) {
            try {
                $fullPath = $storage->path($originalPath);
                $result = $preprocessor->preprocess($fullPath);

                $prepPath = 'menu-analyzer-uploads/prep_'.Str::random(20).'.webp';
                $storage->put($prepPath, file_get_contents($result->path));

                @unlink($result->path);

                Log::channel('llm')->info('Image stored for LLM', [
                    'original' => $originalPath,
                    'preprocessed' => $prepPath,
                ]);

                $preprocessedPaths[] = $prepPath;
            } catch (\Throwable $e) {
                Log::channel('llm')->warning('Image preprocess failed, using original', [
                    'path' => $originalPath,
                    'error' => $e->getMessage(),
                ]);

                $preprocessedPaths[] = $originalPath;
            }
        }

        return $preprocessedPaths;
    }

    /**
     * Poll an async menu analysis by UUID.
     *
     * Call this every 2–3 seconds after a 202 response from `POST /v1/menu-analyses`
     * until `data.attributes.status` reaches a terminal value. There is no webhook or
     * push channel — polling is the only mechanism to learn that the menu is ready.
     *
     * Status lifecycle: `pending` → `processing` → `completed` | `failed`.
     * On `completed`: `attributes.menu`, `saved_menu_id`, `saved_restaurant_id`, `item_count`.
     * On `failed`: `attributes.error_message`.
     */
    public function show(Request $request, string $uuid): MenuAnalysisResource
    {
        $analysis = MenuAnalysis::where('uuid', $uuid)->firstOrFail();

        if ($analysis->user_id !== null && $analysis->user_id !== auth()->id()) {
            abort(403);
        }

        return new MenuAnalysisResource($analysis);
    }
}
