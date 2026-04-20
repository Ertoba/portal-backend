<?php

namespace Modules\AI\app\Core\Engines;

use Modules\AI\app\Core\Contracts\AIEngineInterface;
use OpenAI\Laravel\Facades\OpenAI;


class OpenAIEngine implements AIEngineInterface
{
    private const GEORGIAN_OUTPUT_INSTRUCTION = 'You generate content for a Georgian 6amMart marketplace. Generate all free-text, user-facing content in natural Georgian using Georgian Mkhedruli script. Translate visible product text from images into Georgian when it is used in titles, descriptions, tags, variation names, option names, or SEO content. Preserve JSON keys, required schemas, numeric values, booleans, brand names, SKU/model names, measurements, and exact values selected from provided system lists.';

    public function boot(): void
    {
        // TODO: Implement boot() method.
    }

    public function core($prompt, $imageUrl = null): string
    {
        $content = [['type' => 'text', 'text' => $prompt]];

        if (!empty($imageUrl)) {
            $content[] = [
                'type' => 'image_url',
                'image_url' => ['url' => $imageUrl],
            ];
        }

        $response = OpenAI::chat()->create([
            'model' => 'gpt-4o',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => self::GEORGIAN_OUTPUT_INSTRUCTION,
                ],
                [
                    'role' => 'user',
                    'content' => $content,
                ],
            ],
            'temperature' => 0.3,
        ]);

        return $response->choices[0]->message->content;
    }


}

