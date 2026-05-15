<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'admin', 'superadmin']);

$db   = getDB();
$csrf = generateCsrfToken();

$type = ($_GET['type'] ?? 'product') === 'shop' ? 'shop' : 'product';
$tbl  = $type === 'shop' ? 'shop_reviews' : 'product_reviews';

// ── POST: moderation actions ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/manager/reviews.php');
    }
    $rid     = (int)($_POST['id'] ?? 0);
    $do      = $_POST['do'] ?? '';
    $pType   = ($_POST['type'] ?? 'product') === 'shop' ? 'shop' : 'product';
    $pTbl    = $pType === 'shop' ? 'shop_reviews' : 'product_reviews';
    $allowed = ['shop_reviews', 'product_reviews'];
    if (!in_array($pTbl, $allowed, true)) $pTbl = 'product_reviews';
    $return  = APP_URL . '/manager/reviews.php?type=' . $pType . '&status=' . urlencode($_POST['status'] ?? 'pending');

    if ($rid && $do === 'approve') {
        $db->prepare("UPDATE `$pTbl` SET status='approved' WHERE id=?")->execute([$rid]);
        flashMessage('success', 'Отзыв опубликован.');
    } elseif ($rid && $do === 'reject') {
        $db->prepare("UPDATE `$pTbl` SET status='rejected', is_featured=0 WHERE id=?")->execute([$rid]);
        flashMessage('success', 'Отзыв отклонён.');
    } elseif ($rid && $do === 'delete') {
        $db->prepare("DELETE FROM `$pTbl` WHERE id=?")->execute([$rid]);
        flashMessage('success', 'Отзыв удалён.');
    } elseif ($rid && $do === 'feature') {
        // Only approved reviews can be featured on the About page
        $db->prepare("UPDATE `$pTbl` SET is_featured = NOT is_featured WHERE id=? AND status='approved'")->execute([$rid]);
        flashMessage('success', 'Витрина «О нас» обновлена.');
    }
    redirect($return);
}

// ── List ────────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'pending';
$validStatus  = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($statusFilter, $validStatus, true)) $statusFilter = 'pending';
$where = $statusFilter === 'all' ? '' : 'WHERE r.status = ' . $db->quote($statusFilter);

if ($type === 'shop') {
    $rows = $db->query(
        "SELECT r.*, u.username, u.email, NULL AS part_name, 0 AS part_id
         FROM shop_reviews r JOIN users u ON u.id = r.user_id
         $where ORDER BY (r.status='pending') DESC, r.created_at DESC"
    )->fetchAll();
} else {
    $rows = $db->query(
        "SELECT r.*, u.username, u.email, p.name AS part_name
         FROM product_reviews r
         JOIN users u ON u.id = r.user_id
         JOIN parts p ON p.id = r.part_id
         $where ORDER BY (r.status='pending') DESC, r.created_at DESC"
    )->fetchAll();
}

function reviewCounts(PDO $db, string $tbl): array {
    $c = ['pending'=>0,'approved'=>0,'rejected'=>0];
    foreach ($db->query("SELECT status, COUNT(*) c FROM `$tbl` GROUP BY status") as $r) {
        $c[$r['status']] = (int)$r['c'];
    }
    $c['all'] = array_sum($c);
    return $c;
}
$counts        = reviewCounts($db, $tbl);
$pendingProduct = (int)$db->query("SELECT COUNT(*) FROM product_reviews WHERE status='pending'")->fetchColumn();
$pendingShop    = (int)$db->query("SELECT COUNT(*) FROM shop_reviews WHERE status='pending'")->fetchColumn();
$pendingTotal   = $pendingProduct + $pendingShop;

$pageTitle = 'Отзывы — Менеджер';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<div class="az-panel">

    <aside class="az-sidebar">
        <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
        <nav><ul>
            <li><a href="<?= APP_URL ?>/manager/index.php"><i class="fa fa-dashboard"></i> Панель</a></li>
            <li><a href="<?= APP_URL ?>/manager/parts.php"><i class="fa fa-cogs"></i> Запчасти</a></li>
            <li><a href="<?= APP_URL ?>/manager/categories.php"><i class="fa fa-sitemap"></i> Категории</a></li>
            <li><a href="<?= APP_URL ?>/manager/brands.php"><i class="fa fa-tag"></i> Бренды</a></li>
            <li><a href="<?= APP_URL ?>/manager/blog.php"><i class="fa fa-newspaper-o"></i> Блог</a></li>
            <li><a href="<?= APP_URL ?>/manager/pages.php"><i class="fa fa-file-text-o"></i> Страницы</a></li>
            <li><a href="<?= APP_URL ?>/manager/reviews.php" class="active"><i class="fa fa-star"></i> Отзывы
                <?php if ($pendingTotal): ?>
                <span style="background:#d32f2f;color:#fff;border-radius:10px;padding:1px 7px;font-size:0.7rem;margin-left:4px;"><?= $pendingTotal ?></span>
                <?php endif; ?>
            </a></li>
            <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:12px;">
                <a href="<?= APP_URL ?>/index.php"><i class="fa fa-home"></i> На сайт</a>
            </li>
            <li><a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,100,100,0.85)!important;"><i class="fa fa-sign-out"></i> Выйти</a></li>
        </ul></nav>
    </aside>

    <main class="az-main">
        <div class="az-topbar">
            <h1>Модерация отзывов</h1>
            <span style="font-size:0.85rem;color:#666;">
                <?= sanitize($_SESSION['username'] ?? '') ?>
                <span style="background:#d32f2f;color:#fff;border-radius:4px;padding:2px 7px;font-size:0.72rem;margin-left:4px;"><?= sanitize($_SESSION['role'] ?? '') ?></span>
            </span>
        </div>

        <div class="az-content">

            <?php if ($flash = getFlashMessage()): ?>
                <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
            <?php endif; ?>

            <!-- Type switch -->
            <div style="display:flex;gap:10px;margin-bottom:18px;border-bottom:2px solid #eee;padding-bottom:0;">
                <a href="?type=product&status=<?= $statusFilter ?>"
                   style="padding:10px 20px;font-weight:600;font-size:0.92rem;text-decoration:none;border-bottom:3px solid <?= $type==='product' ? '#d32f2f' : 'transparent' ?>;color:<?= $type==='product' ? '#d32f2f' : '#666' ?>;">
                    <i class="fa fa-cogs"></i> Отзывы о товарах
                    <?php if ($pendingProduct): ?><span style="background:#d32f2f;color:#fff;border-radius:10px;padding:1px 6px;font-size:0.68rem;"><?= $pendingProduct ?></span><?php endif; ?>
                </a>
                <a href="?type=shop&status=<?= $statusFilter ?>"
                   style="padding:10px 20px;font-weight:600;font-size:0.92rem;text-decoration:none;border-bottom:3px solid <?= $type==='shop' ? '#d32f2f' : 'transparent' ?>;color:<?= $type==='shop' ? '#d32f2f' : '#666' ?>;">
                    <i class="fa fa-building-o"></i> Отзывы о магазине
                    <?php if ($pendingShop): ?><span style="background:#d32f2f;color:#fff;border-radius:10px;padding:1px 6px;font-size:0.68rem;"><?= $pendingShop ?></span><?php endif; ?>
                </a>
            </div>

            <?php
            $tabs = ['pending'=>'Ожидают','approved'=>'Одобрены','rejected'=>'Отклонены','all'=>'Все'];
            ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;">
                <?php foreach ($tabs as $k => $label): $on = $statusFilter === $k; ?>
                <a href="?type=<?= $type ?>&status=<?= $k ?>"
                   style="padding:7px 16px;border-radius:20px;font-size:0.84rem;font-weight:600;text-decoration:none;
                          border:1px solid <?= $on ? '#d32f2f' : '#ddd' ?>;
                          background:<?= $on ? '#d32f2f' : '#fff' ?>;color:<?= $on ? '#fff' : '#555' ?>;">
                    <?= $label ?> (<?= $counts[$k] ?>)
                </a>
                <?php endforeach; ?>
            </div>

            <div class="az-card" style="background:#e3f2fd;border:1px solid #90caf9;color:#1565c0;font-size:0.84rem;">
                <i class="fa fa-info-circle"></i>
                Кнопка <i class="fa fa-bullhorn"></i> добавляет одобренный отзыв в блок
                <strong>«Что говорят клиенты»</strong> на странице
                <a href="<?= APP_URL ?>/pages/about.php#reviews" target="_blank">«О нас»</a>.
            </div>

            <div class="az-card" style="padding:0;overflow:hidden;">
                <table class="az-table">
                    <thead>
                        <tr>
                            <?php if ($type === 'product'): ?><th>Товар</th><?php endif; ?>
                            <th>Пользователь</th>
                            <th style="width:110px;">Оценка</th>
                            <th>Отзыв</th>
                            <th style="width:80px;">Дата</th>
                            <th style="width:85px;text-align:center;">Статус</th>
                            <th style="width:70px;text-align:center;">Витрина</th>
                            <th style="width:210px;text-align:center;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="8" style="text-align:center;padding:30px;color:#999;">Отзывов нет.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <?php if ($type === 'product'): ?>
                            <td style="font-size:0.85rem;font-weight:600;max-width:170px;">
                                <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$r['part_id'] ?>" target="_blank"
                                   style="color:#1565c0;text-decoration:none;"><?= sanitize(truncate($r['part_name'], 45)) ?></a>
                            </td>
                            <?php endif; ?>
                            <td style="font-size:0.82rem;">
                                <?= sanitize($r['username']) ?><br>
                                <span style="color:#999;font-size:0.76rem;"><?= sanitize($r['email']) ?></span>
                            </td>
                            <td style="white-space:nowrap;font-size:0.85rem;"><?= starsHtml((float)$r['rating']) ?></td>
                            <td style="font-size:0.85rem;color:#555;max-width:260px;"><?= nl2br(sanitize($r['comment'])) ?></td>
                            <td style="font-size:0.78rem;color:#888;"><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
                            <td style="text-align:center;">
                                <?php $st = ['pending'=>['warning','Ожидает'],'approved'=>['success','Одобрен'],'rejected'=>['danger','Отклонён']][$r['status']]; ?>
                                <span class="badge badge-<?= $st[0] ?>"><?= $st[1] ?></span>
                            </td>
                            <td style="text-align:center;">
                                <?php if ($r['status'] === 'approved'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="type" value="<?= $type ?>">
                                    <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="do" value="feature">
                                    <button type="submit" title="<?= !empty($r['is_featured']) ? 'Убрать из витрины «О нас»' : 'Показать на странице «О нас»' ?>"
                                            class="az-btn az-btn-sm <?= !empty($r['is_featured']) ? 'az-btn-success' : 'az-btn-secondary' ?>">
                                        <i class="fa fa-bullhorn"></i>
                                    </button>
                                </form>
                                <?php else: ?>
                                    <span style="color:#ccc;">—</span>
                                <?php endif; ?>
                            </td>
                            <td style="text-align:center;white-space:nowrap;">
                                <?php if ($r['status'] !== 'approved'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="type" value="<?= $type ?>">
                                    <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="do" value="approve">
                                    <button type="submit" class="az-btn az-btn-success az-btn-sm" title="Опубликовать"><i class="fa fa-check"></i></button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['status'] !== 'rejected'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="type" value="<?= $type ?>">
                                    <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="do" value="reject">
                                    <button type="submit" class="az-btn az-btn-secondary az-btn-sm" title="Отклонить"><i class="fa fa-ban"></i></button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить отзыв безвозвратно?');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="type" value="<?= $type ?>">
                                    <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="do" value="delete">
                                    <button type="submit" class="az-btn az-btn-danger az-btn-sm" title="Удалить"><i class="fa fa-trash-o"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </main>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
