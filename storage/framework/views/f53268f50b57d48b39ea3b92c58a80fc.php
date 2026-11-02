<section class="amb-card amb-section" id="apply">
    <h2><?php echo e($application ? 'Update and resubmit your application' : 'Tell us about your campus'); ?></h2>
    <p class="amb-muted">Students who are course reps, student leaders or volunteers can apply. The platform team reviews every application before activating referral tools.<?php if(! empty($profile)): ?> We filled in what your profile already tells us; check and adjust it.<?php endif; ?></p>
    <form method="POST" action="<?php echo e(route('ambassadors.apply')); ?>">
        <?php echo csrf_field(); ?>
        <div class="amb-fields">
            <?php $__currentLoopData = ['institution' => ['Institution', 200, true], 'faculty' => ['Faculty (optional)', 200, false], 'department' => ['Department / course', 200, true], 'study_level' => ['Level / year of study', 80, true], 'phone' => ['Contact phone with country code', 40, true]]; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $name => [$label, $limit, $required]): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                <label class="amb-field" for="amb-<?php echo e($name); ?>"><?php echo e($label); ?><input id="amb-<?php echo e($name); ?>" name="<?php echo e($name); ?>" value="<?php echo e(old($name, $application?->{$name} ?? ($profile[$name] ?? ''))); ?>" maxlength="<?php echo e($limit); ?>" <?php if($required): ?> required <?php endif; ?> <?php if($name === 'phone'): ?> type="tel" autocomplete="tel" <?php endif; ?>><?php $__errorArgs = [$name];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="amb-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
            <label class="amb-field" for="amb-role">Your role<select id="amb-role" name="leadership_role" required><?php $__currentLoopData = ['course_rep' => 'Course representative', 'student_leader' => 'Student leader', 'student_volunteer' => 'Student volunteer']; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $value => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($value); ?>" <?php if(old('leadership_role', $application?->leadership_role) === $value): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select></label>
            <label class="amb-field" for="amb-size">Students you could help<input id="amb-size" type="number" name="community_size" min="1" max="10000" value="<?php echo e(old('community_size', $application?->community_size)); ?>" required><small>A realistic estimate, not a signup commitment.</small></label>
            <label class="amb-field amb-wide" for="amb-social">Public social or professional profile (optional)<input id="amb-social" type="url" name="social_profile_url" value="<?php echo e(old('social_profile_url', $application?->social_profile_url)); ?>" maxlength="500" placeholder="https://"><small>Only if it helps us understand your community work. Leave blank if you prefer.</small><?php $__errorArgs = ['social_profile_url'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="amb-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
            <label class="amb-field amb-wide" for="amb-statement">Your first-month plan<textarea id="amb-statement" name="statement" rows="4" minlength="30" maxlength="3000" required><?php echo e(old('statement', $application?->statement)); ?></textarea><small>Why do you want to be an ambassador? Tell us who you help and one useful activity you would organise.</small><?php $__errorArgs = ['statement'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><small class="amb-error"><?php echo e($message); ?></small><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></label>
            <label class="amb-check amb-wide"><input type="checkbox" name="terms" value="1" required <?php if(old('terms')): echo 'checked'; endif; ?>><span>I am a student and my application is accurate. I have read the <a href="<?php echo e(route('public.terms')); ?>" target="_blank" rel="noopener">terms</a> and <a href="<?php echo e(route('public.privacy')); ?>" target="_blank" rel="noopener">privacy policy</a>. I will keep participation voluntary, disclose programme rewards, respect classmates’ privacy, and avoid spam, fake accounts or unapproved university endorsement. I understand rewards require review and approval.</span></label>
        </div>
        <?php $__errorArgs = ['application'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="amb-error" role="alert"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
        <div class="amb-actions"><button class="amb-button amb-button-primary">Submit application</button></div>
    </form>
</section>

<p class="amb-muted">To protect the programme from duplicate accounts, we use a first-party browser cookie and hashed signup signals for manual referral review. Shared campus devices or Wi-Fi are not automatically rejected.</p>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/ambassadors/_application-form.blade.php ENDPATH**/ ?>