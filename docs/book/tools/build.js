#!/usr/bin/env node
/**
 * Сборка книги: Markdown -> оформленный HTML -> PDF под печать.
 *
 * Почему свой сборщик, а не готовый генератор: книге нужна вёрстка,
 * которой в генераторах нет — блоки-врезки по эмодзи-заголовкам,
 * титул главы с крупной цифрой и вшитые шрифты с кириллицей
 * (интернета при сборке может не быть, поэтому шрифты кладём в base64).
 *
 * Запуск:  node tools/build.js         — HTML + PDF
 *          node tools/build.js --html  — только HTML (быстро)
 */
const fs = require('fs');
const path = require('path');
const MarkdownIt = require('markdown-it');
const cheerio = require('cheerio');
const hljs = require('highlight.js');

const BOOK = path.resolve(__dirname, '..');
const OUT_HTML = path.join(BOOK, 'kniga.html');
const OUT_PDF = path.join(BOOK, 'kniga.pdf');

/* ---------- шрифты: вшиваем прямо в файл ---------- */

function font(pkg, file) {
  const p = path.join(__dirname, 'node_modules', '@fontsource', pkg, 'files', file);
  if (!fs.existsSync(p)) { console.warn('нет шрифта:', file); return null; }
  return fs.readFileSync(p).toString('base64');
}

function faceCss(family, pkg, files, weight, style = 'normal') {
  return files.map((f) => {
    const b64 = font(pkg, f);
    if (!b64) return '';
    return `@font-face{font-family:'${family}';font-style:${style};font-weight:${weight};` +
      `font-display:block;src:url(data:font/woff2;base64,${b64}) format('woff2');}`;
  }).join('\n');
}

const FONTS = [
  // Unbounded — заголовки. Геометрический, характерный, ни на что не похожий.
  faceCss('Unbounded', 'unbounded', ['unbounded-cyrillic-500-normal.woff2', 'unbounded-latin-500-normal.woff2'], 500),
  faceCss('Unbounded', 'unbounded', ['unbounded-cyrillic-700-normal.woff2', 'unbounded-latin-700-normal.woff2'], 700),
  faceCss('Unbounded', 'unbounded', ['unbounded-cyrillic-800-normal.woff2', 'unbounded-latin-800-normal.woff2'], 800),
  // Spectral — основной текст. Сделан для долгого чтения и с экрана, и с бумаги.
  faceCss('Spectral', 'spectral', ['spectral-cyrillic-400-normal.woff2', 'spectral-latin-400-normal.woff2'], 400),
  faceCss('Spectral', 'spectral', ['spectral-cyrillic-600-normal.woff2', 'spectral-latin-600-normal.woff2'], 600),
  faceCss('Spectral', 'spectral', ['spectral-cyrillic-400-italic.woff2', 'spectral-latin-400-italic.woff2'], 400, 'italic'),
  // JetBrains Mono — код. Кириллица в комментариях не разъезжается.
  faceCss('JBMono', 'jetbrains-mono', ['jetbrains-mono-cyrillic-400-normal.woff2', 'jetbrains-mono-latin-400-normal.woff2'], 400),
  faceCss('JBMono', 'jetbrains-mono', ['jetbrains-mono-cyrillic-700-normal.woff2', 'jetbrains-mono-latin-700-normal.woff2'], 700),
].join('\n');

/* ---------- разметка ---------- */

const md = new MarkdownIt({
  html: true,
  linkify: false,
  typographer: true,
  quotes: '«»„“',
  highlight(str, lang) {
    if (lang && hljs.getLanguage(lang)) {
      try { return hljs.highlight(str, { language: lang }).value; } catch (e) { /* см. ниже */ }
    }
    return md.utils.escapeHtml(str);
  },
});

/* Врезки: заголовок вида «## 🎯 Зачем» превращается в цветной блок.
   Ключ — эмодзи, значение — css-класс и подпись сбоку. */
const VREZKI = {
  '🎯': ['zachem', 'Зачем это'],
  '📖': ['obyasnenie', 'Разбираемся'],
  '💻': ['kod', 'Код'],
  '🔤': ['razbor', 'Разбор по словам'],
  '🖥': ['ekran', 'На экране'],
  '⚠️': ['grabli', 'Грабли'],
  '🏋️': ['zadachi', 'Задачи'],
  '🔗': ['vboyu', 'В бою'],
  '📌': ['itog', 'Итог'],
};

function oformit(htmlRaw, meta) {
  const $ = cheerio.load(`<div id="root">${htmlRaw}</div>`, null, false);

  // Навигация «← Оглавление · Глава 2 →» нужна на GitHub, но не в единой книге.
  $('#root > blockquote').first().remove();
  $('#root > p').each((_, el) => {
    const t = $(el).text().trim();
    if (/←\s*Оглавление|Оглавление\s*\)|→\s*$/.test(t) && t.length < 120) $(el).remove();
  });

  const kids = $('#root').children().toArray();
  let bufer = null;
  const novye = [];
  const zakryt = () => { if (bufer) { novye.push(bufer); bufer = null; } };

  for (const el of kids) {
    const $el = $(el);
    if (el.tagName === 'h2') {
      const txt = $el.text().trim();
      const klyuch = Object.keys(VREZKI).find((e) => txt.startsWith(e));
      zakryt();
      if (klyuch) {
        const [cls, podpis] = VREZKI[klyuch];
        const zagolovok = txt.slice(klyuch.length).trim();
        const sect = $(`<section class="vrezka v-${cls}"></section>`);
        sect.append(`<div class="vrezka-shapka"><span class="vrezka-podpis">${podpis}</span>` +
          `<span class="vrezka-zagolovok">${zagolovok}</span></div>`);
        bufer = sect;
        continue;
      }
      novye.push($el);
      continue;
    }
    if (bufer) bufer.append($el); else novye.push($el);
  }
  zakryt();

  const telo = $('<div class="telo"></div>');
  novye.forEach((n) => telo.append(n));
  // Разделители, оставшиеся от вырезанной навигации, только мешают.
  telo.find('hr + hr').remove();
  telo.children().first().filter('hr').remove();
  telo.children().last().filter('hr').remove();
  telo.find('hr').each((_, el) => {           // «---» перед врезкой не нужен: врезка сама видна
    const sled = $(el).next();
    if (sled.length && sled.hasClass('vrezka')) $(el).remove();
  });

  const dvuznak = String(meta.nomer).padStart(2, '0');
  const shapka = `
    <header class="glava-shapka">
      <div class="glava-metka">
        <span class="glava-slovo">Глава</span>
        <span class="glava-nomer">${dvuznak}</span>
      </div>
      <div class="glava-podpis">${meta.chast}</div>
      <h1 class="glava-nazvanie">${meta.nazvanie}</h1>
    </header>`;

  return `<article class="glava" id="glava-${meta.nomer}">${shapka}${$.html(telo)}</article>`;
}

/* ---------- сборка ---------- */

function razobratZagolovok(mdText, fallback) {
  const m = mdText.match(/^#\s+Глава\s+(\d+)\.\s*(.+)$/m);
  const chast = (mdText.match(/^>\s*\*\*(Часть[^*]+)\*\*/m) || [])[1] || '';
  return {
    nomer: m ? m[1] : fallback,
    nazvanie: m ? m[2].trim() : 'Без названия',
    chast: chast.trim(),
  };
}

function sobrat() {
  const dir = path.join(BOOK, 'glavy');
  const faily = fs.existsSync(dir)
    ? fs.readdirSync(dir).filter((f) => f.endsWith('.md')).sort()
    : [];

  const glavy = faily.map((f, i) => {
    let text = fs.readFileSync(path.join(dir, f), 'utf8');
    const meta = razobratZagolovok(text, String(i + 1));
    text = text.replace(/^#\s+Глава.*$/m, '');               // заголовок рисуем сами
    text = text.replace(/!\[([^\]]*)\]\(\.\.\//g, '![$1](');  // пути картинок — от папки книги
    return { meta, html: oformit(md.render(text), meta) };
  });

  const oglavlenie = glavy.map((g) =>
    `<li><span class="og-nomer">${g.meta.nomer}</span>` +
    `<a href="#glava-${g.meta.nomer}">${g.meta.nazvanie}</a></li>`).join('\n');

  const css = fs.readFileSync(path.join(__dirname, 'style.css'), 'utf8');

  const html = `<!doctype html>
<html lang="ru"><head><meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Сайт с нуля — учебник fullstack-разработки</title>
<style>${FONTS}</style>
<style>${css}</style>
</head><body>

<section class="oblozhka">
  <div class="ob-nadpis">учебник для тех, кто<br>никогда не программировал</div>
  <h1 class="ob-titul">Сайт<br><span class="ob-akcent">с нуля</span></h1>
  <div class="ob-podzag">Как собрать интернет-магазин<br>и стать fullstack-разработчиком</div>
  <div class="ob-liniya"></div>
  <div class="ob-niz">
    <div><strong>60 глав</strong> · практика на живом коде</div>
    <div class="ob-domen">autodoc.tj</div>
  </div>
</section>

<section class="oglavlenie">
  <h2 class="og-titul">Содержание</h2>
  <ol class="og-spisok">${oglavlenie}</ol>
  <p class="og-primechanie">Книга пишется по частям. Здесь — главы, готовые на сегодня.</p>
</section>

${glavy.map((g) => g.html).join('\n')}

<footer class="konec">
  <div class="konec-znak">§</div>
  <p>Продолжение следует.</p>
</footer>
</body></html>`;

  fs.writeFileSync(OUT_HTML, html);
  console.log(`HTML собран: ${OUT_HTML} (${glavy.length} глав, ${(html.length / 1048576).toFixed(1)} МБ)`);
  return glavy.length;
}

async function pdf() {
  const { chromium } = require('playwright');
  // В окружении уже стоит Chromium; своей сборки Playwright может не найти,
  // поэтому указываем путь к предустановленному бинарнику, если он есть.
  const gotovy = '/opt/pw-browsers/chromium';
  const opcii = fs.existsSync(gotovy) ? { executablePath: gotovy } : {};
  const browser = await chromium.launch(opcii);
  const page = await browser.newPage();
  await page.goto('file://' + OUT_HTML, { waitUntil: 'networkidle' });
  await page.emulateMedia({ media: 'print' });
  await page.pdf({
    path: OUT_PDF,
    format: 'A4',
    printBackground: true,
    margin: { top: '16mm', bottom: '18mm', left: '20mm', right: '18mm' },
    displayHeaderFooter: true,
    headerTemplate: '<div></div>',
    footerTemplate:
      `<div style="width:100%;font-family:Georgia,serif;font-size:8pt;color:#8a7f72;
        padding:0 18mm;display:flex;justify-content:space-between;">
        <span>Сайт с нуля</span><span class="pageNumber"></span></div>`,
  });
  await browser.close();
  console.log(`PDF собран: ${OUT_PDF} (${(fs.statSync(OUT_PDF).size / 1048576).toFixed(1)} МБ)`);
}

(async () => {
  const n = sobrat();
  if (!n) { console.log('Глав нет — положите .md в docs/book/glavy/'); return; }
  if (!process.argv.includes('--html')) await pdf();
})();
