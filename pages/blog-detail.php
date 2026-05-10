<?php
require_once dirname(__DIR__) . '/config/config.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) { header('Location: '.APP_URL.'/pages/blog.php'); exit; }

$db   = getDB();
$stmt = $db->prepare("SELECT bp.*, u.username AS author FROM blog_posts bp LEFT JOIN users u ON u.id=bp.author_id WHERE bp.slug=? AND bp.is_published=1 LIMIT 1");
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) { header('HTTP/1.0 404 Not Found'); include dirname(__DIR__).'/pages/404.php'; exit; }

$title  = tField($post, 'title');
$body   = tField($post, 'body');
$pageTitle = $title . ' — ' . getSetting('site_name');
$date   = date('M d, Y', strtotime($post['created_at']));

function quote_string($s) { return "'" . str_replace("'", "''", $s) . "'"; }

// Related posts for sidebar
$relStmt = $db->prepare("SELECT slug, title_ru, title_tg, title_en, created_at FROM blog_posts WHERE is_published=1 AND slug!=? ORDER BY created_at DESC LIMIT 4");
$relStmt->execute([$slug]);
$relatedPosts = $relStmt->fetchAll();

$thumbImages = ['blog6.jpg','blog7.jpg','blog8.jpg','blog9.jpg'];
$bigImages   = ['blog-big1.jpg','blog-big2.jpg','blog-big3.jpg','blog-big4.jpg','blog-big5.jpg'];
$postImg     = $bigImages[abs(crc32($slug)) % count($bigImages)];

require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('blog'),'url'=>APP_URL.'/pages/blog.php'],['label'=>truncate($title,40)]]) ?>

<!--blog body area start-->
<div class="blog_bg_area blog_details_bg">
    <div class="container">
        <div class="blog_page_section">
            <div class="row">
                <div class="col-lg-9 col-md-12">
                    <!--blog details area start-->
                    <div class="blog_wrapper blog_details">
                        <article class="single_blog">
                            <figure>
                                <div class="post_header">
                                    <h3 class="post_title"><?= sanitize($title) ?></h3>
                                    <div class="blog_meta">
                                        <?php if ($post['author']): ?>
                                        <span class="author"><?= t('posted_by') ?> : <a href="#"><?= sanitize($post['author']) ?></a> / </span>
                                        <?php endif; ?>
                                        <span class="meta_date"><?= t('posted_on') ?> : <a href="#"><?= $date ?></a></span>
                                    </div>
                                </div>
                                <div class="blog_thumb">
                                    <img src="<?= APP_URL ?>/assets/img/blog/<?= $postImg ?>" alt="<?= sanitize($title) ?>">
                                </div>
                                <figcaption class="blog_content">
                                    <div class="post_content">
                                        <?= nl2br(sanitize($body)) ?>
                                    </div>
                                    <div class="entry_content">
                                        <div class="social_sharing">
                                            <p><?= t('share_post') ?>:</p>
                                            <ul>
                                                <li><a href="#" title="facebook"><i class="fa fa-facebook"></i></a></li>
                                                <li><a href="#" title="twitter"><i class="fa fa-twitter"></i></a></li>
                                                <li><a href="#" title="pinterest"><i class="fa fa-pinterest"></i></a></li>
                                                <li><a href="#" title="google+"><i class="fa fa-google-plus"></i></a></li>
                                                <li><a href="#" title="linkedin"><i class="fa fa-linkedin"></i></a></li>
                                            </ul>
                                        </div>
                                    </div>
                                </figcaption>
                            </figure>
                        </article>

                        <?php if (!empty($relatedPosts)): ?>
                        <div class="related_posts">
                            <h3><?= t('related_posts') ?></h3>
                            <div class="row">
                                <?php foreach (array_slice($relatedPosts, 0, 3) as $k => $rp):
                                    $rpTitle = tField($rp, 'title');
                                    $rpDate  = date('M d, Y', strtotime($rp['created_at']));
                                    $rpThumb = $thumbImages[$k % count($thumbImages)];
                                ?>
                                <div class="col-lg-4 col-md-6">
                                    <article class="single_related">
                                        <figure>
                                            <div class="related_thumb">
                                                <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($rp['slug']) ?>">
                                                    <img src="<?= APP_URL ?>/assets/img/blog/<?= $rpThumb ?>" alt="<?= sanitize($rpTitle) ?>">
                                                </a>
                                            </div>
                                            <figcaption class="related_content">
                                                <h4><a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($rp['slug']) ?>"><?= sanitize(truncate($rpTitle, 40)) ?></a></h4>
                                                <div class="blog_meta">
                                                    <span class="meta_date"><?= $rpDate ?></span>
                                                </div>
                                            </figcaption>
                                        </figure>
                                    </article>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                    </div>
                    <!--blog details area end-->
                </div>
                <div class="col-lg-3 col-md-12">
                    <div class="blog_sidebar_widget">
                        <div class="widget_list widget_post">
                            <div class="widget_title">
                                <h3><?= t('recent_posts') ?></h3>
                            </div>
                            <?php foreach ($relatedPosts as $j => $rp):
                                $rpTitle = tField($rp, 'title');
                                $rpDate  = date('M d, Y', strtotime($rp['created_at']));
                                $rpThumb = $thumbImages[$j % count($thumbImages)];
                            ?>
                            <div class="post_wrapper">
                                <div class="post_thumb">
                                    <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($rp['slug']) ?>">
                                        <img src="<?= APP_URL ?>/assets/img/blog/<?= $rpThumb ?>" alt="<?= sanitize($rpTitle) ?>">
                                    </a>
                                </div>
                                <div class="post_info">
                                    <h4><a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($rp['slug']) ?>"><?= sanitize(truncate($rpTitle, 40)) ?></a></h4>
                                    <span><?= $rpDate ?></span>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="widget_list widget_tag">
                            <div class="widget_title">
                                <h3><?= t('tags') ?></h3>
                            </div>
                            <div class="tag_widget">
                                <ul>
                                    <li><a href="#"><?= t('auto_parts') ?></a></li>
                                    <li><a href="#"><?= t('repair') ?></a></li>
                                    <li><a href="#"><?= t('service') ?></a></li>
                                    <li><a href="#"><?= t('catalog') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!--blog body area end-->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
