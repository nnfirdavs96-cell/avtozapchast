<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db   = getDB();
$csrf = generateCsrfToken();

$BACKUP_DIR = APP_ROOT . '/storage/backups';
if (!is_dir($BACKUP_DIR)) {
    @mkdir($BACKUP_DIR, 0750, true);
    @file_put_contents($BACKUP_DIR . '/.htaccess', "Require all denied\nDeny from all\n");
    @file_put_contents($BACKUP_DIR . '/index.html', '');
}

require_once __DIR__ . '/_backup_lib.php';

// ─────────────────────────────────────────────────────────────────────────────
// Download
// ─────────────────────────────────────────────────────────────────────────────
if (($_GET['action'] ?? '') === 'download') {
    $name = backup_safe_name($_GET['file'] ?? '');
    $path = $BACKUP_DIR . '/' . $name;
    if ($name && is_file($path)) {
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Content-Length: ' . filesize($path));
        readfile($path);
        exit;
    }
    flashMessage('danger', 'Файл не найден.');
    redirect(APP_URL . '/superadmin/backup.php');
}

// ─────────────────────────────────────────────────────────────────────────────
// POST handlers
// ─────────────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/backup.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'create') {
        @set_time_limit(300);
        $r = backup_create($db, $BACKUP_DIR);
        if ($r['ok']) {
            try {
                $db->prepare(
                    "INSERT INTO backups (filename, size_bytes, tables, created_by, note) VALUES (?,?,?,?,?)"
                )->execute([$r['file'], $r['size'], $r['count'], $_SESSION['user_id'] ?? null, 'Manual via UI']);
            } catch (Exception $e) {}
            flashMessage('success', "Бэкап создан: {$r['file']} ({$r['count']} таблиц, " . number_format($r['size']/1024, 1) . " КБ).");
        } else {
            flashMessage('danger', 'Ошибка бэкапа: ' . $r['error']);
        }
        redirect(APP_URL . '/superadmin/backup.php');
    }

    if ($postAction === 'delete') {
        $name = backup_safe_name($_POST['file'] ?? '');
        $path = $BACKUP_DIR . '/' . $name;
        if ($name && is_file($path) && @unlink($path)) {
            flashMessage('success', "Бэкап удалён: {$name}");
        } else {
            flashMessage('danger', 'Не удалось удалить файл.');
        }
        redirect(APP_URL . '/superadmin/backup.php');
    }

    if ($postAction === 'restore') {
        @set_time_limit(600);
        $name = backup_safe_name($_POST['file'] ?? '');
        $path = $BACKUP_DIR . '/' . $name;
        if (!$name || !is_file($path)) {
            flashMessage('danger', 'Файл бэкапа не найден.');
        } else {
            // Safety: take a pre-restore backup first
            $pre = backup_create($db, $BACKUP_DIR);
            $r   = backup_restore($db, $path);
            if ($r['ok']) {
                flashMessage('success', "Восстановлено успешно. Запросов выполнено: {$r['executed']}. Предварительный бэкап: " . ($pre['ok'] ? $pre['file'] : 'нет'));
            } else {
                flashMessage('danger', "Восстановление с ошибками. Выполнено: {$r['executed']}, ошибок: {$r['errors']}. Последняя: " . sanitize($r['lastError']));
            }
        }
        redirect(APP_URL . '/superadmin/backup.php');
    }

    if ($postAction === 'upload') {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            flashMessage('danger', 'Ошибка загрузки файла.');
        } else {
            $orig  = $_FILES['backup_file']['name'];
            $ext   = (substr($orig, -7) === '.sql.gz') ? '.sql.gz' : (substr($orig, -4) === '.sql' ? '.sql' : '');
            if (!$ext) {
                flashMessage('danger', 'Допускаются только .sql или .sql.gz файлы.');
            } else {
                $target = $BACKUP_DIR . '/backup_uploaded_' . date('Ymd_His') . $ext;
                if (move_uploaded_file($_FILES['backup_file']['tmp_name'], $target)) {
                    flashMessage('success', 'Файл загружен: ' . basename($target));
                } else {
                    flashMessage('danger', 'Не удалось сохранить файл.');
                }
            }
        }
        redirect(APP_URL . '/superadmin/backup.php');
    }

    redirect(APP_URL . '/superadmin/backup.php');
}

// ─────────────────────────────────────────────────────────────────────────────
// List backups
// ─────────────────────────────────────────────────────────────────────────────
$backups = [];
foreach (glob($BACKUP_DIR . '/backup_*.sql*') ?: [] as $f) {
    $backups[] = [
        'name'  => basename($f),
        'size'  => filesize($f),
        'mtime' => filemtime($f),
    ];
}
usort($backups, fn($a, $b) => $b['mtime'] <=> $a['mtime']);

$totalSize = array_sum(array_column($backups, 'size'));

$pageTitle = 'Резервные копии — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="az-panel">
  <aside class="az-sidebar" style="background:#1a0533;">
    <div class="az-sidebar-brand" style="background:rgba(155,89,182,0.3);border-bottom-color:rgba(155,89,182,0.3);">
      <span style="color:#ce93d8;">&#x2605;</span> Суперадмин
    </div>
    <nav class="az-sidebar-nav">
      <a href="<?= APP_URL ?>/superadmin/index.php" class="az-sidebar-link"><i class="fa fa-star"></i> Панель</a>
      <a href="<?= APP_URL ?>/admin/users.php" class="az-sidebar-link"><i class="fa fa-users"></i> Пользователи</a>
      <a href="<?= APP_URL ?>/admin/orders.php" class="az-sidebar-link"><i class="fa fa-shopping-bag"></i> Заказы</a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link"><i class="fa fa-cog"></i> Настройки</a>
      <a href="<?= APP_URL ?>/superadmin/currencies.php" class="az-sidebar-link"><i class="fa fa-money"></i> Валюты</a>
      <a href="<?= APP_URL ?>/superadmin/languages.php" class="az-sidebar-link"><i class="fa fa-language"></i> Языки</a>
      <a href="<?= APP_URL ?>/superadmin/warehouse.php" class="az-sidebar-link"><i class="fa fa-database"></i> Склад API</a>
      <a href="<?= APP_URL ?>/superadmin/backup.php" class="az-sidebar-link active" style="color:#ce93d8;"><i class="fa fa-archive"></i> Бэкапы</a>
      <a href="<?= APP_URL ?>/superadmin/blog.php" class="az-sidebar-link"><i class="fa fa-newspaper-o"></i> Блог</a>
      <hr style="border-color:rgba(255,255,255,0.1);margin:12px 0;">
      <a href="<?= APP_URL ?>/index.php" class="az-sidebar-link"><i class="fa fa-home"></i> На сайт</a>
    </nav>
  </aside>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">Резервные копии базы</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-6 mb-24">
          <div class="az-card">
            <div class="az-card-header"><h4 class="az-card-title">Создать бэкап</h4></div>
            <div class="az-card-body">
              <p style="font-size:0.85rem;color:#666;margin-bottom:16px;">
                Полный SQL-дамп базы <code><?= sanitize(DB_NAME) ?></code>. Сжимается в .gz, если доступен модуль zlib.
              </p>
              <form method="post">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="create">
                <button type="submit" class="az-btn az-btn-primary"><i class="fa fa-database"></i> Создать сейчас</button>
              </form>
            </div>
          </div>
        </div>

        <div class="col-lg-6 mb-24">
          <div class="az-card">
            <div class="az-card-header"><h4 class="az-card-title">Загрузить бэкап</h4></div>
            <div class="az-card-body">
              <p style="font-size:0.85rem;color:#666;margin-bottom:16px;">
                Загрузите ранее сохранённый файл <code>.sql</code> или <code>.sql.gz</code>.
              </p>
              <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="upload">
                <div class="az-form-group">
                  <input type="file" name="backup_file" accept=".sql,.gz" class="form-control" required>
                </div>
                <button type="submit" class="az-btn az-btn-outline"><i class="fa fa-upload"></i> Загрузить</button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <div class="az-card">
        <div class="az-card-header">
          <h4 class="az-card-title">
            Сохранённые копии <small style="font-weight:400;color:#888;">(<?= count($backups) ?> шт., <?= number_format($totalSize/1024/1024, 2) ?> МБ)</small>
          </h4>
        </div>
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr>
                  <th>Имя файла</th>
                  <th style="width:120px;">Размер</th>
                  <th style="width:170px;">Дата</th>
                  <th style="width:300px;">Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($backups)): ?>
                <tr><td colspan="4" style="text-align:center;color:#999;padding:24px;">Бэкапов пока нет.</td></tr>
                <?php endif; ?>
                <?php foreach ($backups as $b): ?>
                <tr>
                  <td><code style="font-size:0.8rem;"><?= sanitize($b['name']) ?></code></td>
                  <td><?= number_format($b['size']/1024, 1) ?> КБ</td>
                  <td style="color:#888;"><?= date('d.m.Y H:i:s', $b['mtime']) ?></td>
                  <td>
                    <a href="<?= APP_URL ?>/superadmin/backup.php?action=download&file=<?= urlencode($b['name']) ?>" class="az-btn az-btn-outline az-btn-sm"><i class="fa fa-download"></i> Скачать</a>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Восстановить базу из этого бэкапа? Текущие данные будут перезаписаны. Перед восстановлением будет создан страховой бэкап.');">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="restore">
                      <input type="hidden" name="file" value="<?= sanitize($b['name']) ?>">
                      <button type="submit" class="az-btn az-btn-sm az-btn-success"><i class="fa fa-undo"></i> Восстановить</button>
                    </form>
                    <form method="post" style="display:inline;" onsubmit="return confirm('Удалить этот бэкап навсегда?');">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="file" value="<?= sanitize($b['name']) ?>">
                      <button type="submit" class="az-btn az-btn-sm az-btn-danger"><i class="fa fa-trash"></i> Удалить</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="az-card mt-24" style="border-left:3px solid #9b59b6;">
        <div class="az-card-body">
          <h5 style="color:#6a1b9a;margin-bottom:8px;">Автобэкап (cron)</h5>
          <p style="font-size:0.85rem;color:#555;line-height:1.7;margin:0;">
            Для ежедневных копий добавьте в cron:<br>
            <code style="background:#f5f5f5;padding:4px 8px;display:inline-block;margin-top:4px;">
              0 3 * * * /usr/bin/php <?= APP_ROOT ?>/superadmin/backup_cron.php
            </code>
          </p>
        </div>
      </div>

    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
