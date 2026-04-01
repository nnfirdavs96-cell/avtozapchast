</main><!-- /#main-content -->

<footer class="site-footer">
  <div class="footer-inner">
    <div class="footer-grid">

      <!-- Brand column -->
      <div class="footer-col footer-brand">
        <div class="footer-logo">
          <div class="footer-logo-icon"></div>
          АВТО<span>ЗАПЧАСТЬ</span>
        </div>
        <p class="footer-desc">
          Профессиональный подбор и продажа автозапчастей для легковых и грузовых автомобилей. Оригинальные и аналоговые запчасти с гарантией качества.
        </p>
        <div class="footer-social">
          <a href="#" class="social-btn" title="Telegram">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M12 0C5.373 0 0 5.373 0 12s5.373 12 12 12 12-5.373 12-12S18.627 0 12 0zm5.562 8.248l-2.013 9.483c-.145.658-.537.818-1.084.508l-3-2.21-1.447 1.394c-.16.16-.295.295-.605.295l.213-3.053 5.56-5.023c.242-.213-.054-.333-.373-.12l-6.871 4.326-2.962-.924c-.643-.204-.657-.643.136-.953l11.57-4.461c.537-.194 1.006.131.876.738z"/></svg>
          </a>
          <a href="#" class="social-btn" title="WhatsApp">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
          </a>
        </div>
      </div>

      <!-- Quick links -->
      <div class="footer-col">
        <h4 class="footer-heading">Каталог</h4>
        <ul class="footer-links">
          <li><a href="<?= APP_URL ?>/catalog/index.php?category=dvigatel">Двигатель</a></li>
          <li><a href="<?= APP_URL ?>/catalog/index.php?category=tormoznaya-sistema">Тормозная система</a></li>
          <li><a href="<?= APP_URL ?>/catalog/index.php?category=podveska">Подвеска</a></li>
          <li><a href="<?= APP_URL ?>/catalog/index.php?category=elektrika">Электрика</a></li>
          <li><a href="<?= APP_URL ?>/catalog/index.php?category=kuzov">Кузов</a></li>
          <li><a href="<?= APP_URL ?>/catalog/index.php?category=transmissiya">Трансмиссия</a></li>
        </ul>
      </div>

      <!-- Info -->
      <div class="footer-col">
        <h4 class="footer-heading">Информация</h4>
        <ul class="footer-links">
          <li><a href="<?= APP_URL ?>/index.php">Главная</a></li>
          <li><a href="<?= APP_URL ?>/catalog/index.php">Все товары</a></li>
          <li><a href="<?= APP_URL ?>/auth/login.php">Войти</a></li>
          <li><a href="<?= APP_URL ?>/auth/register.php">Регистрация</a></li>
          <?php if (isLoggedIn()): ?>
          <li><a href="<?= APP_URL ?>/buyer/orders.php">Мои заказы</a></li>
          <?php endif; ?>
        </ul>
      </div>

      <!-- Contacts -->
      <div class="footer-col">
        <h4 class="footer-heading">Контакты</h4>
        <div class="footer-contact-list">
          <div class="footer-contact-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16.92v3a2 2 0 01-2.18 2 19.79 19.79 0 01-8.63-3.07A19.5 19.5 0 013.07 9.81 19.79 19.79 0 01.04 1.18 2 2 0 012 0h3a2 2 0 012 1.72c.127.96.361 1.903.7 2.81a2 2 0 01-.45 2.11L6.91 7.91a16 16 0 006.72 6.72l1.27-1.27a2 2 0 012.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0122 16.92z"/></svg>
            <span>+7 (800) 555-35-35</span>
          </div>
          <div class="footer-contact-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
            <span>info@avtozapchast.ru</span>
          </div>
          <div class="footer-contact-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
            <span>г. Москва, ул. Автомобильная, д. 1</span>
          </div>
          <div class="footer-contact-item">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span>Пн–Пт: 9:00–20:00<br>Сб–Вс: 10:00–18:00</span>
          </div>
        </div>
      </div>

    </div><!-- /.footer-grid -->
  </div><!-- /.footer-inner -->

  <div class="footer-bottom">
    <div class="footer-inner" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;">
      <span class="footer-copy">&copy; <?= date('Y') ?> АвтоЗапчасть. Все права защищены.</span>
      <span class="footer-tech" style="font-family:var(--font-mono);font-size:0.7rem;color:var(--text-muted);">PHP/MySQL · PDO · CSRF Protected</span>
    </div>
  </div>
</footer>

<script src="<?= APP_URL ?>/assets/js/main.js"></script>
</body>
</html>

<style>
.site-footer {
  background: var(--bg-secondary);
  border-top: 1px solid var(--border);
  margin-top: 80px;
}
.footer-inner {
  max-width: 1440px;
  margin: 0 auto;
  padding: 0 24px;
}
.footer-grid {
  display: grid;
  grid-template-columns: 2fr 1fr 1fr 1.5fr;
  gap: 48px;
  padding: 56px 0 40px;
}
@media (max-width: 900px) {
  .footer-grid { grid-template-columns: 1fr 1fr; gap: 32px; padding: 36px 0 24px; }
}
@media (max-width: 560px) {
  .footer-grid { grid-template-columns: 1fr; gap: 24px; }
}
.footer-logo {
  font-family: var(--font-display);
  font-size: 1.6rem;
  color: var(--text-primary);
  letter-spacing: 2px;
  display: flex;
  align-items: center;
  gap: 8px;
  margin-bottom: 14px;
}
.footer-logo span { color: var(--accent); }
.footer-logo-icon {
  width: 26px;
  height: 26px;
  background: var(--accent);
  clip-path: polygon(50% 0%, 100% 25%, 100% 75%, 50% 100%, 0% 75%, 0% 25%);
}
.footer-desc {
  color: var(--text-muted);
  font-size: 0.8rem;
  line-height: 1.7;
  margin-bottom: 16px;
}
.footer-social { display: flex; gap: 8px; }
.social-btn {
  width: 34px;
  height: 34px;
  background: var(--bg-card);
  border: 1px solid var(--border);
  border-radius: 4px;
  display: flex;
  align-items: center;
  justify-content: center;
  color: var(--text-muted);
  text-decoration: none;
  transition: color 0.2s, border-color 0.2s;
}
.social-btn:hover { color: var(--accent); border-color: var(--accent); }
.footer-heading {
  font-family: var(--font-mono);
  font-size: 0.7rem;
  text-transform: uppercase;
  letter-spacing: 0.12em;
  color: var(--accent);
  margin-bottom: 16px;
  font-weight: 600;
}
.footer-links { list-style: none; padding: 0; margin: 0; }
.footer-links li { margin-bottom: 8px; }
.footer-links a {
  color: var(--text-muted);
  text-decoration: none;
  font-size: 0.825rem;
  transition: color 0.2s;
}
.footer-links a:hover { color: var(--text-primary); }
.footer-contact-list { display: flex; flex-direction: column; gap: 10px; }
.footer-contact-item {
  display: flex;
  gap: 10px;
  align-items: flex-start;
  color: var(--text-muted);
  font-size: 0.8rem;
  line-height: 1.5;
}
.footer-contact-item svg { flex-shrink: 0; margin-top: 2px; color: var(--accent); }
.footer-bottom {
  border-top: 1px solid var(--border);
  padding: 16px 0;
}
.footer-copy {
  color: var(--text-muted);
  font-size: 0.78rem;
  font-family: var(--font-body);
}
</style>
