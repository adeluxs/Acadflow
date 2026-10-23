<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class StudentStudySession extends Model
{
    protected $fillable = ['uuid', 'user_id', 'course_id', 'material_id', 'note_id', 'selected_text', 'question', 'explanation', 'source_fingerprint', 'explained_at', 'last_studied_at'];

    protected function casts(): array
    {
        return ['explained_at' => 'datetime', 'last_studied_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(fn (self $session) => $session->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    public function material()
    {
        return $this->belongsTo(CourseMaterial::class, 'material_id');
    }

    public function note()
    {
        return $this->belongsTo(StudyNote::class, 'note_id');
    }

    public function quizzes()
    {
        return $this->hasMany(PracticeQuiz::class, 'study_session_id');
    }

    public function title(): string
    {
        return $this->material?->title ?? $this->note?->title ?? 'Study session';
    }
}
