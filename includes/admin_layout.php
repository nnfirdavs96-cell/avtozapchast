<?php
/**
 * Render the admin/manager/superadmin sidebar navigation.
 */
function renderAdminSidebar(): void {
    $role = $_SESSION['role'] ?? 'buyer';
    $current = basename($_SERVER['PHP_SELF']);
    ?>
    <aside class="dash-sidebar">
      <h5>Главное</h5>
      <a href="<?= APP_URL ?>/<?= $role==='manager'?'manager':($role==='admin'?'admin':'superadmin') ?>/index.php" class="<?= $current==='index.php'?'active':'' ?>">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 9l9-7 9 7v11a2 2 0 01-2 2H5a2 2 0 01-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>
        Дашборд
      </a>

      <?php if (in_array($role, ['manager','admin','superadmin'], true)): ?>
        <h5>Каталог</h5>
        <a href="<?= APP_URL ?>/manager/parts.php"      class="<?= $current==='parts.php'?'active':'' ?>">📦 Запчасти</a>
        <a href="<?= APP_URL ?>/manager/categories.php" class="<?= $current==='categories.php'?'active':'' ?>">📂 Категории</a>
        <a href="<?= APP_URL ?>/manager/brands.php"     class="<?= $current==='brands.php'?'active':'' ?>">🏷 Бренды</a>
        <a href="<?= APP_URL ?>/manager/cars.php"       class="<?= $current==='cars.php'?'active':'' ?>">🚗 Авто (марки/модели)</a>
        <a href="<?= APP_URL ?>/manager/reviews.php"    class="<?= $current==='reviews.php'?'active':'' ?>">⭐ Отзывы</a>
      <?php endif; ?>

      <?php if (in_array($role, ['admin','superadmin'], true)): ?>
        <h5>Продажи</h5>
        <a href="<?= APP_URL ?>/admin/orders.php"  class="<?= $current==='orders.php'?'active':'' ?>">🛒 Заказы</a>
        <a href="<?= APP_URL ?>/admin/users.php"   class="<?= $current==='users.php'?'active':'' ?>">👥 Покупатели</a>
        <a href="<?= APP_URL ?>/admin/contacts.php" class="<?= $current==='contacts.php'?'active':'' ?>">✉ Сообщения</a>
        <a href="<?= APP_URL ?>/admin/blog.php"    class="<?= $current==='blog.php'?'active':'' ?>">📝 Блог</a>
      <?php endif; ?>

      <?php if ($role === 'superadmin'): ?>
        <h5>Система</h5>
        <a href="<?= APP_URL ?>/superadmin/users.php"    class="<?= $current==='users.php'?'active':'' ?>">⚙ Пользователи и роли</a>
        <a href="<?= APP_URL ?>/superadmin/settings.php" class="<?= $current==='settings.php'?'active':'' ?>">🔧 Настройки сайта</a>
        <a href="<?= APP_URL ?>/superadmin/delivery.php" class="<?= $current==='delivery.php'?'active':'' ?>">🚚 Способы доставки</a>
        <a href="<?= APP_URL ?>/superadmin/payment.php"  class="<?= $current==='payment.php'?'active':'' ?>">💳 Способы оплаты</a>
        <a href="<?= APP_URL ?>/superadmin/i18n.php"     class="<?= $current==='i18n.php'?'active':'' ?>">🌐 Языки и валюты</a>
      <?php endif; ?>

      <h5>Аккаунт</h5>
      <a href="<?= APP_URL ?>/index.php">🏠 На сайт</a>
      <a href="<?= APP_URL ?>/auth/logout.php" style="color:#e74c3c">🚪 Выйти</a>
    </aside>
    <?php
}
