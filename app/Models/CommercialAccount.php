<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class CommercialAccount extends Model
{
    protected $fillable=['uuid','university_id','department_id','plan_key','access_ends_at','name','currency','prepaid_balance_minor','status','metadata'];
    protected function casts(): array { return ['prepaid_balance_minor'=>'integer','metadata'=>'array','access_ends_at'=>'datetime']; }
    protected static function booted(): void { static::creating(fn(self $m)=>$m->uuid ??= (string) Str::uuid()); }
    public function university(){ return $this->belongsTo(University::class); }
    public function department(){ return $this->belongsTo(Department::class); }
    public function isAvailable(): bool { return $this->status === 'active' && (!$this->access_ends_at || $this->access_ends_at->isFuture()); }
    public function requests(){ return $this->hasMany(CommercialAccountRequest::class); }

    public function sponsorsFeature(string $feature): bool
    {
        return $this->isAvailable() && in_array($feature, (array) data_get($this->metadata, 'sponsored_features', []), true);
    }
}
