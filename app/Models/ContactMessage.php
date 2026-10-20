<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    protected $fillable = ['reference', 'name', 'email', 'topic', 'message', 'status'];

    public const TOPICS = [
        'account' => 'Account & access', 'institution' => 'Institution enquiry',
        'product' => 'Product question', 'privacy' => 'Privacy request',
        'security' => 'Security concern', 'other' => 'Something else',
    ];
}
