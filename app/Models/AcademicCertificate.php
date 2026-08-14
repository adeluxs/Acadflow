<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
class AcademicCertificate extends Model
{
 protected $fillable=['uuid','verification_code','user_id','certifiable_type','certifiable_id','title','issuer','issued_on','file_path','metadata'];
 protected function casts():array{return ['issued_on'=>'date','metadata'=>'array'];}
 protected static function booted():void{static::creating(function(self $m){$m->uuid??=(string)Str::uuid();$m->verification_code??=hash('sha256',Str::uuid().microtime(true));});}
 public function getRouteKeyName():string{return 'uuid';} public function user(){return $this->belongsTo(User::class);} public function certifiable(){return $this->morphTo();}
}
