<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KnowledgeReadingProgress extends Model
{
    protected $table = 'knowledge_reading_progress';

    protected $fillable = ['user_id', 'knowledge_series_id', 'knowledge_publication_id', 'progress_percent', 'last_read_at', 'completed_at'];

    protected function casts(): array
    {
        return ['progress_percent' => 'integer', 'last_read_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function series()
    {
        return $this->belongsTo(KnowledgeSeries::class, 'knowledge_series_id');
    }

    public function publication()
    {
        return $this->belongsTo(KnowledgePublication::class, 'knowledge_publication_id');
    }
}
