<?php
require_once __DIR__ . '/../config/config.php';
$posts = getDB()->query("SELECT * FROM blog_posts WHERE is_published=1 ORDER BY created_at DESC")->fetchAll();
$pageTitle = t('blog');
require_once __DIR__ . '/../includes/header.php';
?>
<div class="page-head"><div class="container">
  <h1><?= t('blog') ?></h1>
  <nav class="breadcrumb"><a href="<?= APP_URL ?>/index.php"><?= t('home') ?></a><span class="sep">/</span><span class="current"><?= t('blog') ?></span></nav>
</div></div>
<section class="section">
  <div class="container">
    <?php if (empty($posts)): ?>
      <div class="empty-state"><h3>Пока нет статей</h3></div>
    <?php else: ?>
    <div class="blog-grid">
      <?php foreach ($posts as $p): ?>
        <a href="<?= APP_URL ?>/blog/post.php?slug=<?= sanitize($p['slug']) ?>" class="blog-card">
          <div class="cover" data-letter="<?= sanitize(mb_substr($p['title'],0,1)) ?>"></div>
          <div class="body">
            <div class="meta"><?= date('d.m.Y', strtotime($p['created_at'])) ?></div>
            <h3><?= sanitize($p['title']) ?></h3>
            <p><?= sanitize(truncate($p['excerpt'] ?? '', 140)) ?></p>
            <span class="more"><?= t('read_more') ?> →</span>
          </div>
        </a>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/footer.php'; ?>
