<?php $__env->startSection('title', 'Creator profile'); ?>
<?php $__env->startSection('page-title', 'Creator profile'); ?>
<?php $__env->startSection('page-subtitle', 'Your author identity in the Knowledge Hub'); ?>
<?php $__env->startSection('content'); ?>
<?php
    $public = (bool) old('is_public', $profile?->is_public ?? false);
    $profileFields = ['headline', 'biography', 'position', 'expertise', 'orcid', 'website'];
    $providedFields = count(array_filter($profileFields, fn ($field) => filled($profile?->{$field})));
    $verificationLabels = ['lecturer' => 'Lecturer', 'professor' => 'Professor', 'researcher' => 'Researcher', 'student_ambassador' => 'Student ambassador'];
    $pendingVerification = $verificationRequests->firstWhere('status', 'pending');
?>
<div class="mx-auto max-w-7xl space-y-6" data-creator-editor>
    <header class="overflow-hidden rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-indigo-800 p-6 text-white shadow-sm sm:p-8">
        <div class="flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div class="max-w-2xl"><p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-200">Knowledge Hub · Author identity</p><h1 class="mt-3 text-3xl font-black tracking-tight sm:text-4xl">Your creator profile</h1><p class="mt-3 text-sm leading-6 text-indigo-100">Introduce your expertise, connect your academic identity, and choose what readers see alongside your published work.</p></div>
            <div class="flex flex-wrap gap-3"><a href="<?php echo e(route('knowledge.creator', $creator)); ?>" class="rounded-xl bg-white px-4 py-3 text-sm font-bold text-indigo-950">View saved profile ↗</a><a href="<?php echo e(route('knowledge.manage')); ?>" class="rounded-xl border border-white/25 px-4 py-3 text-sm font-bold text-white hover:bg-white/10">My publications</a></div>
        </div>
        <div class="mt-6 flex flex-wrap gap-x-6 gap-y-2 border-t border-white/15 pt-4 text-xs text-indigo-100"><span><?php echo e($profile?->is_public ? 'Public creator page' : 'Creator page is private'); ?></span><span><?php echo e($providedFields); ?> of <?php echo e(count($profileFields)); ?> optional details added</span><span><?php echo e($profile?->updated_at ? 'Last saved '.$profile->updated_at->diffForHumans() : 'Ready for your first save'); ?></span></div>
    </header>
    <nav aria-label="Profile sections" class="flex flex-wrap gap-2 text-sm"><a href="#creator-identity" class="rounded-full border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700 hover:border-indigo-300">Identity</a><a href="#creator-expertise" class="rounded-full border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700 hover:border-indigo-300">Expertise & links</a><a href="#creator-visibility" class="rounded-full border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700 hover:border-indigo-300">Visibility</a><a href="#creator-verification" class="rounded-full border border-slate-200 bg-white px-4 py-2 font-semibold text-slate-700 hover:border-indigo-300">Verification</a></nav>
    <?php if(session('success')): ?><div role="status" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-5 py-4 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div><?php endif; ?>
    <?php if($errors->any()): ?><div role="alert" class="rounded-2xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-800"><p class="font-bold">Please review the following before saving.</p><ul class="mt-2 list-disc space-y-1 pl-5"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>
    <div class="grid items-start gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
        <form method="POST" action="<?php echo e(route('knowledge.creator.update')); ?>" class="min-w-0 space-y-6" id="creator-profile-form"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
            <section id="creator-identity" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex gap-4"><span aria-hidden="true" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-50 text-sm font-black text-indigo-700">01</span><div><h2 class="text-lg font-black text-slate-950">Public identity</h2><p class="mt-1 text-sm text-slate-500">A clear introduction for readers and collaborators.</p></div></div>
                <div class="mt-6 flex items-center gap-4 rounded-2xl bg-slate-50 p-4"><div class="grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-xl bg-indigo-100 font-black text-indigo-800"><?php if($creator->avatarMedia): ?><img src="<?php echo e(route('media.preview', $creator->avatarMedia)); ?>" alt="" class="h-full w-full object-cover"><?php else: ?><?php echo e(strtoupper(substr($creator->first_name ?: 'A', 0, 1))); ?><?php endif; ?></div><div class="min-w-0"><p class="font-bold text-slate-900"><?php echo e($creator->full_name); ?></p><a href="<?php echo e(route('onboarding.show', ['step' => 2, 'edit' => 1])); ?>" class="text-xs font-semibold text-indigo-700 hover:underline">Edit your name or photo in your account profile →</a></div></div>
                <div class="mt-6 space-y-5">
                    <div><label for="creator-headline" class="block text-sm font-bold text-slate-800">Professional headline</label><input id="creator-headline" name="headline" maxlength="255" value="<?php echo e(old('headline', $profile?->headline)); ?>" placeholder="e.g. Lecturer in computer science · Research methods educator" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" data-preview-input="headline" <?php if($errors->has('headline')): ?> aria-invalid="true" aria-describedby="creator-headline-error" <?php endif; ?>><?php $__errorArgs = ['headline'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p id="creator-headline-error" class="mt-2 text-xs text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <div><label for="creator-position" class="block text-sm font-bold text-slate-800">Academic or professional position <span class="font-normal text-slate-400">(optional)</span></label><input id="creator-position" name="position" maxlength="255" value="<?php echo e(old('position', $profile?->position)); ?>" placeholder="e.g. Senior lecturer, doctoral researcher, independent author" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"><?php $__errorArgs = ['position'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <div><div class="flex items-center justify-between gap-3"><label for="creator-biography" class="text-sm font-bold text-slate-800">About your work</label><span class="text-xs text-slate-400"><span data-biography-count><?php echo e(strlen(old('biography', $profile?->biography) ?? '')); ?></span>/5,000</span></div><textarea id="creator-biography" name="biography" maxlength="5000" rows="6" placeholder="Describe your academic interests, the work you share, and who it helps." class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" data-preview-input="biography"><?php echo e(old('biography', $profile?->biography)); ?></textarea><p class="mt-2 text-xs text-slate-500">Focus on your contribution. Keep contact details you want private out of your biography.</p><?php $__errorArgs = ['biography'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                </div>
            </section>
            <section id="creator-expertise" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex gap-4"><span aria-hidden="true" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-50 text-sm font-black text-indigo-700">02</span><div><h2 class="text-lg font-black text-slate-950">Expertise & academic links</h2><p class="mt-1 text-sm text-slate-500">Help people understand your focus and find your work.</p></div></div>
                <div class="mt-6 space-y-5">
                    <div><label for="creator-expertise-input" class="block text-sm font-bold text-slate-800">Areas of expertise</label><input id="creator-expertise-input" name="expertise" maxlength="1000" value="<?php echo e(old('expertise', implode(', ', $profile?->expertise ?? []))); ?>" placeholder="Research methods, public health, data analysis" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3" data-preview-input="expertise"><p class="mt-2 text-xs text-slate-500">Separate topics with commas. Up to 30 topics.</p><?php $__errorArgs = ['expertise'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <div><label for="creator-website" class="block text-sm font-bold text-slate-800">Website or portfolio <span class="font-normal text-slate-400">(optional)</span></label><input id="creator-website" name="website" type="url" maxlength="500" inputmode="url" value="<?php echo e(old('website', $profile?->website)); ?>" placeholder="https://your-website.com" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"><?php $__errorArgs = ['website'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                    <div><label for="creator-orcid" class="block text-sm font-bold text-slate-800">ORCID iD <span class="font-normal text-slate-400">(optional)</span></label><input id="creator-orcid" name="orcid" maxlength="50" value="<?php echo e(old('orcid', $profile?->orcid)); ?>" placeholder="0000-0000-0000-0000" aria-describedby="creator-orcid-help" class="mt-2 w-full rounded-xl border-slate-300 px-4 py-3"><p id="creator-orcid-help" class="mt-2 text-xs text-slate-500">Enter your ORCID iD or its orcid.org URL. Save before importing your public record.</p><?php $__errorArgs = ['orcid'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="mt-2 text-xs text-rose-700"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
                </div>
            </section>
            <section id="creator-visibility" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
                <div class="flex gap-4"><span aria-hidden="true" class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-indigo-50 text-sm font-black text-indigo-700">03</span><div><h2 class="text-lg font-black text-slate-950">Choose what readers see</h2><p class="mt-1 text-sm text-slate-500">These choices apply to your creator page.</p></div></div>
                <div class="mt-6 space-y-3">
                    <?php $__currentLoopData = [
                        'is_public' => ['Public creator page', 'Allow other people to open your author page.', $public],
                        'show_institution' => ['Show institution', 'Display the institution connected to your account.', old('show_institution', data_get($profile?->privacy_settings, 'show_institution', false))],
                        'show_department' => ['Show department', 'Display your department when one is connected.', old('show_department', data_get($profile?->privacy_settings, 'show_department', false))],
                        'show_impact' => ['Show reputation & impact', 'Display your recorded reputation score and contribution level.', old('show_impact', data_get($profile?->privacy_settings, 'show_impact', $profile !== null))],
                    ]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $field => [$label, $description, $checked]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <label class="flex cursor-pointer items-start justify-between gap-4 rounded-2xl border border-slate-200 p-4"><span><strong class="block text-sm text-slate-800"><?php echo e($label); ?></strong><span class="mt-1 block text-xs leading-5 text-slate-500"><?php echo e($description); ?></span></span><input type="hidden" name="<?php echo e($field); ?>" value="0"><input type="checkbox" name="<?php echo e($field); ?>" value="1" <?php if($checked): echo 'checked'; endif; ?> class="mt-1 h-5 w-5 shrink-0 rounded border-slate-300 text-indigo-700" <?php if($field === 'is_public'): ?> data-preview-visibility <?php endif; ?>></label>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
                <p class="mt-4 text-xs leading-5 text-slate-500">Email, phone, account security and notification preferences are managed in <a class="font-semibold text-indigo-700" href="<?php echo e(route('profile.show')); ?>">My Profile</a>.</p>
            </section>
            <div class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-200 bg-white p-5"><p class="text-xs text-slate-500">Changes appear after you save.</p><div class="flex items-center gap-4"><a href="<?php echo e(route('profile.show')); ?>" class="text-sm font-semibold text-slate-600">Back to My Profile</a><button class="rounded-xl bg-indigo-700 px-5 py-3 text-sm font-bold text-white hover:bg-indigo-800">Save creator profile</button></div></div>
        </form>
        <aside class="min-w-0 space-y-5 xl:sticky xl:top-6">
            <section class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm" aria-label="Reader preview"><div class="bg-slate-950 px-5 py-4"><p class="text-xs font-bold uppercase tracking-wider text-indigo-200">Reader preview</p><p class="mt-1 text-xs text-slate-400">Unsaved changes · text preview</p></div><div class="p-6"><span data-preview-status class="rounded-full px-3 py-1 text-xs font-bold <?php echo e($public ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'); ?>"><?php echo e($public ? 'Public page' : 'Private page'); ?></span><h2 class="mt-5 text-xl font-black text-slate-950"><?php echo e($creator->full_name); ?></h2><p data-preview="headline" class="mt-2 break-words text-sm font-semibold text-indigo-700"><?php echo e(old('headline', $profile?->headline) ?: 'Your professional headline'); ?></p><p data-preview="biography" class="mt-4 whitespace-pre-line break-words text-sm leading-6 text-slate-500"><?php echo e(\Illuminate\Support\Str::limit(old('biography', $profile?->biography) ?: 'Tell readers about the work you do and the knowledge you share.', 240)); ?></p><div data-preview="expertise" class="mt-5 flex flex-wrap gap-2"><?php $__currentLoopData = array_slice(explode(',', old('expertise', implode(', ', $profile?->expertise ?? []))), 0, 6); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $topic): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(trim($topic)): ?><span class="max-w-full break-words rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700"><?php echo e(trim($topic)); ?></span><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div></div></section>
            <section class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"><h2 class="font-black text-slate-900">Import from ORCID</h2><p class="mt-2 text-sm leading-6 text-slate-500">Keep a sourced snapshot of your public ORCID record. Your saved biography and published work stay under your control.</p><?php if($profile?->orcid): ?><p class="mt-4 break-all font-mono text-sm text-slate-800"><?php echo e($profile->orcid); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e($profile->orcid_synced_at ? 'Last imported '.$profile->orcid_synced_at->diffForHumans() : 'No public record imported yet.'); ?></p><form method="POST" action="<?php echo e(route('knowledge.creator.orcid.sync')); ?>" class="mt-4"><?php echo csrf_field(); ?><button class="w-full rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-bold text-slate-700 hover:bg-slate-50">Import saved ORCID record</button></form><?php else: ?><p class="mt-4 rounded-xl bg-slate-50 p-3 text-xs text-slate-500">Add and save an ORCID iD to enable import.</p><?php endif; ?></section>
            <section class="rounded-3xl border border-indigo-100 bg-indigo-50 p-6"><h2 class="font-bold text-indigo-950">One account, one academic identity</h2><p class="mt-2 text-sm leading-6 text-indigo-800">Your name, photo and affiliation come from your existing account. Creator details are shared with your onboarding profile.</p><a href="<?php echo e(route('onboarding.manage')); ?>" class="mt-4 inline-block text-sm font-bold text-indigo-700">Manage academic profile →</a></section>
        </aside>
    </div>
    <section id="creator-verification" class="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-7">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><h2 class="text-xl font-black text-slate-950">Academic identity review</h2><p class="mt-2 max-w-2xl text-sm leading-6 text-slate-500">Ask an administrator to review your creator identity. Organisation membership and administrative access use the separate affiliation approval process.</p></div><span class="rounded-full bg-slate-100 px-3 py-1.5 text-xs font-bold text-slate-600"><?php echo e(ucwords(str_replace('_', ' ', $profile?->verification_status ?? 'not_verified'))); ?></span></div>
        <div class="mt-6 grid gap-7 lg:grid-cols-2">
            <div><?php if($pendingVerification): ?><div role="status" class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900"><p class="font-bold">Your request is awaiting review</p><p class="mt-2"><?php echo e(ucwords(str_replace('_', ' ', $pendingVerification->verification_type))); ?> · submitted <?php echo e($pendingVerification->created_at->diffForHumans()); ?></p><p class="mt-2 text-xs">Your saved creator profile remains editable while this request is reviewed.</p></div><?php else: ?><details <?php if($errors->has('verification_type') || $errors->has('statement')): ?> open <?php endif; ?>><summary class="cursor-pointer rounded-xl border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700">Request identity review</summary><form method="POST" action="<?php echo e(route('knowledge.verification.store')); ?>" class="mt-5 space-y-4"><?php echo csrf_field(); ?><label class="block text-sm font-bold text-slate-800">Academic identity<select name="verification_type" required class="mt-2 w-full rounded-xl border-slate-300"><?php $__currentLoopData = $verificationLabels; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($type); ?>" <?php if(old('verification_type') === $type): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label class="block text-sm font-bold text-slate-800">Supporting information<textarea name="statement" required minlength="20" maxlength="5000" rows="5" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Describe your role and provide links to evidence the reviewer can access."><?php echo e(old('statement')); ?></textarea></label><button class="rounded-xl bg-slate-900 px-4 py-3 text-sm font-bold text-white">Submit for review</button></form></details><?php endif; ?></div>
            <div><h3 class="text-sm font-bold text-slate-800">Recent requests</h3><div class="mt-3 space-y-3"><?php $__empty_0 = true; $__currentLoopData = $verificationRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $review): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><article class="rounded-xl bg-slate-50 p-4"><div class="flex flex-wrap items-center justify-between gap-2"><p class="text-sm font-bold text-slate-800"><?php echo e(ucwords(str_replace('_', ' ', $review->verification_type))); ?></p><span class="text-xs font-semibold text-slate-600"><?php echo e(ucfirst($review->status)); ?></span></div><p class="mt-1 text-xs text-slate-500"><?php echo e($review->created_at->format('j M Y')); ?></p><?php if($review->review_note): ?><p class="mt-2 whitespace-pre-line break-words text-sm text-slate-600"><?php echo e($review->review_note); ?></p><?php endif; ?></article><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="rounded-xl bg-slate-50 p-4 text-sm text-slate-500">No identity review requested. You can complete your profile and share work without one.</p><?php endif; ?></div></div>
        </div>
    </section>
</div>
<?php $__env->stopSection(); ?>
<?php $__env->startPush('scripts'); ?>
<script>
(() => {
    const root = document.querySelector('[data-creator-editor]');
    if (!root) return;
    const update = () => {
        const headline = root.querySelector('[name="headline"]').value.trim();
        const biography = root.querySelector('[name="biography"]').value.trim();
        root.querySelector('[data-preview="headline"]').textContent = headline || 'Your professional headline';
        root.querySelector('[data-preview="biography"]').textContent = biography ? biography.slice(0, 240) + (biography.length > 240 ? '…' : '') : 'Tell readers about the work you do and the knowledge you share.';
        root.querySelector('[data-biography-count]').textContent = root.querySelector('[name="biography"]').value.length;
        const topics = root.querySelector('[data-preview="expertise"]');
        topics.replaceChildren();
        [...new Set(root.querySelector('[name="expertise"]').value.split(',').map(value => value.trim()).filter(Boolean))].slice(0, 6).forEach(topic => {
            const tag = document.createElement('span');
            tag.className = 'max-w-full break-words rounded-lg bg-indigo-50 px-2.5 py-1 text-xs font-semibold text-indigo-700';
            tag.textContent = topic; topics.append(tag);
        });
        const visible = root.querySelector('[data-preview-visibility]').checked;
        const status = root.querySelector('[data-preview-status]');
        status.textContent = visible ? 'Public page' : 'Private page';
        status.className = 'rounded-full px-3 py-1 text-xs font-bold ' + (visible ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600');
    };
    root.querySelectorAll('[data-preview-input], [data-preview-visibility]').forEach(input => input.addEventListener('input', update));
})();
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/knowledge/creator-edit.blade.php ENDPATH**/ ?>