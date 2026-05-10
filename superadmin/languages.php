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
        // Don't deactivate the default language
        $isDefault = (int)$db->prepare("SELECT is_default FROM languages WHERE code = ?")
            ->execute([$code]) ? 0 : 0;
        $stmt = $db->prepare("SELECT is_default FROM languages WHERE code = ?");
        $stmt->execute([$code]);
        $row = $stmt->fetch();
        if ($row && $row['is_default'] && !$active) {
            flashMessage('danger', 'Нельзя деактивировать язык по умолчанию.');
        } else {
            $db->prepare("UPDATE languages SET is_active = ?, updated_at = NOW() WHERE code = ?")
               ->execute([$active, $code]);
            flashMessage('success', 'Статус языка обновлён.');
        }
        redirect(APP_URL . '/superadmin/languages.php');
    }

    if ($postAction === 'set_default' && $code) {
        $db->exec("UPDATE languages SET is_default = 0");
        $db->prepare("UPDATE languages SET is_default = 1, is_active = 1, updated_at = NOW() WHERE code = ?")
           ->execute([$code]);
        // Also update site_settings default_lang
        $db->prepare("INSERT INTO site_settings (`key`, `value`) VALUES ('default_lang', ?) ON DUPLICATE KEY UPDATE `value` = ?, updated_at = NOW()")
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
    // Table may not exist yet — show static list
    $languages = [];
}

$langMeta = [
    'ru' => ['name' => 'Русский',  'native' => 'Русский',  'flag' => '🇷🇺'],
    'tg' => ['name' => 'Таджикский', 'native' => 'Тоҷикӣ', 'flag' => '🇹🇯'],
    'en' => ['name' => 'Английский', 'native' => 'English', 'flag' => '🇬🇧'],
];

$pageTitle = 'Управление языками — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
require_once dirname(__DIR__) . '/includes/nav.php';
?>

<div class="dash-layout">
  <div class="dash-sidebar"><?php renderNav(); ?></div>
  <div class="dash-main">
    <div class="dash-heading">ЯЗЫКИ</div>

    <div class="card mb-24" style="max-width:700px;">
      <div class="card-header">
        <h3>УПРАВЛЕНИЕ ЯЗЫКАМИ САЙТА</h3>
      </div>
      <?php if (!empty($languages)): ?>
      <div class="table-wrap" style="border:none;border-radius:0;">
        <table class="data-table">
          <thead>
            <tr>
              <th>Код</th>
              <th>Название</th>
              <th>Родное название</th>
              <th>По умолчанию</th>
              <th>Статус</th>
              <th>Действия</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($languages as $lang):
              $meta = $langMeta[$lang['code']] ?? ['name' => $lang['code'], 'native' => $lang['code'], 'flag' => '🌐'];
            ?>
            <tr>
              <td>
                <span class="mono" style="font-weight:700;font-size:1rem;">
                  <?= sanitize($lang['code']) ?>
                </span>
              </td>
              <td style="font-size:0.875rem;"><?= sanitize($lang['name'] ?? $meta['name']) ?></td>
              <td style="font-size:0.875rem;color:var(--text-secondary);"><?= sanitize($meta['native']) ?></td>
              <td style="text-align:center;">
                <?php if ($lang['is_default']): ?>
                <span class="badge badge-success">По умолч.</span>
                <?php else: ?>
                <form method="post" style="display:inline;">
                  <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                  <input type="hidden" name="action" value="set_default">
                  <input type="hidden" name="code" value="<?= sanitize($lang['code']) ?>">
                  <button type="submit" class="btn btn-outline btn-sm">Сделать</button>
                </form>
                <?php endif; ?>
              </td>
              <td style="text-align:center;">
                <span class="badge <?= $lang['is_active'] ? 'badge-success' : 'badge-danger' ?>">
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
                  <button type="submit" class="btn btn-sm <?= $lang['is_active'] ? 'btn-danger' : 'btn-success' ?>">
                    <?= $lang['is_active'] ? 'Отключить' : 'Включить' ?>
                  </button>
                </form>
                <?php else: ?>
                <span style="font-size:0.75rem;color:var(--text-muted);">Основной язык</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <!-- Static list if no DB table -->
      <div class="card-body">
        <p class="form-help mb-16">Таблица <code>languages</code> не найдена. Ниже — список поддерживаемых языков платформы.</p>
        <table class="data-table">
          <thead><tr><th>Код</th><th>Название</th><th>Файл перевода</th><th>Статус</th></tr></thead>
          <tbody>
            <?php foreach ($langMeta as $code => $meta): ?>
            <tr>
              <td><span class="mono"><?= $code ?></span></td>
              <td><?= sanitize($meta['name']) ?> (<?= sanitize($meta['native']) ?>)</td>
              <td><span class="mono">/lang/<?= $code ?>.php</span></td>
              <td>
                <span class="badge <?= file_exists(dirname(__DIR__) . '/lang/' . $code . '.php') ? 'badge-success' : 'badge-danger' ?>">
                  <?= file_exists(dirname(__DIR__) . '/lang/' . $code . '.php') ? 'Файл есть' : 'Нет файла' ?>
                </span>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>

    <div class="card" style="max-width:700px;border-color:var(--accent-glow);">
      <div class="card-body">
        <div class="label-mono mb-8" style="color:var(--accent);">// Справка</div>
        <p style="font-size:0.85rem;color:var(--text-secondary);line-height:1.7;">
          Активные языки доступны пользователям в переключателе языков в шапке сайта.<br>
          Язык по умолчанию используется для новых посетителей и как язык интерфейса при недоступности перевода.<br>
          Файлы переводов расположены в <code>/lang/{код}.php</code>.
        </p>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
