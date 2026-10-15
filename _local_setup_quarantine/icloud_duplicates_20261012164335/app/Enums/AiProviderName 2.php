<?php

namespace App\Enums;

/**
 * Supported AI providers. Every provider implements the same AiProviderInterface.
 */
enum AiProviderName: string
{
    case RULE_BASED = 'rule_based';
    case OPENAI = 'openai';
    case CLAUDE = 'claude';
    case GEMINI = 'gemini';
    case DEEPSEEK = 'deepseek';
    case GROK = 'grok';
    case OPENROUTER = 'openrouter';
    case AZURE_OPENAI = 'azure_openai';
    case OLLAMA = 'ollama';

    public function label(): string
    {
        return match ($this) {
            self::RULE_BASED => 'Rule-Based Engine',
            self::OPENAI => 'OpenAI',
            self::CLAUDE => 'Claude (Anthropic)',
            self::GEMINI => 'Gemini (Google)',
            self::DEEPSEEK => 'DeepSeek',
            self::GROK => 'Grok (xAI)',
            self::OPENROUTER => 'OpenRouter',
            self::AZURE_OPENAI => 'Azure OpenAI',
            self::OLLAMA => 'Ollama (Self-hosted)',
        };
    }

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
