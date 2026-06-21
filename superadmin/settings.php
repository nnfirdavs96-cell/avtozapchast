<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['superadmin', 'admin', 'manager']);
requirePermission('settings');

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST: save settings ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.'); redirect(APP_URL . '/superadmin/settings.php');
    }

    $fields = [
        'site_name', 'site_email', 'site_phone', 'site_phone2', 'site_address',
        'site_telegram', 'site_whatsapp', 'site_instagram', 'site_facebook',
        'site_youtube', 'site_tiktok',
        'meta_description', 'meta_keywords',
        'items_per_page', 'default_lang', 'default_currency', 'slider_interval_sec',
        'warehouse_api_url', 'warehouse_api_key',
        'map_lat', 'map_lng', 'map_zoom',
        'global_markup',
    ];
    // Checkboxes
    $checkboxes = ['show_language_switcher', 'show_currency_switcher', 'warehouse_api_enabled', 'auth_email_enabled'];

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

    // Phone countries (multi-select) — stored as comma-separated codes; Tajikistan always kept.
    $pc = $_POST['phone_countries'] ?? [];
    if (!is_array($pc)) $pc = [];
    $validCodes = array_column(phoneCountriesCatalog(), 'code');
    $pc = array_values(array_intersect($pc, $validCodes));
    if (!in_array('tj', $pc, true)) array_unshift($pc, 'tj');
    $pcVal = implode(',', $pc);
    $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES ('phone_countries', ?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()")
       ->execute([$pcVal, $pcVal]);

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
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('settings'); ?>

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

              <!-- Страны для выбора в поле телефона -->
              <div class="col-12">
                <div class="az-form-group">
                  <label>Страны в поле телефона</label>
                  <p style="font-size:0.82rem;color:#888;margin:0 0 8px;">
                    Таджикистан включён всегда. Отметьте другие страны — они появятся в выпадающем списке выбора кода (например, когда станет доступна доставка в эти страны).
                  </p>
                  <?php
                    $enabledCodes = array_filter(array_map('trim', explode(',', $settings['phone_countries'] ?? 'tj')));
                  ?>
                  <div class="pcms" id="phoneCountriesMs">
                    <button type="button" class="pcms-toggle" id="pcmsToggle">
                      <span class="pcms-label">Выбрано стран: <strong id="pcmsCount">0</strong></span>
                      <span class="pcms-caret">▾</span>
                    </button>
                    <div class="pcms-panel" id="pcmsPanel">
                      <input type="text" class="pcms-search" id="pcmsSearch" placeholder="Поиск страны…" autocomplete="off">
                      <div class="pcms-options">
                        <?php foreach (phoneCountriesCatalog() as $pc):
                            $checked = in_array($pc['code'], $enabledCodes, true) || $pc['code']==='tj';
                            $isTj    = $pc['code']==='tj';
                        ?>
                        <label class="pcms-option<?= $isTj ? ' is-locked' : '' ?>" data-name="<?= sanitize(mb_strtolower($pc['name'].' '.$pc['dial'].' '.$pc['code'])) ?>">
                          <input type="checkbox" name="phone_countries[]" value="<?= sanitize($pc['code']) ?>"
                                 <?= $checked ? 'checked' : '' ?> <?= $isTj ? 'disabled' : '' ?>>
                          <img class="pcms-flag" src="https://flagcdn.com/w40/<?= sanitize($pc['code']) ?>.png" alt="" width="24" height="18" loading="lazy">
                          <span class="pcms-name"><?= sanitize($pc['name']) ?></span>
                          <span class="pcms-dial">+<?= sanitize($pc['dial']) ?></span>
                          <?php if ($isTj): ?><span class="pcms-lock">всегда</span><?php endif; ?>
                        </label>
                        <?php endforeach; ?>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <style>
                .pcms { position: relative; max-width: 420px; }
                .pcms-toggle {
                    width: 100%; display: flex; align-items: center; justify-content: space-between;
                    padding: 10px 14px; border: 1px solid #ccd2da; border-radius: 8px; background: #fff;
                    cursor: pointer; font-size: 0.92rem; color: #333;
                }
                .pcms-toggle:hover { border-color: #999; }
                .pcms-caret { transition: transform .15s; color: #888; }
                .pcms.open .pcms-caret { transform: rotate(180deg); }
                .pcms-panel {
                    display: none; position: absolute; z-index: 30; top: calc(100% + 4px); left: 0; right: 0;
                    background: #fff; border: 1px solid #ccd2da; border-radius: 8px;
                    box-shadow: 0 8px 24px rgba(0,0,0,.12); padding: 8px;
                }
                .pcms.open .pcms-panel { display: block; }
                .pcms-search {
                    width: 100%; padding: 8px 10px; border: 1px solid #e0e0e0; border-radius: 6px;
                    margin-bottom: 6px; font-size: 0.88rem;
                }
                .pcms-options { max-height: 280px; overflow-y: auto; }
                .pcms-option {
                    display: flex; align-items: center; gap: 8px; padding: 7px 8px; border-radius: 6px;
                    cursor: pointer; font-weight: 400; margin: 0;
                }
                .pcms-option:hover { background: #f4f6f9; }
                .pcms-option.is-locked { opacity: .65; cursor: default; }
                .pcms-flag { display: block; border-radius: 2px; object-fit: cover; flex: 0 0 auto; }
                .pcms-name { flex: 1 1 auto; }
                .pcms-dial { color: #888; font-size: 0.85rem; }
                .pcms-lock {
                    font-size: 0.7rem; color: #fff; background: #C70909; border-radius: 10px;
                    padding: 1px 8px; text-transform: uppercase;
                }
              </style>
              <script>
                (function () {
                    var ms     = document.getElementById('phoneCountriesMs');
                    var toggle = document.getElementById('pcmsToggle');
                    var panel  = document.getElementById('pcmsPanel');
                    var search = document.getElementById('pcmsSearch');
                    var count  = document.getElementById('pcmsCount');
                    if (!ms) return;
                    var boxes  = panel.querySelectorAll('input[type="checkbox"]');
                    function refreshCount() {
                        var n = 0;
                        boxes.forEach(function (b) { if (b.checked) n++; });
                        count.textContent = n;
                    }
                    toggle.addEventListener('click', function () { ms.classList.toggle('open'); search.focus(); });
                    document.addEventListener('click', function (e) {
                        if (!ms.contains(e.target)) ms.classList.remove('open');
                    });
                    boxes.forEach(function (b) { b.addEventListener('change', refreshCount); });
                    search.addEventListener('input', function () {
                        var q = this.value.trim().toLowerCase();
                        panel.querySelectorAll('.pcms-option').forEach(function (opt) {
                            opt.style.display = (opt.getAttribute('data-name') || '').indexOf(q) !== -1 ? '' : 'none';
                        });
                    });
                    refreshCount();
                })();
              </script>

              <!-- Карта -->
              <div class="col-12">
                <div class="az-form-group">
                  <label style="font-weight:700">📍 Метка на карте (страница Контакты)</label>
                  <div class="row">
                    <div class="col-md-4">
                      <label style="font-size:0.85rem;color:#666">Широта (Latitude)</label>
                      <input type="text" name="map_lat" class="form-control" value="<?= sv($settings, 'map_lat', '40.29864545672122') ?>" placeholder="40.29864545672122">
                    </div>
                    <div class="col-md-4">
                      <label style="font-size:0.85rem;color:#666">Долгота (Longitude)</label>
                      <input type="text" name="map_lng" class="form-control" value="<?= sv($settings, 'map_lng', '69.6142315387528') ?>" placeholder="69.6142315387528">
                    </div>
                    <div class="col-md-4">
                      <label style="font-size:0.85rem;color:#666">Масштаб (15–18)</label>
                      <input type="number" name="map_zoom" class="form-control" value="<?= sv($settings, 'map_zoom', '16') ?>" min="10" max="20" placeholder="16">
                    </div>
                  </div>
                  <small class="text-muted">Откройте <a href="https://maps.google.com" target="_blank">Google Maps</a>, найдите нужное место, нажмите правой кнопкой → "Что здесь?" — скопируйте координаты.</small>
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
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Instagram (username или полная ссылка)</label>
                  <input type="text" name="site_instagram" class="form-control" value="<?= sv($settings, 'site_instagram') ?>" placeholder="avtodoc или https://instagram.com/avtodoc">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>Facebook (username или полная ссылка)</label>
                  <input type="text" name="site_facebook" class="form-control" value="<?= sv($settings, 'site_facebook') ?>" placeholder="avtodoc или https://facebook.com/avtodoc">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>YouTube (username или полная ссылка)</label>
                  <input type="text" name="site_youtube" class="form-control" value="<?= sv($settings, 'site_youtube') ?>" placeholder="@avtodoc или https://youtube.com/@avtodoc">
                </div>
              </div>
              <div class="col-md-6">
                <div class="az-form-group">
                  <label>TikTok (username или полная ссылка)</label>
                  <input type="text" name="site_tiktok" class="form-control" value="<?= sv($settings, 'site_tiktok') ?>" placeholder="@avtodoc или https://tiktok.com/@avtodoc">
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
                  <label>Смена слайдов на главной, сек</label>
                  <input type="number" name="slider_interval_sec" class="form-control" min="2" max="60" step="1" value="<?= sv($settings, 'slider_interval_sec', '5') ?>">
                  <small class="text-muted">Через сколько секунд главный слайдер меняет слайд (2–60).</small>
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

        <!-- Авторизация / вход -->
        <div class="az-card mb-24">
          <div class="az-card-header"><h4 class="az-card-title">Авторизация и вход</h4></div>
          <div class="az-card-body">
            <div class="az-form-group">
              <div class="form-check mb-8">
                <input type="checkbox" name="auth_email_enabled" id="auth_email" class="form-check-input"
                       value="1" <?= (!isset($settings['auth_email_enabled']) || $settings['auth_email_enabled'] === '1') ? 'checked' : '' ?>>
                <label for="auth_email" class="form-check-label">Разрешить вход и регистрацию по email + паролю</label>
              </div>
              <small style="color:#888;display:block;">
                Если выключить — покупатели смогут входить и регистрироваться только по номеру телефона
                (SMS-код). Сотрудники входят по номеру телефона и PIN-коду (задаётся в разделе
                «Пользователи»). Для сотрудников остаётся резервный вход по email на странице
                <code><?= APP_URL ?>/auth/login.php?staff=1</code>.
              </small>
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

        <!-- Наценка товаров -->
        <div class="az-card mb-24">
          <div class="az-card-header"><h4 class="az-card-title">Наценка товаров</h4></div>
          <div class="az-card-body">
            <div class="row">
              <div class="col-md-4">
                <div class="az-form-group">
                  <label>Глобальная наценка (%)</label>
                  <input type="number" name="global_markup" class="form-control" min="0" max="1000" step="0.01"
                         value="<?= sv($settings, 'global_markup', '0') ?>" placeholder="0">
                  <small class="text-muted">
                    Применяется ко всем товарам, у которых не задана собственная или категорийная наценка.<br>
                    <strong>Приоритет:</strong> наценка товара &gt; наценка категории &gt; глобальная наценка.
                  </small>
                </div>
              </div>
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

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
