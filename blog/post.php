<?php
require_once __DIR__ . '/../config/config.php';
$slug = trim((string)($_GET['slug'] ?? ''));
$stmt = getDB()->prepare("SELECT bp.*, u.username AS author_name FROM blog_posts bp LEFT JOIN users u ON u.id=bp.author_id WHERE bp.slug=? AND bp.is_published=1");
$stmt->execute([$slug]);
$post = $stmt->fetch();
if (!$post) { flashMessage('danger', 'Статья не найдена'); redirect(APP_URL . '/blog/index.php'); }

$pageTitle = $post['title'];
$pageDescription = $post['excerpt'] ?? mb_substr(strip_tags($post['body']), 0, 160);
$ogType = 'article';

$jsonLd = [
    '@context'  => 'https://schema.org',
    '@type'     => 'BlogPosting',
    'headline'  => $post['title'],
    'datePublished' => $post['created_at'],
    'author'    => ['@type'=>'Person','name'=>$post['author_name'] ?? 'АвтоЗапчасть'],
    'description' => $pageDescription,
    'url'       => canonicalUrl(),
];

$other = getDB()->prepare("SELECT slug,title FROM blog_posts WHERE is_published=1 AND id<>? ORDER BY created_at DESC LIMIT 4");
$other->execute([$post['id']]);
$otherPosts = $other->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container">
  <h1><?= sanitize($post['title']) ?></h1>
  <nav class="breadcrumb">
    <a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span>
    <a href="<?= APP_URL ?>/blog/index.php"><?= t('blog') ?></a><span class="sep">/</span>
    <span class="current"><?= sanitize($post['title']) ?></span>
  </nav>
</div></div>
<section class="section">
  <div class="container container-sm">
    <article class="checkout-card" style="line-height:1.85;font-size:1.02rem">
      <div style="color:var(--muted);font-size:0.85rem;margin-bottom:14px">
        <?= date('d.m.Y', strtotime($post['created_at'])) ?>
        <?php if ($post['author_name']): ?> · <?= sanitize($post['author_name']) ?><?php endif; ?>
      </div>
      <?php if ($post['excerpt']): ?>
        <p style="font-size:1.1rem;color:var(--text-soft);font-weight:500;margin-bottom:24px"><?= sanitize($post['excerpt']) ?></p>
      <?php endif; ?>
      <div style="white-space:pre-line"><?= sanitize($post['body']) ?></div>
    </article>

    <?php if (!empty($otherPosts)): ?>
      <h3 class="mt-32 mb-16">Читать также</h3>
      <div class="blog-grid">
        <?php foreach ($otherPosts as $o): ?>
          <a href="<?= APP_URL ?>/blog/post.php?slug=<?= sanitize($o['slug']) ?>" class="blog-card">
            <div class="cover" data-letter="<?= sanitize(mb_substr($o['title'],0,1)) ?>"></div>
            <div class="body"><h3><?= sanitize($o['title']) ?></h3></div>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
