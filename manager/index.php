<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['manager', 'admin', 'superadmin']);

$db = getDB();

// ── Stats ──────────────────────────────────────────────────────────────
try { $totalParts  = (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1")->fetchColumn(); } catch (Exception $e) { $totalParts = 0; }
try { $totalCats   = (int)$db->query("SELECT COUNT(*) FROM categories WHERE is_active = 1")->fetchColumn(); } catch (Exception $e) { $totalCats = 0; }
try { $totalBrands = (int)$db->query("SELECT COUNT(*) FROM brands WHERE is_active = 1")->fetchColumn(); } catch (Exception $e) { $totalBrands = 0; }
try { $lowStock    = (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1 AND stock <= 5 AND stock > 0")->fetchColumn(); } catch (Exception $e) { $lowStock = 0; }
try { $outStock    = (int)$db->query("SELECT COUNT(*) FROM parts WHERE is_active = 1 AND stock = 0")->fetchColumn(); } catch (Exception $e) { $outStock = 0; }

// ── Recent parts (last 10 added/updated) ──────────────────────────────
try {
    $recentParts = $db->query(
        "SELECT p.*, b.name AS brand_name, c.name AS category_name
         FROM parts p
         LEFT JOIN brands b ON b.id = p.brand_id
         LEFT JOIN categories c ON c.id = p.category_id
         WHERE p.is_active = 1
         ORDER BY GREATEST(p.created_at, COALESCE(p.updated_at, p.created_at)) DESC
         LIMIT 10"
    )->fetchAll();
} catch (Exception $e) { $recentParts = []; }

$pageTitle = 'Панель менеджера';
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">

    <!-- ── Sidebar ─────────────────────────────────────────────────── -->
    <aside class="az-sidebar">
        <div class="az-sidebar-logo">AUTO<span>PARTS</span></div>
        <nav>
            <ul>
                <li><a href="<?= APP_URL ?>/manager/index.php" class="active"><i class="fa fa-dashboard"></i> <?= t('dashboard') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/parts.php"><i class="fa fa-cogs"></i> <?= t('parts_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/categories.php"><i class="fa fa-sitemap"></i> <?= t('categories_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/brands.php"><i class="fa fa-tag"></i> <?= t('brands_mgmt') ?></a></li>
                <li><a href="<?= APP_URL ?>/manager/blog.php"><i class="fa fa-newspaper-o"></i> Блог</a></li>
                <li style="border-top:1px solid rgba(255,255,255,0.1);margin-top:20px;">
                    <a href="<?= APP_URL ?>/index.php"><i class="fa fa-home"></i> На сайт</a>
                </li>
                <li>
                    <a href="<?= APP_URL ?>/auth/logout.php" style="color:rgba(255,100,100,0.85)!important;">
                        <i class="fa fa-sign-out"></i> <?= t('logout') ?>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <!-- ── Main ───────────────────────────────────────────────────── -->
    <main class="az-main">
        <div class="az-topbar">
            <h1><?= t('dashboard') ?> — Менеджер</h1>
            <div style="display:flex;align-items:center;gap:12px;">
                <?php $currentUser = getCurrentUser(); ?>
                <span style="font-size:0.875rem;color:#666;">
                    <i class="fa fa-user-o"></i> <?= sanitize($currentUser['username'] ?? '') ?>
                    &nbsp;<span style="background:#d32f2f;color:#fff;border-radius:4px;padding:2px 8px;font-size:0.72rem;"><?= sanitize($currentUser['role'] ?? '') ?></span>
                </span>
            </div>
        </div>

        <div class="az-content">

            <!-- Stat cards -->
            <div class="az-stats">
                <div class="az-stat-card">
                    <div class="stat-val"><?= $totalParts ?></div>
                    <div class="stat-lbl"><i class="fa fa-cogs"></i> Запчастей (активных)</div>
                </div>
                <div class="az-stat-card" style="border-left-color:#1976d2;">
                    <div class="stat-val" style="color:#1976d2;"><?= $totalCats ?></div>
                    <div class="stat-lbl"><i class="fa fa-sitemap"></i> Категорий</div>
                </div>
                <div class="az-stat-card" style="border-left-color:#7b1fa2;">
                    <div class="stat-val" style="color:#7b1fa2;"><?= $totalBrands ?></div>
                    <div class="stat-lbl"><i class="fa fa-tag"></i> Брендов</div>
                </div>
                <div class="az-stat-card" style="border-left-color:#f57c00;">
                    <div class="stat-val" style="color:#f57c00;"><?= $lowStock ?></div>
                    <div class="stat-lbl"><i class="fa fa-exclamation-triangle"></i> Заканчивается (≤5)</div>
                </div>
                <div class="az-stat-card" style="border-left-color:#c62828;">
                    <div class="stat-val" style="color:#c62828;"><?= $outStock ?></div>
                    <div class="stat-lbl"><i class="fa fa-times-circle-o"></i> Нет в наличии</div>
                </div>
            </div>

            <!-- Quick actions -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:16px;margin-bottom:28px;">
                <a href="<?= APP_URL ?>/manager/parts.php?action=new"
                   style="display:flex;align-items:center;gap:10px;background:#d32f2f;border-radius:10px;padding:16px;text-decoration:none;color:#fff;">
                    <i class="fa fa-plus-circle" style="font-size:1.4rem;"></i>
                    <span style="font-weight:700;font-size:0.875rem;">Добавить запчасть</span>
                </a>
                <a href="<?= APP_URL ?>/manager/categories.php"
                   style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:16px;text-decoration:none;color:#333;box-shadow:0 1px 4px rgba(0,0,0,0.08);border-left:4px solid #1976d2;">
                    <i class="fa fa-sitemap" style="font-size:1.4rem;color:#1976d2;"></i>
                    <span style="font-weight:600;font-size:0.875rem;">Категории</span>
                </a>
                <a href="<?= APP_URL ?>/manager/brands.php"
                   style="display:flex;align-items:center;gap:10px;background:#fff;border-radius:10px;padding:16px;text-decoration:none;color:#333;box-shadow:0 1px 4px rgba(0,0,0,0.08);border-left:4px solid #7b1fa2;">
                    <i class="fa fa-tag" style="font-size:1.4rem;color:#7b1fa2;"></i>
                    <span style="font-weight:600;font-size:0.875rem;">Бренды</span>
                </a>
            </div>

            <!-- Recent parts table -->
            <div class="az-card">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;">
                    <h3 style="margin:0;">Последние добавленные / обновлённые</h3>
                    <a href="<?= APP_URL ?>/manager/parts.php" class="az-btn az-btn-secondary az-btn-sm">Все запчасти</a>
                </div>

                <?php if (empty($recentParts)): ?>
                    <p style="text-align:center;color:#aaa;padding:30px;">Запчастей ещё нет.</p>
                <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="az-table">
                            <thead>
                                <tr>
                                    <th>Артикул</th>
                                    <th>Название</th>
                                    <th>Бренд</th>
                                    <th>Категория</th>
                                    <th style="text-align:right;">Цена</th>
                                    <th style="text-align:center;">Остаток</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentParts as $p):
                                    $st = getStockStatus((int)$p['stock']);
                                ?>
                                    <tr>
                                        <td><code style="font-size:0.8rem;"><?= sanitize($p['part_number']) ?></code></td>
                                        <td style="font-size:0.875rem;"><?= sanitize(truncate($p['name'], 40)) ?></td>
                                        <td style="color:#888;font-size:0.8rem;"><?= sanitize($p['brand_name'] ?? '—') ?></td>
                                        <td style="color:#888;font-size:0.8rem;"><?= sanitize($p['category_name'] ?? '—') ?></td>
                                        <td style="text-align:right;font-weight:700;color:#d32f2f;"><?= formatPrice($p['price']) ?></td>
                                        <td style="text-align:center;">
                                            <span class="badge badge-<?= $st['class'] ?>"><?= (int)$p['stock'] ?> шт</span>
                                        </td>
                                        <td>
                                            <a href="<?= APP_URL ?>/manager/parts.php?action=edit&id=<?= (int)$p['id'] ?>"
                                               class="az-btn az-btn-secondary az-btn-sm">Ред.</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

        </div><!-- /.az-content -->
    </main>
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
