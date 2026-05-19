<?php

namespace App\Filament\Resources\Prompts\Schemas;

use App\Models\PromptType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class PromptForm
{
    /**
     * Per-prompt-type placeholders. Values in the form's prompt fields will be
     * checked at save-time to ensure required placeholders aren't accidentally
     * removed. helperText surfaces the same list under each Textarea.
     *
     * Keys are PromptType slugs. Substitution sites:
     *  - menu_analyzer.system_prompt   → AnalyzeMenuImageAction::buildMessages
     *  - menu_translator.user_prompt   → TranslateChunkJob::handle / TranslateEntityJob::translateInto
     *
     * @var array<string, array{system_prompt: list<string>, user_prompt: list<string>}>
     */
    private const PLACEHOLDERS = [
        'menu_analyzer' => [
            'system_prompt' => ['{icon_list}', '{icon_count}'],
            'user_prompt' => [],
        ],
        'menu_translator' => [
            'system_prompt' => [],
            'user_prompt' => ['{target_locale}', '{source_locale}', '{restaurant_name}', '{city}', '{country}'],
        ],
    ];

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('prompt_type_id')
                    ->label('Type')
                    ->relationship('promptType', 'name')
                    ->options(PromptType::query()->pluck('name', 'id'))
                    ->required()
                    ->live(),

                TextInput::make('name')
                    ->required()
                    ->maxLength(255),

                Toggle::make('is_active')
                    ->label('Active')
                    ->helperText('Only one prompt per type can be active.'),

                Textarea::make('system_prompt')
                    ->label('System Prompt')
                    ->rows(4)
                    ->nullable()
                    ->columnSpanFull()
                    ->helperText(fn (Get $get) => self::placeholderHint($get, 'system_prompt'))
                    ->rules([
                        fn (Get $get): \Closure => self::placeholderRule($get, 'system_prompt'),
                    ]),

                Textarea::make('user_prompt')
                    ->label('User Prompt')
                    ->rows(6)
                    ->required()
                    ->columnSpanFull()
                    ->helperText(fn (Get $get) => self::placeholderHint($get, 'user_prompt'))
                    ->rules([
                        fn (Get $get): \Closure => self::placeholderRule($get, 'user_prompt'),
                    ]),
            ]);
    }

    private static function placeholderHint(Get $get, string $field): ?HtmlString
    {
        $placeholders = self::placeholdersFor($get, $field);
        if ($placeholders === []) {
            return null;
        }

        $codes = collect($placeholders)
            ->map(fn (string $p): string => '<code>'.e($p).'</code>')
            ->implode(', ');

        return new HtmlString(
            'Supported placeholders: '.$codes
            .'. <strong>Do not remove the braces</strong> — they are substituted at every LLM call.',
        );
    }

    private static function placeholderRule(Get $get, string $field): \Closure
    {
        $placeholders = self::placeholdersFor($get, $field);

        return function (string $_attribute, mixed $value, \Closure $fail) use ($placeholders): void {
            if (! is_string($value) || $value === '') {
                return;
            }
            foreach ($placeholders as $placeholder) {
                if (! str_contains($value, $placeholder)) {
                    $fail("This prompt must contain the {$placeholder} placeholder.");
                }
            }
        };
    }

    /** @return list<string> */
    private static function placeholdersFor(Get $get, string $field): array
    {
        $typeId = $get('prompt_type_id');
        if (! $typeId) {
            return [];
        }

        $slug = PromptType::query()->whereKey($typeId)->value('slug');
        if (! is_string($slug)) {
            return [];
        }

        return self::PLACEHOLDERS[$slug][$field] ?? [];
    }
}
