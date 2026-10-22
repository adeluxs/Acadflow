<?php $__env->startSection('title', 'AI Diagnostics'); ?>

<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6 sm:px-6 lg:px-8">
    <div class="overflow-hidden rounded-3xl bg-slate-950 p-6 text-white shadow-xl sm:p-8">
        <div class="flex flex-col gap-5 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <div class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-3 py-1 text-xs font-semibold">AI runtime observability</div>
                <h1 class="mt-3 text-3xl font-bold tracking-tight">AI Diagnostics</h1>
                <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-300">Verify the configuration AcadSyntra will actually use at runtime. Credentials and secret values are never displayed here.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                <a href="<?php echo e(route('ai.settings')); ?>" class="rounded-xl border border-white/20 bg-white/10 px-4 py-2 text-sm font-bold hover:bg-white/20">← AI Settings</a>
                <a href="<?php echo e(route('ai.analytics')); ?>" class="rounded-xl bg-white px-4 py-2 text-sm font-bold text-slate-950 hover:bg-slate-100">Usage Analytics</a>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
        <?php $__currentLoopData = [
            ['AI Mode', str_replace('_',' ',ucfirst($mode))],
            ['Default Provider', $defaultProvider ?: '—'],
            ['Default Model', $defaultModel ?: '—'],
            ['Fallback', $fallbackProvider ?: 'None'],
            ['Config Generation', '#'.$configGeneration],
            ['Queue', $queueConnection ?: 'default'],
        ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$value]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                <div class="text-[11px] font-bold uppercase tracking-wide text-slate-400"><?php echo e($label); ?></div>
                <div class="mt-2 break-words text-sm font-bold text-slate-950"><?php echo e($value); ?></div>
            </div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold text-slate-950">Provider Health & Capability Registry</h2>
            <p class="mt-1 text-sm text-slate-500">Health results are cached to avoid unnecessary provider API calls. Use Test Connection from AI Settings for an explicit live check.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500">
                    <tr>
                        <th class="px-5 py-3">Provider</th><th class="px-5 py-3">Enabled</th><th class="px-5 py-3">Configured</th><th class="px-5 py-3">Model</th><th class="px-5 py-3">Capabilities</th><th class="px-5 py-3">Health</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    <?php $__currentLoopData = $providers; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => $provider): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <?php ($health = $provider['health'] ?? ['status'=>'not_checked','message'=>'Not checked recently.']); ?>
                        <tr class="align-top">
                            <td class="px-5 py-4 font-bold text-slate-950"><?php echo e($provider['label'] ?? $name); ?><div class="mt-1 font-mono text-[11px] font-normal text-slate-400"><?php echo e($name); ?></div></td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold <?php echo e(!empty($provider['enabled']) ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'); ?>"><?php echo e(!empty($provider['enabled']) ? 'Enabled' : 'Disabled'); ?></span></td>
                            <td class="px-5 py-4"><span class="rounded-full px-2.5 py-1 text-xs font-bold <?php echo e(!empty($provider['configured']) ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-50 text-amber-700'); ?>"><?php echo e(!empty($provider['configured']) ? 'Ready' : 'Incomplete'); ?></span></td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-700"><?php echo e($provider['model'] ?: '—'); ?></td>
                            <td class="px-5 py-4"><div class="flex max-w-sm flex-wrap gap-1"><?php $__currentLoopData = ($provider['capabilities'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $cap): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><span class="rounded-md bg-indigo-50 px-2 py-1 text-[11px] font-semibold text-indigo-700"><?php echo e($cap); ?></span><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></td>
                            <td class="px-5 py-4"><div class="font-semibold text-slate-800"><?php echo e(str_replace('_',' ',ucfirst($health['status'] ?? 'not_checked'))); ?></div><div class="mt-1 max-w-xs text-xs leading-5 text-slate-500"><?php echo e($health['message'] ?? ''); ?></div></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold text-slate-950">Feature Routing Matrix</h2>
            <p class="mt-1 text-sm text-slate-500">This is the route resolver result that each AI feature sees now—not a copy of environment variables.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Feature</th><th class="px-5 py-3">Enabled</th><th class="px-5 py-3">Configuration</th><th class="px-5 py-3">Resolved Provider</th><th class="px-5 py-3">Model</th><th class="px-5 py-3">Fallback Chain</th><th class="px-5 py-3">Capabilities</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php $__currentLoopData = $routes; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature => $route): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <tr class="align-top">
                            <td class="px-5 py-4 font-mono text-xs font-bold text-slate-900"><?php echo e($feature); ?></td>
                            <td class="px-5 py-4"><?php echo e(($route['feature_enabled'] ?? false) ? 'Yes' : 'No'); ?></td>
                            <td class="px-5 py-4 text-xs text-slate-600"><?php echo e(($route['requested_configuration'] ?? 'global') === 'global' ? 'Use Global Default' : 'Feature Override'); ?></td>
                            <td class="px-5 py-4 font-semibold text-slate-900"><?php echo e($route['resolved_provider'] ?? '—'); ?></td>
                            <td class="px-5 py-4 font-mono text-xs text-slate-600"><?php echo e($route['resolved_model'] ?? '—'); ?></td>
                            <td class="px-5 py-4 text-xs text-slate-600"><?php $__empty_0 = true; $__currentLoopData = ($route['provider_chain'] ?? []); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $entry): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div><?php echo e($entry['role'] ?? 'route'); ?> → <strong><?php echo e($entry['provider'] ?? '—'); ?></strong> / <?php echo e($entry['model'] ?? '—'); ?></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><span class="text-slate-400">No external chain</span><?php endif; ?></td>
                            <td class="px-5 py-4 text-xs"><div class="mb-1"><?php echo e(!empty($route['provider_compatible']) ? '✓ Compatible' : '— / Check mode'); ?></div><div class="text-slate-400"><?php echo e(implode(', ', $route['required_capabilities'] ?? []) ?: 'Local/no provider requirement'); ?></div></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
        <div class="border-b border-slate-200 px-5 py-4 sm:px-6">
            <h2 class="text-lg font-bold text-slate-950">Recent AI Requests</h2>
            <p class="mt-1 text-sm text-slate-500">Use request IDs to trace provider switching, fallbacks and failures. User prompts, responses and credentials are not displayed here.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-200 text-sm">
                <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">When</th><th class="px-5 py-3">Request</th><th class="px-5 py-3">Feature</th><th class="px-5 py-3">Mode</th><th class="px-5 py-3">Provider / Model</th><th class="px-5 py-3">Fallback</th><th class="px-5 py-3">Status</th><th class="px-5 py-3">Latency</th></tr></thead>
                <tbody class="divide-y divide-slate-100">
                    <?php $__empty_0 = true; $__currentLoopData = $recent; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                        <tr>
                            <td class="whitespace-nowrap px-5 py-4 text-xs text-slate-500"><?php echo e(optional($log->created_at)->diffForHumans()); ?></td>
                            <td class="px-5 py-4 font-mono text-[11px] text-slate-500"><?php echo e($log->request_id ?: '—'); ?></td>
                            <td class="px-5 py-4 font-mono text-xs font-semibold text-slate-800"><?php echo e($log->feature); ?></td>
                            <td class="px-5 py-4 text-xs"><?php echo e($log->mode ?: '—'); ?></td>
                            <td class="px-5 py-4"><div class="font-semibold text-slate-900"><?php echo e($log->provider ?: $log->source ?: '—'); ?></div><div class="font-mono text-[11px] text-slate-400"><?php echo e($log->model ?: '—'); ?></div></td>
                            <td class="px-5 py-4 text-xs"><?php echo e($log->fallback_used ? 'Yes → '.($log->fallback_provider ?: 'fallback') : 'No'); ?></td>
                            <td class="px-5 py-4"><span class="font-semibold <?php echo e($log->success ? 'text-emerald-700' : 'text-rose-700'); ?>"><?php echo e($log->success ? 'Successful' : 'Failed'); ?></span><?php if($log->error_type): ?><div class="mt-1 font-mono text-[11px] text-rose-500"><?php echo e($log->error_type); ?></div><?php endif; ?></td>
                            <td class="px-5 py-4 text-xs text-slate-500"><?php echo e($log->processing_time !== null ? number_format((float)$log->processing_time, 3).'s' : '—'); ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                        <tr><td colspan="8" class="px-5 py-10 text-center text-sm text-slate-500">No AI usage records are available yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm leading-6 text-amber-900">
        <strong>Deliberate capability boundary:</strong> this AcadSyntra source does not include a live open-web search adapter or an external embedding-provider adapter. Provider AI is used for supported reasoning/generation requests; indexed semantic retrieval continues to use AcadSyntra's existing local index. No provider is presented as live web research unless a real search adapter is implemented later.
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/ai/diagnostics.blade.php ENDPATH**/ ?>