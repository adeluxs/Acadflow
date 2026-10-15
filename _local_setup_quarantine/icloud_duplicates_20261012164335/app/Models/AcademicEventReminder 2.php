<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AcademicEventReminder extends Model
{
    protected $fillable = ['academic_event_id','minutes_before','channel','is_active','last_dispatched_at'];
    protected function casts(): array { return ['is_active'=>'boolean','last_dispatched_at'=>'datetime']; }
    public function event() { return $this->belongsTo(AcademicEvent::class, 'academic_event_id'); }
}
