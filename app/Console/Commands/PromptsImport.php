<?php

namespace App\Console\Commands;

use App\Models\Prompt;
use App\Models\PromptType;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

#[Signature('prompts:import')]
#[Description('Import prompts from JSON files in database/prompts/')]
class PromptsImport extends Command
{
    public function handle(): int
    {
        $directory = database_path('prompts');
        $files = File::glob($directory.'/*.json');

        if (empty($files)) {
            $this->warn("No JSON files found in {$directory}");

            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $data = json_decode(File::get($file), true);

            if (! isset($data['type'], $data['prompts'])) {
                $this->error("Invalid format: {$file}");

                continue;
            }

            $type = PromptType::firstOrCreate(
                ['slug' => $data['type']],
                ['name' => $data['name'] ?? $data['type']],
            );

            foreach ($data['prompts'] as $promptData) {
                Prompt::updateOrCreate(
                    ['prompt_type_id' => $type->id, 'name' => $promptData['name']],
                    [
                        'system_prompt' => $promptData['system_prompt'] ?? null,
                        'user_prompt' => $promptData['user_prompt'],
                        'is_active' => $promptData['is_active'] ?? false,
                    ],
                );
            }

            $this->info("Imported: {$file} ({$type->slug})");
        }

        $this->info('Import complete.');

        return self::SUCCESS;
    }
}
