<?php

declare(strict_types=1);
namespace App\Services\Commerce;
use App\Models\MonetizationIdempotencyKey;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class IdempotencyService
{
    public function execute(string $operation,string $key,array $request,?User $user,callable $callback): mixed
    {
        $key=trim($key); if($key==='') throw ValidationException::withMessages(['idempotency_key'=>'An idempotency key is required.']);
        $hash=hash('sha256',json_encode($request,JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE)?:'');
        return DB::transaction(function() use($operation,$key,$hash,$user,$callback){
            $row=MonetizationIdempotencyKey::query()->where('operation',$operation)->where('idempotency_key',$key)->lockForUpdate()->first();
            if($row){
                if($row->request_hash && !hash_equals($row->request_hash,$hash)) throw ValidationException::withMessages(['idempotency_key'=>'This idempotency key was already used for a different request.']);
                if($row->status==='completed') return $row->response;
                if($row->status==='processing' && $row->updated_at?->gt(now()->subMinutes(5))) throw ValidationException::withMessages(['idempotency_key'=>'This request is already being processed.']);
                $row->update(['status'=>'processing']);
            } else {
                $row=MonetizationIdempotencyKey::create(['user_id'=>$user?->id,'operation'=>$operation,'idempotency_key'=>$key,'request_hash'=>$hash,'status'=>'processing','expires_at'=>now()->addDays(7)]);
            }
            try { $result=$callback(); $payload=$result instanceof \Illuminate\Database\Eloquent\Model ? ['model'=>$result::class,'id'=>$result->getKey()] : (is_array($result)?$result:['value'=>$result]); $row->update(['status'=>'completed','result_type'=>$result instanceof \Illuminate\Database\Eloquent\Model?$result::class:null,'result_id'=>$result instanceof \Illuminate\Database\Eloquent\Model?$result->getKey():null,'response'=>$payload]); return $result; }
            catch(\Throwable $e){ $row->update(['status'=>'failed']); throw $e; }
        },3);
    }
}
