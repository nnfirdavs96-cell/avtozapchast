<?php
require_once __DIR__ . '/config/config.php';
header('Content-Type: application/xml; charset=utf-8');

$urls = [
    APP_URL . '/index.php',
    APP_URL . '/catalog/index.php',
    APP_URL . '/search/vin.php',
    APP_URL . '/blog/index.php',
    APP_URL . '/pages/about.php',
    APP_URL . '/pages/contacts.php',
    APP_URL . '/pages/delivery.php',
    APP_URL . '/pages/payment.php',
    APP_URL . '/pages/privacy.php',
    APP_URL . '/pages/terms.php',
];

$db = getDB();
foreach ($db->query("SELECT slug FROM categories WHERE is_active=1") as $c) {
    $urls[] = APP_URL . '/catalog/index.php?category=' . urlencode($c['slug']);
}
foreach ($db->query("SELECT id FROM parts WHERE is_active=1") as $p) {
    $urls[] = APP_URL . '/catalog/part.php?id=' . (int)$p['id'];
}
foreach ($db->query("SELECT slug FROM blog_posts WHERE is_published=1") as $b) {
    $urls[] = APP_URL . '/blog/post.php?slug=' . urlencode($b['slug']);
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url><loc>' . htmlspecialchars($u, ENT_QUOTES) . '</loc><changefreq>weekly</changefreq></url>' . "\n";
}
echo '</urlset>';
