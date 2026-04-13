<?php

namespace App\Actions;

use App\Contracts\LlmProvider;
use App\Llm\OpenRouterProvider;
use App\Models\Prompt;
use Illuminate\Support\Facades\Storage;
use InvalidArgumentException;
use Prism\Prism\ValueObjects\Media\Image;
use Prism\Prism\ValueObjects\Messages\SystemMessage;
use Prism\Prism\ValueObjects\Messages\UserMessage;
use RuntimeException;

class AnalyzeMenuImageAction
{
    private const TYPE_SLUG = 'menu_analyzer';

    private const MAX_RAW_BYTES = 7 * 1024 * 1024;

    private const SUPPORTED_MIMES = [
        'image/jpeg', 'image/png', 'image/bmp',
        'image/tiff', 'image/webp', 'image/heic',
    ];

    public function __construct(private readonly OpenRouterProvider $defaultProvider) {}

    /**
     * @param  string|string[]  $images  Local storage paths
     */
    public function handle(string|array $images, string $disk = 'public', ?LlmProvider $provider = null): string
    {
        $provider ??= $this->defaultProvider;

        $prompt = Prompt::activeForType(self::TYPE_SLUG)
            ?? throw new RuntimeException('No active prompt found for type "'.self::TYPE_SLUG.'".');

        $images = (array) $images;

        foreach ($images as $image) {
            $this->validateImage($image, $disk);
        }

        $messages = [];

        if ($prompt->system_prompt) {
            $messages[] = new SystemMessage($prompt->system_prompt);
        }

        $prismImages = array_map(
            fn (string $path) => Image::fromStoragePath($path, $disk),
            $images
        );

        $messages[] = new UserMessage(
            content: $prompt->user_prompt,
            additionalContent: $prismImages,
        );

        return $provider->execute($messages, [
            'prompt_id' => $prompt->id,
            'prompt_name' => $prompt->name,
            'image_count' => count($images),
            'paths' => $images,
        ]);
    }

    private function validateImage(string $path, string $disk): void
    {
        $fullPath = Storage::disk($disk)->path($path);
        $mime = mime_content_type($fullPath) ?: 'image/jpeg';

        if (! in_array($mime, self::SUPPORTED_MIMES, true)) {
            throw new InvalidArgumentException(
                "Unsupported image type \"{$mime}\" (supported: ".implode(', ', self::SUPPORTED_MIMES).').'
            );
        }

        $rawBytes = Storage::disk($disk)->size($path);

        if ($rawBytes > self::MAX_RAW_BYTES) {
            throw new RuntimeException(
                sprintf('Image "%s" is %.1f MB — exceeds 7 MB limit.', $path, $rawBytes / 1024 / 1024)
            );
        }
    }
}
