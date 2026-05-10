<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('blog') . ' — ' . getSetting('site_name');

$db   = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 9;
$offset  = ($page - 1) * $perPage;

$total = (int)$db->query("SELECT COUNT(*) FROM blog_posts WHERE is_published=1")->fetchColumn();
$pages = max(1, ceil($total / $perPage));
$stmt  = $db->prepare("SELECT bp.*, u.username AS author FROM blog_posts bp LEFT JOIN users u ON u.id=bp.author_id WHERE bp.is_published=1 ORDER BY bp.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$posts = $stmt->fetchAll();

$lang = getLang();
$bgImages = ['blog-big1.jpg','blog-big2.jpg','blog-big3.jpg','blog-big4.jpg','blog-big5.jpg'];

require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('blog')]]) ?>

<div class="blog_area section_padding" style="padding:60px 0">
  <div class="container">
    <div class="section_title"><h2><?= t('blog') ?></h2></div>
    <div class="row">
      <?php foreach ($posts as $i => $post):
        $title   = tField($post,'title');
        $excerpt = tField($post,'excerpt');
        $img     = $bgImages[$i % count($bgImages)];
        $date    = date('d.m.Y', strtotime($post['created_at']));
      ?>
      <div class="col-lg-4 col-md-6 mb-4">
        <div class="blog_post" style="background:#fff;border-radius:10px;overflow:hidden;box-shadow:0 2px 10px rgba(0,0,0,0.06)">
          <div class="blog_thumb" style="position:relative;overflow:hidden">
            <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>">
              <img src="<?= APP_URL ?>/assets/img/blog/<?= $img ?>" alt="<?= sanitize($title) ?>" style="width:100%;height:200px;object-fit:cover;transition:transform 0.3s" onmouseover="this.style.transform='scale(1.05)'" onmouseout="this.style.transform='scale(1)'">
            </a>
          </div>
          <div style="padding:20px">
            <div style="font-size:0.75rem;color:#aaa;margin-bottom:8px">
              <i class="ion-calendar"></i> <?= $date ?>
              <?php if ($post['author']): ?>&nbsp;&bull;&nbsp;<i class="ion-person"></i> <?= sanitize($post['author']) ?><?php endif; ?>
            </div>
            <h4 style="font-size:1rem;font-weight:700;margin-bottom:10px;line-height:1.4">
              <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>" style="color:#222;text-decoration:none"><?= sanitize($title) ?></a>
            </h4>
            <?php if ($excerpt): ?>
            <p style="color:#666;font-size:0.875rem;line-height:1.6;margin-bottom:14px"><?= sanitize(truncate($excerpt, 120)) ?></p>
            <?php endif; ?>
            <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>" style="color:#d32f2f;font-weight:600;font-size:0.875rem;text-decoration:none">Читать далее &rarr;</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
      <?php if (empty($posts)): ?>
      <div class="col-12 text-center py-5"><p style="color:#aaa"><?= t('no_records') ?></p></div>
      <?php endif; ?>
    </div>
    <?= paginationHtml(['pages'=>$pages,'current'=>$page], APP_URL.'/pages/blog.php') ?>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
