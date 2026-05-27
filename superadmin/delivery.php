<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['superadmin', 'admin', 'manager']);
requirePermission('delivery');

$db   = getDB();
$csrf = generateCsrfToken();

// Check if country column exists (migration may not be applied yet)
$hasCountryCol = false;
try {
    $hasCountryCol = (bool)$db->query(
        "SELECT COUNT(*) FROM information_schema.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'delivery_zones' AND COLUMN_NAME = 'country'"
    )->fetchColumn();
} catch (Throwable $e) {}

$countryList = ['Таджикистан', 'Узбекистан', 'Кыргызстан', 'Казахстан', 'Россия', 'Афганистан', 'Туркменистан'];

// ── POST handler ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/delivery.php');
    }

    $postAction = $_POST['action'] ?? '';

    if ($postAction === 'add') {
        $city    = trim($_POST['city'] ?? '');
        $cost    = max(0, (float)($_POST['cost'] ?? 0));
        $days    = trim($_POST['delivery_days'] ?? '');
        $country = in_array($_POST['country'] ?? '', $countryList, true) ? $_POST['country'] : 'Таджикистан';
        if ($city === '') {
            flashMessage('danger', 'Укажите название города.');
        } else {
            try {
                if ($hasCountryCol) {
                    $db->prepare("INSERT INTO delivery_zones (city, country, cost, delivery_days, is_active, sort_order) VALUES (?,?,?,?,1,99)")
                       ->execute([$city, $country, $cost, $days ?: null]);
                } else {
                    $db->prepare("INSERT INTO delivery_zones (city, cost, delivery_days, is_active, sort_order) VALUES (?,?,?,1,99)")
                       ->execute([$city, $cost, $days ?: null]);
                }
                flashMessage('success', "Город «{$city}» ({$country}) добавлен.");
            } catch (PDOException $e) {
                flashMessage('danger', 'Такой город уже есть в списке для этой страны.');
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
    $countryExpr = $hasCountryCol ? "COALESCE(country,'Таджикистан')" : "'Таджикистан'";
    $orderBy     = $hasCountryCol ? 'country, sort_order, city' : 'sort_order, city';
    $zones = $db->query("SELECT *, {$countryExpr} AS _country FROM delivery_zones ORDER BY {$orderBy}")->fetchAll();
} catch (Exception $e) {
    flashMessage('danger', 'Таблица доставки не найдена. Примените миграцию sql/add_delivery_zones.sql.');
}

// Preset cities organised by country for JS datalist
$presetCitiesByCountry = [
    'Таджикистан'  => ['Душанбе','Худжанд','Бохтар','Куляб','Истаравшан','Турсунзаде','Канибадам','Исфара','Пенджикент','Вахдат','Хорог','Гиссар','Яван','Нурек','Дангара','Истиклол','Рашт','Бустон','Леваканд','Спитамен','Гулистон','Турсунзода','Кушониён','Норак'],
    'Узбекистан'   => ['Ташкент','Самарканд','Бухара','Наманган','Андижан','Фергана','Нукус','Карши','Термез','Коканд','Маргилан','Навои'],
    'Кыргызстан'   => ['Бишкек','Ош','Джалал-Абад','Каракол','Токмок','Узген','Нарын','Талас'],
    'Казахстан'    => ['Алматы','Астана','Шымкент','Актобе','Тараз','Усть-Каменогорск','Павлодар','Семей','Актау'],
    'Россия'       => ['Москва','Санкт-Петербург','Новосибирск','Екатеринбург','Нижний Новгород','Казань','Уфа','Челябинск','Омск','Самара'],
    'Афганистан'   => ['Кабул','Мазари-Шариф','Герат','Кандагар','Джалалабад','Кундуз'],
    'Туркменистан' => ['Ашхабад','Туркменабат','Дашогуз','Мары','Балканабат'],
];

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

      <?php if (!$hasCountryCol): ?>
      <div class="alert alert-warning mb-16">
        Для поддержки стран примените миграцию:<br>
        <code>mysql -u cs360870_auto -p cs360870_auto &lt; sql/add_delivery_zones_country.sql</code>
      </div>
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
                    <?php if ($hasCountryCol): ?><th style="width:140px;">Страна</th><?php endif; ?>
                    <th style="width:160px;">Стоимость (сомони)</th>
                    <th style="width:160px;">Срок (напр. «1–2 дня»)</th>
                    <th style="text-align:center;">Статус</th>
                    <th style="text-align:center;">Вкл/Откл</th>
                    <th style="text-align:center;">Удалить</th>
                  </tr>
                </thead>
                <tbody>
                  <?php
                  $lastCountry = null;
                  foreach ($zones as $z):
                      $zCountry = $z['_country'] ?? 'Таджикистан';
                      if ($hasCountryCol && $zCountry !== $lastCountry):
                          $lastCountry = $zCountry;
                  ?>
                  <tr style="background:#f3eaff;">
                    <td colspan="7" style="padding:6px 12px;font-size:0.78rem;font-weight:700;color:#7b1fa2;letter-spacing:.04em;">
                      <?= sanitize($zCountry) ?>
                    </td>
                  </tr>
                  <?php endif; ?>
                  <tr <?= !$z['is_active'] ? 'style="opacity:0.55;"' : '' ?>>
                    <td>
                      <strong><?= sanitize($z['city']) ?></strong>
                      <input type="hidden" name="zone_id[]" value="<?= (int)$z['id'] ?>">
                    </td>
                    <?php if ($hasCountryCol): ?>
                    <td><small style="color:#888;"><?= sanitize($zCountry) ?></small></td>
                    <?php endif; ?>
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
                  <tr><td colspan="<?= $hasCountryCol ? 7 : 6 ?>" style="text-align:center;color:#999;padding:24px;">Городов пока нет. Добавьте ниже.</td></tr>
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
      <div class="az-card mb-24" style="max-width:700px;">
        <div class="az-card-header"><h4 class="az-card-title">Добавить город</h4></div>
        <div class="az-card-body">
          <form method="post" action="" style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end;">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="add">
            <div style="flex:0 1 160px;">
              <label style="font-size:0.8rem;color:#666;">Страна *</label>
              <select name="country" id="add-country" class="form-control form-control-sm">
                <?php foreach ($countryList as $cn): ?>
                <option value="<?= sanitize($cn) ?>"><?= sanitize($cn) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div style="flex:1 1 180px;">
              <label style="font-size:0.8rem;color:#666;">Город *</label>
              <input type="text" name="city" id="add-city" list="preset-cities" required
                     class="form-control form-control-sm" placeholder="Выберите или введите" autocomplete="off">
              <datalist id="preset-cities"></datalist>
            </div>
            <div style="flex:0 1 120px;">
              <label style="font-size:0.8rem;color:#666;">Стоимость</label>
              <input type="number" name="cost" step="0.01" min="0" value="0" class="form-control form-control-sm">
            </div>
            <div style="flex:0 1 120px;">
              <label style="font-size:0.8rem;color:#666;">Срок</label>
              <input type="text" name="delivery_days" maxlength="40" class="form-control form-control-sm" placeholder="1–2 дня">
            </div>
            <button type="submit" class="az-btn az-btn-primary az-btn-sm">Добавить</button>
          </form>
        </div>
      </div>

      <!-- Info -->
      <div class="az-card" style="max-width:700px;border-left:3px solid #9b59b6;">
        <div class="az-card-body">
          <h5 style="color:#6a1b9a;margin-bottom:8px;">Как это работает</h5>
          <p style="font-size:0.85rem;color:#555;line-height:1.7;margin:0;">
            Покупатель выбирает страну и город при оформлении заказа.<br>
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

<script>
(function () {
    var cities = <?= json_encode($presetCitiesByCountry, JSON_UNESCAPED_UNICODE) ?>;
    var countrySel = document.getElementById('add-country');
    var datalist   = document.getElementById('preset-cities');
    var cityInput  = document.getElementById('add-city');

    function fillDatalist() {
        var list = cities[countrySel.value] || [];
        datalist.innerHTML = list.map(function(c) {
            return '<option value="' + c.replace(/"/g,'&quot;') + '"></option>';
        }).join('');
        cityInput.value = '';
    }

    if (countrySel) {
        countrySel.addEventListener('change', fillDatalist);
        fillDatalist();
    }
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
