<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResearchProjectMember extends Model
{
    protected $fillable = ['research_project_id', 'user_id', 'role', 'contribution_percent', 'permissions'];
    protected function casts(): array { return ['permissions' => 'array', 'contribution_percent' => 'decimal:2']; }
    public function project(): BelongsTo { return $this->belongsTo(ResearchProject::class, 'research_project_id'); }
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
