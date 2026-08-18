<?php
    $profile = (array) config('ai.assistant_profiles.'.$assistantFeature, []);
    $assistantTitle = $assistantTitle ?? ($profile['label'] ?? str($assistantFeature)->headline());
    $assistantDescription = $assistantDescription ?? ($profile['description'] ?? 'Context-aware academic assistance through AcadFlow central AI routing.');
    $assistantSuggestions = $assistantSuggestions ?? ($profile['suggestions'] ?? []);
    $assistantId = $assistantId ?? ('acadflow-ai-'.substr(sha1($assistantFeature.'|'.$assistantEndpoint), 0, 12));
    $assistantUser = auth()->user();
    $assistantRuntime = app(\App\Services\Ai\AiRuntimeConfigService::class);
    $assistantModuleAccessible = $assistantUser
        && \App\Services\FeatureAccessService::canAccessFeature('ai_assistant', $assistantUser);
    $assistantFeatureEnabled = $assistantUser
        && $assistantRuntime->featureEnabled($assistantFeature, $assistantUser->university_id);
    $assistantModeDisabled = $assistantUser
        && $assistantRuntime->mode($assistantUser->university_id) === \App\Enums\AiMode::DISABLED;
    $assistantVisible = $assistantModuleAccessible && $assistantFeatureEnabled && ! $assistantModeDisabled;
?>
<?php if($assistantVisible): ?>
<section id="<?php echo e($assistantId); ?>" class="rounded-2xl border border-indigo-200 bg-gradient-to-br from-white to-indigo-50/50 p-5 shadow-sm" data-contextual-ai>
    <div class="flex flex-wrap items-start justify-between gap-3">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-lg font-black text-slate-900"><?php echo e($assistantTitle); ?></h2>
                <span class="rounded-full bg-indigo-100 px-2.5 py-1 text-[11px] font-bold uppercase tracking-wide text-indigo-700">Central AI Router</span>
            </div>
            <p class="mt-1 max-w-3xl text-sm leading-6 text-slate-600"><?php echo e($assistantDescription); ?></p>
        </div>
    </div>

    <?php if($assistantSuggestions): ?>
        <div class="mt-4 flex flex-wrap gap-2" data-ai-suggestions>
            <?php $__currentLoopData = $assistantSuggestions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $suggestion): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <button type="button" class="rounded-full border border-indigo-200 bg-white px-3 py-1.5 text-xs font-semibold text-indigo-700 hover:bg-indigo-50" data-ai-suggestion="<?php echo e($suggestion); ?>"><?php echo e($suggestion); ?></button>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    <?php endif; ?>

    <form class="mt-4" data-ai-form data-endpoint="<?php echo e($assistantEndpoint); ?>">
        <label class="block text-sm font-bold text-slate-700">Ask <?php echo e($assistantTitle); ?></label>
        <textarea data-ai-question rows="3" maxlength="2000" required class="mt-1.5 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" placeholder="Ask a clear question about the academic context on this page..."></textarea>
        <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
            <p class="text-xs text-slate-500">Obvious gibberish is rejected locally before a provider request. Context remains permission-scoped.</p>
            <button type="submit" class="rounded-xl bg-indigo-600 px-4 py-2 text-sm font-bold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60" data-ai-submit>Ask AI</button>
        </div>
    </form>

    <div class="mt-4 hidden rounded-xl border bg-white p-4" data-ai-result>
        <div class="flex flex-wrap items-center gap-2 text-xs">
            <span class="rounded-full bg-slate-100 px-2 py-1 font-semibold text-slate-700" data-ai-feature><?php echo e($assistantFeature); ?></span>
            <span class="hidden rounded-full bg-emerald-100 px-2 py-1 font-semibold text-emerald-700" data-ai-provider></span>
            <span class="hidden rounded-full bg-blue-100 px-2 py-1 font-semibold text-blue-700" data-ai-model></span>
            <span class="hidden rounded-full bg-amber-100 px-2 py-1 font-semibold text-amber-700" data-ai-fallback>Fallback used</span>
        </div>
        <div class="mt-3 whitespace-pre-wrap text-sm leading-7 text-slate-800" data-ai-answer></div>
        <div class="mt-4 hidden border-t border-slate-100 pt-3" data-ai-sources-wrap>
            <p class="text-xs font-bold uppercase tracking-wide text-slate-500">Authorized sources used</p>
            <div class="mt-2 space-y-1.5 text-xs text-slate-600" data-ai-sources></div>
        </div>
        <p class="mt-3 hidden text-[11px] text-slate-400" data-ai-request></p>
    </div>
</section>
<?php elseif($assistantModuleAccessible && $assistantFeatureEnabled && $assistantModeDisabled): ?>
<section class="rounded-2xl border border-slate-200 bg-slate-50 p-5 shadow-sm">
    <div class="flex items-start gap-3">
        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-slate-200 text-sm font-black text-slate-600">AI</div>
        <div>
            <h2 class="font-black text-slate-900"><?php echo e($assistantTitle); ?></h2>
            <p class="mt-1 text-sm text-slate-600">AI assistance is currently unavailable because the institution AI mode is disabled.</p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if (! $__env->hasRenderedOnce('060b8d25-9870-413d-8e9b-cf81568f6d41')): $__env->markAsRenderedOnce('060b8d25-9870-413d-8e9b-cf81568f6d41'); ?>
<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-contextual-ai]').forEach(root => {
        if (root.dataset.bound === '1') return;
        root.dataset.bound = '1';
        const form = root.querySelector('[data-ai-form]');
        const question = root.querySelector('[data-ai-question]');
        const submit = root.querySelector('[data-ai-submit]');
        const result = root.querySelector('[data-ai-result]');
        const answer = root.querySelector('[data-ai-answer]');
        const provider = root.querySelector('[data-ai-provider]');
        const model = root.querySelector('[data-ai-model]');
        const fallback = root.querySelector('[data-ai-fallback]');
        const sourcesWrap = root.querySelector('[data-ai-sources-wrap]');
        const sources = root.querySelector('[data-ai-sources]');
        const request = root.querySelector('[data-ai-request]');
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

        root.querySelectorAll('[data-ai-suggestion]').forEach(button => button.addEventListener('click', () => {
            question.value = button.dataset.aiSuggestion || '';
            question.focus();
        }));

        form?.addEventListener('submit', async event => {
            event.preventDefault();
            const value = question.value.trim();
            if (value.length < 3) return;
            submit.disabled = true;
            submit.textContent = 'Thinking…';
            result.classList.remove('hidden');
            answer.textContent = 'Processing your request…';
            provider.classList.add('hidden');
            model.classList.add('hidden');
            fallback.classList.add('hidden');
            sourcesWrap.classList.add('hidden');
            request.classList.add('hidden');

            try {
                const response = await fetch(form.dataset.endpoint, {
                    method: 'POST',
                    headers: {'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':csrf},
                    body: JSON.stringify({question:value})
                });
                const data = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(data.message || 'The AI request could not be completed.');
                answer.textContent = data.answer || 'No response was returned.';
                if (data.provider) { provider.textContent = `Provider: ${data.provider}`; provider.classList.remove('hidden'); }
                if (data.model) { model.textContent = `Model: ${data.model}`; model.classList.remove('hidden'); }
                if (data.fallback_used) fallback.classList.remove('hidden');
                if (Array.isArray(data.sources) && data.sources.length) {
                    sources.innerHTML = '';
                    data.sources.forEach(source => {
                        const row = document.createElement('div');
                        row.textContent = `${source.label || 'S'} · ${source.title || 'AcadFlow source'}${source.locator ? ` · ${source.locator}` : ''}`;
                        sources.appendChild(row);
                    });
                    sourcesWrap.classList.remove('hidden');
                }
                if (data.request_id) { request.textContent = `Request ID: ${data.request_id}`; request.classList.remove('hidden'); }
            } catch (error) {
                answer.textContent = error.message || 'AI assistance is currently unavailable.';
            } finally {
                submit.disabled = false;
                submit.textContent = 'Ask AI';
            }
        });
    });
});
</script>
<?php $__env->stopPush(); ?>
<?php endif; ?>
<?php /**PATH C:\Users\Admin\Desktop\Acadflow\resources\views/ai/_contextual-assistant.blade.php ENDPATH**/ ?>