<div class="amb-metrics" aria-label="Referral results">
    @foreach(['registrations' => 'Referred signups', 'activated' => 'Activated referrals', 'retained' => 'Returned in days 7–30', 'paying' => 'Paid buyers', 'qualified' => 'Reviewed qualifications'] as $key => $label)
        <div class="amb-metric"><strong>{{ number_format($metrics[$key]) }}</strong><span>{{ $label }}</span></div>
    @endforeach
</div>
<p class="amb-muted">Activation means completing onboarding and recording an active session. Retention means returning between 7 and 30 days after signup. Paid buyers have a currently paid, non-zero marketplace order after signup. Inactive accounts are excluded from these three measures. Results are cumulative; recent signups need time to qualify.</p>

<p class="amb-muted">Qualified referrals have passed email, study activity and admin review requirements. Every referral-backed reward is checked again before fulfilment. Signups, visits and purchases alone never qualify a referral.</p>
