<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/languages.php');
    }

    $postAction = $_POST['action'] ?? '';
    $code       = trim($_POST['code'] ?? '');

    if ($postAction === 'toggle_active' && $code) {
        $active = (int)($_POST['is_active'] ?? 0);
        $stmt = $db->prepare("SELECT is_default FROM languages WHERE code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if ($row && $row['is_default'] && !$active) {
            flashMessage('danger', 'Нельзя деактивировать язык по умолчанию.');
        } else {
            $db->prepare("UPDATE languages SET is_active = ? WHERE code = ?")->execute([$active, $code]);
            flashMessage('success', 'Статус языка обновлён.');
        }
        redirect(APP_URL . '/superadmin/languages.php');
    }

    if ($postAction === 'set_default' && $code) {
        $db->exec("UPDATE languages SET is_default = 0");
        $db->prepare("UPDATE languages SET is_default = 1, is_active = 1 WHERE code = ?")->execute([$code]);
        $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES ('default_lang', ?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
           ->execute([$code, $code]);
        flashMessage('success', "Язык {$code} установлен по умолчанию.");
        redirect(APP_URL . '/superadmin/languages.php');
    }

    redirect(APP_URL . '/superadmin/languages.php');
}

// ── Load languages ─────────────────────────────────────────────────────────────
$languages = [];
try {
    $languages = $db->query("SELECT * FROM languages ORDER BY is_default DESC, code ASC")->fetchAll();
} catch (Exception $e) {
    // Table may not exist
}

$langMeta = [
    'ru' => ['name' => 'Русский',     'native' => 'Русский',  'flag' => 'RU'],
    'tg' => ['name' => 'Таджикский',  'native' => 'Тоҷикӣ',  'flag' => 'TJ'],
    'en' => ['name' => 'Английский',  'native' => 'English',  'flag' => 'EN'],
];

$pageTitle = 'Языки — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <aside class="az-sidebar" style="background:#1a0533;">
    <div class="az-sidebar-brand" style="background:rgba(155,89,182,0.3);border-bottom-color:rgba(155,89,182,0.3);">
      <span style="color:#ce93d8;">&#x2605;</span> Суперадмин
    </div>
    <nav class="az-sidebar-nav">
      <a href="<?= APP_URL ?>/superadmin/index.php" class="az-sidebar-link"><i class="fa fa-star"></i> Панель</a>
      <a href="<?= APP_URL ?>/superadmin/users.php" class="az-sidebar-link"><i class="fa fa-users"></i> Пользователи</a>
      <a href="<?= APP_URL ?>/admin/orders.php" class="az-sidebar-link"><i class="fa fa-shopping-bag"></i> Заказы</a>
      <a href="<?= APP_URL ?>/admin/products.php" class="az-sidebar-link"><i class="fa fa-cogs"></i> Товары</a>
      <a href="<?= APP_URL ?>/admin/sliders.php" class="az-sidebar-link"><i class="fa fa-picture-o"></i> Слайдер</a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link"><i class="fa fa-cog"></i> Настройки</a>
      <a href="<?= APP_URL ?>/superadmin/currencies.php" class="az-sidebar-link"><i class="fa fa-money"></i> Валюты</a>
      <a href="<?= APP_URL ?>/superadmin/languages.php" class="az-sidebar-link active" style="color:#ce93d8;"><i class="fa fa-language"></i> Языки</a>
      <a href="<?= APP_URL ?>/superadmin/warehouse.php" class="az-sidebar-link"><i class="fa fa-database"></i> Склад API</a>
      <a href="<?= APP_URL ?>/superadmin/blog.php" class="az-sidebar-link"><i class="fa fa-newspaper-o"></i> Блог</a>
      <a href="<?= APP_URL ?>/superadmin/backup.php" class="az-sidebar-link"><i class="fa fa-archive"></i> Бэкапы</a>
      <hr style="border-color:rgba(255,255,255,0.1);margin:12px 0;">
      <a href="<?= APP_URL ?>/index.php" class="az-sidebar-link"><i class="fa fa-home"></i> На сайт</a>
      <a href="<?= APP_URL ?>/auth/logout.php" class="az-sidebar-link" style="color:rgba(255,100,100,0.85)!important;"><i class="fa fa-sign-out"></i> Выйти</a>
    </nav>
  </aside>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">Управление языками</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="az-card mb-24" style="max-width:800px;">
        <div class="az-card-header">
          <h4 class="az-card-title">Языки сайта</h4>
        </div>
        <?php if (!empty($languages)): ?>
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr>
                  <th>Код</th>
                  <th>Название</th>
                  <th>Родное название</th>
                  <th style="text-align:center;">По умолчанию</th>
                  <th style="text-align:center;">Файл перевода</th>
                  <th style="text-align:center;">Статус</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($languages as $lang):
                  $meta = $langMeta[$lang['code']] ?? ['name' => $lang['code'], 'native' => $lang['code'], 'flag' => '??'];
                  $hasFile = file_exists(dirname(__DIR__) . '/lang/' . $lang['code'] . '.php');
                ?>
                <tr <?= $lang['is_default'] ? 'style="background:#f9f5ff;"' : '' ?>>
                  <td>
                    <strong style="font-size:1.1rem;"><?= sanitize($lang['code']) ?></strong>
                    <small class="text-muted"><?= sanitize($meta['flag']) ?></small>
                  </td>
                  <td><?= sanitize($lang['name'] ?? $meta['name']) ?></td>
                  <td style="color:#666;"><?= sanitize($meta['native']) ?></td>
                  <td style="text-align:center;">
                    <?php if ($lang['is_default']): ?>
                    <span class="badge badge-success">По умолч.</span>
                    <?php else: ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="set_default">
                      <input type="hidden" name="code" value="<?= sanitize($lang['code']) ?>">
                      <button type="submit" class="az-btn az-btn-outline az-btn-sm">Сделать</button>
                    </form>
                    <?php endif; ?>
                  </td>
                  <td style="text-align:center;">
                    <span class="badge badge-<?= $hasFile ? 'success' : 'danger' ?>">
                      <?= $hasFile ? 'Есть' : 'Нет' ?>
                    </span>
                  </td>
                  <td style="text-align:center;">
                    <span class="badge badge-<?= $lang['is_active'] ? 'success' : 'danger' ?>">
                      <?= $lang['is_active'] ? 'Активен' : 'Откл.' ?>
                    </span>
                  </td>
                  <td>
                    <?php if (!$lang['is_default']): ?>
                    <form method="post" style="display:inline;">
                      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                      <input type="hidden" name="action" value="toggle_active">
                      <input type="hidden" name="code" value="<?= sanitize($lang['code']) ?>">
                      <input type="hidden" name="is_active" value="<?= $lang['is_active'] ? 0 : 1 ?>">
                      <button type="submit" class="az-btn az-btn-sm <?= $lang['is_active'] ? 'az-btn-danger' : 'az-btn-success' ?>">
                        <?= $lang['is_active'] ? 'Отключить' : 'Включить' ?>
                      </button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:0.75rem;color:#aaa;">Основной язык</span>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php else: ?>
        <!-- Fallback: table not found in DB, show static list -->
        <div class="az-card-body">
          <div class="alert alert-warning mb-16">
            Таблица <code>languages</code> не найдена в базе данных. Показаны поддерживаемые языки платформы.
          </div>
          <table class="az-table">
            <thead><tr><th>Код</th><th>Название</th><th>Родное</th><th>Файл перевода</th><th>Статус</th></tr></thead>
            <tbody>
              <?php foreach ($langMeta as $code => $meta): $hasFile = file_exists(dirname(__DIR__) . '/lang/' . $code . '.php'); ?>
              <tr>
                <td><strong><?= $code ?></strong></td>
                <td><?= sanitize($meta['name']) ?></td>
                <td><?= sanitize($meta['native']) ?></td>
                <td><code>/lang/<?= $code ?>.php</code></td>
                <td>
                  <span class="badge badge-<?= $hasFile ? 'success' : 'danger' ?>">
                    <?= $hasFile ? 'Файл есть' : 'Нет файла' ?>
                  </span>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>

      <div class="az-card" style="max-width:800px;border-left:3px solid #9b59b6;">
        <div class="az-card-body">
          <h5 style="color:#6a1b9a;margin-bottom:8px;">Справка</h5>
          <p style="font-size:0.85rem;color:#555;line-height:1.7;margin:0;">
            Активные языки отображаются в переключателе в шапке сайта.<br>
            Язык по умолчанию используется для новых посетителей и как язык интерфейса при недоступности перевода.<br>
            Файлы переводов расположены в <code>/lang/{код}.php</code>.
          </p>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
