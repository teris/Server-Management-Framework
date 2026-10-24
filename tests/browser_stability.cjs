const assert = require('node:assert/strict');
const path = require('node:path');
const fs = require('node:fs');
const options = JSON.parse(process.argv[2] || '{}');
const { chromium } = require(options.module || process.env.SMF_PLAYWRIGHT_MODULE || 'playwright');
(async () => {
  let checks = 0;
  const check = (value, name) => { checks++; assert.ok(value, name); };
  const browser = await chromium.launch({ headless: true, executablePath: options.browser || process.env.SMF_BROWSER_BIN || undefined, args: ['--no-sandbox'] });
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage(); const errors = []; const external = [];
  page.on('pageerror', error => errors.push(error.message));
  page.on('request', request => { if (!request.url().startsWith(options.base) && !request.url().startsWith('data:') && !request.url().startsWith('blob:')) external.push(request.url()); });
  const base = options.base;
  async function visit(url) { const response = await page.goto(base + url, { waitUntil: 'networkidle' }); check(response.status() === 200, 'page renders: ' + url); }
  try {
    await visit('/src/login.php');
    await page.locator('[name=username]').fill('test-admin'); await page.locator('[name=password]').fill('Admin fixture password 123');
    await Promise.all([page.waitForURL('**/src/index.php'), page.locator('[name=password]').press('Enter')]);
    check(await page.locator('#dashboard-config').count() === 1, 'dashboard configuration is a JSON data block');
    check(await page.evaluate(() => Array.isArray(window.enabledPlugins) && typeof window.jsTranslations === 'object'), 'browser configuration parsed');
    await visit('/src/index.php?option=users');
    await page.locator('#user-username').fill('browser-user'); await page.locator('#user-email').fill('browser@example.invalid'); await page.locator('#user-password').fill('Browser user test password');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Speichern', exact: true }).click()]);
    check(await page.getByRole('cell', { name: 'browser@example.invalid', exact: true }).count() === 1, 'native browser user form saves account');
    await visit('/src/index.php?option=users&edit=1'); await page.locator('#user-role').selectOption('user');
    await Promise.all([page.waitForNavigation(), page.getByRole('button', { name: 'Speichern', exact: true }).click()]);
    check((await page.getByRole('alert').textContent()).includes('letzte aktive Administrator'), 'last-admin failure is visible');
    await visit('/src/index.php?option=modules&mod=proxmox'); check(await page.locator('#proxmox-content').count() === 1, 'Proxmox view renders using shared content contract');
    await visit('/src/index.php?option=profile');
    await visit('/src/index.php?option=settings');
    await page.locator('#site_title').fill('Browser operator title'); await page.locator('#mode').selectOption('database'); await page.locator('#session-timeout').fill('45'); await page.locator('#refresh-interval').fill('60');
    await page.locator('#general-settings-form button[type=submit]').click(); await page.locator('#general-settings-status .alert-success').waitFor();
    await visit('/src/index.php?option=settings');
    check(await page.locator('#site_title').inputValue() === 'Browser operator title' && await page.locator('#mode').inputValue() === 'database' && await page.locator('#session-timeout').inputValue() === '45', 'private operator settings survive another request and cache mode renders');
    await visit('/src/index.php?option=manualupdater'); check(await page.locator('#update-package').isDisabled(), 'unsigned/unconfigured updater cannot upload');
    const state = await context.storageState();
    const mobile = await browser.newContext({ storageState: state, viewport: { width: 390, height: 844 }, isMobile: true, hasTouch: true }); const small = await mobile.newPage(); small.on('pageerror', error => errors.push(error.message));
    await small.goto(base + '/src/index.php?option=users', { waitUntil: 'networkidle' });
    check(await small.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth + 2), 'mobile backend has no page-wide horizontal overflow');
    if (options.artifacts) { fs.mkdirSync(options.artifacts, { recursive: true }); await small.screenshot({ path: path.join(options.artifacts, 'AP16-benutzer-mobil.png'), fullPage: true }); }
    await mobile.close();
    const guest = await browser.newContext({ viewport: { width: 1280, height: 900 } }); const register = await guest.newPage(); register.on('pageerror', error => errors.push(error.message));
    await register.goto(base + '/public/register.php', { waitUntil: 'networkidle' });
    for (const [name,value] of Object.entries({ first_name: 'Browser', last_name: 'Customer', email: 'browser-customer@example.invalid', password: 'Browser registration password', confirm_password: 'Browser registration password' })) await register.locator(`[name=${name}]`).fill(value);
    await register.locator('[name=accept_terms]').check();
    await Promise.all([register.waitForNavigation(), register.locator('#registration-form button[type=submit]').click()]);
    check((await register.locator('[role=alert]').allTextContents()).some(text => text.includes('Konto angelegt')), 'browser registration completes with visible confirmation');
    await register.goto(base + '/public/resend-verification.php', { waitUntil: 'networkidle' });
    await register.locator('#email').focus(); await register.keyboard.press('Tab'); check(await register.evaluate(() => document.activeElement.tagName === 'BUTTON'), 'resend form is keyboard-operable');
    await register.goto(base + '/public/reset-password.php', { waitUntil: 'networkidle' }); await register.locator('#email').focus(); await register.keyboard.press('Tab'); check(await register.evaluate(() => document.activeElement.id === 'realm'), 'password reset keyboard order is usable');
    check(external.length === 0, 'tested core pages load no external scripts or styles');
    check(errors.length === 0, 'no browser runtime errors: ' + errors.join('; '));
    console.log(JSON.stringify({ checks, failed: 0, browser: browser.version(), viewports: ['1440x1000', '390x844'], external_requests: external.length }));
    await guest.close();
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
