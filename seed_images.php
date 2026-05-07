<?php
/**
 * Seed demo images for parts (presentation mode).
 * Generates stylized SVG images per part and stores them in assets/uploads/parts/.
 * Updates parts.images JSON field with the generated filenames.
 *
 * Usage: php seed_images.php
 */
require_once __DIR__ . '/config/config.php';

$db       = getDB();
$partsDir = APP_ROOT . '/assets/uploads/parts/';
if (!is_dir($partsDir)) mkdir($partsDir, 0775, true);

// Color schemes per category slug
$schemes = [
    'dvigatel'           => ['#FF6B35', '#1A0E08'],
    'tormoznaya-sistema' => ['#E74C3C', '#1A0808'],
    'podveska'           => ['#3498DB', '#08121A'],
    'elektrika'          => ['#F39C12', '#1A1408'],
    'kuzov'              => ['#2ECC71', '#081A0E'],
    'transmissiya'       => ['#9B59B6', '#14081A'],
    'filtry'             => ['#FF6B35', '#1A0E08'],
    'remni-i-tsepi'      => ['#FF6B35', '#1A0E08'],
    'svechi-zazgiganiya' => ['#F39C12', '#1A1408'],
    'amortizatory'       => ['#3498DB', '#08121A'],
];

// Iconography per category (SVG paths, viewBox 0 0 120 120)
$icons = [
    'dvigatel'           => '<rect x="20" y="45" width="80" height="40" rx="3" fill="none" stroke="ACC" stroke-width="2"/><path d="M40 45V35M80 45V35M28 65h6M86 65h6M40 65h40" stroke="ACC" stroke-width="2" fill="none"/>',
    'tormoznaya-sistema' => '<circle cx="60" cy="60" r="35" fill="none" stroke="ACC" stroke-width="2"/><circle cx="60" cy="60" r="18" fill="none" stroke="ACC" stroke-width="2"/><path d="M60 25v8M60 87v8M25 60h8M87 60h8" stroke="ACC" stroke-width="2"/>',
    'podveska'           => '<path d="M30 90l16-50M90 90l-16-50M46 40h28" stroke="ACC" stroke-width="2" fill="none"/><circle cx="32" cy="92" r="6" fill="none" stroke="ACC" stroke-width="2"/><circle cx="88" cy="92" r="6" fill="none" stroke="ACC" stroke-width="2"/>',
    'elektrika'          => '<polyline points="68,15 25,75 60,75 55,105 95,45 60,45 68,15" fill="none" stroke="ACC" stroke-width="2"/>',
    'kuzov'              => '<path d="M25 80H15a4 4 0 01-4-4V40a4 4 0 014-4h90a4 4 0 014 4v36a4 4 0 01-4 4h-10" fill="none" stroke="ACC" stroke-width="2"/><path d="M25 80l8-30h54l8 30" fill="none" stroke="ACC" stroke-width="2"/><circle cx="35" cy="82" r="6" fill="none" stroke="ACC" stroke-width="2"/><circle cx="85" cy="82" r="6" fill="none" stroke="ACC" stroke-width="2"/>',
    'transmissiya'       => '<circle cx="30" cy="60" r="14" fill="none" stroke="ACC" stroke-width="2"/><circle cx="90" cy="60" r="14" fill="none" stroke="ACC" stroke-width="2"/><path d="M44 60h32M30 35V25M90 35V25M30 95V85M90 95V85" stroke="ACC" stroke-width="2"/>',
    'filtry'             => '<rect x="40" y="25" width="40" height="70" rx="4" fill="none" stroke="ACC" stroke-width="2"/><path d="M40 40h40M40 55h40M40 70h40M40 85h40" stroke="ACC" stroke-width="1.5"/>',
    'remni-i-tsepi'      => '<ellipse cx="60" cy="60" rx="42" ry="20" fill="none" stroke="ACC" stroke-width="2"/><ellipse cx="60" cy="60" rx="32" ry="13" fill="none" stroke="ACC" stroke-width="1.5"/><path d="M28 60h64" stroke="ACC" stroke-width="1" stroke-dasharray="3 3"/>',
    'svechi-zazgiganiya' => '<rect x="52" y="20" width="16" height="55" rx="2" fill="none" stroke="ACC" stroke-width="2"/><path d="M50 40h20M50 55h20" stroke="ACC" stroke-width="1.5"/><rect x="55" y="75" width="10" height="20" fill="none" stroke="ACC" stroke-width="2"/><path d="M60 95v8" stroke="ACC" stroke-width="2"/>',
    'amortizatory'       => '<rect x="48" y="20" width="24" height="80" rx="3" fill="none" stroke="ACC" stroke-width="2"/><path d="M48 35h24M48 50h24M48 65h24M48 80h24" stroke="ACC" stroke-width="1.5"/><circle cx="60" cy="105" r="5" fill="none" stroke="ACC" stroke-width="2"/>',
];

function hexToRgb(string $hex): array {
    $hex = ltrim($hex, '#');
    return [hexdec(substr($hex,0,2)), hexdec(substr($hex,2,2)), hexdec(substr($hex,4,2))];
}

function generateSvg(string $partNumber, string $brand, string $name, string $catSlug, array $scheme, string $iconPath): string {
    [$accent, $bgDark] = $scheme;
    [$r, $g, $b] = hexToRgb($accent);
    $glow = "rgba($r, $g, $b, 0.08)";
    $iconResolved = str_replace('ACC', $accent, $iconPath);
    $shortName = mb_strlen($name) > 36 ? mb_substr($name, 0, 33) . '…' : $name;
    $brandU = mb_strtoupper($brand);

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 450" width="600" height="450">
  <defs>
    <linearGradient id="bg" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%" stop-color="#0D0D0D"/>
      <stop offset="100%" stop-color="$bgDark"/>
    </linearGradient>
    <radialGradient id="glow" cx="0.7" cy="0.5" r="0.6">
      <stop offset="0%" stop-color="$glow"/>
      <stop offset="100%" stop-color="rgba(0,0,0,0)"/>
    </radialGradient>
    <pattern id="grid" width="30" height="30" patternUnits="userSpaceOnUse">
      <path d="M 30 0 L 0 0 0 30" fill="none" stroke="rgba(255,255,255,0.025)" stroke-width="1"/>
    </pattern>
  </defs>
  <rect width="600" height="450" fill="url(#bg)"/>
  <rect width="600" height="450" fill="url(#grid)"/>
  <rect width="600" height="450" fill="url(#glow)"/>

  <!-- corner brackets -->
  <path d="M 24 24 L 24 60 M 24 24 L 60 24" stroke="$accent" stroke-width="2" fill="none" opacity="0.7"/>
  <path d="M 576 24 L 576 60 M 576 24 L 540 24" stroke="$accent" stroke-width="2" fill="none" opacity="0.7"/>
  <path d="M 24 426 L 24 390 M 24 426 L 60 426" stroke="$accent" stroke-width="2" fill="none" opacity="0.7"/>
  <path d="M 576 426 L 576 390 M 576 426 L 540 426" stroke="$accent" stroke-width="2" fill="none" opacity="0.7"/>

  <!-- top mono label -->
  <text x="40" y="55" font-family="JetBrains Mono, monospace" font-size="11" fill="rgba(255,255,255,0.45)" letter-spacing="2">// AUTO PART CATALOG</text>
  <text x="560" y="55" text-anchor="end" font-family="JetBrains Mono, monospace" font-size="11" fill="$accent" letter-spacing="2">$brandU</text>

  <!-- centered icon -->
  <g transform="translate(180 95) scale(2)">$iconResolved</g>

  <!-- huge part number -->
  <text x="300" y="380" text-anchor="middle" font-family="Bebas Neue, Impact, sans-serif" font-size="58" fill="#F0F0F0" letter-spacing="6">$partNumber</text>

  <!-- name -->
  <text x="300" y="412" text-anchor="middle" font-family="Inter, system-ui, sans-serif" font-size="14" fill="rgba(255,255,255,0.55)" letter-spacing="1">$shortName</text>
</svg>
SVG;
}

// Fetch all active parts
$rows = $db->query("SELECT p.id, p.part_number, p.name, p.images,
                           b.name AS brand_name,
                           c.slug AS cat_slug
                    FROM parts p
                    LEFT JOIN brands b ON b.id = p.brand_id
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE p.is_active = 1
                    ORDER BY p.id")->fetchAll();

$updated = 0;
$skipped = 0;

foreach ($rows as $p) {
    $existing = json_decode($p['images'] ?? '[]', true) ?: [];
    if (!empty($existing)) {
        $skipped++;
        echo "[skip] #{$p['id']} {$p['part_number']} (already has images)\n";
        continue;
    }

    $catSlug = $p['cat_slug'] ?? 'dvigatel';
    $scheme  = $schemes[$catSlug] ?? $schemes['dvigatel'];
    $icon    = $icons[$catSlug]  ?? $icons['dvigatel'];

    $svg     = generateSvg($p['part_number'], $p['brand_name'] ?? '', $p['name'], $catSlug, $scheme, $icon);
    $fname   = 'demo_' . preg_replace('/[^A-Za-z0-9_]/', '_', $p['part_number']) . '.svg';
    file_put_contents($partsDir . $fname, $svg);

    $db->prepare("UPDATE parts SET images = ? WHERE id = ?")
       ->execute([json_encode([$fname]), $p['id']]);

    echo "[ok]   #{$p['id']} {$p['part_number']}  →  $fname\n";
    $updated++;
}

echo "\nDone. Updated: $updated, skipped (already had images): $skipped\n";
