<section class="amb-card amb-section" id="rewards">
    <h2>Rewards</h2><p class="amb-muted">Pending awards are approved commitments awaiting fulfilment. Study wallet credits become available when fulfilled. Data allowances, bonuses and recognition are delivered by the team separately.</p>
    @forelse($rewards as $reward)
        <article class="amb-item">
            <div class="amb-item-head"><div><h3>{{ $reward->title }}</h3><p class="amb-muted">{{ \App\Models\AmbassadorReward::KINDS[$reward->kind] }}@if($reward->amount_minor) · {{ $reward->currency }} {{ \App\Support\Money::fromMinor($reward->amount_minor) }}@endif</p></div><span class="amb-badge amb-badge-{{ $reward->status }}">{{ $reward->status }}</span></div>
            <p class="amb-body">{{ $reward->reason }}</p>
            <p class="amb-muted">Award reference: {{ $reward->award_key }}@if($reward->fulfilled_at) · Fulfilled {{ $reward->fulfilled_at->format('j M Y') }}@endif</p>
            @if(($adminReview ?? false) && $reward->referral_id)<p class="amb-muted">Backed by referral #{{ $reward->referral_id }}. Qualification is rechecked at fulfilment.</p>@endif
            @if($reward->fulfilment_reference)<p class="amb-muted">Delivery reference: {{ $reward->fulfilment_reference }}</p>@endif
            @if(($adminReview ?? false) && $reward->status === 'pending')
                @if(isset($blockedRewards[$reward->id]))<p class="amb-note">Do not pay or deliver this award yet. The referral needs qualification or a fresh review. Older performance bonuses without a linked referral must be cancelled and reissued with a qualified referral.</p>
                @elseif($application->status === 'approved')
                    <form method="POST" action="{{ route('admin.ambassadors.rewards.fulfil', $reward) }}">@csrf
                        @if($reward->kind !== 'wallet_credit')<div class="amb-fields"><label class="amb-field amb-wide">Payment / delivery reference<input name="fulfilment_reference" maxlength="160" required><small>Complete the payment or delivery first. This reference is visible to the ambassador.</small></label></div>@endif
                        <div class="amb-actions"><button class="amb-button amb-button-primary" onclick="return confirm('{{ $reward->kind === 'wallet_credit' ? 'Credit this amount to the ambassador wallet now?' : 'Confirm the payment or delivery has already been completed?' }}')">{{ $reward->kind === 'wallet_credit' ? 'Credit study wallet now' : 'Mark delivered' }}</button></div>
                    </form>
                @else<p class="amb-note">Fulfilment is paused until this ambassador is approved again.</p>@endif
                <form method="POST" action="{{ route('admin.ambassadors.rewards.cancel', $reward) }}" class="amb-actions" onsubmit="return confirm('Cancel this pending award? The record is kept for audit.')">@csrf<button class="amb-button amb-button-danger">Cancel pending award</button></form>
            @endif
        </article>
    @empty<p class="amb-muted">No rewards have been awarded yet.</p>@endforelse
    <div class="amb-pagination">{{ $rewards->fragment('rewards')->links() }}</div>
</section>
