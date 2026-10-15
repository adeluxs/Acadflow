<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class GroupResource extends Model
{
    use SoftDeletes;
    protected $fillable = ['uuid','group_id','uploaded_by','media_asset_id','title','description','external_url','visibility'];
    protected static function booted(): void { static::creating(fn(self $model) => $model->uuid ??= (string) Str::uuid()); }
    public function group() { return $this->belongsTo(Group::class); }
    public function uploader() { return $this->belongsTo(User::class, 'uploaded_by'); }
    public function media() { return $this->belongsTo(MediaAsset::class, 'media_asset_id'); }
    public function getRouteKeyName(): string { return 'uuid'; }
}
