<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeminarPanelMember extends Model
{
    protected $table = 'seminar_panel_members';
    protected $fillable = ['seminar_session_id','user_id','role','score','comment','scored_at'];
    protected function casts(): array { return ['score'=>'decimal:2','scored_at'=>'datetime']; }
    public function session() { return $this->belongsTo(SeminarSession::class, 'seminar_session_id'); }
    public function user() { return $this->belongsTo(User::class); }
}
