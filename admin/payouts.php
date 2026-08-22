<?php
/**
 * Выплаты продавцам (маркетплейс, Фаза 3 — половина «деньги наружу»).
 *
 * Реестр «кому сколько должны» + проведение выплаты + выгрузка для бухгалтерии.
 * Эквайринг здесь не участвует: деньги площадка уже получила при доставке, эта
 * страница про то, как честно рассчитаться с продавцом и оставить след.
 *
 * Начисления сюда попадают сами (смена статуса подзаказа на «Доставлен»), но есть
 * кнопка пересчёта — она нужна для заказов, доставленных ДО появления журнала,
 * и на случай, если статус меняли напрямую в БД.
 */
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/seller_finance.php';
requireRole(['admin', 'superadmin']);

$db   = getDB();
$csrf = generateCsrfToken();
$ready = sellerFinanceReady($db);

// ── Выгрузка CSV для бухгалтерии ─────────────────────────────────────────────
// Отдаём до вывода HTML, иначе в файл попадёт разметка страницы.
if ($ready && ($_GET['export'] ?? '') === 'csv') {
    $rows = sellerPayoutRegistry($db);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="payouts-' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM — иначе Excel ломает кириллицу
    fputcsv($out, ['Магазин', 'Телефон', 'Комиссия %', 'Заработано', 'Выплачено', 'К выплате', 'Последняя выплата'], ';');
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['shop_name'], $r['phone'], $r['commission_percent'],
            number_format((float)$r['earned'], 2, '.', ''),
            number_format((float)$r['paid'], 2, '.', ''),
            number_format((float)$r['balance'], 2, '.', ''),
            $r['last_payout'] ? date('d.m.Y', strtotime($r['last_payout'])) : '',
        ], ';');
    }
    fclose($out);
    exit;
}

// ── POST ─────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF ошибка.');
        redirect(APP_URL . '/admin/payouts.php');
    }
    $act = $_POST['action'] ?? '';

    if (!$ready) {
        flashMessage('danger', 'Миграция финансов не применена: sql/marketplace_phase3_payouts.sql');

    } elseif ($act === 'sync') {
        [$accrued, $reversed] = sellerLedgerSyncAll($db);
        flashMessage('success', $accrued || $reversed
            ? "Пересчёт: начислено $accrued, сторнировано $reversed."
            : 'Пересчёт: всё уже учтено, изменений нет.');

    } elseif ($act === 'payout') {
        $sid    = (int)($_POST['seller_id'] ?? 0);
        $amount = (float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
        $sum    = sellerFinanceSummary($db, $sid);

        if ($amount <= 0) {
            flashMessage('danger', 'Сумма выплаты должна быть больше нуля.');
        } elseif ($amount > $sum['balance'] + 0.001) {
            // Переплату не пропускаем: она увела бы баланс в минус, и потом
            // невозможно понять, это долг продавца или ошибка ввода.
            // Число показываем «сырым», а не через formatPrice: поле ввода тоже
            // принимает сырую сумму, и лимит с подсказкой обязаны совпадать.
            flashMessage('danger', 'Сумма больше остатка к выплате ('
                . number_format($sum['balance'], 2, '.', ' ') . ').');
        } else {
            $id = sellerPayoutCreate(
                $db, $sid, $amount,
                (string)($_POST['method'] ?? 'card'),
                trim((string)($_POST['reference'] ?? '')),
                trim((string)($_POST['note'] ?? '')),
                (string)($_POST['period_from'] ?? '') ?: null,
                (string)($_POST['period_to'] ?? '') ?: null,
                (int)($_SESSION['user_id'] ?? 0)
            );
            flashMessage($id ? 'success' : 'danger',
                $id ? 'Выплата проведена: ' . formatPrice($amount) : 'Не удалось провести выплату.');
        }

    } elseif ($act === 'adjust') {
        // Ручная корректировка — штраф, компенсация, итог спора. Всегда с причиной:
        // движение без объяснения через полгода нечитаемо.
        $sid    = (int)($_POST['seller_id'] ?? 0);
        $amount = (float)str_replace(',', '.', (string)($_POST['amount'] ?? '0'));
        $note   = trim((string)($_POST['note'] ?? ''));
        if (abs($amount) < 0.01 || $note === '') {
            flashMessage('danger', 'Для корректировки нужны сумма и причина.');
        } else {
            sellerLedgerAdd($db, $sid, 'adjustment', $amount, null, $note);
            flashMessage('success', 'Корректировка проведена.');
        }
    }
    redirect(APP_URL . '/admin/payouts.php');
}

// ── Данные ───────────────────────────────────────────────────────────────────
$registry = $ready ? sellerPayoutRegistry($db) : [];
$totalDue = 0.0; $totalPaid = 0.0;
foreach ($registry as $r) { $totalDue += (float)$r['balance']; $totalPaid += (float)$r['paid']; }

$recent = [];
if ($ready) {
    $recent = $db->query(
        "SELECT p.*, s.shop_name, u.username
           FROM seller_payouts p
           JOIN sellers s ON s.id = p.seller_id
           LEFT JOIN users u ON u.id = p.created_by
       ORDER BY p.created_at DESC, p.id DESC LIMIT 50"
    )->fetchAll();
}

$pageTitle = 'Выплаты продавцам — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';
?>

<div class="az-panel">
  <?php renderRoleSidebar('payouts'); ?>

  <div class="az-main">
    <div class="az-topbar">
      <div class="az-topbar-title">Выплаты продавцам</div>
      <div class="az-topbar-user">
        <?= sanitize($_SESSION['username'] ?? 'Admin') ?> &middot;
        <a href="<?= APP_URL ?>/auth/logout.php">Выйти</a>
      </div>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="alert alert-<?= sanitize($flash['type']) ?> mb-16"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <?php if (!$ready): ?>
      <div class="az-card">
        <div class="az-card-body">
          <p><b>Раздел не активирован.</b> Примените миграцию финансов:</p>
          <pre style="background:#f6f6f8;padding:12px;border-radius:8px;overflow:auto;">php sql/apply.php sql/marketplace_phase3_payouts.sql</pre>
          <p class="text-muted mb-0">После этого нажмите «Пересчитать начисления» — заказы, доставленные раньше, попадут в балансы.</p>
        </div>
      </div>

      <?php else: ?>

      <!-- Итоги + обслуживание -->
      <div class="az-card mb-16">
        <div class="az-card-body">
          <div class="d-flex align-items-center gap-8 flex-wrap" style="justify-content:space-between;">
            <div>
              <div style="font-size:1.4rem;font-weight:600;">К выплате всего: <?= formatPrice($totalDue) ?></div>
              <div class="text-muted">Выплачено за всё время: <?= formatPrice($totalPaid) ?></div>
            </div>
            <div class="d-flex gap-8 flex-wrap">
              <a href="?export=csv" class="az-btn az-btn-outline"><i class="fa fa-download"></i> Выгрузить CSV</a>
              <form method="post" style="display:inline;">
                <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
                <input type="hidden" name="action" value="sync">
                <button type="submit" class="az-btn az-btn-outline"
                        title="Начислить по заказам, доставленным до появления журнала">
                  <i class="fa fa-refresh"></i> Пересчитать начисления
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <!-- Реестр -->
      <div class="az-card mb-16">
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr>
                  <th>Магазин</th>
                  <th>Комиссия</th>
                  <th>Заработано</th>
                  <th>Выплачено</th>
                  <th>К выплате</th>
                  <th>Последняя выплата</th>
                  <th>Действия</th>
                </tr>
              </thead>
              <tbody>
              <?php if (!$registry): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">Продавцов пока нет.</td></tr>
              <?php else: foreach ($registry as $r):
                $bal = (float)$r['balance']; $sid = (int)$r['id']; ?>
                <tr>
                  <td>
                    <b><?= sanitize($r['shop_name']) ?></b>
                    <?php if ($r['status'] !== 'approved'): ?>
                      <span class="text-muted">· <?= $r['status'] === 'blocked' ? 'заблокирован' : 'на модерации' ?></span>
                    <?php endif; ?>
                    <?php if (!empty($r['phone'])): ?><div class="text-muted"><?= sanitize($r['phone']) ?></div><?php endif; ?>
                  </td>
                  <td><?= rtrim(rtrim(number_format((float)$r['commission_percent'], 2, '.', ''), '0'), '.') ?>%</td>
                  <td><?= formatPrice((float)$r['earned']) ?></td>
                  <td><?= formatPrice((float)$r['paid']) ?></td>
                  <td><b style="<?= $bal > 0 ? 'color:#1a7f37;' : '' ?>"><?= formatPrice($bal) ?></b></td>
                  <td><?= $r['last_payout'] ? date('d.m.Y', strtotime($r['last_payout'])) : '—' ?></td>
                  <td>
                    <button type="button" class="az-btn az-btn-primary az-btn-sm"
                            onclick="payoutOpen(<?= $sid ?>, '<?= sanitize(addslashes($r['shop_name'])) ?>', <?= number_format($bal, 2, '.', '') ?>)"
                            <?= $bal <= 0 ? 'disabled title="Нечего выплачивать"' : '' ?>>
                      <i class="fa fa-money"></i> Выплатить
                    </button>
                    <button type="button" class="az-btn az-btn-outline az-btn-sm"
                            onclick="adjustOpen(<?= $sid ?>, '<?= sanitize(addslashes($r['shop_name'])) ?>')">
                      Корректировка
                    </button>
                  </td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- История выплат -->
      <div class="az-card">
        <div class="az-card-body p-0">
          <div class="table-responsive">
            <table class="az-table">
              <thead>
                <tr><th>Дата</th><th>Магазин</th><th>Сумма</th><th>Способ</th><th>Номер</th><th>Период</th><th>Провёл</th></tr>
              </thead>
              <tbody>
              <?php if (!$recent): ?>
                <tr><td colspan="7" class="text-center text-muted" style="padding:24px;">Выплат ещё не было.</td></tr>
              <?php else: foreach ($recent as $p): ?>
                <tr>
                  <td><?= date('d.m.Y H:i', strtotime($p['created_at'])) ?></td>
                  <td><?= sanitize($p['shop_name']) ?></td>
                  <td><b><?= formatPrice((float)$p['amount']) ?></b></td>
                  <td><?= sanitize(sellerPayoutMethodLabel($p['method'])) ?></td>
                  <td><?= $p['reference'] ? sanitize($p['reference']) : '—' ?></td>
                  <td>
                    <?php if ($p['period_from'] && $p['period_to']): ?>
                      <?= date('d.m.y', strtotime($p['period_from'])) ?>–<?= date('d.m.y', strtotime($p['period_to'])) ?>
                    <?php else: ?>—<?php endif; ?>
                  </td>
                  <td><?= sanitize($p['username'] ?? '—') ?></td>
                </tr>
              <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Модалка выплаты -->
      <div class="az-modal" id="payoutModal" style="display:none;">
        <div class="az-modal-box">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="payout">
            <input type="hidden" name="seller_id" id="pmSeller">
            <h3 class="az-modal-title">Выплата — <span id="pmShop"></span></h3>

            <div class="az-form-group">
              <label>Сумма</label>
              <!-- Поле принимает сумму в той же валюте, в которой хранятся заказы,
                   поэтому и подсказка ниже — «сырое» число без пересчёта курса:
                   лимит проверки и то, что видит владелец, обязаны совпадать. -->
              <input type="number" step="0.01" min="0.01" name="amount" id="pmAmount" required>
              <div class="text-muted" style="font-size:.8rem;margin-top:6px;">
                Доступно к выплате: <b id="pmMax"></b> <?= sanitize(getCurrencySymbol()) ?>
              </div>
            </div>

            <div class="az-form-group">
              <label>Способ</label>
              <select name="method">
                <option value="card">На карту</option>
                <option value="cash">Наличные</option>
                <option value="bank">Банковский перевод</option>
                <option value="mobile">Мобильный кошелёк</option>
                <option value="other">Другое</option>
              </select>
            </div>

            <div class="az-form-group">
              <label>Номер перевода / чека</label>
              <input type="text" name="reference" maxlength="120" placeholder="чтобы найти платёж в банке">
            </div>

            <div class="d-flex gap-8">
              <div class="az-form-group" style="flex:1;">
                <label>Период с</label>
                <input type="date" name="period_from">
              </div>
              <div class="az-form-group" style="flex:1;">
                <label>по</label>
                <input type="date" name="period_to">
              </div>
            </div>

            <div class="az-form-group">
              <label>Примечание</label>
              <input type="text" name="note" maxlength="255">
            </div>

            <div class="az-modal-acts">
              <button type="button" class="az-btn az-btn-outline" onclick="payoutClose()">Отмена</button>
              <button type="submit" class="az-btn az-btn-primary">Провести выплату</button>
            </div>
          </form>
        </div>
      </div>

      <!-- Модалка корректировки -->
      <div class="az-modal" id="adjustModal" style="display:none;">
        <div class="az-modal-box">
          <form method="post">
            <input type="hidden" name="csrf_token" value="<?= sanitize($csrf) ?>">
            <input type="hidden" name="action" value="adjust">
            <input type="hidden" name="seller_id" id="amSeller">
            <h3 class="az-modal-title">Корректировка — <span id="amShop"></span></h3>
            <p class="text-muted" style="font-size:.85rem;">
              Плюс — начислить продавцу (компенсация), минус — удержать (штраф, итог спора).
            </p>

            <div class="az-form-group">
              <label>Сумма (со знаком)</label>
              <input type="number" step="0.01" name="amount" required placeholder="например -50 или 120">
            </div>

            <div class="az-form-group">
              <label>Причина (обязательно)</label>
              <input type="text" name="note" maxlength="255" required>
            </div>

            <div class="az-modal-acts">
              <button type="button" class="az-btn az-btn-outline" onclick="adjustClose()">Отмена</button>
              <button type="submit" class="az-btn az-btn-primary">Провести</button>
            </div>
          </form>
        </div>
      </div>

      <script>
      function payoutOpen(id, shop, max) {
        document.getElementById('pmSeller').value = id;
        document.getElementById('pmShop').textContent = shop;
        document.getElementById('pmMax').textContent = max;
        var a = document.getElementById('pmAmount');
        a.max = max; a.value = max;               // по умолчанию гасим весь долг
        document.getElementById('payoutModal').style.display = 'flex';
      }
      function payoutClose() { document.getElementById('payoutModal').style.display = 'none'; }
      function adjustOpen(id, shop) {
        document.getElementById('amSeller').value = id;
        document.getElementById('amShop').textContent = shop;
        document.getElementById('adjustModal').style.display = 'flex';
      }
      function adjustClose() { document.getElementById('adjustModal').style.display = 'none'; }
      // Клик по затемнению закрывает — но не клик внутри самой формы.
      document.querySelectorAll('.az-modal').forEach(function (m) {
        m.addEventListener('click', function (e) { if (e.target === m) m.style.display = 'none'; });
      });
      // Esc закрывает любую открытую модалку.
      document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        document.querySelectorAll('.az-modal').forEach(function (m) { m.style.display = 'none'; });
      });
      </script>

      <?php endif; ?>
    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
