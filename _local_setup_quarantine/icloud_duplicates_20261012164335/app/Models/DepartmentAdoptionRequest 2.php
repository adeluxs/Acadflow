<?php

declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class DepartmentAdoptionRequest extends Model
{
    protected $fillable=['uuid','requester_user_id','university_id','faculty_id','department_id','department_name','department_code','representative_title','status','reviewed_by_user_id','reviewed_at','review_note','metadata'];
    protected function casts():array{return ['reviewed_at'=>'datetime','metadata'=>'array'];}
    protected static function booted():void{static::creating(fn(self $m)=>$m->uuid??=(string)Str::uuid());}
    public function requester():BelongsTo{return $this->belongsTo(User::class,'requester_user_id');}
    public function university():BelongsTo{return $this->belongsTo(University::class);}
    public function faculty():BelongsTo{return $this->belongsTo(Faculty::class);}
    public function department():BelongsTo{return $this->belongsTo(Department::class);}
    public function reviewer():BelongsTo{return $this->belongsTo(User::class,'reviewed_by_user_id');}
}
