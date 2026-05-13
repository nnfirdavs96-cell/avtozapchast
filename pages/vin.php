<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';

function getCarImageUrl(string $make, string $model, int $year): string {
    $make  = strtolower(trim($make));
    $model = strtolower(trim($model));

    // Russian/CIS brands — Wikipedia Commons static images
    $staticMap = [
        'lada'  => 'https://upload.wikimedia.org/wikipedia/commons/thumb/5/59/Lada_Vesta_sedan_%28facelift%2C_side%29.jpg/800px-Lada_Vesta_sedan_%28facelift%2C_side%29.jpg',
        'uaz'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/d/d9/UAZ_Patriot_2014.jpg/800px-UAZ_Patriot_2014.jpg',
        'gaz'   => 'https://upload.wikimedia.org/wikipedia/commons/thumb/7/7c/Volga_3111.jpg/800px-Volga_3111.jpg',
    ];
    if (isset($staticMap[$make])) return $staticMap[$make];

    // imagin.studio make slugs (free public CDN)
    $makeMap = [
        'toyota'=>'toyota','honda'=>'honda','nissan'=>'nissan','lexus'=>'lexus',
        'mazda'=>'mazda','subaru'=>'subaru','mitsubishi'=>'mitsubishi','suzuki'=>'suzuki',
        'infiniti'=>'infiniti','acura'=>'acura',
        'bmw'=>'bmw','mercedes'=>'mercedes-benz','mercedes-benz'=>'mercedes-benz',
        'volkswagen'=>'volkswagen','vw'=>'volkswagen','audi'=>'audi','porsche'=>'porsche',
        'opel'=>'opel','seat'=>'seat','skoda'=>'skoda','volvo'=>'volvo',
        'renault'=>'renault','peugeot'=>'peugeot','citroen'=>'citroen',
        'fiat'=>'fiat','alfa romeo'=>'alfa-romeo','jaguar'=>'jaguar',
        'land rover'=>'land-rover','range rover'=>'land-rover',
        'hyundai'=>'hyundai','kia'=>'kia',
        'ford'=>'ford','chevrolet'=>'chevrolet','dodge'=>'dodge','jeep'=>'jeep',
        'chrysler'=>'chrysler','cadillac'=>'cadillac','lincoln'=>'lincoln',
        'tesla'=>'tesla',
    ];
    $makeSlug  = $makeMap[$make] ?? rawurlencode($make);
    $modelSlug = trim(preg_replace('/[^a-z0-9]+/', '-', $model), '-');

    return "https://cdn.imagin.studio/getimage?customer=de&make={$makeSlug}&modelFamily={$modelSlug}&zoomType=fullscreen&angle=side-34&width=900";
}

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
            <!-- Car photo banner -->
            <?php
            $carImgUrl = getCarImageUrl($result['make'] ?? '', $result['model'] ?? '', (int)($result['year'] ?? 0));
            ?>
            <div style="position:relative;height:260px;background:#1a1a2e;overflow:hidden;">
                <img id="carImg"
                     src="<?= sanitize($carImgUrl) ?>"
                     alt="<?= sanitize(($result['make'] ?? '') . ' ' . ($result['model'] ?? '')) ?>"
                     style="width:100%;height:100%;object-fit:cover;object-position:center;"
                     onerror="this.style.display='none';document.getElementById('carImgFallback').style.display='flex';">
                <!-- Fallback silhouette -->
                <div id="carImgFallback" style="display:none;position:absolute;inset:0;background:linear-gradient(135deg,#1a1a2e 0%,#2d3561 100%);align-items:center;justify-content:center;flex-direction:column;gap:12px;">
                    <svg width="180" height="80" viewBox="0 0 180 80" fill="none" xmlns="http://www.w3.org/2000/svg" opacity="0.25">
                        <path d="M12 54 L20 28 Q26 18 42 17 L80 14 Q100 13 116 17 L142 28 L168 54 L170 62 L10 62 Z" fill="white"/>
                        <circle cx="40" cy="64" r="12" fill="white"/>
                        <circle cx="140" cy="64" r="12" fill="white"/>
                        <rect x="26" y="42" width="128" height="18" rx="3" fill="#1a1a2e"/>
                        <path d="M46 17 L54 34 L126 34 L134 17" stroke="#1a1a2e" stroke-width="1.5" fill="none"/>
                    </svg>
                    <span style="color:rgba(255,255,255,0.35);font-size:0.85rem;letter-spacing:0.5px;">Фото недоступно</span>
                </div>
                <!-- Dark gradient overlay at bottom -->
                <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(10,10,30,0.88) 0%,rgba(10,10,30,0.15) 55%,transparent 100%);pointer-events:none;"></div>
                <!-- VIN badge top-right -->
                <div style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,0.55);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);border-radius:8px;padding:7px 14px;font-family:monospace;font-size:0.95rem;letter-spacing:2px;color:#fff;font-weight:700;border:1px solid rgba(255,255,255,0.12);">
                    <?= sanitize($vin) ?>
                </div>
                <!-- Source/cache badges top-left -->
                <div style="position:absolute;top:14px;left:16px;display:flex;gap:6px;">
                    <?php if (!empty($result['from_cache'])): ?>
                    <span style="background:rgba(0,0,0,0.5);backdrop-filter:blur(4px);padding:4px 10px;border-radius:20px;font-size:0.72rem;color:rgba(255,255,255,0.8);">
                        <i class="fa fa-database"></i> Кэш
                    </span>
                    <?php endif; ?>
                    <span style="background:rgba(211,47,47,0.75);backdrop-filter:blur(4px);padding:4px 10px;border-radius:20px;font-size:0.72rem;color:#fff;">
                        <i class="fa fa-plug"></i> <?= sanitize(strtoupper($result['source'] ?? 'local')) ?>
                    </span>
                </div>
                <!-- Car name overlay bottom -->
                <div style="position:absolute;bottom:18px;left:24px;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,0.5);">
                    <div style="font-size:2rem;font-weight:900;line-height:1.1;letter-spacing:-0.5px;">
                        <?= sanitize(($result['make'] ?? '') . ' ' . ($result['model'] ?? '')) ?>
                    </div>
                    <div style="font-size:0.95rem;opacity:0.75;margin-top:5px;">
                        <?= sanitize($result['year'] > 0 ? $result['year'] . ' г.' : '') ?>
                        <?= sanitize(!empty($result['country']) ? ' · ' . $result['country'] : '') ?>
                    </div>
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
