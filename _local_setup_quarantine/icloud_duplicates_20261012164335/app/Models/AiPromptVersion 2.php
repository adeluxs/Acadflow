<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiPromptVersion extends Model
{
    protected $fillable = [
        'university_id',
        'feature',
        'version',
        'system_prompt',
        'user_template',
        'response_schema',
        'settings',
        'is_active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'response_schema' => 'array',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
