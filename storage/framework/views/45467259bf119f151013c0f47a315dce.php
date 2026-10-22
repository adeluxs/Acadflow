<?php $__env->startSection('content'); ?>
<?php echo $__env->make('public.partials.page-heading', ['eyebrow' => 'WE’RE HERE TO HELP'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<section class="section section-topless"><div class="site-container contact-grid"><aside class="contact-aside"><span class="feature-icon"><?php echo $__env->make('public.partials.icon', ['icon' => 'chat'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span><h2>A conversation<br>starts with context.</h2><p>Tell us what you’re working on, where you’re stuck or what your institution needs.</p><div class="contact-tip"><h3>For account questions</h3><p>Use your account email and describe the page or action involved. Your institution administrator can help with course access and membership approvals.</p></div><div class="contact-tip"><h3>For institutions</h3><p>Include the institution name and a short description of the learning, research or administration workflows you want to support.</p></div><?php ($contactEmail = \App\Services\PublicWebsite::supportEmail()); ?><?php if($contactEmail): ?><div class="contact-tip"><h3>Prefer email?</h3><a class="text-link" href="mailto:<?php echo e($contactEmail); ?>"><?php echo e($contactEmail); ?></a></div><?php endif; ?><p class="contact-safety">Keep passwords, payment-card details and confidential academic records out of your message.</p></aside><div class="contact-form-card">
<?php if(session('contact_reference')): ?><div class="success-panel" role="status" tabindex="-1" data-contact-success><span class="feature-icon"><?php echo $__env->make('public.partials.icon', ['icon' => 'check'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></span><h2>Your message has a place.</h2><p>Your enquiry is saved in the platform team’s inbox. Keep this reference if you need to follow up.</p><code><?php echo e(session('contact_reference')); ?></code><a class="button button-secondary" href="<?php echo e(route('public.contact')); ?>">Send another message</a></div>
<?php else: ?>
<h2>Send a message</h2><p class="form-intro">All fields are required. A member of the platform team can reply using the email you provide.</p>
<?php if($errors->any()): ?><div class="form-errors" role="alert" tabindex="-1" data-form-errors><strong>Please check your message.</strong><ul><?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><li><?php echo e($error); ?></li><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></ul></div><?php endif; ?>
<?php if(session('error')): ?><div class="form-errors" role="alert"><?php echo e(session('error')); ?></div><?php endif; ?>
<form method="POST" action="<?php echo e(route('public.contact.store')); ?>" data-contact-form>
<?php echo csrf_field(); ?><input type="hidden" name="form_token" value="<?php echo e($formToken); ?>"><div class="form-trap" aria-hidden="true"><label for="website">Leave this field empty</label><input id="website" name="website" type="text" tabindex="-1" autocomplete="off"></div>
<div class="form-two-columns"><div class="form-field"><label for="contact-name">Your name</label><input id="contact-name" name="name" required maxlength="120" autocomplete="name" value="<?php echo e(old('name')); ?>" placeholder="How should we address you?" <?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="name-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><?php $__errorArgs = ['name'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="field-error" id="name-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div><div class="form-field"><label for="contact-email">Email address</label><input id="contact-email" name="email" type="email" required maxlength="254" autocomplete="email" value="<?php echo e(old('email')); ?>" placeholder="Where can we reply?" <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="email-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="field-error" id="email-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div></div>
<div class="form-field"><label for="contact-topic">What can we help with?</label><select id="contact-topic" name="topic" required <?php $__errorArgs = ['topic'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" aria-describedby="topic-error" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><option value="">Choose a topic</option><?php $__currentLoopData = \App\Models\ContactMessage::TOPICS; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $key => $label): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><option value="<?php echo e($key); ?>" <?php if(old('topic') === $key): echo 'selected'; endif; ?>><?php echo e($label); ?></option><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?></select><?php $__errorArgs = ['topic'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="field-error" id="topic-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
<div class="form-field"><label for="contact-message">Your message</label><textarea id="contact-message" name="message" rows="7" minlength="20" maxlength="5000" required placeholder="A little detail helps us understand what you need…" aria-describedby="message-hint <?php $__errorArgs = ['message'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> message-error <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>" <?php $__errorArgs = ['message'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> aria-invalid="true" <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>><?php echo e(old('message')); ?></textarea><p class="field-hint" id="message-hint">20–5,000 characters. Please leave out sensitive information.</p><?php $__errorArgs = ['message'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?><p class="field-error" id="message-error"><?php echo e($message); ?></p><?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?></div>
<label class="checkbox-label"><input type="checkbox" name="privacy_acknowledged" value="1" required <?php if(old('privacy_acknowledged')): echo 'checked'; endif; ?>><span>I have read the <a href="<?php echo e(route('public.privacy')); ?>">Privacy Policy</a> and understand that my details will be used to handle this enquiry.</span></label>
<p class="field-hint form-privacy-note">Your message is saved in a restricted support inbox. Sending it does not subscribe you to marketing.</p><button class="button contact-submit" type="submit"><span data-submit-label>Send message</span><?php echo $__env->make('public.partials.icon', ['icon' => 'arrow'], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?></button><p class="field-hint" data-submit-status aria-live="polite"></p>
</form><?php endif; ?></div></div></section>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.public', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/public/contact.blade.php ENDPATH**/ ?>