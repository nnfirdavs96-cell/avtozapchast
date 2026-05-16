<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db   = getDB();
$csrf = generateCsrfToken();

$sections = permissionSections();

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/superadmin/permissions.php');
    }
    $uid    = (int)($_POST['user_id'] ?? 0);
    $action = $_POST['action'] ?? '';

    // Confirm target is a real admin/manager (never restrict superadmin/buyer here)
    $u = null;
    if ($uid) {
        $s = $db->prepare("SELECT id, username, role FROM users WHERE id = ? AND role IN ('admin','manager') LIMIT 1");
        $s->execute([$uid]);
        $u = $s->fetch() ?: null;
    }
    if (!$u) {
        flashMessage('danger', 'Пользователь не найден или не является admin/manager.');
        redirect(APP_URL . '/superadmin/permissions.php');
    }

    if ($action === 'save') {
        $picked = $_POST['sections'] ?? [];
        if (!is_array($picked)) $picked = [];
        // Keep only valid keys relevant to this user's role
        $valid = [];
        foreach ($sections as $key => $meta) {
            if (in_array($u['role'], $meta['roles'], true) && in_array($key, $picked, true)) {
                $valid[] = $key;
            }
        }
        $json = json_encode(array_values($valid), JSON_UNESCAPED_UNICODE);
        $db->prepare(
            "INSERT INTO user_permissions (user_id, sections) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE sections = VALUES(sections)"
        )->execute([$uid, $json]);
        flashMessage('success', "Права для «{$u['username']}» сохранены.");
        redirect(APP_URL . '/superadmin/permissions.php?user_id=' . $uid);
    }

    if ($action === 'reset') {
        $db->prepare("DELETE FROM user_permissions WHERE user_id = ?")->execute([$uid]);
        flashMessage('success', "Права «{$u['username']}» сброшены к умолчанию (полный доступ роли).");
        redirect(APP_URL . '/superadmin/permissions.php?user_id=' . $uid);
    }

    redirect(APP_URL . '/superadmin/permissions.php');
}

// ── Load staff & selected user ──────────────────────────────────────────────────
$tableReady = true;
try {
    $db->query("SELECT 1 FROM user_permissions LIMIT 1");
} catch (PDOException $e) {
    $tableReady = false;
}

$staff = $db->query(
    "SELECT id, username, email, role FROM users
     WHERE role IN ('admin','manager') AND is_active = 1
     ORDER BY role, username"
)->fetchAll();

$selId   = (int)($_GET['user_id'] ?? 0);
$selUser = null;
foreach ($staff as $st) { if ((int)$st['id'] === $selId) { $selUser = $st; break; } }

$current = null; // null = not configured (full default access)
if ($selUser && $tableReady) {
    $current = getUserAllowedSections((int)$selUser['id']);
}

$pageTitle = 'Права доступа — ' . getSetting('site_name');
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
      <a href="<?= APP_URL ?>/superadmin/permissions.php" class="az-sidebar-link active" style="color:#ce93d8;"><i class="fa fa-shield"></i> Права доступа</a>
      <a href="<?= APP_URL ?>/admin/orders.php" class="az-sidebar-link"><i class="fa fa-shopping-bag"></i> Заказы</a>
      <a href="<?= APP_URL ?>/admin/products.php" class="az-sidebar-link"><i class="fa fa-cogs"></i> Товары</a>
      <a href="<?= APP_URL ?>/admin/sliders.php" class="az-sidebar-link"><i class="fa fa-picture-o"></i> Слайдер</a>
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link"><i class="fa fa-cog"></i> Настройки</a>
      <a href="<?= APP_URL ?>/superadmin/currencies.php" class="az-sidebar-link"><i class="fa fa-money"></i> Валюты</a>
      <a href="<?= APP_URL ?>/superadmin/languages.php" class="az-sidebar-link"><i class="fa fa-language"></i> Языки</a>
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
      <div class="az-topbar-title" style="color:#6a1b9a;">Права доступа сотрудников</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <?php if (!$tableReady): ?>
      <div class="alert alert-warning mb-16">
        Таблица <code>user_permissions</code> ещё не создана. Выполните миграцию:<br>
        <code>mysql -u avtouser -p'...' avtozapchast &lt; sql/migrate_permissions.sql</code><br>
        Пока её нет — все сотрудники работают с полным доступом своей роли (как сейчас).
      </div>
      <?php endif; ?>

      <div class="az-card mb-24" style="max-width:980px;">
        <div class="az-card-header"><h4 class="az-card-title">Выберите сотрудника</h4></div>
        <div class="az-card-body">
          <form method="get" style="margin:0;">
            <select name="user_id" onchange="this.form.submit()"
                    style="padding:9px 12px;border:1px solid #ddd;border-radius:6px;font-size:0.95rem;min-width:320px;">
              <option value="">— выберите admin / manager —</option>
              <?php foreach ($staff as $st): ?>
              <option value="<?= (int)$st['id'] ?>" <?= $selId === (int)$st['id'] ? 'selected' : '' ?>>
                <?= sanitize($st['username']) ?> (<?= sanitize($st['role']) ?>) — <?= sanitize($st['email']) ?>
              </option>
              <?php endforeach; ?>
            </select>
            <noscript><button type="submit" class="az-btn az-btn-primary az-btn-sm">Открыть</button></noscript>
          </form>
        </div>
      </div>

      <?php if ($selUser):
        $isConfigured = ($current !== null);
      ?>
      <div class="az-card" style="max-width:980px;border-left:3px solid #9b59b6;">
        <div class="az-card-header" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;">
          <h4 class="az-card-title">
            Разделы для «<?= sanitize($selUser['username']) ?>»
            <span class="badge badge-success" style="margin-left:6px;"><?= sanitize($selUser['role']) ?></span>
          </h4>
          <span style="font-size:0.82rem;color:<?= $isConfigured ? '#6a1b9a' : '#888' ?>;">
            <?= $isConfigured ? 'Настроено вручную' : 'По умолчанию (полный доступ роли)' ?>
          </span>
        </div>
        <div class="az-card-body">
          <p style="font-size:0.85rem;color:#666;line-height:1.6;margin-bottom:16px;">
            Отметьте разделы, которые сотрудник может открывать и редактировать.
            Снятая галочка скрывает раздел в меню и блокирует доступ.
            «Сбросить» — вернуть полный доступ роли (как до настройки). Суперадмин не ограничивается.
          </p>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$selUser['id'] ?>">
            <input type="hidden" name="action" value="save">

            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;margin-bottom:20px;">
              <?php foreach ($sections as $key => $meta):
                if (!in_array($selUser['role'], $meta['roles'], true)) continue;
                // Default (not configured) = everything checked = current full access
                $checked = $isConfigured ? in_array($key, $current, true) : true;
              ?>
              <label style="display:flex;align-items:center;gap:10px;padding:12px 14px;border:1px solid #e0e0e0;border-radius:8px;cursor:pointer;background:<?= $checked ? '#f5f0fa' : '#fff' ?>;">
                <input type="checkbox" name="sections[]" value="<?= sanitize($key) ?>" <?= $checked ? 'checked' : '' ?>
                       style="width:18px;height:18px;cursor:pointer;">
                <span style="font-size:0.92rem;font-weight:600;color:#333;"><?= sanitize($meta['label']) ?></span>
              </label>
              <?php endforeach; ?>
            </div>

            <button type="submit" class="az-btn az-btn-primary"
                    style="background:#9b59b6;border-color:#9b59b6;">
              <i class="fa fa-save"></i> Сохранить права
            </button>
            <?php if ($isConfigured): ?>
            <button type="submit" name="action" value="reset"
                    class="az-btn az-btn-outline" style="margin-left:8px;"
                    onclick="return confirm('Сбросить к умолчанию (полный доступ роли)?');">
              Сбросить к умолчанию
            </button>
            <?php endif; ?>
          </form>
        </div>
      </div>
      <?php endif; ?>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
