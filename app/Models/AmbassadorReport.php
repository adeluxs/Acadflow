<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AmbassadorReport extends Model
{
    public const KINDS = ['demo' => 'Product demo', 'onboarding' => 'Onboarding session', 'study_session' => 'Study session', 'feedback' => 'Student feedback'];
    protected $guarded = ['id'];
    protected $hidden = ['evidence_path'];

    public const EVIDENCE_MIMES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'application/pdf' => 'pdf'];
    public const EVIDENCE_MAX_KB = 5120;
    protected function casts(): array { return ['occurred_on' => 'date', 'reviewed_at' => 'datetime']; }
    public function application() { return $this->belongsTo(AmbassadorApplication::class, 'ambassador_application_id'); }
    public function task() { return $this->belongsTo(AmbassadorTask::class, 'ambassador_task_id'); }
}
