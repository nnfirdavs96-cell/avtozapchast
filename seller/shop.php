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
        $logoUrl = saveUploadedImage($_FILES['logo'], 'sellers', $logoErr, ['square' => 512]);
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
            <!-- Предпросмотр: белый фон, вписывание по центру (contain) — непропорциональные
                 логотипы не растягиваются. -->
            <div style="width:88px;height:88px;border-radius:12px;background:#fff;border:1px solid #e6e8ec;display:flex;align-items:center;justify-content:center;overflow:hidden;flex:0 0 auto;">
              <img id="logoPrevImg" src="<?= !empty($shop['logo']) ? sanitize($shop['logo']) : '' ?>" alt="логотип"
                   style="width:100%;height:100%;object-fit:contain;<?= empty($shop['logo']) ? 'display:none;' : '' ?>">
              <i id="logoPrevIcon" class="fa fa-briefcase" style="font-size:30px;color:#b8bcc4;<?= !empty($shop['logo']) ? 'display:none;' : '' ?>"></i>
            </div>
            <div>
              <!-- Кнопка в стиле кабинета вместо серого «Выберите файл». -->
              <label class="sl-btn sl-btn-outline" style="cursor:pointer;margin:0;">
                <i class="fa fa-upload"></i> Загрузить логотип
                <input type="file" id="logoInput" name="logo" accept="image/jpeg,image/png,image/webp" style="display:none;">
              </label>
              <small style="color:#888;display:block;margin-top:6px;max-width:360px;">Квадратное изображение 1:1, рекомендуемо 512×512 px. JPG, PNG, WEBP, до 5 МБ. Оставьте пустым, чтобы не менять.</small>
              <small id="logoWarn" style="color:#b9772a;display:none;margin-top:4px;"><i class="fa fa-exclamation-triangle"></i> Логотип слишком маленький, может выглядеть нечётко.</small>
              <div id="logoFileName" style="color:#555;font-size:.82rem;margin-top:4px;"></div>
            </div>
          </div>
        </div>
        <script>
        /* Предпросмотр логотипа до сохранения + предупреждение, если < 256×256. */
        (function(){
            var inp = document.getElementById('logoInput'); if (!inp) return;
            var img = document.getElementById('logoPrevImg');
            var icon = document.getElementById('logoPrevIcon');
            var warn = document.getElementById('logoWarn');
            var nameEl = document.getElementById('logoFileName');
            inp.addEventListener('change', function(){
                var f = inp.files && inp.files[0];
                if (!f) return;
                if (nameEl) nameEl.textContent = f.name;
                var url = URL.createObjectURL(f);
                if (img) {
                    img.onload = function(){
                        if (warn) warn.style.display = (img.naturalWidth < 256 || img.naturalHeight < 256) ? 'block' : 'none';
                        URL.revokeObjectURL(url);
                    };
                    img.src = url;
                    img.style.display = '';
                }
                if (icon) icon.style.display = 'none';
            });
        })();
        </script>

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
