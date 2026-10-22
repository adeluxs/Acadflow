<?php $__env->startSection('title','Wallet & Earnings'); ?>
<?php $__env->startSection('page-title','Wallet & Earnings'); ?>
<?php $__env->startSection('page-subtitle','Fund small purchases once, track creator earnings separately, and withdraw verified earnings securely'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $fmt = fn (int $minor) => \App\Support\Money::fromMinor($minor);
    $verifiedAccounts = $accounts->where('is_verified', true)->where('currency', $wallet->currency);
?>

<?php if((int) $wallet->recovery_debt_minor > 0): ?>
    <div class="mb-6 rounded-2xl border border-amber-300 bg-amber-50 p-5 text-amber-950">
        <p class="font-black">Earnings recovery in progress</p>
        <p class="mt-1 text-sm leading-6"><?php echo e($wallet->currency); ?> <?php echo e($fmt((int) $wallet->recovery_debt_minor)); ?> from a completed refund/reversal is still recoverable. New creator earnings will automatically reduce this amount before becoming withdrawable. Your spending balance is not affected.</p>
    </div>
<?php endif; ?>

<div class="mb-5 flex flex-wrap gap-3"><a href="<?php echo e(route('commerce.features')); ?>" class="inline-flex items-center rounded-xl bg-indigo-600 px-4 py-2.5 text-sm font-bold text-white">Paid Feature Access</a><span class="inline-flex items-center text-sm text-slate-500">Review fixed access, pay-per-use and AI pricing before using paid tools.</span></div>

<div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Spending balance</p><p class="mt-2 text-3xl font-black text-slate-950"><?php echo e($wallet->currency); ?> <?php echo e($fmt((int)$wallet->spending_balance_minor)); ?></p><p class="mt-2 text-xs text-slate-500">For AI, marketplace and other pay-as-you-use services.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Pending earnings</p><p class="mt-2 text-3xl font-black text-slate-950"><?php echo e($wallet->currency); ?> <?php echo e($fmt((int)$wallet->pending_earnings_minor)); ?></p><p class="mt-2 text-xs text-slate-500">Creator revenue still inside the settlement hold.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Available earnings</p><p class="mt-2 text-3xl font-black text-slate-950"><?php echo e($wallet->currency); ?> <?php echo e($fmt((int)$wallet->available_earnings_minor)); ?></p><p class="mt-2 text-xs text-slate-500">Verified earnings available for withdrawal.</p></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><p class="text-xs font-bold uppercase tracking-wide text-slate-500">Lifetime earnings</p><p class="mt-2 text-3xl font-black text-slate-950"><?php echo e($wallet->currency); ?> <?php echo e($fmt((int)$wallet->lifetime_earnings_minor)); ?></p><p class="mt-2 text-xs text-slate-500">Gross creator earnings recorded by AcadSyntra.</p></div>
</div>

<?php if($pendingFunding->isNotEmpty()): ?>
<section class="mt-6 rounded-2xl border bg-white p-5 shadow-sm">
    <h2 class="text-lg font-black">Payments awaiting confirmation</h2>
    <p class="mt-1 text-sm text-slate-500">Check an earlier payment before adding funds again.</p>
    <?php $__currentLoopData = $pendingFunding; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $funding): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <?php if($funding->transaction): ?>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t pt-3 text-sm">
            <span><?php echo e($funding->currency); ?> <?php echo e($fmt((int) $funding->amount_minor)); ?> · <?php echo e($funding->created_at->format('d M Y, H:i')); ?></span>
            <a href="<?php echo e(route('commerce.wallet.callback', $funding->transaction)); ?>" class="font-bold text-indigo-700">Check payment status</a>
        </div>
        <?php endif; ?>
    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
</section>
<?php endif; ?>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <form method="POST" action="<?php echo e(route('commerce.wallet.fund')); ?>" class="grid gap-3 rounded-2xl border bg-white p-5 shadow-sm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="idempotency_key" value="<?php echo e((string) \Illuminate\Support\Str::uuid()); ?>">
        <div><h2 class="text-lg font-black">Add funds</h2><p class="mt-1 text-sm text-slate-500">Top up once, then use your spending balance for lower-value internal transactions.</p></div>
        <label class="text-sm font-semibold">Amount (<?php echo e($wallet->currency); ?>)<input required type="number" inputmode="decimal" min="<?php echo e($walletRules['minimum_funding']); ?>" step="0.01" name="amount" value="<?php echo e(old('amount')); ?>" class="mt-1 w-full rounded-xl border-slate-300" placeholder="Minimum <?php echo e($walletRules['minimum_funding']); ?>"></label>
        <label class="text-sm font-semibold">Payment method<select required name="gateway" class="mt-1 w-full rounded-xl border-slate-300"><?php $__empty_0 = true; $__currentLoopData = $gateways; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $gateway): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><option value="<?php echo e($gateway->code); ?>"><?php echo e($gateway->name); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><option disabled>No payment gateway is currently available</option><?php endif; ?></select></label>
        <button <?php if($gateways->isEmpty()): echo 'disabled'; endif; ?> class="rounded-xl bg-slate-950 px-4 py-2.5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Continue securely</button>
        <p class="text-xs text-slate-500">Your balance is credited only after AcadSyntra verifies the payment amount and currency directly with the gateway.</p>
    </form>

    <form method="POST" action="<?php echo e(route('commerce.payout-accounts.store')); ?>" class="grid gap-3 rounded-2xl border bg-white p-5 shadow-sm">
        <?php echo csrf_field(); ?>
        <div><h2 class="text-lg font-black">Payout account</h2><p class="mt-1 text-sm text-slate-500">Withdrawal accounts must be verified before they can receive earnings.</p></div>
        <select name="provider" class="rounded-xl border-slate-300"><option value="bank">Bank</option><option value="paystack">Paystack transfer</option><option value="manual">Manual settlement</option></select>
        <input required name="account_name" class="rounded-xl border-slate-300" placeholder="Account name">
        <input required name="account_number" class="rounded-xl border-slate-300" placeholder="Account number">
        <div class="grid gap-3 sm:grid-cols-2"><input name="bank_name" class="rounded-xl border-slate-300" placeholder="Bank name"><input name="bank_code" class="rounded-xl border-slate-300" placeholder="Bank code"></div>
        <input required name="currency" value="<?php echo e($wallet->currency); ?>" class="rounded-xl border-slate-300">
        <button class="rounded-xl border border-slate-300 px-4 py-2.5 font-bold">Save account</button>
    </form>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <form method="POST" action="<?php echo e(route('commerce.withdrawals.store')); ?>" class="grid gap-3 rounded-2xl border bg-white p-5 shadow-sm">
        <?php echo csrf_field(); ?>
        <input type="hidden" name="idempotency_key" value="<?php echo e((string) \Illuminate\Support\Str::uuid()); ?>">
        <div><h2 class="text-lg font-black">Withdraw earnings</h2><p class="mt-1 text-sm text-slate-500">Minimum <?php echo e($wallet->currency); ?> <?php echo e($walletRules['minimum_withdrawal']); ?> · fee <?php echo e($walletRules['withdrawal_fee']); ?>%.</p></div>
        <select required name="payout_account_id" class="rounded-xl border-slate-300"><?php $__empty_0 = true; $__currentLoopData = $verifiedAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><option value="<?php echo e($account->id); ?>"><?php echo e($account->bank_name ?: str($account->provider)->headline()); ?> · <?php echo e($account->masked_account_number); ?> · <?php echo e($account->currency); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><option disabled>No verified payout account yet</option><?php endif; ?></select>
        <input required type="number" inputmode="decimal" min="<?php echo e($walletRules['minimum_withdrawal']); ?>" step="0.01" name="amount" class="rounded-xl border-slate-300" placeholder="Withdrawal amount">
        <button <?php if($verifiedAccounts->isEmpty()): echo 'disabled'; endif; ?> class="rounded-xl bg-blue-600 px-4 py-2.5 font-bold text-white disabled:cursor-not-allowed disabled:opacity-50">Request withdrawal</button>
        <p class="text-xs text-slate-500">The requested amount is reserved immediately so it cannot be withdrawn twice. The final amount sent equals your requested amount minus the configured withdrawal fee; all calculations are performed server-side in minor currency units.</p>
    </form>

    <div class="rounded-2xl border bg-white p-5 shadow-sm">
        <h2 class="text-lg font-black">Payout accounts</h2>
        <div class="mt-3 space-y-3"><?php $__empty_0 = true; $__currentLoopData = $accounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="flex items-center justify-between rounded-xl border p-3 text-sm"><div><p class="font-bold"><?php echo e($account->bank_name ?: str($account->provider)->headline()); ?></p><p class="text-slate-500"><?php echo e($account->masked_account_number); ?> · <?php echo e($account->currency); ?></p></div><span class="rounded-full px-2.5 py-1 text-xs font-bold <?php echo e($account->is_verified ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'); ?>"><?php echo e($account->is_verified ? 'Verified' : 'Verification pending'); ?></span></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="text-sm text-slate-500">No payout account saved yet.</p><?php endif; ?></div>
    </div>
</div>

<div class="mt-6 rounded-2xl border bg-white p-5 shadow-sm">
    <h2 class="text-lg font-black">Wallet activity</h2>
    <div class="mt-3 divide-y"><?php $__empty_0 = true; $__currentLoopData = $wallet->entries; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="flex flex-col justify-between gap-1 py-3 text-sm sm:flex-row sm:items-center"><div><p class="font-semibold text-slate-800"><?php echo e($entry->description ?: str($entry->entry_type)->replace('_',' ')->headline()); ?></p><p class="text-xs text-slate-500"><?php echo e(str($entry->balance_bucket ?: 'wallet')->replace('_',' ')->headline()); ?> · <?php echo e($entry->posted_at?->format('d M Y, H:i')); ?></p></div><span class="font-black <?php echo e($entry->direction==='credit' ? 'text-emerald-700' : 'text-slate-900'); ?>"><?php echo e($entry->direction==='credit'?'+':'-'); ?><?php echo e($wallet->currency); ?> <?php echo e($fmt((int)($entry->amount_minor ?? \App\Support\Money::toMinor((string)$entry->amount)))); ?></span></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="py-6 text-sm text-slate-500">No wallet activity yet.</p><?php endif; ?></div>
</div>

<div class="mt-6 grid gap-6 lg:grid-cols-2">
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><h2 class="text-lg font-black">Creator revenue</h2><div class="mt-3 divide-y"><?php $__empty_0 = true; $__currentLoopData = $allocations; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $allocation): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="flex justify-between gap-3 py-3 text-sm"><span><?php echo e(str($allocation->allocation_type)->headline()); ?> · <?php echo e(str($allocation->status)->replace('_',' ')->headline()); ?></span><span class="font-bold"><?php echo e($wallet->currency); ?> <?php echo e($fmt((int)($allocation->amount_minor ?? \App\Support\Money::toMinor((string)$allocation->amount)))); ?></span></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="py-6 text-sm text-slate-500">No creator revenue yet.</p><?php endif; ?></div><?php echo e($allocations->links()); ?></div>
    <div class="rounded-2xl border bg-white p-5 shadow-sm"><h2 class="text-lg font-black">Withdrawal history</h2><div class="mt-3 divide-y"><?php $__empty_0 = true; $__currentLoopData = $wallet->withdrawals->sortByDesc('created_at'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $withdrawal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="flex justify-between gap-3 py-3 text-sm"><div><p class="font-semibold"><?php echo e($wallet->currency); ?> <?php echo e($fmt((int)($withdrawal->amount_minor ?? \App\Support\Money::toMinor((string)$withdrawal->amount)))); ?></p><p class="text-xs text-slate-500"><?php echo e($withdrawal->created_at?->format('d M Y, H:i')); ?></p></div><span class="font-bold"><?php echo e(str($withdrawal->status)->headline()); ?></span></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="py-6 text-sm text-slate-500">No withdrawal requests yet.</p><?php endif; ?></div></div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/commerce/wallet.blade.php ENDPATH**/ ?>