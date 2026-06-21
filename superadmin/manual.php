<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');               // строго: только суперадмин
require_once APP_ROOT . '/includes/manual_pdf.php';

$csrf = generateCsrfToken();

$MANUAL_DIR  = APP_ROOT . '/storage/manual';
$MANUAL_FILE = $MANUAL_DIR . '/AutoDoc-Manual.pdf';

if (!is_dir($MANUAL_DIR)) {
    @mkdir($MANUAL_DIR, 0750, true);
    @file_put_contents($MANUAL_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($MANUAL_DIR . '/index.html', '');
}

// Generate on first use (or if missing)
function ensureManual(string $file): bool {
    if (is_file($file) && filesize($file) > 1000) return true;
    try { @set_time_limit(120); autodoc_generate_manual($file); return is_file($file); }
    catch (\Throwable $e) { return false; }
}

// ── Download (superadmin-only by virtue of requireRole above) ──────────
if (($_GET['action'] ?? '') === 'download') {
    if (!ensureManual($MANUAL_FILE)) {
        flashMessage('danger', 'Не удалось подготовить PDF.');
        redirect(APP_URL . '/superadmin/manual.php');
    }
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="AutoDoc-Manual.pdf"');
    header('Content-Length: ' . filesize($MANUAL_FILE));
    header('X-Content-Type-Options: nosniff');
    readfile($MANUAL_FILE);
    exit;
}

// ── Regenerate ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/manual.php');
    }
    if (($_POST['action'] ?? '') === 'regenerate') {
        @unlink($MANUAL_FILE);
        flashMessage(ensureManual($MANUAL_FILE) ? 'success' : 'danger',
            is_file($MANUAL_FILE) ? 'Руководство перегенерировано.' : 'Ошибка генерации PDF.');
    }
    redirect(APP_URL . '/superadmin/manual.php');
}

ensureManual($MANUAL_FILE);
$exists = is_file($MANUAL_FILE);
$size   = $exists ? round(filesize($MANUAL_FILE) / 1024) : 0;
$built  = $exists ? date('d.m.Y H:i', filemtime($MANUAL_FILE)) : '—';

$pageTitle = 'Руководство — Суперадмин';
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('manual'); ?>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">Руководство пользователя (PDF)</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="az-card mb-24" style="max-width:760px;">
        <div class="az-card-body" style="padding:28px;">
          <div style="display:flex;align-items:center;gap:20px;flex-wrap:wrap;">
            <div style="width:84px;height:84px;border-radius:12px;background:#C70909;display:flex;align-items:center;justify-content:center;flex-shrink:0;">
              <i class="fa fa-book" style="color:#fff;font-size:38px;"></i>
            </div>
            <div style="flex:1;min-width:240px;">
              <h3 style="margin:0 0 6px;">Руководство по панели управления</h3>
              <p style="margin:0;color:#777;font-size:0.9rem;">
                Иллюстрированная инструкция для сотрудников: роли, вход, товары,
                слайдер, баннеры, заказы и права доступа.
              </p>
              <p style="margin:8px 0 0;font-size:0.82rem;color:#999;">
                <?php if ($exists): ?>
                  PDF готов · <?= $size ?> КБ · обновлён <?= sanitize($built) ?>
                <?php else: ?>
                  Файл ещё не сгенерирован.
                <?php endif; ?>
              </p>
            </div>
          </div>

          <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap;">
            <a href="?action=download" class="az-btn az-btn-primary">
              <i class="fa fa-download"></i> Скачать PDF
            </a>
            <form method="post" style="display:inline;" onsubmit="return confirm('Перегенерировать PDF заново?');">
              <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
              <input type="hidden" name="action" value="regenerate">
              <button type="submit" class="az-btn az-btn-outline">
                <i class="fa fa-refresh"></i> Перегенерировать
              </button>
            </form>
          </div>
        </div>
      </div>

      <div class="az-card" style="max-width:760px;background:#fffaf0;border:1px solid #f0e2c0;">
        <div class="az-card-body" style="padding:20px 24px;">
          <strong style="color:#9a6e14;"><i class="fa fa-lock"></i> Доступ ограничен</strong>
          <p style="margin:8px 0 0;color:#7a6020;font-size:0.88rem;">
            Эту страницу и скачивание PDF видит только суперадминистратор.
            Скачайте файл и передайте его сотрудникам (например, в мессенджере)
            для обучения работе с панелью.
          </p>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
