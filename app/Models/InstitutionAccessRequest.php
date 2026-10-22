<?php

declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
class InstitutionAccessRequest extends Model
{
    protected $hidden=['identifier_hash'];
    protected $fillable=['uuid','user_id','university_id','department_id','requested_role','status','verification_method','identifier_hash','identifier_last4','reviewed_by_user_id','reviewed_at','review_note','metadata'];
    protected function casts():array{return ['reviewed_at'=>'datetime','metadata'=>'array'];}
    protected static function booted():void{static::creating(fn(self $m)=>$m->uuid??=(string)Str::uuid());}
    public function user():BelongsTo{return $this->belongsTo(User::class)->withoutGlobalScope(\App\Models\Scopes\AcademicOrganisationBoundary::class);}
    public function university():BelongsTo{return $this->belongsTo(University::class);}
    public function department():BelongsTo{return $this->belongsTo(Department::class);}
    public function reviewer():BelongsTo{return $this->belongsTo(User::class,'reviewed_by_user_id');}
}
