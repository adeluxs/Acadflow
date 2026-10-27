<?php $__env->startSection('title','Knowledge Hub administration'); ?>
<?php $__env->startSection('page-title','Knowledge Hub administration'); ?>
<?php $__env->startSection('page-subtitle','Publishing, storytelling, creator eligibility and runtime controls'); ?>
<?php $__env->startSection('content'); ?>
<?php ($areas=['academics_research'=>'Academics & Research','campus_student_life'=>'Campus & Student Life','stories_creativity'=>'Stories & Creativity','life_personal_growth'=>'Life & Personal Growth','skills_business_professional'=>'Skills, Business & Careers']); ?>
<div class="space-y-7">
<section class="rounded-3xl bg-gradient-to-br from-slate-950 via-indigo-950 to-blue-900 p-7 text-white"><p class="text-xs font-black uppercase tracking-[.2em] text-indigo-200">Learn · Share · Earn</p><h1 class="mt-2 text-3xl font-black">One publishing system, clear controls</h1><p class="mt-3 max-w-3xl text-sm leading-6 text-indigo-100">Academic credibility stays separate from popularity, paid access and promotion. Disabling paid publishing does not erase existing publications, purchases or ledger history.</p><a href="<?php echo e(route('admin.monetization')); ?>" class="mt-5 inline-flex rounded-xl bg-white px-4 py-2.5 text-sm font-black text-indigo-950">Commission, wallets and payment operations →</a></section>
<form method="POST" action="<?php echo e(route('admin.knowledge.settings')); ?>" class="rounded-3xl border bg-white p-6 shadow-sm"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?><h2 class="text-xl font-black">Runtime feature controls</h2><p class="mt-1 text-sm text-slate-500">These checks are enforced by controllers and services, not only hidden in the interface.</p><div class="mt-5 grid gap-3 md:grid-cols-2 xl:grid-cols-3"><?php $__currentLoopData = ['knowledge_free_publishing_enabled'=>'Free publishing','knowledge_research_content_enabled'=>'Research content','knowledge_stories_enabled'=>'Stories','knowledge_series_enabled'=>'Series','knowledge_paid_content_enabled'=>'Paid publications','knowledge_premium_chapters_enabled'=>'Premium chapters','knowledge_publisher_monetization_enabled'=>'Creator monetization applications','knowledge_promotions_enabled'=>'Paid promotions','knowledge_withdrawals_enabled'=>'Creator withdrawals','knowledge_comments_enabled'=>'Comments','knowledge_reactions_enabled'=>'Reactions','knowledge_follows_enabled'=>'Follows and chapter alerts','knowledge_recommendations_enabled'=>'Recommendations']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><label class="flex items-start gap-3 rounded-2xl border p-4"><input type="checkbox" name="<?php echo e($key); ?>" value="1" <?php if($settings[$key]): echo 'checked'; endif; ?> class="mt-1 rounded"><span><strong class="block text-sm text-slate-900"><?php echo e($label); ?></strong><span class="mt-1 block text-xs text-slate-500">Enabled platform-wide</span></span></label><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></div><div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="text-sm font-bold">Minimum paid publication price<input required type="number" step="0.01" min="0" name="knowledge_minimum_publication_price" value="<?php echo e($settings['knowledge_minimum_publication_price']); ?>" class="mt-2 w-full rounded-xl border-slate-300"></label><label class="text-sm font-bold">Minimum premium chapter price<input required type="number" step="0.01" min="0" name="knowledge_minimum_chapter_price" value="<?php echo e($settings['knowledge_minimum_chapter_price']); ?>" class="mt-2 w-full rounded-xl border-slate-300"></label></div><button class="mt-5 rounded-xl bg-indigo-700 px-5 py-3 text-sm font-black text-white">Save Knowledge Hub controls</button></form>

<section class="grid gap-6 xl:grid-cols-2">
    <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Publication types</h2>
        <p class="mt-1 text-sm text-slate-500">Formats are separate from subject categories.</p>
        <details class="mt-4 rounded-2xl border p-4">
            <summary class="cursor-pointer font-bold">Add publication type</summary>
            <form method="POST" action="<?php echo e(route('admin.knowledge.types.store')); ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php echo csrf_field(); ?>
                <?php echo $__env->make('admin.knowledge.partials.type-fields', ['type' => new \App\Models\KnowledgePublicationType], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <button class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white sm:col-span-2">Create type</button>
            </form>
        </details>
        <div class="mt-4 max-h-[42rem] space-y-3 overflow-y-auto pr-1">
            <?php $__currentLoopData = $types; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $type): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <details class="rounded-2xl border p-4">
                    <summary class="cursor-pointer"><span class="font-bold"><?php echo e($type->name); ?></span><span class="ml-2 text-xs text-slate-500"><?php echo e($areas[$type->area] ?? $type->area); ?> · <?php echo e($type->is_active ? 'Active' : 'Disabled'); ?></span></summary>
                    <form method="POST" action="<?php echo e(route('admin.knowledge.types.update', $type)); ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>
                        <?php echo $__env->make('admin.knowledge.partials.type-fields', ['type' => $type], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <button class="rounded-xl border px-4 py-2 text-sm font-bold sm:col-span-2">Save type</button>
                    </form>
                </details>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Subject categories</h2>
        <p class="mt-1 text-sm text-slate-500">Existing IDs and slugs remain stable when categories are disabled or reordered.</p>
        <details class="mt-4 rounded-2xl border p-4">
            <summary class="cursor-pointer font-bold">Add category</summary>
            <form method="POST" action="<?php echo e(route('admin.knowledge.categories.store')); ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                <?php echo csrf_field(); ?>
                <?php echo $__env->make('admin.knowledge.partials.category-fields', ['category' => new \App\Models\KnowledgeCategory], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                <button class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white sm:col-span-2">Create category</button>
            </form>
        </details>
        <div class="mt-4 space-y-3">
            <?php $__currentLoopData = $categories; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <details class="rounded-2xl border p-4">
                    <summary class="cursor-pointer"><span class="font-bold"><?php echo e($category->parent?->name ? $category->parent->name.' · ' : ''); ?><?php echo e($category->name); ?></span><span class="ml-2 text-xs text-slate-500"><?php echo e($category->publications_count); ?> publications · <?php echo e($category->is_active ? 'Active' : 'Disabled'); ?></span></summary>
                    <form method="POST" action="<?php echo e(route('admin.knowledge.categories.update', $category)); ?>" class="mt-4 grid gap-3 sm:grid-cols-2">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>
                        <?php echo $__env->make('admin.knowledge.partials.category-fields', ['category' => $category], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
                        <button class="rounded-xl border px-4 py-2 text-sm font-bold sm:col-span-2">Save category</button>
                    </form>
                </details>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
        <div class="mt-4"><?php echo e($categories->links()); ?></div>
    </div>
</section>

<section class="rounded-3xl border bg-white p-6 shadow-sm">
    <h2 class="text-xl font-black">Creator monetization applications</h2>
    <p class="mt-1 text-sm text-slate-500">Approval enables paid publishing; it does not bypass publication moderation.</p>
    <div class="mt-5 overflow-x-auto">
        <table class="min-w-full text-left text-sm">
            <thead class="text-xs uppercase text-slate-400"><tr><th class="p-3">Creator</th><th class="p-3">Application</th><th class="p-3">Payout</th><th class="p-3">Review</th></tr></thead>
            <tbody class="divide-y">
                <?php $__empty_0 = true; $__currentLoopData = $applications; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $application): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <tr>
                        <td class="p-3 align-top"><p class="font-bold"><?php echo e($application->user?->full_name); ?></p><p class="text-xs text-slate-500"><?php echo e($application->user?->email); ?></p></td>
                        <td class="max-w-lg p-3 align-top"><span class="rounded-full bg-slate-100 px-2 py-1 text-xs font-bold"><?php echo e(str($application->status)->headline()); ?></span><p class="mt-2 whitespace-pre-line text-slate-600"><?php echo e($application->statement); ?></p><p class="mt-2 text-xs text-slate-400">Ownership <?php echo e($application->ownership_declaration ? 'confirmed' : 'missing'); ?> · terms <?php echo e($application->terms_accepted ? 'accepted' : 'missing'); ?></p></td>
                        <td class="p-3 align-top text-xs">
                            <?php if($application->payoutAccount): ?>
                                <?php echo e($application->payoutAccount->bank_name ?: str($application->payoutAccount->provider)->headline()); ?> · <?php echo e($application->payoutAccount->currency); ?><br>
                                <?php echo e($application->payoutAccount->is_verified ? 'Verified' : 'Unverified'); ?>

                            <?php else: ?>
                                Add later
                            <?php endif; ?>
                        </td>
                        <td class="p-3 align-top">
                            <form method="POST" action="<?php echo e(route('admin.knowledge.applications.review', $application)); ?>" class="grid min-w-56 gap-2">
                                <?php echo csrf_field(); ?>
                                <?php echo method_field('PATCH'); ?>
                                <select name="decision" class="rounded-xl border-slate-300 text-sm"><option value="approve">Approve</option><option value="reject">Reject</option><option value="suspend">Suspend</option></select>
                                <textarea name="review_note" rows="2" maxlength="3000" class="rounded-xl border-slate-300 text-sm" placeholder="Decision reason"></textarea>
                                <button class="rounded-xl bg-slate-900 px-3 py-2 text-xs font-bold text-white">Record decision</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <tr><td colspan="4" class="p-8 text-center text-slate-500">No applications submitted.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    <div class="mt-4"><?php echo e($applications->links()); ?></div>
</section>

<section class="grid gap-6 xl:grid-cols-[.8fr_1.2fr]">
    <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Promotion packages</h2>
        <details class="mt-4 rounded-2xl border p-4">
            <summary class="cursor-pointer font-bold">Add package</summary>
            <form method="POST" action="<?php echo e(route('admin.knowledge.promotion-packages.store')); ?>" class="mt-4 grid gap-3">
                <?php echo csrf_field(); ?>
                <input name="name" required maxlength="120" class="rounded-xl border-slate-300" placeholder="Package name">
                <input name="slug" maxlength="120" class="rounded-xl border-slate-300" placeholder="Slug (optional)">
                <textarea name="description" maxlength="2000" class="rounded-xl border-slate-300" placeholder="Description"></textarea>
                <div class="grid grid-cols-3 gap-2"><input name="duration_days" type="number" min="1" max="365" required class="rounded-xl border-slate-300" placeholder="Days"><input name="price" type="number" min="0.01" step="0.01" required class="rounded-xl border-slate-300" placeholder="Price"><input name="sort_order" type="number" min="0" value="0" required class="rounded-xl border-slate-300" placeholder="Order"></div>
                <label class="text-sm"><input type="checkbox" name="is_active" value="1" checked class="rounded"> Active</label>
                <button class="rounded-xl bg-slate-950 px-4 py-2.5 text-sm font-bold text-white">Create package</button>
            </form>
        </details>
        <div class="mt-4 space-y-3">
            <?php $__currentLoopData = $promotionPackages; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $package): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <details class="rounded-2xl border p-4">
                    <summary class="cursor-pointer font-bold"><?php echo e($package->name); ?> · <?php echo e($package->duration_days); ?> days · <?php echo e($package->price); ?></summary>
                    <form method="POST" action="<?php echo e(route('admin.knowledge.promotion-packages.update', $package)); ?>" class="mt-4 grid gap-3">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PUT'); ?>
                        <input name="name" value="<?php echo e($package->name); ?>" required class="rounded-xl border-slate-300">
                        <input name="slug" value="<?php echo e($package->slug); ?>" required class="rounded-xl border-slate-300">
                        <textarea name="description" class="rounded-xl border-slate-300"><?php echo e($package->description); ?></textarea>
                        <div class="grid grid-cols-3 gap-2"><input name="duration_days" type="number" min="1" max="365" value="<?php echo e($package->duration_days); ?>" required class="rounded-xl border-slate-300"><input name="price" type="number" min="0.01" step="0.01" value="<?php echo e($package->price); ?>" required class="rounded-xl border-slate-300"><input name="sort_order" type="number" min="0" value="<?php echo e($package->sort_order); ?>" required class="rounded-xl border-slate-300"></div>
                        <label class="text-sm"><input type="checkbox" name="is_active" value="1" <?php if($package->is_active): echo 'checked'; endif; ?> class="rounded"> Active</label>
                        <button class="rounded-xl border px-4 py-2 text-sm font-bold">Save package</button>
                    </form>
                </details>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
        </div>
    </div>
    <div class="rounded-3xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-black">Promotion campaigns</h2>
        <div class="mt-4 space-y-3">
            <?php $__empty_0 = true; $__currentLoopData = $promotionCampaigns; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $campaign): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                <div class="rounded-2xl border p-4">
                    <div class="flex flex-wrap justify-between gap-3"><div><p class="font-bold"><?php echo e($campaign->publication?->title); ?></p><p class="text-xs text-slate-500"><?php echo e($campaign->creator?->full_name); ?> · <?php echo e($campaign->package?->name); ?></p></div><span class="text-xs font-bold"><?php echo e(str($campaign->status)->headline()); ?></span></div>
                    <p class="mt-3 text-xs text-slate-500"><?php echo e(number_format($campaign->impressions)); ?> impressions · <?php echo e(number_format($campaign->clicks)); ?> clicks · ends <?php echo e($campaign->ends_at?->format('M j, Y') ?: 'after payment'); ?></p>
                    <form method="POST" action="<?php echo e(route('admin.knowledge.promotions.review', $campaign)); ?>" class="mt-3 flex flex-wrap gap-2">
                        <?php echo csrf_field(); ?>
                        <?php echo method_field('PATCH'); ?>
                        <select name="decision" class="rounded-xl border-slate-300 text-sm"><option value="pause">Pause</option><option value="resume">Resume</option><option value="cancel">Cancel</option></select>
                        <input name="review_note" maxlength="3000" class="min-w-48 flex-1 rounded-xl border-slate-300 text-sm" placeholder="Administrative note">
                        <button class="rounded-xl bg-slate-900 px-4 py-2 text-xs font-bold text-white">Update</button>
                    </form>
                </div>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                <p class="rounded-2xl border border-dashed p-8 text-center text-sm text-slate-500">No campaigns yet.</p>
            <?php endif; ?>
        </div>
        <div class="mt-4"><?php echo e($promotionCampaigns->links()); ?></div>
    </div>
</section>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/knowledge/index.blade.php ENDPATH**/ ?>