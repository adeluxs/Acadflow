<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeminarQuestion extends Model
{
    protected $table = 'seminar_questions';
    protected $fillable = ['uuid','seminar_session_id','asked_by','question','response','status','answered_by','answered_at'];
    protected function casts(): array { return ['answered_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn (self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function getRouteKeyName(): string { return 'uuid'; }
    public function session() { return $this->belongsTo(SeminarSession::class, 'seminar_session_id'); }
    public function asker() { return $this->belongsTo(User::class, 'asked_by'); }
    public function answerer() { return $this->belongsTo(User::class, 'answered_by'); }
}
