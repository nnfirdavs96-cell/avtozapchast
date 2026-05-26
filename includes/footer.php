<?php
$sitePhone   = isset($sitePhone) ? $sitePhone : getSetting('site_phone', '+7 (800) 555-35-35');
$siteEmail   = getSetting('site_email', 'info@avtozapchast.ru');
$siteAddress = getSetting('site_address', 'г. Москва, ул. Автомобильная, д. 1');
$siteTg      = getSetting('site_telegram', '');
$siteWa      = getSetting('site_whatsapp', '');
?>

<!-- footer widgets -->
<footer class="footer_widgets">
    <!-- shipping -->
    <div class="shipping_area">
        <div class="container">
            <div class="shipping_inner">
                <div class="single_shipping">
                    <div class="shipping_icone"><img src="<?= APP_URL ?>/assets/img/about/shipping1.png" alt=""></div>
                    <div class="shipping_content"><h4><?= t('free_delivery') ?></h4><p><?= t('free_delivery_text') ?></p></div>
                </div>
                <div class="single_shipping">
                    <div class="shipping_icone"><img src="<?= APP_URL ?>/assets/img/about/shipping2.png" alt=""></div>
                    <div class="shipping_content"><h4><?= t('secure_payment') ?></h4><p><?= t('secure_payment_text') ?></p></div>
                </div>
                <div class="single_shipping">
                    <div class="shipping_icone"><img src="<?= APP_URL ?>/assets/img/about/shipping3.png" alt=""></div>
                    <div class="shipping_content"><h4><?= t('returns') ?></h4><p><?= t('returns_text') ?></p></div>
                </div>
                <div class="single_shipping">
                    <div class="shipping_icone"><img src="<?= APP_URL ?>/assets/img/about/shipping4.png" alt=""></div>
                    <div class="shipping_content"><h4><?= t('support_24') ?></h4><p><?= t('support_24_text') ?></p></div>
                </div>
                <div class="single_shipping">
                    <div class="shipping_icone"><img src="<?= APP_URL ?>/assets/img/about/shipping5.png" alt=""></div>
                    <div class="shipping_content"><h4><?= t('quality_guarantee') ?></h4><p><?= t('quality_text') ?></p></div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer_top">
        <div class="container">
            <div class="row">
                <div class="col-lg-3">
                    <div class="widgets_container">
                        <h3><?= t('contact_info') ?></h3>
                        <div class="footer_contact">
                            <div class="footer_contact_inner">
                                <div class="contact_icone"><img src="<?= APP_URL ?>/assets/img/icon/icon-phone.png" alt=""></div>
                                <div class="contact_text">
                                    <p><?= t('call_us') ?>:<br><strong><a href="tel:<?= sanitize($sitePhone) ?>"><?= sanitize($sitePhone) ?></a></strong></p>
                                </div>
                            </div>
                            <p><?= sanitize($siteAddress) ?><br><a href="mailto:<?= sanitize($siteEmail) ?>"><?= sanitize($siteEmail) ?></a></p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-9">
                    <div class="footer_col_container">
                        <div class="widgets_container widget_menu">
                            <h3><?= t('information') ?></h3>
                            <div class="footer_menu">
                                <ul>
                                    <li><a href="<?= APP_URL ?>/pages/about.php"><?= t('about') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/reviews.php"><?= t('shop_reviews') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/blog.php"><?= t('blog') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/faq.php"><?= t('faq') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/contact.php"><?= t('contact') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/catalog/index.php"><?= t('shop') ?></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="widgets_container widget_menu">
                            <h3><?= t('customer_service') ?></h3>
                            <div class="footer_menu">
                                <ul>
                                    <li><a href="<?= APP_URL ?>/buyer/index.php"><?= t('my_account_menu') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/buyer/cart.php"><?= t('shopping_cart') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/buyer/wishlist.php"><?= t('wishlist') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/buyer/orders.php"><?= t('orders') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/buyer/checkout.php"><?= t('checkout') ?></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="widgets_container widget_menu">
                            <h3><?= t('my_account_menu') ?></h3>
                            <div class="footer_menu">
                                <ul>
                                    <li><a href="<?= APP_URL ?>/auth/login.php"><?= t('login') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/auth/register.php"><?= t('register') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/buyer/profile.php"><?= t('my_account') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/buyer/orders.php"><?= t('orders') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/buyer/wishlist.php"><?= t('wishlist') ?></a></li>
                                </ul>
                            </div>
                        </div>
                        <div class="widgets_container widget_menu">
                            <h3><?= t('delivery_info') ?></h3>
                            <div class="footer_menu">
                                <ul>
                                    <li><a href="<?= APP_URL ?>/pages/faq.php"><?= t('faq') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/about.php"><?= t('about') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/pages/contact.php"><?= t('contact') ?></a></li>
                                    <?php if ($siteTg): ?><li><a href="https://t.me/<?= sanitize($siteTg) ?>">Telegram</a></li><?php endif; ?>
                                    <?php if ($siteWa): ?><li><a href="https://wa.me/<?= sanitize($siteWa) ?>">WhatsApp</a></li><?php endif; ?>
                                </ul>
                            </div>
                        </div>
                        <div class="widgets_container widget_menu">
                            <h3><?= t('warehouse_stock') ?></h3>
                            <div class="footer_menu">
                                <ul>
                                    <li><a href="<?= APP_URL ?>/catalog/index.php"><?= t('new_arrivals') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/catalog/index.php?sort=price_asc"><?= t('best_sellers') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/search/index.php"><?= t('search') ?></a></li>
                                    <li><a href="<?= APP_URL ?>/catalog/index.php?in_stock=1"><?= t('in_stock') ?></a></li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer_bottom">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6 col-md-6">
                    <div class="copyright_area">
                        <p>&copy; <?= date('Y') ?> <a href="<?= APP_URL ?>/index.php" class="text-uppercase"><?= sanitize(getSetting('site_name','AutoDoc')) ?></a>. Все права защищены.</p>
                    </div>
                </div>
                <div class="col-lg-6 col-md-6">
                    <div class="footer_payment text-right">
                        <img src="<?= APP_URL ?>/assets/img/icon/payment.png" alt="Payment methods">
                    </div>
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Mazlay JS (plugins.js bundles jQuery + Bootstrap + all plugins) -->
<script>window.APP_URL = <?= json_encode(APP_URL) ?>;
window.PHONE_COUNTRIES = <?= json_encode(enabledPhoneCountries(), JSON_UNESCAPED_UNICODE) ?>;</script>
<?php
$mainJsV = @filemtime(APP_ROOT . '/assets/mazlay-js/main.js') ?: time();
$appJsV  = @filemtime(APP_ROOT . '/assets/js/app.js') ?: time();
?>
<script src="<?= MAZLAY_JS ?>/plugins.js"></script>
<script src="<?= MAZLAY_JS ?>/main.js?v=<?= $mainJsV ?>"></script>
<script src="<?= APP_URL ?>/assets/js/app.js?v=<?= $appJsV ?>"></script>
</body>
</html>
