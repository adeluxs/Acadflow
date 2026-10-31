@if($ownReferral)
<section class="amb-card amb-section">
    <h2>Your invitation</h2>
    <p>Your account was created through an ambassador invitation. Your progress: {{ $ownReferral->study_days }} study days; referral status: {{ $ownReferral->qualification_status }}.</p>
    <p class="amb-muted">Referral qualification requires a verified email, completed onboarding, study actions on three separate days within your first 30 days, and at least one action after seven days. Saving a substantive study note or reflection, or submitting a practice quiz with at least three answers, can count. Repeated copies of the same work do not count. The platform team reviews referrals before any performance reward; your private work is not shared with the ambassador.</p>
    @if(! auth()->user()->hasVerifiedEmail())
        <form method="POST" action="{{ route('ambassadors.verify-email') }}">@csrf<button class="amb-button amb-button-primary">Send email verification link</button></form>
    @else<p>Email verified.</p>@endif
</section>
@endif
