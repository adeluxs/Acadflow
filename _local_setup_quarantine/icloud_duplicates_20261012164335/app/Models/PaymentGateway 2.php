<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PaymentGateway extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'is_test_mode',
        'credentials',
        'settings',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_test_mode' => 'boolean',
        'credentials' => 'encrypted:array',
        'settings' => 'array',
    ];

    public function transactions(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }
}
