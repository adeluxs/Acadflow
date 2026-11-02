{{-- Plain-language explanation of the automatic referral reward. Expects $rule from AmbassadorService::referralRewardRule(). --}}
@php($example = 5)
<ol class="amb-public-steps amb-reward-steps">
    <li><strong>You invite a classmate</strong> with your personal invitation link. They create their own free account.</li>
    <li><strong>They really use AcadSyntra:</strong> they verify their email and study (notes, reflections or practice quizzes) on 3 separate days in their first month, at least once after day 7.</li>
    <li><strong>The team reviews the referral</strong> to confirm it is a real, separate student, not a duplicate or fake account.</li>
    <li><strong>You are paid automatically</strong> the moment the referral is approved, straight into your AcadSyntra wallet.</li>
</ol>
<div class="amb-grid amb-section">
    @if($rule['cash_minor'] > 0)
    <div class="amb-card"><h3>{{ $rule['cash_label'] }} cash per student</h3><p class="amb-muted">Goes into your wallet <strong>earnings</strong>. You can withdraw it to your own bank account once your earnings reach the withdrawal minimum of {{ $rule['min_withdrawal_label'] }}@if($rule['referrals_to_withdraw'] > 1) (that is {{ $rule['referrals_to_withdraw'] }} approved students)@endif.@if((float) $rule['withdrawal_fee_percent'] > 0) A {{ $rule['withdrawal_fee_percent'] }}% withdrawal fee applies.@endif Add your bank account in your wallet; the team verifies it once.</p></div>
    @endif
    @if($rule['credit_minor'] > 0)
    <div class="amb-card"><h3>{{ $rule['credit_label'] }} study wallet credit per student</h3><p class="amb-muted">Goes into your wallet <strong>spending balance</strong>. Use it for AcadSyntra plans, AI study tools and paid content. Study credit cannot be withdrawn as cash.</p></div>
    @endif
</div>
<p class="amb-note"><strong>Example:</strong> {{ $example }} classmates join with your link and all {{ $example }} are approved. You receive {{ collect([$rule['cash_minor'] > 0 ? ($rule['naira'])($rule['cash_minor'] * $example).' cash you can withdraw' : null, $rule['credit_minor'] > 0 ? ($rule['naira'])($rule['credit_minor'] * $example).' study credit' : null])->filter()->implode(' and ') }}. If only 3 of them qualify, you receive the reward for those 3.</p>
<p class="amb-muted">What does not earn rewards: visits or clicks on your link, signups that never study, people who already had an account, your own extra accounts, and duplicate or fake accounts. Each student counts once. Amounts are set by AcadSyntra and can change; the amount shown when a referral is approved is the amount you receive.</p>
