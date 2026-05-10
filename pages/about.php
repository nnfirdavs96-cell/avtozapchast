<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('about') . ' — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('about')]]) ?>

<div class="about_us_area section_padding" style="padding:60px 0">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-6 col-md-6 mb-4">
        <div class="about_thumb">
          <img src="<?= APP_URL ?>/assets/img/about/about1.jpg" alt="" style="width:100%;border-radius:10px">
        </div>
      </div>
      <div class="col-lg-6 col-md-6 mb-4">
        <div class="about_content">
          <div class="section_title">
            <h2><?= t('about_title') ?></h2>
          </div>
          <p><?= t('about_desc') ?></p>
          <div class="row mt-4">
            <div class="col-4 text-center">
              <div style="width:60px;height:60px;background:#d32f2f;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;color:#fff;font-size:1.5rem"><i class="icon-settings"></i></div>
              <p style="font-weight:700;margin:0">50 000+</p><p style="font-size:0.8rem;color:#888"><?= t('parts_mgmt') ?></p>
            </div>
            <div class="col-4 text-center">
              <div style="width:60px;height:60px;background:#d32f2f;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;color:#fff;font-size:1.5rem"><i class="icon-users"></i></div>
              <p style="font-weight:700;margin:0">10 000+</p><p style="font-size:0.8rem;color:#888"><?= t('users') ?></p>
            </div>
            <div class="col-4 text-center">
              <div style="width:60px;height:60px;background:#d32f2f;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;color:#fff;font-size:1.5rem"><i class="icon-award"></i></div>
              <p style="font-weight:700;margin:0">15+</p><p style="font-size:0.8rem;color:#888">Лет опыта</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Shipping features -->
<div style="background:#f9f9f9;padding:50px 0">
  <div class="container">
    <div class="section_title"><h2><?= t('our_mission') ?></h2></div>
    <div class="row">
      <?php $features = [
        ['img'=>'About_icon1.png','title'=>t('free_delivery'),'text'=>t('free_delivery_text')],
        ['img'=>'About_icon2.png','title'=>t('secure_payment'),'text'=>t('secure_payment_text')],
        ['img'=>'About_icon3.png','title'=>t('quality_guarantee'),'text'=>t('quality_text')],
      ];
      foreach ($features as $f): ?>
      <div class="col-lg-4 col-md-4 mb-4 text-center">
        <div style="background:#fff;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.06)">
          <img src="<?= APP_URL ?>/assets/img/about/<?= $f['img'] ?>" alt="" style="margin-bottom:16px">
          <h4 style="font-size:1rem;font-weight:700"><?= $f['title'] ?></h4>
          <p style="color:#777;font-size:0.875rem"><?= $f['text'] ?></p>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- Testimonials -->
<div style="padding:60px 0">
  <div class="container">
    <div class="section_title"><h2><?= t('our_team') ?></h2></div>
    <div class="row">
      <?php $testimonials = [
        ['img'=>'testimonial1.jpg','name'=>'Александр К.','text'=>'Отличный магазин! Быстрая доставка, оригинальные запчасти. Заказываю уже 3 года.'],
        ['img'=>'testimonial2.jpg','name'=>'Давлат Н.','text'=>'Хизмат хеле хуб аст. Қисмҳои сифатноки автомобилӣ бо нархи мақбул.'],
        ['img'=>'testimonial3.jpg','name'=>'Ruslan M.','text'=>'Great service! Fast shipping, quality parts for my BMW. Highly recommended!'],
      ];
      foreach ($testimonials as $t_): ?>
      <div class="col-lg-4 col-md-4 mb-4">
        <div style="background:#fff;padding:24px;border-radius:10px;border:1px solid #eee;position:relative">
          <img src="<?= APP_URL ?>/assets/img/about/quote-icon.png" alt="" style="position:absolute;top:20px;right:20px;opacity:0.15">
          <p style="font-style:italic;color:#555;font-size:0.9rem;margin-bottom:16px">"<?= sanitize($t_['text']) ?>"</p>
          <div style="display:flex;align-items:center;gap:12px">
            <img src="<?= APP_URL ?>/assets/img/about/<?= $t_['img'] ?>" alt="" style="width:48px;height:48px;border-radius:50%;object-fit:cover">
            <strong><?= sanitize($t_['name']) ?></strong>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
