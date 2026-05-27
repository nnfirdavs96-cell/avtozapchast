<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/delivery.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $city = trim($_POST['city'] ?? '');
        $cost = max(0, (float)($_POST['cost'] ?? 0));
        $days = trim($_POST['delivery_days'] ?? '');
        if ($city === '') {
            flashMessage('danger', 'Укажите название города.');
        } else {
            try {
                $db->prepare("INSERT INTO delivery_zones (city, cost, delivery_days, is_active, sort_order) VALUES (?,?,?,1,99)")
                   ->execute([$city, $cost, $days ?: null]);
                flashMessage('success', "Город «{$city}» добавлен.");
            } catch (PDOException $e) {
                flashMessage('danger', 'Такой город уже есть в списке.');
            }
        }
        redirect(APP_URL . '/superadmin/delivery.php');
    }

    if ($postAction === 'bulk_update') {
        $ids   = $_POST['zone_id']   ?? [];
        $costs = $_POST['zone_cost'] ?? [];
        $days  = $_POST['zone_days'] ?? [];
        $count = 0;
        foreach ($ids as $i => $id) {
            $id   = (int)$id;
            $cost = max(0, (float)($costs[$i] ?? 0));
            $d    = trim($days[$i] ?? '');
            if ($id > 0) {
                $db->prepare("UPDATE delivery_zones SET cost = ?, delivery_days = ? WHERE id = ?")
                   ->execute([$cost, $d ?: null, $id]);
                $count++;
            }
        }
        flashMessage('success', "Обновлено городов: {$count}.");
        redirect(APP_URL . '/superadmin/delivery.php');
    }

    if ($postAction === 'toggle_active') {
        $id     = (int)($_POST['id'] ?? 0);
        $active = (int)($_POST['is_active'] ?? 0);
        if ($id > 0) {
            $db->prepare("UPDATE delivery_zones SET is_active = ? WHERE id = ?")->execute([$active, $id]);
            flashMessage('success', 'Статус города обновлён.');
        }
        redirect(APP_URL . '/superadmin/delivery.php');
    }

    if ($postAction === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $db->prepare("DELETE FROM delivery_zones WHERE id = ?")->execute([$id]);
            flashMessage('success', 'Город удалён.');
        }
        redirect(APP_URL . '/superadmin/delivery.php');
    }

    redirect(APP_URL . '/superadmin/delivery.php');
}

// ── Load zones ─────────────────────────────────────────────────────────────────
$zones = [];
try {
    $zones = $db->query("SELECT * FROM delivery_zones ORDER BY sort_order, city")->fetchAll();
} catch (Exception $e) {
    flashMessage('danger', 'Таблица доставки не найдена. Примените миграцию sql/add_delivery_zones.sql.');
}

$pageTitle = 'Доставка — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('delivery'); ?>

  <div class="az-main">
    <div class="az-topbar" style="border-bottom-color:#9b59b622;background:#f9f5ff;">
      <div class="az-topbar-title" style="color:#6a1b9a;">Доставка по городам</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <!-- Zones table -->
      <div class="az-card mb-24">
        <div class="az-card-header">
          <h4 class="az-card-title">Города и стоимость доставки <small style="font-weight:400;color:#888;font-size:0.8rem;">(цена в сомони)</small></h4>
        </div>
        <div class="az-card-body p-0">
          <form method="post" action="">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="bulk_update">
            <div class="table-responsive">
              <table class="az-table">
                <thead>
                  <tr>
                    <th>Город</th>
                    <th style="width:160px;">Стоимость (сомони)</th>
                    <th style="width:160px;">Срок (напр. «1–2 дня»)</th>
                    <th style="text-align:center;">Статус</th>
                    <th style="text-align:center;">Вкл/Откл</th>
                    <th style="text-align:center;">Удалить</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($zones as $z): ?>
                  <tr <?= !$z['is_active'] ? 'style="opacity:0.55;"' : '' ?>>
                    <td>
                      <strong><?= sanitize($z['city']) ?></strong>
                      <input type="hidden" name="zone_id[]" value="<?= (int)$z['id'] ?>">
                    </td>
                    <td>
                      <input type="number" name="zone_cost[]" step="0.01" min="0"
                             value="<?= number_format((float)$z['cost'], 2, '.', '') ?>"
                             class="form-control form-control-sm" style="font-family:monospace;">
                    </td>
                    <td>
                      <input type="text" name="zone_days[]" maxlength="40"
                             value="<?= sanitize($z['delivery_days'] ?? '') ?>"
                             class="form-control form-control-sm" placeholder="—">
                    </td>
                    <td style="text-align:center;">
                      <?php if ((float)$z['cost'] <= 0): ?>
                        <span class="badge badge-warning">Уточняется</span>
                      <?php else: ?>
                        <span class="badge badge-success"><?= number_format((float)$z['cost'], 2, '.', ',') ?> смн</span>
                      <?php endif; ?>
                    </td>
                    <td style="text-align:center;">
                      <button type="submit" form="zone-toggle-<?= (int)$z['id'] ?>"
                              class="az-btn az-btn-sm <?= $z['is_active'] ? 'az-btn-danger' : 'az-btn-success' ?>">
                        <?= $z['is_active'] ? 'Откл.' : 'Вкл.' ?>
                      </button>
                    </td>
                    <td style="text-align:center;">
                      <button type="submit" form="zone-del-<?= (int)$z['id'] ?>"
                              class="az-btn az-btn-sm az-btn-outline"
                              onclick="return confirm('Удалить город «<?= sanitize($z['city']) ?>»?');">
                        <i class="fa fa-trash"></i>
                      </button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                  <?php if (empty($zones)): ?>
                  <tr><td colspan="6" style="text-align:center;color:#999;padding:24px;">Городов пока нет. Добавьте ниже.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
            <?php if (!empty($zones)): ?>
            <div style="padding:16px;">
              <button type="submit" class="az-btn az-btn-primary">Сохранить цены и сроки</button>
            </div>
            <?php endif; ?>
          </form>

          <?php foreach ($zones as $z): ?>
          <form method="post" id="zone-toggle-<?= (int)$z['id'] ?>" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="toggle_active">
            <input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
            <input type="hidden" name="is_active" value="<?= $z['is_active'] ? 0 : 1 ?>">
          </form>
          <form method="post" id="zone-del-<?= (int)$z['id'] ?>" style="display:none;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$z['id'] ?>">
          </form>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Add city -->
      <div class="az-card mb-24" style="max-width:600px;">
        <div class="az-card-header"><h4 class="az-card-title">Добавить город</h4></div>
        <div class="az-card-body">
          <form method="post" action="" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <div style="flex:1 1 180px;">
              <label style="font-size:0.8rem;color:#666;">Город *</label>
              <input type="text" name="city" required class="form-control form-control-sm" placeholder="Напр. Душанбе">
            </div>
            <div style="flex:0 1 140px;">
              <label style="font-size:0.8rem;color:#666;">Стоимость</label>
              <input type="number" name="cost" step="0.01" min="0" value="0" class="form-control form-control-sm">
            </div>
            <div style="flex:0 1 140px;">
              <label style="font-size:0.8rem;color:#666;">Срок</label>
              <input type="text" name="delivery_days" maxlength="40" class="form-control form-control-sm" placeholder="1–2 дня">
            </div>
            <button type="submit" class="az-btn az-btn-primary az-btn-sm">Добавить</button>
          </form>
        </div>
      </div>

      <!-- Info -->
      <div class="az-card" style="max-width:600px;border-left:3px solid #9b59b6;">
        <div class="az-card-body">
          <h5 style="color:#6a1b9a;margin-bottom:8px;">Как это работает</h5>
          <p style="font-size:0.85rem;color:#555;line-height:1.7;margin:0;">
            Покупатель выбирает город при оформлении заказа.<br>
            <strong>Стоимость = 0</strong> → доставка показывается как «Уточняется» и
            <em>не прибавляется</em> к сумме (актуально, пока нет договора с такси).<br>
            <strong>Стоимость &gt; 0</strong> → сумма доставки прибавляется к заказу автоматически.<br>
            Отключённые города не показываются покупателю.
          </p>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
