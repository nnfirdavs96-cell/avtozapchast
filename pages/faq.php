<?php
require_once dirname(__DIR__) . '/config/config.php';
$pageTitle = t('faq') . ' — ' . getSetting('site_name');
require_once dirname(__DIR__) . '/includes/header.php';

$faqs_ru = [
    ['q'=>'Как оформить заказ?','a'=>'Выберите запчасть, добавьте в корзину, перейдите к оформлению заказа и заполните форму. После подтверждения наш менеджер свяжется с вами.'],
    ['q'=>'Как быстро осуществляется доставка?','a'=>'Доставка по Москве — 1-2 дня. По России — 3-7 рабочих дней в зависимости от региона.'],
    ['q'=>'Принимаете ли вы возврат?','a'=>'Да, мы принимаем возврат товара в течение 14 дней с момента получения при условии сохранения товарного вида.'],
    ['q'=>'Как проверить совместимость запчасти?','a'=>'Укажите VIN-номер вашего автомобиля при оформлении заказа, или свяжитесь с нашим консультантом по телефону.'],
    ['q'=>'Есть ли гарантия на запчасти?','a'=>'Да, на все запчасти предоставляется гарантия производителя. Сроки гарантии варьируются от 6 месяцев до 2 лет.'],
    ['q'=>'Как оплатить заказ?','a'=>'Мы принимаем оплату банковской картой, наличными при получении, банковским переводом.'],
    ['q'=>'Можно ли забрать заказ самовывозом?','a'=>'Да, вы можете забрать заказ со склада в Москве. Адрес: ул. Автомобильная, д. 1.'],
    ['q'=>'Работаете ли вы с юридическими лицами?','a'=>'Да, мы работаем с юрлицами. Предоставляем полный пакет документов. Свяжитесь с отделом оптовых продаж.'],
];
$faqs_tg = [
    ['q'=>'Чӣ тавр фармоиш додан мумкин аст?','a'=>'Эҳтиёт қисмро интихоб кунед, ба сабад илова кунед, ба тартиб додани фармоиш гузаред ва шаклро пур кунед.'],
    ['q'=>'Таҳвил чӣ қадар зуд сурат мегирад?','a'=>'Дар Маскав — 1-2 рӯз. Дар Русия — 3-7 рӯзи корӣ.'],
    ['q'=>'Шумо баргардониданро қабул мекунед?','a'=>'Бале, баргардонидани молро дар давоми 14 рӯз аз лаҳзаи гирифтан қабул мекунем.'],
    ['q'=>'Чӣ тавр мутобиқати эҳтиёт қисмро санҷидан мумкин аст?','a'=>'Рақами VIN-и мошинатонро ҳангоми тартиб додани фармоиш нишон диҳед.'],
    ['q'=>'Оё эҳтиёт қисмҳо кафолат доранд?','a'=>'Бале, ба ҳамаи эҳтиёт қисмҳо кафолати истеҳсолкунанда дода мешавад.'],
    ['q'=>'Чӣ тавр фармоишро пардохтан мумкин аст?','a'=>'Мо пардохти корти бонкӣ, пули нақд ва интиқоли бонкӣро қабул мекунем.'],
    ['q'=>'Гирифтани фармоишро аз анбор имкон дорад?','a'=>'Бале, шумо метавонед фармоишро аз анбор дар Маскав бигиред.'],
    ['q'=>'Оё шумо бо ашхоси ҳуқуқӣ кор мекунед?','a'=>'Бале, мо бо ашхоси ҳуқуқӣ кор мекунем.'],
];
$faqs_en = [
    ['q'=>'How to place an order?','a'=>'Choose a part, add to cart, proceed to checkout and fill the form. Our manager will contact you after confirmation.'],
    ['q'=>'How fast is delivery?','a'=>'Moscow delivery: 1-2 days. Russia-wide: 3-7 business days depending on your region.'],
    ['q'=>'Do you accept returns?','a'=>'Yes, we accept returns within 14 days of receipt, provided the product is in its original condition.'],
    ['q'=>'How to check part compatibility?','a'=>'Provide your VIN number when ordering, or contact our consultant by phone.'],
    ['q'=>'Is there a warranty on parts?','a'=>'Yes, all parts come with manufacturer warranty from 6 months to 2 years.'],
    ['q'=>'How can I pay?','a'=>'We accept bank cards, cash on delivery, and bank transfers.'],
    ['q'=>'Can I pick up my order?','a'=>'Yes, you can pick up from our Moscow warehouse at: ul. Avtomobilnaya, 1.'],
    ['q'=>'Do you work with legal entities?','a'=>'Yes, we work with companies and provide full documentation.'],
];

$lang = getLang();
$faqs = $lang === 'tg' ? $faqs_tg : ($lang === 'en' ? $faqs_en : $faqs_ru);
?>
<?= breadcrumb([['label'=>t('home'),'url'=>APP_URL.'/index.php'],['label'=>t('faq')]]) ?>

<div class="faq_area section_padding" style="padding:60px 0">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-lg-9">
        <div class="section_title text-center mb-5"><h2><?= t('faq') ?></h2></div>
        <div class="accordion" id="faqAccordion">
          <?php foreach ($faqs as $i => $faq): ?>
          <div class="card" style="margin-bottom:8px;border:1px solid #e0e0e0;border-radius:8px;overflow:hidden">
            <div class="card-header" style="background:#fff;padding:0">
              <h2 class="mb-0">
                <button class="btn btn-link btn-block text-left <?= $i!==0?'collapsed':'' ?>" type="button" data-bs-toggle="collapse" data-bs-target="#faq<?= $i ?>" aria-expanded="<?= $i===0?'true':'false' ?>" style="padding:16px 20px;font-weight:600;color:#333;text-decoration:none;font-size:0.95rem;width:100%;display:flex;justify-content:space-between;align-items:center">
                  <?= sanitize($faq['q']) ?>
                  <i class="ion-ios-arrow-down" style="transition:transform 0.3s"></i>
                </button>
              </h2>
            </div>
            <div id="faq<?= $i ?>" class="collapse <?= $i===0?'show':'' ?>" data-bs-parent="#faqAccordion">
              <div class="card-body" style="padding:16px 20px;color:#666;line-height:1.7;font-size:0.9rem">
                <?= sanitize($faq['a']) ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="text-center mt-5" style="background:#f9f9f9;padding:30px;border-radius:10px">
          <h4><?= t('contact_us') ?></h4>
          <p style="color:#666"><?= t('working_hours') ?>: Пн–Пт 9:00–20:00</p>
          <a href="<?= APP_URL ?>/pages/contact.php" class="button"><?= t('contact') ?></a>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
