<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['manager','admin','superadmin']);

$db = getDB();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $entity = $_POST['entity'] ?? '';
    $action = $_POST['action'] ?? '';
    if ($entity === 'make' && $action === 'create') {
        $db->prepare("INSERT INTO car_makes (name,slug,country) VALUES (?,?,?)")
            ->execute([trim($_POST['name']), trim($_POST['slug']), trim($_POST['country'] ?? '') ?: null]);
    }
    if ($entity === 'model' && $action === 'create') {
        $db->prepare("INSERT INTO car_models (make_id,name,slug,year_from,year_to,body_type) VALUES (?,?,?,?,?,?)")
            ->execute([(int)$_POST['make_id'], trim($_POST['name']), trim($_POST['slug']), (int)$_POST['year_from'], (int)$_POST['year_to'], trim($_POST['body_type'] ?? '') ?: null]);
    }
    if ($entity === 'make' && $action === 'delete') $db->prepare("DELETE FROM car_makes WHERE id=?")->execute([(int)$_POST['id']]);
    if ($entity === 'model' && $action === 'delete') $db->prepare("DELETE FROM car_models WHERE id=?")->execute([(int)$_POST['id']]);
    redirect(APP_URL . '/manager/cars.php');
}

$makes = $db->query("SELECT m.*, (SELECT COUNT(*) FROM car_models WHERE make_id=m.id) AS models_cnt FROM car_makes m ORDER BY m.name")->fetchAll();
$models = $db->query("SELECT cm.*, mk.name AS make FROM car_models cm JOIN car_makes mk ON mk.id=cm.make_id ORDER BY mk.name, cm.name LIMIT 200")->fetchAll();

$pageTitle = 'Автомобили';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>
<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Марки и модели <span class="dash-heading-badge">cars</span></h1>

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px">
      <div>
        <div class="admin-card mb-16">
          <h3 style="margin-bottom:14px">Добавить марку</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
            <input type="hidden" name="entity" value="make">
            <input type="hidden" name="action" value="create">
            <div class="form-row">
              <input type="text" name="name" placeholder="Название" class="form-input" required>
              <input type="text" name="slug" placeholder="slug" class="form-input" required>
            </div>
            <input type="text" name="country" placeholder="Страна" class="form-input mt-8">
            <button class="btn btn-primary mt-16">Добавить</button>
          </form>
        </div>
        <div class="admin-card">
          <h3 style="margin-bottom:14px">Все марки (<?= count($makes) ?>)</h3>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead><tr><th>Название</th><th>Страна</th><th>Моделей</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($makes as $m): ?>
                  <tr>
                    <td><strong><?= sanitize($m['name']) ?></strong></td>
                    <td><?= sanitize($m['country'] ?? '—') ?></td>
                    <td><?= (int)$m['models_cnt'] ?></td>
                    <td>
                      <form method="post" onsubmit="return confirm('Удалить?')" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                        <input type="hidden" name="entity" value="make">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button class="btn btn-outline btn-sm">🗑</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
      <div>
        <div class="admin-card mb-16">
          <h3 style="margin-bottom:14px">Добавить модель</h3>
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
            <input type="hidden" name="entity" value="model">
            <input type="hidden" name="action" value="create">
            <select name="make_id" class="form-select" required>
              <option value="">— Марка —</option>
              <?php foreach ($makes as $m): ?><option value="<?= (int)$m['id'] ?>"><?= sanitize($m['name']) ?></option><?php endforeach; ?>
            </select>
            <div class="form-row mt-8">
              <input type="text" name="name" placeholder="Название" class="form-input" required>
              <input type="text" name="slug" placeholder="slug" class="form-input" required>
            </div>
            <div class="form-row mt-8">
              <input type="number" name="year_from" placeholder="Год от" class="form-input" min="1900" max="2030" value="2010">
              <input type="number" name="year_to"   placeholder="Год до" class="form-input" min="1900" max="2030" value="2025">
            </div>
            <input type="text" name="body_type" placeholder="Тип кузова" class="form-input mt-8">
            <button class="btn btn-primary mt-16">Добавить</button>
          </form>
        </div>
        <div class="admin-card">
          <h3 style="margin-bottom:14px">Все модели (<?= count($models) ?>)</h3>
          <div class="admin-table-wrap" style="max-height:600px;overflow-y:auto">
            <table class="admin-table">
              <thead><tr><th>Марка</th><th>Модель</th><th>Годы</th><th></th></tr></thead>
              <tbody>
                <?php foreach ($models as $m): ?>
                  <tr>
                    <td><?= sanitize($m['make']) ?></td>
                    <td><?= sanitize($m['name']) ?></td>
                    <td><?= (int)$m['year_from'] ?>–<?= (int)$m['year_to'] ?></td>
                    <td>
                      <form method="post" onsubmit="return confirm('Удалить?')" style="display:inline">
                        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                        <input type="hidden" name="entity" value="model">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                        <button class="btn btn-outline btn-sm">🗑</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
