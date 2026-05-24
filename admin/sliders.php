<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'manager', 'superadmin']);
requirePermission('sliders');

$db   = getDB();
$csrf = generateCsrfToken();

// ── Ensure sliders table exists ───────────────────────────────────────
$db->exec("CREATE TABLE IF NOT EXISTS `sliders` (
  `id`               INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `title`            VARCHAR(255)  NOT NULL DEFAULT '',
  `title_highlight`  VARCHAR(255)  NOT NULL DEFAULT '',
  `subtitle`         VARCHAR(255)  NOT NULL DEFAULT '',
  `image_url`        VARCHAR(500)  NOT NULL DEFAULT '',
  `image_url_mobile` VARCHAR(500)  NOT NULL DEFAULT '',
  `link_url`         VARCHAR(500)  NOT NULL DEFAULT '',
  `sort_order`       SMALLINT      NOT NULL DEFAULT 0,
  `is_active`        TINYINT(1)    NOT NULL DEFAULT 1,
  `created_at`       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Add columns to pre-existing tables (portable across MariaDB dev / MySQL 8.0 prod).
dbAddColumnIfMissing($db, 'sliders', 'image_url_mobile', "`image_url_mobile` VARCHAR(500) NOT NULL DEFAULT '' AFTER `image_url`");
dbAddColumnIfMissing($db, 'sliders', 'title_highlight',  "`title_highlight` VARCHAR(255) NOT NULL DEFAULT '' AFTER `title`");
dbAddColumnIfMissing($db, 'sliders', 'text_blocks',      "`text_blocks` TEXT NULL AFTER `subtitle`");

// ── POST handler ──────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/admin/sliders.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'delete') {
        $delId = (int)($_POST['id'] ?? 0);
        if ($delId) $db->prepare("DELETE FROM sliders WHERE id = ?")->execute([$delId]);
        flashMessage('success', 'Слайд удалён.');
        redirect(APP_URL . '/admin/sliders.php');
    }

    if ($postAction === 'toggle') {
        $tid = (int)($_POST['id'] ?? 0);
        $db->prepare("UPDATE sliders SET is_active = NOT is_active WHERE id = ?")->execute([$tid]);
        redirect(APP_URL . '/admin/sliders.php');
    }

    if ($postAction === 'reorder') {
        $order = json_decode($_POST['order'] ?? '[]', true) ?: [];
        foreach ($order as $sort => $sid) {
            $db->prepare("UPDATE sliders SET sort_order = ? WHERE id = ?")->execute([(int)$sort, (int)$sid]);
        }
        echo json_encode(['ok' => true]);
        exit;
    }

    // Save (add / edit)
    $sid       = (int)($_POST['id'] ?? 0);
    $imgUrl    = trim($_POST['image_url'] ?? '');
    $imgMobile = trim($_POST['image_url_mobile'] ?? '');
    $linkUrl   = trim($_POST['link_url'] ?? '');
    $sort      = (int)($_POST['sort_order'] ?? 0);

    // Build text blocks from the parallel form arrays.
    $rawBlocks = [];
    $blkText = $_POST['blk_text'] ?? [];
    if (is_array($blkText)) {
        foreach ($blkText as $i => $txt) {
            $rawBlocks[] = [
                'text'   => $txt,
                'size'   => $_POST['blk_size'][$i]   ?? 24,
                'weight' => $_POST['blk_weight'][$i] ?? '400',
                'color'  => $_POST['blk_color'][$i]  ?? '#ffffff',
                'font'   => $_POST['blk_font'][$i]   ?? '',
                'mb'     => $_POST['blk_mb'][$i]      ?? 10,
            ];
        }
    }
    $blocks     = normalizeSliderBlocks($rawBlocks);
    $textBlocks = $blocks ? json_encode($blocks, JSON_UNESCAPED_UNICODE) : '';
    $title      = $blocks[0]['text'] ?? '';   // first block doubles as the list-preview title

    if ($imgUrl === '' && $imgMobile === '') {
        flashMessage('danger', 'Загрузите хотя бы одно изображение (десктоп или мобильное).');
        redirect(APP_URL . '/admin/sliders.php' . ($sid ? "?edit=$sid" : '?action=new'));
    }

    if ($sid) {
        $db->prepare(
            "UPDATE sliders SET title=?, title_highlight='', subtitle='', text_blocks=?, image_url=?, image_url_mobile=?, link_url=?, sort_order=? WHERE id=?"
        )->execute([$title, $textBlocks, $imgUrl, $imgMobile, $linkUrl, $sort, $sid]);
        flashMessage('success', 'Слайд обновлён.');
    } else {
        $db->prepare(
            "INSERT INTO sliders (title, text_blocks, image_url, image_url_mobile, link_url, sort_order, is_active) VALUES (?,?,?,?,?,?,1)"
        )->execute([$title, $textBlocks, $imgUrl, $imgMobile, $linkUrl, $sort]);
        flashMessage('success', 'Слайд добавлен.');
    }
    redirect(APP_URL . '/admin/sliders.php');
}

// Load edit
$editSlide = null;
$action    = $_GET['action'] ?? 'list';
$editId    = (int)($_GET['edit'] ?? 0);
if ($editId) {
    $stmt = $db->prepare("SELECT * FROM sliders WHERE id = ? LIMIT 1");
    $stmt->execute([$editId]);
    $editSlide = $stmt->fetch();
    $action = 'edit';
}
if ($_GET['action'] ?? '' === 'new') $action = 'new';

$sliders = $db->query("SELECT * FROM sliders ORDER BY sort_order ASC, id ASC")->fetchAll();

// ── Initial text blocks for the editor ────────────────────────────────
// Prefer saved JSON; otherwise migrate legacy title/highlight/subtitle so
// existing slides open with their text already populated. New slides get
// one empty starter block.
$initialBlocks = [];
if ($editSlide && !empty($editSlide['text_blocks'])) {
    $decoded = json_decode($editSlide['text_blocks'], true);
    if (is_array($decoded)) $initialBlocks = normalizeSliderBlocks($decoded);
}
if (!$initialBlocks && $editSlide) {
    if (!empty($editSlide['title']))           $initialBlocks[] = ['text'=>$editSlide['title'],           'size'=>30, 'weight'=>'400', 'color'=>'#ffffff', 'font'=>'', 'mb'=>6];
    if (!empty($editSlide['title_highlight'])) $initialBlocks[] = ['text'=>$editSlide['title_highlight'], 'size'=>60, 'weight'=>'800', 'color'=>'#ffffff', 'font'=>'', 'mb'=>22];
    if (!empty($editSlide['subtitle']))        $initialBlocks[] = ['text'=>$editSlide['subtitle'],        'size'=>22, 'weight'=>'400', 'color'=>'#ffffff', 'font'=>'', 'mb'=>30];
}
if (!$initialBlocks) {
    $initialBlocks[] = ['text'=>'', 'size'=>30, 'weight'=>'400', 'color'=>'#ffffff', 'font'=>'', 'mb'=>6];
    $initialBlocks[] = ['text'=>'', 'size'=>60, 'weight'=>'800', 'color'=>'#ffffff', 'font'=>'', 'mb'=>22];
}

$pageTitle = 'Слайдер — Администратор';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="az-panel">
    <?php renderRoleSidebar('sliders'); ?>

    <main class="az-main">
        <div class="az-topbar">
            <h1>Управление слайдером главной страницы</h1>
            <span style="font-size:0.85rem;color:#666;"><?= sanitize($_SESSION['username'] ?? '') ?></span>
        </div>

        <div class="az-content">

            <?php if ($flash = getFlashMessage()): ?>
                <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <?php if (in_array($action, ['new', 'edit'])): ?>
            <link rel="stylesheet" href="<?= sanitize(sliderFontsGoogleUrl()) ?>">
            <!-- ── Form ──────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.1rem;"><?= $action === 'edit' ? 'Редактировать слайд' : 'Новый слайд' ?></h2>
                <a href="<?= APP_URL ?>/admin/sliders.php" class="az-btn az-btn-secondary az-btn-sm">← Список</a>
            </div>

            <div style="max-width:880px;">
                <form method="POST" action="" id="sliderForm">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                    <input type="hidden" name="action" value="<?= $action === 'edit' ? 'edit' : 'add' ?>">
                    <?php if ($editSlide): ?><input type="hidden" name="id" value="<?= (int)$editSlide['id'] ?>"><?php endif; ?>

                    <div class="az-alert az-alert-info" style="font-size:0.83rem;">
                        <i class="fa fa-info-circle"></i> Загрузите хотя бы одну версию. Если мобильная не задана —
                        на телефонах покажется десктопная (и наоборот).
                    </div>

                    <div class="az-card">
                        <h3><i class="fa fa-desktop"></i> Версия для десктопа</h3>
                        <div id="dtPreviewWrap" style="margin-bottom:12px;">
                            <?php if ($editSlide && !empty($editSlide['image_url'])): ?>
                                <img src="<?= sanitize($editSlide['image_url']) ?>" id="dtPreview"
                                     style="max-width:100%;max-height:220px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                            <?php else: ?>
                                <div id="dtPlaceholder"
                                     style="width:100%;height:160px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:2.5rem;">
                                    <i class="fa fa-image"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.875rem;color:#555;">
                            <i class="fa fa-upload"></i> Загрузить изображение
                            <input type="file" accept="image/*" style="display:none;"
                                   onchange="uploadSliderImg(this, 'imageUrl', 'dtPreview', 'dtPlaceholder', 'dtPreviewWrap', 'dtStatus')">
                        </label>
                        <span id="dtStatus" style="font-size:0.8rem;color:#888;margin-left:8px;"></span>
                        <div style="margin-top:10px;padding:10px 12px;background:#eef6ff;border:1px solid #cfe4fb;border-radius:6px;font-size:0.78rem;color:#1c5a99;line-height:1.55;">
                            <i class="fa fa-info-circle"></i> <strong>Рекомендуемый размер:</strong> 1920&times;600&nbsp;px (широкий горизонтальный).<br>
                            <span style="color:#5a87b3;">Обрезается по центру — важный объект держите по центру.</span>
                        </div>
                        <input type="hidden" name="image_url" id="imageUrl"
                               value="<?= sanitize($editSlide['image_url'] ?? '') ?>">
                    </div>

                    <div class="az-card">
                        <h3><i class="fa fa-mobile"></i> Версия для мобильного</h3>
                        <div id="mbPreviewWrap" style="margin-bottom:12px;">
                            <?php if ($editSlide && !empty($editSlide['image_url_mobile'])): ?>
                                <img src="<?= sanitize($editSlide['image_url_mobile']) ?>" id="mbPreview"
                                     style="max-width:100%;max-height:280px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;">
                            <?php else: ?>
                                <div id="mbPlaceholder"
                                     style="width:100%;height:160px;background:#f5f5f5;border:2px dashed #ced4da;border-radius:6px;display:flex;align-items:center;justify-content:center;color:#bbb;font-size:2.5rem;">
                                    <i class="fa fa-mobile"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        <label style="display:inline-flex;align-items:center;gap:8px;padding:9px 16px;background:#f8f9fa;border:2px dashed #ced4da;border-radius:6px;cursor:pointer;font-size:0.875rem;color:#555;">
                            <i class="fa fa-upload"></i> Загрузить изображение
                            <input type="file" accept="image/*" style="display:none;"
                                   onchange="uploadSliderImg(this, 'imageUrlMobile', 'mbPreview', 'mbPlaceholder', 'mbPreviewWrap', 'mbStatus')">
                        </label>
                        <span id="mbStatus" style="font-size:0.8rem;color:#888;margin-left:8px;"></span>
                        <div style="margin-top:10px;padding:10px 12px;background:#eef6ff;border:1px solid #cfe4fb;border-radius:6px;font-size:0.78rem;color:#1c5a99;line-height:1.55;">
                            <i class="fa fa-info-circle"></i> <strong>Рекомендуемый размер:</strong> ~768&times;900&nbsp;px (вертикальный/квадратный).
                        </div>
                        <input type="hidden" name="image_url_mobile" id="imageUrlMobile"
                               value="<?= sanitize($editSlide['image_url_mobile'] ?? '') ?>">
                    </div>

                    <div class="az-card">
                        <h3><i class="fa fa-eye"></i> Предпросмотр (как будет на сайте)</h3>
                        <div id="slPreview" class="sl-preview">
                            <div id="slPreviewFrame">
                                <div id="slPreviewContent"></div>
                                <span class="sl-preview-btn"><?= t('shop') ?> &raquo;</span>
                            </div>
                        </div>
                        <small style="color:#888;">Пиксель-в-пиксель: фрейм рендерится в реальных 1140 px и масштабируется — перенос слов идентичен сайту.</small>
                    </div>

                    <div class="az-card">
                        <h3><i class="fa fa-font"></i> Текстовые блоки</h3>
                        <p style="font-size:0.83rem;color:#666;margin-top:0;">
                            Добавьте сколько угодно строк (заголовки и подзаголовки). Для каждой настройте
                            размер, жирность, цвет, шрифт и отступ снизу. Порядок — стрелками.
                        </p>
                        <div id="blocksContainer"></div>
                        <button type="button" class="az-btn az-btn-secondary az-btn-sm" id="addBlockBtn">
                            <i class="fa fa-plus"></i> Добавить блок
                        </button>
                    </div>

                    <div class="az-card">
                        <h3>Кнопка и порядок</h3>
                        <div class="az-form-group">
                            <label>Ссылка кнопки (URL)</label>
                            <input type="text" name="link_url"
                                   value="<?= sanitize($editSlide['link_url'] ?? '') ?>"
                                   placeholder="/shop.php">
                        </div>
                        <div class="az-form-group">
                            <label>Порядок сортировки</label>
                            <input type="number" name="sort_order" min="0"
                                   value="<?= (int)($editSlide['sort_order'] ?? 0) ?>"
                                   style="max-width:100px;">
                        </div>
                    </div>

                    <div style="display:flex;gap:10px;">
                        <button type="submit" class="az-btn az-btn-primary">
                            <i class="fa fa-save"></i> <?= $action === 'edit' ? 'Сохранить' : 'Добавить слайд' ?>
                        </button>
                        <a href="<?= APP_URL ?>/admin/sliders.php" class="az-btn az-btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>

            <script>
            async function uploadSliderImg(input, fieldId, previewId, placeholderId, wrapId, statusId) {
                const status = document.getElementById(statusId);
                if (!input.files || !input.files[0]) return;
                const fd = new FormData();
                fd.append('file', input.files[0]);
                status.textContent = 'Загрузка...';
                try {
                    const res  = await fetch('<?= APP_URL ?>/api/upload.php?type=sliders', { method:'POST', body:fd });
                    const data = await res.json();
                    if (data.url) {
                        document.getElementById(fieldId).value = data.url;
                        const prev = document.getElementById(previewId);
                        const ph   = document.getElementById(placeholderId);
                        if (prev) { prev.src = data.url; }
                        else {
                            if (ph) ph.remove();
                            const img = document.createElement('img');
                            img.id = previewId;
                            img.src = data.url;
                            img.style.cssText = 'max-width:100%;max-height:280px;object-fit:cover;border-radius:6px;border:1px solid #dee2e6;';
                            document.getElementById(wrapId).appendChild(img);
                        }
                        status.textContent = 'Загружено';
                        if (fieldId === 'imageUrl' && window.renderSlPreview) window.renderSlPreview();
                    } else {
                        status.textContent = data.error || 'Ошибка';
                    }
                } catch (e) {
                    status.textContent = 'Ошибка сети';
                }
                input.value = '';
            }
            </script>

            <style>
            /* Preview outer: just a clipping viewport — height set by JS */
            .sl-preview { width: 100%; overflow: hidden; border-radius: 8px; border: 1px solid #dee2e6; margin-bottom: 10px; }
            /* Real 1140 × 420 frame, scaled down via transform: scale() in JS so
               text wraps exactly like on the site (pixel-accurate preview). */
            #slPreviewFrame {
                width: 1140px; height: 420px; transform-origin: top left;
                /* --bg is updated by JS when a desktop image is uploaded */
                background: linear-gradient(90deg,rgba(0,0,0,.45) 0%,rgba(0,0,0,.12) 45%,rgba(0,0,0,0) 75%),
                            var(--bg, #14171c) center / cover no-repeat;
                display: flex; flex-direction: column; align-items: flex-start;
                justify-content: center; padding-left: 80px;
            }
            .sl-preview-block { line-height: 1.05; text-shadow: 0 2px 14px rgba(0,0,0,.5); word-break: break-word; }
            .sl-preview-btn {
                display: inline-block; margin-top: 10px; background: #C70909; color: #fff;
                font-weight: 500; border-radius: 4px; padding: 10px 22px; font-size: 14px;
            }
            .blk-row { border: 1px solid #e3e6ea; border-radius: 8px; padding: 12px; margin-bottom: 12px; background: #fafbfc; }
            .blk-row-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
            .blk-row-head .blk-num { font-weight: 700; font-size: 0.8rem; color: #888; }
            .blk-row-head .blk-acts button { border: none; background: #eef0f3; border-radius: 5px; width: 28px; height: 28px; cursor: pointer; margin-left: 4px; color: #555; }
            .blk-row-head .blk-acts button:hover { background: #e0e3e8; }
            .blk-row-head .blk-acts button.blk-del:hover { background: #f8d7da; color: #c0202f; }
            .blk-row .blk-text { width: 100%; margin-bottom: 10px; }
            .blk-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 10px; }
            .blk-grid label { display: block; font-size: 0.72rem; font-weight: 600; color: #666; margin-bottom: 3px; }
            .blk-grid input, .blk-grid select { width: 100%; }
            .blk-grid input[type=color] { height: 34px; padding: 2px; }
            </style>

            <script>
            window.SL_FONTS      = <?= json_encode(sliderFonts(), JSON_UNESCAPED_UNICODE) ?>;
            window.SL_WEIGHTS    = <?= json_encode(sliderWeights(), JSON_UNESCAPED_UNICODE) ?>;
            window.SL_FONT_STACK = <?= json_encode((function(){ $m=[]; foreach(array_keys(sliderFonts()) as $f){ $m[$f]=sliderFontStack($f); } return $m; })(), JSON_UNESCAPED_UNICODE) ?>;
            window.SL_INIT       = <?= json_encode($initialBlocks, JSON_UNESCAPED_UNICODE) ?>;

            (function () {
                const container = document.getElementById('blocksContainer');
                const preview   = document.getElementById('slPreview');
                const frame     = document.getElementById('slPreviewFrame');
                const pvContent = document.getElementById('slPreviewContent');

                function optionsHtml(map, selected) {
                    return Object.keys(map).map(function (k) {
                        const sel = (String(k) === String(selected)) ? ' selected' : '';
                        return '<option value="' + k + '"' + sel + '>' + map[k] + '</option>';
                    }).join('');
                }

                function makeRow(b) {
                    b = b || { text: '', size: 30, weight: '400', color: '#ffffff', font: '', mb: 10 };
                    const row = document.createElement('div');
                    row.className = 'blk-row';
                    row.innerHTML =
                        '<div class="blk-row-head">' +
                            '<span class="blk-num">Блок</span>' +
                            '<span class="blk-acts">' +
                                '<button type="button" class="blk-up"   title="Выше">&#9650;</button>' +
                                '<button type="button" class="blk-down" title="Ниже">&#9660;</button>' +
                                '<button type="button" class="blk-del"  title="Удалить">&times;</button>' +
                            '</span>' +
                        '</div>' +
                        '<input type="text" class="blk-text" name="blk_text[]" placeholder="Текст строки" value="">' +
                        '<div class="blk-grid">' +
                            '<div><label>Размер, px</label><input type="number" min="8" max="200" name="blk_size[]"></div>' +
                            '<div><label>Жирность</label><select name="blk_weight[]">' + optionsHtml(window.SL_WEIGHTS, b.weight) + '</select></div>' +
                            '<div><label>Цвет</label><input type="color" name="blk_color[]"></div>' +
                            '<div><label>Шрифт</label><select name="blk_font[]">' + optionsHtml(window.SL_FONTS, b.font) + '</select></div>' +
                            '<div><label>Отступ снизу, px</label><input type="number" min="0" max="160" name="blk_mb[]"></div>' +
                        '</div>';
                    row.querySelector('.blk-text').value  = b.text || '';
                    row.querySelector('[name="blk_size[]"]').value  = b.size;
                    row.querySelector('[name="blk_color[]"]').value = b.color || '#ffffff';
                    row.querySelector('[name="blk_mb[]"]').value    = b.mb;
                    row.addEventListener('input', renderPreview);
                    row.querySelector('.blk-del').addEventListener('click', function () { row.remove(); renumber(); renderPreview(); });
                    row.querySelector('.blk-up').addEventListener('click', function () {
                        if (row.previousElementSibling) container.insertBefore(row, row.previousElementSibling);
                        renumber(); renderPreview();
                    });
                    row.querySelector('.blk-down').addEventListener('click', function () {
                        if (row.nextElementSibling) container.insertBefore(row.nextElementSibling, row);
                        renumber(); renderPreview();
                    });
                    return row;
                }

                function renumber() {
                    container.querySelectorAll('.blk-row .blk-num').forEach(function (el, i) { el.textContent = 'Блок ' + (i + 1); });
                }

                function renderPreview() {
                    const url = (document.getElementById('imageUrl') || {}).value || '';
                    // background image via CSS custom property so it layers on top of the gradient
                    frame.style.setProperty('--bg', url ? 'url("' + url + '")' : '#14171c');
                    // scale the whole 1140×420 frame down to the container width (pixel-perfect)
                    const scale = Math.max(0.05, preview.clientWidth / 1140);
                    frame.style.transform = 'scale(' + scale + ')';
                    preview.style.height  = (420 * scale) + 'px';
                    pvContent.innerHTML = '';
                    container.querySelectorAll('.blk-row').forEach(function (row) {
                        const text = row.querySelector('.blk-text').value;
                        if (!text.trim()) return;
                        // Use REAL pixel sizes — frame is already scaled, so proportions match the site exactly
                        const size   = parseInt(row.querySelector('[name="blk_size[]"]').value, 10)  || 24;
                        const mb     = parseInt(row.querySelector('[name="blk_mb[]"]').value, 10)    || 0;
                        const weight = row.querySelector('[name="blk_weight[]"]').value;
                        const color  = row.querySelector('[name="blk_color[]"]').value;
                        const font   = row.querySelector('[name="blk_font[]"]').value;
                        const div = document.createElement('div');
                        div.className = 'sl-preview-block';
                        div.textContent = text;
                        div.style.fontSize     = size + 'px';
                        div.style.marginBottom = mb + 'px';
                        div.style.fontWeight   = weight;
                        div.style.color        = color;
                        const stack = window.SL_FONT_STACK[font];
                        if (stack) div.style.fontFamily = stack;
                        pvContent.appendChild(div);
                    });
                }
                window.renderSlPreview = renderPreview;

                (window.SL_INIT && window.SL_INIT.length ? window.SL_INIT : [null]).forEach(function (b) {
                    container.appendChild(makeRow(b));
                });
                renumber();
                renderPreview();

                document.getElementById('addBlockBtn').addEventListener('click', function () {
                    container.appendChild(makeRow());
                    renumber();
                    renderPreview();
                });
                window.addEventListener('resize', renderPreview);
            })();
            </script>

            <?php else: ?>
            <!-- ── List ──────────────────────────────────────────── -->
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                <h2 style="margin:0;font-size:1.1rem;">Слайды (<?= count($sliders) ?> шт.)</h2>
                <a href="?action=new" class="az-btn az-btn-primary">
                    <i class="fa fa-plus"></i> Добавить слайд
                </a>
            </div>

            <?php if (empty($sliders)): ?>
                <div class="az-card" style="text-align:center;padding:48px;color:#aaa;">
                    <i class="fa fa-picture-o" style="font-size:3rem;display:block;margin-bottom:12px;color:#ddd;"></i>
                    Слайдов ещё нет. <a href="?action=new" style="color:#d32f2f;">Добавить первый</a>
                </div>
            <?php else: ?>
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;">
                    <?php foreach ($sliders as $slide): ?>
                    <?php $previewImg = $slide['image_url'] ?: ($slide['image_url_mobile'] ?? ''); ?>
                    <div class="az-card" style="padding:0;overflow:hidden;<?= !$slide['is_active'] ? 'opacity:0.55;' : '' ?>">
                        <div style="position:relative;height:160px;background:#f5f5f5;">
                            <?php if ($previewImg): ?>
                                <img src="<?= sanitize($previewImg) ?>" alt=""
                                     style="width:100%;height:100%;object-fit:cover;">
                            <?php else: ?>
                                <div style="height:100%;display:flex;align-items:center;justify-content:center;color:#ccc;font-size:2.5rem;">
                                    <i class="fa fa-image"></i>
                                </div>
                            <?php endif; ?>
                            <div style="position:absolute;top:8px;right:8px;display:flex;gap:4px;">
                                <?php if (!empty($slide['image_url'])): ?>
                                    <span class="badge badge-secondary" title="Есть десктоп-версия"><i class="fa fa-desktop"></i></span>
                                <?php endif; ?>
                                <?php if (!empty($slide['image_url_mobile'])): ?>
                                    <span class="badge badge-secondary" title="Есть мобильная версия"><i class="fa fa-mobile"></i></span>
                                <?php endif; ?>
                                <span class="badge badge-<?= $slide['is_active'] ? 'success' : 'danger' ?>">
                                    <?= $slide['is_active'] ? 'Активен' : 'Скрыт' ?>
                                </span>
                            </div>
                            <div style="position:absolute;bottom:8px;left:8px;background:rgba(0,0,0,0.55);color:#fff;border-radius:4px;padding:2px 8px;font-size:0.75rem;">
                                #<?= (int)$slide['sort_order'] ?>
                            </div>
                        </div>
                        <div style="padding:14px;">
                            <?php if ($slide['title'] || !empty($slide['title_highlight'])): ?>
                                <div style="font-weight:700;font-size:0.9rem;margin-bottom:4px;">
                                    <?= sanitize($slide['title']) ?>
                                    <?php if (!empty($slide['title_highlight'])): ?>
                                        <span style="color:#C70909;"><?= sanitize($slide['title_highlight']) ?></span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($slide['subtitle']): ?>
                                <div style="font-size:0.8rem;color:#888;margin-bottom:8px;"><?= sanitize($slide['subtitle']) ?></div>
                            <?php endif; ?>
                            <?php if ($slide['link_url']): ?>
                                <div style="font-size:0.75rem;color:#aaa;word-break:break-all;"><?= sanitize($slide['link_url']) ?></div>
                            <?php endif; ?>
                            <div style="display:flex;gap:8px;margin-top:12px;">
                                <a href="?edit=<?= (int)$slide['id'] ?>" class="az-btn az-btn-secondary az-btn-sm">
                                    <i class="fa fa-pencil"></i> Ред.
                                </a>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$slide['id'] ?>">
                                    <button type="submit" class="az-btn az-btn-sm <?= $slide['is_active'] ? 'az-btn-secondary' : 'az-btn-success' ?>">
                                        <i class="fa fa-<?= $slide['is_active'] ? 'eye-slash' : 'eye' ?>"></i>
                                    </button>
                                </form>
                                <form method="POST" style="display:inline;"
                                      onsubmit="return confirm('Удалить этот слайд?')">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$slide['id'] ?>">
                                    <button type="submit" class="az-btn az-btn-danger az-btn-sm"><i class="fa fa-trash-o"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <p style="font-size:0.8rem;color:#aaa;margin-top:16px;">
                    <i class="fa fa-info-circle"></i> Для изменения порядка отредактируйте поле «Порядок сортировки» каждого слайда.
                </p>
            <?php endif; ?>

            <?php endif; // list / form ?>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
