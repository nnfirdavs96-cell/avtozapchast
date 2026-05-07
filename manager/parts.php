<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../includes/admin_layout.php';
requireRole(['manager','admin','superadmin']);

$db = getDB();
$action = $_GET['action'] ?? '';
$editId = (int)($_GET['edit'] ?? 0);
$err = $msg = '';

// Image upload helper
function handleImageUpload(int $partId): array {
    $saved = [];
    if (empty($_FILES['images']) || empty($_FILES['images']['name'])) return $saved;
    $dir = UPLOAD_PATH . 'parts/';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);
    $allowed = ['image/jpeg','image/png','image/webp','image/gif'];
    foreach ($_FILES['images']['name'] as $i => $name) {
        if ($_FILES['images']['error'][$i] !== UPLOAD_ERR_OK) continue;
        $tmp  = $_FILES['images']['tmp_name'][$i];
        $type = mime_content_type($tmp);
        if (!in_array($type, $allowed, true)) continue;
        $ext = pathinfo($name, PATHINFO_EXTENSION);
        $filename = $partId . '-' . bin2hex(random_bytes(6)) . '.' . preg_replace('/[^a-z0-9]/i','',$ext);
        if (move_uploaded_file($tmp, $dir . $filename)) {
            $saved[] = $filename;
        }
    }
    return $saved;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $a = $_POST['action'] ?? '';
    try {
        if ($a === 'create' || $a === 'update') {
            $data = [
                'part_number' => trim((string)$_POST['part_number']),
                'name'        => trim((string)$_POST['name']),
                'description' => trim((string)$_POST['description']),
                'brand_id'    => (int)$_POST['brand_id'],
                'category_id' => (int)$_POST['category_id'],
                'price'       => (float)$_POST['price'],
                'stock'       => (int)$_POST['stock'],
                'weight'      => $_POST['weight'] !== '' ? (float)$_POST['weight'] : null,
                'dimensions'  => trim((string)$_POST['dimensions']) ?: null,
                'is_active'   => isset($_POST['is_active']) ? 1 : 0,
            ];
            if ($a === 'create') {
                $sql = "INSERT INTO parts (part_number,name,description,brand_id,category_id,price,stock,weight,dimensions,is_active,created_by)
                        VALUES (?,?,?,?,?,?,?,?,?,?,?)";
                $db->prepare($sql)->execute([
                    $data['part_number'], $data['name'], $data['description'], $data['brand_id'], $data['category_id'],
                    $data['price'], $data['stock'], $data['weight'], $data['dimensions'], $data['is_active'], (int)$_SESSION['user_id'],
                ]);
                $id = (int)$db->lastInsertId();
            } else {
                $id = (int)$_POST['id'];
                $sql = "UPDATE parts SET part_number=?,name=?,description=?,brand_id=?,category_id=?,price=?,stock=?,weight=?,dimensions=?,is_active=? WHERE id=?";
                $db->prepare($sql)->execute([
                    $data['part_number'], $data['name'], $data['description'], $data['brand_id'], $data['category_id'],
                    $data['price'], $data['stock'], $data['weight'], $data['dimensions'], $data['is_active'], $id,
                ]);
            }
            // Upload images
            $files = handleImageUpload($id);
            if ($files) {
                $primary = !$db->query("SELECT COUNT(*) FROM part_images WHERE part_id=" . $id)->fetchColumn();
                $ins = $db->prepare("INSERT INTO part_images (part_id,path,is_primary,sort_order) VALUES (?,?,?,?)");
                foreach ($files as $i => $f) $ins->execute([$id, $f, $primary && $i === 0 ? 1 : 0, $i]);
            }
            $msg = 'Сохранено';
            redirect(APP_URL . '/manager/parts.php?edit=' . $id);
        }
        if ($a === 'delete_image') {
            $imgId = (int)$_POST['image_id'];
            $im = $db->prepare("SELECT * FROM part_images WHERE id=?");
            $im->execute([$imgId]); $img = $im->fetch();
            if ($img) {
                @unlink(UPLOAD_PATH . 'parts/' . $img['path']);
                $db->prepare("DELETE FROM part_images WHERE id=?")->execute([$imgId]);
            }
            redirect(APP_URL . '/manager/parts.php?edit=' . (int)$_POST['part_id']);
        }
        if ($a === 'delete') {
            $db->prepare("UPDATE parts SET is_active=0 WHERE id=?")->execute([(int)$_POST['id']]);
            $msg = 'Запчасть деактивирована';
            redirect(APP_URL . '/manager/parts.php');
        }
    } catch (Throwable $e) {
        $err = 'Ошибка: ' . $e->getMessage();
    }
}

$editing = null;
$editingImages = [];
if ($editId > 0) {
    $st = $db->prepare("SELECT * FROM parts WHERE id=?");
    $st->execute([$editId]);
    $editing = $st->fetch();
    $editingImages = getPartImages($editId);
}

$brands = getBrands();
$categories = getCategories();
$parts = $db->query("SELECT p.*, b.name AS brand_name, c.name AS category_name
                     FROM parts p LEFT JOIN brands b ON b.id=p.brand_id
                     LEFT JOIN categories c ON c.id=p.category_id
                     ORDER BY p.created_at DESC LIMIT 100")->fetchAll();

$pageTitle = 'Управление запчастями';
$adminArea = true;
require_once __DIR__ . '/../includes/header.php';
?>

<div class="dash-layout">
  <?php renderAdminSidebar(); ?>
  <div class="dash-main">
    <h1 class="dash-heading">Запчасти <span class="dash-heading-badge">manager</span></h1>

    <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>
    <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>

    <div class="admin-card mb-32">
      <h3 style="margin-bottom:16px"><?= $editing ? 'Редактировать' : 'Добавить запчасть' ?></h3>
      <form method="post" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
        <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
        <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int)$editing['id'] ?>"><?php endif; ?>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Артикул *</label>
            <input type="text" name="part_number" class="form-input" required value="<?= sanitize($editing['part_number'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Название *</label>
            <input type="text" name="name" class="form-input" required value="<?= sanitize($editing['name'] ?? '') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Бренд *</label>
            <select name="brand_id" class="form-select" required>
              <?php foreach ($brands as $b): ?>
                <option value="<?= (int)$b['id'] ?>" <?= ($editing['brand_id'] ?? 0)==(int)$b['id']?'selected':'' ?>><?= sanitize($b['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Категория *</label>
            <select name="category_id" class="form-select" required>
              <?php foreach ($categories as $c): ?>
                <option value="<?= (int)$c['id'] ?>" <?= ($editing['category_id'] ?? 0)==(int)$c['id']?'selected':'' ?>><?= sanitize($c['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Цена (₽) *</label>
            <input type="number" name="price" class="form-input" step="0.01" min="0" required value="<?= sanitize($editing['price'] ?? '0') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Остаток *</label>
            <input type="number" name="stock" class="form-input" min="0" required value="<?= sanitize($editing['stock'] ?? '0') ?>">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Вес (кг)</label>
            <input type="number" name="weight" class="form-input" step="0.001" value="<?= sanitize($editing['weight'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Габариты</label>
            <input type="text" name="dimensions" class="form-input" placeholder="LxWxH мм" value="<?= sanitize($editing['dimensions'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Описание</label>
          <textarea name="description" class="form-textarea"><?= sanitize($editing['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
          <label><input type="checkbox" name="is_active" value="1" <?= !$editing || (int)$editing['is_active']?'checked':'' ?>> Активный товар</label>
        </div>

        <div class="form-group">
          <label class="form-label">Изображения товара</label>
          <label class="image-uploader">
            <input type="file" name="images[]" multiple accept="image/*">
            <div class="upload-text">📤 Перетащите файлы сюда или нажмите, чтобы выбрать</div>
            <small style="color:#666">JPEG, PNG, WebP, GIF. Можно загрузить несколько файлов.</small>
          </label>

          <?php if ($editingImages): ?>
            <div class="image-grid">
              <?php foreach ($editingImages as $img): ?>
                <figure>
                  <img src="<?= UPLOAD_URL ?>parts/<?= sanitize($img['path']) ?>" alt="">
                  <?php if ($img['is_primary']): ?><span class="primary-tag">главное</span><?php endif; ?>
                  <form method="post" style="position:absolute;top:6px;right:6px">
                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrfToken) ?>">
                    <input type="hidden" name="action" value="delete_image">
                    <input type="hidden" name="image_id" value="<?= (int)$img['id'] ?>">
                    <input type="hidden" name="part_id" value="<?= (int)$editing['id'] ?>">
                    <button class="del-img" type="submit" title="Удалить">✕</button>
                  </form>
                </figure>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

        <button type="submit" class="btn btn-primary"><?= $editing ? 'Сохранить' : 'Создать' ?></button>
        <?php if ($editing): ?>
          <a href="<?= APP_URL ?>/manager/parts.php" class="btn btn-outline">Отмена</a>
          <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$editing['id'] ?>" target="_blank" class="btn btn-link">Открыть на сайте →</a>
        <?php endif; ?>
      </form>
    </div>

    <div class="admin-card">
      <h3 style="margin-bottom:16px">Все запчасти (последние 100)</h3>
      <div class="admin-table-wrap">
        <table class="admin-table">
          <thead><tr><th>Артикул</th><th>Название</th><th>Бренд</th><th>Категория</th><th>Цена</th><th>Остаток</th><th>Статус</th><th></th></tr></thead>
          <tbody>
            <?php foreach ($parts as $p): ?>
              <tr>
                <td><span class="mono"><?= sanitize($p['part_number']) ?></span></td>
                <td><?= sanitize($p['name']) ?></td>
                <td><?= sanitize($p['brand_name']) ?></td>
                <td><?= sanitize($p['category_name']) ?></td>
                <td><?= money($p['price']) ?></td>
                <td><span class="badge badge-<?= $p['stock']==0?'danger':($p['stock']<=5?'warning':'success') ?>"><?= (int)$p['stock'] ?></span></td>
                <td><span class="badge badge-<?= $p['is_active']?'success':'danger' ?>"><?= $p['is_active']?'Вкл':'Выкл' ?></span></td>
                <td class="actions">
                  <a href="?edit=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">✏</a>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
