<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ImpactSnapshot extends Model
{
    protected $fillable = [
        'university_id',
        'subject_type',
        'subject_id',
        'snapshot_date',
        'metrics',
        'impact_score',
    ];

    protected function casts(): array
    {
        return [
            'snapshot_date' => 'date',
            'metrics' => 'array',
            'impact_score' => 'decimal:2',
        ];
    }
}
