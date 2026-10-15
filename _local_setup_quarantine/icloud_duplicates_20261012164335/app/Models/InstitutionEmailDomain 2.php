<?php

declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class InstitutionEmailDomain extends Model
{
    protected $fillable=['university_id','domain','status','proposed_by','verified_by','verified_at'];
    protected function casts():array{return ['verified_at'=>'datetime'];}
    public function university():BelongsTo{return $this->belongsTo(University::class);}
    public function proposer():BelongsTo{return $this->belongsTo(User::class,'proposed_by');}
    public function verifier():BelongsTo{return $this->belongsTo(User::class,'verified_by');}
}
