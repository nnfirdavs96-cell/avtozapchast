#!/usr/bin/env node
/**
 * Вывод программы -> картинка «окно консоли».
 *
 * Вывод берётся из настоящего запуска (браузер или php), а не пишется руками,
 * поэтому картинки в книге не могут разойтись с тем, что реально происходит.
 *
 * node tools/konsol.js <файл-с-выводом.txt> <куда.png> "<заголовок>" "<подпись>" [вкладка]
 */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');

const [istochnik, vyhod, titul = '', podpis = '', vkladka = 'Console'] = process.argv.slice(2);
const stroki = fs.readFileSync(istochnik, 'utf8').replace(/\n$/, '').split('\n');

const ekran = (s) => s.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const punkty = stroki.map((s) =>
  `<div class="str"><span class="mtk">&rsaquo;</span><span class="txt">${ekran(s)}</span></div>`).join('\n');

const html = `<!doctype html><html><head><meta charset="utf-8"><style>
@import url("shrifty.css");
*{box-sizing:border-box;margin:0}
body{width:940px;background:#FBF7F0;font-family:'Spe',serif;padding:30px 32px}
.tit{font-family:'Unb';font-weight:700;font-size:21px;letter-spacing:-.03em;margin-bottom:16px}
.okno{border:1.5px solid #D9CFC0;border-radius:8px;overflow:hidden;background:#fff;
  box-shadow:0 6px 20px -14px rgba(34,30,26,.5)}
.shapka{background:#F1EAE0;border-bottom:1px solid #E3D8C6;padding:9px 14px;display:flex;align-items:center;gap:9px}
.krug{width:11px;height:11px;border-radius:50%}
.k1{background:#E0685A}.k2{background:#E5B54A}.k3{background:#63B26A}
.vkl{margin-left:14px;font-family:'JB';font-size:13px;color:#6E6459}
.vkl b{color:#B5411F;font-weight:400;border-bottom:2px solid #B5411F;padding-bottom:8px}
.telo{padding:10px 0}
.str{display:flex;gap:11px;padding:4px 16px;border-bottom:1px solid #F6F1EA;align-items:baseline}
.str:last-child{border-bottom:0}
.mtk{color:#B0A79B;font-family:'JB';font-size:13px}
.txt{font-family:'JB';font-size:14px;color:#2b2622;white-space:pre-wrap;
  font-variant-ligatures:none;font-feature-settings:"liga" 0,"calt" 0}
.pod{margin-top:12px;font-size:15.5px;color:#6E6459;font-style:italic}
</style></head><body>
${titul ? `<div class="tit">${titul}</div>` : ''}
<div class="okno">
  <div class="shapka"><span class="krug k1"></span><span class="krug k2"></span><span class="krug k3"></span>
    <span class="vkl">${vkladka === 'Console'
      ? 'Elements &nbsp; <b>Console</b> &nbsp; Sources &nbsp; Network'
      : `<b>${vkladka}</b>`}</span></div>
  <div class="telo">${punkty}</div>
</div>
${podpis ? `<div class="pod">${podpis}</div>` : ''}
</body></html>`;

const vremenny = path.join(__dirname, '..', 'code', 'shemy', '_konsol-vremenny.html');
fs.writeFileSync(vremenny, html);
const vysota = Math.min(1600, 190 + stroki.length * 30 + (podpis ? 40 : 0));
execFileSync('node', [path.join(__dirname, 'snap.js'), vremenny, vyhod, '940', String(vysota), 'body'],
  { stdio: 'inherit', cwd: path.join(__dirname, '..') });
fs.unlinkSync(vremenny);
