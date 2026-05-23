<?php
/**
 * Self-contained generator of the AutoDoc admin manual as a PDF.
 *
 * No external libraries: each A4 page is drawn with GD (text + vector
 * illustrations, Cyrillic via DejaVu Sans) and embedded into a minimal
 * PDF as a JPEG. Used by superadmin/manual.php (superadmin-only).
 */

class ManualPdf
{
    private int $W = 1240;          // A4 @ ~150 DPI
    private int $H = 1754;
    private int $margin = 90;
    private float $y = 0;
    private array $pages = [];      // finished GD images
    private $im = null;             // current page
    private int $pageNo = 0;

    private string $fReg;
    private string $fBold;
    private ?string $logo;

    // palette
    private array $C = [];

    public function __construct(string $fontReg, string $fontBold, ?string $logoPath = null)
    {
        $this->fReg  = $fontReg;
        $this->fBold = $fontBold;
        $this->logo  = ($logoPath && is_file($logoPath)) ? $logoPath : null;
    }

    private function color($im, array $rgb)
    {
        return imagecolorallocate($im, $rgb[0], $rgb[1], $rgb[2]);
    }

    private function newPage(bool $cover = false): void
    {
        if ($this->im) $this->pages[] = $this->im;
        $im = imagecreatetruecolor($this->W, $this->H);
        imagefill($im, 0, 0, imagecolorallocate($im, 255, 255, 255));
        $this->im = $im;
        $this->pageNo++;
        $this->y = $this->margin;
        if (!$cover) {
            // running footer
            $gray = imagecolorallocate($im, 170, 170, 170);
            $red  = imagecolorallocate($im, 199, 9, 9);
            imagefilledrectangle($im, 0, $this->H - 60, $this->W, $this->H - 58, imagecolorallocate($im, 235, 235, 235));
            $this->text($this->fReg, 11, $this->margin, $this->H - 32, 'AutoDoc · Конфиденциально — для сотрудников', $gray);
            $num = 'Стр. ' . $this->pageNo;
            $w = $this->textW($this->fReg, 11, $num);
            $this->text($this->fReg, 11, $this->W - $this->margin - $w, $this->H - 32, $num, $red);
        }
    }

    private function textW(string $font, float $size, string $s): float
    {
        $b = imagettfbbox($size, 0, $font, $s);
        return abs($b[2] - $b[0]);
    }

    private function text(string $font, float $size, float $x, float $y, string $s, $color): void
    {
        imagettftext($this->im, $size, 0, (int)$x, (int)$y, $color, $font, $s);
    }

    /** word-wrap a string to a given pixel width */
    private function wrap(string $font, float $size, string $s, float $maxW): array
    {
        $out = [];
        foreach (explode("\n", $s) as $para) {
            $words = preg_split('/\s+/', trim($para));
            $line = '';
            foreach ($words as $word) {
                $try = $line === '' ? $word : "$line $word";
                if ($this->textW($font, $size, $try) > $maxW && $line !== '') {
                    $out[] = $line;
                    $line = $word;
                } else {
                    $line = $try;
                }
            }
            $out[] = $line;
        }
        return $out;
    }

    private function ensure(float $need): void
    {
        if ($this->y + $need > $this->H - 90) $this->newPage();
    }

    // ─── public content API ───────────────────────────────────────────

    public function cover(string $title, string $subtitle, string $meta): void
    {
        $this->newPage(true);
        $im = $this->im;
        $red = $this->color($im, [199, 9, 9]);
        $dark = $this->color($im, [26, 10, 38]);
        $white = $this->color($im, [255, 255, 255]);
        $gray = $this->color($im, [120, 120, 120]);

        // top band
        imagefilledrectangle($im, 0, 0, $this->W, 24, $red);

        $cy = 230;
        if ($this->logo) {
            $src = @imagecreatefrompng($this->logo);
            if ($src) {
                $sw = imagesx($src); $sh = imagesy($src);
                $tw = 460; $th = (int)round($sh * ($tw / $sw));
                imagecopyresampled($im, $src, (int)(($this->W - $tw) / 2), $cy, 0, 0, $tw, $th, $sw, $sh);
                imagedestroy($src);
                $cy += $th + 80;
            }
        }

        // title
        $tw = $this->textW($this->fBold, 40, $title);
        $this->text($this->fBold, 40, ($this->W - $tw) / 2, $cy, $title, $dark);
        $cy += 70;
        $sw = $this->textW($this->fReg, 22, $subtitle);
        $this->text($this->fReg, 22, ($this->W - $sw) / 2, $cy, $subtitle, $red);
        $cy += 120;

        // confidential card
        imagefilledrectangle($im, $this->margin, $cy, $this->W - $this->margin, $cy + 150, $this->color($im, [248, 244, 240]));
        imagerectangle($im, $this->margin, $cy, $this->W - $this->margin, $cy + 150, $this->color($im, [225, 215, 205]));
        $this->text($this->fBold, 16, $this->margin + 30, $cy + 50, 'КОНФИДЕНЦИАЛЬНО', $red);
        foreach ($this->wrap($this->fReg, 14, 'Документ для внутреннего пользования. Суперадминистратор передаёт его сотрудникам для обучения работе с панелью управления магазином.', $this->W - 2 * $this->margin - 60) as $i => $ln) {
            $this->text($this->fReg, 14, $this->margin + 30, $cy + 85 + $i * 26, $ln, $this->color($im, [80, 80, 80]));
        }

        $mw = $this->textW($this->fReg, 13, $meta);
        $this->text($this->fReg, 13, ($this->W - $mw) / 2, $this->H - 120, $meta, $gray);

        imagefilledrectangle($im, 0, $this->H - 24, $this->W, $this->H, $red);

        // finalize cover, start fresh content page
        $this->newPage();
    }

    public function h1(string $s): void
    {
        $this->ensure(120);
        $this->y += 20;
        $im = $this->im;
        $red = $this->color($im, [199, 9, 9]);
        $dark = $this->color($im, [33, 33, 33]);
        imagefilledrectangle($im, $this->margin, (int)$this->y - 6, $this->margin + 10, (int)$this->y + 34, $red);
        $this->text($this->fBold, 28, $this->margin + 26, $this->y + 30, $s, $dark);
        $this->y += 56;
        imagefilledrectangle($im, $this->margin, (int)$this->y, $this->W - $this->margin, (int)$this->y + 2, $this->color($im, [230, 230, 230]));
        $this->y += 34;
    }

    public function h2(string $s): void
    {
        $this->ensure(80);
        $this->y += 16;
        $this->text($this->fBold, 19, $this->margin, $this->y + 22, $s, $this->color($this->im, [199, 9, 9]));
        $this->y += 46;
    }

    public function para(string $s): void
    {
        $lines = $this->wrap($this->fReg, 15, $s, $this->W - 2 * $this->margin);
        foreach ($lines as $ln) {
            $this->ensure(30);
            $this->text($this->fReg, 15, $this->margin, $this->y + 18, $ln, $this->color($this->im, [55, 55, 55]));
            $this->y += 30;
        }
        $this->y += 8;
    }

    public function bullet(string $s): void
    {
        $maxW = $this->W - 2 * $this->margin - 40;
        $lines = $this->wrap($this->fReg, 15, $s, $maxW);
        foreach ($lines as $i => $ln) {
            $this->ensure(30);
            if ($i === 0) {
                imagefilledellipse($this->im, $this->margin + 8, (int)$this->y + 12, 9, 9, $this->color($this->im, [199, 9, 9]));
            }
            $this->text($this->fReg, 15, $this->margin + 34, $this->y + 18, $ln, $this->color($this->im, [55, 55, 55]));
            $this->y += 30;
        }
    }

    public function step(int $n, string $title, string $body = ''): void
    {
        $this->ensure(70);
        $im = $this->im;
        $red = $this->color($im, [199, 9, 9]);
        $cx = $this->margin + 22; $cy = (int)$this->y + 20;
        imagefilledellipse($im, $cx, $cy, 44, 44, $red);
        $ns = (string)$n;
        $nw = $this->textW($this->fBold, 18, $ns);
        $this->text($this->fBold, 18, $cx - $nw / 2, $cy + 8, $ns, $this->color($im, [255, 255, 255]));
        $this->text($this->fBold, 16, $this->margin + 60, $this->y + 26, $title, $this->color($im, [33, 33, 33]));
        $this->y += 44;
        if ($body !== '') {
            foreach ($this->wrap($this->fReg, 14, $body, $this->W - 2 * $this->margin - 60) as $ln) {
                $this->ensure(26);
                $this->text($this->fReg, 14, $this->margin + 60, $this->y + 16, $ln, $this->color($im, [90, 90, 90]));
                $this->y += 26;
            }
        }
        $this->y += 14;
    }

    public function infoBox(string $title, string $body, string $kind = 'info'): void
    {
        $palettes = [
            'info'    => [[235, 245, 255], [120, 170, 220], [30, 90, 150]],
            'success' => [[235, 250, 238], [120, 200, 140], [30, 120, 60]],
            'warn'    => [[255, 248, 225], [220, 190, 110], [150, 110, 20]],
        ];
        $p = $palettes[$kind] ?? $palettes['info'];
        $body_lines = $this->wrap($this->fReg, 14, $body, $this->W - 2 * $this->margin - 60);
        $h = 54 + count($body_lines) * 24 + 20;
        $this->ensure($h + 20);
        $im = $this->im;
        $x1 = $this->margin; $x2 = $this->W - $this->margin;
        $y1 = (int)$this->y; $y2 = (int)($this->y + $h);
        imagefilledrectangle($im, $x1, $y1, $x2, $y2, $this->color($im, $p[0]));
        imagefilledrectangle($im, $x1, $y1, $x1 + 8, $y2, $this->color($im, $p[1]));
        $this->text($this->fBold, 15, $x1 + 30, $y1 + 34, $title, $this->color($im, $p[2]));
        $yy = $y1 + 64;
        foreach ($body_lines as $ln) {
            $this->text($this->fReg, 14, $x1 + 30, $yy, $ln, $this->color($im, [70, 70, 70]));
            $yy += 24;
        }
        $this->y = $y2 + 22;
    }

    /** Dark sidebar illustration with menu rows; $active = index to highlight */
    public function sidebarMock(array $items, int $active = 0, string $caption = ''): void
    {
        $rowH = 40; $padTop = 70;
        $h = $padTop + count($items) * $rowH + 30;
        $this->ensure($h + 40);
        $im = $this->im;
        $x1 = $this->margin + 60; $w = 360;
        $y1 = (int)$this->y;
        imagefilledrectangle($im, $x1, $y1, $x1 + $w, $y1 + $h, $this->color($im, [26, 10, 38]));
        // logo strip
        $this->text($this->fBold, 16, $x1 + 24, $y1 + 42, 'AutoDoc', $this->color($im, [252, 183, 0]));
        $yy = $y1 + $padTop;
        foreach ($items as $i => $it) {
            if ($i === $active) {
                imagefilledrectangle($im, $x1, $yy, $x1 + $w, $yy + $rowH - 6, $this->color($im, [199, 9, 9]));
            }
            $col = $i === $active ? [255, 255, 255] : [200, 190, 210];
            $this->text($this->fReg, 14, $x1 + 24, $yy + 27, '•  ' . $it, $this->color($im, $col));
            $yy += $rowH;
        }
        // caption to the right
        if ($caption !== '') {
            $cx = $x1 + $w + 40;
            foreach ($this->wrap($this->fReg, 14, $caption, $this->W - $cx - $this->margin) as $k => $ln) {
                $this->text($this->fReg, 14, $cx, $y1 + 90 + $k * 28, $ln, $this->color($im, [70, 70, 70]));
            }
        }
        $this->y = $y1 + $h + 24;
    }

    /** Row of button mockups: [['Сохранить','red'], ['Отмена','gray']] */
    public function buttonRow(array $buttons, string $caption = ''): void
    {
        $this->ensure(90);
        $im = $this->im;
        $x = $this->margin; $y1 = (int)$this->y; $bh = 52;
        foreach ($buttons as $b) {
            [$label, $kind] = [$b[0], $b[1] ?? 'red'];
            $bw = (int)$this->textW($this->fBold, 14, $label) + 56;
            $col = $kind === 'red' ? [199, 9, 9] : ($kind === 'green' ? [40, 160, 80] : [120, 120, 120]);
            imagefilledrectangle($im, $x, $y1, $x + $bw, $y1 + $bh, $this->color($im, $col));
            $this->text($this->fBold, 14, $x + 28, $y1 + 33, $label, $this->color($im, [255, 255, 255]));
            $x += $bw + 22;
        }
        $this->y = $y1 + $bh + 16;
        if ($caption !== '') $this->para($caption);
    }

    /** 2-column role cards: [['Суперадмин',[r,g,b],'desc'], ...] */
    public function roleCards(array $cards): void
    {
        $cols = 2; $gap = 24;
        $cw = (int)(($this->W - 2 * $this->margin - $gap) / $cols);
        $ch = 150;
        $i = 0;
        while ($i < count($cards)) {
            $this->ensure($ch + 20);
            $rowY = (int)$this->y;
            for ($c = 0; $c < $cols && $i < count($cards); $c++, $i++) {
                [$name, $rgb, $desc] = $cards[$i];
                $x1 = $this->margin + $c * ($cw + $gap);
                $im = $this->im;
                imagefilledrectangle($im, $x1, $rowY, $x1 + $cw, $rowY + 46, $this->color($im, $rgb));
                imagefilledrectangle($im, $x1, $rowY + 46, $x1 + $cw, $rowY + $ch, $this->color($im, [248, 248, 248]));
                imagerectangle($im, $x1, $rowY, $x1 + $cw, $rowY + $ch, $this->color($im, [225, 225, 225]));
                $this->text($this->fBold, 16, $x1 + 20, $rowY + 31, $name, $this->color($im, [255, 255, 255]));
                $yy = $rowY + 76;
                foreach ($this->wrap($this->fReg, 13, $desc, $cw - 40) as $ln) {
                    $this->text($this->fReg, 13, $x1 + 20, $yy, $ln, $this->color($im, [80, 80, 80]));
                    $yy += 22;
                }
            }
            $this->y = $rowY + $ch + $gap;
        }
    }

    public function table(array $headers, array $rows): void
    {
        $cols = count($headers);
        $tw = $this->W - 2 * $this->margin;
        $cw = (int)($tw / $cols);
        $rowH = 42;
        $this->ensure($rowH + 20);
        $im = $this->im;
        $x1 = $this->margin; $y1 = (int)$this->y;
        // header
        imagefilledrectangle($im, $x1, $y1, $x1 + $tw, $y1 + $rowH, $this->color($im, [33, 33, 33]));
        for ($c = 0; $c < $cols; $c++) {
            $this->text($this->fBold, 13, $x1 + $c * $cw + 14, $y1 + 28, $headers[$c], $this->color($im, [255, 255, 255]));
        }
        $y1 += $rowH;
        $alt = false;
        foreach ($rows as $row) {
            $this->ensure($rowH + 10);
            if ($this->y < $y1) { $y1 = (int)$this->y; }
            $bg = $alt ? [245, 245, 245] : [255, 255, 255];
            imagefilledrectangle($im, $x1, $y1, $x1 + $tw, $y1 + $rowH, $this->color($im, $bg));
            imagerectangle($im, $x1, $y1, $x1 + $tw, $y1 + $rowH, $this->color($im, [225, 225, 225]));
            for ($c = 0; $c < $cols; $c++) {
                $cell = (string)($row[$c] ?? '');
                $this->text($this->fReg, 13, $x1 + $c * $cw + 14, $y1 + 27, $cell, $this->color($im, [60, 60, 60]));
            }
            $y1 += $rowH;
            $this->y = $y1;
            $alt = !$alt;
        }
        $this->y += 18;
    }

    public function spacer(float $px): void { $this->y += $px; }

    public string $debugDir = '';

    public function save(string $path): void
    {
        if ($this->im) { $this->pages[] = $this->im; $this->im = null; }

        if ($this->debugDir) {
            foreach ($this->pages as $k => $img) {
                imagepng($img, rtrim($this->debugDir, '/') . '/page_' . ($k + 1) . '.png');
            }
        }

        $objs = [];
        $add = function (string $body) use (&$objs) { $objs[] = $body; return count($objs); };

        $catalog = $add(''); // placeholder 1
        $pagesObj = $add(''); // placeholder 2
        $kids = [];

        foreach ($this->pages as $img) {
            ob_start(); imagejpeg($img, null, 82); $jpg = ob_get_clean(); imagedestroy($img);
            $imgId = $add("<< /Type /XObject /Subtype /Image /Width {$this->W} /Height {$this->H} "
                . "/ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter /DCTDecode /Length " . strlen($jpg)
                . " >>\nstream\n" . $jpg . "\nendstream");
            // draw the full-page image scaled to A4 points (595x842)
            $content = "q 595 0 0 842 0 0 cm /Im0 Do Q";
            $contentId = $add("<< /Length " . strlen($content) . " >>\nstream\n" . $content . "\nendstream");
            $pageId = $add("<< /Type /Page /Parent {$pagesObj} 0 R /MediaBox [0 0 595 842] "
                . "/Resources << /XObject << /Im0 {$imgId} 0 R >> >> /Contents {$contentId} 0 R >>");
            $kids[] = "{$pageId} 0 R";
        }

        $objs[$catalog - 1] = "<< /Type /Catalog /Pages {$pagesObj} 0 R >>";
        $objs[$pagesObj - 1] = "<< /Type /Pages /Kids [" . implode(' ', $kids) . "] /Count " . count($kids) . " >>";

        $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];
        foreach ($objs as $i => $body) {
            $offsets[$i + 1] = strlen($pdf);
            $pdf .= ($i + 1) . " 0 obj\n" . $body . "\nendobj\n";
        }
        $xref = strlen($pdf);
        $n = count($objs) + 1;
        $pdf .= "xref\n0 {$n}\n0000000000 65535 f \n";
        for ($i = 1; $i < $n; $i++) $pdf .= sprintf("%010d 00000 n \n", $offsets[$i]);
        $pdf .= "trailer\n<< /Size {$n} /Root {$catalog} 0 R >>\nstartxref\n{$xref}\n%%EOF";

        if (!is_dir(dirname($path))) @mkdir(dirname($path), 0775, true);
        file_put_contents($path, $pdf);
    }
}

/**
 * Build the full AutoDoc manual and write it to $outPath.
 * Returns the path on success.
 */
function autodoc_generate_manual(string $outPath, string $debugDir = ''): string
{
    $reg  = '/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf';
    $bold = '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    $logo = defined('APP_ROOT') ? APP_ROOT . '/logo.png' : __DIR__ . '/../logo.png';

    $d = new ManualPdf($reg, $bold, $logo);
    if ($debugDir) $d->debugDir = $debugDir;

    $d->cover('Руководство пользователя', 'Панель управления магазином AutoDoc',
        'Сгенерировано: ' . date('d.m.Y') . '  ·  autodoc.tj');

    // ── Введение
    $d->h1('1. О системе');
    $d->para('AutoDoc — интернет-магазин автозапчастей. Этот документ объясняет сотрудникам, как пользоваться панелью управления: добавлять товары, оформлять витрину (слайдер и баннеры), обрабатывать заказы и управлять контентом.');
    $d->infoBox('Кому предназначено', 'Руководство выдаётся суперадминистратором сотрудникам (администраторам и менеджерам). Объём прав каждого сотрудника настраивается индивидуально.', 'info');

    // ── Роли
    $d->h1('2. Роли и уровни доступа');
    $d->para('В системе четыре роли. Каждая видит только свои разделы.');
    $d->roleCards([
        ['Суперадмин', [44, 19, 56], 'Полный доступ. Управляет пользователями, правами, настройками, валютами, языками и этим руководством.'],
        ['Администратор', [199, 9, 9], 'Товары, слайдер, баннеры, заказы, пользователи. Точный набор задаёт суперадмин.'],
        ['Менеджер', [33, 33, 33], 'Каталог: запчасти, категории, бренды, блог. Без доступа к заказам и настройкам.'],
        ['Покупатель', [40, 120, 180], 'Клиент магазина: корзина, избранное, оформление и история заказов.'],
    ]);
    $d->h2('Кто что может');
    $d->table(['Раздел', 'Суперадмин', 'Админ', 'Менеджер'], [
        ['Товары', 'да', 'да', 'да'],
        ['Слайдер и баннеры', 'да', 'да', '—'],
        ['Заказы', 'да', 'да', '—'],
        ['Пользователи', 'да', 'да', '—'],
        ['Права доступа', 'да', '—', '—'],
        ['Настройки / Валюты', 'да', '—', '—'],
    ]);

    // ── Вход
    $d->h1('3. Вход в систему');
    $d->step(1, 'Откройте страницу входа', 'В адресной строке: autodoc.tj/auth/login.php');
    $d->step(2, 'Введите логин и пароль', 'Данные выдаёт суперадминистратор. Никому не передавайте свой пароль.');
    $d->step(3, 'Нажмите «Войти»', 'После входа слева появится меню — набор пунктов зависит от вашей роли.');
    $d->infoBox('Безопасность', 'Заканчивая работу на чужом или общем компьютере, всегда нажимайте «Выйти» внизу бокового меню.', 'warn');

    // ── Панель обзор
    $d->h1('4. Боковое меню панели');
    $d->para('Слева расположено меню. Активный раздел подсвечен красным. Внизу — ссылки «На сайт» и «Выйти».');
    $d->sidebarMock(
        ['Панель', 'Товары', 'Слайдер', 'Баннеры', 'Заказы', 'Пользователи'],
        3,
        'Пример меню администратора. Пункт «Баннеры» подсвечен — значит вы сейчас в нём. У менеджера список короче, у суперадмина — длиннее.'
    );

    // ── Товары
    $d->h1('5. Товары');
    $d->para('Раздел «Товары» — это каталог магазина. Здесь добавляют и редактируют запчасти.');
    $d->step(1, 'Откройте «Товары» и нажмите «Добавить»', 'Кнопка добавления находится вверху справа.');
    $d->step(2, 'Заполните поля', 'Название, артикул, бренд, категория, цена, количество на складе.');
    $d->step(3, 'Загрузите фото', 'Нажмите область загрузки и выберите изображение (JPG/PNG/WebP).');
    $d->step(4, 'Сохраните', 'Товар сразу появится в каталоге, если отмечен как активный.');
    $d->buttonRow([['Сохранить', 'red'], ['Отмена', 'gray']], 'Внизу формы — кнопка сохранения (красная) и отмены (серая).');

    // ── Слайдер
    $d->h1('6. Слайдер главной страницы');
    $d->para('Слайдер — это большие сменяющиеся изображения вверху главной. Управляется в разделе «Слайдер».');
    $d->step(1, 'Добавьте слайд', 'Загрузите изображение, при желании укажите заголовок и подзаголовок.');
    $d->step(2, 'Укажите ссылку', 'Куда ведёт кнопка на слайде, например /catalog/index.php');
    $d->step(3, 'Задайте порядок', 'Меньшее число — раньше в показе.');
    $d->infoBox('Скрыть, не удаляя', 'Кнопка с глазом временно прячет слайд. Удаление — корзина — убирает его навсегда.', 'info');

    // ── Баннеры (новое)
    $d->h1('7. Баннеры (новый раздел)');
    $d->para('Под слайдером на главной показываются три рекламных баннера. Раньше они были «зашиты» в шаблон — теперь ими управляют из панели в разделе «Баннеры».');
    $d->step(1, 'Откройте «Баннеры»', 'Пункт меню расположен сразу под «Слайдер».');
    $d->step(2, 'Нажмите «Добавить баннер»', 'Рекомендуемый размер изображения ~570×320 пикселей (горизонтальный).');
    $d->step(3, 'Загрузите картинку и ссылку', 'Ссылка — куда переходит покупатель по клику на баннер.');
    $d->step(4, 'Сохраните и проверьте главную', 'Показываются первые три активных баннера по возрастанию порядка.');
    $d->infoBox('Если баннеров нет', 'Когда в разделе нет ни одного активного баннера, на сайте показываются стандартные изображения из шаблона. Так главная никогда не остаётся пустой.', 'success');

    // ── Заказы
    $d->h1('8. Заказы');
    $d->para('В разделе «Заказы» видны все покупки. Откройте заказ, чтобы увидеть состав, сумму, контакты и адрес доставки.');
    $d->h2('Статусы заказа');
    $d->table(['Статус', 'Значение'], [
        ['Новый', 'Только что оформлен, ждёт обработки'],
        ['В обработке', 'Принят в работу, комплектуется'],
        ['Отправлен', 'Передан в доставку'],
        ['Доставлен', 'Получен покупателем'],
        ['Отменён', 'Заказ отменён'],
    ]);
    $d->step(1, 'Выберите новый статус', 'В карточке заказа есть выпадающий список статусов.');
    $d->step(2, 'Нажмите «Сохранить»', 'Статус обновится, покупатель видит актуальное состояние.');
    $d->infoBox('Адрес доставки', 'Адрес теперь показывается удобно: имя, телефон, e-mail и сам адрес отдельными строками (раньше выводился техническим текстом).', 'success');

    // ── Права
    $d->h1('9. Права доступа (для суперадмина)');
    $d->para('Суперадмин в разделе «Права доступа» задаёт каждому сотруднику список разрешённых разделов. Невыбранные разделы исчезают из его меню.');
    $d->bullet('По умолчанию администратор и менеджер видят свой стандартный набор разделов.');
    $d->bullet('Сняв галочку с раздела, вы мгновенно убираете к нему доступ у сотрудника.');
    $d->bullet('Суперадмин всегда имеет полный доступ ко всему.');

    // ── Советы
    $d->h1('10. Частые вопросы');
    $d->h2('Я не вижу нужный раздел в меню');
    $d->para('Доступ ограничен суперадмином. Обратитесь к нему, чтобы он включил раздел в «Права доступа».');
    $d->h2('Загруженное фото не отображается');
    $d->para('Проверьте формат (JPG, PNG, WebP) и размер файла. Слишком большие изображения лучше уменьшить заранее.');
    $d->h2('Изменения не видны на сайте');
    $d->para('Убедитесь, что элемент активен (включён), и обновите страницу сайта (Ctrl+F5).');

    $d->infoBox('Поддержка', 'По вопросам работы панели обращайтесь к суперадминистратору магазина AutoDoc.', 'info');

    $d->save($outPath);
    return $outPath;
}
