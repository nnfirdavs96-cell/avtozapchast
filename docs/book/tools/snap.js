#!/usr/bin/env node
/**
 * Снимок экрана: реальный браузер -> PNG.
 * Этим же скриптом делаются все иллюстрации книги, поэтому картинки
 * в ней — снимки настоящего экрана, а не рисунки.
 *
 * node tools/snap.js <url|файл> <куда.png> [ширина] [высота] [css-селектор]
 */
const fs = require('fs');
const path = require('path');
const { chromium } = require('playwright');

(async () => {
  const [adres, vyhod, w = '1180', h = '900', selektor] = process.argv.slice(2);
  if (!adres || !vyhod) { console.error('нужны адрес и файл'); process.exit(1); }

  const gotovy = '/opt/pw-browsers/chromium';
  const browser = await chromium.launch(fs.existsSync(gotovy) ? { executablePath: gotovy } : {});
  const page = await browser.newPage({
    viewport: { width: +w, height: +h },
    deviceScaleFactor: 2,           // ретина: текст на скриншотах читаемый
  });

  const url = adres.startsWith('http') ? adres : 'file://' + path.resolve(adres);
  await page.goto(url, { waitUntil: 'networkidle' });
  await page.waitForTimeout(350);   // дать шрифтам дорисоваться

  fs.mkdirSync(path.dirname(path.resolve(vyhod)), { recursive: true });
  if (selektor) await page.locator(selektor).screenshot({ path: vyhod });
  else await page.screenshot({ path: vyhod });

  await browser.close();
  console.log('снято:', vyhod);
})();
