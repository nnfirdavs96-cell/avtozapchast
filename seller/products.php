<?php
/**
 * Кабинет продавца — список моих товаров (со статусами модерации) + удаление.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
$seller = requireSeller();

$db   = getDB();
$sid  = (int)$seller['id'];
$csrf = generateCsrfToken();

// Удаление своего товара.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'Ошибка безопасности.');
    } else {
        $del = (int)($_POST['id'] ?? 0);
        $db->prepare("DELETE FROM parts WHERE id = ? AND seller_id = ?")->execute([$del, $sid]);
        flashMessage('success', 'Товар удалён.');
    }
    redirect(APP_URL . '/seller/products.php');
}

$rows = $db->prepare(
    "SELECT p.*, b.name AS brand_name, c.name AS category_name
       FROM parts p
       LEFT JOIN brands b ON b.id = p.brand_id
       LEFT JOIN categories c ON c.id = p.category_id
      WHERE p.seller_id = ?
   ORDER BY (p.moderation_status='rejected') DESC, (p.moderation_status='pending') DESC, p.updated_at DESC"
);
$rows->execute([$sid]);
$rows = $rows->fetchAll();

$approved = $seller['status'] === 'approved';
$sellerNavActive = 'products';
$pageTitle = 'Мои товары — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sl-wrap">
  <div class="container">
    <div class="sl-head">
      <div>
        <h1 class="sl-title"><i class="fa fa-list"></i> Мои товары</h1>
        <p class="sl-sub">Магазин: <?= sanitize($seller['shop_name']) ?> · всего <?= count($rows) ?></p>
      </div>
      <?php require dirname(__DIR__) . '/includes/seller_nav.php'; ?>
    </div>

    <?php if ($flash = getFlashMessage()): ?>
    <div class="sl-alert sl-alert-<?= $flash['type']==='success'?'ok':'err' ?>"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>

    <div class="sl-toolbar">
      <?php if ($approved): ?>
      <a href="<?= APP_URL ?>/seller/product_edit.php" class="sl-btn sl-btn-primary"><i class="fa fa-plus"></i> Добавить товар</a>
      <?php else: ?>
      <span class="sl-lock"><i class="fa fa-lock"></i> Добавление откроется после одобрения магазина</span>
      <?php endif; ?>
    </div>

    <?php if (empty($rows)): ?>
    <div class="sl-empty">
      <i class="fa fa-cube"></i>
      <p>Пока нет товаров.</p>
      <?php if ($approved): ?><a href="<?= APP_URL ?>/seller/product_edit.php" class="sl-btn sl-btn-primary">Добавить первый товар</a><?php endif; ?>
    </div>
    <?php else: ?>
    <div class="sl-table-wrap">
      <table class="sl-table">
        <thead>
          <tr><th>Фото</th><th>Товар</th><th>Артикул</th><th>Цена</th><th>Наличие</th><th>Статус</th><th></th></tr>
        </thead>
        <tbody>
          <?php foreach ($rows as $p):
            $imgs = json_decode($p['images'] ?? '[]', true) ?: [];
            $img  = $imgs[0] ?? (APP_URL . '/assets/img/product/placeholder.jpg');
            [$cls, $label] = sellerProductStatusInfo($p['moderation_status']);
          ?>
          <tr>
            <td><img class="sl-thumb" src="<?= sanitize($img) ?>" alt=""></td>
            <td>
              <div class="sl-pname"><?= sanitize($p['name']) ?></div>
              <div class="sl-pmeta"><?= sanitize($p['brand_name'] ?? '') ?> · <?= sanitize($p['category_name'] ?? '') ?></div>
              <?php if (!empty($p['reject_reason'])): ?>
              <div class="sl-preject"><i class="fa fa-exclamation-circle"></i> <?= sanitize($p['reject_reason']) ?></div>
              <?php endif; ?>
            </td>
            <td><code><?= sanitize($p['part_number']) ?></code></td>
            <td><?= formatPrice((float)$p['price']) ?></td>
            <td><?= (int)$p['stock'] ?> шт.</td>
            <td><span class="sl-badge sl-badge--<?= $cls ?>"><?= $label ?></span></td>
            <td class="sl-rowact">
              <a href="<?= APP_URL ?>/seller/product_edit.php?id=<?= (int)$p['id'] ?>" class="sl-btn sl-btn-sm sl-btn-outline">Изменить</a>
              <form method="post" action="" style="display:inline" onsubmit="return confirm('Удалить товар?');">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$p['id'] ?>">
                <button type="submit" class="sl-btn sl-btn-sm sl-btn-danger">Удалить</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
