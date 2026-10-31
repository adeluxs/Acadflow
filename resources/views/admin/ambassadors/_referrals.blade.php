<section class="amb-card amb-section" id="referral-reviews">
    <h2>Referral review queue</h2>
    <p class="amb-muted">Verify that each referral represents a real, separate student. Email ownership and study activity are minimum requirements, not proof of identity. Investigate shared browsers, mailbox aliases and signup bursts; campus networks may be shared legitimately. Review notes and student identities are visible only to Super Admin.</p>
    <nav class="amb-actions" aria-label="Referral review status">
        @foreach(['pending' => 'Awaiting review', 'qualified' => 'Qualified', 'disqualified' => 'Disqualified', 'all' => 'All referrals'] as $value => $label)
            <a class="amb-button {{ $reviewStatus === $value ? 'amb-button-primary' : '' }}" href="{{ route('admin.ambassadors.show', ['application' => $application, 'referral_status' => $value]) }}#referral-reviews" @if($reviewStatus === $value) aria-current="page" @endif>{{ $label }}</a>
        @endforeach
    </nav>
    @forelse($referrals as $referral)
        @php($assessment = app(\App\Services\AmbassadorQualificationService::class)->assessment($referral))
        <article class="amb-item">
            <div class="amb-item-head"><h3>Referral #{{ $referral->id }} · {{ $referral->user?->full_name }}</h3><span class="amb-badge">{{ ucfirst($referral->qualification_status) }}</span></div>
            <p class="amb-muted">{{ $referral->user?->email }} · Registered {{ $referral->registered_at->format('j M Y') }} · {{ $referral->study_days }} study days</p>
            @if($assessment['reasons'])<ul>@foreach($assessment['reasons'] as $reason)<li>{{ $reason }}</li>@endforeach</ul>
            @else<p>Meets the minimum activity and email requirements.</p>@endif
            @if($assessment['flags'])<div class="amb-note"><strong>Investigate before approval</strong><ul>@foreach($assessment['flags'] as $flag)<li>{{ $flag }}</li>@endforeach</ul></div>@endif
            @if($referral->qualification_status === 'qualified' && ! $assessment['review_current'])<p class="amb-note">Evidence has changed since approval. Rewards are blocked until a fresh review is completed.</p>@endif
            @if($referral->review_note)<p class="amb-body">Latest review: {{ $referral->review_note }}</p>@endif
            <form method="POST" action="{{ route('admin.ambassadors.referrals.review', $referral) }}">@csrf @method('PATCH')
                <input type="hidden" name="evidence_signature" value="{{ $assessment['signature'] }}">
                <div class="amb-fields"><label class="amb-field amb-wide">Private findings and verification evidence<textarea name="review_note" rows="2" minlength="20" maxlength="2000" required></textarea></label></div>
                @if($assessment['flags'])<label class="amb-check"><input type="checkbox" name="acknowledge_risks" value="1"> I investigated these flags and recorded why this is a legitimate, separate student.</label>@endif
                <div class="amb-actions">
                    <button class="amb-button amb-button-primary" name="status" value="qualified" @disabled(! $assessment['eligible'] || $application->status !== 'approved')>Qualify referral</button>
                    <button class="amb-button amb-button-danger" name="status" value="disqualified" onclick="return confirm('Disqualify this referral? Linked pending rewards stay blocked.')">Disqualify</button>
                    <button class="amb-button" name="status" value="pending">Hold for review</button>
                </div>
            </form>
        </article>
    @empty<p>No referrals match this status.</p>@endforelse
    <div class="amb-pagination">{{ $referrals->links() }}</div>
</section>
