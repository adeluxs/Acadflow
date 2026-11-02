import test from 'node:test';
import assert from 'node:assert/strict';
import {readFile, mkdir} from 'node:fs/promises';
import {chromium} from 'playwright';

const base=process.env.NOTIFICATION_BROWSER_URL;
test('new-user push invitation, notification preferences, deep links, help and admin analytics', {skip:!base,timeout:300000}, async()=>{
    assert.ok(['127.0.0.1','localhost'].includes(new URL(base).hostname),'Use an isolated local notification fixture.');
    const subscription=JSON.parse(await readFile('/private/tmp/acadsyntra-notifications-browser-subscription.json','utf8'));
    const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
    const errors=[];
    const artifacts='/private/tmp/acadsyntra-notifications-browser-shots';
    await mkdir(artifacts,{recursive:true});
    const login=async(email,{push=false}={})=>{
        const context=await browser.newContext({viewport:{width:1440,height:950},serviceWorkers:'block'});
        if(push)await context.addInitScript(subscription=>{
            window.permissionPrompts=0;
            let current=null;
            const item={endpoint:subscription.endpoint,options:{},toJSON:()=>subscription,unsubscribe:async()=>{current=null;return true;}};
            const registration={pushManager:{getSubscription:async()=>current,subscribe:async()=>{current=item;return item;}}};
            Object.defineProperty(window,'Notification',{value:{permission:'default',requestPermission:async()=>{window.permissionPrompts++;window.Notification.permission='granted';return 'granted';}}});
            Object.defineProperty(window,'PushManager',{value:function(){}});
            Object.defineProperty(navigator,'serviceWorker',{value:{register:async()=>registration,ready:Promise.resolve(registration),getRegistration:async()=>registration,addEventListener(){}}});
        },subscription);
        const page=await context.newPage();page.setDefaultTimeout(30000);page.setDefaultNavigationTimeout(60000);page.on('pageerror',error=>errors.push(error.message));
        await page.goto(base+'/notifications/settings');await page.locator('#email').fill(email);await page.locator('#password').fill('NotificationExample123!');
        await page.getByRole('button',{name:'Sign in to AcadSyntra'}).click();await page.waitForURL(url=>url.pathname!=='/login');
        return {page,context};
    };
    try{
        const {page:fresh}=await login('fresh@notifications.test',{push:true});
        await fresh.goto(base+'/onboarding');
        await fresh.locator('[data-push-invitation]').waitFor({state:'visible'});
        assert.equal(await fresh.evaluate(()=>window.permissionPrompts),0);
        const saved=fresh.waitForResponse(response=>response.url().endsWith('/browser-push/subscribe')&&response.request().method()==='POST'&&response.status()===200);
        await fresh.locator('[data-push-invitation] [data-push-enable]').click();await saved;
        assert.equal(await fresh.evaluate(()=>window.permissionPrompts),1);
        console.info('New-user push opt-in saved by the real backend.');

        const {page:student}=await login('student@notifications.test');
        await student.goto(base+'/notifications/settings');
        await student.locator('[name=timezone]').selectOption('Africa/Lagos');await student.locator('input[type=checkbox][name=engagement_reminders]').check();
        await student.getByRole('button',{name:'Save Settings',exact:true}).click();await student.getByText('Notification settings updated.',{exact:false}).first().waitFor();
        await student.reload();assert.equal(await student.locator('[name=timezone]').inputValue(),'Africa/Lagos');assert.equal(await student.locator('input[type=checkbox][name=engagement_reminders]').isChecked(),true);
        await student.setViewportSize({width:390,height:844});assert.ok(await student.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));await student.screenshot({path:artifacts+'/mobile-preferences.png',fullPage:true});
        await student.goto(base+'/notifications');await student.getByRole('link',{name:'Open update',exact:false}).first().click();await student.waitForURL(base+'/notifications/settings');
        console.info('Preferences persisted and the owner-authorised deep link opened.');

        const {page:admin}=await login('platform@notifications.test');
        await admin.goto(base+'/admin/notifications/analytics?fresh=1');await admin.getByRole('heading',{name:'Notification Analytics',exact:true}).waitFor();
        const stats=await (await admin.request.get(base+'/admin/notifications/analytics?fresh=1',{headers:{Accept:'application/json'}})).json();
        assert.ok(stats.report.generated>=1);assert.ok(stats.report.push.active>=1);assert.ok(stats.report.eventCounts.clicked>=1);
        await admin.screenshot({path:artifacts+'/notification-analytics.png',fullPage:true});
        await admin.locator('[data-chart-toggle]').first().click();assert.equal(await admin.locator('[data-chart-toggle]').first().getAttribute('aria-pressed'),'false');
        const download=admin.waitForEvent('download');await admin.getByRole('link',{name:'Export filtered CSV'}).click();assert.match((await download).suggestedFilename(),/notification-deliveries\.csv$/);
        await admin.setViewportSize({width:390,height:844});assert.ok(await admin.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1));
        await admin.goto(base+'/faqs');await admin.locator('[data-public-search]').fill('department adopt');
        await admin.waitForTimeout(300);assert.ok(await admin.locator('[data-search-item]:visible').count()>0);
        await admin.screenshot({path:artifacts+'/mobile-help-centre.png',fullPage:true});
        assert.deepEqual(errors,[],'The notification experience must have no JavaScript errors.');
        console.info('Actual analytics, CSV export, mobile layouts and searchable help verified.');
    }finally{await browser.close();}
});
