<?php
/**
 * Seed photo-realistic SVG images for parts (presentation mode).
 * Generates detailed 3D-style SVG renders per part type and stores them in
 * assets/uploads/parts/. Updates parts.images JSON field with the filenames.
 *
 * Usage: php seed_images.php
 */
require_once __DIR__ . '/config/config.php';

$db       = getDB();
$partsDir = APP_ROOT . '/assets/uploads/parts/';
if (!is_dir($partsDir)) mkdir($partsDir, 0775, true);

/* ─────────────────────────────────────────────────────────────────────
 * Background helper — dark studio backdrop with subtle radial light
 * ───────────────────────────────────────────────────────────────────── */
function svgWrap(string $body, string $partNumber, string $brandU): string {
    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 600 450" width="600" height="450">
  <defs>
    <radialGradient id="studio" cx="0.5" cy="0.4" r="0.7">
      <stop offset="0%"  stop-color="#262626"/>
      <stop offset="60%" stop-color="#141414"/>
      <stop offset="100%" stop-color="#080808"/>
    </radialGradient>
    <radialGradient id="floor" cx="0.5" cy="1" r="0.6">
      <stop offset="0%" stop-color="rgba(255,107,53,0.07)"/>
      <stop offset="100%" stop-color="rgba(0,0,0,0)"/>
    </radialGradient>
    <linearGradient id="metal" x1="0" y1="0" x2="1" y2="1">
      <stop offset="0%"  stop-color="#cfcfcf"/>
      <stop offset="35%" stop-color="#888"/>
      <stop offset="70%" stop-color="#3a3a3a"/>
      <stop offset="100%" stop-color="#1a1a1a"/>
    </linearGradient>
    <linearGradient id="metal2" x1="0" y1="1" x2="1" y2="0">
      <stop offset="0%"  stop-color="#1f1f1f"/>
      <stop offset="50%" stop-color="#6b6b6b"/>
      <stop offset="100%" stop-color="#a8a8a8"/>
    </linearGradient>
    <linearGradient id="orange" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"  stop-color="#FF8B55"/>
      <stop offset="50%" stop-color="#FF6B35"/>
      <stop offset="100%" stop-color="#B83B0E"/>
    </linearGradient>
    <linearGradient id="rubber" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"  stop-color="#3a3a3a"/>
      <stop offset="50%" stop-color="#1a1a1a"/>
      <stop offset="100%" stop-color="#0a0a0a"/>
    </linearGradient>
    <linearGradient id="ceramic" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"  stop-color="#fff7e8"/>
      <stop offset="60%" stop-color="#e8d4a8"/>
      <stop offset="100%" stop-color="#8a6d3f"/>
    </linearGradient>
    <linearGradient id="copper" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%" stop-color="#f0a878"/>
      <stop offset="100%" stop-color="#7a3f1c"/>
    </linearGradient>
    <radialGradient id="hole" cx="0.5" cy="0.5" r="0.5">
      <stop offset="0%" stop-color="#000"/>
      <stop offset="80%" stop-color="#000"/>
      <stop offset="100%" stop-color="#1a1a1a"/>
    </radialGradient>
    <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
      <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(255,255,255,0.02)" stroke-width="1"/>
    </pattern>
    <filter id="dropshadow" x="-50%" y="-50%" width="200%" height="200%">
      <feGaussianBlur in="SourceAlpha" stdDeviation="6"/>
      <feOffset dx="0" dy="14" result="offsetblur"/>
      <feComponentTransfer><feFuncA type="linear" slope="0.6"/></feComponentTransfer>
      <feMerge><feMergeNode/><feMergeNode in="SourceGraphic"/></feMerge>
    </filter>
  </defs>

  <rect width="600" height="450" fill="url(#studio)"/>
  <rect width="600" height="450" fill="url(#grid)"/>
  <rect width="600" height="450" fill="url(#floor)"/>

  <!-- corner brackets -->
  <path d="M 24 24 L 24 60 M 24 24 L 60 24" stroke="#FF6B35" stroke-width="2" fill="none" opacity="0.6"/>
  <path d="M 576 24 L 576 60 M 576 24 L 540 24" stroke="#FF6B35" stroke-width="2" fill="none" opacity="0.6"/>
  <path d="M 24 426 L 24 390 M 24 426 L 60 426" stroke="#FF6B35" stroke-width="2" fill="none" opacity="0.6"/>
  <path d="M 576 426 L 576 390 M 576 426 L 540 426" stroke="#FF6B35" stroke-width="2" fill="none" opacity="0.6"/>

  <!-- top labels -->
  <text x="40" y="55" font-family="JetBrains Mono, monospace" font-size="11" fill="rgba(255,255,255,0.4)" letter-spacing="2">// ORIGINAL PART</text>
  <text x="560" y="55" text-anchor="end" font-family="JetBrains Mono, monospace" font-size="11" fill="#FF6B35" letter-spacing="2">$brandU</text>

  <!-- floor reflection -->
  <ellipse cx="300" cy="395" rx="180" ry="14" fill="rgba(0,0,0,0.5)"/>

  <!-- the part itself -->
  <g filter="url(#dropshadow)">$body</g>

  <!-- bottom badges -->
  <text x="300" y="425" text-anchor="middle" font-family="JetBrains Mono, monospace" font-size="14" fill="rgba(255,255,255,0.7)" letter-spacing="3">$partNumber</text>
</svg>
SVG;
}

/* ─────────────────────────────────────────────────────────────────────
 * Detailed part renderers
 * ───────────────────────────────────────────────────────────────────── */

// Brake disc + caliper (тормозной диск + суппорт)
function renderBrake(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- outer disc -->
  <circle cx="0" cy="0" r="135" fill="url(#metal)" stroke="#0a0a0a" stroke-width="2"/>
  <circle cx="0" cy="0" r="135" fill="none" stroke="rgba(255,255,255,0.15)" stroke-width="1"/>
  <!-- friction surface ring -->
  <circle cx="0" cy="0" r="118" fill="#2a2a2a"/>
  <circle cx="0" cy="0" r="118" fill="url(#metal2)" opacity="0.5"/>
  <!-- radial slots -->
  <g stroke="#0a0a0a" stroke-width="2" fill="none" opacity="0.7">
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(0)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(30)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(60)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(90)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(120)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(150)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(180)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(210)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(240)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(270)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(300)"/>
    <line x1="-115" y1="0" x2="-70" y2="0" transform="rotate(330)"/>
  </g>
  <!-- inner hub -->
  <circle cx="0" cy="0" r="62" fill="url(#metal2)" stroke="#0a0a0a" stroke-width="2"/>
  <circle cx="0" cy="0" r="55" fill="#3a3a3a"/>
  <!-- bolt holes -->
  <g fill="url(#hole)">
    <circle cx="0" cy="-42" r="6"/>
    <circle cx="40" cy="-13" r="6"/>
    <circle cx="25" cy="34" r="6"/>
    <circle cx="-25" cy="34" r="6"/>
    <circle cx="-40" cy="-13" r="6"/>
  </g>
  <!-- center hole -->
  <circle cx="0" cy="0" r="15" fill="url(#hole)"/>
  <!-- highlight -->
  <ellipse cx="-50" cy="-80" rx="40" ry="20" fill="rgba(255,255,255,0.18)" transform="rotate(-30)"/>
</g>
<!-- caliper -->
<g transform="translate(160 220)">
  <rect x="-30" y="-65" width="60" height="130" rx="10" fill="url(#orange)" stroke="#5a1f08" stroke-width="2"/>
  <rect x="-22" y="-55" width="44" height="110" rx="6" fill="#cc4a1a" opacity="0.6"/>
  <text x="0" y="8" text-anchor="middle" font-family="Bebas Neue, Impact" font-size="22" fill="#fff" letter-spacing="2">BREMBO</text>
  <ellipse cx="-12" cy="-45" rx="10" ry="6" fill="rgba(255,255,255,0.3)"/>
</g>
S;
}

// Spark plug (свеча зажигания)
function renderSparkPlug(): string {
    return <<<S
<g transform="translate(300 100)">
  <!-- terminal nut -->
  <rect x="-22" y="0" width="44" height="36" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <rect x="-22" y="0" width="44" height="6" fill="rgba(255,255,255,0.3)"/>
  <!-- ceramic insulator with ribs -->
  <path d="M -28 36 L 28 36 L 26 60 L -26 60 Z" fill="url(#ceramic)" stroke="#5a4520" stroke-width="1"/>
  <path d="M -30 60 L 30 60 L 28 80 L -28 80 Z" fill="url(#ceramic)" stroke="#5a4520" stroke-width="1"/>
  <path d="M -32 80 L 32 80 L 30 100 L -30 100 Z" fill="url(#ceramic)" stroke="#5a4520" stroke-width="1"/>
  <path d="M -34 100 L 34 100 L 32 120 L -32 120 Z" fill="url(#ceramic)" stroke="#5a4520" stroke-width="1"/>
  <!-- ceramic highlight -->
  <path d="M -20 38 Q -24 78 -22 118" stroke="rgba(255,255,255,0.4)" stroke-width="3" fill="none"/>
  <!-- hex metal body -->
  <polygon points="-30,120 30,120 38,140 30,160 -30,160 -38,140" fill="url(#metal2)" stroke="#000" stroke-width="1"/>
  <text x="0" y="148" text-anchor="middle" font-family="JetBrains Mono" font-size="11" fill="#000">NGK</text>
  <!-- threaded shaft -->
  <rect x="-18" y="160" width="36" height="80" fill="url(#metal)"/>
  <g stroke="#000" stroke-width="1" opacity="0.5">
    <line x1="-18" y1="168" x2="18" y2="168"/>
    <line x1="-18" y1="176" x2="18" y2="176"/>
    <line x1="-18" y1="184" x2="18" y2="184"/>
    <line x1="-18" y1="192" x2="18" y2="192"/>
    <line x1="-18" y1="200" x2="18" y2="200"/>
    <line x1="-18" y1="208" x2="18" y2="208"/>
    <line x1="-18" y1="216" x2="18" y2="216"/>
    <line x1="-18" y1="224" x2="18" y2="224"/>
    <line x1="-18" y1="232" x2="18" y2="232"/>
  </g>
  <!-- center electrode -->
  <rect x="-3" y="240" width="6" height="22" fill="url(#metal)"/>
  <!-- ground electrode (L-shape) -->
  <path d="M 12 240 L 12 268 L -3 268 L -3 262" stroke="#666" stroke-width="4" fill="none"/>
  <!-- spark -->
  <line x1="0" y1="262" x2="-2" y2="266" stroke="#FF6B35" stroke-width="2"/>
  <line x1="2" y1="263" x2="-1" y2="267" stroke="#FF6B35" stroke-width="1"/>
</g>
S;
}

// Timing belt (ремень ГРМ)
function renderTimingBelt(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- outer belt loop -->
  <path d="M -210 0 Q -210 -85 -130 -85 L 130 -85 Q 210 -85 210 0 Q 210 85 130 85 L -130 85 Q -210 85 -210 0 Z"
        fill="url(#rubber)" stroke="#000" stroke-width="2"/>
  <!-- inner cutout -->
  <path d="M -180 0 Q -180 -55 -130 -55 L 130 -55 Q 180 -55 180 0 Q 180 55 130 55 L -130 55 Q -180 55 -180 0 Z"
        fill="#0a0a0a"/>
  <!-- belt teeth (top and bottom) -->
  <g fill="#0a0a0a">
    <rect x="-145" y="-80" width="14" height="22"/>
    <rect x="-115" y="-80" width="14" height="22"/>
    <rect x="-85"  y="-80" width="14" height="22"/>
    <rect x="-55"  y="-80" width="14" height="22"/>
    <rect x="-25"  y="-80" width="14" height="22"/>
    <rect x="5"    y="-80" width="14" height="22"/>
    <rect x="35"   y="-80" width="14" height="22"/>
    <rect x="65"   y="-80" width="14" height="22"/>
    <rect x="95"   y="-80" width="14" height="22"/>
    <rect x="125"  y="-80" width="14" height="22"/>
    <rect x="-145" y="58"  width="14" height="22"/>
    <rect x="-115" y="58"  width="14" height="22"/>
    <rect x="-85"  y="58"  width="14" height="22"/>
    <rect x="-55"  y="58"  width="14" height="22"/>
    <rect x="-25"  y="58"  width="14" height="22"/>
    <rect x="5"    y="58"  width="14" height="22"/>
    <rect x="35"   y="58"  width="14" height="22"/>
    <rect x="65"   y="58"  width="14" height="22"/>
    <rect x="95"   y="58"  width="14" height="22"/>
    <rect x="125"  y="58"  width="14" height="22"/>
  </g>
  <!-- highlight -->
  <path d="M -200 -10 Q -200 -75 -135 -75 L 130 -75" stroke="rgba(255,255,255,0.15)" stroke-width="2" fill="none"/>
  <!-- branded text -->
  <text x="0" y="6" text-anchor="middle" font-family="Bebas Neue, Impact" font-size="28" fill="#FF6B35" letter-spacing="6">GATES</text>
  <text x="0" y="28" text-anchor="middle" font-family="JetBrains Mono" font-size="10" fill="rgba(255,255,255,0.5)">POWERGRIP HSN</text>
</g>
S;
}

// Bearing (подшипник)
function renderBearing(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- outer ring -->
  <circle cx="0" cy="0" r="140" fill="url(#metal)" stroke="#000" stroke-width="2"/>
  <circle cx="0" cy="0" r="140" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
  <circle cx="0" cy="0" r="118" fill="#1a1a1a"/>
  <!-- balls -->
  <g>
    <circle cx="0"   cy="-100" r="18" fill="url(#metal)" stroke="#000" stroke-width="1"/>
    <circle cx="71"  cy="-71"  r="18" fill="url(#metal)" stroke="#000" stroke-width="1"/>
    <circle cx="100" cy="0"    r="18" fill="url(#metal)" stroke="#000" stroke-width="1"/>
    <circle cx="71"  cy="71"   r="18" fill="url(#metal)" stroke="#000" stroke-width="1"/>
    <circle cx="0"   cy="100"  r="18" fill="url(#metal)" stroke="#000" stroke-width="1"/>
    <circle cx="-71" cy="71"   r="18" fill="url(#metal)" stroke="#000" stroke-width="1"/>
    <circle cx="-100" cy="0"   r="18" fill="url(#metal)" stroke="#000" stroke-width="1"/>
    <circle cx="-71" cy="-71"  r="18" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  </g>
  <!-- ball highlights -->
  <g fill="rgba(255,255,255,0.6)">
    <circle cx="-5"   cy="-105" r="5"/>
    <circle cx="66"   cy="-76"  r="5"/>
    <circle cx="95"   cy="-5"   r="5"/>
    <circle cx="66"   cy="66"   r="5"/>
    <circle cx="-5"   cy="95"   r="5"/>
    <circle cx="-76"  cy="66"   r="5"/>
    <circle cx="-105" cy="-5"   r="5"/>
    <circle cx="-76"  cy="-76"  r="5"/>
  </g>
  <!-- inner ring -->
  <circle cx="0" cy="0" r="78" fill="url(#metal2)" stroke="#000" stroke-width="2"/>
  <circle cx="0" cy="0" r="58" fill="url(#hole)"/>
  <!-- engraving -->
  <text x="0" y="-32" text-anchor="middle" font-family="JetBrains Mono" font-size="9" fill="rgba(255,255,255,0.5)" letter-spacing="2">SKF</text>
  <!-- highlight -->
  <ellipse cx="-50" cy="-100" rx="60" ry="18" fill="rgba(255,255,255,0.2)" transform="rotate(-30)"/>
</g>
S;
}

// Brake pads (тормозные колодки)
function renderBrakePads(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- back plate (left) -->
  <g transform="translate(-95 0)">
    <path d="M -80 -50 L 80 -55 L 80 50 L -80 55 Z" fill="url(#metal)" stroke="#000" stroke-width="2"/>
    <!-- friction pad -->
    <path d="M -80 -50 L 80 -55 L 80 50 L -80 55 Z" fill="none"/>
    <path d="M -75 -45 L 75 -50 L 75 45 L -75 50 Z" fill="#2a2a2a" stroke="#000" stroke-width="1"/>
    <!-- pad surface texture -->
    <path d="M -75 -45 L 75 -50 L 75 45 L -75 50 Z" fill="url(#orange)" opacity="0.2"/>
    <!-- chamfer slot -->
    <line x1="-60" y1="-40" x2="-60" y2="40" stroke="#000" stroke-width="2"/>
    <line x1="60" y1="-40" x2="60" y2="40" stroke="#000" stroke-width="2"/>
    <text x="0" y="6" text-anchor="middle" font-family="Bebas Neue" font-size="20" fill="rgba(255,255,255,0.4)" letter-spacing="3">BREMBO</text>
  </g>
  <!-- back plate (right, slightly rotated) -->
  <g transform="translate(95 0) rotate(8)">
    <path d="M -80 -50 L 80 -55 L 80 50 L -80 55 Z" fill="url(#metal2)" stroke="#000" stroke-width="2"/>
    <path d="M -75 -45 L 75 -50 L 75 45 L -75 50 Z" fill="#2a2a2a" stroke="#000" stroke-width="1"/>
    <path d="M -75 -45 L 75 -50 L 75 45 L -75 50 Z" fill="url(#orange)" opacity="0.15"/>
    <line x1="-60" y1="-40" x2="-60" y2="40" stroke="#000" stroke-width="2"/>
    <line x1="60" y1="-40" x2="60" y2="40" stroke="#000" stroke-width="2"/>
  </g>
  <!-- highlights -->
  <ellipse cx="-130" cy="-40" rx="40" ry="6" fill="rgba(255,255,255,0.25)"/>
</g>
S;
}

// Shock absorber (амортизатор)
function renderShock(): string {
    return <<<S
<g transform="translate(300 100)">
  <!-- top mount -->
  <ellipse cx="0" cy="10" rx="40" ry="10" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <rect x="-40" y="0" width="80" height="20" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <ellipse cx="0" cy="0" rx="40" ry="10" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <circle cx="0" cy="0" r="6" fill="url(#hole)"/>
  <!-- piston rod -->
  <rect x="-9" y="20" width="18" height="50" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <!-- spring helix -->
  <g stroke="url(#orange)" stroke-width="14" fill="none" stroke-linecap="round" opacity="0.95">
    <path d="M -55 80 C -55 80 55 90 55 100"/>
    <path d="M 55 100 C 55 100 -55 110 -55 120"/>
    <path d="M -55 120 C -55 120 55 130 55 140"/>
    <path d="M 55 140 C 55 140 -55 150 -55 160"/>
    <path d="M -55 160 C -55 160 55 170 55 180"/>
    <path d="M 55 180 C 55 180 -55 190 -55 200"/>
    <path d="M -55 200 C -55 200 55 210 55 220"/>
  </g>
  <g stroke="rgba(255,255,255,0.3)" stroke-width="2" fill="none" stroke-linecap="round">
    <path d="M -50 78 C -50 78 50 88 50 98"/>
    <path d="M -50 158 C -50 158 50 168 50 178"/>
  </g>
  <!-- shock body cylinder -->
  <rect x="-22" y="70" width="44" height="170" fill="url(#metal2)" stroke="#000" stroke-width="2"/>
  <rect x="-22" y="70" width="6" height="170" fill="rgba(255,255,255,0.15)"/>
  <text x="0" y="170" text-anchor="middle" font-family="Bebas Neue" font-size="14" fill="#FF6B35" letter-spacing="2" transform="rotate(90 0 170)">MONROE</text>
  <!-- bottom mount -->
  <ellipse cx="0" cy="240" rx="22" ry="6" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <rect x="-10" y="240" width="20" height="22" fill="url(#metal)"/>
  <circle cx="0" cy="262" r="14" fill="url(#metal2)" stroke="#000" stroke-width="2"/>
  <circle cx="0" cy="262" r="6" fill="url(#hole)"/>
</g>
S;
}

// Oil filter (масляный фильтр)
function renderOilFilter(): string {
    return <<<S
<g transform="translate(300 100)">
  <!-- top cap with hex pattern -->
  <ellipse cx="0" cy="10" rx="62" ry="14" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <rect x="-62" y="0" width="124" height="22" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <ellipse cx="0" cy="0" rx="62" ry="14" fill="url(#metal2)"/>
  <!-- threaded center hole -->
  <circle cx="0" cy="0" r="14" fill="url(#hole)"/>
  <!-- bypass holes -->
  <g fill="url(#hole)">
    <circle cx="-32" cy="0" r="4"/>
    <circle cx="32" cy="0" r="4"/>
    <circle cx="-22" cy="-8" r="4"/>
    <circle cx="22" cy="-8" r="4"/>
    <circle cx="-22" cy="8" r="4"/>
    <circle cx="22" cy="8" r="4"/>
  </g>
  <!-- canister body -->
  <rect x="-62" y="22" width="124" height="220" fill="url(#orange)" stroke="#5a1f08" stroke-width="2"/>
  <!-- highlights and shadows -->
  <rect x="-62" y="22" width="14" height="220" fill="rgba(255,255,255,0.18)"/>
  <rect x="48" y="22"  width="14" height="220" fill="rgba(0,0,0,0.4)"/>
  <!-- branded text -->
  <text x="0" y="120" text-anchor="middle" font-family="Bebas Neue, Impact" font-size="36" fill="#fff" letter-spacing="6">BOSCH</text>
  <text x="0" y="148" text-anchor="middle" font-family="JetBrains Mono" font-size="11" fill="rgba(255,255,255,0.7)" letter-spacing="2">OIL FILTER</text>
  <text x="0" y="180" text-anchor="middle" font-family="JetBrains Mono" font-size="9" fill="rgba(255,255,255,0.5)">MADE IN GERMANY</text>
  <!-- bottom rim -->
  <ellipse cx="0" cy="242" rx="62" ry="10" fill="url(#metal2)" stroke="#000" stroke-width="1"/>
</g>
S;
}

// Air filter (воздушный фильтр)
function renderAirFilter(): string {
    $pleats = getPleats();
    return <<<S
<g transform="translate(300 220) rotate(-8)">
  <!-- frame -->
  <rect x="-200" y="-60" width="400" height="120" rx="8" fill="url(#rubber)" stroke="#000" stroke-width="2"/>
  <!-- pleated paper element -->
  <g>$pleats</g>
  <!-- branded label -->
  <rect x="-60" y="-20" width="120" height="40" rx="3" fill="#FF6B35" stroke="#000" stroke-width="1"/>
  <text x="0" y="6" text-anchor="middle" font-family="Bebas Neue" font-size="22" fill="#fff" letter-spacing="3">BOSCH</text>
</g>
S;
}

function getPleats(): string {
    $out = '';
    for ($i = 0; $i < 25; $i++) {
        $x = -195 + $i * 16;
        $color = ($i % 2 === 0) ? '#cfa872' : '#8a6d3f';
        $out .= "<rect x=\"$x\" y=\"-55\" width=\"15\" height=\"110\" fill=\"$color\"/>";
        $out .= "<line x1=\"" . ($x + 15) . "\" y1=\"-55\" x2=\"" . ($x + 15) . "\" y2=\"55\" stroke=\"#5a4520\" stroke-width=\"0.5\"/>";
    }
    return $out;
}

// Fuel filter (топливный фильтр) - cylindrical
function renderFuelFilter(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- left connector -->
  <rect x="-200" y="-12" width="40" height="24" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <ellipse cx="-200" cy="0" rx="6" ry="12" fill="url(#hole)"/>
  <!-- main cylinder -->
  <rect x="-160" y="-50" width="320" height="100" rx="8" fill="url(#metal)" stroke="#000" stroke-width="2"/>
  <rect x="-160" y="-50" width="320" height="20" fill="rgba(255,255,255,0.25)" rx="8"/>
  <rect x="-160" y="35"  width="320" height="15" fill="rgba(0,0,0,0.4)"/>
  <!-- label -->
  <rect x="-110" y="-30" width="220" height="60" fill="#FF6B35" stroke="#000" stroke-width="1"/>
  <text x="0" y="-2" text-anchor="middle" font-family="Bebas Neue, Impact" font-size="32" fill="#fff" letter-spacing="6">BOSCH</text>
  <text x="0" y="22" text-anchor="middle" font-family="JetBrains Mono" font-size="11" fill="rgba(255,255,255,0.85)" letter-spacing="2">FUEL FILTER</text>
  <!-- right connector -->
  <rect x="160" y="-12" width="40" height="24" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <ellipse cx="200" cy="0" rx="6" ry="12" fill="url(#hole)"/>
</g>
S;
}

// MAF sensor (датчик)
function renderMaf(): string {
    return <<<S
<g transform="translate(300 100)">
  <!-- intake tube -->
  <rect x="-150" y="60" width="300" height="120" rx="20" fill="url(#metal2)" stroke="#000" stroke-width="2"/>
  <rect x="-150" y="60" width="300" height="20" fill="rgba(255,255,255,0.2)"/>
  <!-- tube ends -->
  <ellipse cx="-150" cy="120" rx="14" ry="60" fill="url(#hole)"/>
  <ellipse cx="150" cy="120" rx="14" ry="60" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <!-- sensor body on top -->
  <rect x="-30" y="20" width="80" height="60" fill="#1a1a1a" stroke="#000" stroke-width="2"/>
  <rect x="-26" y="24" width="72" height="20" fill="url(#metal)" opacity="0.3"/>
  <!-- connector -->
  <rect x="0" y="0" width="50" height="22" fill="#2a2a2a" stroke="#000" stroke-width="1"/>
  <rect x="6"  y="6" width="6" height="10" fill="#FF6B35"/>
  <rect x="18" y="6" width="6" height="10" fill="#fff"/>
  <rect x="30" y="6" width="6" height="10" fill="#FF6B35"/>
  <rect x="42" y="6" width="6" height="10" fill="#fff"/>
  <!-- mounting flange -->
  <rect x="-50" y="80" width="120" height="14" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <circle cx="-40" cy="87" r="3" fill="url(#hole)"/>
  <circle cx="60" cy="87" r="3" fill="url(#hole)"/>
  <!-- branded label -->
  <text x="10" y="58" text-anchor="middle" font-family="Bebas Neue" font-size="14" fill="#FF6B35" letter-spacing="2">BOSCH</text>
  <text x="10" y="72" text-anchor="middle" font-family="JetBrains Mono" font-size="7" fill="rgba(255,255,255,0.6)">HFM 5</text>
</g>
S;
}

// Tensioner pulley (ролик натяжителя)
function renderPulley(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- mounting arm -->
  <path d="M -180 -10 L -100 -30 Q -90 -30 -90 -10 L -90 10 Q -90 30 -100 30 L -180 10 Z"
        fill="url(#metal2)" stroke="#000" stroke-width="2"/>
  <circle cx="-160" cy="0" r="14" fill="url(#hole)"/>
  <!-- pulley outer rim -->
  <circle cx="0" cy="0" r="120" fill="url(#metal)" stroke="#000" stroke-width="2"/>
  <!-- ribbed surface -->
  <g stroke="#000" stroke-width="1" fill="none" opacity="0.5">
    <circle cx="0" cy="0" r="115"/>
    <circle cx="0" cy="0" r="108"/>
    <circle cx="0" cy="0" r="101"/>
    <circle cx="0" cy="0" r="94"/>
    <circle cx="0" cy="0" r="87"/>
  </g>
  <!-- inner -->
  <circle cx="0" cy="0" r="80" fill="url(#metal2)" stroke="#000" stroke-width="2"/>
  <circle cx="0" cy="0" r="40" fill="url(#hole)"/>
  <circle cx="0" cy="0" r="14" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <text x="0" y="-50" text-anchor="middle" font-family="JetBrains Mono" font-size="10" fill="rgba(255,255,255,0.5)" letter-spacing="3">SKF</text>
  <!-- highlight -->
  <ellipse cx="-40" cy="-70" rx="50" ry="14" fill="rgba(255,255,255,0.25)" transform="rotate(-30)"/>
</g>
S;
}

// Thermostat (термостат)
function renderThermostat(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- housing flange -->
  <circle cx="0" cy="0" r="100" fill="url(#metal)" stroke="#000" stroke-width="2"/>
  <circle cx="0" cy="0" r="100" fill="none" stroke="rgba(255,255,255,0.2)" stroke-width="1"/>
  <!-- bolt holes around -->
  <g fill="url(#hole)">
    <circle cx="0" cy="-86" r="6"/>
    <circle cx="61" cy="-61" r="6"/>
    <circle cx="86" cy="0" r="6"/>
    <circle cx="61" cy="61" r="6"/>
    <circle cx="0" cy="86" r="6"/>
    <circle cx="-61" cy="61" r="6"/>
    <circle cx="-86" cy="0" r="6"/>
    <circle cx="-61" cy="-61" r="6"/>
  </g>
  <!-- inner valve plate -->
  <circle cx="0" cy="0" r="56" fill="#2a2a2a" stroke="#000" stroke-width="2"/>
  <!-- center pin/spring -->
  <circle cx="0" cy="0" r="22" fill="url(#metal2)" stroke="#000" stroke-width="1"/>
  <circle cx="0" cy="0" r="8" fill="url(#orange)"/>
  <!-- arms -->
  <g stroke="#1a1a1a" stroke-width="6" stroke-linecap="round">
    <line x1="0" y1="0" x2="0" y2="-50"/>
    <line x1="0" y1="0" x2="50" y2="0"/>
    <line x1="0" y1="0" x2="0" y2="50"/>
    <line x1="0" y1="0" x2="-50" y2="0"/>
  </g>
  <text x="0" y="78" text-anchor="middle" font-family="JetBrains Mono" font-size="8" fill="rgba(255,255,255,0.4)">87°C</text>
  <!-- highlight -->
  <ellipse cx="-30" cy="-60" rx="30" ry="10" fill="rgba(255,255,255,0.25)" transform="rotate(-30)"/>
</g>
S;
}

// Bushing (сайлентблок)
function renderBushing(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- outer metal sleeve -->
  <ellipse cx="0" cy="0" rx="120" ry="60" fill="url(#metal)" stroke="#000" stroke-width="2"/>
  <ellipse cx="0" cy="0" rx="120" ry="60" fill="none" stroke="rgba(255,255,255,0.2)"/>
  <ellipse cx="0" cy="0" rx="100" ry="50" fill="url(#rubber)" stroke="#000" stroke-width="1"/>
  <!-- inner metal sleeve -->
  <ellipse cx="0" cy="0" rx="35" ry="30" fill="url(#metal2)" stroke="#000" stroke-width="2"/>
  <ellipse cx="0" cy="0" rx="22" ry="18" fill="url(#hole)"/>
  <!-- side detail (showing depth) -->
  <ellipse cx="0" cy="0" rx="120" ry="60" fill="none" stroke="rgba(0,0,0,0.4)" stroke-width="3" transform="translate(0 8)"/>
  <text x="0" y="3" text-anchor="middle" font-family="JetBrains Mono" font-size="11" fill="rgba(255,255,255,0.5)" letter-spacing="2">FEBI</text>
  <ellipse cx="-50" cy="-30" rx="40" ry="10" fill="rgba(255,255,255,0.18)" transform="rotate(-12)"/>
</g>
S;
}

// Alternator (генератор)
function renderAlternator(): string {
    return <<<S
<g transform="translate(300 220)">
  <!-- main body (cylinder side view) -->
  <rect x="-130" y="-80" width="260" height="160" rx="20" fill="url(#metal2)" stroke="#000" stroke-width="2"/>
  <rect x="-130" y="-80" width="260" height="22" rx="10" fill="rgba(255,255,255,0.2)"/>
  <!-- cooling fins -->
  <g stroke="#000" stroke-width="1" opacity="0.5">
    <line x1="-110" y1="-70" x2="-110" y2="70"/>
    <line x1="-90" y1="-70" x2="-90" y2="70"/>
    <line x1="-70" y1="-70" x2="-70" y2="70"/>
    <line x1="-50" y1="-70" x2="-50" y2="70"/>
    <line x1="50" y1="-70" x2="50" y2="70"/>
    <line x1="70" y1="-70" x2="70" y2="70"/>
    <line x1="90" y1="-70" x2="90" y2="70"/>
    <line x1="110" y1="-70" x2="110" y2="70"/>
  </g>
  <!-- pulley on left -->
  <ellipse cx="-150" cy="0" rx="14" ry="50" fill="url(#metal)" stroke="#000" stroke-width="2"/>
  <ellipse cx="-150" cy="0" rx="14" ry="50" fill="none" stroke="rgba(0,0,0,0.4)"/>
  <line x1="-150" y1="-40" x2="-150" y2="40" stroke="rgba(255,255,255,0.4)" stroke-width="2"/>
  <!-- terminals on right -->
  <rect x="125" y="-30" width="30" height="20" fill="#1a1a1a" stroke="#000" stroke-width="1"/>
  <rect x="125" y="10" width="30" height="20" fill="#1a1a1a" stroke="#000" stroke-width="1"/>
  <circle cx="155" cy="-20" r="4" fill="#FF6B35"/>
  <circle cx="155" cy="20" r="4" fill="#FF6B35"/>
  <!-- label plate -->
  <rect x="-50" y="-20" width="100" height="40" fill="#FF6B35" stroke="#000" stroke-width="1"/>
  <text x="0" y="0" text-anchor="middle" font-family="Bebas Neue" font-size="20" fill="#fff" letter-spacing="3">DENSO</text>
  <text x="0" y="14" text-anchor="middle" font-family="JetBrains Mono" font-size="8" fill="rgba(255,255,255,0.85)">100A 12V</text>
</g>
S;
}

// Glow plug (свеча накаливания)
function renderGlowPlug(): string {
    return <<<S
<g transform="translate(300 100)">
  <!-- terminal -->
  <rect x="-8" y="0" width="16" height="20" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <circle cx="0" cy="0" r="8" fill="url(#metal)" stroke="#000" stroke-width="1"/>
  <!-- insulator -->
  <rect x="-12" y="20" width="24" height="40" fill="url(#ceramic)" stroke="#5a4520"/>
  <!-- hex nut -->
  <polygon points="-22,60 22,60 28,80 22,100 -22,100 -28,80" fill="url(#metal2)" stroke="#000" stroke-width="1"/>
  <text x="0" y="86" text-anchor="middle" font-family="JetBrains Mono" font-size="9" fill="#000">BOSCH</text>
  <!-- threaded body -->
  <rect x="-12" y="100" width="24" height="60" fill="url(#metal)"/>
  <g stroke="#000" stroke-width="1" opacity="0.5">
    <line x1="-12" y1="108" x2="12" y2="108"/>
    <line x1="-12" y1="116" x2="12" y2="116"/>
    <line x1="-12" y1="124" x2="12" y2="124"/>
    <line x1="-12" y1="132" x2="12" y2="132"/>
    <line x1="-12" y1="140" x2="12" y2="140"/>
    <line x1="-12" y1="148" x2="12" y2="148"/>
    <line x1="-12" y1="156" x2="12" y2="156"/>
  </g>
  <!-- heating element (glowing) -->
  <rect x="-5" y="160" width="10" height="80" fill="url(#orange)" stroke="#5a1f08"/>
  <rect x="-5" y="160" width="10" height="80" fill="url(#orange)"/>
  <ellipse cx="0" cy="240" rx="6" ry="4" fill="#FF8B55"/>
</g>
S;
}

/* ─────────────────────────────────────────────────────────────────────
 * Map category slug → renderer
 * ───────────────────────────────────────────────────────────────────── */
$rendererByCat = [
    'tormoznaya-sistema' => 'renderBrake',
    'svechi-zazgiganiya' => 'renderSparkPlug',
    'remni-i-tsepi'      => 'renderTimingBelt',
    'amortizatory'       => 'renderShock',
    'filtry'             => 'renderOilFilter',
    'podveska'           => 'renderBearing',
];

// Routing by part_number (exact match for our seed data) → fallback by category
function chooseRenderer(string $partNumber, string $name, string $catSlug): string {
    // Exact mapping for the 20 seeded parts
    static $byNumber = [
        '0280218116'  => 'renderMaf',          // Датчик MAF Bosch
        'BKR6EK'      => 'renderSparkPlug',    // Свеча зажигания NGK
        'K015561XS'   => 'renderTimingBelt',   // Ремень ГРМ Gates
        '6205-2RS1C3' => 'renderBearing',      // Подшипник SKF
        'BP-0001'     => 'renderBrakePads',    // Колодки Brembo
        'O0390241'    => 'renderShock',        // Амортизатор Monroe
        'F026407077'  => 'renderOilFilter',    // Масляный фильтр Bosch
        'IK20'        => 'renderSparkPlug',    // Свеча NGK Iridium
        'TCK329'      => 'renderTimingBelt',   // Комплект ГРМ Gates
        'VKBA3648'    => 'renderBearing',      // Подшипник ступицы SKF
        '32311FEBI'   => 'renderBushing',      // Сайлентблок Febi
        '18723FEBI'   => 'renderThermostat',   // Термостат Febi
        'P50090'      => 'renderBrakePads',    // Колодки Brembo
        'F026402330'  => 'renderFuelFilter',   // Топливный фильтр Bosch
        'E500L18B17A' => 'renderGlowPlug',     // Свеча накаливания Bosch
        'DN0SD264'    => 'renderAlternator',   // Генератор Denso
        '128501FEBI'  => 'renderThermostat',   // Термостат Mahle/Febi
        'OE648'       => 'renderShock',        // Амортизатор Monroe
        'VKM31010'    => 'renderPulley',       // Ролик натяжителя SKF
        '1987432803'  => 'renderAirFilter',    // Воздушный фильтр Bosch
    ];
    if (isset($byNumber[$partNumber])) return $byNumber[$partNumber];

    // Fallback by category slug for any new parts added later
    $byCat = [
        'tormoznaya-sistema' => 'renderBrake',
        'svechi-zazgiganiya' => 'renderSparkPlug',
        'remni-i-tsepi'      => 'renderTimingBelt',
        'amortizatory'       => 'renderShock',
        'filtry'             => 'renderOilFilter',
        'podveska'           => 'renderBearing',
        'elektrika'          => 'renderAlternator',
        'transmissiya'       => 'renderBearing',
        'kuzov'              => 'renderBearing',
        'dvigatel'           => 'renderOilFilter',
    ];
    return $byCat[$catSlug] ?? 'renderBearing';
}

/* ─────────────────────────────────────────────────────────────────────
 * Main loop
 * ───────────────────────────────────────────────────────────────────── */
$rows = $db->query("SELECT p.id, p.part_number, p.name, p.images,
                           b.name AS brand_name,
                           c.slug AS cat_slug
                    FROM parts p
                    LEFT JOIN brands b ON b.id = p.brand_id
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE p.is_active = 1
                    ORDER BY p.id")->fetchAll();

// Clean old demo SVGs first (so re-run replaces stylized version)
foreach (glob($partsDir . 'demo_*.svg') ?: [] as $oldFile) @unlink($oldFile);

$updated = 0;

foreach ($rows as $p) {
    $renderer = chooseRenderer($p['part_number'], $p['name'], $p['cat_slug'] ?? '');
    $body     = $renderer();
    $svg      = svgWrap($body, $p['part_number'], mb_strtoupper($p['brand_name'] ?? ''));
    $fname    = 'demo_' . preg_replace('/[^A-Za-z0-9_]/', '_', $p['part_number']) . '.svg';
    file_put_contents($partsDir . $fname, $svg);

    $db->prepare("UPDATE parts SET images = ? WHERE id = ?")
       ->execute([json_encode([$fname]), $p['id']]);

    echo "[ok] #{$p['id']} {$p['part_number']}  →  $renderer  →  $fname\n";
    $updated++;
}

echo "\nDone. Updated: $updated parts.\n";
