<?php $__env->startSection('title','Monetization'); ?>
<?php $__env->startSection('content'); ?>
<div class="mx-auto max-w-7xl space-y-6 p-4 sm:p-6">
    <div class="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between"><div><p class="text-xs font-bold uppercase tracking-[.2em] text-indigo-600">Commercial control center</p><h1 class="text-3xl font-black text-slate-900">Monetization</h1><p class="mt-1 text-sm text-slate-500">Pay-as-you-use, wallet, marketplace earnings, AI billing and institutional commercial access in one place.</p></div></div>
    <?php if(session('success')): ?><div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm font-semibold text-emerald-800"><?php echo e(session('success')); ?></div><?php endif; ?>
    <?php if(isset($errors) && $errors->any()): ?><div class="rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-800"><ul class="list-disc pl-5"><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $e): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($e); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-7">
        <?php $__currentLoopData = [['Paid orders',$metrics['paid_orders']],['Gross volume',$displayCurrency.' '.number_format($metrics['gross_minor']/100,2)],['Platform revenue',$displayCurrency.' '.number_format($metrics['platform_revenue_minor']/100,2)],['Pending withdrawals',$metrics['pending_withdrawals']],['Feature revenue',$displayCurrency.' '.number_format($metrics['feature_revenue_minor']/100,2)],['AI revenue',$displayCurrency.' '.number_format($metrics['ai_revenue_minor']/100,2)],['AI margin',$displayCurrency.' '.number_format($metrics['ai_margin_minor']/100,2)]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as [$label,$value]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
        <div class="rounded-2xl border bg-white p-4 shadow-sm"><p class="text-xs font-bold uppercase text-slate-400"><?php echo e($label); ?></p><p class="mt-2 text-xl font-black text-slate-900"><?php echo e($value); ?></p></div>
        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
    </div>

    <form method="POST" action="<?php echo e(route('admin.monetization.update')); ?>" class="rounded-3xl border bg-white p-6 shadow-sm"><?php echo csrf_field(); ?> <?php echo method_field('PUT'); ?>
        <h2 class="text-xl font-black">Runtime pricing & wallet controls</h2><p class="mt-1 text-sm text-slate-500">These values are read directly by the domain services; they are not decorative settings.</p>
        <div class="mt-5 grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <label class="text-sm font-semibold">Minimum wallet funding (<?php echo e($displayCurrency); ?>)<input name="wallet_minimum_funding_amount" value="<?php echo e(old('wallet_minimum_funding_amount',$settings['wallet_minimum_funding_amount'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Minimum withdrawal (<?php echo e($displayCurrency); ?>)<input name="minimum_withdrawal_amount" value="<?php echo e(old('minimum_withdrawal_amount',$settings['minimum_withdrawal_amount'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Withdrawal fee (%)<input name="withdrawal_fee_percentage" value="<?php echo e(old('withdrawal_fee_percentage',$settings['withdrawal_fee_percentage'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Creator hold (days)<input type="number" name="creator_earnings_hold_days" value="<?php echo e(old('creator_earnings_hold_days',$settings['creator_earnings_hold_days'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Platform marketplace commission (%)<input name="knowledge_platform_commission_percentage" value="<?php echo e(old('knowledge_platform_commission_percentage',$settings['knowledge_platform_commission_percentage'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">Institution revenue share (%)<input name="knowledge_institution_revenue_percentage" value="<?php echo e(old('knowledge_institution_revenue_percentage',$settings['knowledge_institution_revenue_percentage'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold">General Free AI requests/day<input type="number" name="ai_free_daily_requests" value="<?php echo e(old('ai_free_daily_requests',$settings['ai_free_daily_requests'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"><span class="mt-1 block text-xs font-normal leading-5 text-slate-500">Every registered user receives this daily allowance unless an assistant has its own published free limit.</span></label>
            <label class="text-sm font-semibold">AI charge/request (<?php echo e($displayCurrency); ?>)<input name="ai_request_charge_amount" value="<?php echo e(old('ai_request_charge_amount',$settings['ai_request_charge_amount'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <label class="text-sm font-semibold"><?php echo e($displayCurrency); ?>/USD reporting rate<input name="ai_local_currency_per_usd_reporting" value="<?php echo e(old('ai_local_currency_per_usd_reporting',$settings['ai_local_currency_per_usd_reporting'])); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
            <input type="hidden" name="general_free_plan_enabled" value="0"><label class="flex items-start gap-3 rounded-xl border p-3"><input type="checkbox" name="general_free_plan_enabled" value="1" class="mt-1" <?php if(old('general_free_plan_enabled',$settings['general_free_plan_enabled'])): echo 'checked'; endif; ?>><span><span class="block text-sm font-bold text-slate-800">Enable General Free plan</span><span class="mt-1 block text-xs leading-5 text-slate-500">Automatically places every registered account on the free plan with the daily AI allowance above. Disabling it removes free AI requests but does not remove core account access.</span></span></label>
            <input type="hidden" name="ai_monetization_enabled" value="0"><label class="flex items-start gap-3 rounded-xl border p-3"><input type="checkbox" name="ai_monetization_enabled" value="1" class="mt-1" <?php if(old('ai_monetization_enabled',$settings['ai_monetization_enabled'])): echo 'checked'; endif; ?>><span><span class="block text-sm font-bold text-slate-800">Charge users for extra AI after all allowances are used</span><span class="mt-1 block text-xs leading-5 text-slate-500">A Study Pro, Teaching Pro or Academic AI Plus payment includes the AI requests configured for that package. When enabled, AcadSyntra uses the General Free allowance first, then the matching package allowance, and charges the user’s wallet only for successful requests after both are exhausted. When disabled, users are not charged AI overage.</span></span></label>
        </div><button class="mt-5 rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-bold text-white">Save monetization settings</button>
    </form>

    <?php if(auth()->user()->isSuperAdmin()): ?>
    <div class="grid gap-6 xl:grid-cols-2">
        <form method="POST" action="<?php echo e(route('admin.monetization.pricing-rules.store')); ?>" class="rounded-3xl border bg-white p-6 shadow-sm" id="pricing-rule-form">
            <?php echo csrf_field(); ?>
            <div class="flex items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-bold uppercase tracking-[.16em] text-indigo-600">Paid feature access</p>
                    <h2 class="mt-1 text-xl font-black text-slate-900">Add or update a feature price</h2>
                    <p class="mt-1 max-w-xl text-sm leading-6 text-slate-500">Choose a real AcadSyntra feature and enter its price. Technical rule keys and names are created automatically.</p>
                </div>
                <span class="hidden rounded-full bg-indigo-50 px-3 py-1 text-xs font-bold text-indigo-700 sm:inline-flex"><?php echo e($displayCurrency); ?></span>
            </div>

            <div class="mt-5 grid gap-5">
                <label class="block">
                    <span class="text-sm font-bold text-slate-800">Feature to charge for</span>
                    <span class="mt-1 block text-xs text-slate-500">Only features that are connected to AcadSyntra's entitlement checks appear here.</span>
                    <select name="target" id="pricing-feature-select" class="mt-2 w-full rounded-xl border-slate-300 bg-white px-3 py-2.5 text-sm focus:border-indigo-500 focus:ring-indigo-500" required>
                        <option value="">Select a feature or AI assistant…</option>
                        <?php $__currentLoopData = $pricingFeatureGroups; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $category => $features): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <optgroup label="<?php echo e($category); ?>">
                                <?php $__currentLoopData = $features; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                    <option value="<?php echo e($feature['target']); ?>" data-pricing-type="<?php echo e($feature['pricing_type'] ?? 'fixed_access'); ?>" data-package="<?php echo e(!empty($feature['package']) ? '1' : '0'); ?>" data-description="<?php echo e($feature['description'] ?? (($feature['pricing_type'] ?? null)==='ai_usage' ? 'Provider-backed AI assistant with assistant-specific pricing and free allowance.' : '')); ?>" <?php if(old('target') === $feature['target']): echo 'selected'; endif; ?>><?php echo e($feature['title']); ?></option>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                            </optgroup>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    </select>
                    <?php if(isset($errors) && $errors->has('target')): ?><span class="mt-2 block text-xs font-semibold text-red-600"><?php echo e($errors->first('target')); ?></span><?php endif; ?>
                    <span id="pricing-feature-help" class="mt-2 block rounded-xl bg-slate-50 px-3 py-2 text-xs leading-5 text-slate-600">Select a feature to see what this pricing rule controls.</span>
                </label>

                <label class="block">
                    <span class="text-sm font-bold text-slate-800">Price</span>
                    <span class="mt-1 block text-xs text-slate-500">For fixed access this is the activation price; for pay-per-use it is the unit price; for AI it is the successful-request price after the free allowance.</span>
                    <div class="mt-2 flex overflow-hidden rounded-xl border border-slate-300 bg-white focus-within:border-indigo-500 focus-within:ring-1 focus-within:ring-indigo-500">
                        <span class="flex items-center border-r bg-slate-50 px-3 text-sm font-black text-slate-600"><?php echo e($displayCurrency); ?></span>
                        <input name="amount" value="<?php echo e(old('amount')); ?>" inputmode="decimal" autocomplete="off" placeholder="e.g. 500.00" class="min-w-0 flex-1 border-0 px-3 py-2.5 text-sm focus:ring-0" required>
                    </div>
                    <?php if(isset($errors) && $errors->has('amount')): ?><span class="mt-2 block text-xs font-semibold text-red-600"><?php echo e($errors->first('amount')); ?></span><?php endif; ?>
                </label>

                <div id="fixed-access-fields" class="hidden rounded-2xl border border-slate-200 p-4">
                    <label class="block"><span class="text-sm font-bold text-slate-800">Monthly access duration (days)</span><span class="mt-1 block text-xs text-slate-500">The main price above is the monthly package price. Use 0 for non-expiring access on ordinary fixed features.</span><input type="number" min="0" max="3650" name="duration_days" value="<?php echo e(old('duration_days',30)); ?>" class="mt-2 w-full rounded-xl border-slate-300"></label>
                </div>
                <div id="package-fields" class="hidden rounded-2xl border border-indigo-200 bg-indigo-50/40 p-4">
                    <p class="text-sm font-black text-indigo-950">Simple package options</p><p class="mt-1 text-xs leading-5 text-indigo-800">The monthly or semester price pays for the selected workspace package or AI package and includes the AI requests you enter below. Study Pro and Teaching Pro cover their role tools; Academic AI Plus is an optional specialist allowance available to students, lecturers and other members. Extra AI charges apply only after daily free and matching package allowances are exhausted and the setting above is enabled.</p>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <label class="text-sm font-semibold">Monthly included AI requests<input type="number" min="0" max="100000" name="included_ai_requests" value="<?php echo e(old('included_ai_requests',0)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm font-semibold">Semester price (<?php echo e($displayCurrency); ?>)<input inputmode="decimal" name="semester_amount" value="<?php echo e(old('semester_amount')); ?>" placeholder="Leave blank to hide" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm font-semibold">Semester duration (days)<input type="number" min="1" max="3650" name="semester_duration_days" value="<?php echo e(old('semester_duration_days',120)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm font-semibold">Semester included AI requests<input type="number" min="0" max="100000" name="semester_included_ai_requests" value="<?php echo e(old('semester_included_ai_requests',0)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm font-semibold">Trial days<input type="number" min="0" max="90" name="trial_days" value="<?php echo e(old('trial_days',0)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm font-semibold">Trial included AI requests<input type="number" min="0" max="10000" name="trial_included_ai_requests" value="<?php echo e(old('trial_included_ai_requests',0)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
                        <label class="text-sm font-semibold">Read-only grace days<input type="number" min="0" max="30" name="grace_days" value="<?php echo e(old('grace_days',7)); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label>
                    </div>
                </div>
                <div id="ai-usage-fields" class="hidden rounded-2xl border border-violet-200 bg-violet-50/50 p-4">
                    <label class="block"><span class="text-sm font-bold text-slate-800">Free successful requests/day</span><span class="mt-1 block text-xs text-slate-500">This overrides the global AI free allowance for this assistant only.</span><input type="number" min="0" max="10000" name="free_daily_requests" value="<?php echo e(old('free_daily_requests',0)); ?>" class="mt-2 w-full rounded-xl border-slate-300"></label>
                </div>

                <div class="rounded-2xl border border-blue-100 bg-blue-50 p-4 text-xs leading-5 text-blue-900">
                    <p class="font-black">How this works</p>
                    <p class="mt-1">AcadSyntra derives the safe pricing mode from the selected runtime target: fixed access creates an entitlement, pay-per-use reserves and settles around the operation, and AI usage applies a per-assistant free allowance/request price. Publishing creates a new immutable rule version and preserves older versions for audit.</p>
                </div>

                <label class="flex items-start gap-3 rounded-2xl border border-slate-200 p-4">
                    <input type="checkbox" name="enabled" value="1" class="mt-0.5 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500" <?php if(old('target') ? old('enabled') : true): echo 'checked'; endif; ?>>
                    <span>
                        <span class="block text-sm font-bold text-slate-800">Activate this price immediately</span>
                        <span class="mt-1 block text-xs leading-5 text-slate-500">When enabled, runtime billing immediately uses this rule. Leave it off if you are preparing the price but do not want it active yet.</span>
                    </span>
                </label>

                <button class="rounded-xl bg-slate-900 px-4 py-2.5 text-sm font-bold text-white transition hover:bg-slate-800 focus:outline-none focus:ring-2 focus:ring-slate-400 focus:ring-offset-2">Publish feature price</button>
            </div>
        </form>
        <section id="commercial-account-setup" class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">B2B / B2B2C onboarding</p>
                    <h2 class="mt-1 text-xl font-black text-slate-900"><?php echo e($setupAccount ? 'Manage institution commercial account' : 'Create institution commercial account'); ?></h2>
                    <p class="mt-1 max-w-2xl text-sm leading-6 text-slate-500">Choose the institution, confirm its commercial terms, and activate what the institution will sponsor. Institution admins can view these terms but cannot change them directly.</p>
                </div>
                <?php if($setupRequest): ?>
                    <span class="rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-black text-amber-700">Reviewing request: <?php echo e($setupRequest->label()); ?></span>
                <?php endif; ?>
            </div>

            <?php if($setupRequest): ?>
                <div class="mt-5 rounded-2xl border border-blue-100 bg-blue-50 p-4 text-sm text-blue-900">
                    <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                        <div>
                            <p class="font-black">Request from <?php echo e($setupRequest->university?->name); ?></p>
                            <?php if($setupRequest->requested_amount_minor): ?><p class="mt-1">Requested additional budget: <strong><?php echo e($setupRequest->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$setupRequest->requested_amount_minor)); ?></strong></p><?php endif; ?>
                            <p class="mt-1 text-blue-800"><?php echo e($setupRequest->message ?: 'No additional message was provided.'); ?></p>
                        </div>
                        <a href="<?php echo e(route('admin.monetization')); ?>#commercial-account-setup" class="text-xs font-black text-blue-700 underline">Clear request</a>
                    </div>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?php echo e(route('admin.monetization.commercial-accounts.store')); ?>" class="mt-5 space-y-6">
                <?php echo csrf_field(); ?>
                <?php if($setupDepartmentId>0): ?><input type="hidden" name="department_id" value="<?php echo e($setupDepartmentId); ?>"><p class="rounded-xl bg-indigo-50 p-3 text-sm font-bold">Department account: <?php echo e($setupRequest?->department?->name ?? $setupAccount?->department?->name); ?>. Funds remain separate from the institution account.</p><?php endif; ?>
                <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-semibold">Adoption plan<select name="plan_key" class="mt-1 w-full rounded-xl border-slate-300"><?php $__currentLoopData = config('academic_adoption.plans'); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key=>$plan): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if($plan['scope']===($setupDepartmentId?'department':'institution')): ?><option value="<?php echo e($key); ?>" <?php if(old('plan_key',$setupAccount?->plan_key??data_get($setupRequest?->requested_changes,'plan_key'))===$key): echo 'selected'; endif; ?>><?php echo e($plan['name']); ?></option><?php endif; ?> <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label><label class="text-sm font-semibold">Access end date (optional)<input type="date" name="access_ends_at" value="<?php echo e(old('access_ends_at',$setupAccount?->access_ends_at?->format('Y-m-d'))); ?>" class="mt-1 w-full rounded-xl border-slate-300"></label></div>
                <?php if($setupRequest): ?><input type="hidden" name="request_id" value="<?php echo e($setupRequest->id); ?>"><?php endif; ?>

                <div class="rounded-2xl border p-5">
                    <div class="flex items-center gap-3"><span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-black text-white">1</span><div><h3 class="font-black text-slate-900">Institution & account status</h3><p class="text-xs text-slate-500">The institution currency comes from its own settings and cannot be forged from the browser.</p></div></div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <div class="text-sm font-bold text-slate-800">Institution
                            <?php if($setupUniversityId>0): ?>
                                <input type="hidden" name="university_id" value="<?php echo e($setupUniversityId); ?>">
                                <div class="mt-2 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2.5 text-sm font-semibold text-slate-800"><?php echo e($setupRequest?->university?->name ?? $setupAccount?->university?->name ?? 'Selected institution'); ?></div>
                                <span class="mt-1 block text-xs font-normal text-slate-500">Institution is locked while reviewing this account/request, preventing accidental cross-institution updates.</span>
                            <?php else: ?>
                                <?php if (isset($component)) { $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.admin-lookup','data' => ['name' => 'university_id','kind' => 'commercial_universities','label' => 'Choose institution','selected' => $selectedUniversity,'required' => true]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('admin-lookup'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['name' => 'university_id','kind' => 'commercial_universities','label' => 'Choose institution','selected' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($selectedUniversity),'required' => true]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $attributes = $__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__attributesOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f)): ?>
<?php $component = $__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f; ?>
<?php unset($__componentOriginal0fbb3d626b5fd703a6ff609bf62a422f); ?>
<?php endif; ?>
                                <span class="mt-1 block text-xs font-normal text-slate-500">Existing accounts are managed from the account list below so their current balance and terms are always prefilled safely.</span>
                            <?php endif; ?>
                        </div>
                        <label class="text-sm font-bold text-slate-800">Account label <span class="font-normal text-slate-400">(optional)</span>
                            <input name="name" value="<?php echo e(old('name',$setupAccount?->name)); ?>" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Automatically uses institution name if left blank">
                        </label>
                        <label class="text-sm font-bold text-slate-800">Commercial account status
                            <select name="status" class="mt-2 w-full rounded-xl border-slate-300"><option value="active" <?php if(old('status',$setupAccount?->status ?? 'active')==='active'): echo 'selected'; endif; ?>>Active — institution sponsorship can run</option><option value="paused" <?php if(old('status',$setupAccount?->status)==='paused'): echo 'selected'; endif; ?>>Paused — temporarily stop institution sponsorship</option></select>
                        </label>
                        <div class="rounded-xl border bg-slate-50 p-4"><p class="text-xs font-black uppercase text-slate-400">Commercial currency</p><p class="mt-2 text-lg font-black text-slate-900"><?php echo e($setupCurrency); ?></p><p class="mt-1 text-xs text-slate-500">Managed from the institution currency setting.</p></div>
                    </div>
                </div>

                <div class="rounded-2xl border p-5">
                    <div class="flex items-center gap-3"><span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-black text-white">2</span><div><h3 class="font-black text-slate-900">Prepaid budget & limits</h3><p class="text-xs text-slate-500">Set the institution’s available commercial budget and any negotiated operating limits.</p></div></div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        <label class="text-sm font-bold text-slate-800">Available prepaid budget after this update (<?php echo e($setupCurrency); ?>)
                            <input name="prepaid_balance" value="<?php echo e(old('prepaid_balance',\App\Support\Money::fromMinor($suggestedTargetMinor))); ?>" inputmode="decimal" class="mt-2 w-full rounded-xl border-slate-300" required>
                            <span class="mt-1 block text-xs font-normal text-slate-500">Current: <?php echo e($setupCurrency); ?> <?php echo e(\App\Support\Money::fromMinor((int)($setupAccount?->prepaid_balance_minor ?? 0))); ?><?php if($requestedAddMinor>0): ?> · Suggested after approved funding request: <?php echo e($setupCurrency); ?> <?php echo e(\App\Support\Money::fromMinor($suggestedTargetMinor)); ?><?php endif; ?></span>
                        </label>
                        <label class="text-sm font-bold text-slate-800">Maximum department administrators per department
                            <input type="number" min="1" max="10000" name="max_administrators" value="<?php echo e(old('max_administrators',data_get($setupChanges,'max_administrators',data_get($setupMetadata,'max_administrators')))); ?>" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Leave blank for no commercial cap">
                            <span class="mt-1 block text-xs font-normal text-slate-500">Only use this when the commercial agreement limits department-admin accounts within each department.</span>
                        </label>
                        <label class="text-sm font-bold text-slate-800">Student semester fee (<?php echo e($setupCurrency); ?>) <span class="font-normal text-slate-400">(optional)</span>
                            <input name="student_semester_fee" value="<?php echo e(old('student_semester_fee', data_get($setupChanges,'student_semester_fee_minor')!==null ? \App\Support\Money::fromMinor((int)data_get($setupChanges,'student_semester_fee_minor')) : (data_get($setupMetadata,'student_semester_fee_minor')!==null ? \App\Support\Money::fromMinor((int)data_get($setupMetadata,'student_semester_fee_minor')) : ''))); ?>" class="mt-2 w-full rounded-xl border-slate-300" placeholder="Example: 2500.00">
                            <span class="mt-1 block text-xs font-normal text-slate-500">Leave empty if AcadSyntra should not generate institution semester invoices from this commercial account.</span>
                        </label>
                        <label class="text-sm font-bold text-slate-800">Invoice grace period
                            <input type="number" min="0" max="90" name="invoice_grace_days" value="<?php echo e(old('invoice_grace_days',data_get($setupChanges,'invoice_grace_days',data_get($setupMetadata,'invoice_grace_days',7)))); ?>" class="mt-2 w-full rounded-xl border-slate-300">
                            <span class="mt-1 block text-xs font-normal text-slate-500">Number of days after an invoice due date before the existing billing policy treats it as overdue.</span>
                        </label>
                    </div>
                </div>

                <div class="rounded-2xl border p-5">
                    <div class="flex items-center gap-3"><span class="inline-flex h-8 w-8 items-center justify-center rounded-full bg-indigo-600 text-xs font-black text-white">3</span><div><h3 class="font-black text-slate-900">What will the institution sponsor?</h3><p class="text-xs text-slate-500">Sponsored access lets institution members use selected paid features without purchasing individual access.</p></div></div>
                    <div class="mt-4 space-y-3">
                        <label class="flex items-start gap-3 rounded-xl border border-indigo-100 bg-indigo-50/60 p-4">
                            <input type="checkbox" name="sponsor_ai_usage" value="1" class="mt-1 rounded border-slate-300 text-indigo-600" <?php if(old('sponsor_ai_usage', data_get($setupRequest?->request_type==='sponsorship' ? $setupChanges : $setupMetadata,'sponsor_ai_usage',false))): echo 'checked'; endif; ?>>
                            <span><span class="block text-sm font-black text-slate-900">Sponsor eligible AI usage</span><span class="mt-1 block text-xs leading-5 text-slate-600">Paid AI requests can use the institution’s prepaid commercial balance instead of the member’s personal wallet.</span></span>
                        </label>
                        <div class="grid gap-3 md:grid-cols-2">
                            <?php $__currentLoopData = $pricingFeatures; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $feature): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <label class="flex items-start gap-3 rounded-xl border p-4">
                                    <input type="checkbox" name="sponsored_features[]" value="<?php echo e($feature['key']); ?>" class="mt-1 rounded border-slate-300 text-indigo-600" <?php if(in_array($feature['key'],(array)old('sponsored_features',$selectedSponsoredFeatures),true)): echo 'checked'; endif; ?>>
                                    <span><span class="block text-sm font-black text-slate-900"><?php echo e($feature['title']); ?></span><span class="mt-1 block text-xs leading-5 text-slate-500"><?php echo e($feature['description']); ?></span></span>
                                </label>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                </div>

                <div class="flex flex-col gap-3 rounded-2xl border border-emerald-100 bg-emerald-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div><p class="text-sm font-black text-emerald-900">Review before applying</p><p class="mt-1 text-xs leading-5 text-emerald-800">Saving applies these approved terms immediately. Any prepaid-balance change is journaled to the audit ledger. If you opened this from an institution request, that request is marked approved.</p></div>
                    <button class="shrink-0 rounded-xl bg-slate-900 px-5 py-3 text-sm font-black text-white"><?php echo e($setupRequest ? 'Approve request & apply terms' : 'Save commercial account'); ?></button>
                </div>
            </form>
        </section>
    </div>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_.9fr]">
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div><p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">Institution requests</p><h2 class="mt-1 text-xl font-black text-slate-900">Commercial requests awaiting review</h2><p class="mt-1 text-sm text-slate-500">Institution admins can ask for setup, more prepaid budget, term changes or sponsored access. Nothing changes financially until Platform Admin applies it.</p></div>
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-black text-amber-700"><?php echo e($pendingCommercialRequests->count()); ?></span>
            </div>
            <div class="mt-5 space-y-4">
                <?php $__empty_0 = true; $__currentLoopData = $pendingCommercialRequests; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $commercialRequest): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <article class="rounded-2xl border p-4">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="text-sm font-black text-slate-900"><?php echo e($commercialRequest->university?->name); ?> <?php if($commercialRequest->department): ?>· <?php echo e($commercialRequest->department->name); ?><?php endif; ?> · <?php echo e($commercialRequest->label()); ?></p>
                                <p class="mt-1 text-xs text-slate-500">Requested by <?php echo e($commercialRequest->requester?->full_name); ?> · <?php echo e($commercialRequest->created_at?->diffForHumans()); ?></p>
                                <?php if($commercialRequest->requested_amount_minor): ?><p class="mt-2 text-sm font-black text-indigo-700">Requested budget: <?php echo e($commercialRequest->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$commercialRequest->requested_amount_minor)); ?></p><?php endif; ?>
                                <p class="mt-2 text-sm leading-6 text-slate-600"><?php echo e($commercialRequest->message ?: 'No additional message.'); ?></p>
                            </div>
                            <span class="shrink-0 rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-black text-amber-700">Awaiting review</span>
                        </div>
                        <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                            <a href="<?php echo e(route('admin.monetization',['commercial_request'=>$commercialRequest->id])); ?>#commercial-account-setup" class="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-4 py-2.5 text-xs font-black text-white">Review & apply terms</a>
                            <form method="POST" action="<?php echo e(route('admin.monetization.commercial-requests.reject',$commercialRequest)); ?>" class="flex min-w-0 flex-1 gap-2"><?php echo csrf_field(); ?>
                                <input name="review_note" class="min-w-0 flex-1 rounded-xl border-slate-300 text-xs" placeholder="Reason for rejection (shown to institution)" required>
                                <button class="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs font-black text-red-700">Reject</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <div class="rounded-2xl border border-dashed p-6 text-center"><p class="font-bold text-slate-700">No institution requests are waiting.</p><p class="mt-1 text-xs text-slate-500">New requests will appear here and can be applied through the guided setup form.</p></div>
                <?php endif; ?>
            </div>
        </section>

        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div><p class="text-xs font-black uppercase tracking-[.16em] text-indigo-600">Existing B2B accounts</p><h2 class="mt-1 text-xl font-black text-slate-900">Institution commercial accounts</h2><p class="mt-1 text-sm text-slate-500">Open an account to update negotiated terms. Institution admins see the approved values on their own Commercial Account dashboard.</p></div>
            <form method="GET" class="mt-4 flex gap-2"><label class="flex-1 text-sm">Account or institution<input type="search" name="account_q" maxlength="100" value="<?php echo e(request('account_q')); ?>" class="mt-1 w-full rounded-lg border-slate-300"></label><button class="self-end rounded-lg border px-3 py-2 text-sm">Search</button></form>
            <div class="mt-5 space-y-3">
                <?php $__empty_0 = true; $__currentLoopData = $commercialAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $commercialAccount): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <a href="<?php echo e(route('admin.monetization',['institution'=>$commercialAccount->university_id,'department'=>$commercialAccount->department_id])); ?>#commercial-account-setup" class="block rounded-2xl border p-4 transition hover:border-indigo-200 hover:bg-indigo-50/30">
                        <div class="flex items-start justify-between gap-3"><div><p class="font-black text-slate-900"><?php echo e($commercialAccount->university?->name ?? $commercialAccount->name); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e($commercialAccount->name); ?> <?php if($commercialAccount->department): ?>· <?php echo e($commercialAccount->department->name); ?><?php endif; ?> · <?php echo e($commercialAccount->currency); ?></p></div><span class="rounded-full px-2.5 py-1 text-[11px] font-black <?php echo e($commercialAccount->status==='active'?'bg-emerald-50 text-emerald-700':'bg-slate-100 text-slate-600'); ?>"><?php echo e(ucfirst($commercialAccount->status)); ?></span></div>
                        <div class="mt-3 flex flex-wrap gap-3 text-xs text-slate-600"><span>Prepaid: <strong class="text-slate-900"><?php echo e($commercialAccount->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$commercialAccount->prepaid_balance_minor)); ?></strong></span><span>AI sponsored: <strong class="text-slate-900"><?php echo e(data_get($commercialAccount->metadata,'sponsor_ai_usage',false)?'Yes':'No'); ?></strong></span><span>Sponsored features: <strong class="text-slate-900"><?php echo e(count((array)data_get($commercialAccount->metadata,'sponsored_features',[]))); ?></strong></span></div>
                    </a>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <div class="rounded-2xl border border-dashed p-6 text-center"><p class="font-bold text-slate-700">No commercial accounts yet.</p><p class="mt-1 text-xs text-slate-500">Use the setup form above or approve an institution setup request.</p></div>
                <?php endif; ?>
                <?php echo e($commercialAccounts->fragment('commercial-account-setup')->links()); ?>

            </div>
        </section>
    </div>
    <?php endif; ?>

    <div class="grid gap-6 xl:grid-cols-3">
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-black">Pending withdrawals</h2><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700"><?php echo e($pendingWithdrawals->count()); ?></span></div>
            <div class="mt-4 space-y-4"><?php $__empty_0 = true; $__currentLoopData = $pendingWithdrawals; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $withdrawal): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="rounded-2xl border p-4 text-sm"><p class="font-black"><?php echo e($withdrawal->wallet?->user?->full_name); ?> · <?php echo e($withdrawal->wallet?->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$withdrawal->amount_minor)); ?></p><p class="mt-1 text-xs text-slate-500"><?php echo e($withdrawal->payoutAccount?->bank_name); ?> · <?php echo e($withdrawal->payoutAccount?->masked_account_number); ?> · fee <?php echo e(\App\Support\Money::fromMinor((int)$withdrawal->fee_minor)); ?></p><form method="POST" action="<?php echo e(route('commerce.withdrawals.process',$withdrawal)); ?>" class="mt-3 grid gap-2"><?php echo csrf_field(); ?><input name="provider_reference" class="rounded-lg border-slate-300 text-xs" placeholder="Provider/bank reference for approval"><input name="note" class="rounded-lg border-slate-300 text-xs" placeholder="Decision note"><div class="flex gap-2"><button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Approve paid</button><button name="decision" value="reject" class="rounded-lg border px-3 py-2 text-xs font-bold">Reject & release hold</button></div></form></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="text-sm text-slate-500">No withdrawal requests awaiting review.</p><?php endif; ?></div>
        </section>
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-black">Refund operations</h2><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700"><?php echo e($pendingRefunds->count()); ?></span></div>
            <div class="mt-4 space-y-4">
                <?php $__empty_0 = true; $__currentLoopData = $pendingRefunds; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $refund): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                    <div class="rounded-2xl border p-4 text-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-black"><?php echo e($refund->order?->order_number); ?> · <?php echo e($refund->order?->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$refund->amount_minor)); ?></p>
                                <p class="mt-1 text-xs text-slate-500"><?php echo e($refund->requester?->full_name); ?></p>
                            </div>
                            <span class="rounded-full px-2 py-1 text-[11px] font-bold <?php echo e($refund->reconciliation_required ? 'bg-red-50 text-red-700' : ($refund->status === 'processing' ? 'bg-blue-50 text-blue-700' : 'bg-amber-50 text-amber-700')); ?>">
                                <?php echo e($refund->reconciliation_required ? 'Reconciliation required' : ucfirst($refund->status)); ?>

                            </span>
                        </div>
                        <p class="mt-2 text-slate-600"><?php echo e($refund->reason); ?></p>

                        <?php if($refund->reconciliation_required): ?>
                            <div class="mt-3 rounded-xl border border-red-200 bg-red-50 p-3 text-xs text-red-800">
                                The provider outcome was uncertain. Verify this refund in the payment-provider dashboard before choosing an outcome. AcadSyntra will not send a second provider refund automatically.
                            </div>
                            <form method="POST" action="<?php echo e(route('commerce.refunds.reconcile',$refund)); ?>" class="mt-3 grid gap-2"><?php echo csrf_field(); ?>
                                <input name="provider_reference" class="rounded-lg border-slate-300 text-xs" placeholder="Provider refund reference (required if confirmed)">
                                <input name="note" class="rounded-lg border-slate-300 text-xs" placeholder="Required reconciliation evidence/note" required>
                                <div class="flex flex-wrap gap-2">
                                    <button name="outcome" value="confirmed" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Confirmed at provider</button>
                                    <button name="outcome" value="not_refunded" class="rounded-lg border px-3 py-2 text-xs font-bold">Provider shows no refund</button>
                                </div>
                            </form>
                        <?php elseif($refund->status === 'processing'): ?>
                            <form method="POST" action="<?php echo e(route('commerce.refunds.process',$refund)); ?>" class="mt-3 grid gap-2"><?php echo csrf_field(); ?>
                                <input type="hidden" name="decision" value="approve">
                                <input name="note" class="rounded-lg border-slate-300 text-xs" placeholder="Optional finalization note">
                                <button class="rounded-lg bg-blue-700 px-3 py-2 text-xs font-bold text-white">Resume safe finalization</button>
                            </form>
                        <?php else: ?>
                            <form method="POST" action="<?php echo e(route('commerce.refunds.process',$refund)); ?>" class="mt-3 grid gap-2"><?php echo csrf_field(); ?>
                                <input name="note" class="rounded-lg border-slate-300 text-xs" placeholder="Decision note">
                                <div class="flex gap-2"><button name="decision" value="approve" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Approve</button><button name="decision" value="reject" class="rounded-lg border px-3 py-2 text-xs font-bold">Reject</button></div>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                    <p class="text-sm text-slate-500">No refund requests awaiting action.</p>
                <?php endif; ?>
            </div>
        </section>
        <section class="rounded-3xl border bg-white p-6 shadow-sm">
            <div class="flex items-center justify-between"><h2 class="text-lg font-black">Payout verification</h2><span class="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-bold text-amber-700"><?php echo e($pendingPayoutAccounts->count()); ?></span></div>
            <div class="mt-4 space-y-4"><?php $__empty_0 = true; $__currentLoopData = $pendingPayoutAccounts; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $account): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?><div class="rounded-2xl border p-4 text-sm"><p class="font-black"><?php echo e($account->user?->full_name); ?></p><p class="mt-1 text-slate-600"><?php echo e($account->account_name); ?> · <?php echo e($account->bank_name); ?> · <?php echo e($account->masked_account_number); ?></p><form method="POST" action="<?php echo e(route('commerce.payout-accounts.verify',$account)); ?>" class="mt-3 flex gap-2"><?php echo csrf_field(); ?><button name="verified" value="1" class="rounded-lg bg-emerald-700 px-3 py-2 text-xs font-bold text-white">Mark verified</button><button name="verified" value="0" class="rounded-lg border px-3 py-2 text-xs font-bold">Keep unverified</button></form></div><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?><p class="text-sm text-slate-500">No payout accounts awaiting verification.</p><?php endif; ?></div>
        </section>
    </div>

    <section class="rounded-3xl border bg-white p-6 shadow-sm">
        <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
            <div><h2 class="text-lg font-black">Feature pricing history</h2><p class="mt-1 text-sm text-slate-500">Latest and previous pricing versions are kept for audit. The technical key is shown only as secondary information.</p></div>
        </div>
        <div class="mt-4 overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead><tr class="border-b text-left text-slate-500"><th class="p-3">Feature</th><th class="p-3">Pricing type</th><th class="p-3">Price</th><th class="p-3">Version</th><th class="p-3">Status</th><th class="p-3">Published</th><th class="p-3">Action</th></tr></thead>
                <tbody>
                    <?php $__empty_0 = true; $__currentLoopData = $pricingRules; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $r): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_0 = false; ?>
                        <tr class="border-b align-top last:border-0">
                            <td class="p-3"><p class="font-bold text-slate-900"><?php echo e($pricingRuleTitles[$r->id] ?? $r->name); ?></p><p class="mt-1 font-mono text-[11px] text-slate-400"><?php echo e($r->key); ?></p></td>
                            <td class="p-3"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-bold text-slate-700"><?php echo e(str((string)data_get($r->metadata,'pricing_type','legacy'))->replace('_',' ')->headline()); ?></span><?php if(data_get($r->metadata,'pricing_type')==='ai_usage'): ?><p class="mt-2 text-xs text-slate-500"><?php echo e((int)data_get($r->metadata,'free_daily_requests',0)); ?> free/day</p><?php elseif(data_get($r->metadata,'pricing_type')==='fixed_access'): ?><p class="mt-2 text-xs text-slate-500"><?php echo e((int)data_get($r->metadata,'duration_days',0) > 0 ? data_get($r->metadata,'duration_days').' days' : 'No expiry'); ?></p><?php endif; ?></td>
                            <td class="p-3 font-semibold text-slate-800">
                                <?php if($r->unit_amount_minor !== null): ?>
                                    <?php echo e($r->currency); ?> <?php echo e(\App\Support\Money::fromMinor((int)$r->unit_amount_minor)); ?>

                                <?php elseif($r->percentage_basis_points !== null): ?>
                                    <?php echo e(\App\Support\Money::fromMinor((int)$r->percentage_basis_points)); ?>% <span class="text-xs font-normal text-slate-400">legacy percentage rule</span>
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td class="p-3">v<?php echo e($r->version); ?></td>
                            <td class="p-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-bold <?php echo e($r->enabled ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-600'); ?>"><?php echo e($r->enabled ? 'Active' : ($latestPricingRuleIds->has($r->id) ? 'Disabled' : 'Previous')); ?></span></td>
                            <td class="p-3 text-slate-500"><?php echo e($r->created_at?->format('d M Y, H:i') ?? '—'); ?></td>
                            <td class="p-3"><?php if($latestPricingRuleIds->has($r->id)): ?><form method="POST" action="<?php echo e(route('admin.monetization.pricing-rules.status',$r)); ?>"><?php echo csrf_field(); ?> <?php echo method_field('PATCH'); ?><input type="hidden" name="enabled" value="<?php echo e($r->enabled ? 0 : 1); ?>"><button class="rounded-lg px-3 py-2 text-xs font-bold <?php echo e($r->enabled ? 'border border-red-200 text-red-700' : 'bg-emerald-700 text-white'); ?>"><?php echo e($r->enabled ? 'Disable pricing' : 'Enable pricing'); ?></button></form><?php else: ?><span class="text-xs text-slate-400">Audit only</span><?php endif; ?></td>
                        </tr>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_0): ?>
                        <tr><td colspan="7" class="p-5 text-slate-500">No feature prices have been published yet. Features remain free until an active pricing rule is created.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="mt-4"><?php echo e($pricingRules->links()); ?></div>
    </section>
</div>

<?php $__env->startPush('scripts'); ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const select = document.getElementById('pricing-feature-select');
        const help = document.getElementById('pricing-feature-help');
        const fixedFields = document.getElementById('fixed-access-fields');
        const packageFields = document.getElementById('package-fields');
        const aiFields = document.getElementById('ai-usage-fields');
        if (!select || !help) return;

        const refreshPricingFeatureHelp = () => {
            const option = select.options[select.selectedIndex];
            const type = option?.dataset?.pricingType || '';
            const isPackage = option?.dataset?.package === '1';
            help.textContent = option?.dataset?.description || 'Select a feature to see what this pricing rule controls.';
            fixedFields?.classList.toggle('hidden', type !== 'fixed_access');
            packageFields?.classList.toggle('hidden', !isPackage);
            aiFields?.classList.toggle('hidden', type !== 'ai_usage');
        };

        select.addEventListener('change', refreshPricingFeatureHelp);
        refreshPricingFeatureHelp();
    });
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/admin/monetization/index.blade.php ENDPATH**/ ?>