import test from 'node:test';
import assert from 'node:assert/strict';
import fs from 'node:fs/promises';
import {chromium} from 'playwright';

const base=process.env.ACADEMIC_ADOPTION_BROWSER_URL;
test('department wizard, existing-account invitation and consensual institution affiliation', {skip:!base,timeout:480000}, async()=>{
    assert.ok(['127.0.0.1','localhost'].includes(new URL(base).hostname),'Use an isolated local fixture server.');
    const browser=await chromium.launch({headless:true,executablePath:process.env.PLAYWRIGHT_CHROMIUM_EXECUTABLE_PATH||'/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'});
    const errors=[];
    const login=async email=>{
        const context=await browser.newContext({viewport:{width:1440,height:950},serviceWorkers:'block'});const page=await context.newPage();
        page.setDefaultTimeout(90000);page.setDefaultNavigationTimeout(90000);
        page.on('pageerror',error=>errors.push(error.message));
        // Set the intended destination before authentication so this lifecycle
        // test does not wait for unrelated personal dashboard integrations.
        await page.goto(base+'/academic/affiliations');await page.locator('#email').fill(email);await page.locator('#password').fill('AdoptionExample123!');
        await page.getByRole('button',{name:'Sign in to AcadSyntra'}).click();await page.waitForURL(url=>url.pathname!=='/login');
        console.info('Signed in fixture:',email);return {page,context};
    };
    await fs.mkdir('/private/tmp/acadsyntra-adoption-browser-shots',{recursive:true});
    try{
        const {page:rep}=await login('representative@adoption.test');
        await rep.goto(base+'/department/onboarding');
        await rep.locator('[data-wizard-step="1"]').click();
        await rep.locator('#institution-search').fill('Adoption Fixture');await rep.locator('#institution-search-button').click();
        await rep.locator('#institution-results button').filter({hasText:'Adoption Fixture University'}).click();
        await rep.locator('[name=faculty_id]').selectOption({label:'School of Science'});
        await rep.locator('[name=department_id]').selectOption({label:'Applied Research Department · unclaimed'});
        await rep.locator('[name=classification]').selectOption('mixed');await rep.locator('[data-wizard-next]').click();
        await rep.locator('[name=representative_title]').fill('Head of Department');await rep.locator('[name=official_email]').fill('department@adoption.test');await rep.locator('[name=official_phone]').fill('+2348000000000');await rep.locator('[name=evidence]').fill('Authorised department representative. Fixture verification evidence for isolated browser testing.');
        await rep.waitForFunction(()=>document.querySelector('#draft-status').textContent.startsWith('Progress saved'));
        await rep.reload();assert.equal(await rep.locator('[name=official_email]').inputValue(),'department@adoption.test');
        await rep.setViewportSize({width:390,height:844});
        assert.ok(await rep.evaluate(()=>document.documentElement.scrollWidth<=window.innerWidth+1),'Mobile wizard must fit the viewport.');
        await rep.screenshot({path:'/private/tmp/acadsyntra-adoption-browser-shots/mobile-wizard.png',fullPage:true});
        await rep.setViewportSize({width:1440,height:950});
        await rep.locator('[data-wizard-next]').click();await rep.locator('[data-wizard-next]').click();
        await rep.locator('[name=authority_confirmed]').check();await rep.screenshot({path:'/private/tmp/acadsyntra-adoption-browser-shots/application-review.png',fullPage:true});
        await rep.locator('[data-wizard-submit]').click();await rep.waitForURL(base+'/department/onboarding');await rep.getByText('Application submitted.',{exact:false}).first().waitFor();
        console.info('Application submitted');const {page:root}=await login('platform@adoption.test');await root.goto(base+'/admin/institution-onboarding');
        const approve=root.locator('form[action*="/admin/department-adoption/"][action$="/approve"]');await approve.getByRole('button').click();
        await rep.goto(base+'/academic/affiliations');await rep.getByRole('button',{name:'Current workspace',exact:true}).waitFor();
        await rep.goto(base+'/admin/department');await rep.getByRole('heading',{name:'Applied Research Department',exact:true}).waitFor();
        await rep.screenshot({path:'/private/tmp/acadsyntra-adoption-browser-shots/department-dashboard.png',fullPage:true});
        await rep.goto(base+'/academic/affiliations');const invite=rep.locator('#academic-invitation-form');await invite.locator('[name=email]').fill('student@adoption.test');await invite.getByRole('button',{name:'Create secure invitation'}).click();
        const invitationUrl=await rep.locator('a').filter({hasText:/\/academic\/invitations\//}).first().getAttribute('href');assert.ok(invitationUrl);
        const {page:student}=await login('student@adoption.test');await student.goto(invitationUrl);await student.getByRole('button',{name:'Accept invitation',exact:true}).click();await student.getByText('Invitation accepted using your existing account.',{exact:false}).waitFor();
        const {page:institution}=await login('institution@adoption.test');await institution.goto(base+'/academic/affiliations');
        const candidate=institution.locator('details').filter({has:institution.locator('form[action$="/academic/department-affiliations"]')});await candidate.locator('summary').click();await candidate.locator('[name=share_academic_data][type=checkbox]').check();await candidate.getByRole('button',{name:'Request affiliation',exact:true}).click();
        await rep.goto(base+'/academic/affiliations');await rep.getByRole('button',{name:'Approve proposed terms',exact:true}).click();
        await root.goto(base+'/academic/affiliations');await root.getByRole('button',{name:'Verify both approvals',exact:true}).click();
        await rep.goto(base+'/academic/affiliations');await rep.getByText('Affiliated',{exact:true}).waitFor();await rep.screenshot({path:'/private/tmp/acadsyntra-adoption-browser-shots/affiliated-department.png',fullPage:true});
        await rep.getByRole('button',{name:'End affiliation; retain department',exact:true}).click();await rep.getByText('Ended',{exact:true}).waitFor();
        await rep.goto(base+'/admin/department');await rep.getByRole('heading',{name:'Applied Research Department',exact:true}).waitFor();
        assert.deepEqual(errors,[],'No JavaScript errors during the complete flow.');
    }finally{await browser.close();}
});
