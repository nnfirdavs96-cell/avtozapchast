<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('about') . ' — ' . getSetting('site_name');

// Load CMS sections from DB
$db = getDB();
$sectionsRaw = $db->query(
    "SELECT * FROM site_sections WHERE section_group='about' AND is_active=1 ORDER BY sort_order, id"
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
                                <img src="<?= APP_URL ?>/assets/img/about/about-us-signature.png" alt="">
                            </div>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>

        <!-- ── Преимущества ──────────────────────────────────── -->
        <div class="choseus_area">
            <div class="row">
                <div class="col-lg-4 col-md-4">
                    <div class="single_chose">
                        <div class="chose_icone"><img src="<?= APP_URL ?>/assets/img/about/About_icon1.png" alt=""></div>
                        <div class="chose_content"><h3><?= t('free_delivery') ?></h3><p><?= t('free_delivery_text') ?></p></div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="single_chose">
                        <div class="chose_icone"><img src="<?= APP_URL ?>/assets/img/about/About_icon2.png" alt=""></div>
                        <div class="chose_content"><h3><?= t('secure_payment') ?></h3><p><?= t('secure_payment_text') ?></p></div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="single_chose">
                        <div class="chose_icone"><img src="<?= APP_URL ?>/assets/img/about/About_icon3.png" alt=""></div>
                        <div class="chose_content"><h3><?= t('quality_guarantee') ?></h3><p><?= t('quality_text') ?></p></div>
                    </div>
                </div>
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
        <div class="faq-client-say-area" id="reviews">
            <div class="row">
                <div class="col-lg-6 col-md-6">
                    <div class="faq-client_title"><h2><?= t('what_we_do_for_you') ?></h2></div>
                    <div class="faq-style-wrap" id="faq-five">
                        <div class="panel panel-default">
                            <div class="panel-heading"><h5 class="panel-title">
                                <a class="" role="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse1" aria-expanded="true">
                                    <span class="button-faq"></span><?= t('fast_delivery') ?>
                                </a></h5>
                            </div>
                            <div id="faq-collapse1" class="collapse show" data-bs-parent="#faq-five">
                                <div class="panel-body"><p><?= t('fast_delivery_text') ?></p></div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading"><h5 class="panel-title">
                                <a class="collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse2">
                                    <span class="button-faq"></span><?= t('years_in_business') ?>
                                </a></h5>
                            </div>
                            <div id="faq-collapse2" class="collapse" data-bs-parent="#faq-five">
                                <div class="panel-body"><p><?= t('years_in_business_text') ?></p></div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading"><h5 class="panel-title">
                                <a class="collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse3">
                                    <span class="button-faq"></span><?= t('quality_guarantee') ?>
                                </a></h5>
                            </div>
                            <div id="faq-collapse3" class="collapse" data-bs-parent="#faq-five">
                                <div class="panel-body"><p><?= t('quality_text') ?></p></div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading"><h5 class="panel-title">
                                <a class="collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse4">
                                    <span class="button-faq"></span><?= t('secure_payment') ?>
                                </a></h5>
                            </div>
                            <div id="faq-collapse4" class="collapse" data-bs-parent="#faq-five">
                                <div class="panel-body"><p><?= t('secure_payment_text') ?></p></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="testimonials-area">
                        <div class="faq-client_title"><h2><?= t('what_customers_say') ?></h2></div>
                        <div class="testimonial-two owl-carousel">
                            <div class="testimonial-wrap-two text-center">
                                <div class="quote-container">
                                    <div class="quote-image"><img src="<?= APP_URL ?>/assets/img/about/testimonial1.jpg" alt=""></div>
                                    <div class="testimonials-text"><p><?= t('testimonial1_text') ?></p></div>
                                    <div class="author"><h6><?= t('testimonial1_name') ?></h6><p><?= t('testimonial1_role') ?></p></div>
                                </div>
                            </div>
                            <div class="testimonial-wrap-two text-center">
                                <div class="quote-container">
                                    <div class="quote-image"><img src="<?= APP_URL ?>/assets/img/about/testimonial2.jpg" alt=""></div>
                                    <div class="testimonials-text"><p><?= t('testimonial2_text') ?></p></div>
                                    <div class="author"><h6><?= t('testimonial2_name') ?></h6><p><?= t('testimonial2_role') ?></p></div>
                                </div>
                            </div>
                            <div class="testimonial-wrap-two text-center">
                                <div class="quote-container">
                                    <div class="quote-image"><img src="<?= APP_URL ?>/assets/img/about/testimonial3.jpg" alt=""></div>
                                    <div class="testimonials-text"><p><?= t('testimonial3_text') ?></p></div>
                                    <div class="author"><h6><?= t('testimonial3_name') ?></h6><p><?= t('testimonial3_role') ?></p></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
