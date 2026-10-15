<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class CommercialAccount extends Model
{
    protected $fillable=['uuid','university_id','name','currency','prepaid_balance_minor','status','metadata'];
    protected function casts(): array { return ['prepaid_balance_minor'=>'integer','metadata'=>'array']; }
    protected static function booted(): void { static::creating(fn(self $m)=>$m->uuid ??= (string) Str::uuid()); }
    public function university(){ return $this->belongsTo(University::class); }
    public function requests(){ return $this->hasMany(CommercialAccountRequest::class); }

    public function sponsorsFeature(string $feature): bool
    {
        return $this->status === 'active' && in_array($feature, (array) data_get($this->metadata, 'sponsored_features', []), true);
    }
}
