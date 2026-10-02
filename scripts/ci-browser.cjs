// Recette du starter sur la vraie pile Docker en CI : aucune donnée de visiteur.
const { chromium } = require(process.env.BOARD_PLAYWRIGHT_MODULE || 'playwright');
const fs = require('node:fs');
(async () => {
  const browser = await chromium.launch({ executablePath: '/usr/bin/google-chrome', headless: true, args: ['--no-sandbox'] });
  try {
    const context = await browser.newContext({ viewport: { width: 360, height: 800 } });
    const page = await context.newPage();
    const errors = []; page.on('pageerror', error => errors.push(error.message));
    await page.goto('http://127.0.0.1:8080/');
    await page.waitForFunction(() => document.querySelector('#submit-button').disabled === false);
    if (await page.locator('#school option').count() !== 5) throw new Error('Référentiel écoles incorrect');
    if (await page.locator('#entry-level option').count() !== 12) throw new Error('Référentiel niveaux incorrect');
    await page.locator('#last-name').fill('Démonstration');
    await page.locator('#first-name').fill('Camille');
    await page.locator('#birth-date').fill('2008-04-12');
    await page.locator('#phone').fill('0600000000');
    await page.locator('#email').fill('demo@example.test');
    await page.locator('#school').selectOption('3');
    await page.locator('#entry-level').selectOption('7');
    await page.locator('#submit-button').click();
    await page.waitForFunction(() => document.querySelector('#message').textContent.includes('ne sont pas ouvertes'));
    if (await page.locator('#last-name').inputValue() !== 'Démonstration') throw new Error('Saisie perdue après refus');
    if (await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)) throw new Error('Débordement à 360 px');
    await page.screenshot({ path: 'reports/formulaire-mobile.png', fullPage: true });
    await page.goto('http://127.0.0.1:8080/admin.html');
    await page.locator('#username').fill('ci-browser');
    await page.locator('#password').fill(process.env.TEST_ADMIN_PASSWORD);
    await page.locator('#login-form button').click();
    await page.locator('#dashboard-section').waitFor({ state: 'visible' });
    await page.locator('#load-list').click();
    await page.waitForFunction(() => document.querySelector('#admin-message').textContent.includes('réaliser'));
    await page.screenshot({ path: 'reports/espace-equipe-mobile.png', fullPage: true });
    await page.locator('#logout').click();
    await page.locator('#login-section').waitFor({ state: 'visible' });
    const status = await page.evaluate(async () => (await fetch('/api/admin/registrations')).status);
    if (status !== 401) throw new Error('Accès autorisé après déconnexion');
    await page.setViewportSize({ width: 1280, height: 900 });
    await page.goto('http://127.0.0.1:8080/');
    await page.waitForFunction(() => document.querySelector('#submit-button').disabled === false);
    await page.screenshot({ path: 'reports/formulaire-desktop.png', fullPage: true });
    if (errors.length) throw new Error(errors.join('\n'));
    const result = 'OK : référentiels réels, refus de collecte, saisies conservées, mobile sans débordement, connexion par session, réponse 501 explicite, déconnexion et refus 401.\n';
    fs.writeFileSync('reports/navigateur.txt', result);
    console.log(result);
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exit(1); });
