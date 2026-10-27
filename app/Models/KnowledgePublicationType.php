<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgePublicationType extends Model
{
    protected $fillable = ['name', 'slug', 'area', 'description', 'icon', 'field_schema', 'supports_series', 'is_academic', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['field_schema' => 'array', 'supports_series' => 'boolean', 'is_academic' => 'boolean', 'is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function publications()
    {
        return $this->hasMany(KnowledgePublication::class, 'publication_type_id');
    }

    public function series()
    {
        return $this->hasMany(KnowledgeSeries::class, 'publication_type_id');
    }
}
