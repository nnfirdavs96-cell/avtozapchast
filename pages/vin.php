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
        // External catalog (PartsAPI) is loaded asynchronously via api/vin_catalog.php
        // because it scans many product groups and may take a few seconds.
    }
}

$totalCompat = array_sum(array_column($facets, 'cnt'));

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
.vx .container{max-width:1160px;}
.vx a{text-decoration:none;}

/* promo */
.vx-promo{margin:6px 0 4px;border-radius:var(--vx-radius);overflow:hidden;color:#fff;
    background:linear-gradient(100deg,#7a0608 0%,var(--vx-red) 48%,#e83b40 100%);
    display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 28px;box-shadow:var(--vx-shadow);}
.vx-promo h3{margin:0;font-size:1.5rem;font-weight:800;}
.vx-promo p{margin:4px 0 0;opacity:.92;font-size:.92rem;}
.vx-promo .badge{background:#fff;color:var(--vx-red);font-weight:800;padding:8px 16px;border-radius:30px;font-size:.92rem;white-space:nowrap;}

/* hero */
.vx-hero{text-align:center;padding:26px 0 4px;}
.vx-hero h1{font-size:2rem;margin:0 0 8px;color:var(--vx-ink);font-weight:800;display:flex;gap:12px;align-items:center;justify-content:center;}
.vx-hero h1 svg{color:var(--vx-red);}
.vx-hero p{color:var(--vx-muted);max-width:640px;margin:0 auto;font-size:.98rem;}

/* search card */
.vx-scard{background:var(--vx-ink);color:#fff;border-radius:18px;max-width:760px;margin:18px auto 6px;padding:24px 26px;box-shadow:0 18px 50px rgba(17,20,30,.25);}
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
            <div class="vx-params">
                <select id="vxMk" onchange="vxOnMake()"><option value=""><?= t('vin_marka') ?></option></select>
                <select id="vxMd" onchange="vxOnModel()" disabled><option value=""><?= t('vin_model_lbl') ?></option></select>
                <select id="vxYr" disabled><option value=""><?= t('vin_year_lbl') ?></option></select>
                <button type="button" class="vx-btn" onclick="vxParamsSubmit()"><?= t('vin_pick') ?></button>
            </div>
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
    <div style="max-width:900px;margin:30px auto 0;">

        <div style="background:#fff;border-radius:12px;box-shadow:var(--vx-shadow);overflow:hidden;margin-bottom:28px;">
            <?php $carImgUrl = getCarImageUrl($result['make'] ?? '', $result['model'] ?? '', (int)($result['year'] ?? 0)); ?>
            <div style="position:relative;height:260px;background:var(--vx-ink);overflow:hidden;">
                <img id="carImg" src="<?= sanitize($carImgUrl) ?>"
                     alt="<?= sanitize(($result['make'] ?? '') . ' ' . ($result['model'] ?? '')) ?>"
                     style="width:100%;height:100%;object-fit:cover;object-position:center;"
                     onerror="this.style.display='none';document.getElementById('carImgFallback').style.display='flex';">
                <div id="carImgFallback" style="display:none;position:absolute;inset:0;background:linear-gradient(135deg,#181a1f 0%,#2d3561 100%);align-items:center;justify-content:center;flex-direction:column;gap:12px;">
                    <svg width="180" height="80" viewBox="0 0 180 80" fill="none" opacity="0.25">
                        <path d="M12 54 L20 28 Q26 18 42 17 L80 14 Q100 13 116 17 L142 28 L168 54 L170 62 L10 62 Z" fill="white"/>
                        <circle cx="40" cy="64" r="12" fill="white"/><circle cx="140" cy="64" r="12" fill="white"/>
                        <rect x="26" y="42" width="128" height="18" rx="3" fill="#181a1f"/>
                    </svg>
                    <span style="color:rgba(255,255,255,0.35);font-size:0.85rem;">Фото недоступно</span>
                </div>
                <div style="position:absolute;inset:0;background:linear-gradient(to top,rgba(10,10,30,0.88) 0%,rgba(10,10,30,0.15) 55%,transparent 100%);pointer-events:none;"></div>
                <div style="position:absolute;top:14px;right:16px;background:rgba(0,0,0,0.55);border-radius:8px;padding:7px 14px;font-family:monospace;font-size:0.95rem;letter-spacing:2px;color:#fff;font-weight:700;border:1px solid rgba(255,255,255,0.12);">
                    <?= sanitize($vin) ?>
                </div>
                <div style="position:absolute;top:14px;left:16px;display:flex;gap:6px;">
                    <?php if (!empty($result['from_cache'])): ?>
                    <span style="background:rgba(0,0,0,0.5);padding:4px 10px;border-radius:20px;font-size:0.72rem;color:rgba(255,255,255,0.8);"><i class="fa fa-database"></i> Кэш</span>
                    <?php endif; ?>
                    <span style="background:rgba(199,9,9,0.78);padding:4px 10px;border-radius:20px;font-size:0.72rem;color:#fff;"><i class="fa fa-plug"></i> <?= sanitize(strtoupper($result['source'] ?? 'local')) ?></span>
                </div>
                <div style="position:absolute;bottom:18px;left:24px;color:#fff;text-shadow:0 2px 8px rgba(0,0,0,0.5);">
                    <div style="font-size:2rem;font-weight:900;line-height:1.1;"><?= sanitize(($result['make'] ?? '') . ' ' . ($result['model'] ?? '')) ?></div>
                    <div style="font-size:0.95rem;opacity:0.75;margin-top:5px;">
                        <?= sanitize($result['year'] > 0 ? $result['year'] . ' г.' : '') ?>
                        <?= sanitize(!empty($result['country']) ? ' · ' . $result['country'] : '') ?>
                    </div>
                </div>
            </div>

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
                        <div style="font-size:0.72rem;color:#aaa;text-transform:uppercase;letter-spacing:1px;margin-bottom:4px;"><i class="fa <?= $icon ?>"></i> <?= $label ?></div>
                        <div style="font-weight:600;color:var(--vx-ink);font-size:0.95rem;"><?= sanitize((string)$val) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

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

        <!-- ── External catalog (PartsAPI): nodes + crosses bridge ─────── -->
        <?php if ($catalogEnabled):
            $oemNodes = Catalog::provider()->oemNodes();
            $catType  = trim(getSetting('catalog_api_type', 'oem'));
            $showTree = !empty($oemNodes);
        ?>
        <style>
            /* ── Дерево узлов: боковая панель «Узлы» + сетка карточек (как на макете) ── */
            .vin-tree{display:flex;gap:18px;align-items:flex-start;margin-bottom:26px;flex-wrap:wrap;}
            .vin-tree-side{flex:0 0 190px;background:#eef0f3;border-radius:14px;padding:16px;align-self:stretch;}
            .vin-tree-pill{display:inline-flex;align-items:center;gap:6px;background:var(--vx-red,#C70909);color:#fff;font-size:0.76rem;font-weight:700;padding:6px 16px;border-radius:16px;margin-bottom:12px;text-transform:uppercase;letter-spacing:.5px;}
            .vin-tree-side ul{list-style:none;margin:0;padding:0;}
            .vin-tree-side li{position:relative;padding:7px 8px 7px 18px;font-size:0.88rem;color:#39414d;cursor:pointer;border-radius:7px;transition:.12s;}
            .vin-tree-side li:before{content:'';position:absolute;left:5px;top:14px;width:5px;height:5px;border-radius:50%;background:#9aa3af;}
            .vin-tree-side li:hover{color:var(--vx-red,#C70909);background:#fff;}
            .vin-tree-cards{flex:1;min-width:200px;display:grid;grid-template-columns:repeat(auto-fill,minmax(130px,158px));gap:10px;align-content:start;justify-content:start;}
            .vin-node-card{text-align:left;background:#fff;border:1px solid #e7e9ee;border-radius:12px;padding:13px 14px;min-height:58px;font-size:0.86rem;font-weight:700;color:#1d2129;cursor:pointer;transition:.15s;display:flex;align-items:center;}
            .vin-node-card:hover{border-color:var(--vx-red,#C70909);box-shadow:0 4px 14px rgba(199,9,9,.10);transform:translateY(-1px);}
            .vin-node-card.active{border-color:var(--vx-red,#C70909);background:#fff5f5;color:var(--vx-red,#C70909);}
            .vin-node-card.all{align-items:center;justify-content:center;color:#fff;background:var(--vx-ink,#181a1f);border-color:var(--vx-ink,#181a1f);gap:6px;}
            .vin-node-card.all:hover{filter:brightness(1.15);color:#fff;}
            /* ── Карточки деталей (фото + крупная цена + «В корзину», как на макете) ── */
            .vin-grp{font-size:0.8rem;font-weight:700;color:#888;text-transform:uppercase;letter-spacing:.5px;margin:0 0 12px;border-bottom:2px solid #f0f1f5;padding-bottom:6px;}
            .vin-pgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(290px,1fr));gap:14px;}
            .vin-pcard{background:#fff;border:1px solid #e7e9ee;border-radius:14px;padding:16px;display:flex;flex-direction:column;}
            .vin-pcard-t{font-weight:700;color:#1d2129;font-size:0.9rem;line-height:1.3;}
            .vin-pcard-t span{display:block;font-weight:400;font-size:0.72rem;color:#9aa3af;font-family:monospace;margin-top:3px;}
            .vin-pcard-b{display:flex;gap:12px;margin-top:12px;align-items:stretch;}
            .vin-pcard-ph{flex:0 0 84px;background:#eef0f3;border-radius:10px;display:flex;align-items:center;justify-content:center;color:#b6bcc6;font-size:0.72rem;min-height:84px;}
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
        </style>
        <div id="vinCatalog" data-vin="<?= sanitize($vin) ?>" data-type="<?= sanitize($catType) ?>" style="margin-top:8px;">
            <h2 style="font-size:1.3rem;font-weight:700;margin:8px 0 16px;color:var(--vx-ink);">
                <i class="fa fa-book" style="color:var(--vx-red);"></i> Оригинальный каталог по VIN
                <span id="vinCatalogCount" style="color:#888;font-weight:400;font-size:0.9rem;"></span>
            </h2>
            <?php if ($showTree): ?>
            <div class="vin-tree">
                <aside class="vin-tree-side">
                    <span class="vin-tree-pill"><i class="fa fa-sitemap"></i> Узлы</span>
                    <ul>
                        <?php foreach ($oemNodes as $n): ?>
                        <li onclick="vinPickNode(<?= (int)$n['cat'] ?>)"><?= sanitize($n['name']) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </aside>
                <div class="vin-tree-cards">
                    <?php foreach ($oemNodes as $n): ?>
                    <button type="button" class="vin-node-card" data-cat="<?= (int)$n['cat'] ?>" onclick="vinLoadNode(<?= (int)$n['cat'] ?>, this)"><?= sanitize($n['name']) ?></button>
                    <?php endforeach; ?>
                    <button type="button" class="vin-node-card all" onclick="vinLoadAll(this)" title="Перебрать все группы (дольше, расходует лимит ключа)"><i class="fa fa-th-large"></i> Все узлы</button>
                </div>
            </div>
            <?php endif; ?>
            <div id="vinCatalogStatus" style="background:#fff;border-radius:10px;padding:22px 24px;text-align:center;color:#888;box-shadow:0 2px 10px rgba(0,0,0,0.06);margin-bottom:32px;">
                <i class="fa fa-spinner fa-spin" style="font-size:1.6rem;color:#C70909;"></i>
                <div style="margin-top:10px;font-size:0.9rem;">Подбираем запчасти по VIN из оригинального каталога…</div>
                <div style="margin-top:4px;font-size:0.78rem;color:#bbb;">Это может занять несколько секунд.</div>
            </div>
            <div id="vinCatalogBody" style="overflow-x:auto;"></div>
        </div>
        <?php endif; ?>

    </div><!-- /max-width -->

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
    var mk = document.getElementById('vxMk');
    if (mk) { mk.value = b; vxOnMake(); }
    document.querySelector('.vx-scard').scrollIntoView({behavior:'smooth'});
}

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

/* ── External catalog (PartsAPI): per-node loading + crosses bridge ── */
function jsAttr(s){ return String(s == null ? '' : s).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,''); }
function vinSetActiveChip(btn){ document.querySelectorAll('.vin-node-card').forEach(function(c){ c.classList.remove('active'); }); if (btn) btn.classList.add('active'); }
function vinLoadNode(cat, btn){ vinSetActiveChip(btn); vinCatalogFetch('&cat=' + encodeURIComponent(cat)); }
function vinLoadAll(btn){ vinSetActiveChip(btn); vinCatalogFetch(''); }
/* Клик по узлу в боковом списке → имитируем клик по соответствующей карточке. */
function vinPickNode(cat){ var c = document.querySelector('.vin-node-card[data-cat="' + cat + '"]'); if (c) c.click(); }
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
function vinRenderCatalog(d){
    var statusEl = document.getElementById('vinCatalogStatus'), bodyEl = document.getElementById('vinCatalogBody'), countEl = document.getElementById('vinCatalogCount');
    if (d.rate_limited) { statusEl.style.display = 'block'; statusEl.innerHTML = '<div style="font-size:0.9rem;color:#b8860b;"><i class="fa fa-clock-o"></i> Каталог временно недоступен: превышен суточный лимит запросов. Попробуйте позже.</div>'; return; }
    if (!d.success || !d.items || d.items.length === 0) { statusEl.style.display = 'block'; statusEl.innerHTML = '<div style="font-size:0.9rem;">В этом узле по данному VIN запчасти не найдены. Выберите другой узел или нажмите «Все узлы».</div>'; return; }
    statusEl.style.display = 'none'; countEl.textContent = '(' + d.count + ')';
    var groups = {};
    d.items.forEach(function(it){ var g = it.group || 'Прочее'; (groups[g] = groups[g] || []).push(it); });
    var html = '', idx = 0;
    Object.keys(groups).forEach(function(g){
        html += '<div style="margin-bottom:26px;"><div class="vin-grp">' + escapeHtml(g) + '</div><div class="vin-pgrid">';
        groups[g].forEach(function(it){
            idx++; var cid = 'vinc-' + idx;
            html += '<div class="vin-pcard" id="' + cid + '">';
            html += '<div class="vin-pcard-t">' + escapeHtml(it.name) + '<span>' + escapeHtml(it.brand || '') + ' · ' + escapeHtml(it.part_number) + '</span></div>';
            html += '<div class="vin-pcard-b"><div class="vin-pcard-ph">фото</div><div class="vin-pcard-buy">';
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
    html += '<p style="text-align:center;color:#bbb;font-size:0.76rem;margin:6px 0 32px;"><i class="fa fa-plug"></i> Оригинальный каталог TecDoc / PartsAPI' + (d.from_cache ? ' · из кэша' : '') + '</p>';
    bodyEl.innerHTML = html;
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

/* Init: auto-load first node (1 request) or full scan for non-original. */
(function(){
    var box = document.getElementById('vinCatalog'); if (!box) return;
    var vin = box.getAttribute('data-vin') || ''; if (vin.length !== 17) return;
    var firstCard = document.querySelector('.vin-node-card[data-cat]');
    if (firstCard) { vinLoadNode(firstCard.getAttribute('data-cat'), firstCard); }
    else           { vinLoadAll(null); }
})();
</script>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
