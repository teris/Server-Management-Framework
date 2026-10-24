const fs = require('fs');
const options = JSON.parse(fs.readFileSync(0, 'utf8'));
const { chromium } = require(options.module);
let browser;const passed=[],errors=[],assets=[];
function check(ok,name){if(!ok)throw new Error(name);passed.push(name);}
(async()=>{
 browser=await chromium.launch({headless:true,executablePath:options.browser});
 const context=await browser.newContext({viewport:{width:390,height:844}});const page=await context.newPage();
 page.on('pageerror',e=>errors.push(e.message));page.on('response',r=>{if(r.status()>=400&&['stylesheet','script','font','image'].includes(r.request().resourceType()))assets.push(r.status());});
 await page.goto(options.url+'/setup.php',{waitUntil:'networkidle'});check(await page.locator('h1').isVisible(),'installer opens without configuration');
 check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'mobile installer has no horizontal overflow');
 await page.getByLabel('Startschlüssel',{exact:true}).fill(options.token);await page.getByRole('button',{name:'Weiter',exact:true}).click();
 await page.getByLabel('Privater Speicherordner',{exact:true}).fill(options.private);await page.getByLabel('Datenbanksystem',{exact:true}).selectOption('sqlite');await page.getByRole('button',{name:'Weiter',exact:true}).click();
 for(const[label,value]of Object.entries({'Shopname':'Browser Shop','HTTPS-Adresse der Shopdomain':'https://shop.example.invalid','Supportadresse':'support@example.invalid','E-Mail-Absender':'system@example.invalid','Administratorname':'browser-installer','Administrator-E-Mail':'admin@example.invalid','Administratorpasswort – mindestens 12 Zeichen':options.password,'Passwort bestätigen':options.password}))await page.getByLabel(label,{exact:true}).fill(value);
 await page.getByRole('button',{name:'Weiter',exact:true}).click();await page.getByRole('heading',{name:'Vorhandene Panels erkennen'}).waitFor();check(await page.getByRole('heading',{name:'Vorhandene Panels erkennen'}).isVisible(),'wizard advances to panel discovery: '+JSON.stringify({alerts:await page.getByRole('alert').allTextContents(),invalid:await page.locator('input:invalid').evaluateAll(items=>items.map(e=>({name:e.name,message:e.validationMessage}))),headings:await page.locator('h2').allTextContents()}));
 await page.getByRole('button',{name:'Verbindungen prüfen'}).click();await page.getByRole('heading',{name:'Shopmodule auswählen'}).waitFor();check(await page.getByRole('heading',{name:'Shopmodule auswählen'}).isVisible(),'shop works without external panels');
 await page.setViewportSize({width:1440,height:1000});check(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1),'desktop installer has no horizontal overflow');
 await page.getByRole('button',{name:'Weiter',exact:true}).click();await page.getByRole('heading',{name:'Prüfergebnis und Einrichtung'}).waitFor();check(await page.getByRole('heading',{name:'Prüfergebnis und Einrichtung'}).isVisible(),'installation has a concrete final review');
 await page.getByRole('button',{name:'Shop installieren',exact:true}).click();await page.getByRole('heading',{name:'Installation abgeschlossen',exact:true}).waitFor();check(true,'guided installation completes in browser');
 check((await page.content()).includes('keinen Cronjob eingerichtet'),'completion reports actual scheduler status');
 await page.getByRole('link',{name:'Zur Verwaltung',exact:true}).click();await page.locator('[name=username]').fill('browser-installer');await page.locator('[name=password]').fill(options.password);await Promise.all([page.waitForURL('**/src/index.php'),page.locator('button[type=submit]').click()]);await page.waitForLoadState('networkidle');check(page.url().endsWith('/src/index.php'),'chosen administrator signs into installed application');
 const closed=await page.goto(options.url+'/setup.php');check(closed.status()===409,'completed installation cannot be reopened');check(errors.length===0,'no uncaught browser errors');check(assets.length===0,'installer assets load successfully');
 process.stdout.write(JSON.stringify({checks:passed.length,failed:0,passed,browser:browser.version(),viewports:['390x844','1440x1000']})+'\n');
})().catch(e=>{let message=e.message;for(const v of[options.token,options.password])message=message.split(v).join('[redacted]');process.stderr.write(JSON.stringify({error:message,browser_errors:errors})+'\n');process.exitCode=1;}).finally(async()=>{if(browser)await browser.close();});
