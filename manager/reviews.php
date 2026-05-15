<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'admin', 'superadmin']);

$db   = getDB();
$csrf = generateCsrfToken();

// ── POST: moderation actions ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/manager/reviews.php');
    }
    $rid    = (int)($_POST['id'] ?? 0);
    $do     = $_POST['do'] ?? '';
    $return = APP_URL . '/manager/reviews.php?status=' . urlencode($_POST['status'] ?? 'pending');

    if ($rid && $do === 'approve') {
        $db->prepare("UPDATE product_reviews SET status='approved' WHERE id=?")->execute([$rid]);
        flashMessage('success', 'Отзыв опубликован.');
    } elseif ($rid && $do === 'reject') {
        $db->prepare("UPDATE product_reviews SET status='rejected' WHERE id=?")->execute([$rid]);
        flashMessage('success', 'Отзыв отклонён.');
    } elseif ($rid && $do === 'delete') {
        $db->prepare("DELETE FROM product_reviews WHERE id=?")->execute([$rid]);
        flashMessage('success', 'Отзыв удалён.');
    }
    redirect($return);
}

// ── List ────────────────────────────────────────────────────────────────────
$statusFilter = $_GET['status'] ?? 'pending';
$validStatus  = ['pending', 'approved', 'rejected', 'all'];
if (!in_array($statusFilter, $validStatus, true)) $statusFilter = 'pending';

$where  = $statusFilter === 'all' ? '' : 'WHERE r.status = ' . $db->quote($statusFilter);
$rows = $db->query(
    "SELECT r.*, p.name AS part_name, u.username, u.email
     FROM product_reviews r
     JOIN parts p ON p.id = r.part_id
     JOIN users u ON u.id = r.user_id
     $where
     ORDER BY (r.status='pending') DESC, r.created_at DESC"
)->fetchAll();

// Counts per status for the filter tabs
$counts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
foreach ($db->query("SELECT status, COUNT(*) c FROM product_reviews GROUP BY status") as $cr) {
    $counts[$cr['status']] = (int)$cr['c'];
}
$counts['all'] = array_sum($counts);

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
                <?php if ($counts['pending']): ?>
                <span style="background:#d32f2f;color:#fff;border-radius:10px;padding:1px 7px;font-size:0.7rem;margin-left:4px;"><?= $counts['pending'] ?></span>
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

            <?php
            $tabs = [
                'pending'  => ['Ожидают',     '#fff3e0', '#e65100'],
                'approved' => ['Одобрены',    '#e8f5e9', '#2e7d32'],
                'rejected' => ['Отклонены',   '#ffebee', '#c62828'],
                'all'      => ['Все',         '#eceff1', '#455a64'],
            ];
            ?>
            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-bottom:18px;">
                <?php foreach ($tabs as $k => $meta): $on = $statusFilter === $k; ?>
                <a href="?status=<?= $k ?>"
                   style="padding:7px 16px;border-radius:20px;font-size:0.84rem;font-weight:600;text-decoration:none;
                          border:1px solid <?= $on ? '#d32f2f' : '#ddd' ?>;
                          background:<?= $on ? '#d32f2f' : '#fff' ?>;color:<?= $on ? '#fff' : '#555' ?>;">
                    <?= $meta[0] ?> (<?= $counts[$k] ?>)
                </a>
                <?php endforeach; ?>
            </div>

            <div class="az-card" style="padding:0;overflow:hidden;">
                <table class="az-table">
                    <thead>
                        <tr>
                            <th>Товар</th>
                            <th>Пользователь</th>
                            <th style="width:110px;">Оценка</th>
                            <th>Отзыв</th>
                            <th style="width:90px;">Дата</th>
                            <th style="width:90px;text-align:center;">Статус</th>
                            <th style="width:230px;text-align:center;">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($rows)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:30px;color:#999;">Отзывов нет.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($rows as $r): ?>
                        <tr>
                            <td style="font-size:0.85rem;font-weight:600;max-width:180px;">
                                <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$r['part_id'] ?>" target="_blank"
                                   style="color:#1565c0;text-decoration:none;"><?= sanitize(truncate($r['part_name'], 50)) ?></a>
                            </td>
                            <td style="font-size:0.82rem;">
                                <?= sanitize($r['username']) ?><br>
                                <span style="color:#999;font-size:0.76rem;"><?= sanitize($r['email']) ?></span>
                            </td>
                            <td style="white-space:nowrap;font-size:0.85rem;"><?= starsHtml((float)$r['rating']) ?></td>
                            <td style="font-size:0.85rem;color:#555;max-width:280px;"><?= nl2br(sanitize($r['comment'])) ?></td>
                            <td style="font-size:0.78rem;color:#888;"><?= date('d.m.Y', strtotime($r['created_at'])) ?></td>
                            <td style="text-align:center;">
                                <?php
                                $st = ['pending'=>['warning','Ожидает'],'approved'=>['success','Одобрен'],'rejected'=>['danger','Отклонён']][$r['status']];
                                ?>
                                <span class="badge badge-<?= $st[0] ?>"><?= $st[1] ?></span>
                            </td>
                            <td style="text-align:center;white-space:nowrap;">
                                <?php if ($r['status'] !== 'approved'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="do" value="approve">
                                    <button type="submit" class="az-btn az-btn-success az-btn-sm" title="Опубликовать">
                                        <i class="fa fa-check"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <?php if ($r['status'] !== 'rejected'): ?>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="do" value="reject">
                                    <button type="submit" class="az-btn az-btn-secondary az-btn-sm" title="Отклонить">
                                        <i class="fa fa-ban"></i>
                                    </button>
                                </form>
                                <?php endif; ?>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Удалить отзыв безвозвратно?');">
                                    <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                                    <input type="hidden" name="status" value="<?= sanitize($statusFilter) ?>">
                                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                                    <input type="hidden" name="do" value="delete">
                                    <button type="submit" class="az-btn az-btn-danger az-btn-sm" title="Удалить">
                                        <i class="fa fa-trash-o"></i>
                                    </button>
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
