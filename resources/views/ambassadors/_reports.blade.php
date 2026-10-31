<section class="amb-card amb-section" id="activity-reports">
    <h2>Activity reports</h2><p class="amb-muted">Attendance figures are self-reported. A review confirms the report, not a referral count or automatic reward.</p>
    @forelse($reports as $report)
        <article class="amb-item">
            <div class="amb-item-head"><div><h3>{{ $report->title }}</h3><p class="amb-muted">{{ \App\Models\AmbassadorReport::KINDS[$report->kind] }}@if($report->task) · {{ $report->task->title }}@endif · {{ $report->occurred_on->format('j M Y') }} · {{ $report->participants }} participants reported</p></div><span class="amb-badge amb-badge-{{ $report->status }}">{{ $report->status }}</span></div>
            <p class="amb-body">{{ $report->summary }}</p>
            @if($report->evidence_url)<p class="amb-muted"><a href="{{ $report->evidence_url }}" target="_blank" rel="noopener noreferrer nofollow">Open supporting link ↗</a> <span>(external site — check before trusting)</span></p>@endif
            @if($report->evidence_path)<p class="amb-muted"><a href="{{ ($adminReview ?? false) ? route('admin.ambassadors.reports.evidence', $report) : route('ambassadors.reports.evidence', $report) }}" target="_blank" rel="noopener" data-no-loader="1">Open supporting file</a> · {{ $report->evidence_name }} · {{ max(1, (int) ceil(($report->evidence_size ?? 0) / 1024)) }} KB</p>@endif
            @if($report->review_note)<p class="amb-note">Team feedback: {{ $report->review_note }}</p>@endif
            @if(($adminReview ?? false) && $report->status === 'pending')
                <form method="POST" action="{{ route('admin.ambassadors.reports.review', $report) }}">@csrf @method('PATCH')
                    <div class="amb-fields"><label class="amb-field amb-wide">Review note<textarea name="review_note" rows="2" minlength="5" maxlength="2000" required></textarea></label></div>
                    <div class="amb-actions"><button class="amb-button amb-button-primary" name="status" value="approved">Approve report</button><button class="amb-button amb-button-danger" name="status" value="rejected" onclick="return confirm('Reject this activity report? The note is shown to the ambassador.')">Reject report</button></div>
                </form>
            @endif
        </article>
    @empty<p class="amb-muted">No reports yet. A short demo or a useful piece of student feedback is a good start.</p>@endforelse
    <div class="amb-pagination">{{ $reports->fragment('activity-reports')->links() }}</div>
</section>
