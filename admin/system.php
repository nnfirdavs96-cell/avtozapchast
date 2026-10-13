<?php
/**
 * Система — серверная информация (PHP/MySQL/диск/APP_URL). ТОЛЬКО суперадмин:
 * версии и параметры сервера чувствительны, другим ролям не отдаём. Проверка
 * роли на сервере — requireRole('superadmin') даёт 403/редирект не-суперадмину.
 */
require_once dirname(__DIR__) . '/config/config.php';
requireRole('superadmin');

$db = getDB();

$phpVersion   = phpversion();
$mysqlVersion = 'N/A';
try { $mysqlVersion = $db->query("SELECT VERSION()")->fetchColumn(); } catch (Exception $e) {}
$diskTotal = disk_total_space('/');
$diskFree  = disk_free_space('/');
$diskUsed  = $diskTotal - $diskFree;

$pageTitle = 'Система — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/admin-header.php';

function formatBytes($bytes, $precision = 1): string {
    $units = ['Б', 'КБ', 'МБ', 'ГБ', 'ТБ'];
    $bytes = max($bytes, 0);
    $pow   = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow   = min($pow, count($units) - 1);
    return round($bytes / pow(1024, $pow), $precision) . ' ' . $units[$pow];
}
?>

<div class="az-panel">
  <?php renderRoleSidebar('system'); ?>

  <div class="az-main">
    <div class="az-topbar">
      <h1>Система</h1>
      <span style="font-size:0.85rem;color:#666;">
        <?= sanitize($_SESSION['username'] ?? 'Superadmin') ?>
        <span style="background:#7b1fa2;color:#fff;border-radius:4px;padding:2px 7px;font-size:0.72rem;margin-left:4px;">superadmin</span>
      </span>
    </div>

    <div class="az-content">
      <?php if ($flash = getFlashMessage()): ?>
      <div class="az-alert az-alert-<?= sanitize($flash['type']) ?>"><?= sanitize($flash['message']) ?></div>
      <?php endif; ?>

      <div class="row">
        <div class="col-lg-5 mb-24">
          <div class="az-card">
            <div class="az-card-header">
              <h4 class="az-card-title">Система</h4>
            </div>
            <div class="az-card-body">
              <table class="table table-sm table-borderless" style="font-size:0.85rem;">
                <tr>
                  <td class="text-muted">PHP</td>
                  <td><strong><?= sanitize($phpVersion) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">MySQL</td>
                  <td><strong><?= sanitize($mysqlVersion) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">Диск всего</td>
                  <td><strong><?= formatBytes($diskTotal) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">Использовано</td>
                  <td><strong><?= formatBytes($diskUsed) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">Свободно</td>
                  <td><strong><?= formatBytes($diskFree) ?></strong></td>
                </tr>
                <tr>
                  <td class="text-muted">Загрузка</td>
                  <td>
                    <?php $diskPct = $diskTotal > 0 ? round($diskUsed / $diskTotal * 100) : 0; ?>
                    <div style="background:#eee;border-radius:4px;height:6px;width:100%;overflow:hidden;">
                      <div style="background:<?= $diskPct > 80 ? '#c62828' : '#388e3c' ?>;height:100%;width:<?= $diskPct ?>%;"></div>
                    </div>
                    <small class="text-muted"><?= $diskPct ?>%</small>
                  </td>
                </tr>
                <tr>
                  <td class="text-muted">APP_URL</td>
                  <td><code><?= sanitize(APP_URL ?: '(relative)') ?></code></td>
                </tr>
                <tr>
                  <td class="text-muted">Сайт</td>
                  <td><strong><?= sanitize(getSetting('site_name')) ?></strong></td>
                </tr>
              </table>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /.az-content -->
  </div><!-- /.az-main -->
</div><!-- /.az-panel -->

<?php require_once dirname(__DIR__) . '/includes/admin-footer.php'; ?>
