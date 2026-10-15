<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LecturerLayoutPreference extends Model
{
    protected $fillable = [
        'user_id',
        'required_fonts',
        'page_size',
        'min_margin_inches',
        'line_spacing',
        'min_font_size_pt',
        'require_page_numbering',
        'require_institution_branding',
    ];

    protected function casts(): array
    {
        return [
            'required_fonts' => 'array',
            'min_margin_inches' => 'decimal:2',
            'min_font_size_pt' => 'integer',
            'require_page_numbering' => 'boolean',
            'require_institution_branding' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
