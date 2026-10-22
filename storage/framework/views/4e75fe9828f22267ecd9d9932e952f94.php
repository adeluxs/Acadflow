<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #4f46e5; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .footer { padding: 20px; text-align: center; font-size: 12px; color: #666; }
        .button { display: inline-block; padding: 10px 20px; background: #4f46e5; color: white; text-decoration: none; border-radius: 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><?php echo e(\App\Services\SettingService::get('site_name', 'AcadSyntra')); ?> Notification</h1>
        </div>
        <div class="content">
            <h2><?php echo e($title); ?></h2>
            <p><?php echo e($body); ?></p>
            <?php if(isset($data['url'])): ?>
                <p><a href="<?php echo e($data['url']); ?>" class="button">View Details</a></p>
            <?php endif; ?>
        </div>
        <div class="footer">
            <p>This is an automated message from <?php echo e(\App\Services\SettingService::get('site_name', 'AcadSyntra')); ?>.</p>
            <p>Manage your <a href="<?php echo e(route('notifications.settings')); ?>">notification preferences</a> and quiet hours.</p>
            <?php if($unsubscribeUrl ?? null): ?><p><a href="<?php echo e($unsubscribeUrl); ?>">Unsubscribe from these optional reminders</a></p><?php endif; ?>
            <?php if($supportEmail = \App\Services\PublicWebsite::supportEmail()): ?>
            <p>Contact us: <a href="mailto:<?php echo e($supportEmail); ?>"><?php echo e($supportEmail); ?></a></p>
            <?php endif; ?>
        </div>
    </div>
    <?php if($trackingUrl ?? null): ?><img src="<?php echo e($trackingUrl); ?>" width="1" height="1" alt=""><?php endif; ?>
</body>
</html>
<?php /**PATH /Users/user/Desktop/acadflow/acadflow-web/resources/views/emails/notification.blade.php ENDPATH**/ ?>