<?php

namespace App\Actions;

use Illuminate\Support\Facades\Http;

class AnalyzeMenuImageAction
{
    private const URL = 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1/chat/completions';

    private const MODEL = 'qwen-vl-max';

    public function handle(string $image): string
    {
        $imageUrl = str_starts_with($image, 'http')
            ? $image
            : 'data:'.mime_content_type($image).';base64,'.base64_encode(file_get_contents($image));

        $response = Http::withToken(env('QWEN_API_KEY'))
            ->timeout(120)
            ->withOptions(['proxy' => ''])
            ->post(self::URL, [
                'model' => self::MODEL,
                'messages' => [
                    [
                        'role' => 'user',
                        'content' => [
                            [
                                'type' => 'image_url',
                                'image_url' => ['url' => $imageUrl],
                            ],
                            [
                                'type' => 'text',
                                'text' => 'You are an expert menu analyst and culinary translator. '.
                                    'Extract ALL menu items from this image. '.
                                    'For each item return a JSON array (no markdown) with fields: '.
                                    'category, original_name, name_en, name_ru, price, currency, description_en, description_ru.',
                            ],
                        ],
                    ],
                ],
            ]);

        $response->throw();

        return $response->json('choices.0.message.content');
    }
}
