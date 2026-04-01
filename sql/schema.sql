-- АвтоЗапчасть Database Schema
-- MySQL 5.7+

CREATE DATABASE IF NOT EXISTS avtozapchast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE avtozapchast;

-- Users table
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('buyer','admin','manager','superadmin') NOT NULL DEFAULT 'buyer',
    phone VARCHAR(20) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_email (email),
    INDEX idx_role (role)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Categories table (self-referencing for tree structure)
CREATE TABLE IF NOT EXISTS categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    parent_id INT UNSIGNED DEFAULT NULL,
    description TEXT DEFAULT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (parent_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_slug (slug),
    INDEX idx_parent (parent_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Brands table
CREATE TABLE IF NOT EXISTS brands (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    logo_path VARCHAR(255) DEFAULT NULL,
    country VARCHAR(100) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    INDEX idx_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Parts table
CREATE TABLE IF NOT EXISTS parts (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    part_number VARCHAR(100) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    brand_id INT UNSIGNED DEFAULT NULL,
    category_id INT UNSIGNED DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock INT NOT NULL DEFAULT 0,
    weight DECIMAL(8,3) DEFAULT NULL COMMENT 'kg',
    dimensions VARCHAR(100) DEFAULT NULL COMMENT 'LxWxH mm',
    images JSON DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_by INT UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (brand_id) REFERENCES brands(id) ON DELETE SET NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_part_number (part_number),
    INDEX idx_brand (brand_id),
    INDEX idx_category (category_id),
    INDEX idx_price (price),
    FULLTEXT idx_search (part_number, name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Orders table
CREATE TABLE IF NOT EXISTS orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    status ENUM('pending','processing','shipped','delivered','cancelled') NOT NULL DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    shipping_address TEXT NOT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Order items table
CREATE TABLE IF NOT EXISTS order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    unit_price DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE RESTRICT,
    INDEX idx_order (order_id),
    INDEX idx_part (part_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cart table
CREATE TABLE IF NOT EXISTS cart (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    part_id INT UNSIGNED NOT NULL,
    quantity INT NOT NULL DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (part_id) REFERENCES parts(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, part_id),
    INDEX idx_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Site settings table
CREATE TABLE IF NOT EXISTS site_settings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL UNIQUE,
    `value` TEXT DEFAULT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Users (password: "password123" for all test users)
-- Hash generated with: password_hash('password123', PASSWORD_DEFAULT)
INSERT INTO users (username, email, password_hash, role, phone, is_active) VALUES
('buyer_test', 'buyer@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '+7 (999) 111-11-11', 1),
('manager_test', 'manager@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', '+7 (999) 222-22-22', 1),
('admin_test', 'admin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', '+7 (999) 333-33-33', 1),
('superadmin', 'superadmin@test.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', '+7 (999) 000-00-00', 1),
('ivan_petrov', 'ivan@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '+7 (495) 123-45-67', 1),
('anna_sidorova', 'anna@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'buyer', '+7 (495) 987-65-43', 1);

-- Categories (root level)
INSERT INTO categories (name, slug, parent_id, description, sort_order, is_active) VALUES
('Двигатель', 'dvigatel', NULL, 'Запчасти для двигателя: фильтры, свечи, ремни, прокладки', 1, 1),
('Тормозная система', 'tormoznaya-sistema', NULL, 'Тормозные колодки, диски, суппорты, шланги', 2, 1),
('Подвеска', 'podveska', NULL, 'Амортизаторы, пружины, рычаги, сайлентблоки', 3, 1),
('Электрика', 'elektrika', NULL, 'Генераторы, стартеры, свечи зажигания, датчики', 4, 1),
('Кузов', 'kuzov', NULL, 'Кузовные панели, бамперы, зеркала, стёкла', 5, 1),
('Трансмиссия', 'transmissiya', NULL, 'КПП, сцепление, карданный вал, ШРУС', 6, 1);

-- Subcategories
INSERT INTO categories (name, slug, parent_id, description, sort_order, is_active) VALUES
('Масляные фильтры', 'maslyanie-filtry', 1, 'Масляные фильтры для всех марок авто', 1, 1),
('Воздушные фильтры', 'vozdushnie-filtry', 1, 'Воздушные фильтры двигателя', 2, 1),
('Топливные фильтры', 'toplivnie-filtry', 1, 'Топливные фильтры и насосы', 3, 1),
('Тормозные колодки', 'tormoznje-kolodki', 2, 'Передние и задние тормозные колодки', 1, 1),
('Тормозные диски', 'tormoznje-diski', 2, 'Тормозные диски вентилируемые и сплошные', 2, 1),
('Амортизаторы', 'amortizatory', 3, 'Газовые и масляные амортизаторы', 1, 1),
('Свечи зажигания', 'svechi-zagnivaniya', 4, 'Свечи зажигания и свечи накала', 1, 1);

-- Brands
INSERT INTO brands (name, slug, logo_path, country, description, is_active) VALUES
('Bosch', 'bosch', NULL, 'Германия', 'Ведущий мировой поставщик технологий и услуг', 1),
('NGK', 'ngk', NULL, 'Япония', 'Мировой лидер в производстве свечей зажигания', 1),
('Gates', 'gates', NULL, 'США', 'Производитель ремней, шлангов и гидравлических компонентов', 1),
('SKF', 'skf', NULL, 'Швеция', 'Ведущий поставщик подшипников и уплотнений', 1),
('Febi', 'febi', NULL, 'Германия', 'Производитель оригинальных запасных частей', 1),
('Brembo', 'brembo', NULL, 'Италия', 'Мировой лидер в производстве тормозных систем', 1),
('Mann-Filter', 'mann-filter', NULL, 'Германия', 'Фильтры и технические компоненты для автомобилей', 1),
('Monroe', 'monroe', NULL, 'США', 'Амортизаторы и компоненты подвески', 1);

-- Parts (20 sample parts)
INSERT INTO parts (part_number, name, description, brand_id, category_id, price, stock, weight, dimensions, is_active, created_by) VALUES
('0 280 218 116', 'Датчик массового расхода воздуха', 'Оригинальный датчик MAF для систем впрыска топлива. Совместим с VW, Audi, Skoda, Seat 2.0 TDI', 1, 4, 3850.00, 12, 0.180, '85x45x45', 1, 2),
('BP-0001', 'Тормозные колодки передние Brembo P23089', 'Высокоэффективные тормозные колодки для городского и спортивного вождения. Комплект 4 шт.', 6, 10, 4200.00, 25, 0.650, '145x60x18', 1, 2),
('GFE 1072', 'Ремень ГРМ Gates PowerGrip', 'Высококачественный ремень ГРМ. Увеличенный ресурс 150 000 км. Для Ford Focus, Mondeo 2.0', 3, 1, 1850.00, 18, 0.320, '1200x25x6', 1, 2),
('BFR6EQP', 'Свечи зажигания NGK BFR6EQP', 'Платиновые свечи зажигания NGK. Комплект 4 шт. Для двигателей объёмом 1.4-2.0', 2, 13, 2100.00, 40, 0.210, '19x19x72', 1, 2),
('W 712/95', 'Масляный фильтр Mann-Filter W 712/95', 'Оригинальный масляный фильтр. Совместим с BMW 3, 5, 7 серии, X3, X5', 7, 7, 450.00, 85, 0.180, '65x65x76', 1, 2),
('C 27 009', 'Воздушный фильтр Mann-Filter C 27 009', 'Панельный воздушный фильтр. Для VW Polo, Skoda Fabia, Seat Ibiza 1.2/1.4/1.6', 7, 8, 680.00, 60, 0.240, '240x175x45', 1, 2),
('29251', 'Подшипник ступицы SKF VKBA 1481', 'Комплект подшипника ступицы переднего колеса. Для Audi A4, A6, VW Passat', 4, 3, 3200.00, 15, 1.200, '82x82x58', 1, 2),
('23944', 'Амортизатор передний Monroe G8208', 'Газовый амортизатор передней подвески. Для Toyota Camry, Corolla 2010-2018', 8, 12, 5600.00, 8, 2.800, '520x55x55', 1, 2),
('F 026 402 085', 'Топливный фильтр Bosch F 026 402 085', 'Топливный фильтр высокого давления. Для дизельных двигателей TDI/CDI', 1, 9, 1240.00, 30, 0.280, '115x75x75', 1, 2),
('09 A 141 671', 'Комплект сцепления Febi', 'Комплект сцепления: диск, корзина, подшипник выжима. Для VW Golf IV, Bora 1.9 TDI', 5, 6, 8900.00, 5, 4.500, '230x230x120', 1, 2),
('BP-0002', 'Тормозные колодки задние Brembo P85020', 'Задние тормозные колодки. Комплект 4 шт. Для BMW 3, 5 серии', 6, 10, 3600.00, 20, 0.480, '125x55x15', 1, 2),
('SD4205', 'Тормозной диск передний Brembo 09.A390.11', 'Вентилируемый тормозной диск. Диаметр 300 мм. Для Audi A4 B8, A5', 6, 11, 4800.00, 12, 3.200, '300x300x25', 1, 2),
('0 986 494 172', 'Тормозные колодки Bosch QuietCast', 'Тормозные колодки с системой шумоподавления. Для Volkswagen Transporter', 1, 10, 2800.00, 22, 0.580, '155x65x18', 1, 2),
('6C0 698 151 A', 'Ремкомплект тормозного суппорта', 'Ремкомплект суппорта Febi: поршень, манжеты, пыльники. Передний мост', 5, 2, 1650.00, 14, 0.350, '80x80x80', 1, 2),
('LFR6AIX', 'Свечи зажигания NGK Iridium LFR6AIX', 'Иридиевые свечи зажигания. Ресурс 100 000 км. Для Honda, Toyota, Nissan', 2, 13, 3200.00, 35, 0.180, '19x19x72', 1, 2),
('T43156', 'Ремень ГРМ Gates + помпа комплект', 'Полный комплект ГРМ: ремень, помпа, натяжной ролик. Для Renault Megane 1.5 dCi', 3, 1, 5400.00, 9, 1.100, '250x200x100', 1, 2),
('0 281 002 757', 'Датчик давления топлива Bosch', 'Датчик давления топлива в рампе. Для дизельных двигателей Common Rail', 1, 4, 4200.00, 7, 0.120, '45x30x30', 1, 2),
('41126765751', 'Подшипник рулевой колонки SKF', 'Подшипник упорный рулевой колонки. Для BMW E46, E90, E91, E92', 4, 3, 1890.00, 18, 0.450, '70x70x15', 1, 2),
('32 92 6 751 413', 'Сайлентблок рычага Febi', 'Сайлентблок переднего нижнего рычага. Комплект 2 шт. Для BMW E90, E91', 5, 3, 2100.00, 28, 0.320, '65x65x45', 1, 2),
('G20240-10000', 'Масляный фильтр NGK', 'Масляный фильтр для японских автомобилей. Для Honda Civic, CR-V, Accord', 2, 7, 380.00, 95, 0.150, '60x60x65', 1, 2);

-- Site settings
INSERT INTO site_settings (`key`, `value`) VALUES
('site_name', 'АвтоЗапчасть'),
('site_email', 'info@avtozapchast.ru'),
('site_phone', '+7 (800) 123-45-67'),
('site_address', 'г. Москва, ул. Автомобильная, д. 42'),
('site_working_hours', 'Пн-Пт: 9:00-19:00, Сб: 10:00-17:00'),
('site_description', 'Интернет-магазин автозапчастей для иномарок и отечественных автомобилей'),
('currency_symbol', '₽'),
('min_order_amount', '500'),
('free_shipping_from', '5000');
