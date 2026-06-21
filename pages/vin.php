<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';
require_once dirname(__DIR__) . '/includes/catalog_api.php';

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
$filterCat      = (int)($_GET['cat'] ?? 0);
$result         = null;
$compatParts    = [];
$catalogEnabled = CatalogApi::enabled();
$facets         = [];
$error          = '';
$searchPerfomed = false;
$userHistory    = isLoggedIn() ? VinService::getUserHistory((int)$_SESSION['user_id'], 8) : [];

if ($vin) {
    $searchPerfomed = true;
    if (!VinService::validate($vin)) {
        $error = 'Неверный VIN-код. Проверьте: 17 символов, только A–Z и 0–9, без букв I, O, Q.';
    } else {
        $result = VinService::decode($vin);
        if (!empty($result['make'])) {
            $facets      = VinService::getCategoryFacets(
                $result['make'], $result['model'] ?? '', (int)($result['year'] ?? 0)
            );
            $compatParts = VinService::searchCompatibleParts(
                $result['make'],
                $result['model'] ?? '',
                (int)($result['year'] ?? 0),
                $filterCat ?: null
            );
            if (isLoggedIn()) {
                VinService::recordSearch((int)$_SESSION['user_id'], $vin, $result);
            }
        }
        // External catalog (PartsAPI) is loaded asynchronously via api/vin_catalog.php
        // because it scans many product groups and may take a few seconds.
    }
}

$totalCompat = array_sum(array_column($facets, 'cnt'));

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
        <?php if (!empty($facets) || !empty($compatParts)): ?>

        <!-- Category filter chips -->
        <?php if (!empty($facets)): ?>
        <div style="background:#fff;border-radius:10px;padding:14px 18px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,0.05);">
            <div style="font-size:0.72rem;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;">
                <i class="fa fa-filter" style="color:#d32f2f;"></i> Фильтр по категориям
            </div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php
                $chipBase = APP_URL . '/pages/vin.php?vin=' . urlencode($vin);
                $isAll    = $filterCat === 0;
                ?>
                <a href="<?= $chipBase ?>"
                   style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;font-size:0.85rem;font-weight:600;text-decoration:none;<?= $isAll ? 'background:#d32f2f;color:#fff;' : 'background:#f0f1f5;color:#1a1a2e;' ?>">
                    Все <span style="opacity:0.75;font-weight:400;">(<?= (int)$totalCompat ?>)</span>
                </a>
                <?php foreach ($facets as $f):
                    $active = $filterCat === (int)$f['id'];
                ?>
                <a href="<?= $chipBase ?>&cat=<?= (int)$f['id'] ?>"
                   style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;font-size:0.85rem;font-weight:600;text-decoration:none;<?= $active ? 'background:#d32f2f;color:#fff;' : 'background:#f0f1f5;color:#1a1a2e;' ?>">
                    <?= sanitize($f['name']) ?>
                    <span style="opacity:0.75;font-weight:400;">(<?= (int)$f['cnt'] ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:20px;color:#1a1a2e;">
            <i class="fa fa-cogs" style="color:#d32f2f;"></i>
            Совместимые запчасти
            <span style="color:#888;font-weight:400;font-size:0.9rem;">(<?= count($compatParts) ?>)</span>
        </h2>

        <?php if (empty($compatParts)): ?>
        <div style="background:#fff;border-radius:10px;padding:24px;text-align:center;color:#888;margin-bottom:32px;">
            В этой категории запчастей нет. <a href="<?= sanitize($chipBase) ?>" style="color:#d32f2f;">Показать все</a>
        </div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;margin-bottom:40px;">
            <?php foreach ($compatParts as $p):
                $imgs    = json_decode($p['images'] ?? '[]', true) ?: [];
                $thumb   = $imgs[0] ?? '';
                $st      = getStockStatus((int)$p['stock']);
                $inStock = (int)$p['stock'] > 0;
            ?>
            <div class="vin-part-card" data-part-id="<?= (int)$p['id'] ?>" style="background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.07);overflow:hidden;transition:box-shadow 0.2s;display:flex;flex-direction:column;">
                <a href="<?= partUrl($p) ?>">
                    <?php if ($thumb): ?>
                    <img src="<?= sanitize($thumb) ?>" alt="<?= sanitize($p['name']) ?>"
                         style="width:100%;height:160px;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:120px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#ddd;font-size:2rem;">
                        <i class="fa fa-image"></i>
                    </div>
                    <?php endif; ?>
                </a>
                <div style="padding:14px 16px;flex:1;display:flex;flex-direction:column;">
                    <div style="font-size:0.72rem;color:#aaa;margin-bottom:4px;">
                        <?= sanitize($p['brand_name'] ?? '') ?>
                        <?= sanitize(!empty($p['category_name']) ? ' · ' . $p['category_name'] : '') ?>
                    </div>
                    <a href="<?= partUrl($p) ?>"
                       style="font-weight:600;color:#1a1a2e;font-size:0.9rem;display:block;margin-bottom:4px;text-decoration:none;line-height:1.3;">
                        <?= sanitize(truncate($p['name'], 60)) ?>
                    </a>
                    <div style="font-size:0.7rem;color:#bbb;font-family:monospace;margin-bottom:8px;">
                        <?= sanitize($p['part_number']) ?>
                    </div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;">
                        <span style="font-size:1.1rem;font-weight:800;color:#d32f2f;">
                            <?= formatPrice($p['price']) ?>
                        </span>
                        <span class="badge badge-<?= $st['class'] ?>" style="font-size:0.7rem;">
                            <?= $st['label'] ?>
                        </span>
                    </div>
                    <div style="display:flex;gap:6px;margin-top:10px;">
                        <button type="button" onclick="vinAddToCart(<?= (int)$p['id'] ?>,this)"
                                <?= $inStock ? '' : 'disabled' ?>
                                style="flex:1;background:<?= $inStock ? '#d32f2f' : '#ccc' ?>;color:#fff;border:none;padding:9px 8px;border-radius:6px;font-size:0.82rem;font-weight:700;cursor:<?= $inStock ? 'pointer' : 'not-allowed' ?>;transition:background 0.15s;">
                            <i class="fa fa-shopping-cart"></i> В корзину
                        </button>
                        <button type="button" onclick="vinToggleAnalogs(<?= (int)$p['id'] ?>,this)"
                                title="Аналоги"
                                style="background:#f0f1f5;color:#1a1a2e;border:none;padding:9px 12px;border-radius:6px;font-size:0.82rem;font-weight:600;cursor:pointer;">
                            <i class="fa fa-exchange"></i>
                        </button>
                    </div>
                    <div class="vin-analogs" id="analogs-<?= (int)$p['id'] ?>" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed #eef0f3;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php else: ?>
        <!-- No compat parts yet — suggest catalog search -->
        <div style="background:#fff;border-radius:10px;padding:28px 24px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:32px;">
            <i class="fa fa-search" style="font-size:2.5rem;color:#ddd;display:block;margin-bottom:12px;"></i>
            <h3 style="color:#555;margin-bottom:8px;">Совместимые запчасти ещё не привязаны</h3>
            <p style="color:#888;margin-bottom:20px;">
                Но вы можете найти нужные детали, выполнив поиск по названию марки
                <?= sanitize(!empty($result['make']) ? '«' . $result['make'] . '»' : '') ?> в каталоге.
            </p>
            <a href="<?= APP_URL ?>/search/index.php?q=<?= urlencode($result['make'] ?? '') ?>"
               style="display:inline-block;background:#d32f2f;color:#fff;padding:10px 28px;border-radius:6px;font-weight:600;text-decoration:none;">
                <i class="fa fa-search"></i> Найти в каталоге
            </a>
        </div>
        <?php endif; ?>

        <!-- ── External catalog (PartsAPI), loaded async, shown only when enabled ── -->
        <?php if ($catalogEnabled):
            $oemNodes = CatalogApi::oemNodes();
            $catType  = trim(getSetting('catalog_api_type', 'oem'));
            $showTree = $catType === 'oem' && !empty($oemNodes);
        ?>
        <style>
            .vin-node-chip{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:22px;
                font-size:0.85rem;font-weight:600;border:1px solid #e3e5ea;background:#fff;color:#1a1a2e;
                cursor:pointer;transition:all 0.15s;}
            .vin-node-chip:hover{border-color:#d32f2f;color:#d32f2f;}
            .vin-node-chip.active{background:#d32f2f;border-color:#d32f2f;color:#fff;}
        </style>
        <div id="vinCatalog" data-vin="<?= sanitize($vin) ?>" data-type="<?= sanitize($catType) ?>" style="margin-top:8px;">
            <h2 style="font-size:1.3rem;font-weight:700;margin:8px 0 16px;color:#1a1a2e;">
                <i class="fa fa-book" style="color:#d32f2f;"></i>
                Оригинальный каталог по VIN
                <span id="vinCatalogCount" style="color:#888;font-weight:400;font-size:0.9rem;"></span>
            </h2>

            <?php if ($showTree): ?>
            <!-- Дерево узлов: клик по узлу → getPartsbyVIN(cat) одним запросом -->
            <div id="vinNodes" style="display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin-bottom:18px;">
                <span style="font-size:0.72rem;color:#888;text-transform:uppercase;letter-spacing:1px;margin-right:4px;">
                    <i class="fa fa-sitemap" style="color:#d32f2f;"></i> Узлы:
                </span>
                <?php foreach ($oemNodes as $n): ?>
                <button type="button" class="vin-node-chip" data-cat="<?= (int)$n['cat'] ?>"
                        onclick="vinLoadNode(<?= (int)$n['cat'] ?>, this)">
                    <?= sanitize($n['name']) ?>
                </button>
                <?php endforeach; ?>
                <button type="button" class="vin-node-chip" onclick="vinLoadAll(this)" title="Перебрать все группы (дольше, расходует лимит ключа)">
                    <i class="fa fa-th-large"></i> Все узлы
                </button>
            </div>
            <?php endif; ?>

            <div id="vinCatalogStatus" style="background:#fff;border-radius:10px;padding:22px 24px;text-align:center;color:#888;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:32px;">
                <i class="fa fa-spinner fa-spin" style="font-size:1.6rem;color:#d32f2f;"></i>
                <div style="margin-top:10px;font-size:0.9rem;">Подбираем запчасти по VIN из оригинального каталога…</div>
                <div style="margin-top:4px;font-size:0.78rem;color:#bbb;">Это может занять несколько секунд.</div>
            </div>
            <div id="vinCatalogBody" style="overflow-x:auto;"></div>
        </div>
        <?php endif; ?>

    </div><!-- /max-width -->
    <?php endif; // result ?>

    <?php else: ?>
    <!-- ── User VIN history (shown when no search yet) ──────────────── -->
    <?php if (!empty($userHistory)): ?>
    <div style="max-width:900px;margin:0 auto 32px;">
        <div style="background:#fff;border-radius:12px;padding:22px 24px;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <h3 style="font-size:1rem;font-weight:700;color:#1a1a2e;margin:0;">
                    <i class="fa fa-history" style="color:#d32f2f;"></i>
                    Ваша история поиска
                </h3>
                <span style="font-size:0.78rem;color:#aaa;"><?= count($userHistory) ?> запис<?= count($userHistory) === 1 ? 'ь' : 'ей' ?></span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;">
                <?php foreach ($userHistory as $h): ?>
                <a href="<?= APP_URL ?>/pages/vin.php?vin=<?= urlencode($h['vin']) ?>"
                   style="display:block;border:1px solid #eef0f3;border-radius:8px;padding:10px 14px;text-decoration:none;color:#1a1a2e;transition:border-color 0.15s,background 0.15s;"
                   onmouseover="this.style.borderColor='#d32f2f';this.style.background='#fff7f7'"
                   onmouseout="this.style.borderColor='#eef0f3';this.style.background='#fff'">
                    <div style="font-family:monospace;font-size:0.82rem;letter-spacing:1px;color:#888;margin-bottom:3px;">
                        <?= sanitize($h['vin']) ?>
                    </div>
                    <div style="font-weight:600;font-size:0.88rem;">
                        <?= sanitize(trim(($h['make'] ?? '') . ' ' . ($h['model'] ?? ''))) ?: '—' ?>
                        <?php if (!empty($h['year'])): ?>
                        <span style="color:#aaa;font-weight:400;"> · <?= (int)$h['year'] ?> г.</span>
                        <?php endif; ?>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

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

<meta name="csrf" content="<?= generateCsrfToken() ?>">
<script>
// Auto-uppercase and filter invalid chars
var __vinInp = document.getElementById('vinInput');
if (__vinInp) {
    __vinInp.addEventListener('input', function() {
        this.value = this.value.toUpperCase().replace(/[^A-HJ-NPR-Z0-9]/g, '');
    });
}

// ── Add to cart from VIN results ─────────────────────────────────────
function vinAddToCart(partId, btn) {
    if (btn.disabled) return;
    var csrf = document.querySelector('meta[name="csrf"]').getAttribute('content');
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Добавление…';

    var fd = new FormData();
    fd.append('action', 'add');
    fd.append('part_id', partId);
    fd.append('quantity', 1);
    fd.append('_csrf', csrf);

    fetch('<?= APP_URL ?>/api/cart.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.redirect) { window.location.href = d.redirect; return; }
            if (d.success) {
                btn.innerHTML = '<i class="fa fa-check"></i> В корзине';
                btn.style.background = '#4caf50';
                var cc = document.querySelector('.cart-count, [data-cart-count]');
                if (cc && d.cart_count != null) cc.textContent = d.cart_count;
                setTimeout(function(){ btn.innerHTML = orig; btn.disabled = false; btn.style.background = '#d32f2f'; }, 2000);
            } else {
                alert(d.message || 'Не удалось добавить в корзину');
                btn.innerHTML = orig; btn.disabled = false;
            }
        })
        .catch(function(){
            alert('Ошибка сети. Попробуйте ещё раз.');
            btn.innerHTML = orig; btn.disabled = false;
        });
}

// ── Toggle analog parts inline ───────────────────────────────────────
function vinToggleAnalogs(partId, btn) {
    var box = document.getElementById('analogs-' + partId);
    if (!box) return;
    if (box.style.display !== 'none') {
        box.style.display = 'none';
        btn.style.background = '#f0f1f5';
        return;
    }
    btn.style.background = '#ffe0e0';
    if (box.dataset.loaded === '1') { box.style.display = 'block'; return; }

    box.innerHTML = '<div style="text-align:center;color:#aaa;font-size:0.8rem;padding:8px;"><i class="fa fa-spinner fa-spin"></i> Поиск аналогов…</div>';
    box.style.display = 'block';

    fetch('<?= APP_URL ?>/api/vin_analogs.php?part_id=' + partId, { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            box.dataset.loaded = '1';
            if (!d.success || !d.items || d.items.length === 0) {
                box.innerHTML = '<div style="color:#aaa;font-size:0.78rem;padding:6px 0;">Аналоги не найдены</div>';
                return;
            }
            var html = '<div style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><i class="fa fa-exchange"></i> Аналоги (' + d.count + ')</div>';
            d.items.forEach(function(a){
                html += '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f5f6f8;">' +
                    '<a href="' + a.url + '" style="flex:1;text-decoration:none;color:#1a1a2e;font-size:0.8rem;line-height:1.25;">' +
                    '<div style="font-weight:600;">' + escapeHtml(a.name.length > 40 ? a.name.slice(0,40) + '…' : a.name) + '</div>' +
                    '<div style="font-size:0.68rem;color:#aaa;font-family:monospace;">' + escapeHtml(a.part_number) + ' · ' + escapeHtml(a.brand_name) + '</div>' +
                    '</a>' +
                    '<span style="font-size:0.82rem;font-weight:700;color:#d32f2f;white-space:nowrap;">' + a.price + '</span>' +
                    '<button onclick="vinAddToCart(' + a.id + ',this)" title="В корзину" style="background:#d32f2f;color:#fff;border:none;width:28px;height:28px;border-radius:5px;cursor:pointer;font-size:0.7rem;"><i class="fa fa-cart-plus"></i></button>' +
                    '</div>';
            });
            box.innerHTML = html;
        })
        .catch(function(){
            box.innerHTML = '<div style="color:#c00;font-size:0.78rem;">Ошибка загрузки аналогов</div>';
        });
}

function escapeHtml(s) {
    return String(s == null ? '' : s)
        .replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

// ── External catalog (PartsAPI): per-node loading + crosses bridge ────────
function jsAttr(s){ return String(s == null ? '' : s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,''); }

function vinSetActiveChip(btn){
    document.querySelectorAll('.vin-node-chip').forEach(function(c){ c.classList.remove('active'); });
    if (btn) btn.classList.add('active');
}

// Клик по узлу → один запрос getPartsbyVIN(cat). Бережёт лимит ключа.
function vinLoadNode(cat, btn){ vinSetActiveChip(btn); vinCatalogFetch('&cat=' + encodeURIComponent(cat)); }
// «Все узлы» → полный перебор групп (дольше, расходует лимит).
function vinLoadAll(btn){ vinSetActiveChip(btn); vinCatalogFetch(''); }

function vinCatalogFetch(extra){
    var box = document.getElementById('vinCatalog');
    if (!box) return;
    var vin = box.getAttribute('data-vin') || '';
    if (vin.length !== 17) return;

    var statusEl = document.getElementById('vinCatalogStatus');
    var bodyEl   = document.getElementById('vinCatalogBody');
    var countEl  = document.getElementById('vinCatalogCount');

    statusEl.style.display = 'block';
    statusEl.innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:1.6rem;color:#d32f2f;"></i>' +
        '<div style="margin-top:10px;font-size:0.9rem;">Загружаем запчасти из оригинального каталога…</div>';
    bodyEl.innerHTML = '';
    countEl.textContent = '';

    fetch('<?= APP_URL ?>/api/vin_catalog.php?vin=' + encodeURIComponent(vin) + extra, { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(vinRenderCatalog)
        .catch(function(){
            statusEl.innerHTML = '<div style="font-size:0.9rem;color:#c0392b;">Не удалось загрузить каталог. Попробуйте ещё раз.</div>';
        });
}

function vinRenderCatalog(d){
    var statusEl = document.getElementById('vinCatalogStatus');
    var bodyEl   = document.getElementById('vinCatalogBody');
    var countEl  = document.getElementById('vinCatalogCount');

    if (d.rate_limited) {
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<div style="font-size:0.9rem;color:#b8860b;">' +
            '<i class="fa fa-clock-o"></i> Каталог временно недоступен: превышен суточный лимит запросов. ' +
            'Попробуйте позже.</div>';
        return;
    }
    if (!d.success || !d.items || d.items.length === 0) {
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<div style="font-size:0.9rem;">В этом узле по данному VIN запчасти не найдены. ' +
            'Выберите другой узел или нажмите «Все узлы».</div>';
        return;
    }
    statusEl.style.display = 'none';
    countEl.textContent = '(' + d.count + ')';

    // Группировка: МАШИНА → УЗЕЛ(группа) → ДЕТАЛЬ → №
    var groups = {};
    d.items.forEach(function(it){
        var g = it.group || 'Прочее';
        (groups[g] = groups[g] || []).push(it);
    });

    var html = '', idx = 0;
    Object.keys(groups).forEach(function(g){
        html += '<div style="margin-bottom:22px;">';
        html += '<div style="font-size:0.8rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:0.5px;margin-bottom:8px;border-bottom:2px solid #f0f1f5;padding-bottom:6px;">' + escapeHtml(g) + '</div>';
        html += '<table style="width:100%;border-collapse:collapse;">';
        groups[g].forEach(function(it){
            idx++;
            var rid = 'vinrow-' + idx;
            html += '<tr id="' + rid + '" style="border-bottom:1px solid #f5f6f8;">';
            html += '<td style="padding:9px 8px;">' +
                      '<div style="font-weight:600;color:#1a1a2e;font-size:0.86rem;">' + escapeHtml(it.name) + '</div>' +
                      '<div style="font-size:0.72rem;color:#aaa;font-family:monospace;">' + escapeHtml(it.brand) + ' · ' + escapeHtml(it.part_number) + '</div>' +
                    '</td>';
            if (it.in_catalog) {
                html += '<td style="padding:9px 8px;text-align:right;white-space:nowrap;">' +
                          '<span style="font-weight:800;color:#d32f2f;">' + escapeHtml(it.price) + '</span>' +
                          (it.stock > 0
                            ? ' <span style="font-size:0.68rem;color:#4caf50;">в наличии</span>'
                            : ' <span style="font-size:0.68rem;color:#bbb;">под заказ</span>') +
                        '</td>';
            } else {
                html += '<td style="padding:9px 8px;text-align:right;white-space:nowrap;">' +
                          '<span style="font-size:0.78rem;color:#999;">под заказ</span>' +
                        '</td>';
            }
            // Действия: корзина / найти + кнопка «кроссы» (аналоги по № — МОСТИК)
            html += '<td style="padding:9px 8px;text-align:right;white-space:nowrap;">';
            if (it.in_catalog) {
                html += '<button type="button" onclick="vinAddToCart(' + it.part_id + ',this)" ' +
                          (it.stock > 0 ? '' : 'disabled ') +
                          'style="background:' + (it.stock>0?'#d32f2f':'#ccc') + ';color:#fff;border:none;padding:7px 12px;border-radius:6px;font-size:0.78rem;font-weight:700;cursor:' + (it.stock>0?'pointer':'not-allowed') + ';margin-right:6px;"><i class="fa fa-shopping-cart"></i></button>';
            } else {
                html += '<a href="<?= APP_URL ?>/search/index.php?q=' + encodeURIComponent(it.part_number) + '" ' +
                          'style="font-size:0.76rem;color:#d32f2f;font-weight:600;text-decoration:none;margin-right:8px;">найти <i class="fa fa-search"></i></a>';
            }
            html += '<button type="button" title="Аналоги по кроссам" ' +
                      'onclick="vinCrosses(\'' + jsAttr(it.part_number) + '\',\'' + jsAttr(it.brand) + '\',this,\'' + rid + '\')" ' +
                      'style="background:#f0f1f5;color:#1a1a2e;border:none;padding:7px 10px;border-radius:6px;font-size:0.78rem;cursor:pointer;"><i class="fa fa-exchange"></i></button>';
            html += '</td></tr>';
        });
        html += '</table></div>';
    });

    html += '<p style="text-align:center;color:#bbb;font-size:0.76rem;margin:6px 0 32px;">' +
            '<i class="fa fa-plug"></i> Оригинальный каталог TecDoc / PartsAPI' +
            (d.from_cache ? ' · из кэша' : '') + '</p>';
    bodyEl.innerHTML = html;
}

// МОСТИК: № детали → getCrosses → совпадение со складом (цена/наличие/в корзину).
function vinCrosses(article, brand, btn, rowId){
    var existing = document.getElementById('cross-' + rowId);
    if (existing){ existing.parentNode.removeChild(existing); btn.style.background = '#f0f1f5'; return; }
    var row = document.getElementById(rowId);
    if (!row) return;
    btn.style.background = '#ffe0e0';
    var colspan = row.children.length;
    row.insertAdjacentHTML('afterend',
        '<tr id="cross-' + rowId + '"><td colspan="' + colspan + '" style="background:#fafbfc;padding:10px 16px;">' +
        '<div style="color:#aaa;font-size:0.8rem;"><i class="fa fa-spinner fa-spin"></i> Поиск аналогов (кроссов)…</div></td></tr>');
    var cell = document.querySelector('#cross-' + rowId + ' td');

    fetch('<?= APP_URL ?>/api/vin_crosses.php?article=' + encodeURIComponent(article) + '&brand=' + encodeURIComponent(brand), { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.rate_limited){ cell.innerHTML = '<div style="color:#b8860b;font-size:0.8rem;">Превышен лимит запросов. Попробуйте позже.</div>'; return; }
            if (!d.success || !d.items || d.items.length === 0){ cell.innerHTML = '<div style="color:#aaa;font-size:0.8rem;">Аналоги (кроссы) не найдены.</div>'; return; }
            var html = '<div style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><i class="fa fa-exchange"></i> Аналоги по кроссам (' + d.count + ')</div>';
            d.items.forEach(function(it){
                html += '<div style="display:flex;align-items:center;gap:10px;padding:5px 0;border-bottom:1px solid #f0f1f5;">';
                html += '<div style="flex:1;font-size:0.8rem;"><span style="font-weight:600;">' + escapeHtml(it.brand || '') + '</span> ' +
                        '<span style="font-family:monospace;color:#666;">' + escapeHtml(it.part_number) + '</span>' +
                        (it.is_original ? ' <span style="font-size:0.66rem;color:#aaa;">(исходный)</span>' : '') + '</div>';
                if (it.in_catalog){
                    html += '<span style="font-weight:800;color:#d32f2f;white-space:nowrap;">' + escapeHtml(it.price) + '</span>';
                    html += it.stock > 0
                        ? '<span style="font-size:0.66rem;color:#4caf50;">в наличии</span>'
                        : '<span style="font-size:0.66rem;color:#bbb;">под заказ</span>';
                    html += '<button type="button" onclick="vinAddToCart(' + it.part_id + ',this)" ' + (it.stock>0?'':'disabled ') +
                            'style="background:' + (it.stock>0?'#d32f2f':'#ccc') + ';color:#fff;border:none;width:30px;height:30px;border-radius:5px;cursor:' + (it.stock>0?'pointer':'not-allowed') + ';"><i class="fa fa-shopping-cart"></i></button>';
                } else {
                    html += '<a href="<?= APP_URL ?>/search/index.php?q=' + encodeURIComponent(it.part_number) + '" ' +
                            'style="font-size:0.76rem;color:#d32f2f;font-weight:600;text-decoration:none;white-space:nowrap;">найти <i class="fa fa-search"></i></a>';
                }
                html += '</div>';
            });
            cell.innerHTML = html;
        })
        .catch(function(){ cell.innerHTML = '<div style="color:#c00;font-size:0.8rem;">Ошибка загрузки аналогов.</div>'; });
}

// Инициализация: авто-загрузка первого узла (1 запрос) или полный перебор для неоригинала.
(function(){
    var box = document.getElementById('vinCatalog');
    if (!box) return;
    var vin = box.getAttribute('data-vin') || '';
    if (vin.length !== 17) return;
    var firstChip = document.querySelector('.vin-node-chip[data-cat]');
    if (firstChip) { vinLoadNode(firstChip.getAttribute('data-cat'), firstChip); }
    else           { vinLoadAll(null); }
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
