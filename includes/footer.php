</main>

<?php if (empty($adminArea)): ?>
<!-- Newsletter -->
<section class="newsletter">
  <div class="container">
    <h2><?= t('newsletter') ?></h2>
    <p><?= t('newsletter_sub') ?></p>
    <form method="post" action="<?= APP_URL ?>/api/newsletter.php">
      <input type="hidden" name="csrf_token" value="<?= generateCsrfToken() ?>">
      <input type="email" name="email" placeholder="<?= t('your_email') ?>" required>
      <button type="submit"><?= t('subscribe') ?></button>
    </form>
  </div>
</section>

<!-- Footer -->
<footer class="site-footer">
  <div class="container footer-grid">
    <div class="footer-brand">
      <a href="<?= APP_URL ?>/index.php" class="logo"><span class="logo-mark">A</span> АВТО<span style="color:#C70909">DOC</span></a>
      <p>Профессиональный подбор и продажа автозапчастей в России и Таджикистане. Оригинал и аналоги от ведущих мировых производителей с гарантией качества.</p>
      <div class="social">
        <a href="https://t.me/autodoc_tj"   title="Telegram" aria-label="Telegram"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.4 0 0 5.4 0 12s5.4 12 12 12 12-5.4 12-12S18.6 0 12 0zm5.6 8.2l-2 9.5c-.1.7-.5.8-1.1.5l-3-2.2-1.4 1.4c-.2.2-.3.3-.6.3l.2-3.1 5.6-5c.2-.2-.1-.3-.4-.1l-6.9 4.3-3-1c-.6-.2-.7-.6.1-1l11.6-4.5c.5-.2 1 .1.9.7z"/></svg></a>
        <a href="https://wa.me/79161234567" title="WhatsApp" aria-label="WhatsApp"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.5 14.4c-.3-.1-1.8-.9-2-1s-.5-.1-.7.1c-.2.3-.8 1-1 1.2-.2.2-.3.2-.6.1-.3-.1-1.2-.5-2.4-1.5-.9-.8-1.5-1.8-1.7-2.1-.2-.3 0-.5.1-.6.1-.1.3-.3.4-.5.1-.2.2-.3.3-.5.1-.2 0-.4 0-.5l-.9-2.2c-.2-.6-.5-.5-.7-.5-.2 0-.4 0-.6 0-.2 0-.5.1-.8.4-.3.3-1 1-1 2.5s1.1 2.9 1.2 3.1c.1.2 2.1 3.2 5.1 4.5.7.3 1.3.5 1.7.6.7.2 1.4.2 1.9.1.6-.1 1.8-.7 2-1.4.2-.7.2-1.3.2-1.4-.1-.1-.3-.2-.6-.4M12 21.8c-1.7 0-3.4-.5-4.9-1.4l-.4-.2-3.7 1 1-3.6-.2-.4c-1-1.5-1.5-3.3-1.5-5.2 0-5.4 4.4-9.9 9.9-9.9 2.6 0 5.1 1 7 2.9 1.9 1.9 2.9 4.4 2.9 7-.1 5.4-4.5 9.8-10.1 9.8m8.4-18.3C18.2 1.2 15.2 0 12 0 5.5 0 .2 5.3.2 11.9c0 2.1.5 4.1 1.6 5.9L0 24l6.3-1.6c1.7.9 3.7 1.5 5.7 1.5 6.6 0 11.9-5.3 11.9-11.9 0-3.2-1.2-6.2-3.5-8.5"/></svg></a>
        <a href="#" title="Instagram" aria-label="Instagram"><svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C8.7 0 8.3 0 7 .1 5.7.1 4.9.3 4.1.6c-.8.3-1.5.7-2.2 1.4S.7 3.4.4 4.1C.1 4.9-.1 5.7 0 7 0 8.3 0 8.7 0 12c0 3.3 0 3.7.1 5 0 1.3.2 2.1.5 2.9.3.8.7 1.5 1.4 2.2.7.7 1.4 1.1 2.2 1.4.8.3 1.6.5 2.9.5 1.3.1 1.7.1 5 .1 3.3 0 3.7 0 5-.1 1.3 0 2.1-.2 2.9-.5.8-.3 1.5-.7 2.2-1.4.7-.7 1.1-1.4 1.4-2.2.3-.8.5-1.6.5-2.9.1-1.3.1-1.7.1-5s0-3.7-.1-5c0-1.3-.2-2.1-.5-2.9-.3-.8-.7-1.5-1.4-2.2-.7-.7-1.4-1.1-2.2-1.4-.8-.3-1.6-.5-2.9-.5C15.7 0 15.3 0 12 0zm0 2.2c3.2 0 3.6 0 4.8.1 1.2 0 1.8.2 2.2.4.6.2 1 .5 1.4.9.4.4.7.8.9 1.4.2.4.4 1 .4 2.2.1 1.2.1 1.6.1 4.8s0 3.6-.1 4.8c0 1.2-.2 1.8-.4 2.2-.2.6-.5 1-.9 1.4-.4.4-.8.7-1.4.9-.4.2-1 .4-2.2.4-1.2.1-1.6.1-4.8.1s-3.6 0-4.8-.1c-1.2 0-1.8-.2-2.2-.4-.6-.2-1-.5-1.4-.9-.4-.4-.7-.8-.9-1.4-.2-.4-.4-1-.4-2.2-.1-1.2-.1-1.6-.1-4.8s0-3.6.1-4.8c0-1.2.2-1.8.4-2.2.2-.6.5-1 .9-1.4.4-.4.8-.7 1.4-.9.4-.2 1-.4 2.2-.4 1.2-.1 1.6-.1 4.8-.1zm0 3.7a6.1 6.1 0 100 12.2 6.1 6.1 0 000-12.2zm0 10.1a4 4 0 110-8 4 4 0 010 8zm7.8-10.4a1.4 1.4 0 11-2.8 0 1.4 1.4 0 012.8 0z"/></svg></a>
      </div>
    </div>
    <div>
      <h4><?= t('catalog') ?></h4>
      <ul>
        <?php foreach (array_slice(getCategories(),0,7) as $c): if ($c['parent_id']!==null) continue; ?>
          <li><a href="<?= APP_URL ?>/catalog/index.php?category=<?= sanitize($c['slug']) ?>"><?= sanitize(tField('category',(int)$c['id'],'name',$c['name'])) ?></a></li>
        <?php endforeach; ?>
      </ul>
    </div>
    <div>
      <h4><?= t('customer_service') ?></h4>
      <ul>
        <li><a href="<?= APP_URL ?>/pages/about.php"><?= t('about') ?></a></li>
        <li><a href="<?= APP_URL ?>/pages/delivery.php"><?= t('delivery') ?></a></li>
        <li><a href="<?= APP_URL ?>/pages/payment.php"><?= t('payment') ?></a></li>
        <li><a href="<?= APP_URL ?>/pages/contacts.php"><?= t('contacts') ?></a></li>
        <li><a href="<?= APP_URL ?>/blog/index.php"><?= t('blog') ?></a></li>
        <li><a href="<?= APP_URL ?>/pages/privacy.php">Политика конфиденциальности</a></li>
        <li><a href="<?= APP_URL ?>/pages/terms.php">Условия использования</a></li>
      </ul>
    </div>
    <div class="footer-contact">
      <h4><?= t('contacts') ?></h4>
      <div>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 010 1.18 2 2 0 011.92-1h3a2 2 0 012 1.72c.13.96.36 1.9.7 2.81a2 2 0 01-.45 2.11l-1.27 1.27a16 16 0 006.6 6.6l1.27-1.27a2 2 0 012.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0122 16.92z"/></svg>
        <div>
          <strong style="color:#fff">Россия:</strong><br>+7 (800) 555-35-35<br>
          <strong style="color:#fff">Таджикистан:</strong><br>+992 92 646-46-46
        </div>
      </div>
      <div>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22 6 12 13 2 6"/></svg>
        <div>info@avtozapchast.ru<br>info@autodoc.tj</div>
      </div>
      <div>
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
        <div>г. Москва, ул. Автомобильная, 1<br>г. Душанбе, пр. Рудаки, 25</div>
      </div>
    </div>
  </div>
  <div class="container footer-bottom">
    <span>© <?= date('Y') ?> АВТОDOC. <?= t('all_rights') ?>.</span>
    <span class="footer-payments">VISA · MasterCard · МИР · СБП · Сомони</span>
  </div>
</footer>
<?php endif; ?>

<script src="<?= APP_URL ?>/assets/js/main.js?v=2"></script>
</body>
</html>
