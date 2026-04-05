<?php

namespace App\Console\Commands;

use App\Models\PromptType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('prompts:export')]
#[Description('Export all prompts to JSON files in database/prompts/')]
class PromptsExport extends Command
{
    public function handle(): int
    {
        $directory = database_path('prompts');
        File::ensureDirectoryExists($directory);

        $types = PromptType::with('prompts')->get();

        if ($types->isEmpty()) {
            $this->warn('No prompt types found.');

            return self::SUCCESS;
        }

        foreach ($types as $type) {
            $data = [
                'type' => $type->slug,
                'name' => $type->name,
                'prompts' => $type->prompts->map(fn ($p) => [
                    'name' => $p->name,
                    'system_prompt' => $p->system_prompt,
                    'user_prompt' => $p->user_prompt,
                    'is_active' => $p->is_active,
                ])->toArray(),
            ];

            $path = $directory.'/'.$type->slug.'.json';
            File::put($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->info("Exported: {$path}");
        }

        $this->info('Export complete.');

        return self::SUCCESS;
    }
}
