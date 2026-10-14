<?php
/**
 * Справочник автомобилей и подбор запчастей по авто.
 *
 * Иерархия: марка (vehicle_makes) → модель (vehicle_models) → модификация
 * (vehicle_mods: годы, двигатель, кузов). Связь товар↔авто — many-to-many в
 * part_vehicles: ссылка на МОДЕЛЬ (mod_id=NULL = подходит ко всем годам) или на
 * конкретную модификацию. needs_review=1 — связь поставлена авто-скриптом и ждёт
 * проверки. Схема создаётся миграцией sql/migrate_vehicles.php и лениво здесь.
 */

/** slug из названия (латиница/цифры/дефис): «Mercedes-Benz»→mercedes-benz, «RX350»→rx350. */
function vehSlug(string $s): string
{
    $s = trim($s);
    // Транслитерация кириллицы на всякий случай (модели обычно латиницей).
    $translit = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
    if ($translit !== false && $translit !== '') $s = $translit;
    $s = strtolower($s);
    $s = preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

/** Ленивая гарантия схемы — чтобы страницы не падали до миграции. */
function ensureVehicleSchema(): void
{
    static $done = false;
    if ($done) return;
    try {
        $db = getDB();
        $db->exec("CREATE TABLE IF NOT EXISTS `vehicle_makes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(80) NOT NULL,
            `slug` VARCHAR(80) NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`), UNIQUE KEY `uk_make_slug` (`slug`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `vehicle_models` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `make_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(100) NOT NULL,
            `slug` VARCHAR(100) NOT NULL,
            `sort_order` INT NOT NULL DEFAULT 0,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_model` (`make_id`, `slug`),
            KEY `idx_make` (`make_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `vehicle_mods` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `model_id` INT UNSIGNED NOT NULL,
            `name` VARCHAR(120) NOT NULL DEFAULT '',
            `year_from` SMALLINT UNSIGNED DEFAULT NULL,
            `year_to` SMALLINT UNSIGNED DEFAULT NULL,
            `engine` VARCHAR(60) DEFAULT NULL,
            `volume` DECIMAL(3,1) DEFAULT NULL,
            `fuel` VARCHAR(20) DEFAULT NULL,
            `body` VARCHAR(60) DEFAULT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`),
            KEY `idx_model` (`model_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        // для уже созданных таблиц — добавить недостающие колонки
        if (function_exists('dbAddColumnIfMissing')) {
            dbAddColumnIfMissing($db, 'vehicle_mods', 'volume', "`volume` DECIMAL(3,1) DEFAULT NULL AFTER `engine`");
            dbAddColumnIfMissing($db, 'vehicle_mods', 'fuel',   "`fuel` VARCHAR(20) DEFAULT NULL AFTER `volume`");
        }

        // Заявки продавцов на добавление авто (которых нет в справочнике).
        $db->exec("CREATE TABLE IF NOT EXISTS `vehicle_requests` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `seller_id` INT UNSIGNED DEFAULT NULL,
            `make` VARCHAR(80) NOT NULL,
            `model` VARCHAR(100) NOT NULL,
            `years` VARCHAR(40) DEFAULT NULL,
            `engine` VARCHAR(60) DEFAULT NULL,
            `status` ENUM('new','done','rejected') NOT NULL DEFAULT 'new',
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_status` (`status`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $db->exec("CREATE TABLE IF NOT EXISTS `part_vehicles` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `part_id` INT UNSIGNED NOT NULL,
            `model_id` INT UNSIGNED NOT NULL,
            `mod_id` INT UNSIGNED DEFAULT NULL,
            `needs_review` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_part` (`part_id`),
            KEY `idx_model` (`model_id`),
            KEY `idx_mod` (`mod_id`),
            KEY `idx_review` (`needs_review`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        $done = true;
    } catch (Throwable $e) { /* не роняем страницу */ }
}

/** Готова ли схема (таблица part_vehicles есть) — чтобы EXISTS не падал до миграции. */
function vehSchemaReady(PDO $db): bool
{
    static $ready = null;
    if ($ready !== null) return $ready;
    try { $ready = (bool)$db->query("SHOW TABLES LIKE 'part_vehicles'")->fetchColumn(); }
    catch (Throwable $e) { $ready = false; }
    return $ready;
}

/** Подпись модификации: «2006–2009 · 2GR-FE · 3.5 л · бензин · XU30». */
function vehModLabel(array $m): string
{
    $parts = [];
    $yf = $m['year_from'] ?? null; $yt = $m['year_to'] ?? null;
    if ($yf || $yt) $parts[] = ($yf ?: '…') . '–' . ($yt ?: 'н.в.');
    if (!empty($m['engine'])) $parts[] = $m['engine'];
    if (!empty($m['volume'])) $parts[] = rtrim(rtrim((string)$m['volume'], '0'), '.') . ' л';
    if (!empty($m['fuel']))   $parts[] = $m['fuel'];
    if (!empty($m['body']))   $parts[] = $m['body'];
    if (!empty($m['name']) && !$parts) $parts[] = $m['name'];
    return implode(' · ', $parts);
}

/** Варианты топлива (для списков). */
function vehFuels(): array
{
    return ['бензин', 'дизель', 'гибрид', 'электро', 'газ/бензин'];
}

/** Все марки. $onlyWithProducts — только те, у кого есть активные товары (+ счётчик). */
function vehMakes(PDO $db, bool $onlyWithProducts = false): array
{
    try {
        if ($onlyWithProducts) {
            $sql = "SELECT mk.id, mk.name, mk.slug, COUNT(DISTINCT pv.part_id) AS cnt
                      FROM vehicle_makes mk
                      JOIN vehicle_models md ON md.make_id = mk.id
                      JOIN part_vehicles pv ON pv.model_id = md.id
                      JOIN parts p ON p.id = pv.part_id
                     WHERE mk.is_active = 1 AND p.is_active = 1
                       AND (p.seller_id IS NULL OR p.moderation_status = 'active')
                  GROUP BY mk.id, mk.name, mk.slug
                  ORDER BY mk.sort_order, mk.name";
            return $db->query($sql)->fetchAll();
        }
        return $db->query("SELECT id, name, slug FROM vehicle_makes WHERE is_active = 1 ORDER BY sort_order, name")->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** Модели марки. $onlyWithProducts — только с товарами (+ счётчик). */
function vehModels(PDO $db, int $makeId, bool $onlyWithProducts = false): array
{
    if ($makeId <= 0) return [];
    try {
        if ($onlyWithProducts) {
            $st = $db->prepare(
                "SELECT md.id, md.name, md.slug, COUNT(DISTINCT pv.part_id) AS cnt
                   FROM vehicle_models md
                   JOIN part_vehicles pv ON pv.model_id = md.id
                   JOIN parts p ON p.id = pv.part_id
                  WHERE md.make_id = ? AND md.is_active = 1 AND p.is_active = 1
                    AND (p.seller_id IS NULL OR p.moderation_status = 'active')
               GROUP BY md.id, md.name, md.slug
               ORDER BY md.sort_order, md.name");
            $st->execute([$makeId]);
            return $st->fetchAll();
        }
        $st = $db->prepare("SELECT id, name, slug FROM vehicle_models WHERE make_id = ? AND is_active = 1 ORDER BY sort_order, name");
        $st->execute([$makeId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** Модификации модели. */
function vehMods(PDO $db, int $modelId): array
{
    if ($modelId <= 0) return [];
    try {
        $st = $db->prepare("SELECT id, name, year_from, year_to, engine, volume, fuel, body FROM vehicle_mods
                            WHERE model_id = ? AND is_active = 1 ORDER BY year_from DESC, id");
        $st->execute([$modelId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/** На какие авто подходит товар — для карточки. Группируем по модели. */
function partVehicles(PDO $db, int $partId): array
{
    if ($partId <= 0) return [];
    try {
        $st = $db->prepare(
            "SELECT pv.model_id, pv.mod_id, pv.needs_review,
                    mk.name AS make, mk.slug AS make_slug,
                    md.name AS model, md.slug AS model_slug,
                    vm.year_from, vm.year_to, vm.engine, vm.volume, vm.fuel, vm.body, vm.name AS mod_name
               FROM part_vehicles pv
               JOIN vehicle_models md ON md.id = pv.model_id
               JOIN vehicle_makes  mk ON mk.id = md.make_id
          LEFT JOIN vehicle_mods   vm ON vm.id = pv.mod_id
              WHERE pv.part_id = ?
           ORDER BY mk.name, md.name, vm.year_from DESC");
        $st->execute([$partId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/**
 * Сохранить привязки товара к авто (replace-all).
 * $items — [['model_id'=>int, 'mod_id'=>?int], …]. $needsReview помечает все связи.
 */
function partSaveVehicles(PDO $db, int $partId, array $items, bool $needsReview = false): void
{
    if ($partId <= 0) return;
    ensureVehicleSchema();
    try {
        $db->prepare("DELETE FROM part_vehicles WHERE part_id = ?")->execute([$partId]);
        $ins = $db->prepare("INSERT INTO part_vehicles (part_id, model_id, mod_id, needs_review) VALUES (?,?,?,?)");
        $seen = [];
        foreach ($items as $it) {
            $modelId = (int)($it['model_id'] ?? 0);
            if ($modelId <= 0) continue;
            $modId = !empty($it['mod_id']) ? (int)$it['mod_id'] : null;
            $key = $modelId . ':' . ($modId ?? '0');
            if (isset($seen[$key])) continue;   // без дублей
            $seen[$key] = 1;
            $ins->execute([$partId, $modelId, $modId, $needsReview ? 1 : 0]);
        }
    } catch (Throwable $e) { /* не роняем сохранение товара */ }
}

/** Сырые связи товара: [model_id, mod_id, make_id] — для форм редактирования. */
function partVehicleLinks(PDO $db, int $partId): array
{
    if ($partId <= 0) return [];
    try {
        $st = $db->prepare(
            "SELECT pv.model_id, pv.mod_id, md.make_id
               FROM part_vehicles pv JOIN vehicle_models md ON md.id = pv.model_id
              WHERE pv.part_id = ? ORDER BY md.make_id, pv.model_id");
        $st->execute([$partId]);
        return $st->fetchAll();
    } catch (Throwable $e) { return []; }
}

/**
 * HTML-блок «Применимость» для форм товара (кабинет продавца и админка).
 * Строки Марка→Модель→Год/Двигатель, кнопка «+ Добавить авто», зависимые списки
 * подгружаются через api/vehicles.php. При сохранении формы читать veh_model[] и
 * veh_mod[] (см. partSaveVehicles). Возвращает '' если справочник не готов.
 */
function vehicleFormBlockHtml(PDO $db, int $partId, bool $allowRequest = false): string
{
    if (!vehSchemaReady($db)) return '';
    $makes = vehMakes($db);
    if (!$makes) return '';

    // Опции марок (строкой, переиспользуем в строках и шаблоне).
    $mkOpts = '<option value="">Марка</option>';
    foreach ($makes as $mk) {
        $mkOpts .= '<option value="' . (int)$mk['id'] . '">' . htmlspecialchars($mk['name'], ENT_QUOTES) . '</option>';
    }

    $links = partVehicleLinks($db, $partId);

    $row = static function (?array $link) use ($mkOpts): string {
        $mk = $link ? (int)$link['make_id'] : 0;
        $md = $link ? (int)$link['model_id'] : 0;
        $mo = $link ? (int)($link['mod_id'] ?? 0) : 0;
        // Марку проставляем selected в опциях; модель/модификацию догрузит JS.
        $opts = $mk > 0 ? preg_replace('/value="' . $mk . '"/', 'value="' . $mk . '" selected', $mkOpts, 1) : $mkOpts;
        return '<div class="veh-row" data-make="' . $mk . '" data-model="' . $md . '" data-mod="' . $mo . '">'
            . '<select class="vf-make" aria-label="Марка">' . $opts . '</select>'
            . '<select class="vf-model" name="veh_model[]" aria-label="Модель" disabled><option value="">Модель</option></select>'
            . '<select class="vf-mod" name="veh_mod[]" aria-label="Модификация" disabled><option value="">Все модификации</option></select>'
            . '<button type="button" class="vf-del" aria-label="Удалить строку">&times;</button>'
            . '</div>';
    };

    $rowsHtml = '';
    foreach ($links as $l) $rowsHtml .= $row($l);
    if ($rowsHtml === '') $rowsHtml = $row(null);   // одна пустая строка

    $apiUrl = APP_URL . '/api/vehicles.php';
    $reqUrl = APP_URL . '/api/vehicle_request.php';
    $csrf   = function_exists('generateCsrfToken') ? generateCsrfToken() : '';
    ob_start(); ?>
<div class="veh-block" data-api="<?= htmlspecialchars($apiUrl, ENT_QUOTES) ?>">
  <label class="veh-block-label">Для каких автомобилей подходит</label>
  <p class="veh-block-hint">Укажите машины, к которым подходит запчасть. Так покупатели найдут её через подбор по авто — это увеличивает продажи.</p>
  <div class="veh-rows"><?= $rowsHtml ?></div>
  <button type="button" class="veh-add sl-btn sl-btn-outline sl-btn-sm"><i class="fa fa-plus"></i> Добавить ещё автомобиль</button>
  <p class="veh-block-ex">Например: Lexus → RX350 → 2006–2009, 2GR-FE.</p>
  <?php if ($allowRequest): ?>
  <div class="veh-req" data-url="<?= htmlspecialchars($reqUrl, ENT_QUOTES) ?>">
    <a href="javascript:void(0)" class="veh-req-toggle">Нет нужной машины? Сообщите нам</a>
    <div class="veh-req-form" style="display:none;">
      <input type="hidden" class="vr-csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">
      <input type="text" class="vr-make" placeholder="Марка (напр. Toyota)">
      <input type="text" class="vr-model" placeholder="Модель (напр. Prius)">
      <input type="text" class="vr-years" placeholder="Годы (напр. 2010–2015)">
      <input type="text" class="vr-engine" placeholder="Двигатель (напр. 2ZR-FXE)">
      <button type="button" class="vr-send sl-btn sl-btn-outline sl-btn-sm">Отправить заявку</button>
      <span class="vr-status"></span>
    </div>
  </div>
  <?php endif; ?>
  <template class="veh-tpl"><?= $row(null) ?></template>
</div>
<script>
(function(){
  var block = document.querySelector('.veh-block'); if (!block) return;
  var api = block.getAttribute('data-api');
  var rowsWrap = block.querySelector('.veh-rows');
  var tpl = block.querySelector('.veh-tpl');
  function esc(s){ return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/"/g,'&quot;'); }
  function loadModels(row, makeId, sel, cb){
    var md = row.querySelector('.vf-model');
    if (!makeId){ md.innerHTML = '<option value="">Модель</option>'; md.disabled = true; return; }
    md.innerHTML = '<option value="">…</option>'; md.disabled = true;
    fetch(api + '?action=models&make=' + encodeURIComponent(makeId), {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        var h = '<option value="">Модель</option>';
        (d.models||[]).forEach(function(m){ h += '<option value="'+m.id+'"'+(String(m.id)===String(sel)?' selected':'')+'>'+esc(m.name)+'</option>'; });
        md.innerHTML = h; md.disabled = false; if (cb) cb();
      }).catch(function(){ md.disabled = false; });
  }
  function loadMods(row, modelId, sel){
    var mo = row.querySelector('.vf-mod');
    if (!modelId){ mo.innerHTML = '<option value="">Все модификации</option>'; mo.disabled = true; return; }
    mo.innerHTML = '<option value="">…</option>'; mo.disabled = true;
    fetch(api + '?action=mods&model=' + encodeURIComponent(modelId), {credentials:'same-origin'})
      .then(function(r){ return r.json(); })
      .then(function(d){
        var h = '<option value="">Все модификации</option>';
        (d.mods||[]).forEach(function(m){ h += '<option value="'+m.id+'"'+(String(m.id)===String(sel)?' selected':'')+'>'+esc(m.label)+'</option>'; });
        mo.innerHTML = h; mo.disabled = false;
      }).catch(function(){ mo.disabled = false; });
  }
  function bind(row){
    var mk = row.querySelector('.vf-make'), md = row.querySelector('.vf-model'), del = row.querySelector('.vf-del');
    mk.addEventListener('change', function(){ loadModels(row, mk.value, '', function(){ loadMods(row, '', ''); }); });
    md.addEventListener('change', function(){ loadMods(row, md.value, ''); });
    if (del) del.addEventListener('click', function(){ row.remove(); });
    var dm = row.getAttribute('data-make'), dmo = row.getAttribute('data-model'), dmd = row.getAttribute('data-mod');
    if (dm && dm !== '0'){ loadModels(row, dm, dmo, function(){ if (dmo && dmo !== '0') loadMods(row, dmo, dmd); }); }
  }
  Array.prototype.forEach.call(rowsWrap.querySelectorAll('.veh-row'), bind);
  block.querySelector('.veh-add').addEventListener('click', function(){
    var node = tpl.content.firstElementChild.cloneNode(true);
    rowsWrap.appendChild(node); bind(node);
  });

  // «Нет нужной машины? Сообщите нам» — заявка админу.
  var req = block.querySelector('.veh-req');
  if (req) {
    var toggle = req.querySelector('.veh-req-toggle'), form = req.querySelector('.veh-req-form');
    toggle.addEventListener('click', function(){ form.style.display = form.style.display === 'none' ? 'flex' : 'none'; });
    req.querySelector('.vr-send').addEventListener('click', function(){
      var st = req.querySelector('.vr-status');
      var mk = req.querySelector('.vr-make').value.trim(), md = req.querySelector('.vr-model').value.trim();
      if (!mk || !md) { st.textContent = 'Укажите марку и модель.'; return; }
      var fd = new FormData();
      fd.append('_csrf', req.querySelector('.vr-csrf').value);
      fd.append('make', mk); fd.append('model', md);
      fd.append('years', req.querySelector('.vr-years').value.trim());
      fd.append('engine', req.querySelector('.vr-engine').value.trim());
      st.textContent = 'Отправка…';
      fetch(req.getAttribute('data-url'), { method:'POST', body:fd, credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){ if (d && d.ok){ form.style.display='none'; st.textContent='Заявка отправлена — добавим машину в справочник ✓'; } else { st.textContent = (d && d.msg) ? d.msg : 'Не удалось отправить.'; } })
        .catch(function(){ st.textContent='Ошибка сети.'; });
    });
  }
})();
</script>
<?php
    return ob_get_clean();
}

/** Собрать привязки из POST формы товара (veh_model[]/veh_mod[]) для partSaveVehicles. */
function vehicleItemsFromPost(array $post): array
{
    $items = [];
    foreach ((array)($post['veh_model'] ?? []) as $k => $mid) {
        $mid = (int)$mid;
        if ($mid <= 0) continue;
        $items[] = ['model_id' => $mid, 'mod_id' => ((int)($post['veh_mod'][$k] ?? 0)) ?: null];
    }
    return $items;
}

/** Разрешить марку по slug или id. Возвращает строку или null. */
function vehFindMake(PDO $db, string $key): ?array
{
    $key = trim($key);
    if ($key === '') return null;
    try {
        if (ctype_digit($key)) {
            $st = $db->prepare("SELECT * FROM vehicle_makes WHERE id = ? LIMIT 1");
            $st->execute([(int)$key]);
        } else {
            $st = $db->prepare("SELECT * FROM vehicle_makes WHERE slug = ? LIMIT 1");
            $st->execute([vehSlug($key)]);
        }
        return $st->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}

/** Разрешить модель по slug/id в рамках марки. */
function vehFindModel(PDO $db, int $makeId, string $key): ?array
{
    $key = trim($key);
    if ($key === '' || $makeId <= 0) return null;
    try {
        if (ctype_digit($key)) {
            $st = $db->prepare("SELECT * FROM vehicle_models WHERE id = ? AND make_id = ? LIMIT 1");
            $st->execute([(int)$key, $makeId]);
        } else {
            $st = $db->prepare("SELECT * FROM vehicle_models WHERE slug = ? AND make_id = ? LIMIT 1");
            $st->execute([vehSlug($key), $makeId]);
        }
        return $st->fetch() ?: null;
    } catch (Throwable $e) { return null; }
}
