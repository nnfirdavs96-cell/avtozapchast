<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['superadmin']);

$db = getDB();
$err = $msg = '';

if ($_SERVER['REQUEST_METHOD']==='POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $a = $_POST['action'] ?? '';
    try {
        if ($a==='create') {
            $hash = password_hash((string)$_POST['password'], PASSWORD_BCRYPT);
            $db->prepare("INSERT INTO users (username,email,phone,password_hash,role,is_active) VALUES (?,?,?,?,?,1)")
                ->execute([trim($_POST['username']), trim($_POST['email']), trim($_POST['phone'] ?? '') ?: null, $hash, $_POST['role']]);
        }
        if ($a==='update') {
            $db->prepare("UPDATE users SET username=?, email=?, phone=?, role=?, is_active=? WHERE id=?")
                ->execute([trim($_POST['username']), trim($_POST['email']), trim($_POST['phone'] ?? '') ?: null, $_POST['role'], isset($_POST['is_active'])?1:0, (int)$_POST['id']]);
            if (!empty($_POST['password'])) {
                $db->prepare("UPDATE users SET password_hash=? WHERE id=?")
                    ->execute([password_hash((string)$_POST['password'], PASSWORD_BCRYPT), (int)$_POST['id']]);
            }
        }
        if ($a==='delete' && (int)$_POST['id'] !== (int)$_SESSION['user_id']) {
            $db->prepare("UPDATE users SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
        }
    } catch (Throwable $e) { $err = $e->getMessage(); }
    if (!$err) redirect(APP_URL . '/superadmin/users.php');
}

$editId = (int)($_GET['edit'] ?? 0);
$editing = $editId ? $db->query("SELECT * FROM users WHERE id={$editId}")->fetch() : null;
$users = $db->query("SELECT u.*, (SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS orders_cnt FROM users u ORDER BY u.created_at DESC")->fetchAll();

$pageTitle = 'Пользователи';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Пользователи и роли <span class="dash-heading-badge">superadmin</span></h1>
    <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

    <div class="admin-card mb-32">
      <h3 style="margin-bottom:14px"><?= $editing?'Редактировать':'Добавить пользователя' ?></h3>
      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="action" value="<?= $editing?'update':'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="form-row">
          <input type="text" name="username" class="form-input" placeholder="Имя" required value="<?= sanitize($editing['username'] ?? '') ?>">
          <input type="email" name="email" class="form-input" placeholder="E-mail" required value="<?= sanitize($editing['email'] ?? '') ?>">
        </div>
        <div class="form-row mt-8">
          <input type="tel" name="phone" class="form-input" placeholder="Телефон" value="<?= sanitize($editing['phone'] ?? '') ?>">
          <select name="role" class="form-select">
            <?php foreach (['buyer','manager','admin','superadmin'] as $r): ?>
              <option value="<?= $r ?>" <?= ($editing['role'] ?? 'buyer')===$r?'selected':'' ?>><?= $r ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <input type="password" name="password" class="form-input mt-8" placeholder="<?= $editing?'Новый пароль (опционально)':'Пароль' ?>" <?= $editing?'':'required' ?>>
        <?php if ($editing): ?>
          <label class="mt-8" style="display:block"><input type="checkbox" name="is_active" value="1" <?= (int)$editing['is_active']?'checked':'' ?>> Активен</label>
        <?php endif; ?>
        <button class="btn btn-primary mt-16"><?= $editing?'Сохранить':'Создать' ?></button>
        <?php if ($editing): ?><a href="?" class="btn btn-outline">Отмена</a><?php endif; ?>
      </form>
    </div>

    <div class="admin-card">
      <h3 style="margin-bottom:14px">Все пользователи (<?= count($users) ?>)</h3>
      <div class="admin-table-wrap"><table class="admin-table">
        <thead><tr><th>ID</th><th>Имя</th><th>E-mail</th><th>Роль</th><th>Заказов</th><th>Активен</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td>#<?= (int)$u['id'] ?></td>
              <td><strong><?= sanitize($u['username']) ?></strong></td>
              <td><?= sanitize($u['email']) ?></td>
              <td><span class="role-badge <?= sanitize($u['role']) ?>"><?= sanitize($u['role']) ?></span></td>
              <td><?= (int)$u['orders_cnt'] ?></td>
              <td><span class="badge badge-<?= $u['is_active']?'success':'danger' ?>"><?= $u['is_active']?'Да':'Нет' ?></span></td>
              <td class="actions">
                <a href="?edit=<?= (int)$u['id'] ?>" class="btn btn-outline btn-sm">✏</a>
                <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>
                <form method="post" onsubmit="return confirm('Деактивировать?')" style="display:inline">
                  <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                  <button class="btn btn-outline btn-sm">🗑</button>
                </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
