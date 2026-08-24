<?php
/**
 * Выполняет SQL и рисует результат таблицей — картинкой для книги.
 *
 * Запросы в книге написаны под MySQL, но проверяются на SQLite: базовый SQL
 * (SELECT, WHERE, JOIN, GROUP BY, ORDER BY) в них совпадает слово в слово.
 * Так каждая таблица в книге — результат настоящего выполнения, а не
 * набранный руками текст, который легко разойдётся с правдой.
 *
 * php tools/sql.php <база.sqlite> <файл-с-запросом.sql> <куда.html> "<заголовок>" "<подпись>"
 */

[$sam, $baza, $fail_zaprosa, $vyhod, $titul, $podpis] = array_pad($argv, 6, '');

$pdo = new PDO('sqlite:' . $baza);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$zapros = trim(file_get_contents($fail_zaprosa));
$stroki = $pdo->query($zapros)->fetchAll(PDO::FETCH_ASSOC);

function e(?string $t): string {
    return htmlspecialchars((string) $t, ENT_QUOTES, 'UTF-8');
}

// Подсветка ключевых слов — чтобы запрос читался, а не был серой простынёй
$klyuchevye = ['SELECT','FROM','WHERE','AND','OR','NOT','ORDER BY','GROUP BY','HAVING',
    'LIMIT','OFFSET','JOIN','LEFT JOIN','INNER JOIN','ON','AS','INSERT INTO','VALUES',
    'UPDATE','SET','DELETE','LIKE','IN','BETWEEN','IS NULL','IS NOT NULL','COUNT','SUM',
    'AVG','MIN','MAX','ROUND','DESC','ASC','DISTINCT'];
$podsvechennyi = e($zapros);
foreach ($klyuchevye as $slovo) {
    $podsvechennyi = preg_replace('/\b' . preg_quote($slovo, '/') . '\b/u',
        '<b>' . $slovo . '</b>', $podsvechennyi);
}

$kolonki = $stroki ? array_keys($stroki[0]) : [];

$shapka_tablicy = '';
foreach ($kolonki as $k) {
    $shapka_tablicy .= '<th>' . e($k) . '</th>';
}

$telo_tablicy = '';
foreach ($stroki as $s) {
    $telo_tablicy .= '<tr>';
    foreach ($s as $z) {
        $chislo = is_numeric($z) ? ' class="ch"' : '';
        $telo_tablicy .= '<td' . $chislo . '>' . e((string) $z) . '</td>';
    }
    $telo_tablicy .= '</tr>';
}

$vsego = count($stroki);
$slovo = ($vsego % 10 === 1 && $vsego % 100 !== 11) ? 'строка'
    : (in_array($vsego % 10, [2,3,4], true) && !in_array($vsego % 100, [12,13,14], true) ? 'строки' : 'строк');

$html = <<<HTML
<!doctype html><html><head><meta charset="utf-8"><style>
@import url("shrifty.css");
*{box-sizing:border-box;margin:0}
body{width:980px;background:#FBF7F0;font-family:'Spe',serif;color:#221E1A;padding:30px 32px}
.tit{font-family:'Unb';font-weight:700;font-size:21px;letter-spacing:-.03em;margin-bottom:16px}
.zapros{background:#F7F1E6;border:1px solid #E3D8C6;border-left:4px solid #B5411F;
  border-radius:6px;padding:14px 16px;font-family:'JB';font-size:14px;line-height:1.6;
  white-space:pre-wrap;margin-bottom:6px;color:#2b2622}
.zapros b{color:#9B2C6F;font-weight:400}
.strelka{text-align:center;color:#B0A79B;font-size:18px;margin:8px 0}
.rez{border:1px solid #D9CFC0;border-radius:6px;overflow:hidden;background:#fff}
table{width:100%;border-collapse:collapse;font-size:14px}
th{font-family:'Unb';font-weight:700;font-size:10.5px;letter-spacing:.1em;text-transform:uppercase;
  text-align:left;color:#fff;background:#14584F;padding:9px 12px;white-space:nowrap}
td{padding:8px 12px;border-bottom:1px solid #EFE8DD;font-family:'JB';font-size:13.5px}
td.ch{text-align:right}
tr:last-child td{border-bottom:0}
tr:nth-child(even) td{background:#FBF8F3}
.pusto{padding:18px;color:#7C7166;font-style:italic}
.itog{margin-top:9px;font-size:14.5px;color:#6E6459}
.pod{margin-top:12px;font-size:15.5px;color:#6E6459;font-style:italic}
</style></head><body>
<div class="tit">{$titul}</div>
<div class="zapros">{$podsvechennyi}</div>
<div class="strelka">&darr;</div>
<div class="rez">
HTML;

$html .= $stroki
    ? "<table><thead><tr>$shapka_tablicy</tr></thead><tbody>$telo_tablicy</tbody></table>"
    : '<div class="pusto">Запрос выполнен, строк не вернулось</div>';

$html .= "</div><div class=\"itog\">Результат: {$vsego} {$slovo}</div>";
if ($podpis !== '') {
    $html .= '<div class="pod">' . $podpis . '</div>';
}
$html .= '</body></html>';

file_put_contents($vyhod, $html);
fwrite(STDERR, "строк в результате: $vsego\n");
