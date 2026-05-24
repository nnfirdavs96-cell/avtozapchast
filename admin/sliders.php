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
dbAddColumnIfMissing($db, 'sliders', 'text_blocks',        "`text_blocks` TEXT NULL AFTER `subtitle`");
dbAddColumnIfMissing($db, 'sliders', 'text_blocks_mobile', "`text_blocks_mobile` TEXT NULL AFTER `text_blocks`");
dbAddColumnIfMissing($db, 'sliders', 'text_pos',          "`text_pos` VARCHAR(20) NOT NULL DEFAULT 'left-center' AFTER `text_blocks_mobile`");
dbAddColumnIfMissing($db, 'sliders', 'text_pos_mobile',   "`text_pos_mobile` VARCHAR(20) NOT NULL DEFAULT 'left-center' AFTER `text_pos`");
dbAddColumnIfMissing($db, 'sliders', 'button_text',       "`button_text` VARCHAR(100) NOT NULL DEFAULT '' AFTER `text_pos_mobile`");

// ── One-time migration: fix link_url values pointing to private/internal IPs ──
// (e.g. Timeweb reverse-proxy IP 10.x.x.x stored during development)
(function() use ($db) {
    $privatePattern = "link_url LIKE 'http://10.%' OR link_url LIKE 'https://10.%'"
                    . " OR link_url LIKE 'http://192.168.%' OR link_url LIKE 'https://192.168.%'"
                    . " OR link_url LIKE 'http://172.1%.%' OR link_url LIKE 'https://172.1%.%'";
    $rows = $db->query("SELECT id, link_url FROM sliders WHERE $privatePattern")->fetchAll();
    if ($rows) {
        $upd = $db->prepare("UPDATE sliders SET link_url = ? WHERE id = ?");
        foreach ($rows as $row) {
            $fixed = preg_replace('~^https?://(?:10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+)~i', '', $row['link_url']);
            $upd->execute([$fixed, $row['id']]);
        }
    }
})();

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
    $sid        = (int)($_POST['id'] ?? 0);
    $imgUrl     = trim($_POST['image_url'] ?? '');
    $imgMobile  = trim($_POST['image_url_mobile'] ?? '');
    $linkUrl    = trim($_POST['link_url'] ?? '');
    $buttonText = trim($_POST['button_text'] ?? '');
    $sort       = (int)($_POST['sort_order'] ?? 0);

    // Strip private/internal IP prefixes so stored URLs stay portable across environments.
    $linkUrl = preg_replace('~^https?://(?:10\.\d+\.\d+\.\d+|192\.168\.\d+\.\d+|172\.(?:1[6-9]|2\d|3[01])\.\d+\.\d+)~i', '', $linkUrl);

    // Desktop & mobile text blocks arrive as JSON (serialised by the editor JS),
    // so each device keeps its own independent set of blocks.
    $decodeBlocks = function ($json): array {
        $arr = json_decode((string)$json, true);
        return is_array($arr) ? normalizeSliderBlocks($arr) : [];
    };
    $blocksD = $decodeBlocks($_POST['blocks_desktop'] ?? '');
    $blocksM = $decodeBlocks($_POST['blocks_mobile']  ?? '');

    $textBlocks       = $blocksD ? json_encode($blocksD, JSON_UNESCAPED_UNICODE) : '';
    $textBlocksMobile = $blocksM ? json_encode($blocksM, JSON_UNESCAPED_UNICODE) : '';
    // First desktop block (or first mobile if no desktop) doubles as list-preview title.
    $title = $blocksD[0]['text'] ?? ($blocksM[0]['text'] ?? '');

    $validPos = fn(string $p): string =>
        preg_match('/^(left|center|right)-(top|center|bottom)$/', $p) ? $p : 'left-center';
    $textPos       = $validPos(trim($_POST['text_pos']        ?? 'left-center'));
    $textPosMobile = $validPos(trim($_POST['text_pos_mobile'] ?? 'left-center'));

    if ($imgUrl === '' && $imgMobile === '') {
        flashMessage('danger', 'Загрузите хотя бы одно изображение (десктоп или мобильное).');
        redirect(APP_URL . '/admin/sliders.php' . ($sid ? "?edit=$sid" : '?action=new'));
    }

    if ($sid) {
        $db->prepare(
            "UPDATE sliders SET title=?, title_highlight='', subtitle='', text_blocks=?, text_blocks_mobile=?, text_pos=?, text_pos_mobile=?, image_url=?, image_url_mobile=?, link_url=?, button_text=?, sort_order=? WHERE id=?"
        )->execute([$title, $textBlocks, $textBlocksMobile, $textPos, $textPosMobile, $imgUrl, $imgMobile, $linkUrl, $buttonText, $sort, $sid]);
        flashMessage('success', 'Слайд обновлён.');
    } else {
        $db->prepare(
            "INSERT INTO sliders (title, text_blocks, text_blocks_mobile, text_pos, text_pos_mobile, image_url, image_url_mobile, link_url, button_text, sort_order, is_active) VALUES (?,?,?,?,?,?,?,?,?,?,1)"
        )->execute([$title, $textBlocks, $textBlocksMobile, $textPos, $textPosMobile, $imgUrl, $imgMobile, $linkUrl, $buttonText, $sort]);
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

// Mobile blocks: prefer saved mobile JSON; otherwise start from a copy of the
// desktop blocks so the slide keeps working on phones until the admin tweaks it.
$initialBlocksMobile = [];
if ($editSlide && !empty($editSlide['text_blocks_mobile'])) {
    $decodedM = json_decode($editSlide['text_blocks_mobile'], true);
    if (is_array($decodedM)) $initialBlocksMobile = normalizeSliderBlocks($decodedM);
}
if (!$initialBlocksMobile) {
    $initialBlocksMobile = $initialBlocks;
}

$savedPosDesktop = $editSlide['text_pos']        ?? 'left-center';
$savedPosMobile  = $editSlide['text_pos_mobile'] ?? $savedPosDesktop;

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
                            <i class="fa fa-info-circle"></i> <strong>Рекомендуемый размер:</strong> ~768&times;768&nbsp;px (квадратное) или 768&times;500&nbsp;px (горизонтальное).<br>
                            <span style="color:#5a87b3;">Слайдер показывает центр картинки (380&nbsp;px высота). Важный объект держите в центре.</span>
                        </div>
                        <input type="hidden" name="image_url_mobile" id="imageUrlMobile"
                               value="<?= sanitize($editSlide['image_url_mobile'] ?? '') ?>">
                    </div>

                    <div class="az-card">
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:10px;">
                            <h3 style="margin:0;"><i class="fa fa-eye"></i> Предпросмотр</h3>
                            <div class="sl-mode-toggle">
                                <button type="button" class="sl-mode-btn active" data-mode="desktop">
                                    <i class="fa fa-desktop"></i> Десктоп
                                </button>
                                <button type="button" class="sl-mode-btn" data-mode="mobile">
                                    <i class="fa fa-mobile"></i> Мобильный
                                </button>
                            </div>
                        </div>
                        <div id="slPreview" class="sl-preview">
                            <div id="slPreviewFrame">
                                <div id="slPreviewContent"></div>
                                <span class="sl-preview-btn" id="slPreviewBtn"><?= sanitize($editSlide['button_text'] ?? '') ?: t('shop') ?> &raquo;</span>
                            </div>
                        </div>
                        <small style="color:#888;">Пиксель-в-пиксель: рендерится в реальных размерах и масштабируется.</small>
                    </div>

                    <div id="modeBanner" class="az-alert" style="display:flex;align-items:center;gap:10px;font-weight:600;">
                        <i class="fa fa-pencil"></i>
                        <span>Вы редактируете: <strong id="modeBannerLabel">Десктоп</strong></span>
                        <span style="font-weight:400;font-size:0.82rem;opacity:0.8;">— переключайте «Десктоп / Мобильный» выше, чтобы настроить каждую версию отдельно.</span>
                        <button type="button" id="copyFromDesktop" class="az-btn az-btn-secondary az-btn-sm" style="margin-left:auto;display:none;">
                            <i class="fa fa-copy"></i> Скопировать из десктопа
                        </button>
                    </div>

                    <div class="az-card">
                        <h3><i class="fa fa-arrows"></i> Расположение текста <span id="posModeTag" style="color:#C70909;"></span></h3>
                        <p style="font-size:0.83rem;color:#666;margin-top:0;">Выберите куда разместить текст — нажмите нужную ячейку.</p>
                        <?php
                        $posLabels = [
                            'left-top'=>'Лево верх','center-top'=>'Центр верх','right-top'=>'Право верх',
                            'left-center'=>'Лево середина','center-center'=>'Центр середина','right-center'=>'Право середина',
                            'left-bottom'=>'Лево низ','center-bottom'=>'Центр низ','right-bottom'=>'Право низ',
                        ];
                        $posIcons = [
                            'left-top'=>'↖','center-top'=>'↑','right-top'=>'↗',
                            'left-center'=>'←','center-center'=>'⊙','right-center'=>'→',
                            'left-bottom'=>'↙','center-bottom'=>'↓','right-bottom'=>'↘',
                        ];
                        ?>
                        <div class="pos-picker">
                            <?php foreach ($posIcons as $posKey => $icon): ?>
                                <button type="button" class="pos-btn"
                                        data-pos="<?= $posKey ?>" title="<?= $posLabels[$posKey] ?>">
                                    <?= $icon ?>
                                </button>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="text_pos"        id="textPosInput"       value="<?= sanitize($savedPosDesktop) ?>">
                        <input type="hidden" name="text_pos_mobile" id="textPosInputMobile" value="<?= sanitize($savedPosMobile) ?>">
                        <div style="margin-top:8px;font-size:0.8rem;color:#555;">
                            Выбрано: <strong id="posLabel"></strong>
                        </div>
                    </div>

                    <div class="az-card">
                        <h3><i class="fa fa-font"></i> Текстовые блоки <span id="blkModeTag" style="color:#C70909;"></span></h3>
                        <p style="font-size:0.83rem;color:#666;margin-top:0;">
                            Добавьте сколько угодно строк (заголовки и подзаголовки). Для каждой настройте
                            размер, жирность, цвет, шрифт и отступ снизу. Порядок — стрелками.
                        </p>
                        <div id="blocksContainer"></div>
                        <button type="button" class="az-btn az-btn-secondary az-btn-sm" id="addBlockBtn">
                            <i class="fa fa-plus"></i> Добавить блок
                        </button>
                        <input type="hidden" name="blocks_desktop" id="blocksDesktop">
                        <input type="hidden" name="blocks_mobile"  id="blocksMobile">
                    </div>

                    <div class="az-card">
                        <h3>Кнопка и порядок</h3>
                        <div class="az-form-group">
                            <label>Текст кнопки <small style="color:#888;font-weight:400;">(если пусто — будет «<?= t('shop') ?>»)</small></label>
                            <input type="text" name="button_text" id="buttonTextInput"
                                   value="<?= sanitize($editSlide['button_text'] ?? '') ?>"
                                   placeholder="<?= sanitize(t('shop')) ?>"
                                   style="max-width:260px;">
                        </div>
                        <div class="az-form-group">
                            <label>Ссылка кнопки (URL)</label>
                            <input type="text" name="link_url"
                                   value="<?= sanitize($editSlide['link_url'] ?? '') ?>"
                                   placeholder="/catalog/index.php">
                            <small style="color:#888;">Используйте относительный путь (/catalog/index.php) — не вводите IP-адрес.</small>
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
            .sl-mode-toggle { display:flex; gap:4px; }
            .sl-mode-btn { border: 1px solid #dee2e6; background: #f8f9fa; border-radius: 6px; padding: 5px 12px; cursor: pointer; font-size: 0.8rem; color: #555; }
            .sl-mode-btn.active { background: #1a1a2e; border-color: #1a1a2e; color: #fff; }
            .pos-picker { display: grid; grid-template-columns: repeat(3,48px); gap: 6px; }
            .pos-btn { width:48px; height:48px; border: 2px solid #dee2e6; border-radius: 8px; background: #f8f9fa; cursor: pointer; font-size: 1.25rem; color: #555; transition: all .15s; }
            .pos-btn:hover { border-color: #C70909; color: #C70909; background: #fff5f5; }
            .pos-btn.active { border-color: #C70909; background: #C70909; color: #fff; }
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
            window.SL_INIT        = <?= json_encode($initialBlocks, JSON_UNESCAPED_UNICODE) ?>;
            window.SL_INIT_MOBILE = <?= json_encode($initialBlocksMobile, JSON_UNESCAPED_UNICODE) ?>;

            (function () {
                const container = document.getElementById('blocksContainer');
                const preview   = document.getElementById('slPreview');
                const frame     = document.getElementById('slPreviewFrame');
                const pvContent = document.getElementById('slPreviewContent');
                const form      = document.getElementById('sliderForm');
                var   slMode    = 'desktop'; // 'desktop' | 'mobile'

                // Independent state per device — each holds its own block list + position.
                var blocksState = {
                    desktop: (window.SL_INIT        && window.SL_INIT.length        ? window.SL_INIT.slice()        : [null]),
                    mobile:  (window.SL_INIT_MOBILE && window.SL_INIT_MOBILE.length ? window.SL_INIT_MOBILE.slice() : [null])
                };
                var posState = {
                    desktop: document.getElementById('textPosInput').value       || 'left-center',
                    mobile:  document.getElementById('textPosInputMobile').value || 'left-center'
                };

                var SL_DIM = {
                    desktop: { w: 1140, h: 420 },
                    mobile:  { w: 390,  h: 380 }   // matches the CSS height:380px on mobile
                };
                var posLabels = {
                    'left-top':'Лево верх','center-top':'Центр верх','right-top':'Право верх',
                    'left-center':'Лево середина','center-center':'Центр середина','right-center':'Право середина',
                    'left-bottom':'Лево низ','center-bottom':'Центр низ','right-bottom':'Право низ'
                };

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
                        '<input type="text" class="blk-text" placeholder="Текст строки" value="">' +
                        '<div class="blk-grid">' +
                            '<div><label>Размер, px</label><input type="number" min="8" max="200" class="blk-size"></div>' +
                            '<div><label>Жирность</label><select class="blk-weight">' + optionsHtml(window.SL_WEIGHTS, b.weight) + '</select></div>' +
                            '<div><label>Цвет</label><input type="color" class="blk-color"></div>' +
                            '<div><label>Шрифт</label><select class="blk-font">' + optionsHtml(window.SL_FONTS, b.font) + '</select></div>' +
                            '<div><label>Отступ снизу, px</label><input type="number" min="0" max="160" class="blk-mb"></div>' +
                        '</div>';
                    row.querySelector('.blk-text').value   = b.text || '';
                    row.querySelector('.blk-size').value   = b.size != null ? b.size : 30;
                    row.querySelector('.blk-color').value  = b.color || '#ffffff';
                    row.querySelector('.blk-mb').value     = b.mb != null ? b.mb : 10;
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

                // Read the rows currently in the DOM into a plain array of block objects.
                function collectRows() {
                    var out = [];
                    container.querySelectorAll('.blk-row').forEach(function (row) {
                        out.push({
                            text:   row.querySelector('.blk-text').value,
                            size:   parseInt(row.querySelector('.blk-size').value, 10) || 24,
                            weight: row.querySelector('.blk-weight').value,
                            color:  row.querySelector('.blk-color').value,
                            font:   row.querySelector('.blk-font').value,
                            mb:     parseInt(row.querySelector('.blk-mb').value, 10) || 0
                        });
                    });
                    return out;
                }

                // Rebuild the rows from a block array.
                function loadRows(arr) {
                    container.innerHTML = '';
                    (arr && arr.length ? arr : [null]).forEach(function (b) {
                        container.appendChild(makeRow(b));
                    });
                    renumber();
                }

                function renderPreview() {
                    var dim = SL_DIM[slMode];
                    var url = (document.getElementById('imageUrl') || {}).value || '';
                    if (slMode === 'mobile') {
                        var mobileUrl = (document.getElementById('imageUrlMobile') || {}).value || '';
                        if (mobileUrl) url = mobileUrl;
                    }

                    frame.style.setProperty('--bg', url ? 'url("' + url + '")' : '#14171c');
                    var scale = Math.max(0.05, preview.clientWidth / dim.w);
                    frame.style.width     = dim.w + 'px';
                    frame.style.height    = dim.h + 'px';
                    frame.style.transform = 'scale(' + scale + ')';
                    preview.style.height  = (dim.h * scale) + 'px';

                    var pos    = posState[slMode] || 'left-center';
                    var parts  = pos.split('-');
                    var hAlign = parts[0] || 'left';
                    var vAlign = parts[1] || 'center';
                    frame.style.justifyContent = vAlign === 'top' ? 'flex-start' : (vAlign === 'bottom' ? 'flex-end' : 'center');
                    frame.style.paddingTop    = vAlign === 'top'    ? (slMode === 'mobile' ? '30px' : '60px') : '0';
                    frame.style.paddingBottom = vAlign === 'bottom' ? (slMode === 'mobile' ? '30px' : '60px') : '0';
                    frame.style.alignItems    = hAlign === 'center' ? 'center' : (hAlign === 'right' ? 'flex-end' : 'flex-start');
                    frame.style.paddingLeft   = hAlign === 'left'  ? (slMode === 'mobile' ? '20px' : '80px') : '0';
                    frame.style.paddingRight  = hAlign === 'right' ? (slMode === 'mobile' ? '20px' : '80px') : '0';

                    pvContent.innerHTML = '';
                    container.querySelectorAll('.blk-row').forEach(function (row) {
                        var text = row.querySelector('.blk-text').value;
                        if (!text.trim()) return;
                        var size   = parseInt(row.querySelector('.blk-size').value, 10) || 24;
                        var mb     = parseInt(row.querySelector('.blk-mb').value, 10) || 0;
                        var weight = row.querySelector('.blk-weight').value;
                        var color  = row.querySelector('.blk-color').value;
                        var font   = row.querySelector('.blk-font').value;
                        var div = document.createElement('div');
                        div.className = 'sl-preview-block';
                        div.textContent = text;
                        div.style.fontSize     = size + 'px';
                        div.style.marginBottom = mb + 'px';
                        div.style.fontWeight   = weight;
                        div.style.color        = color;
                        var stack = window.SL_FONT_STACK[font];
                        if (stack) div.style.fontFamily = stack;
                        div.style.textAlign = hAlign;
                        pvContent.appendChild(div);
                    });
                }
                window.renderSlPreview = renderPreview;

                // ── Position picker (mode-aware) ──────────────────────
                var posLbl = document.getElementById('posLabel');
                function syncPosPicker() {
                    var cur = posState[slMode];
                    document.querySelectorAll('.pos-btn').forEach(function (b) {
                        b.classList.toggle('active', b.dataset.pos === cur);
                    });
                    posLbl.textContent = posLabels[cur] || cur;
                }
                document.querySelectorAll('.pos-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        posState[slMode] = btn.dataset.pos;
                        syncPosPicker();
                        renderPreview();
                    });
                });

                // ── Mode label/tags ───────────────────────────────────
                var modeBanner      = document.getElementById('modeBanner');
                var modeBannerLabel = document.getElementById('modeBannerLabel');
                var posModeTag      = document.getElementById('posModeTag');
                var blkModeTag      = document.getElementById('blkModeTag');
                var copyBtn         = document.getElementById('copyFromDesktop');
                function syncModeLabels() {
                    var label = slMode === 'mobile' ? 'Мобильный' : 'Десктоп';
                    modeBannerLabel.textContent = label;
                    posModeTag.textContent = '(' + label + ')';
                    blkModeTag.textContent = '(' + label + ')';
                    modeBanner.className = 'az-alert ' + (slMode === 'mobile' ? 'az-alert-warning' : 'az-alert-info');
                    modeBanner.style.display = 'flex';
                    copyBtn.style.display = slMode === 'mobile' ? 'inline-flex' : 'none';
                }

                // ── Mode toggle: save current rows, swap to the other set ──
                function switchMode(newMode) {
                    if (newMode === slMode) return;
                    blocksState[slMode] = collectRows();   // persist current edits
                    slMode = newMode;
                    loadRows(blocksState[slMode]);
                    syncPosPicker();
                    syncModeLabels();
                    renderPreview();
                }
                document.querySelectorAll('.sl-mode-btn').forEach(function (btn) {
                    btn.addEventListener('click', function () {
                        document.querySelectorAll('.sl-mode-btn').forEach(function (b) { b.classList.remove('active'); });
                        btn.classList.add('active');
                        switchMode(btn.dataset.mode);
                    });
                });

                // Copy desktop blocks + position into mobile (convenience).
                copyBtn.addEventListener('click', function () {
                    if (slMode !== 'mobile') return;
                    blocksState.mobile = JSON.parse(JSON.stringify(blocksState.desktop));
                    posState.mobile = posState.desktop;
                    loadRows(blocksState.mobile);
                    syncPosPicker();
                    renderPreview();
                });

                document.getElementById('addBlockBtn').addEventListener('click', function () {
                    container.appendChild(makeRow());
                    renumber();
                    renderPreview();
                });
                window.addEventListener('resize', renderPreview);

                // Live-update preview button text.
                var btnTxtInput = document.getElementById('buttonTextInput');
                var pvBtn       = document.getElementById('slPreviewBtn');
                var defaultBtnLabel = <?= json_encode(t('shop')) ?>;
                if (btnTxtInput && pvBtn) {
                    btnTxtInput.addEventListener('input', function () {
                        pvBtn.textContent = (this.value.trim() || defaultBtnLabel) + ' »';
                    });
                }

                // ── On submit: serialise both states into hidden inputs ──
                form.addEventListener('submit', function () {
                    blocksState[slMode] = collectRows();
                    document.getElementById('blocksDesktop').value     = JSON.stringify(blocksState.desktop);
                    document.getElementById('blocksMobile').value      = JSON.stringify(blocksState.mobile);
                    document.getElementById('textPosInput').value      = posState.desktop;
                    document.getElementById('textPosInputMobile').value = posState.mobile;
                });

                // Initial render (desktop mode).
                loadRows(blocksState.desktop);
                syncPosPicker();
                syncModeLabels();
                renderPreview();
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
