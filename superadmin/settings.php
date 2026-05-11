<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST: save settings ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/settings.php');
    }

    $fields = [
        'site_name', 'site_email', 'site_phone', 'site_phone2', 'site_address',
        'site_telegram', 'site_whatsapp', 'meta_description', 'meta_keywords',
        'items_per_page', 'default_lang', 'default_currency',
        'warehouse_api_url', 'warehouse_api_key',
    ];
    // Checkboxes
    $checkboxes = ['show_language_switcher', 'show_currency_switcher', 'warehouse_api_enabled'];

    foreach ($fields as $key) {
        $val = trim($_POST[$key] ?? '');
        $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
           ->execute([$key, $val, $val]);
    }
    foreach ($checkboxes as $key) {
        $val = isset($_POST[$key]) ? '1' : '0';
        $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
           ->execute([$key, $val, $val]);
    }

    flashMessage('success', 'Настройки сохранены.');
    redirect(APP_URL . '/superadmin/settings.php');
}

// ── Load all settings ─────────────────────────────────────────────────────────
$settingsRaw = $db->query("SELECT `key`, `value` FROM site_settings ORDER BY `key`")->fetchAll();
$settings = [];
foreach ($settingsRaw as $row) {
    $settings[$row['key']] = $row['value'];
}

// Load currencies for default_currency select
$currencies = [];
try {
    $currencies = $db->query("SELECT code, name_ru FROM currencies ORDER BY code")->fetchAll();
} catch (Exception $e) {}

function sv(array $s, string $k, string $default = ''): string {
    return sanitize($s[$k] ?? $default);
}

$pageTitle = 'Настройки сайта — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
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
      <a href="<?= APP_URL ?>/superadmin/settings.php" class="az-sidebar-link active" style="color:#ce93d8;"><i class="fa fa-cog"></i> Настройки</a>
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
      <div class="az-topbar-title" style="color:#6a1b9a;">Настройки сайта</div>
      <div class="az-topbar-user"><?= sanitize($_SESSION['username'] ?? 'Superadmin') ?> &middot; <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a></div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <form method="post" action="" style="max-width:800px;">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <!-- General settings -->
        <div class="az-card mb-24">
          <div class="az-card-header"><h4 class="az-card-title">Основные настройки</h4></div>
          <div class="az-card-body">
            <div class="row">
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Название сайта</label>
                  <input type="text" name="site_name" class="form-control" value="<?= sv($settings, 'site_name') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Email сайта</label>
                  <input type="email" name="site_email" class="form-control" value="<?= sv($settings, 'site_email') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Телефон (основной)</label>
                  <input type="text" name="site_phone" class="form-control" value="<?= sv($settings, 'site_phone') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Телефон (дополнительный)</label>
                  <input type="text" name="site_phone2" class="form-control" value="<?= sv($settings, 'site_phone2') ?>">
                </div>
              </div>
              <div class="col-12">
                <div class="az-form-group">
                  <label>Адрес</label>
                  <input type="text" name="site_address" class="form-control" value="<?= sv($settings, 'site_address') ?>">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Telegram (@username)</label>
                  <input type="text" name="site_telegram" class="form-control" value="<?= sv($settings, 'site_telegram') ?>" placeholder="username">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>WhatsApp (номер с кодом страны)</label>
                  <input type="text" name="site_whatsapp" class="form-control" value="<?= sv($settings, 'site_whatsapp') ?>" placeholder="79001234567">
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- SEO -->
        <div class="az-card mb-24">
          <div class="az-card-header"><h4 class="az-card-title">SEO</h4></div>
          <div class="az-card-body">
            <div class="az-form-group">
              <label>Meta Description</label>
              <textarea name="meta_description" class="form-control" rows="3"><?= sv($settings, 'meta_description') ?></textarea>
            </div>
            <div class="az-form-group">
              <label>Meta Keywords</label>
              <input type="text" name="meta_keywords" class="form-control" value="<?= sv($settings, 'meta_keywords') ?>">
            </div>
          </div>
        </div>

        <!-- Locale -->
        <div class="az-card mb-24">
          <div class="az-card-header"><h4 class="az-card-title">Локализация</h4></div>
          <div class="az-card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Товаров на странице</label>
                  <input type="number" name="items_per_page" class="form-control" min="4" max="100" value="<?= sv($settings, 'items_per_page', '12') ?>">
                </div>
              </div>
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Язык по умолчанию</label>
                  <select name="default_lang" class="form-control">
                    <option value="ru" <?= ($settings['default_lang'] ?? 'ru') === 'ru' ? 'selected' : '' ?>>Русский (ru)</option>
                    <option value="tg" <?= ($settings['default_lang'] ?? '') === 'tg' ? 'selected' : '' ?>>Тоҷикӣ (tg)</option>
                    <option value="en" <?= ($settings['default_lang'] ?? '') === 'en' ? 'selected' : '' ?>>English (en)</option>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Валюта по умолчанию</label>
                  <select name="default_currency" class="form-control">
                    <?php foreach ($currencies as $cur): ?>
                    <option value="<?= sanitize($cur['code']) ?>" <?= ($settings['default_currency'] ?? 'RUB') === $cur['code'] ? 'selected' : '' ?>>
                      <?= sanitize($cur['code']) ?> — <?= sanitize($cur['name_ru']) ?>
                    </option>
                    <?php endforeach; ?>
                    <?php if (empty($currencies)): ?>
                    <option value="RUB" selected>RUB — Российский рубль</option>
                    <?php endif; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <div class="form-check">
                    <input type="checkbox" name="show_language_switcher" id="show_lang" class="form-check-input"
                           value="1" <?= !empty($settings['show_language_switcher']) && $settings['show_language_switcher'] === '1' ? 'checked' : '' ?>>
                    <label for="show_lang" class="form-check-label">Показывать переключатель языка</label>
                  </div>
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <div class="form-check">
                    <input type="checkbox" name="show_currency_switcher" id="show_cur" class="form-check-input"
                           value="1" <?= !empty($settings['show_currency_switcher']) && $settings['show_currency_switcher'] === '1' ? 'checked' : '' ?>>
                    <label for="show_cur" class="form-check-label">Показывать переключатель валюты</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Warehouse API -->
        <div class="az-card mb-24">
          <div class="az-card-header"><h4 class="az-card-title">Склад API (Москва)</h4></div>
          <div class="az-card-body">
            <div class="az-form-group">
              <div class="form-check mb-16">
                <input type="checkbox" name="warehouse_api_enabled" id="wh_enabled" class="form-check-input"
                       value="1" <?= !empty($settings['warehouse_api_enabled']) && $settings['warehouse_api_enabled'] === '1' ? 'checked' : '' ?>>
                <label for="wh_enabled" class="form-check-label">Включить интеграцию со складом</label>
              </div>
            </div>
            <div class="az-form-group">
              <label>URL склада API</label>
              <input type="url" name="warehouse_api_url" class="form-control" value="<?= sv($settings, 'warehouse_api_url') ?>" placeholder="https://api.warehouse.ru/v1">
            </div>
            <div class="az-form-group">
              <label>API ключ</label>
              <input type="text" name="warehouse_api_key" class="form-control" value="<?= sv($settings, 'warehouse_api_key') ?>" placeholder="sk_live_...">
              <small class="text-muted">Ключ хранится в базе данных. Не передавайте его третьим лицам.</small>
            </div>
          </div>
        </div>

        <button type="submit" class="az-btn az-btn-primary" style="min-width:200px;">Сохранить настройки</button>
      </form>

      <!-- All settings table -->
      <div class="az-card mt-32" style="max-width:800px;">
        <div class="az-card-header"><h4 class="az-card-title">Все параметры в базе</h4></div>
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table" style="font-size:0.8rem;">
              <thead><tr><th>Ключ</th><th>Значение</th><th>Обновлён</th></tr></thead>
              <tbody>
                <?php foreach ($db->query("SELECT * FROM site_settings ORDER BY `key`")->fetchAll() as $row): ?>
                <tr>
                  <td><code><?= sanitize($row['key']) ?></code></td>
                  <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"><?= sanitize($row['value'] ?? '') ?></td>
                  <td style="color:#aaa;white-space:nowrap;"><?= date('d.m.Y H:i', strtotime($row['updated_at'] ?? 'now')) ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
