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
    `content_ru`  TEXT DEFAULT NULL,
    `content_tg`  TEXT DEFAULT NULL,
    `content_en`  TEXT DEFAULT NULL,
    `image`       VARCHAR(255) DEFAULT NULL,
    `sort_order`  SMALLINT DEFAULT 0,
    `is_active`   TINYINT(1) DEFAULT 1,
    `updated_at`  DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default about sections
INSERT IGNORE INTO `site_sections` (`slug`,`section_group`,`title_ru`,`title_tg`,`title_en`,`content_ru`,`sort_order`) VALUES
('about_hero',    'about', 'О компании',       'Дар бораи мо',    'About Us',        'Мы — команда профессионалов с 10-летним опытом в сфере автозапчастей. Наша миссия — обеспечить каждого автовладельца качественными деталями по честной цене.', 1),
('about_team',    'about', 'Наша команда',     'Дастаи мо',       'Our Team',        'Наши специалисты — опытные технические консультанты, которые помогут подобрать нужную деталь для любого автомобиля.', 2),
('about_reviews', 'about', 'Отзывы клиентов',  'Назари мизоҷон',  'Customer Reviews','Более 5000 довольных клиентов уже оценили качество нашей продукции и уровень сервиса.', 3),
('about_stores',  'about', 'Наши магазины',    'Мағозаҳои мо',    'Our Stores',      'Мы работаем в нескольких городах Таджикистана. Все адреса и время работы на странице контактов.', 4);

SELECT 'Migration complete' AS status;
