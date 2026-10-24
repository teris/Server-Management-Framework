const fs = require('fs');
const options = JSON.parse(fs.readFileSync(0, 'utf8'));
const { chromium } = require(options.module);
const passed = [], pageErrors = [], assetErrors = [];
let activeBrowser;
function check(value, name) { if (!value) throw new Error(name); passed.push(name); }
(async () => {
  const browser = await chromium.launch({ headless: true, executablePath: options.browser });
  activeBrowser = browser;
  const context = await browser.newContext({ viewport: { width: 1440, height: 1000 } });
  const page = await context.newPage();
  page.on('pageerror', error => pageErrors.push({ page: new URL(page.url()).pathname, message: error.message }));
  page.on('response', response => { if (response.status() >= 400 && ['stylesheet', 'script', 'font', 'image'].includes(response.request().resourceType())) assetErrors.push({ url: response.url(), status: response.status() }); });
  for (const viewport of [{ width: 1440, height: 1000 }, { width: 390, height: 844 }]) {
    await page.setViewportSize(viewport);
    const response = await page.goto(options.url + '/', { waitUntil: 'networkidle' });
    check(response.status() === 200, `homepage ${viewport.width}`);
    check(await page.locator('h1').isVisible(), `heading visible ${viewport.width}`);
    check(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `no horizontal overflow ${viewport.width}`);
    await page.screenshot({ path: options.images + `\\shop-${viewport.width}.png`, fullPage: true });
    if (viewport.width < 600) {
      await page.locator('[data-menu-toggle]').first().click();
      check(await page.getByRole('link', { name: 'Produkte', exact: true }).first().isVisible(), 'mobile navigation opens');
    }
  }
  await page.setViewportSize({ width: 1440, height: 1000 });
  if (!options.adminState) {
    await page.goto(options.url + '/src/login.php');
    await page.locator('[name="username"]').fill(options.accounts.admin.username);
    await page.locator('[name="password"]').fill(options.accounts.admin.password);
    await Promise.all([page.waitForURL('**/src/index.php'), page.locator('button[type="submit"]').click()]);
  } else { await context.addCookies(options.adminState.cookies); }
  await page.goto(options.url + '/admin', { waitUntil: 'networkidle' });
  check(await page.locator('h1').filter({ hasText: 'Alles im Blick' }).isVisible(), 'core administrator session opens shop administration');
  await page.goto(options.url + '/src/modules.php');
  check(await page.getByRole('heading', { name: 'Module und Shop' }).isVisible(), 'module management opens with shared session');
  const customerContext = await browser.newContext({ viewport: { width: 390, height: 844 } });
  const customer = await customerContext.newPage();
  customer.on('pageerror', error => pageErrors.push({ page: new URL(customer.url()).pathname, message: error.message }));
  if (!options.customerState) {
    await customer.goto(options.url + '/login');
    await customer.locator('[name="email"]').fill(options.accounts['customer-a'].email);
    await customer.locator('[name="password"]').fill(options.accounts['customer-a'].password);
    await Promise.all([customer.waitForURL('**/konto'), customer.locator('form:has(input[name="password"]) button:not([type="button"])').last().click()]);
  } else { await customerContext.addCookies(options.customerState.cookies); await customer.goto(options.url + '/konto'); }
  check(await customer.locator('h1').isVisible(), 'customer signs in on mobile');
  await customer.goto(options.url + '/public/dashboard.php');
  check(!customer.url().includes('login.php'), 'same customer session opens core dashboard');
  check(pageErrors.length === 0, 'no uncaught browser errors');
  check(assetErrors.length === 0, 'all styles/scripts/fonts/images load');
  await browser.close();
  process.stdout.write(JSON.stringify({ checks: passed.length, failed: 0, passed, page_errors: pageErrors, asset_errors: assetErrors }) + '\n');
})().catch(async error => {
  let message = error.message;
  for (const person of Object.values(options.accounts)) message = message.split(person.password).join('[redacted]');
  process.stderr.write(JSON.stringify({ error: message, page_errors: pageErrors, asset_errors: assetErrors }) + '\n');
  if (activeBrowser) await activeBrowser.close().catch(() => {});
  process.exitCode = 1;
});
