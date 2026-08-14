<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SettingOverride extends Model
{
    protected $fillable = ['setting_id', 'university_id', 'value', 'type', 'updated_by'];

    public function setting(): BelongsTo { return $this->belongsTo(Setting::class); }
    public function university(): BelongsTo { return $this->belongsTo(University::class); }
    public function updater(): BelongsTo { return $this->belongsTo(User::class, 'updated_by'); }
}
