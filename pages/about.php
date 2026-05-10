<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('about') . ' — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('about')]]) ?>

<!--about bg area start-->
<div class="about_bg_area">
    <div class="container">
        <!--about section area -->
        <section class="about_section mb-60">
            <div class="row align-items-center">
                <div class="col-12">
                    <figure>
                        <div class="about_thumb">
                            <img src="<?= APP_URL ?>/assets/img/about/about1.jpg" alt="<?= t('about') ?>">
                        </div>
                        <figcaption class="about_content">
                            <h1><?= t('about_title') ?></h1>
                            <p><?= t('about_desc') ?></p>
                            <div class="about_signature">
                                <img src="<?= APP_URL ?>/assets/img/about/about-us-signature.png" alt="">
                            </div>
                        </figcaption>
                    </figure>
                </div>
            </div>
        </section>
        <!--about section end-->

        <!--chose us area start-->
        <div class="choseus_area">
            <div class="row">
                <div class="col-lg-4 col-md-4">
                    <div class="single_chose">
                        <div class="chose_icone">
                            <img src="<?= APP_URL ?>/assets/img/about/About_icon1.png" alt="">
                        </div>
                        <div class="chose_content">
                            <h3><?= t('free_delivery') ?></h3>
                            <p><?= t('free_delivery_text') ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="single_chose">
                        <div class="chose_icone">
                            <img src="<?= APP_URL ?>/assets/img/about/About_icon2.png" alt="">
                        </div>
                        <div class="chose_content">
                            <h3><?= t('secure_payment') ?></h3>
                            <p><?= t('secure_payment_text') ?></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 col-md-4">
                    <div class="single_chose">
                        <div class="chose_icone">
                            <img src="<?= APP_URL ?>/assets/img/about/About_icon3.png" alt="">
                        </div>
                        <div class="chose_content">
                            <h3><?= t('quality_guarantee') ?></h3>
                            <p><?= t('quality_text') ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--chose us area end-->

        <!--gallery/services img area-->
        <div class="about_gallery_section mb-55">
            <div class="row">
                <div class="col-lg-4 col-md-4">
                    <article class="single_gallery_section">
                        <figure>
                            <div class="gallery_thumb">
                                <img src="<?= APP_URL ?>/assets/img/about/about2.jpg" alt="">
                            </div>
                            <figcaption class="about_gallery_content">
                                <h3><?= t('what_we_do') ?></h3>
                                <p><?= t('what_we_do_text') ?></p>
                            </figcaption>
                        </figure>
                    </article>
                </div>
                <div class="col-lg-4 col-md-4">
                    <article class="single_gallery_section">
                        <figure>
                            <div class="gallery_thumb">
                                <img src="<?= APP_URL ?>/assets/img/about/about3.jpg" alt="">
                            </div>
                            <figcaption class="about_gallery_content">
                                <h3><?= t('our_mission') ?></h3>
                                <p><?= t('our_mission_text') ?></p>
                            </figcaption>
                        </figure>
                    </article>
                </div>
                <div class="col-lg-4 col-md-4">
                    <article class="single_gallery_section">
                        <figure>
                            <div class="gallery_thumb">
                                <img src="<?= APP_URL ?>/assets/img/about/about4.jpg" alt="">
                            </div>
                            <figcaption class="about_gallery_content">
                                <h3><?= t('our_history') ?></h3>
                                <p><?= t('our_history_text') ?></p>
                            </figcaption>
                        </figure>
                    </article>
                </div>
            </div>
        </div>
        <!--gallery/services img end-->

        <!--testimonial area start-->
        <div class="faq-client-say-area">
            <div class="row">
                <div class="col-lg-6 col-md-6">
                    <div class="faq-client_title">
                        <h2><?= t('what_we_do_for_you') ?></h2>
                    </div>
                    <div class="faq-style-wrap" id="faq-five">
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title">
                                    <a id="octagon" class="collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse1" aria-expanded="true" aria-controls="faq-collapse1">
                                        <span class="button-faq"></span><?= t('fast_delivery') ?>
                                    </a>
                                </h5>
                            </div>
                            <div id="faq-collapse1" class="collapse show" aria-expanded="true" role="tabpanel" data-bs-parent="#faq-five">
                                <div class="panel-body">
                                    <p><?= t('fast_delivery_text') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title">
                                    <a class="collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse2" aria-expanded="false" aria-controls="faq-collapse2">
                                        <span class="button-faq"></span><?= t('years_in_business') ?>
                                    </a>
                                </h5>
                            </div>
                            <div id="faq-collapse2" class="collapse" aria-expanded="false" role="tabpanel" data-bs-parent="#faq-five">
                                <div class="panel-body">
                                    <p><?= t('years_in_business_text') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title">
                                    <a class="collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse3" aria-expanded="false" aria-controls="faq-collapse3">
                                        <span class="button-faq"></span><?= t('quality_guarantee') ?>
                                    </a>
                                </h5>
                            </div>
                            <div id="faq-collapse3" class="collapse" role="tabpanel" data-bs-parent="#faq-five">
                                <div class="panel-body">
                                    <p><?= t('quality_text') ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="panel panel-default">
                            <div class="panel-heading">
                                <h5 class="panel-title">
                                    <a class="collapsed" role="button" data-bs-toggle="collapse" data-bs-target="#faq-collapse4" aria-expanded="false" aria-controls="faq-collapse4">
                                        <span class="button-faq"></span><?= t('secure_payment') ?>
                                    </a>
                                </h5>
                            </div>
                            <div id="faq-collapse4" class="collapse" role="tabpanel" data-bs-parent="#faq-five">
                                <div class="panel-body">
                                    <p><?= t('secure_payment_text') ?></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="testimonials-area">
                        <div class="faq-client_title">
                            <h2><?= t('what_customers_say') ?></h2>
                        </div>
                        <div class="testimonial-two owl-carousel">
                            <div class="testimonial-wrap-two text-center">
                                <div class="quote-container">
                                    <div class="quote-image">
                                        <img src="<?= APP_URL ?>/assets/img/about/testimonial1.jpg" alt="">
                                    </div>
                                    <div class="testimonials-text">
                                        <p><?= t('testimonial1_text') ?></p>
                                    </div>
                                    <div class="author">
                                        <h6><?= t('testimonial1_name') ?></h6>
                                        <p><?= t('testimonial1_role') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="testimonial-wrap-two text-center">
                                <div class="quote-container">
                                    <div class="quote-image">
                                        <img src="<?= APP_URL ?>/assets/img/about/testimonial2.jpg" alt="">
                                    </div>
                                    <div class="testimonials-text">
                                        <p><?= t('testimonial2_text') ?></p>
                                    </div>
                                    <div class="author">
                                        <h6><?= t('testimonial2_name') ?></h6>
                                        <p><?= t('testimonial2_role') ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="testimonial-wrap-two text-center">
                                <div class="quote-container">
                                    <div class="quote-image">
                                        <img src="<?= APP_URL ?>/assets/img/about/testimonial3.jpg" alt="">
                                    </div>
                                    <div class="testimonials-text">
                                        <p><?= t('testimonial3_text') ?></p>
                                    </div>
                                    <div class="author">
                                        <h6><?= t('testimonial3_name') ?></h6>
                                        <p><?= t('testimonial3_role') ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!--testimonial area end-->
    </div>
</div>
<!--about bg area end-->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
