<?php

declare(strict_types=1);

namespace App\Services\Commerce;

use App\Models\LedgerJournal;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LedgerService
{
    /** @param list<array{wallet_account_id?:int|null,account_code:string,direction:string,amount_minor:int,metadata?:array}> $postings */
    public function post(string $reference, string $operation, string $currency, array $postings, ?User $user = null, array $metadata = []): LedgerJournal
    {
        return DB::transaction(function () use ($reference,$operation,$currency,$postings,$user,$metadata) {
            if ($existing = LedgerJournal::query()->where('reference',$reference)->first()) return $existing->load('postings');
            $debits=0;$credits=0;
            foreach($postings as $posting){
                $amount=(int)($posting['amount_minor']??0);
                if($amount<=0) throw ValidationException::withMessages(['amount'=>'Ledger postings must be greater than zero.']);
                if(($posting['direction']??'')==='debit') $debits+=$amount;
                elseif(($posting['direction']??'')==='credit') $credits+=$amount;
                else throw ValidationException::withMessages(['ledger'=>'Ledger direction must be debit or credit.']);
            }
            if($debits!==$credits) throw ValidationException::withMessages(['ledger'=>'Ledger journal is not balanced.']);
            $journal=LedgerJournal::create(['reference'=>$reference,'operation'=>$operation,'user_id'=>$user?->id,'currency'=>strtoupper($currency),'status'=>'posted','metadata'=>$metadata,'posted_at'=>now()]);
            foreach($postings as $posting){ $journal->postings()->create($posting+['currency'=>strtoupper($currency)]); }
            return $journal->load('postings');
        }, 3);
    }
}
