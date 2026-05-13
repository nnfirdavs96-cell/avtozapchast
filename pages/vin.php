<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';

$pageTitle = 'VIN-поиск запчастей — ' . getSetting('site_name');

$vin            = strtoupper(trim($_GET['vin'] ?? $_POST['vin'] ?? ''));
$result         = null;
$compatParts    = [];
$error          = '';
$searchPerfomed = false;

if ($vin) {
    $searchPerfomed = true;
    if (!VinService::validate($vin)) {
        $error = 'Неверный VIN-код. Проверьте: 17 символов, только A–Z и 0–9, без букв I, O, Q.';
    } else {
        $result = VinService::decode($vin);
        if (!empty($result['make'])) {
            $compatParts = VinService::searchCompatibleParts(
                $result['make'],
                $result['model'] ?? '',
                (int)($result['year'] ?? 0)
            );
        }
    }
}

require_once dirname(__DIR__) . '/includes/header.php';
?>

<?= breadcrumb([
    ['label'=>t('home'),'url'=>APP_URL.'/index.php'],
    ['label'=>'VIN-поиск'],
]) ?>

<div style="background:#f8f9fa;min-height:60vh;padding:40px 0 60px;">
<div class="container">

    <!-- ── Hero search block ─────────────────────────────────────────── -->
    <div style="max-width:760px;margin:0 auto 40px;text-align:center;">
        <h1 style="font-size:2rem;font-weight:800;margin-bottom:8px;color:#1a1a2e;">
            <i class="fa fa-search" style="color:#d32f2f;"></i>
            Поиск запчастей по VIN
        </h1>
        <p style="color:#666;margin-bottom:28px;font-size:1rem;">
            Введите 17-значный VIN-номер автомобиля — мы определим марку, модель, год выпуска
            и подберём совместимые запчасти из нашего каталога.
        </p>

        <form method="GET" action="" id="vinForm">
            <div style="display:flex;gap:0;box-shadow:0 4px 24px rgba(0,0,0,0.12);border-radius:10px;overflow:hidden;">
                <input type="text" name="vin" id="vinInput"
                       value="<?= sanitize($vin) ?>"
                       placeholder="Введите VIN (17 символов): WBAWX31060PK42218"
                       maxlength="17"
                       style="flex:1;padding:18px 20px;font-size:1.05rem;border:none;outline:none;letter-spacing:1px;font-family:monospace;text-transform:uppercase;background:#fff;">
                <button type="submit"
                        style="padding:18px 32px;background:#d32f2f;color:#fff;border:none;cursor:pointer;font-size:1rem;font-weight:700;white-space:nowrap;transition:background 0.2s;">
                    <i class="fa fa-search"></i> Найти
                </button>
            </div>
            <p style="font-size:0.8rem;color:#aaa;margin-top:8px;">
                VIN находится: в ПТС / СТС, на панели приборов (видно через лобовое стекло), на пороге водительской двери.
            </p>
        </form>
    </div>

    <?php if ($searchPerfomed): ?>

    <?php if ($error): ?>
    <!-- Error -->
    <div style="max-width:760px;margin:0 auto 32px;">
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:20px 24px;color:#856404;">
            <i class="fa fa-exclamation-triangle" style="margin-right:8px;"></i>
            <?= sanitize($error) ?>
        </div>
    </div>

    <?php elseif ($result): ?>
    <!-- ── Decoded car info ──────────────────────────────────────────── -->
    <div style="max-width:900px;margin:0 auto;">

        <div style="background:#fff;border-radius:12px;box-shadow:0 2px 16px rgba(0,0,0,0.08);overflow:hidden;margin-bottom:32px;">
            <!-- Header -->
            <div style="background:linear-gradient(135deg,#1a1a2e 0%,#d32f2f 100%);padding:24px 28px;color:#fff;display:flex;align-items:center;gap:16px;flex-wrap:wrap;">
                <div style="background:rgba(255,255,255,0.15);border-radius:8px;padding:14px 18px;font-family:monospace;font-size:1.2rem;letter-spacing:3px;font-weight:700;">
                    <?= sanitize($vin) ?>
                </div>
                <div>
                    <div style="font-size:1.5rem;font-weight:800;">
                        <?= sanitize(($result['make'] ?? '') . ' ' . ($result['model'] ?? '')) ?>
                    </div>
                    <div style="opacity:0.8;font-size:0.9rem;">
                        <?= sanitize($result['year'] > 0 ? $result['year'] . ' г.' : '') ?>
                        <?= sanitize($result['country'] ? ' · ' . $result['country'] : '') ?>
                    </div>
                </div>
                <div style="margin-left:auto;">
                    <?php if (!empty($result['from_cache'])): ?>
                    <span style="background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:20px;font-size:0.75rem;">
                        <i class="fa fa-database"></i> Кэш
                    </span>
                    <?php endif; ?>
                    <span style="background:rgba(255,255,255,0.2);padding:4px 10px;border-radius:20px;font-size:0.75rem;margin-left:4px;">
                        <i class="fa fa-plug"></i> <?= sanitize(strtoupper($result['source'] ?? 'local')) ?>
                    </span>
                </div>
            </div>

            <!-- Details grid -->
            <div style="padding:24px 28px;">
                <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:20px;">
                    <?php
                    $fields = [
                        'make'         => ['Марка',       'fa-car'],
                        'model'        => ['Модель',      'fa-tag'],
                        'year'         => ['Год выпуска', 'fa-calendar'],
                        'body_type'    => ['Тип кузова',  'fa-cube'],
                        'engine'       => ['Двигатель',   'fa-cog'],
                        'fuel_type'    => ['Топливо',     'fa-tint'],
                        'drive_type'   => ['Привод',      'fa-road'],
                        'country'      => ['Страна',      'fa-globe'],
                        'manufacturer' => ['Производитель','fa-industry'],
                        'plant_country'=> ['Завод',       'fa-map-marker'],
                    ];
                    foreach ($fields as $key => [$label, $icon]):
                        $val = $result[$key] ?? '';
                        if (!$val || $val === '0') continue;
                    ?>
                    <div style="border:1px solid #eef0f3;border-radius:8px;padding:14px 16px;">
                        <div style="font-size:0.72rem;color:#aaa;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;">
                            <i class="fa <?= $icon ?>"></i> <?= $label ?>
                        </div>
                        <div style="font-weight:600;color:#1a1a2e;font-size:0.95rem;">
                            <?= sanitize((string)$val) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- ── Compatible parts ──────────────────────────────────────── -->
        <?php if (!empty($compatParts)): ?>
        <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:20px;color:#1a1a2e;">
            <i class="fa fa-cogs" style="color:#d32f2f;"></i>
            Совместимые запчасти (<?= count($compatParts) ?>)
        </h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;margin-bottom:40px;">
            <?php foreach ($compatParts as $p):
                $imgs  = json_decode($p['images'] ?? '[]', true) ?: [];
                $thumb = $imgs[0] ?? '';
                $st    = getStockStatus((int)$p['stock']);
            ?>
            <div style="background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.07);overflow:hidden;transition:box-shadow 0.2s;" onmouseover="this.style.boxShadow='0 6px 24px rgba(0,0,0,0.14)'" onmouseout="this.style.boxShadow='0 2px 10px rgba(0,0,0,0.07)'">
                <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$p['id'] ?>">
                    <?php if ($thumb): ?>
                    <img src="<?= sanitize($thumb) ?>" alt="<?= sanitize($p['name']) ?>"
                         style="width:100%;height:160px;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:120px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#ddd;font-size:2rem;">
                        <i class="fa fa-image"></i>
                    </div>
                    <?php endif; ?>
                </a>
                <div style="padding:14px 16px;">
                    <div style="font-size:0.75rem;color:#aaa;margin-bottom:4px;">
                        <?= sanitize($p['brand_name'] ?? '') ?>
                        <?= sanitize($p['category_name'] ? ' · ' . $p['category_name'] : '') ?>
                    </div>
                    <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$p['id'] ?>"
                       style="font-weight:600;color:#1a1a2e;font-size:0.9rem;display:block;margin-bottom:8px;text-decoration:none;">
                        <?= sanitize(truncate($p['name'], 50)) ?>
                    </a>
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <span style="font-size:1.1rem;font-weight:800;color:#d32f2f;">
                            <?= formatPrice($p['price']) ?>
                        </span>
                        <span class="badge badge-<?= $st['class'] ?>" style="font-size:0.72rem;">
                            <?= $st['label'] ?>
                        </span>
                    </div>
                    <a href="<?= APP_URL ?>/catalog/part.php?id=<?= (int)$p['id'] ?>"
                       style="display:block;margin-top:10px;background:#d32f2f;color:#fff;text-align:center;padding:8px;border-radius:6px;font-size:0.85rem;font-weight:600;text-decoration:none;">
                        Подробнее
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <?php else: ?>
        <!-- No compat parts yet — suggest catalog search -->
        <div style="background:#fff;border-radius:10px;padding:28px 24px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:32px;">
            <i class="fa fa-search" style="font-size:2.5rem;color:#ddd;display:block;margin-bottom:12px;"></i>
            <h3 style="color:#555;margin-bottom:8px;">Совместимые запчасти ещё не привязаны</h3>
            <p style="color:#888;margin-bottom:20px;">
                Но вы можете найти нужные детали, выполнив поиск по названию марки
                <?= sanitize($result['make'] ? '«' . $result['make'] . '»' : '') ?> в каталоге.
            </p>
            <a href="<?= APP_URL ?>/search/index.php?q=<?= urlencode($result['make'] ?? '') ?>"
               style="display:inline-block;background:#d32f2f;color:#fff;padding:10px 28px;border-radius:6px;font-weight:600;text-decoration:none;">
                <i class="fa fa-search"></i> Найти в каталоге
            </a>
        </div>
        <?php endif; ?>

    </div><!-- /max-width -->
    <?php endif; // result ?>

    <?php else: ?>
    <!-- ── Tips (shown when no search yet) ──────────────────────────── -->
    <div style="max-width:900px;margin:0 auto;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;">
            <?php
            $tips = [
                ['fa-shield','Где найти VIN?',
                 'В ПТС/СТС, на таблице под лобовым стеклом, на пороге водительской двери или в дверной стойке.'],
                ['fa-globe','Что показывает VIN?',
                 'Страну производства, завод, марку, модель, год выпуска, тип двигателя и кузова.'],
                ['fa-cogs','Подбор запчастей',
                 'По VIN мы автоматически показываем совместимые запчасти из нашего каталога.'],
            ];
            foreach ($tips as [$icon, $title, $text]):
            ?>
            <div style="background:#fff;border-radius:10px;padding:24px;box-shadow:0 2px 10px rgba(0,0,0,0.06);">
                <div style="width:48px;height:48px;background:#fce4e4;border-radius:10px;display:flex;align-items:center;justify-content:center;margin-bottom:14px;">
                    <i class="fa fa-<?= $icon ?>" style="font-size:1.4rem;color:#d32f2f;"></i>
                </div>
                <h3 style="font-size:1rem;font-weight:700;margin-bottom:8px;color:#1a1a2e;"><?= $title ?></h3>
                <p style="color:#777;font-size:0.875rem;line-height:1.6;"><?= $text ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

</div>
</div>

<script>
// Auto-uppercase and filter invalid chars
document.getElementById('vinInput').addEventListener('input', function() {
    this.value = this.value.toUpperCase().replace(/[^A-HJ-NPR-Z0-9]/g, '');
});
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
