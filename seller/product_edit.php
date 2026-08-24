<?php
/**
 * Кабинет продавца — добавление/редактирование товара.
 * Сохраняется с seller_id текущего продавца и статусом 'pending' (на проверку).
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
require_once dirname(__DIR__) . '/includes/parts/grouping.php';
$seller = requireSeller();

if ($seller['status'] !== 'approved') {
    flashMessage('warning', 'Добавлять товары можно после одобрения магазина модератором.');
    redirect(APP_URL . '/seller/index.php');
}

$db   = getDB();
$sid  = (int)$seller['id'];
$csrf = generateCsrfToken();
$brands     = getBrands();
$categories = getCategories();

$errors = [];
$pid    = (int)($_GET['id'] ?? 0);
$edit   = null;

// Загрузка своего товара для редактирования.
if ($pid > 0) {
    $st = $db->prepare("SELECT * FROM parts WHERE id = ? AND seller_id = ? LIMIT 1");
    $st->execute([$pid, $sid]);
    $edit = $st->fetch();
    if (!$edit) { flashMessage('danger', 'Товар не найден.'); redirect(APP_URL . '/seller/products.php'); }
}

// ── Сохранение ────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $errors[] = 'Ошибка безопасности. Обновите страницу.';
    } else {
        $pnum  = trim($_POST['part_number'] ?? '');
        $name  = trim($_POST['name'] ?? '');
        $desc  = trim($_POST['description'] ?? '');
        $brand = (int)($_POST['brand_id'] ?? 0);
        $cat   = (int)($_POST['category_id'] ?? 0);
        $price = (float)str_replace(',', '.', $_POST['price'] ?? '0');
        $oldP  = trim($_POST['old_price'] ?? '') !== '' ? (float)str_replace(',', '.', $_POST['old_price']) : null;
        $stock = max(0, (int)($_POST['stock'] ?? 0));
        $weight = trim($_POST['weight'] ?? '') !== '' ? (float)str_replace(',', '.', $_POST['weight']) : null;
        $dims   = trim($_POST['dimensions'] ?? '');
        // Уникальный товар: б/у, разборка, позиция без заводского артикула. Такой
        // не сводится в общую карточку с чужими — двигатель с разборки от двух
        // продавцов это два разных товара, а не два предложения на один.
        $uniq   = !empty($_POST['is_unique_item']);
        // Семейство задаётся ссылкой на свой же товар: «это тот же чехол, только
        // бежевый». 0 — самостоятельный товар.
        $parent = (int)($_POST['variant_of'] ?? 0);

        // Атрибуты приходят тремя параллельными массивами. Пустые пары
        // отбрасываются в partsSaveAttributes — форма всегда шлёт запасные строки.
        $attrs = [];
        foreach ((array)($_POST['attr_name'] ?? []) as $k => $an) {
            $attrs[] = [
                'kind'  => (($_POST['attr_kind'][$k] ?? 'spec') === 'axis') ? 'axis' : 'spec',
                'name'  => (string)$an,
                'value' => (string)($_POST['attr_value'][$k] ?? ''),
            ];
        }

        $existingImgs = json_decode($_POST['existing_images'] ?? '[]', true) ?: [];
        $newImgs      = array_filter(array_map('trim', explode(',', $_POST['new_images'] ?? '')));
        $allImages    = array_slice(array_merge(array_values($existingImgs), array_values($newImgs)), 0, 6);

        if ($pnum === '')              $errors[] = 'Укажите артикул.';
        if ($name === '' || mb_strlen($name) < 3) $errors[] = 'Название — минимум 3 символа.';
        if ($brand <= 0)               $errors[] = 'Выберите бренд.';
        if ($cat <= 0)                 $errors[] = 'Выберите категорию.';
        if ($price <= 0)               $errors[] = 'Укажите цену больше нуля.';

        // Один и тот же артикул у РАЗНЫХ продавцов — норма, они и должны
        // конкурировать. Ошибка — когда продавец выкладывает его сам себе дважды:
        // тогда у одной детали две цены от одного магазина.
        if ($pnum !== '' && empty($errors)
            && partsSellerHasArticle($db, $pnum, $brand, $sid, $pid ?: null)) {
            $errors[] = 'Вы уже выложили товар с этим артикулом и брендом. '
                      . 'Отредактируйте существующий товар вместо создания второго.';
        }

        if (empty($errors)) {
            $imagesJson = json_encode(array_values($allImages), JSON_UNESCAPED_UNICODE);
            if ($edit) {
                // Правки уходят на повторную проверку.
                $db->prepare(
                    "UPDATE parts SET part_number=?, name=?, description=?, brand_id=?, category_id=?,
                        price=?, old_price=?, stock=?, weight=?, dimensions=?, images=?,
                        moderation_status='pending', reject_reason=NULL, updated_at=NOW()
                     WHERE id=? AND seller_id=?"
                )->execute([$pnum, $name, $desc ?: null, $brand, $cat, $price, $oldP, $stock, $weight, $dims ?: null, $imagesJson, $pid, $sid]);
                partsApplyGrouping($db, $pid, $pnum, $brand, $uniq);
                partsApplyFamily($db, $pid, $parent, $sid);
                partsSaveAttributes($db, $pid, $attrs);
                flashMessage('success', 'Товар обновлён и отправлен на проверку.');
            } else {
                $db->prepare(
                    "INSERT INTO parts (seller_id, part_number, name, description, brand_id, category_id,
                        price, old_price, stock, weight, dimensions, images, is_active, moderation_status, created_by, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?,?,?,?,1,'pending',?,NOW())"
                )->execute([$sid, $pnum, $name, $desc ?: null, $brand, $cat, $price, $oldP, $stock, $weight, $dims ?: null, $imagesJson, (int)$_SESSION['user_id']]);
                // После вставки — уже есть id, поэтому товар не найдёт сам себя.
                $newId = (int)$db->lastInsertId();
                partsApplyGrouping($db, $newId, $pnum, $brand, $uniq);
                partsApplyFamily($db, $newId, $parent, $sid);
                partsSaveAttributes($db, $newId, $attrs);
                flashMessage('success', 'Товар добавлен и отправлен на проверку. После одобрения он появится в каталоге.');
            }
            redirect(APP_URL . '/seller/products.php');
        }
    }
    // при ошибке — заполним форму отправленными значениями
    $edit = array_merge($edit ?? [], $_POST);
}

$imgs = json_decode($edit['images'] ?? '[]', true) ?: [];

// Мои товары для выбора семейства. Исключаем редактируемый: товар не может быть
// вариантом самого себя. Показываем только «ведущих» и самостоятельных — привязка
// к варианту второго уровня превратила бы плоское семейство в дерево, а
// переключатель на витрине умеет только один уровень.
$myProducts = [];
if ($sid) {
    $mp = $db->prepare(
        "SELECT id, name, part_number FROM parts
          WHERE seller_id = ? AND product_group_id IS NULL AND id <> ?
       ORDER BY name LIMIT 200"
    );
    $mp->execute([$sid, $pid ?: 0]);
    $myProducts = $mp->fetchAll();
}

// Уже сохранённые атрибуты + запасные пустые строки, чтобы было куда дописать.
$myAttrs = $pid ? (partsAttributes($db, [$pid])[$pid] ?? []) : [];
while (count($myAttrs) < 3) $myAttrs[] = ['kind' => 'spec', 'name' => '', 'value' => ''];
$sellerNavActive = $edit && $pid ? 'products' : 'add';
$pageTitle = ($pid ? 'Редактировать товар' : 'Добавить товар') . ' — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sl-wrap">
  <div class="container">
    <div class="sl-head">
      <div>
        <h1 class="sl-title"><i class="fa fa-<?= $pid?'pencil':'plus' ?>"></i> <?= $pid ? 'Редактировать товар' : 'Добавить товар' ?></h1>
        <p class="sl-sub">Магазин: <?= sanitize($seller['shop_name']) ?></p>
      </div>
      <?php require dirname(__DIR__) . '/includes/seller_nav.php'; ?>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="sl-alert sl-alert-err">
      <?php foreach ($errors as $e): ?><div>• <?= sanitize($e) ?></div><?php endforeach; ?>
    </div>
    <?php endif; ?>

    <form method="post" action="" class="sl-form">
      <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
      <input type="hidden" name="existing_images" id="existingImages" value="<?= sanitize(json_encode($imgs)) ?>">
      <input type="hidden" name="new_images" id="newImages" value="">

      <div class="sl-form-grid">
        <label class="sl-field sl-col2">
          <span>Название товара <b>*</b></span>
          <input type="text" name="name" value="<?= sanitize($edit['name'] ?? '') ?>" placeholder="Напр.: Фильтр масляный Toyota Camry" required>
        </label>

        <label class="sl-field">
          <span>Артикул (номер детали) <b>*</b></span>
          <input type="text" name="part_number" value="<?= sanitize($edit['part_number'] ?? '') ?>" placeholder="Напр.: 90915-YZZE1" required>
        </label>

        <label class="sl-field">
          <span>Вариант товара</span>
          <select name="variant_of">
            <option value="0">— самостоятельный товар —</option>
            <?php foreach ($myProducts as $mp2):
                $selVal = (int)($edit['product_group_id'] ?? 0) ?: (int)($_POST['variant_of'] ?? 0); ?>
            <option value="<?= (int)$mp2['id'] ?>" <?= $selVal === (int)$mp2['id'] ? 'selected' : '' ?>>
              <?= sanitize(mb_substr($mp2['name'], 0, 60)) ?> (<?= sanitize($mp2['part_number']) ?>)
            </option>
            <?php endforeach; ?>
          </select>
          <small class="sl-hint">
            Если это тот же товар в другом исполнении — цвет, размер, объём, возрастная
            группа — выберите основной товар. На витрине они соберутся в одну карточку
            с переключателем. Различие укажите ниже как <b>ось варианта</b>.
          </small>
        </label>

        <!-- Атрибуты. Ось варианта отличает исполнения друг от друга и имеет свою
             цену с наличием; характеристика — просто описание. Различать
             обязательно: «вес ребёнка» у автокресла это ось, «вес посылки» — нет. -->
        <div class="sl-field">
          <span>Характеристики и варианты</span>
          <table class="sl-attrs">
            <thead>
              <tr><th style="width:150px;">Тип</th><th>Название</th><th>Значение</th></tr>
            </thead>
            <tbody id="attrRows">
            <?php foreach ($myAttrs as $a2): ?>
              <tr>
                <td>
                  <select name="attr_kind[]">
                    <option value="spec" <?= ($a2['kind'] ?? 'spec') === 'spec' ? 'selected' : '' ?>>Характеристика</option>
                    <option value="axis" <?= ($a2['kind'] ?? '') === 'axis' ? 'selected' : '' ?>>Ось варианта</option>
                  </select>
                </td>
                <td><input type="text" name="attr_name[]" value="<?= sanitize($a2['name'] ?? '') ?>" placeholder="Цвет"></td>
                <td><input type="text" name="attr_value[]" value="<?= sanitize($a2['value'] ?? '') ?>" placeholder="Чёрный"></td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <button type="button" class="sl-btn sl-btn-outline sl-btn-sm" onclick="addAttrRow()">
            <i class="fa fa-plus"></i> Ещё строка
          </button>
          <small class="sl-hint">
            <b>Ось варианта</b> — то, чем исполнения отличаются друг от друга (цвет,
            объём, возраст). У каждого своя цена и наличие.<br>
            <b>Характеристика</b> — просто описание: вес, страна, гарантия. Пустые
            строки не сохраняются.
          </small>
        </div>

        <!-- Уникальный товар не сводится в общую карточку с товарами других
             продавцов: у б/у детали своё состояние, свой пробег и свои фото —
             это отдельный товар, а не ещё одно предложение на тот же. -->
        <label class="sl-check">
          <input type="checkbox" name="is_unique_item" value="1"
                 <?= !empty($edit['is_unique_item']) ? 'checked' : '' ?>>
          <span>
            <b>Уникальный товар</b> — б/у, разборка или позиция без заводского артикула.
            <small>Такой товар получит отдельную карточку и не будет объединён с товарами
            других продавцов. Для новых запчастей с артикулом галочку не ставьте —
            иначе покупатель не увидит вашу цену рядом с ценами конкурентов.</small>
          </span>
        </label>

        <label class="sl-field">
          <span>Бренд <b>*</b></span>
          <select name="brand_id" required>
            <option value="">— выберите —</option>
            <?php foreach ($brands as $b): ?>
            <option value="<?= (int)$b['id'] ?>" <?= ((int)($edit['brand_id'] ?? 0))===(int)$b['id']?'selected':'' ?>><?= sanitize(tField($b,'name')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="sl-field">
          <span>Категория <b>*</b></span>
          <select name="category_id" required>
            <option value="">— выберите —</option>
            <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ((int)($edit['category_id'] ?? 0))===(int)$c['id']?'selected':'' ?>><?= sanitize(tField($c,'name')) ?></option>
            <?php endforeach; ?>
          </select>
        </label>

        <label class="sl-field">
          <span>Цена, смн <b>*</b></span>
          <input type="number" name="price" step="0.01" min="0.01" value="<?= sanitize($edit['price'] ?? '') ?>" placeholder="0.00" required>
        </label>

        <label class="sl-field">
          <span>Старая цена (для скидки)</span>
          <input type="number" name="old_price" step="0.01" min="0" value="<?= sanitize($edit['old_price'] ?? '') ?>" placeholder="необязательно">
        </label>

        <label class="sl-field">
          <span>Количество на складе</span>
          <input type="number" name="stock" min="0" value="<?= sanitize($edit['stock'] ?? '0') ?>">
        </label>

        <label class="sl-field">
          <span>Вес, кг</span>
          <input type="text" name="weight" value="<?= sanitize($edit['weight'] ?? '') ?>" placeholder="необязательно">
        </label>

        <label class="sl-field">
          <span>Габариты (ДхШхВ, мм)</span>
          <input type="text" name="dimensions" value="<?= sanitize($edit['dimensions'] ?? '') ?>" placeholder="напр.: 100x80x60">
        </label>

        <label class="sl-field sl-col2">
          <span>Описание</span>
          <textarea name="description" rows="4" placeholder="Характеристики, применимость, комплектация…"><?= sanitize($edit['description'] ?? '') ?></textarea>
        </label>

        <div class="sl-field sl-col2">
          <span>Фотографии (до 6)</span>
          <div class="sl-imgrid" id="imgGrid">
            <?php foreach ($imgs as $u): ?>
            <div class="sl-imgitem" data-url="<?= sanitize($u) ?>">
              <img src="<?= sanitize($u) ?>" alt="">
              <button type="button" class="sl-imgx" onclick="slRemoveExisting(this,'<?= sanitize($u) ?>')">×</button>
            </div>
            <?php endforeach; ?>
          </div>
          <label class="sl-upload">
            <i class="fa fa-upload"></i> Загрузить фото
            <input type="file" id="imgUpload" multiple accept="image/*" style="display:none;" onchange="slUpload(this)">
          </label>
          <span id="uploadStatus" class="sl-upstatus"></span>
        </div>
      </div>

      <div class="sl-form-actions">
        <button type="submit" class="sl-btn sl-btn-primary"><i class="fa fa-check"></i> <?= $pid ? 'Сохранить и отправить на проверку' : 'Добавить товар' ?></button>
        <a href="<?= APP_URL ?>/seller/products.php" class="sl-btn sl-btn-outline">Отмена</a>
      </div>
      <p class="sl-note"><i class="fa fa-info-circle"></i> После сохранения товар уходит на проверку модератору. Как только его одобрят — он появится в каталоге.</p>
    </form>
  </div>
</div>

<script>
const slUploadUrl = '<?= APP_URL ?>/api/upload.php?type=products';
const slNewImages = [];
function slSync(){ document.getElementById('newImages').value = slNewImages.join(','); }
function slRemoveExisting(btn, url){
  btn.closest('.sl-imgitem').remove();
  let ex = JSON.parse(document.getElementById('existingImages').value || '[]');
  document.getElementById('existingImages').value = JSON.stringify(ex.filter(u => u !== url));
}
function slRemoveNew(btn, url){
  const i = slNewImages.indexOf(url); if(i>-1) slNewImages.splice(i,1); slSync();
  btn.closest('.sl-imgitem').remove();
}
async function slUpload(input){
  const files = Array.from(input.files), grid = document.getElementById('imgGrid'), status = document.getElementById('uploadStatus');
  if (grid.querySelectorAll('.sl-imgitem').length + files.length > 6){ alert('Максимум 6 фотографий'); input.value=''; return; }
  status.textContent = 'Загрузка…';
  for (const file of files){
    const fd = new FormData(); fd.append('file', file);
    try {
      const res = await fetch(slUploadUrl, { method:'POST', body:fd, credentials:'same-origin' });
      const d = await res.json();
      if (d.url){
        slNewImages.push(d.url); slSync();
        const div = document.createElement('div'); div.className='sl-imgitem'; div.dataset.url=d.url;
        div.innerHTML = '<img src="'+d.url+'" alt=""><button type="button" class="sl-imgx" onclick="slRemoveNew(this,\''+d.url+'\')">×</button>';
        grid.appendChild(div);
      } else { alert(d.error || 'Ошибка загрузки'); }
    } catch(e){ alert('Ошибка сети: ' + e.message); }
  }
  status.textContent=''; input.value='';
}
</script>

<script>
// Клонируем последнюю строку атрибутов и чистим значения. Без библиотек —
// в кабинете продавца больше ничего динамического нет, тянуть ради этого JS-фреймворк незачем.
function addAttrRow() {
  var tb = document.getElementById('attrRows');
  if (!tb || !tb.lastElementChild) return;
  var row = tb.lastElementChild.cloneNode(true);
  row.querySelectorAll('input').forEach(function (i) { i.value = ''; });
  tb.appendChild(row);
}
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
