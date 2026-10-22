<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationReminderState extends Model
{
    public $timestamps = false;
    protected $guarded = ['id'];
    protected $attributes = ['sent_count' => 0];
    protected function casts(): array { return ['sent_count' => 'integer', 'last_sent_at' => 'datetime', 'next_due_at' => 'datetime', 'completed_at' => 'datetime']; }
}
