<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('about') . ' — ' . getSetting('site_name');

// Load all CMS sections from DB
$db = getDB();
$sectionsRaw = $db->query(
    "SELECT * FROM site_sections WHERE is_active=1 ORDER BY sort_order, id"
)->fetchAll();
$sections = [];
foreach ($sectionsRaw as $s) {
    $sections[$s['slug']] = $s;
}

function sec(array $sections, string $slug, string $field): string {
    $lang = getLang();
    $s = $sections[$slug] ?? null;
    if (!$s) return '';
    $val = $s[$field . '_' . $lang] ?? $s[$field . '_ru'] ?? '';
    return sanitize($val);
}

// Featured real customer reviews for the «Что говорят клиенты» showcase
$featuredReviews = $db->query(
    "SELECT u.username AS name, r.rating, r.comment, 'shop' AS kind, NULL AS part_name
       FROM shop_reviews r JOIN users u ON u.id = r.user_id
      WHERE r.status='approved' AND r.is_featured=1
     UNION ALL
     SELECT u.username AS name, r.rating, r.comment, 'product' AS kind, p.name AS part_name
       FROM product_reviews r
       JOIN users u ON u.id = r.user_id
       JOIN parts p ON p.id = r.part_id
      WHERE r.status='approved' AND r.is_featured=1
     ORDER BY RAND()
     LIMIT 9"
)->fetchAll();

require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('about')]]) ?>

<div class="about_bg_area">
    <div class="container">

        <!-- ── О компании ─────────────────────────────────────── -->
        <section class="about_section mb-60" id="about">
            <div class="row align-items-center">
                <div class="col-12">
                    <figure>
                        <?php $heroImg = $sections['about_hero']['image'] ?? ''; ?>
                        <div class="about_thumb">
                            <img src="<?= $heroImg ?: APP_URL . '/assets/img/about/about1.jpg' ?>"
                                 alt="<?= sec($sections,'about_hero','title') ?>">
                        </div>
                        <figcaption class="about_content">
                            <h1><?= sec($sections,'about_hero','title') ?: t('about_title') ?></h1>
                            <p><?= sec($sections,'about_hero','content') ?: t('about_desc') ?></p>
                            <div class="about_signature">
                                <?php
                                $sigImg = $sections['about_signature']['image'] ?? '';
                                if ($sigImg):
                                ?>
                                    <img src="<?= sanitize($sigImg) ?>" alt="">
                                <?php else: ?>
                                    <img src="<?= APP_URL ?>/assets/img/about/about-us-signature.png" alt="">
                                <?php endif; ?>
                            </div>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- ── Преимущества ──────────────────────────────────── -->
        <?php
        $benefits = [
            ['slug'=>'about_benefit_1','icon'=>'About_icon1.png','titleKey'=>'free_delivery',   'textKey'=>'free_delivery_text'],
            ['slug'=>'about_benefit_2','icon'=>'About_icon2.png','titleKey'=>'secure_payment',  'textKey'=>'secure_payment_text'],
            ['slug'=>'about_benefit_3','icon'=>'About_icon3.png','titleKey'=>'quality_guarantee','textKey'=>'quality_text'],
        ];
        ?>
        <div class="choseus_area">
            <div class="row">
                <?php foreach ($benefits as $b):
                    $bTitle = sec($sections, $b['slug'], 'title') ?: t($b['titleKey']);
                    $bText  = sec($sections, $b['slug'], 'content') ?: t($b['textKey']);
                    $bIcon  = $sections[$b['slug']]['image'] ?? '';
                    $bIconSrc = $bIcon ?: APP_URL . '/assets/img/about/' . $b['icon'];
                ?>
                <div class="col-lg-4 col-md-4">
                    <div class="single_chose">
                        <div class="chose_icone"><img src="<?= sanitize($bIconSrc) ?>" alt=""></div>
                        <div class="chose_content"><h3><?= $bTitle ?></h3><p><?= $bText ?></p></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── Галерея разделов ──────────────────────────────── -->
        <?php
        $gallerySlugs = ['about_team','about_reviews','about_stores'];
        $galleryDefaults = [
            'about_team'    => ['img'=>'about2.jpg','title'=>t('what_we_do'),    'text'=>t('what_we_do_text')],
            'about_reviews' => ['img'=>'about3.jpg','title'=>t('our_mission'),   'text'=>t('our_mission_text')],
            'about_stores'  => ['img'=>'about4.jpg','title'=>t('our_history'),   'text'=>t('our_history_text')],
        ];
        ?>
        <div class="about_gallery_section mb-55" id="team">
            <div class="row">
                <?php foreach ($gallerySlugs as $slug):
                    $s   = $sections[$slug] ?? null;
                    $def = $galleryDefaults[$slug];
                    $img = ($s && $s['image']) ? $s['image'] : APP_URL . '/assets/img/about/' . $def['img'];
                    $lang = getLang();
                    $title   = ($s && !empty($s['title_'.$lang]))   ? $s['title_'.$lang]   : ($s['title_ru'] ?? $def['title']);
                    $content = ($s && !empty($s['content_'.$lang])) ? $s['content_'.$lang] : ($s['content_ru'] ?? $def['text']);
                    $anchor  = str_replace('about_','',$slug);
                ?>
                <div class="col-lg-4 col-md-4">
                    <article class="single_gallery_section" id="<?= $anchor ?>">
                        <figure>
                            <div class="gallery_thumb">
                                <img src="<?= sanitize($img) ?>" alt="<?= sanitize($title) ?>">
                            </div>
                            <figcaption class="about_gallery_content">
                                <h3><?= sanitize($title) ?></h3>
                                <p><?= sanitize($content) ?></p>
                            </figcaption>
                        </figure>
                    </article>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- ── FAQ + Отзывы ──────────────────────────────────── -->
        <?php
        $faqItems = [
            ['slug'=>'about_faq_1','titleKey'=>'fast_delivery',    'textKey'=>'fast_delivery_text'],
            ['slug'=>'about_faq_2','titleKey'=>'years_in_business', 'textKey'=>'years_in_business_text'],
            ['slug'=>'about_faq_3','titleKey'=>'quality_guarantee', 'textKey'=>'quality_text'],
            ['slug'=>'about_faq_4','titleKey'=>'secure_payment',    'textKey'=>'secure_payment_text'],
        ];
        $testimonials = [
            ['slug'=>'about_testimonial_1','img'=>'testimonial1.jpg'],
            ['slug'=>'about_testimonial_2','img'=>'testimonial2.jpg'],
            ['slug'=>'about_testimonial_3','img'=>'testimonial3.jpg'],
        ];
        ?>
        <div class="faq-client-say-area" id="reviews">
            <div class="row">
                <div class="col-lg-6 col-md-6">
                    <div class="faq-client_title"><h2><?= t('what_we_do_for_you') ?></h2></div>
                    <div class="faq-style-wrap" id="faq-five">
                        <?php foreach ($faqItems as $fi => $faq):
                            $faqTitle = sec($sections, $faq['slug'], 'title') ?: t($faq['titleKey']);
                            $faqText  = sec($sections, $faq['slug'], 'content') ?: t($faq['textKey']);
                            $collapseId = 'faq-collapse' . ($fi + 1);
                            $isFirst    = $fi === 0;
                        ?>
                        <div class="panel panel-default">
                            <div class="panel-heading"><h5 class="panel-title">
                                <a class="<?= $isFirst ? '' : 'collapsed' ?>" role="button"
                                   data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>"
                                   <?= $isFirst ? 'aria-expanded="true"' : '' ?>>
                                    <span class="button-faq"></span><?= $faqTitle ?>
                                </a></h5>
                            </div>
                            <div id="<?= $collapseId ?>" class="collapse <?= $isFirst ? 'show' : '' ?>" data-bs-parent="#faq-five">
                                <div class="panel-body"><p><?= $faqText ?></p></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="testimonials-area">
                        <div class="faq-client_title"><h2><?= t('what_customers_say') ?></h2></div>
                        <div class="testimonial-two owl-carousel">
                            <?php if (!empty($featuredReviews)): ?>
                                <?php foreach ($featuredReviews as $fr):
                                    $frRole = $fr['kind'] === 'product' && $fr['part_name']
                                        ? t('reviews') . ': ' . $fr['part_name']
                                        : t('shop_reviews');
                                ?>
                                <div class="testimonial-wrap-two text-center">
                                    <div class="quote-container">
                                        <div style="font-size:1.1rem;margin-bottom:10px;"><?= starsHtml((float)$fr['rating']) ?></div>
                                        <div class="testimonials-text"><p><?= sanitize($fr['comment']) ?></p></div>
                                        <div class="author">
                                            <h6><?= sanitize($fr['name']) ?></h6>
                                            <p><?= sanitize($frRole) ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($testimonials as $tm):
                                    $lang = getLang();
                                    $s    = $sections[$tm['slug']] ?? null;
                                    $tmName = $s ? ((!empty($s['title_'.$lang]) ? $s['title_'.$lang] : $s['title_ru']) ?: '') : t($tm['slug'] . '_name');
                                    $tmRole = $s ? ((!empty($s['subtitle_'.$lang]) ? $s['subtitle_'.$lang] : $s['subtitle_ru']) ?: '') : '';
                                    $tmText = $s ? ((!empty($s['content_'.$lang]) ? $s['content_'.$lang] : $s['content_ru']) ?: '') : t($tm['slug'] . '_text');
                                    $tmImg  = ($s && !empty($s['image'])) ? $s['image'] : APP_URL . '/assets/img/about/' . $tm['img'];
                                ?>
                                <div class="testimonial-wrap-two text-center">
                                    <div class="quote-container">
                                        <div class="quote-image"><img src="<?= sanitize($tmImg) ?>" alt="<?= sanitize($tmName) ?>"></div>
                                        <div class="testimonials-text"><p><?= sanitize($tmText) ?></p></div>
                                        <div class="author">
                                            <h6><?= sanitize($tmName) ?></h6>
                                            <?php if ($tmRole): ?><p><?= sanitize($tmRole) ?></p><?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
