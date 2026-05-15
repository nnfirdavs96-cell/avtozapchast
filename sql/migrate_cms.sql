-- CMS: blog categories + about page sections
-- Run: mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_cms.sql

-- 1. Add category to blog_posts
ALTER TABLE blog_posts
    ADD COLUMN IF NOT EXISTS category VARCHAR(50) NOT NULL DEFAULT 'news'
    AFTER slug;

-- 2. site_sections table (about page CMS)
CREATE TABLE IF NOT EXISTS `site_sections` (
    `id`          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `slug`        VARCHAR(100) NOT NULL UNIQUE,
    `section_group` VARCHAR(50) NOT NULL DEFAULT 'about',
    `title_ru`    VARCHAR(255) NOT NULL DEFAULT '',
    `title_tg`    VARCHAR(255) NOT NULL DEFAULT '',
    `title_en`    VARCHAR(255) NOT NULL DEFAULT '',
    `subtitle_ru` VARCHAR(255) NOT NULL DEFAULT '',
    `subtitle_tg` VARCHAR(255) NOT NULL DEFAULT '',
    `subtitle_en` VARCHAR(255) NOT NULL DEFAULT '',
    `content_ru`  TEXT DEFAULT NULL,
    `content_tg`  TEXT DEFAULT NULL,
    `content_en`  TEXT DEFAULT NULL,
    `image`       VARCHAR(255) DEFAULT NULL,
    `sort_order`  SMALLINT DEFAULT 0,
    `is_active`   TINYINT(1) DEFAULT 1,
    `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add subtitle columns if table already exists (idempotent)
ALTER TABLE `site_sections`
    ADD COLUMN IF NOT EXISTS `subtitle_ru` VARCHAR(255) NOT NULL DEFAULT '' AFTER `title_en`,
    ADD COLUMN IF NOT EXISTS `subtitle_tg` VARCHAR(255) NOT NULL DEFAULT '' AFTER `subtitle_ru`,
    ADD COLUMN IF NOT EXISTS `subtitle_en` VARCHAR(255) NOT NULL DEFAULT '' AFTER `subtitle_tg`;

-- 3. Default about page main sections
INSERT IGNORE INTO `site_sections` (`slug`,`section_group`,`title_ru`,`title_tg`,`title_en`,`content_ru`,`sort_order`) VALUES
('about_hero',    'about', 'О компании',       'Дар бораи мо',    'About Us',        'Мы — команда профессионалов с 10-летним опытом в сфере автозапчастей. Наша миссия — обеспечить каждого автовладельца качественными деталями по честной цене.', 1),
('about_team',    'about', 'Наша команда',     'Дастаи мо',       'Our Team',        'Наши специалисты — опытные технические консультанты, которые помогут подобрать нужную деталь для любого автомобиля.', 2),
('about_reviews', 'about', 'Отзывы клиентов',  'Назари мизоҷон',  'Customer Reviews','Более 5000 довольных клиентов уже оценили качество нашей продукции и уровень сервиса.', 3),
('about_stores',  'about', 'Наши магазины',    'Мағозаҳои мо',    'Our Stores',      'Мы работаем в нескольких городах Таджикистана. Все адреса и время работы на странице контактов.', 4);

-- 4. Signature image section
INSERT IGNORE INTO `site_sections` (`slug`,`section_group`,`title_ru`,`title_tg`,`title_en`,`sort_order`) VALUES
('about_signature', 'about', 'Подпись (О компании)', 'Имзо', 'Signature', 5);

-- 5. Benefit icons (Преимущества)
INSERT IGNORE INTO `site_sections` (`slug`,`section_group`,`title_ru`,`title_tg`,`title_en`,`content_ru`,`content_tg`,`content_en`,`sort_order`) VALUES
('about_benefit_1', 'benefits', 'Бесплатная Доставка', 'Тавсилоти ройгон', 'Free Delivery',
 'Бесплатная доставка на заказы от определённой суммы по всему Таджикистану.',
 'Расонидани ройгон барои фармоишҳо аз маблағи муайян дар тамоми Тоҷикистон.',
 'Free delivery on orders above a set amount across Tajikistan.', 1),
('about_benefit_2', 'benefits', 'Безопасная Оплата',   'Пардохти бехатар', 'Secure Payment',
 'Принимаем все виды оплаты. Ваши данные надёжно защищены.',
 'Ҳама намудҳои пардохтро қабул мекунем. Маълумоти шумо ба таври эътимоднок муҳофизат карда мешавад.',
 'We accept all payment types. Your data is securely protected.', 2),
('about_benefit_3', 'benefits', 'Гарантия Качества',   'Кафолати сифат',   'Quality Guarantee',
 'Все запчасти проходят проверку качества. Гарантия на все товары.',
 'Ҳамаи қитъаҳо санҷиши сифатро мегузаранд. Кафолат барои ҳамаи молҳо.',
 'All parts pass quality checks. Warranty on all products.', 3);

-- 6. FAQ accordion items
INSERT IGNORE INTO `site_sections` (`slug`,`section_group`,`title_ru`,`title_tg`,`title_en`,`content_ru`,`content_tg`,`content_en`,`sort_order`) VALUES
('about_faq_1', 'faq', 'Быстрая Доставка', 'Расонидани зуд', 'Fast Delivery',
 'Мы доставляем запчасти в течение 1-3 рабочих дней по Душанбе и 3-7 дней по всему Таджикистану.',
 'Мо қитъаҳоро дар тӯли 1-3 рӯзи корӣ дар Душанбе ва 3-7 рӯз дар тамоми Тоҷикистон месупорем.',
 'We deliver parts within 1-3 business days in Dushanbe and 3-7 days across Tajikistan.', 1),
('about_faq_2', 'faq', '10 лет на рынке', '10 сол дар бозор', '10 Years on Market',
 'Более 10 лет мы помогаем автовладельцам Таджикистана найти нужные запчасти по честным ценам.',
 'Зиёда аз 10 сол мо ба соҳибони автомобилҳои Тоҷикистон дар ёфтани қитъаҳои дуруст ба нархҳои одилона кӯмак мекунем.',
 'For over 10 years we have helped Tajikistan car owners find the right parts at fair prices.', 2),
('about_faq_3', 'faq', 'Гарантия Качества', 'Кафолати сифат', 'Quality Guarantee',
 'Мы работаем только с проверенными поставщиками и предоставляем гарантию на все товары.',
 'Мо танҳо бо таъминкунандагони санҷидашуда кор мекунем ва барои ҳамаи молҳо кафолат медиҳем.',
 'We work only with verified suppliers and provide warranty on all products.', 3),
('about_faq_4', 'faq', 'Безопасная Оплата', 'Пардохти бехатар', 'Secure Payment',
 'Принимаем наличные, банковские карты и онлайн-переводы. Все транзакции защищены.',
 'Мо пули нақд, корти бонкӣ ва интиқоли онлайнро қабул мекунем. Ҳамаи муомилаҳо муҳофизат карда мешаванд.',
 'We accept cash, bank cards and online transfers. All transactions are secured.', 4);

-- 7. Testimonials (title=name, subtitle=role, content=text, image=photo)
INSERT IGNORE INTO `site_sections` (`slug`,`section_group`,`title_ru`,`title_tg`,`title_en`,`subtitle_ru`,`subtitle_tg`,`subtitle_en`,`content_ru`,`content_tg`,`content_en`,`sort_order`) VALUES
('about_testimonial_1', 'testimonials',
 'Алишер Каримов',  'Алишер Каримов',  'Alisher Karimov',
 'Постоянный клиент', 'Мизоҷи доимӣ', 'Regular Customer',
 'Отличный сервис! Заказал тормозные колодки — доставили на следующий день. Обязательно буду заказывать снова.',
 'Хидмати аъло! Дискҳои тормузро фармудам — рӯзи дигар оварданд. Ҳатман дубора фармоиш хоҳам кард.',
 'Excellent service! Ordered brake pads — delivered the next day. Will definitely order again.', 1),
('about_testimonial_2', 'testimonials',
 'Фарида Рахимова', 'Фарида Раҳимова', 'Farida Rahimova',
 'Владелец автомобиля', 'Соҳиби автомобил', 'Car Owner',
 'Качество запчастей на высоте. Цены честные, менеджеры помогли с выбором. Рекомендую всем!',
 'Сифати қитъаҳо дар сатҳи баланд. Нархҳо одилона, менеҷерон дар интихоб кӯмак карданд. Ба ҳама тавсия медиҳам!',
 'Top quality parts. Fair prices, managers helped with selection. I recommend to everyone!', 2),
('about_testimonial_3', 'testimonials',
 'Бехзод Назаров',  'Беҳзод Назаров',  'Behzod Nazarov',
 'Автомеханик',     'Механики автомобил', 'Auto Mechanic',
 'Пользуюсь этим магазином уже 3 года. Никогда не подводили — всегда есть нужные детали в наличии.',
 'Аз ин мағоза 3 сол боз истифода мебарам. Ҳеҷ гоҳ нокомӣ надоданд — ҳамеша қитъаҳои заруриро доранд.',
 'Using this store for 3 years already. Never let me down — the right parts are always in stock.', 3);

SELECT 'Migration complete' AS status;
