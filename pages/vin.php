<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/includes/vin_service.php';
require_once dirname(__DIR__) . '/includes/catalog.php';

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

$pageTitle = t('vin_title') . ' — ' . getSetting('site_name');

$vin            = strtoupper(trim($_GET['vin'] ?? $_POST['vin'] ?? ''));
$filterCat      = (int)($_GET['cat'] ?? 0);
$result         = null;
$compatParts    = [];
$catalogEnabled = Catalog::provider()->enabled();
// Provider-specific: Parts-Catalogs даёт визуальные взрыв-схемы и car-specific узлы.
$pcProvider = (getSetting('catalog_provider', 'partsapi') === 'partspc');
$pcSchema   = $pcProvider && getSetting('catalog_pc_schema', '1') === '1';
$facets         = [];
$error          = '';
$searchPerfomed = false;
$userHistory    = isLoggedIn() ? VinService::getUserHistory((int)$_SESSION['user_id'], 8) : [];

if ($vin) {
    $searchPerfomed = true;
    if (!VinService::validate($vin)) {
        $error = t('vin_err');
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
        // ── Карточка авто: реальные данные из Parts-Catalogs (car/info). Тот же
        //    вызов уже идёт для дерева узлов, поэтому доп. запроса к PC (и лишнего
        //    кредита по VIN) НЕ уходит — оба читают из общего кэша car:lang:vin.
        if ($pcProvider && $catalogEnabled && $result !== null
                && method_exists(Catalog::provider(), 'vinCarInfo')) {
            $pcInfo = Catalog::provider()->vinCarInfo($vin);
            if (!empty($pcInfo)) {
                foreach (['make','model','year','engine','body_type',
                          'series','region','steering','transmission'] as $k) {
                    if (isset($pcInfo[$k]) && $pcInfo[$k] !== '' && $pcInfo[$k] !== 0) {
                        $result[$k] = $pcInfo[$k];
                    }
                }
                $result['source']      = 'parts-catalogs';
                $result['pc_enriched'] = true;
            }
        }
        // External catalog (PartsAPI) is loaded asynchronously via api/vin_catalog.php
        // because it scans many product groups and may take a few seconds.
    }
}

$totalCompat = array_sum(array_column($facets, 'cnt'));

// ── Режим «По параметрам»: авто выбрано в каскаде Parts-Catalogs (без VIN) ──
// Переиспользуем ту же ветку каталога (узлы → схемы → детали), что и для VIN.
$carId   = trim($_GET['carId'] ?? '');
$pcCatId = trim($_GET['catalogId'] ?? '');
$pcCrit  = trim($_GET['criteria'] ?? '');
$carName = trim($_GET['carName'] ?? '');
$carMode = (!$vin && $pcProvider && $catalogEnabled && $carId !== '' && $pcCatId !== '');
if ($carMode) {
    $searchPerfomed = true;
    $error  = '';
    $np     = preg_split('/\s+/', $carName, 2);
    $result = [                                   // минимальная «шапка авто»
        'make'   => ($np[0] ?? '') !== '' ? $np[0] : ($carName ?: 'Автомобиль'),
        'model'  => $np[1] ?? '',
        'year'   => 0,
        'source' => 'parts-catalogs',
    ];
    // Дотягиваем реальные атрибуты авто из cars2 (модель/модификация/кузов/регион/…).
    // Тот же вызов уже делает каскад — данные из кэша, лишних кредитов нет.
    $pcModelId = trim($_GET['modelId'] ?? '');
    $prov = Catalog::provider();
    if ($pcModelId !== '' && method_exists($prov, 'pcCarAttrs')) {
        $attrs = $prov->pcCarAttrs($pcCatId, $pcModelId, $carId);
        foreach (['make','model','series','engine','year','body_type','region','transmission'] as $k) {
            if (isset($attrs[$k]) && $attrs[$k] !== '' && $attrs[$k] !== 0) $result[$k] = $attrs[$k];
        }
    }
    // Страна: cars2 не отдаёт страну производителя → выводим по бренду.
    if (empty($result['country'])) {
        $brandCountry = [
            'mercedes'=>'Германия','mercedes-benz'=>'Германия','bmw'=>'Германия','audi'=>'Германия',
            'volkswagen'=>'Германия','vw'=>'Германия','porsche'=>'Германия','opel'=>'Германия','smart'=>'Германия',
            'toyota'=>'Япония','lexus'=>'Япония','honda'=>'Япония','acura'=>'Япония','nissan'=>'Япония','infiniti'=>'Япония',
            'mazda'=>'Япония','subaru'=>'Япония','mitsubishi'=>'Япония','suzuki'=>'Япония','daihatsu'=>'Япония',
            'hyundai'=>'Южная Корея','kia'=>'Южная Корея','daewoo'=>'Южная Корея','ssangyong'=>'Южная Корея',
            'ford'=>'США','chevrolet'=>'США','dodge'=>'США','jeep'=>'США','chrysler'=>'США','cadillac'=>'США','tesla'=>'США','gmc'=>'США',
            'renault'=>'Франция','peugeot'=>'Франция','citroen'=>'Франция','citroën'=>'Франция',
            'volvo'=>'Швеция','saab'=>'Швеция','skoda'=>'Чехия','škoda'=>'Чехия','seat'=>'Испания',
            'fiat'=>'Италия','alfa romeo'=>'Италия','lancia'=>'Италия','ferrari'=>'Италия','lamborghini'=>'Италия','maserati'=>'Италия',
            'jaguar'=>'Великобритания','land rover'=>'Великобритания','mini'=>'Великобритания','bentley'=>'Великобритания',
            'lada'=>'Россия','uaz'=>'Россия','gaz'=>'Россия','doosan'=>'Южная Корея',
        ];
        $mk = mb_strtolower(trim($result['make'] ?? ''));
        if (isset($brandCountry[$mk])) $result['country'] = $brandCountry[$mk];
    }
    $result['pc_enriched'] = true;
}

// ── Expert contacts (for help block + "by parameters" funnel) ──────────────
$exWa    = preg_replace('/\D/', '', getSetting('site_whatsapp', ''));
$exTg    = trim(getSetting('site_telegram', ''));
$exPhone = getSetting('site_phone', '+992 92 646-46-46');

// ── Curated makes → models for the "by parameters" quick funnel ────────────
// Real per-vehicle TecDoc matching activates on the paid PartsAPI key (see README);
// until then this funnel routes to catalog search / an expert request and никогда
// не выдаёт выдуманных данных.
$vinMakes = [
    'Toyota'        => ['Camry','Corolla','RAV4','Land Cruiser','Prado','Hilux'],
    'BMW'           => ['3 серия','5 серия','X5','X3','7 серия'],
    'Mercedes-Benz' => ['C-Class','E-Class','GLC','GLE','S-Class'],
    'Hyundai'       => ['Accent','Elantra','Sonata','Tucson','Santa Fe'],
    'Kia'           => ['Rio','Cerato','Sportage','Sorento','K5'],
    'Lada'          => ['Granta','Vesta','Niva','Largus'],
    'Nissan'        => ['Almera','Qashqai','X-Trail','Patrol'],
    'Chevrolet'     => ['Cobalt','Lacetti','Captiva','Malibu'],
    'Volkswagen'    => ['Polo','Passat','Tiguan','Touareg'],
    'Honda'         => ['Civic','Accord','CR-V'],
    'Opel'          => ['Astra','Insignia','Zafira'],
    'Ford'          => ['Focus','Mondeo','Kuga','Explorer'],
];

require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- ── VIN page skin: structure as the approved mockup, colours = our brand tokens ── -->
<style>
.vx{
    --vx-red:#C70909; --vx-red-d:#A00707; --vx-ink:#181a1f; --vx-ink2:#20232b;
    --vx-bg:#f4f5f7; --vx-card:#fff; --vx-line:#e4e7ec; --vx-muted:#6b7280;
    --vx-text:#1d2129; --vx-radius:14px; --vx-shadow:0 6px 24px rgba(17,20,30,.07);
    background:var(--vx-bg); color:var(--vx-text); padding:18px 0 56px;
}
.vx .container{max-width:min(1560px,94vw);}
.vx a{text-decoration:none;}

/* promo */
.vx-promo{margin:6px 0 4px;border-radius:var(--vx-radius);overflow:hidden;color:#fff;
    background:linear-gradient(100deg,#7a0608 0%,var(--vx-red) 48%,#e83b40 100%);
    display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 28px;box-shadow:var(--vx-shadow);}
.vx-promo h3{margin:0;font-size:1.5rem;font-weight:800;}
.vx-promo p{margin:4px 0 0;opacity:.92;font-size:.92rem;}
.vx-promo .badge{background:#fff;color:var(--vx-red);font-weight:800;padding:8px 16px;border-radius:30px;font-size:.92rem;white-space:nowrap;}
@media(max-width:640px){
    .vx-promo{flex-direction:column;align-items:flex-start;text-align:left;padding:20px 22px;}
    .vx-promo h3{font-size:1.28rem;}
    .vx-promo .badge{align-self:flex-start;}
}

/* hero */
.vx-hero{text-align:center;padding:26px 0 4px;}
.vx-hero h1{font-size:2rem;margin:0 0 8px;color:var(--vx-ink);font-weight:800;display:flex;gap:12px;align-items:center;justify-content:center;}
.vx-hero h1 svg{color:var(--vx-red);}
.vx-hero p{color:var(--vx-muted);max-width:640px;margin:0 auto;font-size:.98rem;}

/* search card */
.vx-scard{background:var(--vx-ink);color:#fff;border-radius:18px;max-width:1040px;margin:18px auto 6px;padding:24px 26px;box-shadow:0 18px 50px rgba(17,20,30,.25);}
.vx-scard h2{margin:0 0 4px;font-size:1.2rem;}
.vx-scard .hint{color:#9aa0ab;font-size:.83rem;margin-bottom:16px;}
.vx-scard .hint a{color:#ff6b6b;font-weight:600;cursor:pointer;}
.vx-tabs{display:inline-flex;background:var(--vx-ink2);border-radius:12px;padding:5px;margin-bottom:18px;}
.vx-tab{border:0;background:transparent;color:#c2c7d0;font-size:.9rem;font-weight:600;padding:9px 20px;border-radius:9px;cursor:pointer;transition:.15s;}
.vx-tab.active{background:var(--vx-red);color:#fff;}
.vx-panel{display:none;}.vx-panel.active{display:block;}
.vx-vinrow{display:flex;gap:12px;align-items:stretch;}
.vx-vinfield{flex:1;position:relative;}
.vx-vinfield input{width:100%;height:56px;border:2px solid transparent;border-radius:12px;padding:0 46px 0 18px;font-size:1.05rem;letter-spacing:2px;font-family:monospace;text-transform:uppercase;outline:none;}
.vx-vinfield input:focus{border-color:var(--vx-red);}
.vx-qmark{position:absolute;right:14px;top:50%;transform:translateY(-50%);width:22px;height:22px;border-radius:50%;background:#e5e7eb;color:#555;font-size:.8rem;font-weight:700;display:flex;align-items:center;justify-content:center;cursor:help;}
.vx-qmark .tip{visibility:hidden;opacity:0;position:absolute;right:-6px;top:30px;width:240px;background:#0b0d11;color:#e8eaee;font-size:.72rem;font-weight:400;letter-spacing:0;padding:10px 12px;border-radius:10px;box-shadow:var(--vx-shadow);transition:.15s;z-index:5;text-transform:none;line-height:1.45;}
.vx-qmark:hover .tip{visibility:visible;opacity:1;}
.vx-btn{border:0;background:var(--vx-red);color:#fff;font-weight:700;font-size:1rem;padding:0 32px;border-radius:12px;cursor:pointer;transition:.15s;white-space:nowrap;}
.vx-btn:hover{background:var(--vx-red-d);}
.vx-btn:disabled{background:#4b5563;cursor:not-allowed;opacity:.7;}
.vx-undr{display:flex;justify-content:space-between;margin-top:10px;font-size:.78rem;color:#9aa0ab;gap:10px;flex-wrap:wrap;}
.vx-undr a{color:#cdd2da;border-bottom:1px dashed #5b6270;cursor:pointer;}
.vx-counter{font-family:monospace;}
.vx-vinerr{color:#ff8a8a;font-size:.78rem;margin-top:8px;display:none;}
.vx-params{display:grid;grid-template-columns:1fr 1fr 1fr auto;gap:12px;}
.vx-params select{height:56px;border:0;border-radius:12px;padding:0 16px;font-size:.95rem;background:#fff;outline:none;cursor:pointer;}
.vx-params select:disabled{opacity:.5;cursor:not-allowed;}
@media(max-width:680px){.vx-params{grid-template-columns:1fr;}.vx-vinrow{flex-direction:column;}}
.vx-helpline{text-align:center;color:var(--vx-muted);font-size:.85rem;margin:14px auto 0;max-width:620px;}
.vx-helpline b{color:var(--vx-text);}

/* sections */
.vx-sec{margin:34px 0;}
.vx-sec h2.t{font-size:1.4rem;margin:0 0 16px;color:var(--vx-ink);}
.vx-sec h2.t span{color:var(--vx-muted);font-weight:500;font-size:.95rem;margin-left:8px;}

/* brands */
.vx-brands{display:grid;grid-template-columns:repeat(6,1fr);gap:12px;}
@media(max-width:900px){.vx-brands{grid-template-columns:repeat(3,1fr);}}
@media(max-width:520px){.vx-brands{grid-template-columns:repeat(2,1fr);}}
.vx-brand{background:var(--vx-card);border:1px solid var(--vx-line);border-radius:12px;height:70px;display:flex;align-items:center;justify-content:center;font-weight:700;font-size:.98rem;color:#374151;cursor:pointer;transition:.15s;text-align:center;padding:0 8px;}
.vx-brand:hover{border-color:var(--vx-red);color:var(--vx-red);box-shadow:var(--vx-shadow);transform:translateY(-2px);}

/* how */
.vx-how{display:grid;grid-template-columns:repeat(3,1fr);gap:16px;}
@media(max-width:820px){.vx-how{grid-template-columns:1fr;}}
.vx-hcard{background:var(--vx-card);border:1px solid var(--vx-line);border-radius:var(--vx-radius);padding:22px;position:relative;box-shadow:var(--vx-shadow);}
.vx-hcard .ic{width:52px;height:52px;border-radius:14px;background:rgba(199,9,9,.10);color:var(--vx-red);display:flex;align-items:center;justify-content:center;margin-bottom:14px;}
.vx-hcard h3{margin:0 0 6px;font-size:1.05rem;color:var(--vx-ink);}
.vx-hcard p{margin:0;color:var(--vx-muted);font-size:.9rem;line-height:1.55;}
.vx-hcard .num{position:absolute;top:16px;right:20px;font-size:1.9rem;font-weight:800;color:#eef0f3;}

/* expert */
.vx-expert{display:flex;align-items:center;gap:24px;background:linear-gradient(90deg,var(--vx-ink),#2a3038);color:#fff;border-radius:var(--vx-radius);padding:26px 30px;box-shadow:var(--vx-shadow);overflow:hidden;}
.vx-expert .tx{flex:1;}
.vx-expert h3{margin:0 0 6px;font-size:1.35rem;}
.vx-expert p{margin:0 0 16px;color:#b9bec8;font-size:.92rem;}
.vx-expert .cta{display:flex;gap:10px;flex-wrap:wrap;}
.vx-expert .cta a{padding:11px 20px;border-radius:10px;font-weight:600;font-size:.9rem;display:inline-flex;align-items:center;gap:8px;}
.vx-wa{background:#25d366;color:#063;}.vx-tg{background:#2aabee;color:#fff;}.vx-expert .call{background:rgba(255,255,255,.12);color:#fff;}
.vx-expert .ill{width:120px;height:120px;border-radius:50%;background:rgba(199,9,9,.18);display:flex;align-items:center;justify-content:center;color:#fff;flex-shrink:0;}
@media(max-width:640px){.vx-expert{flex-direction:column;text-align:center;}}

/* trust */
.vx-trust{display:grid;grid-template-columns:repeat(5,1fr);gap:14px;margin:30px 0 0;}
@media(max-width:820px){.vx-trust{grid-template-columns:repeat(2,1fr);}}
.vx-tr{display:flex;gap:12px;align-items:center;background:var(--vx-card);border:1px solid var(--vx-line);border-radius:12px;padding:14px;}
.vx-tr .ic{width:42px;height:42px;border-radius:10px;background:rgba(199,9,9,.10);color:var(--vx-red);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.vx-tr b{font-size:.88rem;display:block;color:var(--vx-ink);}.vx-tr span{font-size:.76rem;color:var(--vx-muted);}

/* skeleton + not-found + params result */
.vx-sk{background:var(--vx-card);border:1px solid var(--vx-line);border-radius:var(--vx-radius);overflow:hidden;}
.vx-sk .ph{height:130px;background:linear-gradient(90deg,#eee,#f5f5f5,#eee);background-size:200% 100%;animation:vxsh 1.2s infinite;}
.vx-sk .l{height:12px;margin:12px 14px;border-radius:6px;background:linear-gradient(90deg,#eee,#f5f5f5,#eee);background-size:200% 100%;animation:vxsh 1.2s infinite;}
.vx-sk .l.s{width:50%;}
@keyframes vxsh{0%{background-position:200% 0;}100%{background-position:-200% 0;}}
.vx-nf{text-align:center;background:var(--vx-card);border:1px solid var(--vx-line);border-radius:var(--vx-radius);padding:44px 20px;box-shadow:var(--vx-shadow);}
.vx-nf .ic{width:72px;height:72px;border-radius:50%;background:#f3f4f6;color:#9aa0ab;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;}
.vx-nf h3{margin:0 0 6px;font-size:1.25rem;color:var(--vx-ink);}
.vx-nf p{color:var(--vx-muted);margin:0 auto 18px;max-width:460px;}
.vx-nf .cta{display:flex;gap:10px;justify-content:center;flex-wrap:wrap;}
.vx-nf .cta a{padding:11px 20px;border-radius:10px;font-weight:600;font-size:.9rem;cursor:pointer;}
.vx-nf .cta .pr{background:var(--vx-red);color:#fff;}
.vx-nf .cta .gh{background:#fff;border:1px solid var(--vx-line);color:var(--vx-ink);}
</style>

<div class="vx">
<?= breadcrumb([
    ['label'=>t('home'),'url'=>APP_URL.'/index.php'],
    ['label'=>t('vin_title')],
]) ?>

<div class="container">

    <!-- PROMO -->
    <div class="vx-promo">
        <div>
            <h3><?= t('vin_promo_title') ?></h3>
            <p><?= t('vin_promo_text') ?></p>
        </div>
        <div class="badge"><?= t('free_delivery') ?></div>
    </div>

    <!-- HERO -->
    <div class="vx-hero">
        <h1>
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <?= t('vin_title') ?>
        </h1>
        <p><?= t('vin_subtitle') ?></p>
    </div>

    <!-- SEARCH CARD -->
    <div class="vx-scard">
        <h2><?= t('vin_search_title') ?></h2>
        <div class="hint"><?= t('vin_no_vin_hint') ?></div>

        <div class="vx-tabs">
            <button type="button" class="vx-tab active" id="vx-t-vin" onclick="vxTab('vin')"><?= t('vin_tab_vin') ?></button>
            <button type="button" class="vx-tab" id="vx-t-params" onclick="vxTab('params')"><?= t('vin_tab_params') ?></button>
        </div>

        <!-- VIN panel -->
        <form method="GET" action="" class="vx-panel active" id="vx-p-vin">
            <div class="vx-vinrow">
                <div class="vx-vinfield">
                    <input type="text" name="vin" id="vinInput" value="<?= sanitize($vin) ?>"
                           maxlength="17" autocomplete="off" placeholder="<?= sanitize(t('vin_placeholder')) ?>">
                    <div class="vx-qmark">?
                        <div class="tip"><?= t('vin_where') ?></div>
                    </div>
                </div>
                <button type="submit" class="vx-btn" id="vinBtn" <?= strlen($vin) === 17 ? '' : 'disabled' ?>>
                    <i class="fa fa-search"></i> <?= t('vin_find') ?>
                </button>
            </div>
            <div class="vx-undr">
                <span><?= t('vin_example') ?>: <a onclick="vxFillExample()">WBAWX31060PK42218</a></span>
                <span class="vx-counter" id="vinCnt"><?= strlen($vin) ?>/17</span>
            </div>
            <div class="vx-vinerr" id="vinErr"><?= t('vin_err') ?></div>
        </form>

        <!-- Params panel -->
        <div class="vx-panel" id="vx-p-params">
            <!-- Курируемый список (для провайдеров без каскада) -->
            <div class="vx-params">
                <select id="vxMk" onchange="vxOnMake()"><option value=""><?= t('vin_marka') ?></option></select>
                <select id="vxMd" onchange="vxOnModel()" disabled><option value=""><?= t('vin_model_lbl') ?></option></select>
                <select id="vxYr" disabled><option value=""><?= t('vin_year_lbl') ?></option></select>
                <button type="button" class="vx-btn" onclick="vxParamsSubmit()"><?= t('vin_pick') ?></button>
            </div>
            <!-- Живой каскад Parts-Catalogs (Марка → Модель → уточнения → авто) -->
            <div id="vxPcCascade" style="display:none;">
                <div id="vxPcSelects" class="vxpc-selects"></div>
                <div id="vxPcStatus" style="margin-top:10px;font-size:.84rem;color:#9aa0ab;"></div>
                <div id="vxPcCars" style="margin-top:14px;"></div>
            </div>
            <style>
            .vxpc-selects{display:flex;flex-wrap:wrap;gap:10px;}
            .vxpc-selects select{height:52px;border:0;border-radius:12px;padding:0 14px;font-size:.92rem;background:#fff;color:#1d2129;outline:none;cursor:pointer;min-width:150px;flex:1 1 150px;}
            .vx-carpick:hover{border-color:var(--vx-red,#C70909)!important;box-shadow:0 3px 12px rgba(199,9,9,.10);}
            </style>
        </div>
    </div>

    <div class="vx-helpline"><?= t('vin_no_vin_hint') ?> <b><?= sanitize($exPhone) ?></b></div>

    <!-- ── Params funnel result (client-side) ─────────────────────────── -->
    <div id="vxParamsResult" style="display:none;max-width:760px;margin:22px auto 0;">
        <div style="background:var(--vx-card);border:1px solid var(--vx-line);border-radius:var(--vx-radius);box-shadow:var(--vx-shadow);padding:24px 26px;text-align:center;">
            <div style="font-size:.78rem;color:var(--vx-muted);text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><?= t('vin_request') ?></div>
            <div id="vxParamsLabel" style="font-size:1.3rem;font-weight:800;color:var(--vx-ink);margin-bottom:18px;"></div>
            <div class="vx-nf cta" style="display:flex;gap:10px;justify-content:center;flex-wrap:wrap;background:none;border:0;box-shadow:none;padding:0;">
                <a class="pr" id="vxParamsSearch" style="background:var(--vx-red);color:#fff;padding:11px 22px;border-radius:10px;font-weight:600;"><i class="fa fa-search"></i> <?= t('vin_search_catalog') ?></a>
                <a class="gh" id="vxParamsExpert" target="_blank" rel="noopener" style="background:#fff;border:1px solid var(--vx-line);color:var(--vx-ink);padding:11px 22px;border-radius:10px;font-weight:600;"><i class="fa fa-user-circle-o"></i> <?= t('vin_pick_expert') ?></a>
            </div>
        </div>
    </div>

    <?php if ($searchPerfomed): ?>
    <!-- ── Loading skeleton (shown briefly via JS on submit) ──────────── -->
    <div id="vxSkeleton" style="display:none;max-width:900px;margin:30px auto 0;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px;">
            <div class="vx-sk"><div class="ph"></div><div class="l"></div><div class="l s"></div></div>
            <div class="vx-sk"><div class="ph"></div><div class="l"></div><div class="l s"></div></div>
            <div class="vx-sk"><div class="ph"></div><div class="l"></div><div class="l s"></div></div>
            <div class="vx-sk"><div class="ph"></div><div class="l"></div><div class="l s"></div></div>
        </div>
    </div>

    <div id="vxResults">
    <?php if ($error): ?>
    <!-- Error -->
    <div style="max-width:760px;margin:30px auto 0;">
        <div style="background:#fff3cd;border:1px solid #ffc107;border-radius:10px;padding:20px 24px;color:#856404;">
            <i class="fa fa-exclamation-triangle" style="margin-right:8px;"></i>
            <?= sanitize($error) ?>
        </div>
    </div>

    <?php elseif ($result && !empty($result['make'])): ?>
    <!-- ── Decoded car info ──────────────────────────────────────────── -->
    <div style="max-width:100%;margin:30px auto 0;">

        <?php
        $carTitle = trim(($result['make'] ?? '') . ' ' . ($result['model'] ?? ''));
        $carSub   = trim(
            (($result['year'] ?? 0) > 0 ? $result['year'] . ' г.' : '')
            . (!empty($result['country']) ? ((($result['year'] ?? 0) > 0) ? ' · ' : '') . $result['country'] : '')
        );
        $fields = [
            'make'         => ['Марка',       'fa-car'],
            'model'        => ['Модель',      'fa-tag'],
            'series'       => ['Поколение',   'fa-code-fork'],
            'year'         => ['Год выпуска', 'fa-calendar'],
            'body_type'    => ['Тип кузова',  'fa-cube'],
            'engine'       => ['Двигатель',   'fa-cog'],
            'transmission' => ['Коробка передач','fa-cogs'],
            'fuel_type'    => ['Топливо',     'fa-tint'],
            'drive_type'   => ['Привод',      'fa-road'],
            'steering'     => ['Рулевое управление','fa-dot-circle-o'],
            'country'      => ['Страна',      'fa-globe'],
            'region'       => ['Регион',      'fa-map-o'],
            'manufacturer' => ['Производитель','fa-industry'],
            'plant_country'=> ['Завод',       'fa-map-marker'],
        ];
        ?>
        <style>
            .vin-carcard{display:grid;grid-template-columns:minmax(240px,.58fr) minmax(0,1.42fr);border-radius:16px;overflow:hidden;box-shadow:var(--vx-shadow);border:1px solid #e7e9ee;margin-bottom:28px;background:#fff;}
            @media(max-width:760px){ .vin-carcard{grid-template-columns:1fr;} }
            .vin-cc-l{position:relative;background:radial-gradient(120% 120% at 20% 18%,#2a2f3a,#14161b 72%);color:#fff;padding:20px;display:flex;flex-direction:column;justify-content:space-between;gap:16px;min-height:170px;overflow:hidden;}
            /* Бренд-вотермарк («лого»-эффект) + компактный чертёж авто фоном */
            .vin-cc-brand{position:absolute;right:18px;top:46px;z-index:0;font-size:2.6rem;font-weight:900;letter-spacing:.02em;text-transform:uppercase;line-height:1;pointer-events:none;white-space:nowrap;
                color:rgba(255,255,255,.1);text-shadow:0 0 22px rgba(255,255,255,0);
                animation:vinBrandGlow 4.5s ease-in-out infinite;}
            @keyframes vinBrandGlow{
                0%,100%{ color:rgba(255,255,255,.09); text-shadow:0 0 16px rgba(199,9,9,0); }
                50%{ color:rgba(255,255,255,.22); text-shadow:0 0 30px rgba(199,9,9,.45),0 0 6px rgba(255,255,255,.25); }
            }
            @media (prefers-reduced-motion:reduce){ .vin-cc-brand{ animation:none; color:rgba(255,255,255,.14); } }
            .vin-cc-car{position:absolute;right:-4%;bottom:6%;width:56%;max-width:300px;color:rgba(255,255,255,.11);z-index:0;pointer-events:none;}
            .vin-cc-badges{position:relative;z-index:2;display:flex;gap:8px;flex-wrap:wrap;align-items:flex-start;}
            .vin-cc-badges span{display:inline-flex;align-items:center;gap:5px;padding:6px 13px;border-radius:20px;font-size:.66rem;color:#fff;white-space:nowrap;line-height:1.4;}
            .vin-cc-title{position:relative;z-index:2;}
            .vin-cc-title .m{font-size:1.55rem;font-weight:900;line-height:1.06;text-shadow:0 2px 8px rgba(0,0,0,.4);}
            .vin-cc-title .s{font-size:.86rem;opacity:.75;margin-top:6px;}
            /* Характеристики — в две колонки (карточка ниже и компактнее) */
            .vin-cc-r{background:#fff;display:grid;grid-template-columns:1fr 1fr;align-content:center;}
            @media(max-width:760px){ .vin-cc-r{grid-template-columns:1fr;} }
            .vin-cc-row{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 20px;border-bottom:1px solid #edeff2;min-width:0;}
            .vin-cc-row:nth-child(odd){border-right:1px solid #edeff2;}
            @media(max-width:760px){ .vin-cc-row:nth-child(odd){border-right:0;} }
            .vin-cc-k{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#6b7480;font-weight:700;white-space:nowrap;}
            .vin-cc-k i{margin-right:7px;color:#aab0b8;}
            .vin-cc-v{font-weight:800;color:#14161b;font-size:.96rem;text-align:right;}
        </style>
        <div class="vin-carcard">
            <!-- Левая тёмная панель: источник/VIN + чертёж авто + марка/модель -->
            <div class="vin-cc-l">
                <?php if (!empty($result['make'])): ?><div class="vin-cc-brand"><?= sanitize($result['make']) ?></div><?php endif; ?>
                <div class="vin-cc-badges">
                    <?php if (!empty($result['from_cache'])): ?><span style="background:rgba(0,0,0,.4);"><i class="fa fa-database"></i> Кэш</span><?php endif; ?>
                    <span style="background:var(--vx-red,#C70909);font-weight:800;letter-spacing:.05em;text-transform:uppercase;font-size:.62rem;"><i class="fa fa-plug"></i> <?= sanitize(strtoupper($result['source'] ?? 'local')) ?></span>
                    <?php if ($vin !== ''): ?><span style="background:rgba(0,0,0,.45);font-family:monospace;letter-spacing:1px;border:1px solid rgba(255,255,255,.12);"><?= sanitize($vin) ?></span><?php endif; ?>
                </div>
                <svg class="vin-cc-car" viewBox="0 0 460 175" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M20 118 Q30 92 70 88 L120 84 Q150 50 220 48 Q300 46 330 84 L400 96 Q430 100 440 118"/>
                    <path d="M120 84 Q150 58 208 57 L212 84 Z"/>
                    <path d="M222 57 Q286 57 318 84 L226 84 Z"/>
                    <path d="M20 118 L440 118 L432 138 Q430 144 422 144 L360 144"/>
                    <path d="M110 144 L54 144 Q30 144 24 130 L20 118"/>
                    <circle cx="132" cy="146" r="26"/><circle cx="132" cy="146" r="11"/>
                    <circle cx="338" cy="146" r="26"/><circle cx="338" cy="146" r="11"/>
                    <line x1="158" y1="118" x2="312" y2="118"/>
                </svg>
                <div class="vin-cc-title">
                    <div class="m"><?= sanitize($carTitle !== '' ? $carTitle : 'Автомобиль') ?></div>
                    <?php if ($carSub !== ''): ?><div class="s"><?= sanitize($carSub) ?></div><?php endif; ?>
                </div>
            </div>
            <!-- Правая колонка: характеристики (только непустые, модель — в заголовке) -->
            <div class="vin-cc-r">
                <?php
                $rowsShown = 0;
                foreach ($fields as $key => [$label, $icon]):
                    if ($key === 'model') continue;              // модель уже в заголовке слева
                    $val = $result[$key] ?? '';
                    if (!$val || $val === '0') continue;
                    $rowsShown++;
                ?>
                <div class="vin-cc-row">
                    <span class="vin-cc-k"><i class="fa <?= $icon ?>"></i><?= $label ?></span>
                    <span class="vin-cc-v"><?= sanitize((string)$val) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if ($rowsShown === 0): ?>
                <div class="vin-cc-row"><span class="vin-cc-k">Данные</span><span class="vin-cc-v" style="color:#98a0ab;">уточняются</span></div>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /car-info max-width:900px -->

    <!-- ── External catalog: на всю ширину контейнера (не в узкой обёртке) ─── -->
    <?php if ($catalogEnabled):
            $oemNodes = Catalog::provider()->oemNodes();
            $catType  = trim(getSetting('catalog_api_type', 'oem'));
            // Для Parts-Catalogs дерево строится по VIN на клиенте (api/vin_nodes.php),
            // поэтому каркас показываем даже при пустом справочнике узлов.
            $showTree = !empty($oemNodes) || $pcProvider;
        ?>
        <style>
            /* ── Каталог 7zap-стиль: классификатор слева + контент-панель справа ── */
            .vin-cat7{display:flex;gap:18px;align-items:flex-start;margin-bottom:26px;scroll-margin-top:90px;}
            .vin-cat7-side{flex:0 0 260px;background:#fff;border:1px solid #e7e9ee;border-radius:16px;padding:18px 14px;position:sticky;top:90px;max-height:calc(100vh - 120px);overflow-y:auto;box-shadow:0 12px 30px rgba(20,22,27,.06);}
            .vin-cat7-main{flex:1;min-width:0;}
            @media (max-width: 767px){
                .vin-cat7{flex-direction:column;}
                .vin-cat7-side{position:static;flex:none;width:100%;max-height:260px;
                    background:linear-gradient(#fff,#fff),linear-gradient(#fff,#fff),
                        linear-gradient(to bottom,rgba(20,22,27,.09),rgba(20,22,27,0)),
                        linear-gradient(to top,rgba(20,22,27,.09),rgba(20,22,27,0));
                    background-repeat:no-repeat;background-color:#fff;
                    background-size:100% 22px,100% 22px,100% 22px,100% 22px;
                    background-position:top,bottom,top,bottom;background-attachment:local,local,scroll,scroll;}
            }
            .vin-tree-pill{display:inline-flex;align-items:center;gap:7px;background:linear-gradient(100deg,var(--vx-red,#C70909),#e8393e);color:#fff;font-size:0.74rem;font-weight:800;padding:7px 16px;border-radius:20px;margin-bottom:14px;text-transform:uppercase;letter-spacing:.6px;box-shadow:0 6px 16px rgba(199,9,9,.28);}
            .vin-cat7-side ul{list-style:none;margin:0;padding:0;display:flex;flex-direction:column;gap:2px;}
            .vin-cat7-side li{position:relative;padding:10px 12px 10px 16px;font-size:0.85rem;color:#4a5361;font-weight:600;cursor:pointer;border-radius:9px;border-left:3px solid transparent;line-height:1.32;transition:.15s;}
            .vin-cat7-side li:hover{color:var(--vx-red,#C70909);background:#fbf3f3;}
            .vin-cat7-side li.active{color:var(--vx-red,#C70909);background:#fff1f1;border-left-color:var(--vx-red,#C70909);font-weight:800;}
            /* Сетка карточек-узлов с миниатюрами схем (как у 7zap) */
            .vin-node-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(185px,1fr));gap:14px;}
            .vin-node-card{background:#fff;border:1px solid #e7e9ee;border-radius:12px;padding:0 0 12px;font-size:0.84rem;font-weight:700;color:#1d2129;cursor:pointer;transition:.15s;display:flex;flex-direction:column;overflow:hidden;text-align:center;}
            .vin-node-card:hover{border-color:var(--vx-red,#C70909);box-shadow:0 4px 14px rgba(199,9,9,.10);transform:translateY(-1px);}
            .vin-node-card.active{border-color:var(--vx-red,#C70909);background:#fff5f5;color:var(--vx-red,#C70909);}
            .vin-node-thumb{display:flex;align-items:center;justify-content:center;height:118px;background:#fbfbfc;border-bottom:1px solid #f0f1f5;margin-bottom:10px;}
            .vin-node-thumb img{max-width:92%;max-height:106px;object-fit:contain;}
            .vin-node-thumb svg{color:#c9cdd5;}
            .vin-node-name{padding:0 10px;line-height:1.25;}
            .vin-node-card.all{flex-direction:row;align-items:center;justify-content:center;gap:8px;color:#fff;background:var(--vx-ink,#181a1f);border-color:var(--vx-ink,#181a1f);min-height:60px;padding:12px;}
            .vin-node-card.all:hover{filter:brightness(1.15);color:#fff;}
            /* Вид узла (схема+детали) — заменяет сетку НА МЕСТЕ, без прокрутки вниз */
            .vin-nv-head{display:flex;align-items:center;gap:12px;margin-bottom:14px;flex-wrap:wrap;}
            .vin-nv-back{background:#fff;border:1px solid #e7e9ee;border-radius:9px;padding:8px 14px;font-size:0.82rem;font-weight:700;color:#1d2129;cursor:pointer;transition:.15s;}
            .vin-nv-back:hover{border-color:var(--vx-red,#C70909);color:var(--vx-red,#C70909);}
            .vin-nv-title{margin:0;font-size:1.05rem;font-weight:800;color:var(--vx-ink,#181a1f);}
            /* ── Карточки деталей (фото + крупная цена + «В корзину», как на макете) ── */
            .vin-grp{font-size:0.8rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin:0 0 12px;border-bottom:2px solid #f0f1f5;padding-bottom:6px;}
            .vin-pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px;}
            .vin-pcard{background:#fff;border:1px solid #e7e9ee;border-radius:14px;padding:16px;display:flex;flex-direction:column;}
            .vin-pcard-t{font-weight:700;color:#1d2129;font-size:0.9rem;line-height:1.3;}
            .vin-pcard-t span{display:block;font-weight:400;font-size:0.72rem;color:#9aa3af;font-family:monospace;margin-top:3px;}
            .vin-pcard-b{display:flex;gap:12px;margin-top:12px;align-items:stretch;}
            .vin-pcard-ph{flex:0 0 84px;background:#eef0f3;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#b6bcc6;font-size:0.72rem;min-height:84px;}
            .vin-pos-box{flex:0 0 58px;min-height:58px;font-size:1.05rem;font-weight:800;color:#39414d;background:#f1f3f6;cursor:pointer;transition:.12s;}
            .vin-pos-box:hover{background:#fff;color:var(--vx-red,#C70909);box-shadow:inset 0 0 0 2px var(--vx-red,#C70909);}
            /* «Фото» = вырезка из схемы (зум-фрагмент вокруг детали). Фон ставит vinApplyCrops. */
            .vin-crop{flex:0 0 84px;min-height:84px;position:relative;background-color:#fff;border-radius:10px;overflow:hidden;cursor:zoom-in;box-shadow:inset 0 0 0 1px #e7e9ee;transition:.12s;}
            .vin-crop:hover{box-shadow:inset 0 0 0 2px var(--vx-red,#C70909);}
            .vin-crop .vin-crop-n{position:absolute;left:5px;bottom:4px;background:var(--vx-red,#C70909);color:#fff;font-size:0.62rem;font-weight:800;padding:1px 7px;border-radius:20px;}
            .vin-crop .vin-crop-z{position:absolute;right:4px;top:4px;width:19px;height:19px;border-radius:6px;background:rgba(0,0,0,.5);color:#fff;font-size:0.6rem;display:flex;align-items:center;justify-content:center;}
            .vin-pcard-buy{flex:1;display:flex;flex-direction:column;gap:8px;}
            .vin-price{background:var(--vx-red,#C70909);color:#fff;font-weight:800;font-size:1.1rem;text-align:center;border-radius:10px;padding:14px 8px;line-height:1;}
            .vin-price.ph{background:#f3f4f6;color:#9aa3af;font-size:0.85rem;font-weight:600;padding:16px 8px;}
            .vin-stock{font-size:0.72rem;color:#9aa3af;text-align:center;}
            .vin-stock.ok{color:#2e9e44;}
            .vin-cart{display:block;width:100%;background:var(--vx-ink,#181a1f);color:#fff;border:none;border-radius:9px;padding:11px;font-size:0.82rem;font-weight:700;cursor:pointer;text-align:center;text-decoration:none;transition:.15s;}
            .vin-cart:hover{filter:brightness(1.2);color:#fff;}
            .vin-cart[disabled]{background:#ccc;cursor:not-allowed;}
            .vin-cart.ghost{background:#fff;color:var(--vx-red,#C70909);border:1px solid #e7e9ee;}
            .vin-cart.ghost:hover{border-color:var(--vx-red,#C70909);filter:none;}
            .vin-cart.sm{width:auto;padding:7px 12px;font-size:0.75rem;}
            .vin-cross-btn{align-self:center;background:none;border:none;color:#9aa3af;font-size:0.74rem;cursor:pointer;padding:2px 6px;}
            .vin-cross-btn:hover,.vin-cross-btn.on{color:var(--vx-red,#C70909);}
            .vin-cross-box{}
            .vin-cross-h{font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:1px;margin:10px 0 4px;}
            .vin-cross-row{display:flex;align-items:center;gap:8px;justify-content:space-between;padding:5px 0;border-top:1px dashed #eef0f3;font-size:0.8rem;}
            .vin-cross-row .ci span{font-family:monospace;color:#666;}
            .vin-cross-row .ci em{font-size:0.66rem;color:#aaa;font-style:normal;}
            .vin-cross-row .cp{font-weight:800;color:var(--vx-red,#C70909);white-space:nowrap;}
            .vin-cross-row .cu{font-size:0.7rem;color:#bbb;}
            .vin-cross-row .cl{color:var(--vx-red,#C70909);font-weight:600;white-space:nowrap;}
            /* ── Визуальная взрыв-схема (Parts-Catalogs): картинка + кликабельные хотспоты ── */
            .vin-scheme-wrap{margin-bottom:22px;scroll-margin-top:90px;}
            #vinCatalogStatus,#vinCatalogBody{scroll-margin-top:90px;}
            /* Раскладка узла: схема слева (залипает и остаётся на виду) + детали
               справа (скроллятся). Двухколоночный режим включается классом has-scheme,
               который ставится только когда у узла есть картинка схемы. */
            .vin-nv-split{display:block;}
            .vin-nv-split.has-scheme{display:flex;gap:18px;align-items:flex-start;}
            .vin-nv-split.has-scheme .vin-nv-scheme{flex:0 0 clamp(320px,44%,600px);position:sticky;top:90px;max-height:calc(100vh - 100px);}
            .vin-nv-split.has-scheme .vin-scheme-wrap{margin-bottom:0;}
            .vin-nv-split.has-scheme #vinCatalogBody{flex:1;min-width:0;}
            @media (max-width:820px){
                .vin-nv-split.has-scheme{flex-direction:column;}
                .vin-nv-split.has-scheme .vin-nv-scheme{flex:none;width:100%;position:sticky;top:0;z-index:6;background:#fff;padding-bottom:8px;margin-bottom:6px;}
                .vin-nv-split.has-scheme .vin-scheme-box img{max-height:42vh;}
            }
            .vin-scheme-cap{font-size:0.82rem;color:#888;margin-bottom:8px;text-align:center;}
            .vin-scheme-box{position:relative;width:100%;max-width:100%;margin:0 auto;background:#fff;border:1px solid #e7e9ee;border-radius:12px;overflow:visible;}
            .vin-scheme-box img{width:100%;max-height:70vh;object-fit:contain;display:block;background:#fff;border-radius:12px;}
            .vin-hot{position:absolute;border:2px solid transparent;border-radius:4px;cursor:pointer;transition:.12s;box-sizing:border-box;}
            .vin-hot:hover{border-color:var(--vx-red,#C70909);background:rgba(199,9,9,.16);}
            /* Заметная подсветка выноски: яркая заливка + свечение + пульсация,
               чтобы деталь явно загоралась даже на мелком/плотном участке схемы. */
            .vin-hot.hot{border-color:var(--vx-red,#C70909);background:rgba(199,9,9,.30);z-index:5;
                box-shadow:0 0 0 3px rgba(199,9,9,.40), 0 0 14px 4px rgba(199,9,9,.55);
                animation:vinHotPulse .95s ease-in-out infinite;}
            .vin-hot.hot::after{content:'';position:absolute;left:50%;top:50%;width:9px;height:9px;
                transform:translate(-50%,-50%);border-radius:50%;background:var(--vx-red,#C70909);
                box-shadow:0 0 0 3px #fff, 0 0 8px rgba(199,9,9,.9);pointer-events:none;}
            @keyframes vinHotPulse{
                0%,100%{box-shadow:0 0 0 3px rgba(199,9,9,.40), 0 0 10px 2px rgba(199,9,9,.45);}
                50%{box-shadow:0 0 0 6px rgba(199,9,9,.20), 0 0 20px 7px rgba(199,9,9,.70);}
            }
            @media (prefers-reduced-motion:reduce){ .vin-hot.hot{animation:none;} }
            .vin-pcard.hot{border-color:var(--vx-red,#C70909);box-shadow:0 0 0 2px rgba(199,9,9,.18);}
            .vin-pos-badge{display:inline-block;min-width:18px;text-align:center;background:#eef0f3;color:#39414d;border-radius:5px;font-size:0.66rem;padding:1px 5px;margin-right:5px;}
            /* ── Тултип на схеме (имя детали при наведении на выноску ИЛИ карточку) ── */
            .vin-tip{position:absolute;z-index:30;transform:translate(-50%,calc(-100% - 9px));background:#16171c;color:#fff;font-size:0.72rem;line-height:1.35;padding:7px 10px;border-radius:8px;max-width:240px;pointer-events:none;box-shadow:0 6px 18px rgba(0,0,0,.38);display:none;}
            .vin-tip b{color:#ffcf6b;}
            .vin-tip .vt-m{display:block;font-family:monospace;color:#c7ccd3;margin-top:2px;}
            .vin-tip .vt-more{display:block;color:#9aa3af;margin-top:3px;font-size:0.68rem;}
            .vin-tip:after{content:'';position:absolute;left:50%;bottom:-6px;transform:translateX(-50%);border:6px solid transparent;border-top-color:#16171c;}
            /* Верхнее положение — когда у детали нет точки на схеме (без стрелки, без сдвига вверх). */
            .vin-tip.at-top{transform:translate(-50%,0);}
            .vin-tip.at-top:after{display:none;}
            /* ── Кнопка «увеличить схему» ── */
            .vin-zoom-btn{position:absolute;top:8px;right:8px;z-index:6;width:34px;height:34px;border:0;border-radius:8px;background:rgba(0,0,0,.55);color:#fff;cursor:pointer;font-size:0.9rem;display:flex;align-items:center;justify-content:center;}
            .vin-zoom-btn:hover{background:var(--vx-red,#C70909);}
            /* ── Лайтбокс (схема на весь экран, зум + перетаскивание) ── */
            .vin-lb{position:fixed;inset:0;z-index:9999;background:rgba(8,9,13,.93);display:flex;flex-direction:column;}
            .vin-lb-bar{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:10px 16px;color:#fff;}
            .vin-lb-cap{font-size:0.85rem;color:#cfd3da;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
            .vin-lb-btns{display:flex;gap:8px;flex:0 0 auto;}
            .vin-lb-btns button{width:38px;height:38px;border:0;border-radius:9px;background:rgba(255,255,255,.14);color:#fff;font-size:1.15rem;font-weight:700;cursor:pointer;line-height:1;}
            .vin-lb-btns button:hover{background:var(--vx-red,#C70909);}
            .vin-lb-stage{flex:1;overflow:hidden;display:flex;align-items:center;justify-content:center;cursor:grab;touch-action:none;}
            .vin-lb-stage.drag{cursor:grabbing;}
            .vin-lb-inner{position:relative;transform-origin:center center;will-change:transform;}
            .vin-lb-inner img{display:block;max-width:94vw;max-height:82vh;user-select:none;-webkit-user-drag:none;border-radius:6px;background:#fff;}
        </style>
        <div id="vinCatalog" data-vin="<?= sanitize($vin) ?>" data-type="<?= sanitize($catType) ?>"
             data-car-id="<?= sanitize($carId) ?>" data-catalog-id="<?= sanitize($pcCatId) ?>"
             data-criteria="<?= sanitize($pcCrit) ?>" data-brand="<?= sanitize($result['make'] ?? '') ?>" style="margin-top:8px;">
            <h2 style="font-size:1.3rem;font-weight:700;margin:8px 0 16px;color:var(--vx-ink);">
                <i class="fa fa-book" style="color:var(--vx-red);"></i> Оригинальный каталог по VIN
                <span id="vinCatalogCount" style="color:#888;font-weight:400;font-size:0.9rem;"></span>
            </h2>
            <div class="vin-cat7">
                <?php if ($showTree): ?>
                <aside class="vin-cat7-side">
                    <span class="vin-tree-pill"><i class="fa fa-sitemap"></i> Узлы</span>
                    <ul id="vinNodeList">
                        <?php foreach ($oemNodes as $n): ?>
                        <li data-cat="<?= sanitize((string)$n['cat']) ?>" onclick="vinPickNode('<?= sanitize((string)$n['cat']) ?>')"><?= sanitize($n['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
                <?php endif; ?>
                <div class="vin-cat7-main">
                    <!-- Состояние A: сетка карточек-узлов (миниатюры схем — как у 7zap) -->
                    <div id="vinNodeGrid" class="vin-node-grid" <?= $showTree ? '' : 'style="display:none;"' ?>>
                        <?php foreach ($oemNodes as $n): ?>
                        <button type="button" class="vin-node-card" data-cat="<?= sanitize((string)$n['cat']) ?>" data-name="<?= sanitize($n['name']) ?>" onclick="vinLoadNode('<?= sanitize((string)$n['cat']) ?>', this)">
                            <span class="vin-node-thumb"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10.3 3.7a2 2 0 0 1 3.4 0l1 1.7 2-.2a2 2 0 0 1 1.7 3l-1 1.7 1 1.7a2 2 0 0 1-1.7 3l-2-.2-1 1.7a2 2 0 0 1-3.4 0l-1-1.7-2 .2a2 2 0 0 1-1.7-3l1-1.7-1-1.7a2 2 0 0 1 1.7-3l2 .2 1-1.7z"/><circle cx="12" cy="12" r="2.5"/></svg></span>
                            <span class="vin-node-name"><?= sanitize($n['name']) ?></span>
                        </button>
                        <?php endforeach; ?>
                        <button type="button" class="vin-node-card all" onclick="vinLoadAll(this)" title="Перебрать все группы (дольше, расходует лимит ключа)"><i class="fa fa-th-large"></i> Все узлы</button>
                    </div>
                    <!-- Состояние B: выбранный узел (схема + детали) — сменяет сетку на месте -->
                    <div id="vinNodeView" style="display:none;">
                        <div class="vin-nv-head">
                            <button type="button" class="vin-nv-back" onclick="vinBackToNodes()"><i class="fa fa-arrow-left"></i> Все узлы</button>
                            <h3 class="vin-nv-title" id="vinNodeTitle"></h3>
                        </div>
                        <div id="vinNvSplit" class="vin-nv-split">
                            <?php if ($pcSchema): ?>
                            <div class="vin-nv-scheme">
                                <div id="vinSchemePanel" class="vin-scheme-wrap" style="display:none;">
                                    <div id="vinSchemeCap" class="vin-scheme-cap"></div>
                                    <div id="vinSchemeBox" class="vin-scheme-box">
                                        <img id="vinSchemeImg" alt="">
                                        <div id="vinSchemeHot"></div>
                                        <div id="vinSchemeTip" class="vin-tip"></div>
                                        <button type="button" class="vin-zoom-btn" onclick="vinLbOpen()" title="Увеличить схему"><i class="fa fa-search-plus"></i></button>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            <div id="vinCatalogBody" style="overflow-x:auto;"></div>
                        </div>
                    </div>
                    <?php if ($pcSchema): ?>
                    <!-- Лайтбокс: схема на весь экран (зум колесом/кнопками + перетаскивание) -->
                    <div id="vinLightbox" class="vin-lb" style="display:none;" onclick="vinLbBg(event)">
                        <div class="vin-lb-bar">
                            <span id="vinLbCap" class="vin-lb-cap"></span>
                            <span class="vin-lb-btns">
                                <button type="button" onclick="vinLbZoom(-1)" title="Уменьшить">&minus;</button>
                                <button type="button" onclick="vinLbZoom(1)" title="Увеличить">+</button>
                                <button type="button" onclick="vinLbClose()" title="Закрыть (Esc)">&times;</button>
                            </span>
                        </div>
                        <div class="vin-lb-stage" id="vinLbStage">
                            <div class="vin-lb-inner" id="vinLbInner">
                                <img id="vinLbImg" alt="">
                                <div id="vinLbHot"></div>
                                <div id="vinLbTip" class="vin-tip"></div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>
                    <div id="vinCatalogStatus" style="background:#fff;border-radius:10px;padding:22px 24px;text-align:center;color:#888;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin:14px 0 32px;<?= $showTree ? 'display:none;' : '' ?>">
                        <i class="fa fa-spinner fa-spin" style="font-size:1.6rem;color:#C70909;"></i>
                        <div style="margin-top:10px;font-size:0.9rem;">Подбираем запчасти по VIN из оригинального каталога…</div>
                        <div style="margin-top:4px;font-size:0.78rem;color:#bbb;">Это может занять несколько секунд.</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- ── Compatible parts (own catalog) ─────────────────────────── -->
        <?php if (!empty($facets) || !empty($compatParts)): ?>
        <?php if (!empty($facets)): ?>
        <div style="background:#fff;border-radius:10px;padding:14px 18px;margin-bottom:18px;box-shadow:0 2px 8px rgba(0,0,0,0.05);">
            <div style="font-size:0.72rem;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:10px;"><i class="fa fa-filter" style="color:var(--vx-red);"></i> Фильтр по категориям</div>
            <div style="display:flex;flex-wrap:wrap;gap:8px;">
                <?php $chipBase = APP_URL . '/pages/vin.php?vin=' . urlencode($vin); $isAll = $filterCat === 0; ?>
                <a href="<?= $chipBase ?>" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;font-size:0.85rem;font-weight:600;<?= $isAll ? 'background:#C70909;color:#fff;' : 'background:#f0f1f5;color:#1d2129;' ?>">
                    Все <span style="opacity:0.75;font-weight:400;">(<?= (int)$totalCompat ?>)</span>
                </a>
                <?php foreach ($facets as $f): $active = $filterCat === (int)$f['id']; ?>
                <a href="<?= $chipBase ?>&cat=<?= (int)$f['id'] ?>" style="display:inline-flex;align-items:center;gap:6px;padding:7px 14px;border-radius:20px;font-size:0.85rem;font-weight:600;<?= $active ? 'background:#C70909;color:#fff;' : 'background:#f0f1f5;color:#1d2129;' ?>">
                    <?= sanitize($f['name']) ?> <span style="opacity:0.75;font-weight:400;">(<?= (int)$f['cnt'] ?>)</span>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <h2 style="font-size:1.3rem;font-weight:700;margin-bottom:20px;color:var(--vx-ink);">
            <i class="fa fa-cogs" style="color:var(--vx-red);"></i> Совместимые запчасти
            <span style="color:#888;font-weight:400;font-size:0.9rem;">(<?= count($compatParts) ?>)</span>
        </h2>

        <?php if (empty($compatParts)): ?>
        <div style="background:#fff;border-radius:10px;padding:24px;text-align:center;color:#888;margin-bottom:32px;">В этой категории запчастей нет. <a href="<?= sanitize($chipBase) ?>" style="color:#C70909;">Показать все</a></div>
        <?php else: ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:20px;margin-bottom:40px;">
            <?php foreach ($compatParts as $p):
                $imgs    = json_decode($p['images'] ?? '[]', true) ?: [];
                $thumb   = $imgs[0] ?? '';
                $st      = getStockStatus((int)$p['stock']);
                $inStock = (int)$p['stock'] > 0;
            ?>
            <div class="vin-part-card" style="background:#fff;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.07);overflow:hidden;display:flex;flex-direction:column;">
                <a href="<?= partUrl($p) ?>">
                    <?php if ($thumb): ?>
                    <img src="<?= sanitize($thumb) ?>" alt="<?= sanitize($p['name']) ?>" style="width:100%;height:160px;object-fit:cover;">
                    <?php else: ?>
                    <div style="width:100%;height:120px;background:#f5f5f5;display:flex;align-items:center;justify-content:center;color:#ddd;font-size:2rem;"><i class="fa fa-image"></i></div>
                    <?php endif; ?>
                </a>
                <div style="padding:14px 16px;flex:1;display:flex;flex-direction:column;">
                    <div style="font-size:0.72rem;color:#aaa;margin-bottom:4px;"><?= sanitize($p['brand_name'] ?? '') ?><?= sanitize(!empty($p['category_name']) ? ' · ' . $p['category_name'] : '') ?></div>
                    <a href="<?= partUrl($p) ?>" style="font-weight:600;color:var(--vx-ink);font-size:0.9rem;display:block;margin-bottom:4px;line-height:1.3;"><?= sanitize(truncate($p['name'], 60)) ?></a>
                    <div style="font-size:0.7rem;color:#bbb;font-family:monospace;margin-bottom:8px;"><?= sanitize($p['part_number']) ?></div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:auto;">
                        <span style="font-size:1.1rem;font-weight:800;color:var(--vx-red);"><?= formatPrice($p['price']) ?></span>
                        <span class="badge badge-<?= $st['class'] ?>" style="font-size:0.7rem;"><?= $st['label'] ?></span>
                    </div>
                    <div style="display:flex;gap:6px;margin-top:10px;">
                        <button type="button" onclick="vinAddToCart(<?= (int)$p['id'] ?>,this)" <?= $inStock ? '' : 'disabled' ?>
                                style="flex:1;background:<?= $inStock ? '#C70909' : '#ccc' ?>;color:#fff;border:none;padding:9px 8px;border-radius:6px;font-size:0.82rem;font-weight:700;cursor:<?= $inStock ? 'pointer' : 'not-allowed' ?>;">
                            <i class="fa fa-shopping-cart"></i> В корзину
                        </button>
                        <button type="button" onclick="vinToggleAnalogs(<?= (int)$p['id'] ?>,this)" title="Аналоги"
                                style="background:#f0f1f5;color:#1d2129;border:none;padding:9px 12px;border-radius:6px;font-size:0.82rem;font-weight:600;cursor:pointer;"><i class="fa fa-exchange"></i></button>
                    </div>
                    <div class="vin-analogs" id="analogs-<?= (int)$p['id'] ?>" style="display:none;margin-top:10px;padding-top:10px;border-top:1px dashed #eef0f3;"></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        <?php else: ?>
        <div style="background:#fff;border-radius:10px;padding:28px 24px;text-align:center;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:32px;">
            <i class="fa fa-search" style="font-size:2.5rem;color:#ddd;display:block;margin-bottom:12px;"></i>
            <h3 style="color:#555;margin-bottom:8px;">Совместимые запчасти ещё не привязаны</h3>
            <p style="color:#888;margin-bottom:20px;">Найдите нужные детали поиском по марке <?= sanitize(!empty($result['make']) ? '«' . $result['make'] . '»' : '') ?> в каталоге.</p>
            <a href="<?= APP_URL ?>/search/index.php?q=<?= urlencode($result['make'] ?? '') ?>" style="display:inline-block;background:#C70909;color:#fff;padding:10px 28px;border-radius:6px;font-weight:600;"><i class="fa fa-search"></i> Найти в каталоге</a>
        </div>
        <?php endif; ?>

    <?php else: ?>
    <!-- VIN entered but car not identified → friendly not-found with CTA -->
    <div style="max-width:760px;margin:30px auto 0;">
        <div class="vx-nf">
            <div class="ic"><svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3M11 8v3M11 14h.01"/></svg></div>
            <h3><?= t('vin_nf_title') ?></h3>
            <p><?= t('vin_nf_text') ?></p>
            <div class="cta">
                <a class="pr" onclick="vxTab('params');document.querySelector('.vx-scard').scrollIntoView({behavior:'smooth'});"><?= t('vin_tab_params') ?></a>
                <a class="gh" href="#vxExpert"><?= t('vin_write_expert') ?></a>
            </div>
        </div>
    </div>
    <?php endif; ?>
    </div><!-- /#vxResults -->

    <?php elseif (!empty($userHistory)): ?>
    <!-- ── User VIN history (no search yet) ──────────────────────────── -->
    <div style="max-width:900px;margin:26px auto 0;">
        <div style="background:#fff;border-radius:12px;padding:22px 24px;box-shadow:0 2px 12px rgba(0,0,0,0.06);">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:14px;">
                <h3 style="font-size:1rem;font-weight:700;color:var(--vx-ink);margin:0;"><i class="fa fa-history" style="color:var(--vx-red);"></i> Ваша история поиска</h3>
                <span style="font-size:0.78rem;color:#aaa;"><?= count($userHistory) ?></span>
            </div>
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(260px,1fr));gap:10px;">
                <?php foreach ($userHistory as $h): ?>
                <a href="<?= APP_URL ?>/pages/vin.php?vin=<?= urlencode($h['vin']) ?>" style="display:block;border:1px solid #eef0f3;border-radius:8px;padding:10px 14px;color:var(--vx-ink);">
                    <div style="font-family:monospace;font-size:0.82rem;letter-spacing:1px;color:#888;margin-bottom:3px;"><?= sanitize($h['vin']) ?></div>
                    <div style="font-weight:600;font-size:0.88rem;"><?= sanitize(trim(($h['make'] ?? '') . ' ' . ($h['model'] ?? ''))) ?: '—' ?><?php if (!empty($h['year'])): ?><span style="color:#aaa;font-weight:400;"> · <?= (int)$h['year'] ?> г.</span><?php endif; ?></div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- ── POPULAR MAKES (quick start) ───────────────────────────────── -->
    <div class="vx-sec">
        <h2 class="t"><?= t('vin_popular') ?> <span><?= t('vin_popular_hint') ?></span></h2>
        <div class="vx-brands">
            <?php foreach (array_keys($vinMakes) as $mk): ?>
            <div class="vx-brand" onclick="vxPickBrand('<?= sanitize(addslashes($mk)) ?>')"><?= sanitize($mk) ?></div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── HOW IT WORKS ──────────────────────────────────────────────── -->
    <div class="vx-sec">
        <h2 class="t"><?= t('vin_how') ?></h2>
        <div class="vx-how">
            <div class="vx-hcard"><div class="num">1</div>
                <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 9h18M7 14h6"/></svg></div>
                <h3><?= t('vin_how1_t') ?></h3><p><?= t('vin_how1_d') ?></p>
            </div>
            <div class="vx-hcard"><div class="num">2</div>
                <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8h.01M11 12h1v4h1"/></svg></div>
                <h3><?= t('vin_how2_t') ?></h3><p><?= t('vin_how2_d') ?></p>
            </div>
            <div class="vx-hcard"><div class="num">3</div>
                <div class="ic"><svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.3 3.7a2 2 0 0 1 3.4 0l1 1.7 2-.2a2 2 0 0 1 1.7 3l-1 1.7 1 1.7a2 2 0 0 1-1.7 3l-2-.2-1 1.7a2 2 0 0 1-3.4 0l-1-1.7-2 .2a2 2 0 0 1-1.7-3l1-1.7-1-1.7a2 2 0 0 1 1.7-3l2 .2 1-1.7z"/><circle cx="12" cy="12" r="2.5"/></svg></div>
                <h3><?= t('vin_how3_t') ?></h3><p><?= t('vin_how3_d') ?></p>
            </div>
        </div>
    </div>

    <!-- ── EXPERT HELP ───────────────────────────────────────────────── -->
    <div class="vx-sec" id="vxExpert">
        <div class="vx-expert">
            <div class="ill"><svg width="60" height="60" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg></div>
            <div class="tx">
                <h3><?= t('vin_expert_title') ?></h3>
                <p><?= t('vin_expert_text') ?></p>
                <div class="cta">
                    <?php if ($exWa !== ''): ?><a class="vx-wa" href="https://wa.me/<?= sanitize($exWa) ?>" target="_blank" rel="noopener"><i class="fa fa-whatsapp"></i> WhatsApp</a><?php endif; ?>
                    <?php if ($exTg !== ''): ?><a class="vx-tg" href="https://t.me/<?= sanitize(ltrim($exTg,'@')) ?>" target="_blank" rel="noopener"><i class="fa fa-telegram"></i> Telegram</a><?php endif; ?>
                    <a class="call" href="tel:<?= sanitize($exPhone) ?>"><i class="fa fa-phone"></i> <?= sanitize($exPhone) ?></a>
                </div>
            </div>
        </div>
    </div>

    <!-- ── TRUST ─────────────────────────────────────────────────────── -->
    <div class="vx-trust">
        <div class="vx-tr"><div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="6" width="15" height="12" rx="1"/><path d="M16 9h4l3 3v6h-7"/><circle cx="6" cy="18.5" r="1.6"/><circle cx="18.5" cy="18.5" r="1.6"/></svg></div><div><b><?= t('free_delivery') ?></b><span><?= t('free_delivery_text') ?></span></div></div>
        <div class="vx-tr"><div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg></div><div><b><?= t('secure_payment') ?></b><span><?= t('secure_payment_text') ?></span></div></div>
        <div class="vx-tr"><div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7L3 8M3 3v5h5"/></svg></div><div><b><?= t('returns') ?></b><span><?= t('returns_text') ?></span></div></div>
        <div class="vx-tr"><div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2 4 5v6c0 5 3.4 8.5 8 10 4.6-1.5 8-5 8-10V5l-8-3z"/><path d="m9 12 2 2 4-4"/></svg></div><div><b><?= t('quality_guarantee') ?></b><span><?= t('quality_text') ?></span></div></div>
        <div class="vx-tr"><div class="ic"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg></div><div><b><?= t('support_24') ?></b><span><?= t('support_24_text') ?></span></div></div>
    </div>

</div><!-- /.container -->
</div><!-- /.vx -->

<meta name="csrf" content="<?= generateCsrfToken() ?>">
<script>
window.VX_MAKES = <?= json_encode($vinMakes, JSON_UNESCAPED_UNICODE) ?>;
window.VX_SEARCH_URL = <?= json_encode(APP_URL . '/search/index.php') ?>;
window.VX_WA = <?= json_encode($exWa) ?>;
window.VX_TG = <?= json_encode(ltrim($exTg, '@')) ?>;
window.VX_PRICE_LAZY = <?= getSetting('catalog_price_autoeuro', '0') === '1' ? 'true' : 'false' ?>;

/* ── Tabs ─────────────────────────────────────────────────────────── */
function vxTab(t){
    document.getElementById('vx-t-vin').classList.toggle('active', t === 'vin');
    document.getElementById('vx-t-params').classList.toggle('active', t === 'params');
    document.getElementById('vx-p-vin').classList.toggle('active', t === 'vin');
    document.getElementById('vx-p-params').classList.toggle('active', t === 'params');
}

/* ── VIN field: uppercase/filter, counter, enable button, example ──── */
var __vinInp = document.getElementById('vinInput');
var __vinBtn = document.getElementById('vinBtn');
var __vinCnt = document.getElementById('vinCnt');
var __vinErr = document.getElementById('vinErr');
if (__vinInp) {
    __vinInp.addEventListener('input', function(){
        var v = this.value.toUpperCase().replace(/[^A-HJ-NPR-Z0-9]/g, '');
        if (v.length > 17) v = v.slice(0, 17);
        this.value = v;
        if (__vinCnt) __vinCnt.textContent = v.length + '/17';
        if (__vinBtn) __vinBtn.disabled = v.length !== 17;
        if (__vinErr) __vinErr.style.display = (v.length > 0 && v.length < 17) ? 'block' : 'none';
    });
}
function vxFillExample(){ if(!__vinInp) return; __vinInp.value = 'WBAWX31060PK42218'; __vinInp.dispatchEvent(new Event('input')); }

/* ── Params cascade (curated) ─────────────────────────────────────── */
(function(){
    var mk = document.getElementById('vxMk');
    if (!mk) return;
    Object.keys(window.VX_MAKES).forEach(function(b){
        var o = document.createElement('option'); o.value = b; o.textContent = b; mk.appendChild(o);
    });
})();
function vxOnMake(){
    var mk = document.getElementById('vxMk'), md = document.getElementById('vxMd'), yr = document.getElementById('vxYr');
    md.innerHTML = '<option value="">' + <?= json_encode(t('vin_model_lbl')) ?> + '</option>';
    yr.innerHTML = '<option value="">' + <?= json_encode(t('vin_year_lbl')) ?> + '</option>';
    yr.disabled = true;
    if (mk.value && window.VX_MAKES[mk.value]) {
        window.VX_MAKES[mk.value].forEach(function(m){ var o=document.createElement('option'); o.value=m; o.textContent=m; md.appendChild(o); });
        md.disabled = false;
    } else { md.disabled = true; }
}
function vxOnModel(){
    var md = document.getElementById('vxMd'), yr = document.getElementById('vxYr');
    yr.innerHTML = '<option value="">' + <?= json_encode(t('vin_year_lbl')) ?> + '</option>';
    if (md.value) { for (var y=(new Date()).getFullYear(); y>=2005; y--){ var o=document.createElement('option'); o.value=y; o.textContent=y; yr.appendChild(o);} yr.disabled=false; }
    else { yr.disabled = true; }
}
function vxParamsSubmit(){
    var mk = document.getElementById('vxMk').value, md = document.getElementById('vxMd').value, yr = document.getElementById('vxYr').value;
    if (!mk) { document.getElementById('vxMk').focus(); return; }
    var label = [mk, md, yr].filter(Boolean).join(' ');
    document.getElementById('vxParamsLabel').textContent = label;
    document.getElementById('vxParamsSearch').href = window.VX_SEARCH_URL + '?q=' + encodeURIComponent((mk + ' ' + md).trim());
    var msg = 'Здравствуйте! Подберите, пожалуйста, запчасти для: ' + label;
    var ex = document.getElementById('vxParamsExpert');
    if (window.VX_WA) ex.href = 'https://wa.me/' + window.VX_WA + '?text=' + encodeURIComponent(msg);
    else if (window.VX_TG) ex.href = 'https://t.me/' + window.VX_TG;
    else ex.href = '#vxExpert';
    var box = document.getElementById('vxParamsResult');
    box.style.display = 'block';
    box.scrollIntoView({behavior:'smooth', block:'center'});
}
function vxPickBrand(b){
    vxTab('params');
    if (window.VX_PC) { document.querySelector('.vx-scard').scrollIntoView({behavior:'smooth'}); return; }
    var mk = document.getElementById('vxMk');
    if (mk) { mk.value = b; vxOnMake(); }
    document.querySelector('.vx-scard').scrollIntoView({behavior:'smooth'});
}

/* ── Живой каскад «По параметрам» (Parts-Catalogs): Марка → Модель → уточнения → авто ── */
(function(){
    var wrap = document.getElementById('vxPcCascade'); if (!wrap) return;
    var curated = document.querySelector('#vx-p-params .vx-params');

    var API = '<?= APP_URL ?>/api/vin_params.php';
    var PAGE = '<?= APP_URL ?>/pages/vin.php';
    var selWrap = document.getElementById('vxPcSelects');
    var statusEl = document.getElementById('vxPcStatus');
    var carsEl  = document.getElementById('vxPcCars');
    var catalogId = '', modelId = '';
    var L_MK = <?= json_encode(t('vin_marka')) ?>, L_MD = <?= json_encode(t('vin_model_lbl')) ?>;

    function e(s){ return String(s==null?'':s).replace(/[&<>"']/g,function(c){return({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c];}); }
    function spin(m){ statusEl.style.color='#9aa0ab'; statusEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i> ' + e(m||'Загрузка…'); }
    function msg(m,err){ statusEl.style.color = err ? '#ff8a8a' : '#9aa0ab'; statusEl.textContent = m || ''; }
    function api(q){ return fetch(API + '?' + q, {credentials:'same-origin'}).then(function(r){ return r.json(); }); }
    function idxCsv(){ var o=[]; selWrap.querySelectorAll('select.pc-param').forEach(function(s){ if(s.value) o.push(s.value); }); return o.join(','); }
    function clearParams(){ selWrap.querySelectorAll('select.pc-param').forEach(function(s){ s.remove(); }); }
    function brandName(){ var b=document.getElementById('pcBrand'); return b && b.options[b.selectedIndex] ? b.options[b.selectedIndex].textContent : ''; }

    selWrap.innerHTML =
        '<select id="pcBrand"><option value="">' + e(L_MK) + '</option></select>' +
        '<select id="pcModel" disabled><option value="">' + e(L_MD) + '</option></select>';
    var pcBrand = document.getElementById('pcBrand'), pcModel = document.getElementById('pcModel');

    // Живой каскад активируем ТОЛЬКО если провайдер реально отдаёт марки
    // (Parts-Catalogs). Иначе на странице остаётся курируемый список — без ложных
    // ошибок и без зависимости от флага провайдера.
    api('step=brands').then(function(d){
        if (!d || !d.success || !d.items || !d.items.length) return;   // не PC → курируемый список
        if (curated) curated.style.display = 'none';
        wrap.style.display = 'block';
        msg('');
        d.items.forEach(function(b){ var o=document.createElement('option'); o.value=b.id; o.textContent=b.name; pcBrand.appendChild(o); });
    }).catch(function(){});

    pcBrand.addEventListener('change', function(){
        catalogId = pcBrand.value; modelId=''; clearParams(); carsEl.innerHTML='';
        pcModel.innerHTML = '<option value="">' + e(L_MD) + '</option>'; pcModel.disabled = true;
        if (!catalogId) { msg(''); return; }
        spin('Загружаем модели…');
        api('step=models&catalogId=' + encodeURIComponent(catalogId)).then(function(d){
            if (!d.items || !d.items.length) { msg('Модели не найдены.', true); return; }
            msg('');
            d.items.forEach(function(m){ var o=document.createElement('option'); o.value=m.id; o.textContent=m.name; pcModel.appendChild(o); });
            pcModel.disabled = false;
        }).catch(function(){ msg('Ошибка загрузки моделей.', true); });
    });

    pcModel.addEventListener('change', function(){
        modelId = pcModel.value; clearParams(); carsEl.innerHTML='';
        if (!modelId) { msg(''); return; }
        reloadParamsAndCars();
    });

    function reloadParamsAndCars(){
        var csv = idxCsv();
        spin('Уточняем параметры…');
        api('step=carparams&catalogId=' + encodeURIComponent(catalogId) + '&modelId=' + encodeURIComponent(modelId) + '&parameter=' + encodeURIComponent(csv))
            .then(function(d){ renderParams(d.items||[]); msg(''); loadCars(); })
            .catch(function(){ msg('Ошибка загрузки параметров.', true); loadCars(); });
    }

    function renderParams(items){
        var chosen = {}; selWrap.querySelectorAll('select.pc-param').forEach(function(s){ if(s.value) chosen[s.getAttribute('data-key')]=s.value; });
        clearParams();
        items.forEach(function(p){
            var s = document.createElement('select'); s.className='pc-param'; s.setAttribute('data-key', p.key||'');
            var ph = document.createElement('option'); ph.value=''; ph.textContent = p.name || '—'; s.appendChild(ph);
            (p.values||[]).forEach(function(v){ var o=document.createElement('option'); o.value=v.idx; o.textContent=v.value; if (chosen[p.key]===v.idx) o.selected=true; s.appendChild(o); });
            s.addEventListener('change', reloadParamsAndCars);
            selWrap.appendChild(s);
        });
    }

    function loadCars(){
        carsEl.innerHTML = '<div style="color:#9aa0ab;font-size:.85rem;"><i class="fa fa-spinner fa-spin"></i> Загружаем автомобили…</div>';
        api('step=cars&catalogId=' + encodeURIComponent(catalogId) + '&modelId=' + encodeURIComponent(modelId) + '&parameter=' + encodeURIComponent(idxCsv()))
            .then(function(d){ renderCars(d.items||[]); })
            .catch(function(){ carsEl.innerHTML = '<div style="color:#ff8a8a;font-size:.85rem;">Ошибка загрузки автомобилей.</div>'; });
    }

    function renderCars(cars){
        if (!cars.length) { carsEl.innerHTML = '<div style="color:#9aa0ab;font-size:.85rem;">Уточните параметры выше — покажем подходящие автомобили.</div>'; return; }
        var bn = brandName();
        var html = '<div style="font-size:.74rem;color:#9aa0ab;text-transform:uppercase;letter-spacing:1px;margin-bottom:8px;">Автомобили (' + cars.length + ') — выберите свой</div>';
        html += '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:10px;">';
        cars.forEach(function(c){
            var nm = c.name || c.modelName || 'Автомобиль';
            var full = (bn ? bn + ' ' : '') + nm;
            var href = PAGE + '?carId=' + encodeURIComponent(c.carId) + '&catalogId=' + encodeURIComponent(c.catalogId)
                     + '&criteria=' + encodeURIComponent(c.criteria||'') + '&modelId=' + encodeURIComponent(c.modelId||'')
                     + '&carName=' + encodeURIComponent(full);
            html += '<a href="' + href + '" class="vx-carpick" style="display:block;background:#fff;border:1px solid #e7e9ee;border-radius:10px;padding:12px 14px;color:#1d2129;font-size:.86rem;font-weight:600;text-decoration:none;transition:.12s;">'
                  + e(nm) + '<span style="display:block;font-weight:400;font-size:.72rem;color:#9aa3af;margin-top:2px;">' + e(bn + (c.modelName ? ' · ' + c.modelName : '')) + '</span></a>';
        });
        html += '</div>';
        carsEl.innerHTML = html;
    }
})();

/* ── Add to cart from VIN results ─────────────────────────────────── */
function vinAddToCart(partId, btn) {
    if (btn.disabled) return;
    var csrf = document.querySelector('meta[name="csrf"]').getAttribute('content');
    var orig = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Добавление…';
    var fd = new FormData();
    fd.append('action', 'add'); fd.append('part_id', partId); fd.append('quantity', 1); fd.append('_csrf', csrf);
    fetch('<?= APP_URL ?>/api/cart.php', { method: 'POST', body: fd, credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.redirect) { window.location.href = d.redirect; return; }
            if (d.success) {
                btn.innerHTML = '<i class="fa fa-check"></i> В корзине'; btn.style.background = '#4caf50';
                var cc = document.querySelector('.cart-count, [data-cart-count]');
                if (cc && d.cart_count != null) cc.textContent = d.cart_count;
                setTimeout(function(){ btn.innerHTML = orig; btn.disabled = false; btn.style.background = '#C70909'; }, 2000);
            } else { alert(d.message || 'Не удалось добавить в корзину'); btn.innerHTML = orig; btn.disabled = false; }
        })
        .catch(function(){ alert('Ошибка сети. Попробуйте ещё раз.'); btn.innerHTML = orig; btn.disabled = false; });
}

/* ── Toggle analog parts inline ───────────────────────────────────── */
function vinToggleAnalogs(partId, btn) {
    var box = document.getElementById('analogs-' + partId);
    if (!box) return;
    if (box.style.display !== 'none') { box.style.display = 'none'; btn.style.background = '#f0f1f5'; return; }
    btn.style.background = '#f6dada';
    if (box.dataset.loaded === '1') { box.style.display = 'block'; return; }
    box.innerHTML = '<div style="text-align:center;color:#aaa;font-size:0.8rem;padding:8px;"><i class="fa fa-spinner fa-spin"></i> Поиск аналогов…</div>';
    box.style.display = 'block';
    fetch('<?= APP_URL ?>/api/vin_analogs.php?part_id=' + partId, { credentials: 'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            box.dataset.loaded = '1';
            if (!d.success || !d.items || d.items.length === 0) { box.innerHTML = '<div style="color:#aaa;font-size:0.78rem;padding:6px 0;">Аналоги не найдены</div>'; return; }
            var html = '<div style="font-size:0.7rem;color:#888;text-transform:uppercase;letter-spacing:1px;margin-bottom:6px;"><i class="fa fa-exchange"></i> Аналоги (' + d.count + ')</div>';
            d.items.forEach(function(a){
                html += '<div style="display:flex;align-items:center;gap:8px;padding:6px 0;border-bottom:1px solid #f5f6f8;">' +
                    '<a href="' + a.url + '" style="flex:1;color:#1d2129;font-size:0.8rem;line-height:1.25;">' +
                    '<div style="font-weight:600;">' + escapeHtml(a.name.length > 40 ? a.name.slice(0,40) + '…' : a.name) + '</div>' +
                    '<div style="font-size:0.68rem;color:#aaa;font-family:monospace;">' + escapeHtml(a.part_number) + ' · ' + escapeHtml(a.brand_name) + '</div></a>' +
                    '<span style="font-size:0.82rem;font-weight:700;color:#C70909;white-space:nowrap;">' + a.price + '</span>' +
                    '<button onclick="vinAddToCart(' + a.id + ',this)" title="В корзину" style="background:#C70909;color:#fff;border:none;width:28px;height:28px;border-radius:5px;cursor:pointer;font-size:0.7rem;"><i class="fa fa-cart-plus"></i></button></div>';
            });
            box.innerHTML = html;
        })
        .catch(function(){ box.innerHTML = '<div style="color:#c00;font-size:0.78rem;">Ошибка загрузки аналогов</div>'; });
}

function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
}

/* ── External catalog: per-node loading + visual scheme (Parts-Catalogs) + crosses ── */
window.VX_PC = <?= $pcProvider ? 'true' : 'false' ?>;
window.VX_PC_SCHEMA = <?= $pcSchema ? 'true' : 'false' ?>;
function jsAttr(s){ return String(s == null ? '' : s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,''); }
function vinCssEsc(s){ return (window.CSS && CSS.escape) ? CSS.escape(String(s)) : String(s); }
function vinSetActiveChip(btn){
    document.querySelectorAll('.vin-node-card').forEach(function(c){ c.classList.remove('active'); });
    if (btn && btn.classList) btn.classList.add('active');
    // Подсветка пункта в классификаторе слева
    var cat = btn && btn.getAttribute ? btn.getAttribute('data-cat') : null;
    document.querySelectorAll('#vinNodeList li').forEach(function(li){
        li.classList.toggle('active', cat !== null && li.getAttribute('data-cat') === cat);
    });
}
/* Переключение контент-панели: сетка узлов ↔ вид узла (схема+детали) — НА МЕСТЕ, без прокрутки вниз. */
function vinShowView(title){
    var grid = document.getElementById('vinNodeGrid'), view = document.getElementById('vinNodeView');
    var t = document.getElementById('vinNodeTitle');
    if (grid) grid.style.display = 'none';
    if (view) view.style.display = 'block';
    if (t) t.textContent = title || '';
    var wrap = document.querySelector('.vin-cat7');
    if (wrap) { try { wrap.scrollIntoView({behavior:'smooth', block:'start'}); } catch(e){} }
}
function vinBackToNodes(){
    var grid = document.getElementById('vinNodeGrid'), view = document.getElementById('vinNodeView');
    var statusEl = document.getElementById('vinCatalogStatus');
    if (view) view.style.display = 'none';
    if (grid) grid.style.display = '';
    if (statusEl) statusEl.style.display = 'none';
    vinSetActiveChip(null);
}
function vinLoadNode(cat, btn){
    vinSetActiveChip(btn);
    var name = btn && btn.getAttribute ? (btn.getAttribute('data-name') || (btn.textContent || '').trim()) : '';
    vinShowView(name);
    if (window.VX_PC_SCHEMA) { vinLoadScheme(cat); } else { vinCatalogFetch('&cat=' + encodeURIComponent(cat)); }
}
function vinLoadAll(btn){
    vinSetActiveChip(btn);
    vinShowView('Все узлы');
    var p = document.getElementById('vinSchemePanel'); if (p) p.style.display = 'none';
    vinCatalogFetch('');
}
/* Клик по узлу в боковом списке → имитируем клик по соответствующей карточке. */
function vinPickNode(cat){ var c = document.querySelector('.vin-node-card[data-cat="' + vinCssEsc(cat) + '"]'); if (c) c.click(); }

/* Подсветка «хотспот ↔ карточка детали» по номеру выноски. */
function vinHi(pos, on){ if (pos === '' || pos == null) return; document.querySelectorAll('[data-pos="' + vinCssEsc(pos) + '"]').forEach(function(el){ el.classList.toggle('hot', on); }); vinTipFor(pos, on); }

/* Карта «позиция → детали» (для тултипа с именем). Заполняется vinBuildPartsHtml. */
window.VIN_POS = {};
/* Данные текущей схемы (для лайтбокса). Заполняется vinRenderScheme. */
window.VIN_SCHEME = null;
/* Координаты точек по позициям (px в размере схемы) — для вырезки-«фото» в карточке. */
window.VIN_HOT = {};

/* «Фото» карточки = вырезка (зум-фрагмент) схемы вокруг точки детали. */
function vinApplyCrops(){
    var img = document.getElementById('vinSchemeImg');
    if (!img || !img.naturalWidth || !window.VIN_SCHEME || !window.VIN_SCHEME.img) return;
    var W = img.naturalWidth, H = img.naturalHeight, url = window.VIN_SCHEME.img;
    document.querySelectorAll('.vin-crop[data-pos]').forEach(function(el){
        if (el.dataset.cropped === '1') return;
        var h = window.VIN_HOT[el.getAttribute('data-pos')];
        if (!h) return;
        var box = el.clientWidth || 84;
        var cx = h.x + h.w / 2, cy = h.y + h.h / 2;
        var win = Math.max(h.w, h.h) * 4; if (win < 130) win = 130;  // окно вокруг детали
        var scale = box / win;
        el.style.backgroundImage    = 'url("' + url + '")';
        el.style.backgroundRepeat   = 'no-repeat';
        el.style.backgroundSize     = (W * scale) + 'px ' + (H * scale) + 'px';
        el.style.backgroundPosition = (-(cx - win / 2) * scale) + 'px ' + (-(cy - win / 2) * scale) + 'px';
        el.dataset.cropped = '1';
    });
}

/* HTML тултипа для позиции: имя первой детали + бренд/OEM, и счётчик остальных. */
function vinTipHtml(pos){
    var arr = (window.VIN_POS && window.VIN_POS[pos]) ? window.VIN_POS[pos] : [];
    if (!arr.length) return '<b>№' + escapeHtml(pos) + '</b>';
    var p = arr[0];
    var h = '<b>№' + escapeHtml(pos) + '</b> ' + escapeHtml(p.name || '');
    var meta = [p.brand, p.oem].filter(Boolean).map(escapeHtml).join(' · ');
    if (meta) h += '<span class="vt-m">' + meta + '</span>';
    if (arr.length > 1) h += '<span class="vt-more">+ ещё ' + (arr.length - 1) + ' на позиции №' + escapeHtml(pos) + '</span>';
    return h;
}
/* Тач-устройства (курсор coarse / нет реального hover) — на них mouseenter «залипает»
   без парного mouseleave (нет ухода курсора), поэтому автотултип по наведению там
   отключаем; подсветка + переход по клику/тапу работают как обычно (см. vinWireHots). */
var vinIsTouch = ('ontouchstart' in window) || (window.matchMedia && window.matchMedia('(pointer:coarse)').matches);

/* Показать/скрыть тултип у выноски на активной схеме (лайтбокс если открыт, иначе обычная). */
function vinTipFor(pos, on){
    if (vinIsTouch) return;
    var lb  = vinLbIsOpen();
    var box = document.getElementById(lb ? 'vinLbInner' : 'vinSchemeBox');
    if (!box) return;
    var tip = box.querySelector('.vin-tip');
    if (!tip) return;
    if (!on) { tip.style.display = 'none'; return; }
    var hotEl = box.querySelector('.vin-hot[data-pos="' + vinCssEsc(pos) + '"]');
    var left, top;
    if (hotEl) {
        tip.className = 'vin-tip';
        tip.innerHTML = vinTipHtml(pos);
        left = hotEl.offsetLeft + hotEl.offsetWidth / 2;
        top  = hotEl.offsetTop;
    } else {
        // У детали нет точки на схеме (Parts-Catalogs не дал координаты) —
        // показываем имя сверху с пометкой, чтобы наведение не было «мёртвым».
        tip.className = 'vin-tip at-top';
        tip.innerHTML = vinTipHtml(pos) + '<span class="vt-more">точка на схеме не указана</span>';
        left = box.clientWidth / 2;
        top  = 10;
    }
    // Клэмп по горизонтали, чтобы тултип (~240px) не обрезался за краем узкой колонки.
    var half = 120;
    left = Math.max(half, Math.min(box.clientWidth - half, left));
    tip.style.left = left + 'px';
    tip.style.top  = top + 'px';
    tip.style.display = 'block';
}

/* ── Лайтбокс схемы: открыть на весь экран, зум колесом/кнопками, перетаскивание ── */
var vinLbScale = 1, vinLbTx = 0, vinLbTy = 0, vinLbDrag = null;
function vinLbIsOpen(){ var lb = document.getElementById('vinLightbox'); return !!(lb && lb.style.display === 'flex'); }
function vinLbApply(){ var el = document.getElementById('vinLbInner'); if (el) el.style.transform = 'translate(' + vinLbTx + 'px,' + vinLbTy + 'px) scale(' + vinLbScale + ')'; }
function vinLbZoom(dir){
    vinLbScale = Math.min(5, Math.max(1, +(vinLbScale + dir * 0.3).toFixed(2)));
    if (vinLbScale === 1) { vinLbTx = 0; vinLbTy = 0; }
    vinLbApply();
}
/* Навесить обработчики на выноски (общий для обычной схемы и лайтбокса). */
function vinWireHots(container, inLb){
    container.querySelectorAll('.vin-hot').forEach(function(a){
        var pos = a.getAttribute('data-pos');
        if (!vinIsTouch) {
            a.addEventListener('mouseenter', function(){ vinHi(pos, true); });
            a.addEventListener('mouseleave', function(){ vinHi(pos, false); });
        }
        a.addEventListener('click', function(e){ e.preventDefault(); e.stopPropagation();
            vinHi(pos, true); setTimeout(function(){ vinHi(pos, false); }, 1600);
            if (inLb) vinLbClose();
            var c = document.querySelector('.vin-pcard[data-pos="' + vinCssEsc(pos) + '"]');
            if (c) c.scrollIntoView({behavior:'smooth', block:'center'});
        });
    });
}
function vinLbOpen(){
    if (!window.VIN_SCHEME || !window.VIN_SCHEME.img) return;
    var lb = document.getElementById('vinLightbox'); if (!lb) return;
    document.getElementById('vinLbImg').src = window.VIN_SCHEME.img;
    document.getElementById('vinLbCap').textContent = window.VIN_SCHEME.caption || '';
    var lbhot = document.getElementById('vinLbHot');
    lbhot.innerHTML = document.getElementById('vinSchemeHot').innerHTML;  // выноски в % (уже масштабированы)
    vinWireHots(lbhot, true);
    vinLbScale = 1; vinLbTx = 0; vinLbTy = 0; vinLbApply();
    lb.style.display = 'flex';
    // Заблокировать скролл фона: на iOS Safari инерционная прокрутка страницы под
    // fixed-лайтбоксом просвечивала сквозь него (виден кусок страницы позади схемы).
    document.body.style.overflow = 'hidden'; document.body.style.touchAction = 'none';
}
/* Открыть лайтбокс, сразу приблизив к конкретной детали (клик по вырезке-«фото»). */
function vinLbOpenAt(pos){
    vinLbOpen();
    var h = window.VIN_HOT[pos], img = document.getElementById('vinLbImg');
    if (!h || !img) return;
    var apply = function(){
        var stage = document.getElementById('vinLbStage');
        var w = img.clientWidth, ht = img.clientHeight;
        var natW = img.naturalWidth || 0, natH = img.naturalHeight || 0;
        if (!w || !ht || !natW || !natH || !stage) return;  // картинка ещё не готова
        // Окно вокруг детали (в px изображения). Масштаб — чтобы окно заполнило ~90%
        // экрана: виден только фрагмент, остальное обрезано (overflow лайтбокса).
        var winNat  = Math.max(h.w, h.h) * 5; if (winNat < 150) winNat = 150;
        var winDisp = winNat / natW * w;
        var S = 0.9 * Math.min(stage.clientWidth, stage.clientHeight) / winDisp;
        S = Math.max(1.5, Math.min(10, S));
        var cx = (h.x + h.w / 2) / natW * w, cy = (h.y + h.h / 2) / natH * ht;
        vinLbScale = S;
        vinLbTx = -(cx - w / 2) * S;
        vinLbTy = -(cy - ht / 2) * S;
        vinLbApply();  // деталь оказывается в центре экрана — тултип не показываем (он бы раздулся)
    };
    // Считаем ТОЛЬКО когда картинка реально загружена и размер известен.
    if (img.complete && img.naturalWidth) requestAnimationFrame(apply);
    else img.addEventListener('load', function(){ requestAnimationFrame(apply); }, { once:true });
}
function vinLbClose(){
    var lb = document.getElementById('vinLightbox'); if (lb) lb.style.display = 'none';
    var t = document.querySelector('#vinLbInner .vin-tip'); if (t) t.style.display = 'none';
    // Разблокировать скролл фона (см. vinLbOpen — блокировка спасает от "просвечивания"
    // страницы под лайтбоксом на iOS Safari при инерционной прокрутке под fixed-слоем).
    document.body.style.overflow = ''; document.body.style.touchAction = '';
}
function vinLbBg(e){ if (e.target && e.target.id === 'vinLightbox') vinLbClose(); }
(function(){
    var stage = document.getElementById('vinLbStage');
    if (stage){
        stage.addEventListener('wheel', function(e){ if (!vinLbIsOpen()) return; e.preventDefault(); vinLbZoom(e.deltaY < 0 ? 1 : -1); }, { passive:false });
        stage.addEventListener('mousedown', function(e){ if (vinLbScale <= 1) return; vinLbDrag = { x:e.clientX, y:e.clientY, tx:vinLbTx, ty:vinLbTy }; stage.classList.add('drag'); });
        window.addEventListener('mousemove', function(e){ if (!vinLbDrag) return; vinLbTx = vinLbDrag.tx + (e.clientX - vinLbDrag.x); vinLbTy = vinLbDrag.ty + (e.clientY - vinLbDrag.y); vinLbApply(); });
        window.addEventListener('mouseup', function(){ if (vinLbDrag){ vinLbDrag = null; stage.classList.remove('drag'); } });

        // ── Тач: pinch двумя пальцами — зум; один палец при zoom>1 — перетаскивание;
        //    двойной тап — переключить 1× ↔ 2.5×. Раньше на тач-экране зумом можно было
        //    управлять только кнопками ±, жестами — никак.
        var pinchStart = null, panStart = null, lastTapT = 0, lastTapPos = null;
        function tDist(a, b){ return Math.hypot(a.clientX - b.clientX, a.clientY - b.clientY); }
        stage.addEventListener('touchstart', function(e){
            if (!vinLbIsOpen()) return;
            if (e.touches.length === 2) {
                e.preventDefault();
                pinchStart = { dist: tDist(e.touches[0], e.touches[1]), scale: vinLbScale };
                panStart = null;
            } else if (e.touches.length === 1) {
                var t = e.touches[0], now = Date.now();
                if (lastTapPos && (now - lastTapT) < 320 && Math.hypot(t.clientX - lastTapPos.x, t.clientY - lastTapPos.y) < 30) {
                    vinLbScale = vinLbScale > 1 ? 1 : 2.5;
                    if (vinLbScale === 1) { vinLbTx = 0; vinLbTy = 0; }
                    vinLbApply();
                    lastTapT = 0; lastTapPos = null;
                    return;
                }
                lastTapT = now; lastTapPos = { x: t.clientX, y: t.clientY };
                if (vinLbScale > 1) panStart = { x: t.clientX, y: t.clientY, tx: vinLbTx, ty: vinLbTy };
            }
        }, { passive:false });
        stage.addEventListener('touchmove', function(e){
            if (!vinLbIsOpen()) return;
            if (e.touches.length === 2 && pinchStart) {
                e.preventDefault();
                var d = tDist(e.touches[0], e.touches[1]);
                vinLbScale = Math.min(5, Math.max(1, +(pinchStart.scale * (d / pinchStart.dist)).toFixed(2)));
                vinLbApply();
            } else if (e.touches.length === 1 && panStart) {
                e.preventDefault();
                var t = e.touches[0];
                vinLbTx = panStart.tx + (t.clientX - panStart.x);
                vinLbTy = panStart.ty + (t.clientY - panStart.y);
                vinLbApply();
            }
        }, { passive:false });
        stage.addEventListener('touchend', function(e){
            if (e.touches.length < 2) pinchStart = null;
            if (e.touches.length < 1) { panStart = null; if (vinLbScale === 1) { vinLbTx = 0; vinLbTy = 0; vinLbApply(); } }
        });
    }
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape' && vinLbIsOpen()) vinLbClose(); });
})();
/* Клик по номеру детали → мигнуть выноской на схеме. Схема залипает слева и всегда
   на виду (sticky), поэтому прокрутка к ней больше не нужна. */
function vinFocusPos(pos){
    vinHi(pos, true);
    setTimeout(function(){ vinHi(pos, false); }, 1600);
}

/* Контекст авто: VIN ИЛИ выбранное по параметрам авто (carId/catalogId/criteria). */
function vinCtxQuery(){
    var box = document.getElementById('vinCatalog'); if (!box) return '';
    var carId = box.getAttribute('data-car-id') || '', catId = box.getAttribute('data-catalog-id') || '';
    if (carId && catId) {
        return 'carId=' + encodeURIComponent(carId) + '&catalogId=' + encodeURIComponent(catId)
             + '&criteria=' + encodeURIComponent(box.getAttribute('data-criteria') || '')
             + '&brand=' + encodeURIComponent(box.getAttribute('data-brand') || '');
    }
    var vin = box.getAttribute('data-vin') || '';
    return vin.length === 17 ? 'vin=' + encodeURIComponent(vin) : '';
}

/* ── Визуальная взрыв-схема узла (Parts-Catalogs): картинка + кликабельные хотспоты ── */
function vinLoadScheme(cat){
    var ctx = vinCtxQuery(); if (!ctx) return;
    var statusEl = document.getElementById('vinCatalogStatus'), bodyEl = document.getElementById('vinCatalogBody'), countEl = document.getElementById('vinCatalogCount');
    var panel = document.getElementById('vinSchemePanel');
    statusEl.style.display = 'block';
    statusEl.innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:1.6rem;color:#C70909;"></i><div style="margin-top:10px;font-size:0.9rem;">Загружаем схему и детали…</div>';
    bodyEl.innerHTML = ''; countEl.textContent = '';
    if (panel) panel.style.display = 'none';
    fetch('<?= APP_URL ?>/api/vin_scheme.php?' + ctx + '&cat=' + encodeURIComponent(cat), { credentials:'same-origin' })
        .then(function(r){ return r.json(); }).then(vinRenderScheme)
        .catch(function(){ statusEl.innerHTML = '<div style="font-size:0.9rem;color:#c0392b;">Не удалось загрузить схему. Попробуйте ещё раз.</div>'; });
}
function vinScaleHot(img, hot){
    if (hot.dataset.scaled === '1') return;
    var W = img.naturalWidth, H = img.naturalHeight; if (!W || !H) return;
    hot.dataset.scaled = '1';
    hot.querySelectorAll('.vin-hot').forEach(function(n){
        n.style.left   = (parseFloat(n.style.left)   / W * 100) + '%';
        n.style.top    = (parseFloat(n.style.top)    / H * 100) + '%';
        n.style.width  = (parseFloat(n.style.width)  / W * 100) + '%';
        n.style.height = (parseFloat(n.style.height) / H * 100) + '%';
    });
}
function vinRenderScheme(d){
    var statusEl = document.getElementById('vinCatalogStatus'), bodyEl = document.getElementById('vinCatalogBody'), countEl = document.getElementById('vinCatalogCount');
    var panel = document.getElementById('vinSchemePanel');
    if (d.rate_limited) { statusEl.style.display = 'block'; statusEl.innerHTML = '<div style="font-size:0.9rem;color:#b8860b;"><i class="fa fa-clock-o"></i> Превышен суточный лимит запросов. Попробуйте позже.</div>'; return; }
    var parts = d.parts || [];
    // Диаграмма (если картинка есть)
    if (panel && d.img) {
        var cap = document.getElementById('vinSchemeCap'), img = document.getElementById('vinSchemeImg'), hot = document.getElementById('vinSchemeHot');
        cap.textContent = d.caption || '';
        window.VIN_SCHEME = { img: d.img, caption: d.caption || '' };  // для лайтбокса
        window.VIN_HOT = {};                                           // координаты точек для вырезки
        hot.innerHTML = ''; hot.dataset.scaled = '';
        (d.hotspots || []).forEach(function(h){
            if (!window.VIN_HOT[h.n]) window.VIN_HOT[h.n] = { x:+h.x, y:+h.y, w:+h.w, h:+h.h };
            var a = document.createElement('a'); a.className = 'vin-hot'; a.href = '#'; a.setAttribute('data-pos', h.n);
            a.style.left = h.x + 'px'; a.style.top = h.y + 'px'; a.style.width = h.w + 'px'; a.style.height = h.h + 'px';
            if (!vinIsTouch) {
                a.addEventListener('mouseenter', function(){ vinHi(h.n, true); });
                a.addEventListener('mouseleave', function(){ vinHi(h.n, false); });
            }
            a.addEventListener('click', function(e){ e.preventDefault();
                vinHi(h.n, true); setTimeout(function(){ vinHi(h.n, false); }, 1600);
                var c = document.querySelector('.vin-pcard[data-pos="' + vinCssEsc(h.n) + '"]'); if (c) c.scrollIntoView({behavior:'smooth', block:'center'}); });
            hot.appendChild(a);
        });
        img.onload = function(){ vinScaleHot(img, hot); vinApplyCrops(); };
        img.src = d.img;
        if (img.complete && img.naturalWidth) vinScaleHot(img, hot);
        panel.style.display = 'block';
    } else if (panel) { panel.style.display = 'none'; window.VIN_SCHEME = null; window.VIN_HOT = {}; }
    // Двухколоночная раскладка (схема слева sticky + детали справа) — только если
    // у узла реально есть картинка схемы; иначе детали занимают всю ширину.
    var split = document.getElementById('vinNvSplit');
    if (split) split.classList.toggle('has-scheme', !!(panel && d.img));
    // Детали узла (список справа от схемы). Контент сменяется на месте (vinShowView).
    if (!parts.length) {
        statusEl.style.display = 'block';
        statusEl.innerHTML = '<div style="font-size:0.9rem;">В этом узле деталей не найдено. Выберите другой узел.</div>';
        return;
    }
    statusEl.style.display = 'none'; countEl.textContent = '(' + parts.length + ')';
    bodyEl.innerHTML = vinBuildPartsHtml(parts) + '<p style="text-align:center;color:#bbb;font-size:0.76rem;margin:6px 0 32px;"><i class="fa fa-plug"></i> Оригинальный каталог Parts-Catalogs' + (d.from_cache ? ' · из кэша' : '') + '</p>';
    vinApplyCrops();  // вырезки-«фото» (если картинка уже загружена)
    if (window.VX_PRICE_LAZY) vinFillPrices(bodyEl);
}
function vinCatalogFetch(extra){
    var box = document.getElementById('vinCatalog'); if (!box) return;
    var vin = box.getAttribute('data-vin') || ''; if (vin.length !== 17) return;
    var statusEl = document.getElementById('vinCatalogStatus'), bodyEl = document.getElementById('vinCatalogBody'), countEl = document.getElementById('vinCatalogCount');
    statusEl.style.display = 'block';
    statusEl.innerHTML = '<i class="fa fa-spinner fa-spin" style="font-size:1.6rem;color:#C70909;"></i><div style="margin-top:10px;font-size:0.9rem;">Загружаем запчасти из оригинального каталога…</div>';
    bodyEl.innerHTML = ''; countEl.textContent = '';
    fetch('<?= APP_URL ?>/api/vin_catalog.php?vin=' + encodeURIComponent(vin) + extra, { credentials:'same-origin' })
        .then(function(r){ return r.json(); }).then(vinRenderCatalog)
        .catch(function(){ statusEl.innerHTML = '<div style="font-size:0.9rem;color:#c0392b;">Не удалось загрузить каталог. Попробуйте ещё раз.</div>'; });
}
/* Построить HTML карточек деталей (сгруппировано по узлу). data-pos = номер выноски
   (для связи со схемой). Общий для каталога (PartsAPI) и схемы (Parts-Catalogs). */
/* Расшифровка частых сокращений OEM-каталога (BMW ETK и т.п.) для читаемости.
   Разворачиваем только однозначные; часть сокращений остаётся (так в исходных данных). */
function vinExpandAbbr(s){
    if (!s) return s || '';
    return String(s)
        .replace(/к-том/g, 'комплектом').replace(/К-том/g, 'Комплектом')
        .replace(/К-т/g, 'Комплект').replace(/к-т/g, 'комплект')
        .replace(/Компл\./g, 'Комплект ').replace(/компл\./g, 'комплект ')
        .replace(/доосн\./g, 'дооснащения ').replace(/дообор\./g, 'дооборудование ')
        .replace(/нерж\./g, 'нержавеющей ')
        .replace(/Облиц\./g, 'Облицовка ').replace(/облиц\./g, 'облицовка ')
        .replace(/солнцезащ\.?/g, 'солнцезащитные ')
        .replace(/электр\./g, 'электрич.')
        .replace(/\s{2,}/g, ' ').trim();
}
function vinBuildPartsHtml(items){
    var groups = {};
    // Карта «позиция → детали» для тултипа на схеме (имя детали при наведении).
    window.VIN_POS = {};
    items.forEach(function(it){
        var g = it.group || 'Прочее'; (groups[g] = groups[g] || []).push(it);
        var p = it.pos ? String(it.pos) : '';
        if (p) { (window.VIN_POS[p] = window.VIN_POS[p] || []).push({ name: vinExpandAbbr(it.name), brand: it.brand || '', oem: it.part_number || '' }); }
    });
    var html = '', idx = 0;
    Object.keys(groups).forEach(function(g){
        html += '<div style="margin-bottom:26px;"><div class="vin-grp">' + escapeHtml(g) + '</div><div class="vin-pgrid">';
        groups[g].forEach(function(it){
            idx++; var cid = 'vinc-' + idx;
            var pos = it.pos ? String(it.pos) : '';
            // Десктоп: подсветка по наведению (mouseover/out). Тач-устройства: mouseout там
            // часто не срабатывает до следующего тапа по другому элементу (тултип «залипает») —
            // вместо этого тап переключает подсветку и она гаснет сама через 1.6с (vinFocusPos).
            var posAttr  = pos ? ' data-pos="' + escapeHtml(pos) + '"' + (vinIsTouch
                ? ' onclick="vinFocusPos(\'' + jsAttr(pos) + '\')"'
                : ' onmouseover="vinHi(\'' + jsAttr(pos) + '\',true)" onmouseout="vinHi(\'' + jsAttr(pos) + '\',false)"') : '';
            var posBadge = pos ? '<span class="vin-pos-badge">№' + escapeHtml(pos) + '</span>' : '';
            html += '<div class="vin-pcard" id="' + cid + '"' + posAttr + '>';
            html += '<div class="vin-pcard-t">' + posBadge + escapeHtml(vinExpandAbbr(it.name)) + '<span>' + escapeHtml(it.brand || '') + ' · ' + escapeHtml(it.part_number) + '</span></div>';
            // У OEM-каталога нет фото детали. Если есть точка на схеме — «фото» = вырезка
            // (зум-фрагмент) схемы вокруг детали (фон ставит vinApplyCrops). Иначе — № или пусто.
            var hasCrop = pos && window.VIN_SCHEME && window.VIN_SCHEME.img && window.VIN_HOT && window.VIN_HOT[pos];
            var phBox;
            if (hasCrop) {
                phBox = '<div class="vin-pcard-ph vin-crop" data-pos="' + escapeHtml(pos) + '" onclick="vinLbOpenAt(\'' + jsAttr(pos) + '\')" title="Увеличить деталь"><span class="vin-crop-z"><i class="fa fa-search-plus"></i></span><span class="vin-crop-n">№' + escapeHtml(pos) + '</span></div>';
            } else if (pos) {
                phBox = '<div class="vin-pcard-ph vin-pos-box" onclick="vinFocusPos(\'' + jsAttr(pos) + '\')" title="Показать на схеме">№' + escapeHtml(pos) + '</div>';
            } else {
                phBox = '';
            }
            html += '<div class="vin-pcard-b">' + phBox + '<div class="vin-pcard-buy">';
            if (it.in_catalog) {
                html += '<div class="vin-price">' + escapeHtml(it.price) + '</div>';
                html += '<div class="vin-stock' + (it.stock > 0 ? ' ok' : '') + '">' + (it.stock > 0 ? 'в наличии · доставка Худжанд' : 'под заказ') + '</div>';
                html += '<button type="button" class="vin-cart" onclick="vinAddToCart(' + it.part_id + ',this)"' + (it.stock > 0 ? '' : ' disabled') + '>В корзину</button>';
            } else {
                html += '<div class="vin-price ph vin-price-ph" data-oem="' + escapeHtml(it.part_number) + '" data-brand="' + escapeHtml(it.brand || '') + '">под заказ</div>';
                html += '<div class="vin-stock">цена уточняется</div>';
                html += '<a class="vin-cart ghost" href="<?= APP_URL ?>/search/index.php?q=' + encodeURIComponent(it.part_number) + '">Найти в каталоге</a>';
            }
            html += '<button type="button" class="vin-cross-btn" onclick="vinCrosses(\'' + jsAttr(it.part_number) + '\',\'' + jsAttr(it.brand) + '\',this,\'' + cid + '\')"><i class="fa fa-exchange"></i> аналоги по кроссам</button>';
            html += '</div></div><div class="vin-cross-box" id="cross-' + cid + '"></div></div>';
        });
        html += '</div></div>';
    });
    return html;
}
function vinRenderCatalog(d){
    var statusEl = document.getElementById('vinCatalogStatus'), bodyEl = document.getElementById('vinCatalogBody'), countEl = document.getElementById('vinCatalogCount');
    window.VIN_SCHEME = null; window.VIN_HOT = {};  // каталог PartsAPI без схемы — вырезок нет
    if (d.rate_limited) { statusEl.style.display = 'block'; statusEl.innerHTML = '<div style="font-size:0.9rem;color:#b8860b;"><i class="fa fa-clock-o"></i> Каталог временно недоступен: превышен суточный лимит запросов. Попробуйте позже.</div>'; return; }
    if (!d.success || !d.items || d.items.length === 0) { statusEl.style.display = 'block'; statusEl.innerHTML = '<div style="font-size:0.9rem;">В этом узле по данному VIN запчасти не найдены. Выберите другой узел или нажмите «Все узлы».</div>'; return; }
    statusEl.style.display = 'none'; countEl.textContent = '(' + d.count + ')';
    bodyEl.innerHTML = vinBuildPartsHtml(d.items) + '<p style="text-align:center;color:#bbb;font-size:0.76rem;margin:6px 0 32px;"><i class="fa fa-plug"></i> Оригинальный каталог TecDoc / PartsAPI' + (d.from_cache ? ' · из кэша' : '') + '</p>';
    if (window.VX_PRICE_LAZY) vinFillPrices(bodyEl);
}

/* Ленивая подгрузка цен для деталей не со склада (свой склад → AutoEuro). */
function vinFillPrices(scope){
    var phs = (scope || document).querySelectorAll('.vin-price-ph');
    Array.prototype.forEach.call(phs, function(ph){
        if (ph.getAttribute('data-done')) return;
        ph.setAttribute('data-done', '1');
        var oem = ph.getAttribute('data-oem') || '', brand = ph.getAttribute('data-brand') || '';
        if (!oem) return;
        fetch('<?= APP_URL ?>/api/vin_price.php?oem=' + encodeURIComponent(oem) + '&brand=' + encodeURIComponent(brand), { credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(d){
                if (!d || !d.found) return;
                var dlv = d.delivery ? ' · ' + escapeHtml(String(d.delivery)) + ' дн' : '';
                var src = d.source === 'warehouse' ? 'склад' : 'поставщик';
                ph.classList.remove('ph');         // превратить плашку «под заказ» в красный ценник
                ph.innerHTML = escapeHtml(d.price);
                var buy = ph.closest('.vin-pcard-buy');
                if (buy) {
                    var st = buy.querySelector('.vin-stock');
                    if (st) { st.textContent = src + dlv; st.classList.add('ok'); }
                } else {
                    ph.innerHTML = escapeHtml(d.price) + ' <span style="font-size:0.66rem;color:#888;">' + src + dlv + '</span>';
                }
            })
            .catch(function(){});
    });
}
function vinCrosses(article, brand, btn, cid){
    var box = document.getElementById('cross-' + cid); if (!box) return;
    if (box.getAttribute('data-open')) { box.innerHTML = ''; box.removeAttribute('data-open'); btn.classList.remove('on'); return; }
    box.setAttribute('data-open', '1'); btn.classList.add('on');
    box.innerHTML = '<div style="color:#aaa;font-size:0.8rem;padding:8px 0;"><i class="fa fa-spinner fa-spin"></i> Поиск аналогов (кроссов)…</div>';
    fetch('<?= APP_URL ?>/api/vin_crosses.php?article=' + encodeURIComponent(article) + '&brand=' + encodeURIComponent(brand), { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (d.rate_limited){ box.innerHTML = '<div style="color:#b8860b;font-size:0.8rem;">Превышен лимит запросов. Попробуйте позже.</div>'; return; }
            if (!d.success || !d.items || d.items.length === 0){ box.innerHTML = '<div style="color:#aaa;font-size:0.8rem;">Аналоги (кроссы) не найдены.</div>'; return; }
            var html = '<div class="vin-cross-h"><i class="fa fa-exchange"></i> Аналоги по кроссам (' + d.count + ')</div>';
            d.items.forEach(function(it){
                html += '<div class="vin-cross-row"><div class="ci"><b>' + escapeHtml(it.brand || '') + '</b> <span>' + escapeHtml(it.part_number) + '</span>' + (it.is_original ? ' <em>(исходный)</em>' : '') + '</div>';
                if (it.in_catalog){
                    html += '<span class="cp">' + escapeHtml(it.price) + '</span>';
                    html += it.stock > 0
                        ? '<button type="button" class="vin-cart sm" onclick="vinAddToCart(' + it.part_id + ',this)">В корзину</button>'
                        : '<span class="cu">под заказ</span>';
                } else {
                    html += '<span class="vin-price-ph" data-oem="' + escapeHtml(it.part_number) + '" data-brand="' + escapeHtml(it.brand || '') + '"></span>';
                    html += '<a class="cl" href="<?= APP_URL ?>/search/index.php?q=' + encodeURIComponent(it.part_number) + '">найти</a>';
                }
                html += '</div>';
            });
            box.innerHTML = html;
            if (window.VX_PRICE_LAZY) vinFillPrices(box);
        })
        .catch(function(){ box.innerHTML = '<div style="color:#c00;font-size:0.8rem;">Ошибка загрузки аналогов.</div>'; });
}

/* SVG-плейсхолдер миниатюры узла (когда у провайдера нет картинки). */
var VIN_NODE_PH = '<svg width="34" height="34" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="M10.3 3.7a2 2 0 0 1 3.4 0l1 1.7 2-.2a2 2 0 0 1 1.7 3l-1 1.7 1 1.7a2 2 0 0 1-1.7 3l-2-.2-1 1.7a2 2 0 0 1-3.4 0l-1-1.7-2 .2a2 2 0 0 1-1.7-3l1-1.7-1-1.7a2 2 0 0 1 1.7-3l2 .2 1-1.7z"/><circle cx="12" cy="12" r="2.5"/></svg>';

/* Parts-Catalogs: узлы зависят от конкретного авто → тянем реальное дерево по VIN.
   Карточки с миниатюрами схем (img из groups2) — как у 7zap. Первый узел НЕ
   автогрузим: пользователь видит сетку узлов и выбирает сам. */
function vinLoadPcNodes(){
    var ctx = vinCtxQuery(); if (!ctx) return;
    var grid = document.getElementById('vinNodeGrid'), side = document.getElementById('vinNodeList');
    var statusEl = document.getElementById('vinCatalogStatus');
    if (grid) grid.innerHTML = '<div style="grid-column:1/-1;color:#999;font-size:0.85rem;padding:8px;"><i class="fa fa-spinner fa-spin"></i> Загружаем узлы автомобиля…</div>';
    fetch('<?= APP_URL ?>/api/vin_nodes.php?' + ctx, { credentials:'same-origin' })
        .then(function(r){ return r.json(); })
        .then(function(d){
            if (!d.success || !d.nodes || !d.nodes.length) {
                if (grid) grid.innerHTML = '';
                if (statusEl) { statusEl.style.display = 'block'; statusEl.innerHTML = '<div style="font-size:0.9rem;">Узлы для этого автомобиля не найдены. Проверьте данные или ключ Parts-Catalogs.</div>'; }
                return;
            }
            var ch = '', sh = '';
            d.nodes.forEach(function(n){
                var cat = jsAttr(n.cat), nm = escapeHtml(vinExpandAbbr(n.name));
                var thumb = n.img
                    ? '<img src="' + escapeHtml(n.img) + '" alt="" loading="lazy" onerror="this.parentNode.innerHTML=VIN_NODE_PH;">'
                    : VIN_NODE_PH;
                ch += '<button type="button" class="vin-node-card" data-cat="' + escapeHtml(n.cat) + '" data-name="' + nm + '" onclick="vinLoadNode(\'' + cat + '\', this)">'
                    + '<span class="vin-node-thumb">' + thumb + '</span>'
                    + '<span class="vin-node-name">' + nm + '</span></button>';
                sh += '<li data-cat="' + escapeHtml(n.cat) + '" onclick="vinPickNode(\'' + cat + '\')">' + nm + '</li>';
            });
            if (grid) grid.innerHTML = ch;
            if (side)  side.innerHTML  = sh;
        })
        .catch(function(){
            if (grid) grid.innerHTML = '';
            if (statusEl) { statusEl.style.display = 'block'; statusEl.innerHTML = '<div style="font-size:0.9rem;color:#c0392b;">Не удалось загрузить узлы. Попробуйте обновить страницу.</div>'; }
        });
}
/* Init: показываем сетку узлов (7zap-стиль, пользователь выбирает сам);
   без дерева — полный перебор. */
(function(){
    var box = document.getElementById('vinCatalog'); if (!box) return;
    if (!vinCtxQuery()) return;                 // ни VIN, ни выбранного авто
    if (window.VX_PC) { vinLoadPcNodes(); return; }
    var firstCard = document.querySelector('.vin-node-card[data-cat]');
    if (!firstCard) vinLoadAll(null);   // дерева нет → полный перебор
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
