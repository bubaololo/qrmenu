<?php

namespace App\Actions;

use App\Models\Prompt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AnalyzeMenuImageAction
{
    private const DASHSCOPE_URL = 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions';

    private const MODEL = 'qwen-vl-max';

    private const TYPE_SLUG = 'menu_analyzer';

    private const TIMEOUT_SECONDS = 300;

    /**
     * @param  string|string[]  $images  Local storage paths (disk: public) or data URIs
     */
    public function handle(string|array $images): string
    {
        $prompt = Prompt::activeForType(self::TYPE_SLUG)
            ?? throw new RuntimeException('No active prompt found for type "'.self::TYPE_SLUG.'".');

        $images = (array) $images;

        $content = [];
        foreach ($images as $image) {
            $dataUri = $this->toDataUri($image);
            $content[] = ['type' => 'image_url', 'image_url' => ['url' => $dataUri]];
        }
        $content[] = ['type' => 'text', 'text' => $prompt->user_prompt];

        $messages = [];
        if ($prompt->system_prompt) {
            $messages[] = ['role' => 'system', 'content' => $prompt->system_prompt];
        }
        $messages[] = ['role' => 'user', 'content' => $content];

        Log::channel('single')->info('LLM request', [
            'model' => self::MODEL,
            'image_count' => count($images),
            'paths' => $images,
            'prompt' => $prompt->name,
        ]);

        try {
            $response = Http::withToken(env('QWEN_API_KEY'))
                ->timeout(self::TIMEOUT_SECONDS)
                ->withOptions(['proxy' => ''])
                ->post(self::DASHSCOPE_URL, [
                    'model' => self::MODEL,
                    'messages' => $messages,
                ]);

            $response->throw();

            $text = $response->json('choices.0.message.content');

            Log::channel('single')->info('LLM response', [
                'status'      => $response->status(),
                'usage'       => $response->json('usage'),
                'finish_reason' => $response->json('choices.0.finish_reason'),
                'length'      => strlen($text ?? ''),
                'raw'         => $text,
            ]);

            return $text;
        } catch (Throwable $e) {
            Log::channel('single')->error('LLM error', [
                'error'   => $e->getMessage(),
                'request' => [
                    'model'       => self::MODEL,
                    'image_count' => count($images),
                    'paths'       => $images,
                    'prompt'      => $prompt->name,
                ],
            ]);

            throw $e;
        }
    }

    private function toDataUri(string $path): string
    {
        if (str_starts_with($path, 'data:')) {
            return $path;
        }

        $contents = Storage::disk('public')->get($path);
        $fullPath = Storage::disk('public')->path($path);
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
