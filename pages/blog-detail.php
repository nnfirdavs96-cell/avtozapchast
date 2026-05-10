<?php
require_once dirname(__DIR__) . '/config/config.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: '.APP_URL.'/pages/blog.php'); exit; }

$db   = getDB();
$stmt = $db->prepare("SELECT bp.*, u.username AS author FROM blog_posts bp LEFT JOIN users u ON u.id=bp.author_id WHERE bp.slug=? AND bp.is_published=1 LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) { header('HTTP/1.0 404 Not Found'); include dirname(__DIR__).'/pages/404.php'; exit; }

$title  = tField($post,'title');
$body   = tField($post,'body');
$pageTitle = $title . ' — ' . getSetting('site_name');
$date   = date('d.m.Y', strtotime($post['created_at']));

$related = $db->query("SELECT * FROM blog_posts WHERE is_published=1 AND slug!=".quote_string($slug)." ORDER BY RAND() LIMIT 3")->fetchAll();

function quote_string($s) { return "'" . str_replace("'", "''", $s) . "'"; }

require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('blog'),'url'=>APP_URL.'/pages/blog.php'],['label'=>truncate($title,40)]]) ?>

<div class="blog_details_area section_padding" style="padding:60px 0">
  <div class="container">
    <div class="row">
      <div class="col-lg-9">
        <div style="background:#fff;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.06)">
          <div style="font-size:0.8rem;color:#aaa;margin-bottom:12px">
            <i class="ion-calendar"></i> <?= $date ?>
            <?php if ($post['author']): ?>&nbsp;&bull;&nbsp;<i class="ion-person"></i> <?= sanitize($post['author']) ?><?php endif; ?>
          </div>
          <h1 style="font-size:1.6rem;font-weight:800;margin-bottom:20px;line-height:1.3"><?= sanitize($title) ?></h1>
          <div style="color:#555;line-height:1.8;font-size:0.95rem">
            <?= nl2br(sanitize($body)) ?>
          </div>
          <div style="margin-top:30px;padding-top:20px;border-top:1px solid #eee">
            <a href="<?= APP_URL ?>/pages/blog.php" style="color:#d32f2f;font-weight:600;text-decoration:none">&larr; <?= t('blog') ?></a>
          </div>
        </div>
      </div>
      <div class="col-lg-3">
        <div style="background:#fff;padding:20px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.06)">
          <h4 style="font-size:1rem;font-weight:700;margin-bottom:16px">Похожие записи</h4>
          <?php
          $relStmt = $db->prepare("SELECT slug, title_ru, title_tg, title_en, created_at FROM blog_posts WHERE is_published=1 AND slug!=? ORDER BY RAND() LIMIT 3");
          $relStmt->execute([$slug]);
          $related = $relStmt->fetchAll();
          foreach ($related as $r):
            $rTitle = tField($r,'title');
          ?>
          <div style="margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #f0f0f0">
            <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($r['slug']) ?>" style="color:#333;text-decoration:none;font-size:0.875rem;font-weight:600;line-height:1.4;display:block"><?= sanitize($rTitle) ?></a>
            <span style="color:#aaa;font-size:0.75rem"><?= date('d.m.Y',strtotime($r['created_at'])) ?></span>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
