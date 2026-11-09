@if(in_array($question->id, session('reported_questions', []), true))
    <span class="mt-2 inline-block text-xs font-semibold text-slate-500">Thanks, reported.</span>
@else
    <form method="POST" action="{{ route('knowledge.rewards.quiz.report', $question) }}" class="{{ ($compact ?? false) ? '' : 'mt-2' }} inline-flex items-center gap-2">
        @csrf
        <label class="sr-only" for="report-{{ $question->id }}">What is wrong with this question?</label>
        <select id="report-{{ $question->id }}" name="reason" class="rounded-lg border-slate-200 py-1 text-xs">
            <option value="wrong_answer">The marked answer is wrong</option>
            <option value="unclear">The question is unclear</option>
            <option value="not_in_guide">It isn't covered in the guide</option>
        </select>
        <button class="rounded-lg border border-slate-200 px-2.5 py-1 text-xs font-bold text-slate-600 hover:bg-slate-50">Report</button>
    </form>
@endif
