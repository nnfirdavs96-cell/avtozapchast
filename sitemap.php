<?php
// Dynamic XML sitemap for search engines (Google / Yandex).
// Served at /sitemap.xml via .htaccess rewrite (Apache) or nginx location;
// also reachable directly at /sitemap.php.
require_once __DIR__ . '/config/config.php';

$base = (defined('APP_URL') && APP_URL !== '') ? rtrim(APP_URL, '/') : 'https://autodoc.tj';

$urls = [];
$add = function (string $path, string $changefreq = 'weekly', string $priority = '0.6', ?string $lastmod = null) use (&$urls, $base) {
    $urls[] = [
        'loc'        => $base . $path,
        'changefreq' => $changefreq,
        'priority'   => $priority,
        'lastmod'    => $lastmod,
    ];
};

// Static pages
$add('/', 'daily', '1.0');
$add('/catalog/index.php', 'daily', '0.9');
$add('/pages/blog.php', 'weekly', '0.5');
$add('/pages/reviews.php', 'weekly', '0.4');
$add('/pages/vin.php', 'monthly', '0.5');
$add('/pages/about.php', 'monthly', '0.4');
$add('/pages/contact.php', 'monthly', '0.4');
$add('/pages/faq.php', 'monthly', '0.4');

try {
    $db = getDB();

    foreach ($db->query("SELECT slug FROM categories WHERE is_active = 1 ORDER BY sort_order, id")->fetchAll() as $c) {
        if (!empty($c['slug'])) {
            $add('/catalog/category.php?slug=' . urlencode($c['slug']), 'weekly', '0.7');
        }
    }

    foreach ($db->query("SELECT id, updated_at FROM parts WHERE is_active = 1 ORDER BY id")->fetchAll() as $p) {
        $lastmod = !empty($p['updated_at']) ? date('Y-m-d', strtotime($p['updated_at'])) : null;
        $add('/catalog/part.php?id=' . (int)$p['id'], 'weekly', '0.6', $lastmod);
    }

    try {
        foreach ($db->query("SELECT id, updated_at FROM blog_posts WHERE is_published = 1 ORDER BY id")->fetchAll() as $bp) {
            $lastmod = !empty($bp['updated_at']) ? date('Y-m-d', strtotime($bp['updated_at'])) : null;
            $add('/pages/blog-detail.php?id=' . (int)$bp['id'], 'monthly', '0.4', $lastmod);
        }
    } catch (Throwable $e) {
        // blog table optional — ignore
    }
} catch (Throwable $e) {
    // DB unavailable — still emit the static URLs below
}

header('Content-Type: application/xml; charset=UTF-8');
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";
foreach ($urls as $u) {
    echo '  <url>' . "\n";
    echo '    <loc>' . htmlspecialchars($u['loc'], ENT_XML1, 'UTF-8') . '</loc>' . "\n";
    if (!empty($u['lastmod'])) {
        echo '    <lastmod>' . $u['lastmod'] . '</lastmod>' . "\n";
    }
    echo '    <changefreq>' . $u['changefreq'] . '</changefreq>' . "\n";
    echo '    <priority>' . $u['priority'] . '</priority>' . "\n";
    echo '  </url>' . "\n";
}
echo '</urlset>' . "\n";
