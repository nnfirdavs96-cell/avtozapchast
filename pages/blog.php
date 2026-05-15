<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('blog') . ' — ' . getSetting('site_name');

$db   = getDB();
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 6;
$offset  = ($page - 1) * $perPage;

$catFilter = $_GET['cat'] ?? '';
$catLabels = ['news'=>'Новости','tips'=>'Советы по ТО','review'=>'Обзоры запчастей'];
$catWhere  = $catFilter ? "AND bp.category = " . $db->quote($catFilter) : '';

$total = (int)$db->query("SELECT COUNT(*) FROM blog_posts bp WHERE bp.is_published=1 $catWhere")->fetchColumn();
$pages = max(1, ceil($total / $perPage));
$stmt  = $db->prepare("SELECT bp.*, u.username AS author FROM blog_posts bp LEFT JOIN users u ON u.id=bp.author_id WHERE bp.is_published=1 $catWhere ORDER BY bp.created_at DESC LIMIT $perPage OFFSET $offset");
$stmt->execute();
$posts = $stmt->fetchAll();

// Recent posts for sidebar
$recentStmt = $db->query("SELECT slug, title_ru, title_tg, title_en, created_at FROM blog_posts WHERE is_published=1 ORDER BY created_at DESC LIMIT 4");
$recentPosts = $recentStmt->fetchAll();

$bgImages = ['blogpage1.jpg','blogpage2.jpg','blogpage3.jpg','blogpage4.jpg','blogpage5.jpg'];
$thumbImages = ['blog6.jpg','blog7.jpg','blog8.jpg','blog9.jpg'];

require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('blog')]]) ?>

<div class="blog_bg_area">
    <div class="container">
        <!--blog area start-->
        <div class="blog_page_section">
            <div class="row">
                <div class="col-lg-9 col-md-12">
                    <div class="blog_wrapper mb-30">
                        <div class="blog_header">
                            <h1><?= t('blog') ?></h1>
                            <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:12px;margin-bottom:4px;">
                                <a href="<?= APP_URL ?>/pages/blog.php"
                                   style="padding:5px 14px;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;border:1px solid <?= !$catFilter ? '#d32f2f' : '#ddd' ?>;background:<?= !$catFilter ? '#d32f2f' : '#fff' ?>;color:<?= !$catFilter ? '#fff' : '#555' ?>;">
                                    Все статьи
                                </a>
                                <?php foreach ($catLabels as $k => $l): ?>
                                <a href="<?= APP_URL ?>/pages/blog.php?cat=<?= $k ?>"
                                   style="padding:5px 14px;border-radius:20px;font-size:0.82rem;font-weight:600;text-decoration:none;border:1px solid <?= $catFilter===$k ? '#d32f2f' : '#ddd' ?>;background:<?= $catFilter===$k ? '#d32f2f' : '#fff' ?>;color:<?= $catFilter===$k ? '#fff' : '#555' ?>;">
                                    <?= $l ?>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="blog_wrapper_inner">
                            <?php if (empty($posts)): ?>
                            <p class="text-center py-5"><?= t('no_records') ?></p>
                            <?php endif; ?>
                            <?php foreach ($posts as $i => $post):
                                $title   = tField($post, 'title');
                                $excerpt = tField($post, 'excerpt');
                                $img     = $bgImages[$i % count($bgImages)];
                                $date    = date('M d, Y', strtotime($post['created_at']));
                            ?>
                            <article class="single_blog">
                                <figure>
                                    <div class="blog_thumb">
                                        <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>">
                                            <img src="<?= APP_URL ?>/assets/img/blog/<?= $img ?>" alt="<?= sanitize($title) ?>">
                                        </a>
                                    </div>
                                    <figcaption class="blog_content">
                                        <h4 class="post_title">
                                            <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>"><?= sanitize($title) ?></a>
                                        </h4>
                                        <div class="blog_meta">
                                            <span class="author"><?= t('posted_by') ?> : <a href="#"><?= sanitize($post['author'] ?? 'admin') ?></a> / </span>
                                            <span class="meta_date"><?= t('posted_on') ?> : <a href="#"><?= $date ?></a></span>
                                        </div>
                                        <?php if ($excerpt): ?>
                                        <div class="blog_desc">
                                            <p><?= sanitize(truncate($excerpt, 200)) ?></p>
                                        </div>
                                        <?php endif; ?>
                                        <footer class="btn_more">
                                            <a href="<?= APP_URL ?>/pages/blog-detail.php?slug=<?= urlencode($post['slug']) ?>"><?= t('read_more') ?></a>
                                        </footer>
                                    </figcaption>
                                </figure>
                            </article>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <!--blog pagination area start-->
                    <?= paginationHtml(['pages'=>$pages,'current'=>$page], APP_URL.'/pages/blog.php') ?>
                    <!--blog pagination area end-->
                </div>
                <div class="col-lg-3 col-md-12">
                    <div class="blog_sidebar_widget">
                        <div class="widget_list widget_post">
                            <div class="widget_title">
                                <h3><?= t('recent_posts') ?></h3>
                            </div>
                            <?php foreach ($recentPosts as $j => $rp):
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
        <!--blog area end-->
    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
