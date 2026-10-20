<?php echo e(\App\Services\SettingService::get('site_name', 'AcadSyntra')); ?> Notification

--------------------------------------------------------------------------------

<?php echo e($body); ?>


<?php if(isset($data['url'])): ?>
View Details: <?php echo e($data['url']); ?>

<?php endif; ?>

--------------------------------------------------------------------------------
This is an automated message from <?php echo e(\App\Services\SettingService::get('site_name', 'AcadSyntra')); ?>.
To manage your notification preferences, visit your account settings: <?php echo e(config('app.url')); ?>/settings
<?php if($supportEmail = \App\Services\PublicWebsite::supportEmail()): ?>
Contact us: <?php echo e($supportEmail); ?>

<?php endif; ?>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/emails/plain-text.blade.php ENDPATH**/ ?>