<?php
/**
 * Кабинет продавца — настройки магазина.
 * Здесь продавец меняет название, телефон, описание и логотип магазина.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller.php';
require_once dirname(__DIR__) . '/includes/uploads.php';
$seller = requireSeller();

$db   = getDB();
$sid  = (int)$seller['id'];
$csrf = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/seller/shop.php');
    }
    $shopName = trim($_POST['shop_name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $descr    = trim($_POST['description'] ?? '');

    $errors = [];
    if ($shopName === '' || mb_strlen($shopName) < 2 || mb_strlen($shopName) > 150) {
        $errors[] = 'Название магазина — от 2 до 150 символов.';
    }

    // Логотип: меняем, только если прислали новый файл.
    $logoUrl = null;
    if (!empty($_FILES['logo']['name'])) {
        $logoUrl = saveUploadedImage($_FILES['logo'], 'sellers', $logoErr);
        if ($logoUrl === '') $errors[] = 'Логотип: ' . $logoErr;
    }

    if ($errors) {
        flashMessage('danger', implode(' ', $errors));
    } else {
        if ($logoUrl !== null && $logoUrl !== '') {
            $db->prepare("UPDATE sellers SET shop_name=?, phone=?, description=?, logo=?, updated_at=NOW() WHERE id=?")
               ->execute([$shopName, ($phone !== '' ? $phone : null), ($descr !== '' ? $descr : null), $logoUrl, $sid]);
        } else {
            $db->prepare("UPDATE sellers SET shop_name=?, phone=?, description=?, updated_at=NOW() WHERE id=?")
               ->execute([$shopName, ($phone !== '' ? $phone : null), ($descr !== '' ? $descr : null), $sid]);
        }
        flashMessage('success', 'Настройки магазина сохранены.');
    }
    redirect(APP_URL . '/seller/shop.php');
}

// Свежие данные магазина.
$st = $db->prepare("SELECT shop_name, phone, description, logo FROM sellers WHERE id = ? LIMIT 1");
$st->execute([$sid]);
$shop = $st->fetch() ?: [];

$sellerNavActive = 'shop';
$pageTitle = 'Настройки магазина — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="sl-wrap">
  <div class="container">
    <div class="sl-head">
      <div>
        <h1 class="sl-title"><i class="fa fa-cog"></i> Настройки магазина</h1>
        <p class="sl-sub">Название, контакты и логотип</p>
      </div>
      <?php require dirname(__DIR__) . '/includes/seller_nav.php'; ?>
    </div>

    <?php if ($flash = getFlashMessage()): ?>
    <div class="alert alert-<?= sanitize($flash['type']) ?>" style="margin-bottom:16px;"><?= sanitize($flash['message']) ?></div>
    <?php endif; ?>

    <div class="sl-card" style="max-width:640px;background:#fff;border:1px solid #eee;border-radius:12px;padding:22px;">
      <form method="post" action="" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">

        <div style="margin-bottom:18px;">
          <label style="display:block;font-weight:600;margin-bottom:6px;">Логотип магазина</label>
          <div style="display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
            <div style="width:88px;height:88px;border-radius:12px;background:#f4f5f7;border:1px solid #e6e8ec;display:flex;align-items:center;justify-content:center;overflow:hidden;">
              <?php if (!empty($shop['logo'])): ?>
                <img src="<?= sanitize($shop['logo']) ?>" alt="логотип" style="width:100%;height:100%;object-fit:cover;">
              <?php else: ?>
                <i class="fa fa-briefcase" style="font-size:30px;color:#b8bcc4;"></i>
              <?php endif; ?>
            </div>
            <div>
              <input type="file" name="logo" accept="image/*">
              <small style="color:#888;display:block;margin-top:4px;">JPG, PNG, WEBP, GIF, до 5 МБ. Оставьте пустым, чтобы не менять.</small>
            </div>
          </div>
        </div>

        <div style="margin-bottom:16px;">
          <label style="display:block;font-weight:600;margin-bottom:6px;">Название магазина <span style="color:#C70909;">*</span></label>
          <input type="text" name="shop_name" value="<?= sanitize($shop['shop_name'] ?? '') ?>" maxlength="150" required
                 style="width:100%;padding:10px 12px;border:1px solid #d9dce1;border-radius:8px;">
        </div>

        <div style="margin-bottom:16px;">
          <label style="display:block;font-weight:600;margin-bottom:6px;">Телефон магазина</label>
          <input type="tel" name="phone" value="<?= sanitize($shop['phone'] ?? '') ?>" placeholder="+992 __ ___-__-__"
                 style="width:100%;padding:10px 12px;border:1px solid #d9dce1;border-radius:8px;">
        </div>

        <div style="margin-bottom:20px;">
          <label style="display:block;font-weight:600;margin-bottom:6px;">Описание магазина</label>
          <textarea name="description" rows="4" placeholder="Коротко о вашем магазине"
                    style="width:100%;padding:10px 12px;border:1px solid #d9dce1;border-radius:8px;"><?= sanitize($shop['description'] ?? '') ?></textarea>
        </div>

        <button type="submit" class="sl-btn sl-btn-primary" style="min-width:200px;">Сохранить</button>
      </form>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
