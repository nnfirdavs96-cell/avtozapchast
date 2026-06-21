# АвтоЗапчасть — Интернет-магазин автозапчастей

Полнофункциональный интернет-магазин автозапчастей на PHP с многоязычной поддержкой (RU / TG / EN), мульти-валютностью, ролевой системой и панелями управления для разных ролей.

**Технологии:** PHP 8.0+, MySQL 8.0+/MariaDB 10.4+, Nginx/Apache, Bootstrap 4, Mazlay HTML-template, jQuery, Owl Carousel.

---

## Содержание

1. [Системные требования](#системные-требования)
2. [Быстрый старт](#быстрый-старт)
3. [Подробная установка](#подробная-установка)
4. [Настройка веб-сервера](#настройка-веб-сервера)
5. [Перенос на хостинг (Timeweb) и тест-сервер](#перенос-на-хостинг-timeweb-и-тест-сервер)
6. [Аккаунты по умолчанию](#аккаунты-по-умолчанию)
7. [Структура проекта](#структура-проекта)
8. [Роли и права доступа](#роли-и-права-доступа)
9. [Функционал по ролям](#функционал-по-ролям)
10. [Доставка](#доставка)
11. [Скидки, новинки и хиты продаж](#скидки-новинки-и-хиты-продаж)
12. [SEO (Google Search Console / Яндекс.Вебмастер)](#seo-google-search-console--яндексвебмастер)
13. [API загрузки изображений](#api-загрузки-изображений)
14. [Многоязычность](#многоязычность)
15. [Валюты](#валюты)
16. [Резервное копирование](#резервное-копирование)
17. [Решение проблем](#решение-проблем)
18. [Разработка](#разработка)
19. [Архитектура](#архитектура)

---

## Системные требования

### Операционная система
- Linux: Debian 11+, Ubuntu 20.04+, CentOS 8+ (рекомендуется)
- Windows: Windows Server 2019+ / любая версия с XAMPP, OpenServer

### Программное обеспечение
| Компонент | Минимальная версия | Рекомендуется |
|-----------|-------------------|---------------|
| PHP | 8.0 | 8.2+ |
| MySQL | 8.0 | 8.0+ |
| MariaDB | 10.4 | 10.6+ |
| Nginx | 1.18 | 1.22+ |
| Apache | 2.4 | 2.4.54+ |

### PHP-расширения (обязательные)
```
pdo_mysql    — работа с базой данных
mbstring     — многоязычные строки
fileinfo     — определение MIME-типов при загрузке
zlib         — сжатие резервных копий
gd / imagick — обработка изображений (опционально)
curl         — API ЦБ РФ, склад API
json         — стандартные операции
session      — авторизация
```

### Установка PHP-расширений (Debian/Ubuntu)
```bash
apt update
apt install -y php8.2 php8.2-fpm php8.2-mysql php8.2-mbstring \
    php8.2-curl php8.2-gd php8.2-zip php8.2-xml php8.2-fileinfo
```

### Аппаратные требования
- **CPU:** 1 ядро (минимум), 2+ ядра (рекомендуется)
- **RAM:** 1 ГБ (минимум), 2+ ГБ (рекомендуется)
- **Диск:** 5 ГБ свободно (без учёта загруженных изображений и БД)

---

## Быстрый старт

Для тех, кто хочет установить за 5 минут:

```bash
# 1. Клонировать
git clone https://github.com/nnfirdavs96-cell/avtozapchast.git /var/www/html/avtozapchast
cd /var/www/html/avtozapchast

# 2. Создать БД
mysql -u root -p -e "CREATE DATABASE avtozapchast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'avtouser'@'localhost' IDENTIFIED BY 'ВАШ_ПАРОЛЬ';  -- задайте свой; в репозиторий не коммитить
GRANT ALL ON avtozapchast.* TO 'avtouser'@'localhost'; FLUSH PRIVILEGES;"

# 3. Применить схемы
for f in schema.sql schema_v2.sql schema_v3.sql schema_v4.sql; do
    mysql -u avtouser -p avtozapchast < sql/$f
done

# 4. Настроить права
chown -R www-data:www-data assets/uploads
chmod -R 755 assets/uploads
chmod -R 775 storage

# 5. Открыть в браузере
echo "Готово! Откройте http://ваш-домен/"
```

---

## Подробная установка

### Шаг 1: Клонирование репозитория

```bash
# Перейти в директорию веб-сервера
cd /var/www/html

# Клонировать репозиторий
git clone https://github.com/nnfirdavs96-cell/avtozapchast.git
cd avtozapchast

# Проверить, что всё скачалось
ls -la
```

### Шаг 2: Создание базы данных

Войти в MySQL под root:
```bash
mysql -u root -p
```

Выполнить:
```sql
-- Создать базу
CREATE DATABASE avtozapchast
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

-- Создать пользователя (замените пароль на свой!)
CREATE USER 'avtouser'@'localhost' IDENTIFIED BY 'ВАШ_ПАРОЛЬ';  -- задайте свой; в репозиторий не коммитить

-- Дать права
GRANT ALL PRIVILEGES ON avtozapchast.* TO 'avtouser'@'localhost';

-- Применить
FLUSH PRIVILEGES;

-- Выйти
EXIT;
```

### Шаг 3: Применение схем БД

Схемы применять **строго в порядке версий**:

```bash
cd /var/www/html/avtozapchast

# Версия 1: основные таблицы
mysql -u avtouser -p avtozapchast < sql/schema.sql

# Версия 2: wishlist, currencies, languages, blog_posts
mysql -u avtouser -p avtozapchast < sql/schema_v2.sql

# Версия 3: backups, warehouse_api_log
mysql -u avtouser -p avtozapchast < sql/schema_v3.sql

# Версия 4: sliders + image_path в блоге
mysql -u avtouser -p avtozapchast < sql/schema_v4.sql

# CMS: категории блога + разделы страницы «О нас» (site_sections)
mysql -u avtouser -p avtozapchast < sql/migrate_cms.sql

# Отзывы на товары (product_reviews) — применять ПОСЛЕ migrate_cms
mysql -u avtouser -p avtozapchast < sql/migrate_reviews.sql

# Отзывы о магазине + флаг витрины (shop_reviews, is_featured)
# ВАЖНО: строго ПОСЛЕ migrate_reviews.sql
mysql -u avtouser -p avtozapchast < sql/migrate_reviews_v2.sql

# VIN-поиск (декодер + аналоги)
mysql -u avtouser -p avtozapchast < sql/migrate_vin.sql
mysql -u avtouser -p avtozapchast < sql/migrate_vin_v2.sql

# Глобальная/категорийная наценка (site_settings global_markup, колонки markup_percent)
mysql -u avtouser -p avtozapchast < sql/migrate_markup.sql

# Таджикский рынок: язык/валюта/контакты по умолчанию
mysql -u avtouser -p avtozapchast < sql/migrate_tajik_market.sql

# Только сомони (TJS / СМН), отключить прочие валюты
mysql -u avtouser -p avtozapchast < sql/only_tjs_currency.sql

# Прямое ценообразование в СМН: курс TJS = 1.0 (цена в БД = цена на витрине 1:1)
mysql -u avtouser -p avtozapchast < sql/tjs_direct_pricing.sql

# Переименование сайта АвтоЗапчасть → AvtoDoc (обновляет site_settings)
mysql -u avtouser -p avtozapchast < sql/rename_to_avtodoc.sql

# Гранулярные права: таблица user_permissions (суперадмин раздаёт разделы)
mysql -u avtouser -p avtozapchast < sql/migrate_permissions.sql

# Изображение категории на главной (colonка categories.image_path)
mysql -u avtouser -p avtozapchast < sql/add_category_image.sql

# Логотип бренда/партнёра (колонка brands.logo_path)
mysql -u avtouser -p avtozapchast < sql/add_brand_logo.sql

# Аватар + сохранённый адрес доставки покупателя
# (users.avatar_path, first_name, last_name, address, city, zip_code, country)
mysql -u avtouser -p avtozapchast < sql/add_user_profile_fields.sql

# AutoEuro API (склад, поиск/заказ) — опционально, если используете внешний склад
mysql -u avtouser -p avtozapchast < sql/schema_autoeuro.sql

# Способ оплаты в заказе (orders.payment_method) — ОБЯЗАТЕЛЬНО, иначе оформление заказа падает
mysql -u avtouser -p avtozapchast < sql/add_order_payment_method.sql

# Доставка по городам (таблица delivery_zones + orders.shipping_cost)
mysql -u avtouser -p avtozapchast < sql/add_delivery_zones.sql

# Доставка по странам (delivery_zones.country + уникальный ключ city+country)
mysql -u avtouser -p avtozapchast < sql/add_delivery_zones_country.sql

# Скидки: старая цена до скидки (parts.old_price)
mysql -u avtouser -p avtozapchast < sql/add_old_price.sql
```

> ⚠️ Если выводятся ошибки `Duplicate entry` — это нормально. Это значит, что данные уже есть в базе.
>
> ⚠️ Порядок миграций отзывов важен: `migrate_reviews.sql` создаёт
> `product_reviews`, а `migrate_reviews_v2.sql` добавляет к ней колонку
> `is_featured` и создаёт `shop_reviews`. Все миграции идемпотентны
> (`IF NOT EXISTS` / `INSERT IGNORE`) — можно запускать повторно.

Проверить, что таблицы созданы:
```bash
mysql -u avtouser -p avtozapchast -e "SHOW TABLES;"
```

Должны появиться: `users`, `categories`, `brands`, `parts`, `orders`, `order_items`, `cart`, `wishlist`, `site_settings`, `currencies`, `languages`, `blog_posts`, `sliders`, `backups`, `warehouse_api_log`, `site_sections`, `product_reviews`, `shop_reviews`.

### Шаг 4: Настройка config.php

Открыть `config/config.php`:
```bash
nano config/config.php
```

**`APP_URL` определяется автоматически** (с мая 2026) — править вручную не нужно:

```php
// config/config.php — логика уже встроена:
//   • localhost / 127.0.0.1 / ::1  → APP_URL = ''            (относительные URL, локальная разработка)
//   • любой другой хост            → APP_URL = 'https://autodoc.tj' (канонический домен в продакшене)
```

> **Зачем так:** раньше `APP_URL` был пустым и в коммите, поэтому все ссылки и
> редиректы были относительными и «прилипали» к хосту, через который зашли. На
> Timeweb обратный прокси передаёт бэкенду внутренний IP (`10.230.13.107`) в
> заголовке `Host` — и весь сайт открывался по этому IP вместо домена. Авто-логика
> переживает `git pull` (ничего копировать вручную не нужно) и держит продакшен
> строго на `https://autodoc.tj`. Хелпер `redirect()` дополнительно достраивает
> относительные пути до абсолютных через `APP_URL`, чтобы прокси не мог подменить
> `Location` на внутренний IP.

Если домен сменится — поправьте единственное место в `config/config.php`
(строка с `define('APP_URL', 'https://autodoc.tj')`).

**Порт админ-панели (`ADMIN_PORT`):**
```php
// Разделы /admin, /superadmin, /manager доступны ТОЛЬКО на этом порту.
// На любом другом порту они отдают 403 (защита от случайного входа).
// Сайт для покупателей работает на всех портах как обычно.
define('ADMIN_PORT', '8888');
```
> Чтобы порт 8888 реально заработал, нужно добавить второй
> VirtualHost в Apache — см. раздел «Настройка веб-сервера → Apache →
> Отдельный порт для админ-панели». Чтобы вообще отключить разделение
> (админка снова на общем порту) — задайте `define('ADMIN_PORT', '');`.

### Шаг 5: Настройка подключения к БД (db_credentials.php)

**Не редактируйте `config/database.php`** — реквизиты подключения каждого сервера
живут в отдельном файле `config/db_credentials.php`, который **исключён из git**
(`.gitignore`). Так у dev-машины и хостинга свои настройки, и `git pull` никогда
не перетирает чужие.

Создайте `config/db_credentials.php` на каждом сервере (пароль — из панели
хостинга / своей локальной установки, **в репозиторий не коммитить**):

```php
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'ВАШ_ПОЛЬЗОВАТЕЛЬ');
define('DB_PASS', 'ВАШ_ПАРОЛЬ');      // НЕ коммитить в git
define('DB_NAME', 'ВАША_БАЗА');
```

`config/database.php` сам подключит этот файл, если он есть; иначе использует
безопасные dev-значения по умолчанию. Никаких реальных паролей в репозитории нет
и быть не должно.

### Шаг 6: Настройка прав доступа

```bash
cd /var/www/html/avtozapchast

# Владелец — пользователь веб-сервера
chown -R www-data:www-data .

# Папки для записи (загрузки)
chmod -R 755 assets/uploads
chmod -R 775 storage
chmod -R 775 storage/backups 2>/dev/null || true

# Создать недостающие подпапки
mkdir -p assets/uploads/{products,sliders,blog}
mkdir -p storage/backups
chown -R www-data:www-data assets/uploads storage
```

### Шаг 7: Проверка

```bash
# Проверить, что PHP видит все расширения
php -m | grep -E "pdo_mysql|mbstring|fileinfo|curl|gd"

# Проверить подключение к БД
php -r "require 'config/database.php'; \$db = getDB(); echo 'OK: ' . \$db->query('SELECT COUNT(*) FROM users')->fetchColumn() . ' users';"
```

Открыть в браузере: `http://ваш-сервер/`

---

## Настройка веб-сервера

### ⭐ Реальная конфигурация прод-сервера (10.230.13.107)

> Это **фактическое состояние боевого сервера** — источник истины.
> Разделы «Nginx (рекомендуется)» и «Apache» ниже — это общие примеры
> для нового развёртывания. На текущем сервере работает то, что
> описано здесь.

**Веб-сервер:** `nginx` + `php-fpm 8.2` (сокет `/run/php/php8.2-fpm.sock`).
Apache на сервере **установлен, но мёртв** (`apache2.service` failed) —
его не трогаем, он ни на что не влияет.

**Бинарники не в `PATH` рута** — вызывать по полному пути:
`/usr/sbin/nginx -t`, `systemctl reload nginx`.

#### Как было (до разбора, май 2026)

На сервере оказались **две копии** проекта, обе — git-репозитории:

| Папка | Что это | Состояние |
|-------|---------|-----------|
| `/var/www/html/` | старая копия с `.git` на ветке `claude/build-multilingual-currency-site-a12ui`, 178 несохранённых правок, **пустой** `assets/uploads/` | DocumentRoot **мёртвого** Apache |
| `/var/www/html/avtozapchast/` | актуальная копия, ветка `main`, все правки, фото в `assets/uploads/` (`blog/ products/ sliders/`) | **отдаётся nginx** |

Путаница была в том, что мёртвый Apache смотрел в `/var/www/html`, а
живой nginx — уже правильно в `/var/www/html/avtozapchast`. Поэтому
`git pull` в `avtozapchast` **всегда был корректным** и сразу попадал
на сайт. Старую копию `/var/www/html` оставили нетронутой как бэкап,
nginx её не отдаёт — на сайт не влияет.

#### Как работает сейчас

`/etc/nginx/sites-enabled/`:
- `avtozapchast` → симлинк на `/etc/nginx/sites-available/avtozapchast`
  — `server_name 10.230.13.107`, `root /var/www/html/avtozapchast`,
  слушает **`listen 80;` и `listen 8888;`**
- `avtosk.conf` — дефолтный `server_name _;`, тоже `root
  /var/www/html/avtozapchast`, только `listen 80;`

**Сайт и админка — один код + одна база `avtozapchast` (MySQL).**
Порт 80 и порт 8888 — две «двери» в один и тот же сайт. Любое
изменение в админке (товары, цены, слайдеры, фото, настройки)
пишется в общую БД/папку и сразу видно на основном сайте.

| Адрес | Что отдаётся |
|-------|--------------|
| `http://10.230.13.107/` | сайт для покупателей ✅ |
| `http://10.230.13.107/admin` | **403** — PHP-гейт `requireAdminPort()` (порт ≠ 8888) |
| `http://10.230.13.107:8888/admin` | админка (302 → форма входа) ✅ |
| `http://10.230.13.107:8888/superadmin`, `/manager` | то же |

Два уровня защиты: (1) нестандартный порт 8888 + (2) логин/пароль.

#### Что именно меняли на сервере (порт 8888)

PHP-защита (`ADMIN_PORT=8888` в `config/config.php` +
`requireAdminPort()` в `includes/functions.php`) приехала через
`git pull`. На стороне nginx — **одна строка** в активном конфиге:

```bash
# 1. Бэкап
cp /etc/nginx/sites-available/avtozapchast /etc/nginx/sites-available/avtozapchast.bak

# 2. Добавить listen 8888 сразу после первого listen 80
sed -i '0,/listen 80;/s//listen 80;\n    listen 8888;/' /etc/nginx/sites-available/avtozapchast

# 3. Проверить синтаксис (ОБЯЗАТЕЛЬНО до reload)
/usr/sbin/nginx -t            # ждём: syntax is ok / test is successful

# 4. Применить
systemctl reload nginx

# 5. Проверка
curl -s -o /dev/null -w "корень 80:   %{http_code}\n" http://localhost/index.php   # 200
curl -s -o /dev/null -w "/admin 80:   %{http_code}\n" http://localhost/admin/       # 403
curl -s -o /dev/null -w "/admin 8888: %{http_code}\n" http://localhost:8888/admin/  # 302
```

> nginx передаёт реальный порт в PHP через `fastcgi_param SERVER_PORT
> $server_port` (внутри `snippets/fastcgi-php.conf` / `fastcgi_params`),
> поэтому `requireAdminPort()` корректно различает 80 и 8888.

**Откат разделения:**
```bash
cp /etc/nginx/sites-available/avtozapchast.bak /etc/nginx/sites-available/avtozapchast
/usr/sbin/nginx -t && systemctl reload nginx
# при необходимости в коде: define('ADMIN_PORT', '') в config/config.php
```

> ⚠️ Многострочные heredoc (`cat > файл <<'EOF'`) при вставке в этот
> терминал **склеивают строки** — конфиг ломается. Менять конфиги
> только точечно (`sed`) или через файл в репозитории + `cp`.

#### Обновление сайта на этом сервере

```bash
cd /var/www/html/avtozapchast        # ← ТОЛЬКО эта папка, не /var/www/html
git pull origin main
mysql -u avtouser -p avtozapchast < sql/rename_to_avtodoc.sql  # один раз; -p запросит пароль
systemctl reload php8.2-fpm          # если правился PHP
```

---

### Nginx (рекомендуется)

Создать файл `/etc/nginx/sites-available/avtozapchast.conf`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/html/avtozapchast;
    index index.php index.html;

    client_max_body_size 10M;

    # Основной маршрутизатор
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 300;
    }

    # Запрет доступа к чувствительным файлам
    location ~ /\.(git|env|htaccess) {
        deny all;
        return 404;
    }
    location ~ /config/ {
        deny all;
        return 404;
    }
    location ~ /sql/ {
        deny all;
        return 404;
    }
    location ~ /storage/ {
        deny all;
        return 404;
    }

    # Кеширование статики
    location /assets/ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
    location ~* \.(jpg|jpeg|png|gif|webp|svg|css|js|woff|woff2|ttf|eot)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    # Логи
    access_log /var/log/nginx/avtozapchast_access.log;
    error_log /var/log/nginx/avtozapchast_error.log;
}
```

Активировать:
```bash
ln -s /etc/nginx/sites-available/avtozapchast.conf /etc/nginx/sites-enabled/
nginx -t                # проверить синтаксис
systemctl reload nginx  # применить
```

### Apache

Включить нужные модули:
```bash
a2enmod rewrite
a2enmod headers
a2enmod expires
```

Создать `/etc/apache2/sites-available/avtozapchast.conf`:

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/avtozapchast

    <Directory /var/www/html/avtozapchast>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    # Запрет доступа к чувствительным папкам
    <Directory /var/www/html/avtozapchast/config>
        Require all denied
    </Directory>
    <Directory /var/www/html/avtozapchast/sql>
        Require all denied
    </Directory>
    <Directory /var/www/html/avtozapchast/storage>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/avtozapchast_error.log
    CustomLog ${APACHE_LOG_DIR}/avtozapchast_access.log combined
</VirtualHost>
```

Активировать:
```bash
a2ensite avtozapchast.conf
apache2ctl configtest
systemctl reload apache2
```

#### Отдельный порт для админ-панели (8888)

Цель — два уровня защиты: `/admin`, `/superadmin`, `/manager`
открываются только по `:8888`, а на обычном `:80` отдают 403 (даже
зная логин/пароль). Сайт для покупателей на `:80` не меняется.

> Защита по порту уже реализована в коде (`ADMIN_PORT` +
> `requireAdminPort()` в `includes/functions.php`). Ниже — как
> сделать так, чтобы порт 8888 физически слушался Apache.

1. Добавить порт в `/etc/apache2/ports.conf`:
```apache
Listen 80
Listen 8888
```

2. Добавить второй VirtualHost в `/etc/apache2/sites-available/avtozapchast.conf`
(тот же DocumentRoot, что и `:80`):
```apache
<VirtualHost *:8888>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/avtozapchast

    <Directory /var/www/html/avtozapchast>
        Options Indexes FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    <Directory /var/www/html/avtozapchast/config>
        Require all denied
    </Directory>
    <Directory /var/www/html/avtozapchast/sql>
        Require all denied
    </Directory>
    <Directory /var/www/html/avtozapchast/storage>
        Require all denied
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/avtozapchast_8888_error.log
    CustomLog ${APACHE_LOG_DIR}/avtozapchast_8888_access.log combined
</VirtualHost>
```

3. **Обязательно** проверить конфиг до перезапуска, иначе сайт ляжет:
```bash
apache2ctl configtest      # должно быть: Syntax OK
systemctl reload apache2
```

4. Проверка:

| Адрес | Ожидаемо |
|-------|----------|
| `http://СЕРВЕР/` | сайт покупателей ✅ |
| `http://СЕРВЕР/admin` | 403 ❌ |
| `http://СЕРВЕР:8888/admin` | вход в админку ✅ |

> Откатить разделение: убрать `Listen 8888` и блок `<VirtualHost
> *:8888>`, `apache2ctl configtest`, `systemctl reload apache2`. В коде
> при необходимости — `define('ADMIN_PORT', '')` в `config/config.php`.

### .htaccess (для Apache)

Создать `.htaccess` в корне:
```apache
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php?$1 [L,QSA]

# Запрет доступа к .git и .env
<FilesMatch "^\.(htaccess|git|env)$">
    Order allow,deny
    Deny from all
</FilesMatch>
```

---

## Перенос на хостинг (Timeweb) и тест-сервер

### Схема работы: GitHub → тест → продакшн

GitHub — единственный «источник правды». Сначала проверяем изменения на тесте
(Debian), и **только после успешного теста** катим на боевой Timeweb. Так на
публичный сайт попадает исключительно проверенный код.

```
                    ┌──────────────┐
                    │    GitHub    │  ← центральное хранилище кода
                    │  (источник   │     (единственный «источник правды»)
                    │   правды)    │
                    └──────┬───────┘
                  git pull │ git pull
                 ┌─────────┴──────────┐
                 ▼                    ▼
        ┌─────────────────┐   ┌──────────────┐
        │  Debian (дома)  │   │   Timeweb    │
        │   ТЕСТ           │   │  ПУБЛИЧНЫЙ    │
        │ проверяем здесь  │   │  autodoc.tj   │
        └─────────────────┘   └──────────────┘
        сначала тестим тут     потом катим сюда
```

**Поток разработки:**

1. Дорабатываем код → коммит → пуш в GitHub (через PR в ветку `main`).
2. На **Debian (тест)** делаем `git pull origin main` → проверяем, что всё работает.
3. Только после успешного теста — на **Timeweb (прод)** делаем `git pull origin main`
   → клиенты видят изменения.

> **Ветки (по желанию, для большей надёжности).** Сейчас обе площадки тянут `main`,
> и тест на Debian идёт первым. При желании можно развести: отдельная ветка
> разработки/теста для Debian и `main` (только проверенное) для Timeweb. Не
> обязательно — решаем по ходу.

### Площадки

Проект работает на двух площадках с **одинаковым кодом из одной ветки `main`**,
но с независимыми настройками подключения к БД:

| Площадка | Что это | Веб-сервер | Особенности |
|----------|---------|-----------|-------------|
| **Debian / тест-сервер `10.230.13.107`** | площадка для отладки (тест перед проддом) | `nginx` + `php-fpm 8.2` | домен `autodoc.tj`; обратный прокси передаёт бэкенду внутренний IP в `Host` |
| **Timeweb (хостинг)** | продакшн-хостинг, публичный сайт | `Apache` (`.htaccess`) | СУБД **MySQL 8.0**; реквизиты БД — из панели Timeweb |

Главные принципы:

1. **Один источник истины — ветка `main`.** Оба сервера обновляются через
   `git pull origin main`. Никаких ручных правок кода прямо на серверах.
2. **Реквизиты БД — вне git.** Каждый сервер держит свой
   `config/db_credentials.php` (см. «Шаг 5»). `git pull` их не трогает.
3. **`APP_URL` — авто.** Определяется в `config/config.php` (localhost →
   относительные URL, иначе → `https://autodoc.tj`). Ничего копировать не нужно.
4. **Совместимость MariaDB ↔ MySQL 8.0.** Все добавления колонок идут через
   `dbAddColumnIfMissing()`, а не через MariaDB-only `ADD COLUMN IF NOT EXISTS`.

### Файлы для деплоя (`deploy/`)

| Файл | Назначение |
|------|------------|
| `.htaccess` (в корне) | Маршрутизация как nginx `try_files`, запрет доступа к `config/`, `sql/`, `deploy/`, `storage/backups/`, `.git/`; security-заголовки; PHP-лимиты загрузки (32M). Нужен для Apache на Timeweb. |
| `deploy/timeweb/config.php` | Пример прод-конфига (`APP_URL = https://autodoc.tj`). Справочный — в самом `config/config.php` уже встроена авто-логика. |
| `deploy/timeweb/database.php` | Шаблон с плейсхолдерами под реквизиты Timeweb. Реальные значения кладутся в `config/db_credentials.php`. |
| `deploy/timeweb/export-db.sh` | Снимает дамп БД (`mysqldump`, пароль запрашивается интерактивно) для импорта через phpMyAdmin Timeweb. |
| `deploy/nginx-avtozapchast.conf`, `deploy/nginx-avtozapchast-domain.conf` | Примеры конфигов nginx (по IP и по домену). |

### Первичный перенос на Timeweb

```bash
# 1. На тест-сервере снять дамп БД (пароль спросит интерактивно)
bash deploy/timeweb/export-db.sh
#    → получится autodoc_export_ГГГГММДД_ЧЧММСС.sql

# 2. Импортировать дамп в Timeweb:
#    Панель Timeweb → Базы данных → phpMyAdmin → Import → выбрать .sql

# 3. Развернуть код на Timeweb (git clone ветки main в каталог сайта)
git clone <repo-url> .
git checkout main

# 4. Создать config/db_credentials.php с реквизитами БД из панели Timeweb
#    (DB_USER / DB_PASS / DB_NAME — см. «Шаг 5»). В git НЕ коммитить.

# 5. Проверить права на запись
chmod -R 775 assets/uploads storage
```

> `.sql`-дампы и `config/db_credentials.php` исключены из git (`.gitignore`),
> поэтому пароли и выгрузки БД никогда не попадают в репозиторий.

### Обновление обоих серверов после мержа в `main`

```bash
cd <каталог-сайта>      # на Timeweb — корень сайта; на тест-сервере — /var/www/html/avtozapchast
git pull origin main
# применить новые миграции из sql/ при необходимости (mysql ... < sql/<файл>.sql; -p спросит пароль)
```

> Колонки слайдера/баннеров/категорий создаются автоматически при первом
> открытии соответствующей страницы админки (`dbAddColumnIfMissing`), отдельная
> миграция для них не нужна.

---

## Аккаунты по умолчанию

После применения `schema.sql` создаются 4 учётки:

| Роль | Email | Пароль | Назначение |
|------|-------|--------|------------|
| **Суперадмин** | superadmin@avtozapchast.ru | `Password123!` | Полный доступ ко всему |
| **Администратор** | admin@avtozapchast.ru | `Password123!` | Контент, заказы, пользователи |
| **Менеджер** | manager@avtozapchast.ru | `Password123!` | Каталог, блог |
| **Покупатель** | buyer@avtozapchast.ru | `Password123!` | Личный кабинет |

> ⚠️ **ОБЯЗАТЕЛЬНО** смените все пароли после первого входа!

Сменить пароль:
1. Войти в личный кабинет: `/auth/login.php`
2. Перейти в профиль: `/buyer/profile.php` (или соответствующую панель)
3. Ввести новый пароль

Сменить через MySQL:
```sql
UPDATE users
SET password_hash = '$2y$10$НОВЫЙ_BCRYPT_ХЕШ'
WHERE email = 'superadmin@avtozapchast.ru';
```

Сгенерировать хеш:
```bash
php -r "echo password_hash('НовыйПароль123!', PASSWORD_BCRYPT) . PHP_EOL;"
```

---

## Структура проекта

```
avtozapchast/
├── README.md               # Этот файл
├── CLAUDE.md               # Инструкции для AI-ассистента
├── index.php               # Главная страница
│
├── admin/                  # 🎛️ Панель администратора
│   ├── index.php           #   - Дашборд со статистикой
│   ├── products.php        #   - Управление товарами + загрузка до 6 фото
│   ├── sliders.php         #   - Слайдер главной (CRUD + изображения)
│   ├── orders.php          #   - Просмотр и изменение статуса заказов
│   └── users.php           #   - Просмотр пользователей
│
├── manager/                # 📝 Панель менеджера
│   ├── index.php           #   - Дашборд (товары, остатки)
│   ├── parts.php           #   - CRUD запчастей + изображения
│   ├── categories.php      #   - Иерархические категории
│   ├── brands.php          #   - Бренды (Bosch, NGK и т.д.)
│   ├── blog.php            #   - CRUD блога (RU/TG/EN + обложка + категория)
│   ├── pages.php           #   - CMS: разделы страницы «О нас»
│   └── reviews.php         #   - Модерация отзывов (товары/магазин)
│
├── superadmin/             # 🔧 Панель суперадминистратора
│   ├── index.php           #   - Главный дашборд (вся статистика)
│   ├── users.php           #   - Полный CRUD пользователей + роли + кнопка «Права доступа»
│   ├── permissions.php     #   - Гранулярные права: разделы на сотрудника
│   ├── settings.php        #   - Настройки сайта (название, контакты)
│   ├── currencies.php      #   - Валюты + курс (множитель цены) + ЦБ РФ
│   ├── languages.php       #   - Управление языками
│   ├── vin.php             #   - VIN-поиск (делегируется через права)
│   ├── warehouse.php       #   - AutoEuro API склад (делегируется через права)
│   ├── blog.php            #   - CRUD блога (расширенный)
│   ├── delivery.php        #   - Доставка: города/страны, цена, срок
│   ├── backup.php          #   - Управление резервными копиями
│   ├── backup_cron.php     #   - CLI-скрипт для cron (авто-бэкап)
│   └── _backup_lib.php     #   - Библиотека бэкапов
│
├── buyer/                  # 👤 Личный кабинет покупателя
│   ├── index.php           #   - Дашборд (последние заказы)
│   ├── orders.php          #   - История заказов + отмена заказа
│   ├── checkout.php        #   - Оформление заказа (страна/город, доставка, оплата)
│   ├── cart.php            #   - Корзина
│   ├── wishlist.php        #   - Избранное
│   └── profile.php         #   - Профиль + смена пароля
│
├── auth/                   # 🔐 Авторизация
│   ├── login.php           #   - Вход
│   ├── register.php        #   - Регистрация
│   └── logout.php          #   - Выход
│
├── api/                    # 🌐 API endpoints
│   ├── upload.php          #   - Загрузка изображений (manager/admin/superadmin)
│   ├── cart.php            #   - Корзина (add/remove/count)
│   ├── wishlist.php        #   - Избранное
│   ├── search.php          #   - Живой поиск
│   ├── vin_analogs.php     #   - VIN: аналоги запчастей
│   ├── review_submit.php   #   - Отправка отзыва на товар (после покупки)
│   └── shop_review_submit.php #  - Отправка отзыва о магазине
│
├── catalog/                # 🛒 Каталог
│   ├── index.php           #   - Список товаров с фильтрами (+ звёзды-рейтинг)
│   ├── category.php        #   - Товары категории (+ звёзды-рейтинг)
│   └── part.php            #   - Карточка товара + вкладка «Отзывы»
│
├── pages/                  # 📄 Статичные страницы
│   ├── about.php           #   - О компании (CMS site_sections + витрина отзывов)
│   ├── reviews.php         #   - Отзывы о магазине (публичная страница + форма)
│   ├── blog.php            #   - Блог (фильтр по категориям)
│   ├── blog-detail.php     #   - Статья блога
│   ├── vin.php             #   - VIN-поиск запчастей
│   ├── contact.php         #   - Контакты + карта
│   ├── faq.php             #   - Часто задаваемые вопросы
│   └── 404.php             #   - Страница не найдена
│
├── search/
│   └── index.php           # Поиск по сайту
│
├── assets/                 # 🎨 Статические ресурсы
│   ├── css/
│   │   └── custom.css      #   - Кастомные стили (поверх Mazlay)
│   ├── js/                 #   - Кастомные JS-скрипты
│   ├── img/                #   - Статичные изображения
│   ├── fonts/              #   - Шрифты
│   ├── mazlay-css/         #   - Стили шаблона Mazlay
│   ├── mazlay-js/          #   - JS шаблона Mazlay
│   └── uploads/            #   - Загруженные пользователями файлы
│       ├── products/       #     - Изображения товаров
│       ├── sliders/        #     - Изображения слайдера
│       └── blog/           #     - Обложки статей блога
│
├── config/                 # ⚙️ Конфигурация
│   ├── config.php          #   - Основной конфиг (APP_URL, paths)
│   └── database.php        #   - Подключение к БД
│
├── includes/               # 🔧 Общие компоненты
│   ├── functions.php       #   - Helper-функции (sanitize, redirect, ...)
│   ├── header.php          #   - Шапка сайта (header + nav)
│   ├── footer.php          #   - Подвал сайта
│   ├── i18n.php            #   - Интернационализация (t() helper)
│   └── currency.php        #   - Конвертация валют
│
├── lang/                   # 🌍 Языковые файлы
│   ├── ru.php              #   - Русский (по умолчанию)
│   ├── tg.php              #   - Таджикский
│   └── en.php              #   - English
│
├── sql/                    # 💾 Схемы базы данных
│   ├── schema.sql          #   - v1: основные таблицы
│   ├── schema_v2.sql       #   - v2: wishlist, currencies, blog
│   ├── schema_v3.sql       #   - v3: backups, warehouse log
│   ├── schema_v4.sql       #   - v4: sliders, blog image
│   ├── migrate_cms.sql     #   - CMS: site_sections + категория блога
│   ├── migrate_reviews.sql #   - Отзывы на товары (product_reviews)
│   ├── migrate_reviews_v2.sql # - Отзывы о магазине + is_featured
│   ├── migrate_markup.sql  #   - Глобальная/категорийная наценка
│   ├── migrate_tajik_market.sql # - Язык/валюта/контакты TJ по умолчанию
│   ├── only_tjs_currency.sql  # - Оставить только валюту TJS (СМН)
│   ├── tjs_direct_pricing.sql # - Курс TJS=1: цена в БД = цена 1:1 на витрине
│   ├── rename_to_avtodoc.sql # -  Переименование сайта → AvtoDoc (site_settings)
│   ├── migrate_permissions.sql # - Гранулярные права (user_permissions)
│   ├── schema_autoeuro.sql #   - AutoEuro API (склад, поиск/заказ)
│   └── migrate_vin*.sql    #   - VIN-поиск (декодер, аналоги)
│
└── storage/                # 💼 Хранилище
    └── backups/            #   - SQL-дампы резервных копий
```

---

## Роли и права доступа

В системе 4 роли. Каждая видит свой набор страниц:

| Возможность | Суперадмин | Администратор | Менеджер | Покупатель |
|-------------|:----------:|:-------------:|:--------:|:----------:|
| Создавать/редактировать пользователей | ✅ | ❌ | ❌ | ❌ |
| Назначать роли | ✅ | ❌ | ❌ | ❌ |
| Управление API склада | ✅ | ❌ | ❌ | ❌ |
| Настройки сайта | ✅ | ❌ | ❌ | ❌ |
| Управление валютами | ✅ | ❌ | ❌ | ❌ |
| Управление языками | ✅ | ❌ | ❌ | ❌ |
| Резервные копии БД | ✅ | ❌ | ❌ | ❌ |
| Управление товарами + фото | ✅ | ✅ | ✅ | ❌ |
| Слайдер главной страницы | ✅ | ✅ | ❌ | ❌ |
| Просмотр всех заказов | ✅ | ✅ | ❌ | ❌ |
| Изменение статуса заказа | ✅ | ✅ | ❌ | ❌ |
| Управление блогом | ✅ | ✅ | ✅ | ❌ |
| Категории и бренды | ✅ | ✅ | ✅ | ❌ |
| Корзина и избранное | ✅ | ✅ | ✅ | ✅ |
| Свой профиль | ✅ | ✅ | ✅ | ✅ |
| Свои заказы | ✅ | ✅ | ✅ | ✅ |

### Куда попадает каждая роль после входа?

| Роль | Главная панель | URL |
|------|---------------|-----|
| Покупатель | Витрина | `/index.php` |
| Менеджер | Панель менеджера | `/manager/index.php` |
| Администратор | Панель админа | `/admin/index.php` |
| Суперадмин | Панель суперадмина | `/superadmin/index.php` |

### Гранулярные права (per-user) — `superadmin/permissions.php`

Таблица выше — это **поведение по умолчанию** (`roleDefaultSections()`).
Суперадмин может переопределить доступ **для конкретного сотрудника**
(admin/manager), отметив галочками разделы на странице
`/superadmin/permissions.php` (ссылка в меню суперадмина + кнопка на
форме редактирования пользователя).

- Разделы каталога: Товары, **Наценки** (поля себестоимость/наценка),
  Слайдер, Заказы, Пользователи, Категории, Бренды, Блог, Страницы,
  Отзывы, Склад API, VIN.
- **Нет настройки** → действуют умолчания роли (всё как в таблице выше) —
  деплой ничего не меняет, пока суперадмин явно не настроит.
- **Есть настройка** → доступ строго по галочкам (можно дать менеджеру
  «Заказы» или забрать у админа «Слайдер»).
- **Суперадмин** не ограничивается никогда. «Сбросить к умолчанию»
  удаляет запись → возврат к стандартному доступу роли.

Технически: страницы вызывают `requireRole(...)` (роль/авторизация),
затем `requirePermission('section')` (грант суперадмина). Меню
фильтруется `userCan()`. Хранилище — `user_permissions` (JSON на
пользователя); код работает и без миграции (`try/catch`).

---

## Функционал по ролям

### 🔧 Суперадминистратор

**Пользователи** (`/superadmin/users.php`):
- Создавать новых пользователей с любой ролью
- Редактировать профиль (имя, email, телефон, пароль)
- Назначать роли (buyer / manager / admin / superadmin)
- Блокировать / разблокировать
- Удалять (с удалением связанных данных)
- Поиск по имени/email

**Настройки сайта** (`/superadmin/settings.php`):
- Название сайта, логотип
- Контакты (телефон, email, адрес)
- Цвета темы

**Валюты** (`/superadmin/currencies.php`):
- Добавлять/редактировать валюты
- **Автоматическое обновление курсов** с API ЦБ РФ (кнопка "Получить курсы ЦБ")
- Установить валюту по умолчанию

**API склада** (`/superadmin/warehouse.php`):
- Настройка URL и API-ключа удалённого склада в Москве
- Тест соединения с логированием ответа
- Журнал запросов к API

**Доставка** (`/superadmin/delivery.php`):
- Города и страны доставки (таблица `delivery_zones`)
- Стоимость и срок доставки по каждому городу
- `Стоимость = 0` → на витрине показывается «Уточняется», к сумме заказа **не прибавляется**
- `Стоимость > 0` → автоматически прибавляется к сумме заказа
- Включение/отключение городов; отключённые не видны покупателю
- Группировка городов по странам (выбор страны при добавлении)
- Подробнее: см. раздел [Доставка](#доставка)

**Резервные копии** (`/superadmin/backup.php`):
- Создать SQL-дамп всей БД (с gzip-сжатием)
- Скачать дамп
- Восстановить из дампа (с автоматическим pre-restore бэкапом)
- Удалить старые дампы
- Загрузить внешний дамп
- CLI-скрипт `backup_cron.php` для авто-бэкапов

### 🎛️ Администратор

**Товары** (`/admin/products.php`):
- Полный CRUD товаров
- Загрузка до 6 изображений на товар (drag-and-drop)
- Изменение порядка изображений
- "Удаление" — деактивация (soft-delete)
- Восстановление удалённых товаров
- Фильтр: только активные / все
- Поиск по артикулу, названию, бренду
- **Старая цена / до скидки** — если задана и больше цены продажи, товар попадает в «Скидки»
  с бейджем `−XX%` и зачёркнутой ценой (см. раздел [Скидки, новинки и хиты продаж](#скидки-новинки-и-хиты-продаж))

**Слайдер главной страницы** (`/admin/sliders.php`):
- Добавить/редактировать слайды
- Загрузить картинку для каждого слайда
- Указать заголовок, подзаголовок, ссылку
- Изменить порядок сортировки
- Скрыть/показать слайд

**Заказы** (`/admin/orders.php`):
- Просмотр всех заказов
- Изменение статуса (новый → принят → отправлен → доставлен / отменён)
- Просмотр содержимого заказа

### 📝 Менеджер

**Запчасти** (`/manager/parts.php`):
- CRUD запчастей с загрузкой изображений
- Артикул, название, цена, остаток, вес, габариты
- Привязка к бренду и категории

**Категории** (`/manager/categories.php`):
- Иерархия категорий (родитель → дочерние)
- Иконки для категорий

**Бренды** (`/manager/brands.php`):
- Логотип бренда
- Описание

**Блог** (`/manager/blog.php`):
- CRUD статей на 3 языках (RU/TG/EN)
- Загрузка обложки статьи
- Slug (URL-friendly идентификатор) с авто-генерацией из заголовка
- Категория статьи (Новости / Советы по ТО / Обзоры / Другое)
- Черновик / Опубликовано

**Страницы — CMS «О нас»** (`/manager/pages.php`):
- Редактирование разделов страницы «О нас» из `site_sections`
- 4 группы: основные разделы, преимущества (3 иконки), FAQ (4 пункта), отзывы (3 шт.)
- Многоязычно (RU/TG/EN), загрузка изображений, сортировка, скрыть/показать

**Отзывы — модерация** (`/manager/reviews.php`):
- Переключатель **Товары / Магазин**
- Фильтры по статусу (ожидают / одобрены / отклонены / все) со счётчиками
- Одобрить / отклонить / удалить
- Тумблер «в витрину О нас» (`is_featured`) — одобренный отзыв
  попадает в блок «Что говорят клиенты» на странице «О нас»
- Бейдж количества ожидающих модерации во всех сайдбарах менеджера

### 👤 Покупатель

**Витрина** (`/index.php`):
- Просмотр товаров, поиск, фильтры
- Средний рейтинг (звёзды) в карточках товаров
- Добавление в корзину/избранное

**Отзывы:**
- Отзыв на товар (`/catalog/part.php`) — только после получения заказа,
  с премодерацией; на вкладке «Отзывы» виден статус своего отзыва
- Отзыв о магазине (`/pages/reviews.php`) — для любого авторизованного,
  с премодерацией

**Личный кабинет** (`/buyer/`):
- История заказов
- Корзина
- Избранное (wishlist)
- Профиль (имя, email, телефон, пароль)

**Оформление заказа** (`/buyer/checkout.php`):
- Выбор страны → список городов меняется автоматически (города из раздела «Доставка»)
- Для Таджикистана — выпадающий список городов с ценой доставки
- Для других стран — поле ввода города, доставка «уточняется»
- Стоимость доставки добавляется к сумме в реальном времени
- Способ оплаты: наличными при получении / банковский перевод

**Отмена заказа** (`/buyer/orders.php`):
- Заказ в статусе «ожидает» (`pending`) — покупатель может отменить сам
- После подтверждения (`processing`/`shipped`) — отмена через поддержку:
  показывается телефон и ссылка WhatsApp

---

## Доставка

Управление доставкой: **Суперадмин → Доставка** (`/superadmin/delivery.php`).

### Как это работает
1. Суперадмин добавляет города (и страну) с ценой и сроком доставки.
2. Покупатель на оформлении заказа выбирает страну → список городов меняется автоматически.
3. Стоимость доставки прибавляется к сумме заказа и сохраняется в `orders.shipping_cost`.

### Логика цены
| Стоимость города | На витрине | В сумме заказа |
|---|---|---|
| `0` | «Уточняется» | **не прибавляется** (пока нет договора с такси) |
| `> 0` | показывается цена | **прибавляется автоматически** |

Отключённые города (`is_active = 0`) покупателю не видны.

### Таблица `delivery_zones`
| Колонка | Описание |
|---|---|
| `city` | название города |
| `country` | страна (по умолчанию «Таджикистан») |
| `cost` | стоимость доставки (СМН); `0` = уточняется |
| `delivery_days` | срок, напр. «1–2 дня» |
| `is_active` | показывать покупателю |
| `sort_order` | порядок сортировки |

Уникальный ключ — пара `(city, country)`: один город может быть в разных странах.

### Миграции
```bash
mysql -u <user> -p <db> < sql/add_delivery_zones.sql          # таблица + orders.shipping_cost
mysql -u <user> -p <db> < sql/add_delivery_zones_country.sql  # колонка country
```

> На текущий момент доставка настроена только по Таджикистану. Для других стран
> цена не задана → доставка «уточняется». Когда появится договор с такси —
> просто проставьте цены в админке, изменения в коде не нужны.

---

## Скидки, новинки и хиты продаж

Эти разделы доступны из меню магазина и работают автоматически по данным БД.

### Скидки
- Админ задаёт **«Старую цену»** товара (`/admin/products.php`) больше цены продажи.
- Товар автоматически попадает в раздел «Скидки» (`/catalog/index.php?sale=1`).
- На карточке: красный бейдж `−XX%` + зачёркнутая старая цена.
- На главной — блок «Товары со скидкой» (крупная промо-карточка + сетка).
- Процент: `(old_price − price) / old_price × 100`.
- Миграция: `sql/add_old_price.sql` (колонка `parts.old_price`).

### Новинки (`?sort=new`)
- Товары, добавленные за последние **30 дней**, получают синий бейдж «Новый».
- Логика в хелпере `isNewProduct()` (`includes/functions.php`).

### Хиты продаж (`?sort=popular`)
- Сортировка по реальным продажам из `order_items`
  (учитываются все заказы, кроме `cancelled`).
- На главной — вкладка «Бестселлеры».

### Где показываются бейджи и цены
Главная, каталог, страница категории, страница товара, поиск — везде через
единые хелперы:
- `productBadges($part)` — бейдж (скидка / новинка / наличие)
- `priceBox($part)` — блок цены (зачёркнутая старая + новая)
- `discountPercent($part)` — процент скидки

---

## SEO (Google Search Console / Яндекс.Вебмастер)

В проекте есть `sitemap.php` (динамическая карта сайта) и `robots.txt`.

### Карта сайта
- Доступна по адресу `https://<домен>/sitemap.php`
- Включает: главную, каталог, категории, товары, страницы, блог
- В `robots.txt` указана строка `Sitemap: https://<домен>/sitemap.php`

### Регистрация в Google Search Console
1. Откройте https://search.google.com/search-console
2. Добавьте ресурс → «Ресурс с префиксом URL» → введите `https://autodoc.tj`
3. Подтвердите владение: способ **«HTML-тег»** — скопируйте `<meta name="google-site-verification" ...>`
   и вставьте в `<head>` (файл `includes/header.php`), либо загрузите HTML-файл в корень сайта
4. После подтверждения: Sitemaps → добавьте `sitemap.php`

### Регистрация в Яндекс.Вебмастер
1. Откройте https://webmaster.yandex.ru
2. Добавьте сайт → введите `https://autodoc.tj`
3. Подтвердите владение: **Мета-тег** → вставьте `<meta name="yandex-verification" ...>` в `<head>`
4. Раздел «Файлы Sitemap» → добавьте `https://autodoc.tj/sitemap.php`

> Мета-теги подтверждения добавляются один раз в `includes/header.php` внутри `<head>`.
> После проверки удалять их не нужно.

---

## API загрузки изображений

### Endpoint

```
POST /api/upload.php?type={products|sliders|blog}
Content-Type: multipart/form-data
```

### Параметры

| Параметр | Тип | Описание |
|----------|-----|----------|
| `type` (GET) | string | `products`, `sliders` или `blog` |
| `file` (POST) | file | Загружаемый файл изображения |

### Ограничения

| Параметр | Значение |
|----------|----------|
| Максимальный размер | 5 МБ |
| Допустимые форматы | JPEG, PNG, WEBP, GIF |
| Проверка MIME | По реальному содержимому, не по расширению |
| Доступ | Только `manager`, `admin`, `superadmin` |

### Ответ

**Успех:**
```json
{
  "url": "/assets/uploads/products/products_653a1f2b8c9d4.123.jpg",
  "filename": "products_653a1f2b8c9d4.123.jpg"
}
```

**Ошибка:**
```json
{
  "error": "Файл больше 5 МБ"
}
```

### Пример использования (JavaScript)

```javascript
const fd = new FormData();
fd.append('file', fileInput.files[0]);

fetch('/api/upload.php?type=products', {
    method: 'POST',
    body: fd,
    credentials: 'same-origin'  // для передачи cookies сессии
})
.then(res => res.json())
.then(data => {
    if (data.url) console.log('Загружено:', data.url);
    else alert(data.error);
});
```

### Где хранятся файлы

```
assets/uploads/products/  — изображения товаров
assets/uploads/sliders/   — изображения слайдера
assets/uploads/blog/      — обложки статей блога
```

### Изменить максимальный размер

В `php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 12M
```

В `api/upload.php`:
```php
$maxBytes = 10 * 1024 * 1024;  // 10 МБ
```

В Nginx:
```nginx
client_max_body_size 10M;
```

---

## Многоязычность

Поддерживаемые языки:
- 🇷🇺 **Русский** (`ru`) — по умолчанию
- 🇹🇯 **Таджикский** (`tg`)
- 🇬🇧 **English** (`en`)

### Переключение языка

Через GET-параметр:
```
?lang=ru
?lang=tg
?lang=en
```

Или через переключатель в правом верхнем углу сайта.

Язык сохраняется в сессии: `$_SESSION['lang']`.

### Использование переводов в коде

```php
<?= t('home') ?>          <!-- → "Главная" / "Асосӣ" / "Home" -->
<?= t('add_to_cart') ?>   <!-- → "В корзину" / "Ба сабад" / "Add to cart" -->
```

### Добавить новый перевод

1. Открыть `lang/ru.php`, `lang/tg.php`, `lang/en.php`
2. Добавить ключ в каждый файл:
   ```php
   'my_new_key' => 'Мой текст',
   ```
3. Использовать в шаблоне:
   ```php
   <?= t('my_new_key') ?>
   ```

### Добавить новый язык

1. Создать `lang/de.php` (например, для немецкого):
   ```php
   <?php
   return [
       'home' => 'Startseite',
       'login' => 'Anmelden',
       // ... скопировать все ключи из ru.php и перевести
   ];
   ```
2. Добавить в `includes/i18n.php` в массив `$availableLanguages`:
   ```php
   'de' => 'Deutsch',
   ```

---

## Валюты

Поддерживаемые валюты:
- 💴 **RUB** — Российский рубль (по умолчанию)
- 💵 **USD** — Доллар США
- 💰 **TJS** — Таджикский сомони

### Переключение валюты

GET-параметр:
```
?currency=RUB
?currency=USD
?currency=TJS
```

Или через переключатель на сайте.

### Автоматическое обновление курсов

ЦБ РФ предоставляет открытый API. Кнопка в `/superadmin/currencies.php` → "Получить курсы ЦБ" делает запрос к:
```
https://www.cbr-xml-daily.ru/daily_json.js
```

И обновляет курсы всех валют, кроме RUB (которая всегда 1.0).

### Использование в коде

```php
<?= formatPrice(1500) ?>  <!-- → "1 500 ₽" или "$15" или "150 SM" -->
```

### Добавить новую валюту

```sql
INSERT INTO currencies (code, name_ru, name_tg, name_en, symbol, rate, is_active, is_default)
VALUES ('EUR', 'Евро', 'Евро', 'Euro', '€', 0.010500, 1, 0);
```

---

## Резервное копирование

### Ручное создание

Через UI:
1. Войти как суперадмин
2. Перейти `/superadmin/backup.php`
3. Нажать "Создать резервную копию"
4. Файл сохранится в `storage/backups/`

Через CLI:
```bash
php superadmin/backup_cron.php
```

### Автоматическое создание (cron)

```bash
crontab -e
```

Добавить:
```cron
# Каждый день в 3:00 ночи
0 3 * * * php /var/www/html/avtozapchast/superadmin/backup_cron.php >> /var/log/avtozapchast_backup.log 2>&1
```

### Восстановление

Через UI:
1. `/superadmin/backup.php`
2. Найти нужный дамп в списке
3. Нажать "Восстановить" → подтвердить

> Перед восстановлением автоматически создаётся pre-restore бэкап на случай отката.

Через CLI:
```bash
gunzip -c storage/backups/backup_2026-05-11.sql.gz | mysql -u avtouser -p avtozapchast
```

### Удаление старых бэкапов

В `backup_cron.php` можно указать срок хранения. По умолчанию старше 30 дней удаляются.

---

## Решение проблем

### Сайт не открывается, белый экран

**1. Проверить логи:**
```bash
tail -f /var/log/nginx/error.log
tail -f /var/log/nginx/avtozapchast_error.log
tail -f /var/log/php8.2-fpm.log
```

**2. Включить отображение ошибок PHP:**

В `config/config.php` временно добавить:
```php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
```

> ⚠️ Не оставлять это на продакшене!

### Ошибка "DB connection failed"

Проверить:
- Запущен ли MySQL: `systemctl status mysql`
- Правильный ли пароль в `config/database.php`
- Существует ли пользователь: `mysql -u avtouser -p -e "SELECT 1;"`

### "Неверный email или пароль" при правильных данных

Проверить, что в базе пароль захеширован bcrypt:
```sql
SELECT email, LEFT(password_hash, 7) FROM users LIMIT 5;
-- Должно быть: $2y$10$...
```

Если хеш отличается — пересоздайте:
```bash
php -r "echo password_hash('Password123!', PASSWORD_BCRYPT);"
```
```sql
UPDATE users SET password_hash = 'ВСТАВИТЬ_ХЕШ' WHERE email = 'superadmin@avtozapchast.ru';
```

### Загрузка изображений не работает

**Проверить права:**
```bash
ls -la assets/uploads/
# Должно быть: drwxr-xr-x www-data www-data
```

Если не так:
```bash
chown -R www-data:www-data assets/uploads
chmod -R 755 assets/uploads
```

**Проверить лимиты PHP:**
```bash
php -i | grep -E "upload_max_filesize|post_max_size|memory_limit"
```

Если меньше 5М — увеличить в `/etc/php/8.2/fpm/php.ini`:
```ini
upload_max_filesize = 10M
post_max_size = 12M
memory_limit = 128M
```

Перезапустить:
```bash
systemctl restart php8.2-fpm
```

### Курсы валют не обновляются

Проверить, что доступен сайт ЦБ:
```bash
curl https://www.cbr-xml-daily.ru/daily_json.js
```

Если нет — проблема с интернетом / DNS / firewall.

### Карта в контактах не отображается

Контактная страница использует iframe Яндекс.Карт. Если страница встроена внутри другого iframe — браузер может заблокировать. Откройте напрямую `/pages/contact.php`.

### 502 Bad Gateway

PHP-FPM упал. Перезапустить:
```bash
systemctl restart php8.2-fpm
systemctl status php8.2-fpm
```

### Кириллица отображается как `?????`

Проверить кодировку БД:
```sql
SHOW VARIABLES LIKE 'character_set_database';
-- должно быть utf8mb4
```

Если нет — пересоздайте БД с правильной кодировкой:
```sql
DROP DATABASE avtozapchast;
CREATE DATABASE avtozapchast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

---

## Разработка

### Установка для разработки

```bash
# Клонировать
git clone https://github.com/nnfirdavs96-cell/avtozapchast.git
cd avtozapchast

# Создать свою ветку
git checkout -b feature/название-вашей-фичи
```

### Стандарт коммитов

- `Add ...` — новая функциональность
- `Fix ...` — исправление бага
- `Update ...` — улучшение существующего
- `Refactor ...` — рефакторинг без изменения поведения
- `Remove ...` — удаление кода
- `Docs ...` — обновление документации

Пример:
```bash
git commit -m "Add wishlist counter to header"
git commit -m "Fix login redirect for managers"
```

### Pull Request

```bash
# Запушить ветку
git push origin feature/название-вашей-фичи

# На GitHub создать PR в main
# https://github.com/nnfirdavs96-cell/avtozapchast/pulls
```

### Стиль кода

- **PHP:** PSR-12 (отступы 4 пробела, фигурные скобки на новой строке для функций/классов)
- **SQL:** ключевые слова прописными буквами, имена столбцов snake_case
- **CSS:** kebab-case (`.az-card-header`)
- **JS:** camelCase (`uploadImages`, `removeNewImage`)
- **Имена файлов:** snake_case (`backup_cron.php`)
- **Имена констант:** SCREAMING_SNAKE_CASE (`APP_URL`, `DB_HOST`)

### Структура нового PHP-файла

```php
<?php
require_once dirname(__DIR__) . '/config/config.php';
requireRole(['admin', 'superadmin']);  // если нужна авторизация

$db   = getDB();
$csrf = generateCsrfToken();

// ── Логика обработки POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        flashMessage('danger', 'CSRF error');
        redirect(APP_URL . '/.../...');
    }
    // ... обработка ...
}

// ── Подготовка данных для шаблона ─────────────────────────────
$data = $db->query("SELECT * FROM ...")->fetchAll();

$pageTitle = 'Заголовок страницы';
require_once dirname(__DIR__) . '/includes/header.php';
?>

<!-- HTML здесь -->

<?php require_once dirname(__DIR__) . '/includes/footer.php'; ?>
```

---

## Архитектура

### Подключение БД

Singleton через функцию `getDB()` в `config/database.php`:
```php
$db = getDB();
$stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$id]);
$user = $stmt->fetch();
```

### Сессии и авторизация

Авто-старт сессии в `config/config.php`. После входа:
```php
$_SESSION['user_id']   = $user['id'];
$_SESSION['role']      = $user['role'];     // buyer | manager | admin | superadmin
$_SESSION['username']  = $user['username'];
$_SESSION['user_data'] = [...];
```

Helpers:
```php
isLoggedIn()                          // bool
getCurrentUser()                      // array | null
requireRole('admin')                  // редирект на /login, если нет роли
requireRole(['admin', 'superadmin'])  // несколько ролей
```

### CSRF-защита

Все POST-формы должны включать:
```php
<input type="hidden" name="csrf_token" value="<?= sanitize(generateCsrfToken()) ?>">
```

И проверять:
```php
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    flashMessage('danger', 'CSRF error');
    redirect(...);
}
```

### Flash-сообщения

```php
flashMessage('success', 'Товар добавлен');
flashMessage('danger', 'Произошла ошибка');
flashMessage('warning', '...');
flashMessage('info', '...');

// В шаблоне:
if ($flash = getFlashMessage()) {
    echo '<div class="az-alert az-alert-' . $flash['type'] . '">' . $flash['message'] . '</div>';
}
```

### Хелперы

| Функция | Описание |
|---------|----------|
| `sanitize($str)` | XSS-защита (htmlspecialchars) |
| `redirect($url)` | HTTP-редирект |
| `truncate($str, $len)` | Обрезание строки |
| `formatPrice($price)` | Форматирование цены с валютой |
| `t($key)` | Перевод строки |
| `getSetting($key)` | Получить настройку из site_settings |
| `getBrands()` | Получить все активные бренды |
| `getCategories()` | Получить иерархию категорий |
| `getStockStatus($qty)` | Статус остатка (in / low / out) |
| `getOrderStatusLabel($status)` | Человеческое название статуса |
| `getOrderStatusClass($status)` | CSS-класс для бейджа |
| `starsHtml($rating)` | HTML звёзд по оценке 0–5 (FontAwesome) |
| `getProductRatings($partIds)` | `[part_id => [avg, count]]` по одобренным отзывам |
| `productStarsInline($partId, $ratings)` | Компактная строка звёзд для карточки товара |
| `userPurchasedPart($userId, $partId)` | Купил ли пользователь товар (доставленный заказ) |
| `getShopRatingSummary()` | `[avg, count]` по одобренным отзывам о магазине |
| `requireRole($roles)` | Гейт страницы по роли (superadmin — везде) |
| `hasRole($roles)` | Текущий пользователь в одной из ролей? |
| `permissionSections()` | Каталог управляемых разделов `ключ→название` |
| `roleDefaultSections($role)` | Разделы роли по умолчанию (текущее поведение) |
| `getUserConfiguredSections($uid)` | Явный список разделов или `null` |
| `effectiveAllowedSections($uid,$role)` | Конфиг, иначе умолчание роли |
| `userCan($section)` | Доступен ли раздел текущему пользователю |
| `requirePermission($section)` | Гейт по гранту суперадмина (после `requireRole`) |
| `requireAdminPort()` | 403, если админ-раздел открыт не на `ADMIN_PORT` |
| `renderRoleSidebar($active)` | Сайдбар по роли, отфильтрован `userCan` |

> Хелперы отзывов (`getProductRatings`, `getShopRatingSummary`) и прав
> (`getUserConfiguredSections`) обёрнуты в `try/catch (PDOException)` —
> возвращают пусто/`null`, если миграции ещё не применены, чтобы
> страницы не падали.

---

## CHANGELOG / История изменений

Описано в формате «что → где → зачем», чтобы любой разработчик мог
сориентироваться в коде. Новые записи — сверху.

### Скидки, новинки, хиты продаж + блок «Товары со скидкой» (PR #157–#159)

**Цель:** дать витрине разделы скидок/новинок/хитов как в шаблоне Mazlay.

- **Что:** колонка `parts.old_price`; хелперы `discountPercent()`, `isNewProduct()`,
  `productBadges()`, `priceBox()` в `includes/functions.php`.
- **Где:** бейджи `−XX%`/«Новый» и зачёркнутая цена — на главной, в каталоге,
  категориях, странице товара и поиске. Фильтры `?sale=1`, `?sort=popular`, `?sort=new`
  в `catalog/index.php`. Блок «Товары со скидкой» на главной (`index.php`).
- **Админка:** поле «Старая цена» в `admin/products.php`.
- **Миграция:** `sql/add_old_price.sql`.

### Доставка по городам и странам (PR #153–#156)

**Цель:** расчёт стоимости доставки при оформлении заказа.

- **Что:** таблица `delivery_zones`, колонка `orders.shipping_cost`, затем `delivery_zones.country`.
- **Где:** управление — `superadmin/delivery.php`; расчёт и выбор города/страны —
  `buyer/checkout.php` (список городов меняется при смене страны).
- **Логика:** `cost = 0` → «уточняется», не прибавляется; `cost > 0` → прибавляется.
- **Миграции:** `sql/add_delivery_zones.sql`, `sql/add_delivery_zones_country.sql`.

### Отмена заказа покупателем + фикс оформления заказа (PR #151–#152)

- **Фикс:** колонка `orders.payment_method` отсутствовала → все заказы падали с ошибкой
  «Ошибка оформления заказа». Добавлена миграция `sql/add_order_payment_method.sql`.
- **Отмена:** в `buyer/orders.php` заказ в статусе `pending` отменяется покупателем;
  после подтверждения — контакт поддержки (телефон + WhatsApp).

### Слайдер: независимые десктоп/мобильный настройки, кнопка, фикс IP-ссылки, высота моб. (PR #127–#133)

**Цель:** полностью разделить вид слайда на десктопе и телефоне, дать контроль над
кнопкой и убрать переход на внутренний IP.

**1. Независимые настройки десктоп / мобильный.** Раньше изменение текста, шрифтов
и отступов на десктопе менялось и на мобильном. Теперь у каждой версии — свой набор.

| Файл | Что изменено |
|------|--------------|
| БД `sliders` | Новые колонки (через `dbAddColumnIfMissing`): `text_blocks_mobile TEXT`, `text_pos_mobile VARCHAR(20)`, `button_text VARCHAR(100)`. |
| `admin/sliders.php` | Редактор хранит два независимых состояния (`blocksState.desktop/mobile`, `posState.desktop/mobile`), сериализует в скрытые поля `blocks_desktop`/`blocks_mobile` при сабмите. Вкладки **Десктоп/Мобильный**, баннер режима, кнопка «Скопировать из десктопа». |
| `index.php` | Два абсолютных слоя `.sl-variant--desktop` / `.sl-variant--mobile`; на `<768px` через CSS-медиазапрос показывается только мобильный. Мобильный использует `--fsm` (закреплённый размер без автомасштаба). |
| `assets/css/custom.css` | `.sl-variant { position:absolute; inset:0 }`; переключение слоёв по `@media (max-width:767px)`. |

**2. Управление кнопкой слайда.** Текст кнопки задаётся в админке (поле «Текст
кнопки»; пусто → стандартный `t('shop')`), превью обновляется в реальном времени.

**3. Фикс ссылки на внутренний IP `10.230.13.107`.** Кнопка вела на внутренний
прокси-IP Timeweb вместо `https://autodoc.tj`.

| Файл | Что изменено |
|------|--------------|
| `admin/sliders.php` | Одноразовая миграция при загрузке: все `link_url` с приватными IP (`10.x`, `192.168.x`, `172.16-31.x`) исправляются автоматически. При сохранении IP-префикс всегда обрезается из `link_url`. |
| `index.php` | На фронтенде `link_url` тоже нормализуется перед выводом (защита от легаси-записей). |

**4. Высота мобильного слайдера и превью.** Подбор оптимальной высоты, чтобы
изображение не обрезалось «за край» и админ-превью совпадало с сайтом.

| Файл | Что изменено |
|------|--------------|
| `assets/css/custom.css` | Мобильный слайдер: фиксированная высота `380px` (≤390px экраны — `320px`), `background-size:cover; center`. |
| `assets/js/app.js` | `applyResponsiveBg()` упрощён — только устанавливает фоновое изображение (мобильный вариант), высота полностью через CSS. |
| `admin/sliders.php` | Превью мобильного режима — фрейм `390×380` (совпадает с реальной высотой). Рекомендация изображения: **~1080×1080 px (квадратное)**, важный объект — в центре. |

**5. Фикс позиционирования (баг).** В редакторе был дублирующийся блок
«Расположение текста» — позиция никогда не сохранялась. Дубль удалён.

### Фикс: весь сайт открывался по внутреннему IP `10.230.13.107` (PR #124)

**Проблема:** на проде все страницы (вход, магазин, блог…) и редиректы оставались
на внутреннем IP `10.230.13.107` вместо `https://autodoc.tj`.

**Причина:** `config/config.php` коммитился с пустым `APP_URL`, поэтому все ~486
ссылок и 110 вызовов `redirect()` были относительными и резолвились относительно
хоста, который обратный прокси Timeweb передавал бэкенду в заголовке `Host` — а это
приватный IP. Относительный `Location` прокси переписывал на тот же IP. `git pull`
каждый раз возвращал пустой `APP_URL`, поэтому ручное копирование прод-конфига не
держалось.

| Файл | Что изменено |
|------|--------------|
| `config/config.php` | `APP_URL` определяется автоматически: `''` на localhost (относительные URL для разработки), `https://autodoc.tj` на любом другом хосте. Переживает `git pull`. |
| `includes/functions.php` | `redirect()` достраивает относительные пути (`/path`) до абсолютных через `APP_URL`, чтобы прокси не подменял `Location` на внутренний IP. Абсолютные и уже-префиксованные URL не трогаются. |

### Слайдер: редактор текстовых блоков, мобильный размер шрифта, позиционирование и демо-превью (PR #118–#123)

**Цель:** сотрудник должен сам управлять видом слайдов — без правки кода. Сколько
строк, размер шрифта (отдельно десктоп/мобильный), жирность, цвет, шрифт, отступы,
куда поместить текст (9 позиций), и видеть демонстрационный экран до сохранения.

| Файл | Что изменено |
|------|--------------|
| `admin/sliders.php` | Полностью переписан редактор: карточки «Текстовые блоки» (добавить/удалить/переместить строку; на каждую — размер десктоп, **размер мобильный** (0 = авто), жирность, цвет, шрифт, отступ снизу), сетка-пикер 3×3 для позиции текста (`text_pos`), живое превью с переключателем **Десктоп / Мобильный** (точные размеры 1140×420 / 390×300 через `transform: scale`). |
| `includes/functions.php` | `normalizeSliderBlocks()` валидирует массив блоков (текст, размеры, вес, цвет `#rrggbb`, шрифт, отступ); `sliderFonts()`/`sliderFontStack()`/`sliderWeights()` — белые списки Google-шрифтов и насыщенностей; `dbAddColumnIfMissing()` — портируемая миграция колонок (MariaDB dev / MySQL 8.0 прод). |
| `index.php` | Слайды рендерят блоки `.slider_block` с инлайн-CSS-переменными `--fs` (десктоп) и `--fsm` (мобильный, только если задан); `text_pos` применяется через Bootstrap-классы выравнивания. |
| `assets/css/custom.css` | `.slider_block { font-size: var(--fs) }`; медиа-запросы масштабируют для планшетов/телефонов: на ≤767px `font-size: var(--fsm, calc(var(--fs)*0.32))`. |
| БД `sliders` | Новые колонки (через `dbAddColumnIfMissing`): `text_blocks TEXT` (JSON-массив блоков), `text_pos VARCHAR(20)`, `image_url_mobile`. |

> **Важно (совместимость MySQL 8.0):** синтаксис `ALTER TABLE … ADD COLUMN IF NOT
> EXISTS` — только для MariaDB. На Timeweb (MySQL 8.0) он давал ошибку, колонка не
> создавалась, и сохранение слайда падало с 500. Все миграции колонок переведены на
> `dbAddColumnIfMissing()` (проверка через `information_schema.COLUMNS`). Тот же фикс
> применён в `admin/banners.php` и `manager/categories.php`.

### Баннеры в админке, PDF-руководство, фиксы витрины и адреса доставки

**Управление баннерами главной (новый раздел).** Три рекламных баннера под
слайдером были «зашиты» в шаблон (`banner1.jpg`–`banner3.jpg`) — правки требовали
программиста. Теперь ими управляют из панели.

| Файл | Что изменено |
|------|--------------|
| `admin/banners.php` | **Новый.** CRUD баннеров (картинка, ссылка, порядок, вкл/выкл) по образцу `admin/sliders.php`. Таблица `banners` создаётся автоматически. Гейт: `requireRole(['admin','manager','superadmin'])` + `requirePermission('sliders')`. |
| `index.php` | Блок баннеров рендерится из таблицы `banners` (первые 3 активных). Если их нет — стандартные картинки шаблона (главная не пустеет). |
| `api/upload.php` | В список разрешённых типов добавлен `banners`. |
| `includes/functions.php` | Пункт «Баннеры» в сайдбаре admin и superadmin; `permissionAlias('banners') → 'sliders'` (отдельное право не нужно). |

**PDF-руководство для сотрудников (только суперадмин).** Иллюстрированная
инструкция по работе с панелью, которую суперадмин раздаёт сотрудникам.

| Файл | Что изменено |
|------|--------------|
| `includes/manual_pdf.php` | **Новый.** Самодостаточный генератор PDF на GD (без внешних библиотек): каждая A4-страница рисуется как изображение (текст DejaVu Sans с кириллицей + векторные иллюстрации: сайдбар, шаги, кнопки, карточки ролей, таблицы) и встраивается в PDF как JPEG. |
| `superadmin/manual.php` | **Новый.** Страница только для суперадмина: просмотр, скачивание (`?action=download`) и перегенерация PDF. PDF лежит в `storage/manual/` (закрыт `.htaccess`), отдаётся через гейт. |
| `includes/functions.php` | Пункт «Руководство» в сайдбаре суперадмина. |

**Фикс: адрес доставки показывался как JSON.** `buyer/checkout.php` сохраняет
адрес как JSON, но `buyer/orders.php` и `admin/orders.php` выводили сырую строку
`{"first_name":...}`. Добавлена `formatShippingAddress()` в `includes/functions.php` —
декодирует JSON в читаемый вид (имя, телефон, email, адрес), а не-JSON показывает как текст.

**Логотипы брендов крупнее.** В `assets/css/custom.css` высота `.single_brand`
увеличена 100→130px (мобайл 85→110px, 75→100px), padding уменьшен — логотипы
визуально больше.

**Фикс серой иконки Instagram.** В шаблоне есть `.instagram2` (магента), а
`index.php` использует `class="instagram"` — без правила цвета (иконка была серой).
В `custom.css` добавлены фирменные градиент Instagram и точные цвета telegram/
whatsapp/facebook (через `:has(.fa-*)`).

**Защита `avatar_path`.** В `buyer/profile.php` поле принимается из POST —
добавлена проверка: только app-относительные пути или `http(s)://` (блокирует
схемы `javascript:`/`data:`).

**Деплой на Timeweb (Apache).** `.htaccess` (маршрутизация как nginx `try_files`,
запрет доступа к `config/`,`sql/`,`deploy/`), `deploy/timeweb/{config,database}.php`,
`deploy/timeweb/export-db.sh`.

### Карусель брендов: размер логотипов и полупрозрачные стрелки (PR #103)

**Цель:** на мобильных логотипы превращались в крошечные «чёрточки» — не было
явного размера у `.single_brand` / `img`. Кнопки навигации тоже выглядели
плотно — нужны полупрозрачные, чтобы не перекрывали логотипы.

| Файл | Что изменено |
|------|--------------|
| `assets/css/custom.css` | `.single_brand` стал flex-контейнером с `min-height: 110px` (90px на планшетах, 80px на телефонах); `img` получил явные `max-height: 80px / 60px / 48px` и `object-fit: contain`; стрелки `rgba(199,9,9,.78)` + `backdrop-filter: blur(2px)` + полупрозрачная белая обводка |

### Карусель брендов: стрелки, автопрокрутка, мобильная адаптация (PR #97–#101)

**Цель:** карусель партнёров/брендов на главной была статичной — без
стрелок и автоскролла. Также бренды дублировались (логотипы клонировались
OWL'ом из-за группировки по 2 в слайд) и плохо смотрелись на мобильных.

| Файл | Что изменено |
|------|--------------|
| `index.php` | Бренды теперь по одному на слайд (а не парами) — нет клонирования; query: `ORDER BY sort_order ASC, name ASC` |
| `manager/brands.php` | В таблице колонка `#` показывает порядковый номер (1,2,3…), не raw `id`; новая колонка **Порядок**, поле `sort_order` в форме |
| `assets/mazlay-js/main.js` | `nav: true`, навигация FA-иконками, `autoplay: true` 3 сек с паузой при ховере; responsive: на мобильных 2 → 3 → 4 → 5 → 6 брендов |
| `assets/css/custom.css` | Стрелки 60×60 (42×42 на мобильных), красные круги с белой обводкой, padding контейнера 60px (стрелки внутри), `body` в селекторах для максимальной специфичности |
| `sql/add_brand_sort_order.sql` | Идемпотентная миграция: `ALTER TABLE brands ADD sort_order INT NOT NULL DEFAULT 0` |

**Миграция:**
```bash
mysql -u root -p avtozapchast < sql/add_brand_sort_order.sql
```

### Единый сайдбар для всех админ-панелей (PR #96)

**Цель:** на разных страницах админ-разделов был свой захардкоженный
HTML-сайдбар (16 файлов), пункты различались. При переходе между страницами
пункт «Партнёры» то появлялся, то пропадал.

| Файл | Что изменено |
|------|--------------|
| `includes/functions.php` | В `renderRoleSidebar()` для суперадмина добавлены пункты **Партнёры, Страницы, Отзывы, Категории** |
| 16 файлов (`admin/`, `manager/`, `superadmin/`) | Захардкоженные `<aside class="az-sidebar">…</aside>` заменены на вызов `<?php renderRoleSidebar('key'); ?>` |
| Результат | `–364 / +19 строк`, единая точка истины для меню |

Затронутые страницы: `superadmin/{index,users,permissions,settings,currencies,languages,blog,backup}.php`,
`manager/{index,parts,categories,brands,blog,pages,reviews}.php`,
`admin/products.php`.

### Переименование AvtoDoc → AutoDoc (PR #95)

**Цель:** обновление бренда до «AutoDoc».

| Файл | Что изменено |
|------|--------------|
| `config/config.php` | `APP_NAME = 'AutoDoc'` |
| `lang/ru.php`, `en.php`, `tg.php` | `site_name = 'AutoDoc'` |
| `includes/footer.php` | Fallback для копирайта |
| `assets/css/custom.css` | Комментарий обновлён |
| `sql/rename_to_autodoc.sql` | `UPDATE settings SET value = REPLACE(value, 'AvtoDoc', 'AutoDoc')` |

**Миграция (обязательна, иначе в БД останется старое значение):**
```bash
mysql -u root -p avtozapchast < sql/rename_to_autodoc.sql
```

Также можно через **Суперадмин → Настройки → Основные → Название сайта**.

### Новый логотип AutoDoc с прозрачным фоном (PR #91, #92, #93, #94)

**Цель:** заменить старый логотип на новый «AutoDoc».

| Файл | Что изменено |
|------|--------------|
| `assets/img/logo/avtodoc-logo.png` | Новый логотип (600×180, RGBA, прозрачный фон) |
| `includes/header.php` | Путь обновлён, `alt="AutoDoc"` |
| `assets/img/logo/avtodoc-logo.jpg` | Удалён (был старый формат) |

Алгоритм очистки фона: flood-fill от пограничных пикселей с проверкой по
яркости и насыщенности (тёмный десатурированный фон → α=0, цветной/яркий
контент → α=255), затем обрезка по контенту и масштабирование под веб.

### Кабинет покупателя: единый магазинный макет + навигация (PR #88, #89)

**Цель:** страницы покупателя были в двух разных макетах — Профиль /
Панель / Заказы использовали тёмную админ-панель с сайдбаром, а
Корзина / Избранное — обычный магазинный вид. Покупатель не админ;
всё приведено к магазинному виду.

| Файл | Что изменено |
|------|--------------|
| `buyer/profile.php`, `buyer/index.php`, `buyer/orders.php` | `admin-header/footer` → `header/footer`; убраны `az-panel` / `az-sidebar` / `az-topbar`; добавлены хлебные крошки (`breadcrumb()`) и обёртка `.az-account` |
| `buyer/cart.php`, `buyer/wishlist.php` | Добавлен вызов `renderBuyerAccountNav()` — навигация теперь одинакова на всех 5 страницах кабинета |
| `includes/functions.php` | Новый помощник `renderBuyerAccountNav(string $active)` — горизонтальные вкладки Панель / Заказы / Профиль / Корзина / Избранное / Выход |
| `assets/css/custom.css` | Стили `.az-account`, `.az-account-nav` (вкладки в фирменном тёмно-красном, адаптив) |

> Контент-карточки (`.az-card`, `.az-table`, `.az-form-group`) —
> глобальные, не тронуты, корректно рендерятся в магазинном макете.
> Только CSS/PHP, миграций не требует.

### Редизайн админ/профиль панелей под фирменный стиль (PR #87)

**Цель:** админка выглядела как generic Bootstrap (Material-красный
`#d32f2f`, серо-синий сайдбар) и не совпадала с витриной.

| Файл | Что изменено |
|------|--------------|
| `assets/css/custom.css` | Единая система через CSS-переменные (`--az-red:#C70909` и др.). Все панели (покупатель/менеджер/админ/суперадмин) в фирменном красном `#C70909`. Тёмный графитовый сайдбар с красной активной полосой. Заголовки карточек — КАПС с красной меткой. Кнопки как в магазине (КАПС, радиус 3px, hover-подъём). Единые токены для таблиц/форм/бейджей/пагинации |
| `includes/functions.php` | `renderRoleSidebar()`: убран чужеродный фиолетовый градиент суперадмина, золотая ★ для различения роли |
| `buyer/profile.php`, `buyer/index.php` | Инлайн-цвета `#d32f2f` → `#C70909` |

> **Высокоточечный файл:** весь стиль `.az-*` сосредоточен в
> `assets/css/custom.css`. Публичная витрина не затронута — правки
> только в админ-блоке. Требует Ctrl+F5 (сброс кэша CSS).

### Профиль покупателя: аватар + адрес доставки (PR #86)

**Цель:** покупатель не мог загрузить аватар; адрес приходилось
вводить заново при каждом заказе.

| Файл | Что изменено |
|------|--------------|
| `sql/add_user_profile_fields.sql` | **Новая идемпотентная миграция:** колонки `users.avatar_path, first_name, last_name, address, city, zip_code, country` (через `information_schema` + `PREPARE/EXECUTE`) |
| `buyer/profile.php` | Загрузка/удаление аватара (предпросмотр), карточка «Адрес доставки». UPDATE-запросы в `try/catch` — сайт не падает, если миграция ещё не применена |
| `buyer/checkout.php` | Автоподстановка сохранённого адреса в форму заказа (тоже в `try/catch`) |
| `includes/functions.php` | `getCurrentUser()` читает `avatar_path` (с fallback-запросом без колонки) |
| `includes/header.php`, `buyer/index.php` | Аватар вместо буквенного кружка |
| `api/upload.php` | Добавлен тип загрузки `avatars` |

> ⚠️ После `git pull` обязательно выполнить
> `mysql ... < sql/add_user_profile_fields.sql` и
> `chown -R www-data:www-data assets/uploads`. До миграции функции
> аватара/адреса работают в режиме no-op (не ломают сайт).

### Управляемый контент: изображения, бренды, соцсети (PR #81–#85)

**Цель:** убрать хардкод картинок категорий и логотипов «партнёров»
на главной; дать управление соцсетями; подсказать размеры загрузок.

| Файл | Что изменено |
|------|--------------|
| `sql/add_category_image.sql`, `sql/add_brand_logo.sql` | Идемпотентные миграции: `categories.image_path`, `brands.logo_path` |
| `manager/categories.php`, `manager/brands.php` | Загрузка изображения категории / логотипа бренда (предпросмотр, удаление) |
| `index.php` | Картинки категорий и логотипы партнёров берутся из БД (fallback на стандартную) |
| `superadmin/settings.php` | Соцсети Telegram, WhatsApp, Instagram, Facebook, **YouTube, TikTok**; ссылка «Партнёры» в сайдбаре |
| `index.php` (футер) | Рендер всех соцсетей; умный хелпер `$socUrl` (username или полная ссылка) |
| `lang/ru.php`, `lang/tg.php`, `lang/en.php` | Ключ `follow_us` (был сырой `FOLLOW_US`) |
| `api/upload.php` | Типы загрузки `categories`, `brands` |
| Все формы загрузки изображений (бренды, категории, слайдер, товары, блог, разделы) | Информационные подсказки с рекомендуемым размером / соотношением / форматом |

### Брендинг: оригинальный логотип AvtoDoc (PR #80)

| Файл | Что изменено |
|------|--------------|
| `assets/img/logo/avtodoc-logo.png` | Прозрачный вырез из оригинального PNG (фон удалён) |
| `assets/img/logo/avtodoc-favicon.png` | Эмблема-щит 256×256 как favicon |
| `includes/header.php`, `includes/admin-header.php` | Подключение PNG-логотипа и favicon |

### Защита админ-панели отдельным портом 8888 (PR #75)

**Цель:** чтобы случайный посетитель не попал в админку с основного
адреса — даже зная логин и пароль. Разделы `/admin`, `/superadmin`,
`/manager` открываются только по `:8888`.

| Файл | Что изменено / добавлено |
|------|--------------------------|
| `config/config.php` | Константа `ADMIN_PORT` (по умолчанию `8888`). Пусто = разделение отключено. |
| `includes/functions.php` | `requireAdminPort()` — отдаёт 403, если запрос пришёл не на `ADMIN_PORT`. Вызывается в `requireRole()` только когда требуется роль `admin/manager/superadmin` — публичный сайт не затронут. |
| README | Раздел «Реальная конфигурация прод-сервера»: фактический сервер — **nginx + php-fpm 8.2** (не Apache, он мёртв). Реализация — `listen 8888;` в `/etc/nginx/sites-available/avtozapchast` через `sed`, `nginx -t` перед reload. История двух копий и обновление сайта. |

> Двухуровневая защита: (1) неизвестный порт 8888 + (2) логин/пароль.
> Уровень 1 (код) — через `git pull`. Уровень 2 — `listen 8888;` в
> nginx-конфиге. Развёрнуто на проде 10.230.13.107: `/admin` на :80 →
> 403, на :8888 → форма входа. Откат безопасен и описан в README.
> Apache на сервере failed и игнорируется.

### Favicon AvtoDoc (PR #74)

**Цель:** убрать авто-иконку браузера «AD», поставить брендовую.

| Файл | Что изменено / добавлено |
|------|--------------------------|
| `assets/img/favicon.svg` | **Новый.** Тёмный фон, белая «A» + красная «D» (в стиле логотипа). |
| `includes/header.php` | `<link rel="icon" type="image/svg+xml">` перед `.ico` (SVG приоритетнее, `.ico` — запасной). |

> Только статика. После `git pull` иконка обновляется жёстким
> обновлением вкладки (Ctrl+Shift+R).

### Логотип AvtoDoc: крупнее + анимация (PR #73)

| Файл | Что изменено |
|------|--------------|
| `assets/css/custom.css` | `.logo .logo-text` `1.5rem` → `2.1rem` (мобайл `1.05`→`1.5rem`, ≤390px `1`→`1.3rem`). Hover: `scale(1.06)` с упругим cubic-bezier + свечение красной части «Doc» (`text-shadow`), `active` `scale(1.02)`. |

### Переименование сайта АвтоЗапчасть → AvtoDoc (PR #72)

| Файл | Что изменено |
|------|--------------|
| `includes/header.php` | Логотип `Avto<span>Doc</span>` (Avto белым, Doc красным). |
| `assets/css/custom.css` | `.logo-text` белый, `.logo-text span` красный `#d32f2f`. |
| `lang/ru.php`, `tg.php`, `en.php` | `site_name` = `AvtoDoc`. |
| `config/config.php` | `APP_NAME` = `AvtoDoc`. |
| `includes/footer.php` | Дефолт `getSetting('site_name','AvtoDoc')`. |
| `sql/schema.sql`, `sql/migrate_tajik_market.sql` | Сид `site_name` = `AvtoDoc`. |
| `sql/rename_to_avtodoc.sql` | **Новый.** `UPDATE site_settings ... 'AvtoDoc'` для уже работающей БД. |

> ⚠️ На рабочем сервере **обязательна миграция**
> `sql/rename_to_avtodoc.sql` — сохранённое в `site_settings` имя
> перекрывает дефолт из `lang/*`.

### Формат цены, баннер, цвет полосы категорий (PR #71)

| Файл | Что изменено |
|------|--------------|
| `includes/currency.php` | `formatPrice()` — сумма впереди, затем валюта строчными в `<span class="cur-sym">смн</span>` (было `СМН650.00` → стало `650.00 смн`). |
| `assets/css/custom.css` | `.cur-sym` (0.72em, приглушённая, lowercase). `.slider_area .single_slider` — `cover/center/no-repeat !important` (Mazlay `background:#000` шорткатом сбрасывал size/position → чёрная полоса слева). `.categories_title` на мобиле `#d32f2f` (был двухцветный красный с `#C70909` Mazlay). |
| `assets/js/main.js` | Формат цены в выпадающем поиске выровнен под `1,180.00 смн`. |

### Мобильная вёрстка: единый отступ 16px (PR #70)

**Цель:** убрать рассинхрон краёв на мобильной — шапка была ýже контента.

| Файл | Что изменено |
|------|--------------|
| `assets/css/custom.css` | `@media (max-width:767px)`: `.header_middle .container`, `.header_bottom .container` (красная полоса «ВСЕ КАТЕГОРИИ»), `.top_tags_area .container` — горизонтальный паддинг `0 12px` → `0 16px`, чтобы совпадал с контентным `.container` и футером (везде 16px). |

> Десктоп не затронут (правки только в `@media max-width:767px`).
> Только CSS, миграции не нужны; cache-busting подтянет файл после
> `git pull`. Полная визуальная полировка ведётся по скриншотам
> конкретных экранов.

### Гранулярные права доступа суперадмина (PR #66, #67, #68)

**Цель:** суперадмин раздаёт каждому сотруднику (admin/manager)
индивидуально, какие разделы он может открывать и редактировать
(Товары, Наценки, Слайдер, Заказы, Пользователи, Категории, Бренды,
Блог, Страницы, Отзывы, Склад API, VIN).

| Файл | Что изменено / добавлено |
|------|--------------------------|
| `sql/migrate_permissions.sql` | **Новый.** Таблица `user_permissions(user_id PK, sections JSON, updated_at)`, FK→users cascade. Нет строки = действуют умолчания роли. |
| `superadmin/permissions.php` | **Новый.** Выбор сотрудника → чекбоксы всех 12 разделов → «Сохранить» / «Сбросить к умолчанию». |
| `includes/functions.php` | Хелперы: `permissionSections()` (каталог ключ→название), `permissionAlias()`, `roleDefaultSections($role)` (текущий доступ роли по умолчанию), `getUserConfiguredSections()`, `effectiveAllowedSections()`, `userCan($section)`, `requirePermission($section)`. `renderRoleSidebar()` фильтрует пункты по `userCan`. |
| Контролируемые страницы | `requirePermission('...')` сразу после `requireRole(...)`: `admin/products,sliders,orders,users`; `manager/parts,categories,brands,blog,pages,reviews`; `superadmin/warehouse,vin`. У `admin/sliders,orders,users` и `superadmin/vin,warehouse` расширен `requireRole` (добавлены admin/manager) — выданное право реально открывает страницу, без выдачи `requirePermission` блокирует (default-deny). |
| `admin/products.php`, `manager/parts.php` | Поля «Себестоимость/Наценка» скрыты без права `markup`; JS авторасчёта защищён от отсутствующих полей. |
| Сайдбары суперадмина | Пункт «Права доступа» во всех (renderRoleSidebar + захардкоженные), кнопка «Права доступа» на форме редактирования пользователя. |

> **Безопасный дефолт:** пока суперадмин не настроил пользователя —
> доступ ровно как раньше (`roleDefaultSections`). Суперадмин не
> ограничивается никогда. Код работает и до миграции (try/catch).

### Прямое ценообразование в СМН + контроль курса (PR #65)

**Цель:** сайт только для таджикского рынка — цена, введённая в
админке, = цена на витрине 1:1, без пересчёта из рублей.

| Файл | Что изменено / добавлено |
|------|--------------------------|
| `sql/tjs_direct_pricing.sql` | **Новый.** Курс `TJS = 1.0` (раньше 0.115 в `migrate_tajik_market.sql` занижал все цены ~×8.7). Цена в БД = цена на витрине. |
| `sql/migrate_tajik_market.sql` | Убрана устаревшая строка `rate = 0.115` (повторный запуск не возвращает баг). |
| `admin/products.php`, `manager/parts.php` | Подписи полей цены `(₽)` → `(СМН)`. |
| `superadmin/currencies.php` | Тексты/подписи под модель прямых цен: курс = множитель цены; для 1:1 держать TJS = 1.0. Курс уже управляется суперадмином (поле, «Сохранить все курсы», ЦБ РФ). |

### Фиксы мобильной версии и слайдера (PR #61–#64)

| Файл | Что изменено |
|------|--------------|
| `assets/mazlay-js/main.js` | Меню «ВСЕ КАТЕГОРИИ»: jQuery `.categories_title` slideToggle не запускается при ширине < 992px — мобильным владеет class-based `.is-open` из `app.js` (конфликт давал пустой блок). |
| `assets/css/custom.css` | Шапка убрана из списка `overflow-x:clip` (clip по X режет и Y → меню категорий «уходило за баннер»); шапке задан `overflow:visible`. ☰-иконка `.categories_title::before` показана слева на мобиле (как на десктопе), отступ у h2. |
| `assets/mazlay-js/main.js` | Баннер-слайдер: встроенный `autoplay` Owl (loop+animateOut) зависал после свайпа/ховера/смены вкладки — заменён на свой `setInterval(15s)`, пересобирается на `changed.owl.carousel`, синхронен с прогресс-точкой; пауза на ховере (десктоп). |

### UX отзывов и редактируемые тексты (PR #58, #59)

| Файл | Что изменено |
|------|--------------|
| `pages/reviews.php`, `catalog/part.php` | После отправки отзыва (`pending`) форма скрывается, показывается карточка «✅ Ваш отзыв отправлен». `rejected` → форма для повторной отправки; `approved` → форма редактирования. |
| `manager/reviews.php` | Сворачиваемая панель «Тексты сообщений» — суперадмин/менеджер редактирует тексты подтверждения / «на проверке» / «только после покупки». Хранится в `site_settings`, пустое поле → дефолт из lang. |
| `pages/reviews.php`, `catalog/part.php` | Тексты через `getSetting(... , t(...))` (фолбэк на lang-ключи). |
| `lang/ru|tg|en.php` | `review_purchase_only` поясняет: защита от накрутки, модерация через админку. |

### Фикс 500 при установке языка по умолчанию (PR #60)

| Файл | Что изменено |
|------|--------------|
| `superadmin/languages.php` | Убран `updated_at = NOW()` из `UPDATE languages` (в таблице `languages` нет такой колонки → `PDOException` → HTTP 500). Запрос к `site_settings` не тронут (там колонка есть). |

### Система отзывов: товары + магазин (PR #57)

**Цель:** реальные отзывы от покупателей вместо пустой заглушки, с
премодерацией и защитой от накруток.

**Правила:** отзыв оставляет только авторизованный пользователь; каждый
отзыв проходит модерацию; на товар можно оставить отзыв **только после
получения** (доставленный заказ); один отзыв на товар/магазин от
пользователя (повторная отправка перезаписывает и снова уходит на проверку).

| Файл | Что изменено / добавлено |
|------|--------------------------|
| `sql/migrate_reviews.sql` | **Новый.** Таблица `product_reviews` (part_id, user_id, rating 1-5, comment, status pending/approved/rejected, uk part+user, FK cascade). |
| `sql/migrate_reviews_v2.sql` | **Новый.** Таблица `shop_reviews` (отзывы о магазине) + колонка `is_featured` в `product_reviews`. Применять строго после `migrate_reviews.sql`. |
| `api/review_submit.php` | **Новый.** Приём отзыва на товар: CSRF, проверка авторизации, проверка покупки (`userPurchasedPart`), валидация, `INSERT … ON DUPLICATE KEY UPDATE` со сбросом в `pending`. |
| `api/shop_review_submit.php` | **Новый.** Приём отзыва о магазине (без проверки покупки, только авторизация). |
| `pages/reviews.php` | **Новый.** Публичная страница «Отзывы о магазине»: рейтинг компании, список одобренных, форма со звёздами. |
| `manager/reviews.php` | **Новый.** Модерация: переключатель Товары/Магазин, фильтры по статусу со счётчиками, одобрить / отклонить / удалить, тумблер «в витрину О нас» (is_featured). |
| `catalog/part.php` | Вкладка «Отзывы»: средний рейтинг, список, форма; гейтинг по покупке; статус своего отзыва. |
| `catalog/index.php`, `catalog/category.php`, `index.php` | Звёзды-рейтинг в карточках товаров (`getProductRatings` + `productStarsInline`). |
| `pages/about.php` | Блок «Что говорят клиенты» подтягивает реальные отзывы с `is_featured=1`; фолбэк на ручные `site_sections`, если ничего не помечено. |
| `includes/functions.php` | Новые хелперы: `starsHtml`, `getProductRatings`, `productStarsInline`, `userPurchasedPart`, `getShopRatingSummary`. Все запросы к таблицам отзывов обёрнуты в `try/catch (PDOException)` — страницы работают без миграции. |
| `includes/header.php`, `footer.php` | Ссылка «Отзывы о магазине» в шапке, мегаменю и футере. |
| Сайдбары менеджера | Пункт «Отзывы» с бейджем числа ожидающих модерации. |
| `lang/ru|tg|en.php` | Строки интерфейса отзывов (RU/TG/EN). |

> **Деградация без миграции:** если таблицы `product_reviews` /
> `shop_reviews` ещё не созданы, главная, каталог, категория, страница
> товара и «О нас» работают как раньше (запросы в `try/catch` →
> пустой результат). Сайт не упадёт при любом порядке деплоя.

### CMS страницы «О нас» и категории блога (PR #55, #56)

**Цель:** контент статичных блоков редактируется из админки, без правки кода.

| Файл | Что изменено / добавлено |
|------|--------------------------|
| `sql/migrate_cms.sql` | **Новый.** Колонка `category` в `blog_posts`; таблица `site_sections` (slug-keyed, многоязычные `title/subtitle/content_ru|tg|en`, image, sort_order, is_active). Дефолтные строки: hero, team, reviews, stores, signature, 3 benefit, 4 faq, 3 testimonial. |
| `manager/pages.php` | **Новый.** Редактор разделов «О нас»: 4 группы (основные / преимущества / FAQ / отзывы), поле «Роль/Должность» для отзывов, загрузка изображений. |
| `manager/blog.php` | Поле «Категория» (news / tips / review / other) в форме и списке статей. |
| `pages/about.php` | Все блоки (hero, подпись, 3 иконки преимуществ, FAQ-аккордеон, витрина) читаются из `site_sections` с фолбэком на `t()`-ключи. |
| `pages/blog.php` | Фильтр-табы по категориям (`?cat=`). |
| Сайдбары менеджера | Пункт «Страницы». |

### Только сомони (TJS / СМН) (PR #51, #53)

| Файл | Что изменено |
|------|--------------|
| `sql/only_tjs_currency.sql` | **Новый.** Отключает все валюты кроме TJS, символ `СМН`, `is_default=1`. |
| `includes/currency.php` | Фолбэк-валюта — TJS со символом `СМН`; `getCurrencySymbol()` → `СМН`. |
| `includes/header.php` | Удалён переключатель валют (десктоп + оффканвас). |

### Мега-меню навигации (PR #50)

| Файл | Что изменено |
|------|--------------|
| `includes/header.php` | Классы `az-has-megamenu` / `az-megamenu--sm` / `az-megamenu--wide`. |
| `assets/css/custom.css` | Стили мега-меню; `li.az-has-megamenu{position:relative}`, `--wide{position:static}`; `.sticky-header,.header_bottom{overflow:visible}`. |

### Cache-busting и фиксы вёрстки (PR #52, #53, #54)

| Файл | Что изменено |
|------|--------------|
| `includes/header.php`, `footer.php` | `?v=<filemtime>` к `custom.css`, `main.js`, `app.js` — браузер всегда тянет свежую статику. |
| `includes/functions.php` | `productImageUrl()` различает абсолютный URL и относительный путь — фикс «двойного URL» (фото товаров не отображались). |
| `assets/css/custom.css` | Subscribe-форма на `flexbox`; кнопка ТАМОС `inline-flex` height 50px. |
| `assets/mazlay-js/main.js`, `assets/js/app.js` | Кнопка «наверх» через `window.scrollTo({top:0,behavior:'smooth'})`. |

### Mobile P0 — адаптация под iPhone 15 Pro Max и SE (PR #16)

**Цель:** сделать сайт пригодным для использования с телефона
(320–430px), без горизонтального скролла, с видимыми кнопками
Войти/Регистрация и нормальными touch-targets ≥44×44px.

| Файл | Что изменено |
|------|--------------|
| `includes/header.php` | Добавлены `.mobile_menu_trigger` (гамбургер) и `.header_auth_mobile` (иконка auth) — видны только на мобиле. |
| `assets/css/custom.css` | Полностью переписана секция `@media (max-width: 991px)` и `≤767px`. Скрыт оригинальный `.canvas_open` из оффканваса (мы рисуем свой в шапке). |

Ключевые правила в `custom.css`:
```css
html, body { overflow-x: hidden !important; max-width: 100vw; }
.container { padding: 0 16px; max-width: 100%; }
.offcanvas_menu_wrapper { width: 86vw; max-width: 340px; left: 0; margin-left: -100vw; }
.offcanvas_menu_wrapper.active { margin-left: 0; }
button, a, .auth-link-mobile { min-width: 44px; min-height: 44px; }
```

**Структура мобильной шапки** (≤767px):
1. `header_top` — спрятан (`display: none`).
2. `header_middle` — логотип слева; справа `auth · wishlist · cart · hamburger`; поиск в отдельной строке во всю ширину.
3. `header_bottom` (красная полоса) — только «ВСЕ КАТЕГОРИИ».

### Динамические слайдеры (коммит b2d42e5)

**Было:** `index.php` рендерил три захардкоженных слайда из шаблона
Mazlay (`slider1.jpg`, `slider2.jpg`, `slider3.jpg`). Правки админа
в `admin/sliders.php` ни на что не влияли.

**Стало:** `index.php` читает из таблицы `sliders` и рендерит через
`foreach`. URL картинки нормализуется: абсолютный → как есть,
относительный → добавляется `APP_URL`. Если слайдов нет — секция
полностью скрыта.

```php
$sliders = $db->query("SELECT * FROM sliders WHERE is_active=1
                       ORDER BY sort_order ASC, id ASC")->fetchAll();
```

### Универсальный `renderRoleSidebar()` (коммит 41b82b2)

**Было:** каждая страница в `/admin/*` имела свой захардкоженный
сайдбар. Когда суперадмин переходил из `/superadmin/index.php` в
`/admin/sliders.php` — пропадали ссылки «Настройки», «Валюты»,
«Языки», «Бэкап», «Блог» (показывался узкий admin-сайдбар).

**Стало:** функция `renderRoleSidebar(string $active = '')` в
`includes/functions.php` смотрит `getCurrentUser()['role']` и
выводит правильный набор пунктов:
- `superadmin` — фиолетовый градиент, ★ Суперадмин, 11 пунктов.
- `admin` — тёмный фон, 5 пунктов.
- `manager` — тёмный фон, 5 пунктов.

Все страницы в `admin/`, `superadmin/`, `manager/` теперь зовут:
```php
<?php renderRoleSidebar('orders'); ?>  // active = текущий пункт
```

### Фикс падения каталога — `urlencode()` array (коммиты a1c849c, 5b7fdae)

**Симптом:** боты слали `?q[]=spam`, PHP 8.2 валился с
`TypeError: urlencode(): Argument #1 ($string) must be of type string, array given`.

**Корневых причин было две**:

1. `$_GET['q']` мог прийти массивом — `trim()`/`urlencode()` падали.
   Фикс — скалярный гард во всех публичных страницах
   (`catalog/index.php`, `search/index.php`, `catalog/category.php`):
   ```php
   $_getStr = static fn($k, $d='') => is_scalar($_GET[$k] ?? null)
                                      ? (string)$_GET[$k] : $d;
   $q = trim($_getStr('q'));
   ```

2. **Коллизия имени переменной** `$q` между `catalog/index.php` (где
   `$q` — строка поиска) и `includes/header.php` (где он использовал
   `$q = array_merge($queryParams, ['lang'=>$lCode])` в циклах языков
   и валют). После include шапка перетирала строку массивом и
   `http_build_query()` принимал массив в `urlencode()`.
   Фикс — переименовать в `header.php` локальную переменную
   на `$qHdr`.

### Безопасный `sanitize()` (коммит a1c849c)

`sanitize()` теперь корректно обрабатывает массивы и объекты —
возвращает пустую строку вместо TypeError:
```php
function sanitize($input): string {
    if (is_array($input) || is_object($input)) return '';
    return htmlspecialchars((string)($input ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
```

### Внешние ссылки и flash-уведомления (коммит a50c094)

- Соцссылки в футере получили `target="_blank" rel="noopener noreferrer"`.
- Flash-баннер живёт **8 секунд** вместо 4 (читаемее на медленном
  интернете), кнопка-крестик для ручного закрытия.

---

## Как продолжить разработку

### 1. Стиль кода

- Все запросы — **подготовленные** (`$db->prepare()`+`execute()`).
  Никаких конкатенаций пользовательских данных в SQL.
- Все выводы в HTML — через `sanitize($value)`.
- Все POST-формы должны включать CSRF-токен:
  ```html
  <input type="hidden" name="csrf_token" value="<?= sanitize(generateCsrfToken()) ?>">
  ```
  и валидироваться через `verifyCsrfToken($_POST['csrf_token'] ?? '')`.
- Любой `$_GET['x']` сначала пропускайте через `is_scalar()` —
  боты систематически шлют массивы.

### 2. Где править стили

- **Не трогайте** `assets/mazlay-css/style.css` — это шаблон, при
  обновлении Mazlay перетрётся.
- Все правки — в `assets/css/custom.css`, в конец секции, к которой
  они относятся (admin / storefront / mobile).
- Мобильная вёрстка живёт в `@media (max-width: 991px/767px/390px)`
  в самом низу файла.

### 3. Как добавить страницу в админке

1. Создайте `admin/myfeature.php`.
2. В начале — `require_once dirname(__DIR__) . '/config/config.php'` и
   `requireRole(['admin', 'superadmin'])`.
3. Подключите шапку: `require_once dirname(__DIR__) . '/includes/header.php'`.
4. Поставьте сайдбар: `<?php renderRoleSidebar('myfeature'); ?>` и
   добавьте новый пункт `myfeature` в массив навигации внутри
   `renderRoleSidebar()` в `includes/functions.php`.
5. Подключите футер: `require_once dirname(__DIR__) . '/includes/footer.php'`.

### 4. Как обновить production

На сервере (`/var/www/html/avtozapchast`):
```bash
sudo git pull origin main

# Применить новые миграции, если они появились в этом обновлении
# (идемпотентны — повторный запуск безопасен; порядок важен)
mysql -u avtouser -p avtozapchast < sql/migrate_cms.sql
mysql -u avtouser -p avtozapchast < sql/migrate_reviews.sql
mysql -u avtouser -p avtozapchast < sql/migrate_reviews_v2.sql

# Контент/профиль (этот цикл правок) — тоже идемпотентны:
mysql -u avtouser -p avtozapchast < sql/add_category_image.sql
mysql -u avtouser -p avtozapchast < sql/add_brand_logo.sql
mysql -u avtouser -p avtozapchast < sql/add_user_profile_fields.sql

# Ребрендинг + сортировка брендов (PR #95, #97):
mysql -u avtouser -p avtozapchast < sql/rename_to_autodoc.sql
mysql -u avtouser -p avtozapchast < sql/add_brand_sort_order.sql

# Папки загрузок должны быть доступны веб-серверу на запись
sudo chown -R www-data:www-data assets/uploads && sudo chmod -R 775 assets/uploads

sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

> Код устойчив к отсутствию новых таблиц/колонок (запросы в
> `try/catch`), но миграции всё равно нужно применить, чтобы
> функционал (отзывы, изображения, аватар, адрес) заработал.
> Если правился CSS/JS — сбросьте кэш браузера (Ctrl+F5 / инкогнито).

Если `git status` показывает 199 «изменённых» файлов — это разница
прав доступа. Лечится один раз:
```bash
sudo git config core.fileMode false
sudo git checkout -- .
```

### 5. Локальная разработка

```bash
git clone https://github.com/nnfirdavs96-cell/avtozapchast.git
cd avtozapchast
mysql -u root -p < sql/schema.sql
mysql -u root -p < sql/seed.sql
cp config/config.example.php config/config.php   # отредактируйте БД-доступы
php -S localhost:8000
```

Открыть `http://localhost:8000`. Дефолтные аккаунты — см. раздел
[Аккаунты по умолчанию](#аккаунты-по-умолчанию).

### 6. Ветвление и PR

- Разработка ведётся в feature-ветках: `feature/<задача>` или
  `fix/<тикет>`.
- Бот Claude использует ветку `claude/review-repository-CglIw` —
  не пушьте туда свои коммиты вручную.
- PR в `main` → review → squash-merge.

### 7. Контрольный список перед коммитом

- [ ] Все `$_GET`/`$_POST` обёрнуты в `is_scalar()` или
      приведены к нужному типу (`(int)`, `(string)`).
- [ ] POST-формы проверяют CSRF.
- [ ] Вывод данных — через `sanitize()`.
- [ ] SQL — только подготовленные запросы.
- [ ] Если правили `header.php`/`functions.php` — проверить, что
      ничего не сломалось у всех ролей (buyer/manager/admin/superadmin).
- [ ] Если правили CSS — проверить на 320px / 430px / 768px / 1280px.
- [ ] Если правили админку — проверить, что сайдбар не пропал.

---

## Мобильная адаптация (важно для разработчиков)

В проекте реализована подробная мобильная адаптация поверх Mazlay HTML-шаблона.
Все мобильные правки сосредоточены в **двух местах**, чтобы не задевать десктоп.

### Где живут мобильные правки

| Файл | Назначение |
|------|-----------|
| `assets/css/custom.css` | Все CSS-правки. Только внутри `@media (max-width: 991px)`, `@media (max-width: 767px)`, `@media (max-width: 390px)` |
| `assets/js/app.js` | JS-помощники: бургер, выпадающее меню категорий, accordion в сайдбаре фильтров |

**ВАЖНО:** не редактируйте `mazlay-template/css/style.css` и `mazlay-template/js/main.js`
— это поставляемый шаблон. Все override-ы делаем в `custom.css` / `app.js`.

### Ключевые исправления

#### 1. Горизонтальный скролл (root cause)
Mazlay `.mini_cart` использует `position: fixed; min-width: 355px`. iOS Safari
учитывает фиксированные элементы в ширине документа → горизонтальный скролл.
**Решение:** `.mini_cart { display: none !important }` на мобиле (≤991px).

#### 2. Иконка корзины на мобиле
Mazlay-овая мини-корзина отключена. Клик по иконке корзины на мобиле
ведёт сразу на `/buyer/cart.php`. См. `assets/js/app.js`:
```javascript
document.querySelectorAll('.mini_cart_wrapper > a').forEach(function (a) {
    a.addEventListener('click', function (e) {
        if (isMobile()) {
            e.preventDefault();
            window.location.href = (window.APP_URL || '') + '/buyer/cart.php';
        }
    }, true);
});
```

#### 3. Бургер-меню (offcanvas)
Используется Mazlay-овский `.offcanvas_menu_wrapper`. При открытии
панели тело страницы блокируется через `body.no-scroll` — следит
`MutationObserver` в `app.js`.

#### 4. Кнопка "ВСЕ КАТЕГОРИИ" на мобиле
Mazlay по умолчанию показывает дропдаун по hover (не работает на тач).
Кроме того, `.sticky-header` имеет `overflow: hidden`, который обрезает
абсолютно позиционированный дропдаун.

**Решение:**
- В CSS: `.sticky-header, .header_bottom { overflow: visible !important }` на мобиле
- Дропдаун скрыт по умолчанию (`display: none`), показывается через
  класс `.is-open` (z-index: 9999)
- В JS: клик по `.categori_toggle` тогглит класс `.is-open`;
  клик вне `.categories_menu` — закрывает

#### 5. Карусели Owl на мобиле → CSS Grid
Owl Carousel инициализируется в Mazlay `main.js` и оборачивает items
в `.owl-stage-outer > .owl-stage > .owl-item`. На мобиле это даёт
неудобную карусель с одним товаром в ряд.

**Решение:** не уничтожаем Owl через JS (это оставляет в DOM
обёртки с `overflow:hidden` и `transform:translate3d`), а
переопределяем его CSS напрямую:
```css
.product_carousel.owl-loaded .owl-stage-outer { overflow: visible !important; }
.product_carousel.owl-loaded .owl-stage {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    transform: none !important;
    width: 100% !important;
}
.product_carousel.owl-loaded .owl-item,
.product_carousel.owl-loaded .col-lg-3,
.product_carousel.owl-loaded .product_items { display: contents !important; }
.product_carousel.owl-loaded .owl-nav,
.product_carousel.owl-loaded .owl-dots { display: none !important; }
```

`display: contents` делает обёртки прозрачными → `single_product`
становится прямым children для grid. Тот же приём — для
`.categories_product_inner.owl-loaded`.

#### 6. Подписи карточек товаров
Поле `product_thumb img` фиксируем:
```css
.single_product .product_thumb a img,
.single_product .product_thumb img {
    width: 100% !important;
    height: 140px !important;
    object-fit: contain !important;
    background: #f7f7f7;
}
```

#### 7. Newsletter form (подписка) и КОНТАКТЫ
Mazlay использует `position: absolute; right: 0` для кнопки внутри
поля ввода → на мобиле вылезает за экран. **Решение:**
```css
.subscribe_form form { display: flex; flex-direction: column; }
.subscribe_form form input, .subscribe_form form button {
    position: static !important; width: 100% !important;
}
```

#### 8. Sidebar фильтров на странице каталога
На мобиле каждый `.sidebar_widget .widget_list` превращается в
аккордеон с заголовком-toggle и сворачивающимся body — см. `app.js`.

#### 9. Глобальные правила
```css
@media (max-width: 767px) {
    html, body { overflow-x: hidden !important; max-width: 100vw; }
    img { max-width: 100% !important; height: auto; }
}
```

### Чек-лист при правке CSS / JS

- [ ] Десктоп-вёрстка (≥992px) не пострадала
- [ ] Тестировать на 320px, 390px (iPhone 14/15), 768px (планшет)
- [ ] Нет горизонтальной прокрутки на всех страницах
- [ ] Tap-target ≥ 44×44px (кнопки, ссылки)
- [ ] Шрифт ≥ 16px на input — иначе iOS Safari зумит при фокусе
- [ ] Проверить главную, каталог, страницу товара, корзину, профиль,
      auth/login, admin

### Обновления на сервере

```bash
cd /var/www/html/avtozapchast
git pull origin main

# Применить НОВЫЕ миграции (идемпотентны, повторный запуск безопасен):
mysql -u avtouser -p avtozapchast < sql/migrate_permissions.sql   # права (один раз)
mysql -u avtouser -p avtozapchast < sql/tjs_direct_pricing.sql    # цены 1:1 в СМН
mysql -u avtouser -p avtozapchast < sql/rename_to_avtodoc.sql     # имя сайта → AvtoDoc
# (schema_autoeuro.sql — только если используете внешний склад AutoEuro)

# Разделение админки на порт 8888 (один раз, см. «Apache → Отдельный
# порт для админ-панели»): добавить Listen 8888 + VirtualHost, затем
apache2ctl configtest && systemctl reload apache2   # ОБЯЗАТЕЛЬНО configtest до reload

# Если правился JS/CSS — почистить кэш браузера / открыть в инкогнито.
#   На iOS Safari — закрыть вкладку полностью или очистить данные сайта.
# Если правился PHP — рестарт PHP-FPM:
sudo systemctl reload php8.1-fpm
```

> Cache-busting (`?v=<filemtime>`) обновляет CSS/JS автоматически
> **после `git pull`** (меняется дата файла). Без pull браузер тянет
> старую статику.

### История мобильных исправлений (PRs)

| PR | Что исправлено |
|----|---------------|
| #18 | Базовая мобильная вёрстка: бургер, корзина-иконка, sticky header |
| #19 | "Второй гамбургер" (categories_title::before icon) |
| #20 | Карточки товаров: фикс. высота картинок, 2 колонки grid |
| #21 | Newsletter / КОНТАКТЫ кнопки full-width, categories dropdown |
| #22 | Class-based categories toggle, Owl destroy approach |
| #23 | Owl CSS override (вместо destroy), `.sticky-header { overflow: visible }` |

---

## Последние изменения (сессия Claude Code)

> Всё ниже сделано в одной сессии разработки; все изменения доступны в `main`.
> Для применения на сервере достаточно `git pull origin main`.

### Краткий Changelog

| PR | Что сделано |
|----|-------------|
| #139 | Маска телефона +992 (Таджикистан) без «отскока» при удалении |
| #140 | Выбор страны для телефона + управление из настроек суперадмина |
| #141 | ~38 стран с флагами, выпадающий список с поиском в настройках |
| #142 | Реальные PNG-флаги (flagcdn.com) вместо emoji (сломанных в Windows) |
| #143 | Автозаполнение картинок товаров без фото (заглушки product1-13.jpg) |
| #144 | Подкатегории в «ВСЕ КАТЕГОРИИ» (seed-функция) |
| #145 | setSetting() + одноразовый seed через header.php вместо admin-триггера |
| #146 | Фикс seed: NOT NULL image_path_mobile, per-INSERT try/catch, bump flag |
| #147 | Виджет «КАТЕГОРИИ» в каталоге: подкатегории + скролл |
| #148 | Сворачиваемые подкатегории (стрелка), скролл брендов, 30+ брендов, тумблер «В наличии» |
| #151–#152 | Checkout: колонка `payment_method`; отмена заказа покупателем + контакт поддержки |
| #153–#156 | Доставка по городам (такси) для Таджикистана; выбор страны меняет список городов |
| #157–#159 | Скидки/новинки/хиты: бейджи и фильтры; блок «Товары со скидкой» на главной |
| #161 | Регистрация и вход по номеру телефона (SMS-код) рядом с email |
| #162 | Улучшен стиль выпадающего списка кода страны у телефона |
| #163 | Управление верхним баннером каталога из админ-панели |
| #164 | Сгруппированный сайдбар админки, live-превью баннеров, расширенное делегирование прав |
| #165 | Автозаполнение баннеров картинками из шаблона Mazlay (slider1–3.jpg) |
| #170–#174 | Блок скидок: убран белый фон и кнопка «Войти»; живое обновление корзины; фикс закрытия и z-index мини-корзины |
| — | Вход сотрудников по телефону+PIN; тумблер «Вход по email» в настройках; email/пароль необязательны для сотрудника |
| #175–#185 | Интеграция PartsAPI: VIN-декодер `VINdecodeOE` + каталог по VIN `getPartsbyVIN`; настройки и тест-кнопка в админке; справочник 751 товарной группы; серверный кэш |
| #186 | Фикс VIN/каталога: не кэшировать залипший пустой результат (`type=all`→`oem`-guard), версия декода, приоритет OEM-групп |
| #187 | **Надёжный HTTP-транспорт `httpGet()` (cURL → fallback file_get_contents)** для VIN и каталога — снят корневой отказ «источник: local / ошибок == групп» на shared-хостинге, где `file_get_contents` по HTTPS молча падает; диагностика `diag_partsapi.php` |
| #189 | Штатная обработка лимита PartsAPI (`error_code 5000`, HTTP 401 «Exceeded the number of requests»): флаг `rate_limited`, ранний выход без сжигания квоты, понятное сообщение на странице VIN и в админ-тесте. Диагностика `diag_partsapi.php` подтвердила: транспорт исправен, причина пустого каталога — суточный лимит запросов с IP демо-ключа |
| #191 | **Дерево каталога по узлам + аналоги-кроссы.** Клик по узлу = один запрос `getPartsbyVIN(cat)` вместо перебора десятков групп (бережёт лимит ключа). МОСТИК цепочки: кнопка ⇄ у позиции → `getCrosses` → сверка аналогов со складом (`api/vin_crosses.php`, `crossesWithWarehouse`). Узлы настраиваются в админке (`catalog_api_oem_nodes`, строки `ID=Название`). Новый кэш `partsapi_kv_cache`. Цепочка: **VIN → узел → деталь → № → (кроссы) → мой товар → корзина** |

---

### Детальное описание изменений

#### 🔑 Пароль: кнопка показать/скрыть

**Файлы:** `auth/login.php`, `auth/register.php`, `assets/js/app.js`, `assets/css/custom.css`

На формах входа и регистрации добавлена кнопка-глаз (FontAwesome) для показа/скрытия пароля.

```html
<!-- Разметка: оборачиваем поле в .pwd-field -->
<span class="pwd-field">
    <input type="password" name="password" ...>
    <button type="button" class="pwd-toggle" aria-label="Показать пароль">
        <i class="fa fa-eye"></i>
    </button>
</span>
```

JS (в `app.js`) через делегирование по `.pwd-toggle`:
```js
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.pwd-toggle');
    if (!btn) return;
    var input = btn.closest('.pwd-field')?.querySelector('input');
    if (!input) return;
    var show = input.type === 'password';
    input.type = show ? 'text' : 'password';
    var icon = btn.querySelector('i');
    if (icon) icon.className = show ? 'fa fa-eye-slash' : 'fa fa-eye';
});
```

CSS — перебивает красный стиль темы:
```css
.pwd-field .pwd-toggle {
    background: transparent !important;
    color: #aaa !important;
    border: none !important;
    /* ... */
}
```

---

#### 📷 Аватар покупателя: доступ разрешён

**Файл:** `api/upload.php`

Покупатели (`role = buyer`) получили права загружать только аватары (`type = avatars`). Остальные типы загрузки по-прежнему требуют роли manager/admin/superadmin.

```php
$isStaff = in_array($_SESSION['role'] ?? '', ['manager', 'admin', 'superadmin']);
if (!$isStaff && $type !== 'avatars') {
    http_response_code(403); echo json_encode(['error' => 'Доступ запрещён']); exit;
}
```

---

#### 📱 Маска телефона +992 (Таджикистан) и выбор страны

**Файлы:** `assets/js/app.js`, `includes/functions.php`, `includes/footer.php`, `includes/admin-footer.php`, `superadmin/settings.php`, `assets/css/custom.css`

**Маска:**
- По умолчанию `+992 (XX) XXX-XX-XX` (Таджикистан).
- Алгоритм маски не добавляет trailing-разделители → при backspace цифры удаляются без «отскока».

**Выбор страны:**
- ~38 стран с кодами набора, реальными PNG-флагами (https://flagcdn.com/w40/{code}.png) и масками.
- Если в настройках включено >1 страны — появляется custom dropdown с флагами.
- Если 1 страна — показывается только флаг без dropdown.

**Каталог стран (единый источник истины в PHP):**
```php
// includes/functions.php
function phoneCountriesCatalog(): array {
    return [
        ['code'=>'tj','dial'=>'992','flag'=>'🇹🇯','name'=>'Таджикистан','mask'=>'(XX) XXX-XX-XX'],
        ['code'=>'ru','dial'=>'7',  'flag'=>'🇷🇺','name'=>'Россия',     'mask'=>'(XXX) XXX-XX-XX'],
        // ... ~38 стран
    ];
}

function enabledPhoneCountries(): array { /* фильтрация по настройке phone_countries */ }
```

**Инъекция в JS:**
```php
// includes/footer.php и includes/admin-footer.php
<script>window.PHONE_COUNTRIES = <?= json_encode(enabledPhoneCountries(), JSON_UNESCAPED_UNICODE) ?>;</script>
```

**Управление из суперадмина:**  
`Суперадмин → Настройки → Страны телефонных кодов` — multiselect с флагами, Таджикистан всегда первый и обязательный.

---

#### 🏷️ Автозаполнение картинок товаров

**Файл:** `includes/functions.php` → `fillMissingProductImages()`

Товарам без изображений назначаются шаблонные фото `product1.jpg..product13.jpg` (из темы Mazlay), подбираемые по `id % 13 + 1`. Идемпотентно: трогает только строки с пустым полем `images`.

Запускается **один раз** при первом заходе на сайт через флаг `prod_imgseed_done` в `site_settings`.

---

#### 🗂️ Подкатегории и мега-меню «ВСЕ КАТЕГОРИИ»

**Файл:** `includes/functions.php` → `seedCategorySubcategories()`

Функция заполняет 6 родительских категорий подкатегориями:

| Категория | Подкатегории |
|-----------|-------------|
| Двигатель | Поршни и кольца, Клапаны, Прокладки ГБЦ, Масляный насос, Ремни ГРМ, Масляные фильтры |
| Тормозная система | Тормозные колодки, Тормозные диски, Суппорты, Тормозные шланги, Тормозная жидкость |
| Подвеска | Амортизаторы, Пружины, Рычаги, Шаровые опоры, Сайлентблоки, Стойки стабилизатора |
| Электрика | Аккумуляторы, Стартеры, Генераторы, Свечи зажигания, Датчики, Реле и предохранители |
| Кузов | Бамперы, Капоты, Крылья, Зеркала, Фары, Решётки радиатора |
| Трансмиссия | Сцепление, Маховики, ШРУСы, Карданные валы, Подшипники ступицы |

Запускается **один раз** через флаг `cat_subseed_v2` (не при каждом заходе).  
**Удалённые категории не восстанавливаются** — seed устанавливает флаг и больше не запускается.

---

#### ⚙️ setSetting() — хелпер настроек

**Файл:** `includes/functions.php`

```php
function setSetting(string $key, string $value): void {
    $db->prepare(
        "INSERT INTO site_settings (`key`, `value`) VALUES (?,?)
         ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()"
    )->execute([$key, $value, $value]);
}
```

Дополняет существующий `getSetting()`. Используется для записи one-time флагов из `header.php`.

---

#### 🚀 Автозапуск seed при первом заходе

**Файл:** `includes/header.php`

```php
// Запускается один раз после деплоя, флаги в site_settings:
if (!getSetting('cat_subseed_v2', '')) {
    seedCategorySubcategories();
    setSetting('cat_subseed_v2', '1');
}
if (!getSetting('prod_imgseed_done', '')) {
    fillMissingProductImages();
    setSetting('prod_imgseed_done', '1');
}
if (!getSetting('brands_seed_done', '')) {
    seedBrands();
    setSetting('brands_seed_done', '1');
}
```

**Важно:** seed запускается до `getCategories()`, поэтому меню уже содержит подкатегории при первом же рендере.

---

#### 🏭 seedBrands() — 30 популярных брендов

**Файл:** `includes/functions.php` → `seedBrands()`

Добавляет при первом старте: Bosch, Brembo, Denso, Febi, Gates, Monroe, NGK, SKF, Mann-Filter, Mahle, Sachs, TRW, Valeo, Continental, LuK, Hella, Lemförder, ATE, Mobil, Castrol, Liqui Moly, Aisin, KYB, Exedy, Delphi, Optimal, Ruville, Zimmermann, Nipparts, Blue Print.

Запускается один раз (флаг `brands_seed_done`). Бренды видны и управляемы в `manager/brands.php`.

---

#### 📋 Виджет «КАТЕГОРИИ» в каталоге

**Файлы:** `catalog/index.php`, `assets/css/custom.css`, `assets/js/app.js`

- Подкатегории выводятся под каждой родительской категорией.
- **По умолчанию свёрнуты** — раскрываются стрелкой `▼` (кнопка `.cat_toggle`).
- Активная ветка автоматически раскрывается при загрузке страницы.
- Список ограничен **360px со скроллом** (тонкий кастомный скроллбар).
- Аналогичный скролл добавлен для виджета «ПО БРЕНДУ».

**Markup:**
```html
<li class="cat_parent is_open has_children">
    <div class="cat_parent_row">
        <a href="...">Двигатель</a>
        <button class="cat_toggle"><i class="fa fa-angle-down"></i></button>
    </div>
    <ul class="cat_children">
        <li><a href="...">Поршни и кольца</a></li>
        ...
    </ul>
</li>
```

**JS:** При клике на `.cat_toggle` переключает класс `.is_open` на `<li>`. CSS анимирует `max-height` от 0 до 600px.

---

#### 🔘 Тумблер «В наличии»

**Файлы:** `catalog/index.php`, `assets/css/custom.css`, `assets/js/app.js`

Вместо двух ссылок «Все товары» / «В наличии» — CSS-тумблер, который при переключении делает `window.location.href` на нужный URL (сохраняет все текущие GET-параметры).

```html
<label class="avail_toggle">
    <input type="checkbox" id="avail_in_stock" <?= $inStock ? 'checked' : '' ?>
           data-on="?...&in_stock=1"
           data-off="?...">  <!-- без in_stock -->
    <span class="avail_switch"></span>
    <span class="avail_text">В наличии</span>
</label>
```

---

### Как продолжить разработку

#### После `git clone` / `git pull`

```bash
# 1. Обновить на сервере:
cd ~/public_html
git pull origin main

# 2. Открыть любую страницу сайта в браузере.
#    header.php автоматически выполнит все pending seed-функции (один раз):
#    - seedCategorySubcategories()  → подкатегории в меню
#    - fillMissingProductImages()   → картинки для товаров без фото
#    - seedBrands()                 → 30 популярных брендов
#    Повторные заходы — без overhead.

# 3. При добавлении нового seed:
#    а) Написать функцию seedXxx() в includes/functions.php
#    б) Добавить в includes/header.php:
#       if (!getSetting('xxx_seed_done', '')) { seedXxx(); setSetting('xxx_seed_done', '1'); }
#    в) Имя флага менять при исправлении уже запустившегося seed
#       (например 'xxx_seed_v2') — иначе он не повторится на серверах.
```

#### Добавление подкатегории вручную

1. Суперадмин / Менеджер → **Категории** → «Новая категория»
2. Указать название, слаг, выбрать **Родительскую категорию**
3. Сохранить — подкатегория сразу появляется в меню и боковом виджете

#### Добавление страны для телефона

1. Суперадмин → **Настройки** → раздел «Страны телефонных кодов»
2. Выбрать нужные страны из списка
3. Сохранить — стране-тумблер появится у всех форм с телефоном

Для добавления новой страны в каталог — отредактировать `phoneCountriesCatalog()` в `includes/functions.php`.

#### Сброс seed-флага вручную (если нужно перезапустить)

```sql
DELETE FROM site_settings WHERE `key` IN ('cat_subseed_v2', 'prod_imgseed_done', 'brands_seed_done');
```
Или через PHP: откройте любую страницу — seed запустится снова (только добавит недостающее, дубликатов не будет).

---

### 📲 Регистрация и вход по номеру телефона (SMS) — PR #161, #162

**Файлы:** `auth/register.php`, `auth/login.php`, `api/sms_auth.php`, `includes/functions.php`, `includes/header.php`, `buyer/profile.php`, `buyer/checkout.php`, `sql/add_phone_auth.sql`, `assets/js/app.js`, `assets/css/custom.css`

Покупатель теперь может зарегистрироваться и войти **по номеру телефона с кодом из SMS** — рядом с привычным способом по email. На формах входа/регистрации появились вкладки **«По номеру» / «По email»**.

**Схема БД (миграция одноразовая, флаг `phone_auth_schema_v1`):**
- `users.email` и `users.password_hash` стали NULLABLE (у телефонных аккаунтов их может не быть)
- добавлена колонка `users.phone_e164` (нормализованный номер) + индекс
- новая таблица `phone_otp` (код-хэш, назначение, попытки, срок действия, расход)

**Логика OTP** (`createPhoneOtp()` / `verifyPhoneOtp()`):
- код 4 цифры, срок жизни 5 минут, одноразовый
- антифлуд: 60 сек между отправками, не более 5 в час, до 5 попыток ввода

**Нормализация номера** (`normalizePhone()`): локальный таджикский 9-значный → префикс `992`; `8XXXXXXXXXX` → `7XXXXXXXXXX`.

**SMS-шлюз пока в тестовом режиме:** реальная отправка не подключена — код показывается на экране и пишется в `storage/sms.log`. Чтобы включить боевую отправку, добавьте провайдера в `sendSms()`.

> На сервере миграция выполняется автоматически при первом заходе (вызов `ensurePhoneAuthSchema()` в `header.php`). Ручной SQL — в `sql/add_phone_auth.sql`.

---

### 🖼 Управление баннерами каталога и автозаполнение из шаблона — PR #163, #164, #165

**Файлы:** `admin/banners.php`, `catalog/index.php`, `index.php`, `includes/functions.php`, `includes/header.php`

**Баннер каталога из админки (#163):** раньше верхний баннер магазина был захардкожен (`banner23.jpg`). Теперь у баннеров есть поле **`placement`**:
- `home` — три баннера под слайдером главной
- `catalog` — широкий баннер вверху страницы магазина

Каталог берёт активный баннер с `placement='catalog'`, при отсутствии — откатывается на изображение по умолчанию.

**Сгруппированный сайдбар + live-превью + делегирование (#164):**
- Меню админки разбито на группы: **Обзор · Каталог · Продажи · Контент · Доступ · Система**
- В форме баннера — живое превью: показывает, как баннер будет выглядеть в зависимости от `placement` и загруженной картинки
- Делегирование прав расширено: суперадмин может выдать доступ к доставке, настройкам, валютам и языкам. Права `permissions` и `backup` остаются только у суперадмина.

**Автозаполнение баннеров из шаблона Mazlay (#165):**
- Функция `seedBanners()` при пустой таблице `banners` добавляет 3 баннера с картинками слайдера из шаблона (`/assets/img/slider/slider1–3.jpg`)
- Вызов в `header.php` под флагом `banners_seed_done` — выполняется один раз
- Баннеры ведут на `/catalog/index.php`; картинки и ссылки потом легко меняются в **Админ → Баннеры**

> Текст слайдера и баннеры — разные сущности: у баннеров только картинка + ссылка, тексты накладываются только на слайдере (Админ → Слайдеры).

---

## Интеграция внешнего каталога (PartsAPI / TecDoc) + VIN

Сайт поставляется **под ключ**: интеграционный слой для внешнего каталога
запчастей и расширенного VIN-декодера уже встроен и **проверен в бою**
(autodoc.tj). Клиент покупает подписку (рекомендуется **PartsAPI.ru** — даёт
TecDoc-каталог + VIN-декодер по оригинальным каталогам, работает в СНГ/России),
вставляет ключи в админке — и каталог оживает. **Пока ключи не введены и
тумблер выключен — сайт работает как обычно**: бесплатный декодер NHTSA +
локальная база WMI + собственный каталог товаров. Ничего не ломается.

### Два метода PartsAPI

Используются **два ключа** (у каждого метода свой):

| Метод | Назначение | Настройка `vin_api_key` / `catalog_api_key` |
|-------|------------|---------------------------------------------|
| `VINdecodeOE` | Расшифровка VIN по оригинальным каталогам (марка, модель, год, модификация) | `vin_api_key` |
| `getPartsbyVIN` | Список запчастей на авто по VIN, по товарным группам | `catalog_api_key` |
| `getCrosses` | Аналоги-кроссы по номеру детали (№ → список аналогичных №) — МОСТИК к своему складу | `catalog_api_key` |

### Где настраивается

**Суперадмин → VIN-поиск → вкладка «Настройки API»** (`superadmin/vin.php`):

| Блок | Что задаётся |
|------|--------------|
| **Настройки VIN API** | Провайдер (`NHTSA` / `PartsAPI` / другой), URL-шаблон, ключ |
| **Каталог запчастей (внешний API)** | Тумблер, ключ, вид запчастей (оригинал/аналог), число групп |
| **Проверить соединение** | Тестовый перебор первых групп + фрагмент ответа |

### Важная особенность `getPartsbyVIN`

У PartsAPI **нет** запроса «дай все запчасти по VIN». Запчасти выдаются по
**товарным группам**: `getPartsbyVIN(vin, type, cat)` возвращает позиции одной
группы `cat`. Поэтому каталог собирается **перебором групп** (справочник на 751
группу в `includes/partsapi_cats.php`).

Так как каждая группа — отдельный запрос, важен параметр
`catalog_api_max_groups`:
- **демо-ключ** (≈50 запросов/сутки) → ставьте `15–25`;
- **платный тариф** → можно больше или `0` (все 751).

Результат каждого VIN **кэшируется** в таблице `partsapi_catalog_cache` на 30
дней, поэтому повторные запросы того же VIN мгновенны и не тратят лимит. Кэш
**версионируется** (`CACHE_VER`): после изменения логики разбора старые записи
автоматически игнорируются — чистить вручную не нужно.

Ответ группы: `[{group, name, parts, shortname}]`, где `parts` —
`"БРЕНД|АРТИКУЛ,БРЕНД|АРТИКУЛ,…"`: несколько вариантов (аналоги от разных
брендов) на одну деталь, через запятую, внутри пары разделитель `|`. Каждая
пара разворачивается в отдельную позицию. **Цен API не отдаёт** — если артикул
совпал с товаром на своём складе (`parts.part_number`), подставляется своя
цена/наличие/«в корзину»; иначе позиция показывается как «под заказ».

### Как работает VIN-поиск (поток)

1. Покупатель вводит VIN на `/pages/vin.php`.
2. `VinService::validate()` — проверка формата: 17 символов, только `A–Z`/`0–9`
   без `I`/`O`/`Q`. **Контрольная цифра (9-я позиция) не требуется** — её
   соблюдают только производители Северной Америки, а японские/корейские/
   европейские VIN (напр. Mitsubishi `Z8TXLCW6WCM902224`) её не проходят.
3. `VinService::decode()`:
   - **локальная база WMI** (мгновенно, без интернета) — марка/страна/год по
     первым символам; покрывает Lada/ГАЗ/УАЗ и крупные мировые марки;
   - если включён провайдер `partsapi` — запрос `VINdecodeOE`; реальный ответ
     `{"data":{"array":{…}}}` с транслит-ключами (`brend`, `naimenovanie`,
     `modely`, `modifikaciya`, `data`, `rynok`) разбирается и сливается с локальным;
   - результат кэшируется в `vin_cache` (30 дней).
4. Показывается карточка авто: марка, модель, год, модификация, рынок, кузов.

### Как работает каталог по VIN (поток)

**Дерево МАШИНА → УЗЕЛ → ДЕТАЛЬ → № (по клику на узел):**

1. Под карточкой авто — кнопки-узлы (Кузов и т.д. из настройки `catalog_api_oem_nodes`).
   Первый узел подгружается автоматически, остальные — по клику.
2. Клик по узлу → `api/vin_catalog.php?vin=…&cat=ID` → `CatalogApi::searchByVinCat()`
   делает **один** запрос `getPartsbyVIN(vin, type, cat)` (бережёт лимит ключа,
   в отличие от перебора десятков групп). Кнопка «Все узлы» при необходимости
   запускает полный перебор (`searchByVin()`).
3. Позиции разбираются на пары бренд/артикул, дедуплицируются, сопоставляются со
   своим складом (обогащение ценой/наличием/ссылкой) и кэшируются
   (`partsapi_kv_cache` для узлов, `partsapi_catalog_cache` для полного перебора).
4. JS рисует таблицу по узлам: свои товары — с ценой и «в корзину», прочие —
   «под заказ» + ссылка «найти».

**МОСТИК — аналоги по кроссам (№ → getCrosses → мой склад):**

5. У каждой позиции — кнопка ⇄: `api/vin_crosses.php?article=№` →
   `CatalogApi::crossesWithWarehouse()` берёт исходный № + его кроссы (`getCrosses`)
   и сверяет их со складом. Совпавший артикул (исходный **или** его аналог) →
   своя цена/наличие/«в корзину»; нет на складе → «под заказ» + «найти».
   Так покупатель находит **аналог в наличии**, даже если оригинала на складе нет.

### Что теперь можно делать

**Покупателю:**
- ввести VIN → увидеть свой автомобиль и подобрать запчасти по оригинальному
  каталогу производителя;
- видеть варианты-аналоги (разные бренды) на одну деталь;
- товары в наличии — сразу в корзину; остальные — найти в каталоге / под заказ.

**Магазину (админка → VIN-поиск):**
- включать/выключать каталог одним тумблером;
- переключать оригинал ↔ аналоги (`type`);
- регулировать число опрашиваемых групп под лимит ключа;
- проверять соединение и сбрасывать кэш;
- вести собственный каталог совместимости (вкладки «Автомобили» / «Совместимость»).

**Связка со своим складом:**
- заведите товары с теми же артикулами (`part_number`), и они автоматически
  подсветятся ценой и наличием в выдаче по VIN — каталог превращается в витрину
  «что есть у нас именно под эту машину», а остальное идёт под заказ.

### Настройки в `site_settings`

| Ключ | Назначение | По умолчанию |
|------|------------|--------------|
| `vin_search_enabled` | Включить VIN-поиск на сайте `0`/`1` | `1` |
| `vin_api_provider` | `nhtsa` · `partsapi` · `custom` | `nhtsa` |
| `vin_api_url` | URL-шаблон VINdecodeOE (`{VIN}`/`{KEY}`) | — |
| `vin_api_key` | Ключ VINdecodeOE | — |
| `vin_api_timeout` | Таймаут, сек | `8` |
| `catalog_api_enabled` | Тумблер каталога `0`/`1` | `0` |
| `catalog_api_key` | Ключ getPartsbyVIN | — |
| `catalog_api_type` | `oem` (оригинал) · `''` (аналог) | `oem` |
| `catalog_api_max_groups` | Сколько групп опрашивать (`0` = все 751) | `25` |
| `catalog_api_oem_nodes` | OEM-узлы дерева, строки `ID=Название` (напр. `1191=Кузов`) | `1191=Кузов` |
| `catalog_api_base` | Базовый URL API | `https://api.partsapi.ru/` |
| `catalog_api_timeout` | Таймаут, сек | `12` |

> Миграций БД не требуется: настройки — в `site_settings`, кэш-таблицы
> `partsapi_catalog_cache` (полный перебор) и `partsapi_kv_cache` (узлы + кроссы)
> создаются автоматически при первом обращении.

### Как это устроено в коде

- `includes/partsapi_cats.php` — справочник `PARTSAPI_CATS` (751 группа) +
  `PARTSAPI_POPULAR` (ходовые группы, опрашиваются первыми).
- `includes/catalog_api.php` — класс `CatalogApi`: перебор групп
  `getPartsbyVIN` (`searchByVin`) и загрузка **одного узла** (`searchByVinCat`),
  аналоги-кроссы `getCrosses` → склад (`crossesWithWarehouse`), узлы дерева
  (`oemNodes`), разбор `БРЕНД|АРТИКУЛ`, обогащение со склада, версионируемый кэш.
  Инертен, пока `catalog_api_enabled=0`.
- `includes/vin_service.php` — провайдер `partsapi` понимает реальный формат
  `VINdecodeOE` (`{"data":{"array":{…}}}` с транслит-ключами); `validate()` не
  требует контрольную цифру.
- `api/vin_catalog.php` — AJAX-эндпоинт каталога по VIN; параметр `&cat=ID`
  грузит один узел, без него — полный перебор.
- `api/vin_crosses.php` — AJAX-эндпоинт аналогов-кроссов по номеру детали
  (`getCrosses` + сверка со складом).
- `pages/vin.php` — карточка авто рендерится сразу; каталог — дерево узлов
  (клик грузит узел), у каждой позиции кнопка ⇄ для аналогов-кроссов.
- `includes/functions.php` → **`httpGet()`** — единый HTTP-хелпер для внешних
  запросов: приоритет **cURL** (несёт свой CA-bundle, работает на shared-хостинге,
  где `allow_url_fopen=Off` или у stream-обёртки нет CA), фолбэк — `file_get_contents`.
  И `VinService`, и `CatalogApi` ходят только через него.

### Диагностика связи (если каталог пуст / «источник: local»)

Симптом: `ошибок == групп` и `источник: local` одновременно — значит **все**
запросы к `api.partsapi.ru` падают. Чаще всего это **не лимит ключа**, а
транспорт: на ряде хостингов прямой `file_get_contents` по HTTPS молча
возвращает `false`, тогда как cURL работает. Скрипт-диагностика
**`diag_partsapi.php`** (положить в корень, запустить `php diag_partsapi.php`,
после — удалить) делает один и тот же запрос двумя способами и печатает сырой
ответ/ошибку, сразу указывая причину:

| Что видно в выводе | Причина | Что делать |
|--------------------|---------|-----------|
| cURL ✓, fopen ✗ | транспорт (HTTPS через fopen) | уже исправлено хелпером `httpGet` |
| оба `http=401`, тело `error_code:5000` «Exceeded the number of requests» | исчерпан суточный лимит запросов **с IP** (демо-ключ ≈50/сутки) | платный тариф / ждать сброса |
| оба ✗, не коннектятся | сеть/файрвол хостинга | открыть исходящие на `api.partsapi.ru:443` |

> **Лимит обрабатывается штатно:** при `error_code 5000` `CatalogApi` ставит
> флаг `rate_limited`, прекращает перебор (не жжёт квоту) и не кэширует пустой
> результат; на странице VIN показывается «Каталог временно недоступен: превышен
> суточный лимит запросов», а в админском тесте — явное сообщение про лимит IP.

### Текущий статус интеграции (проверено диагностикой)

Диагностика `diag_partsapi.php` на боевом сервере (`vh464`) подтвердила:

- ✅ **транспорт исправен** — и cURL, и `file_get_contents` доходят до
  `api.partsapi.ru` за ~0.1 с (cURL 7.81, OpenSSL, `allow_url_fopen=On`);
- ✅ **ключи приняты** — оба (`VINdecodeOE`, `getPartsbyVIN`) проходят авторизацию;
- ✅ **код интеграции рабочий** — парсинг, перебор групп, обработка лимита;
- ⛔ **единственный блокер — квота демо-ключа**: API отвечает
  `error_code 5000` («Exceeded the number of requests from the current IP
  address») — суточный лимит запросов с IP сервера (≈50/сутки) исчерпан.

> Декод VIN и каталог по VIN оживут **без изменений в коде**, как только лимит
> снимется: дождаться суточного сброса демо-ключа **или** подключить платный
> тариф PartsAPI (снимает ограничение по IP). Раннее прерывание перебора при
> `error_code 5000` подтверждено в бою (`групп=1, ошибок=1` вместо 25 — квота
> не расходуется впустую).

### Передача клиенту

1. Клиент регистрируется на **PartsAPI.ru**, покупает тариф, берёт **два
   ключа** (`VINdecodeOE` и `getPartsbyVIN`).
2. В **Суперадмин → VIN-поиск → Настройки API** вставляет ключи; в блоке
   каталога выбирает вид запчастей и число групп.
3. Жмёт **«Проверить соединение»** — видит, что запчасти приходят. (На демо-ключе
   при исчерпанной квоте появится сообщение про лимит запросов с IP — это норма.)
4. Включает тумблер **«Включить каталог по API»** и сохраняет.

> На платном тарифе увеличьте `число групп` (в админке) до 100+ или `0` —
> каталог станет полным. На демо-ключе оставьте 15–25 из-за суточного лимита.

---

### Что ещё можно улучшить (следующие шаги)

- [ ] **Боевой SMS-шлюз** — подключить провайдера в `sendSms()` (сейчас тест-режим: код на экране + `storage/sms.log`).
- [ ] **Реальные изображения товаров** — загрузить фото реальных запчастей через панель менеджера, они автоматически заменят заглушки (функция трогает только строки с пустым полем `images`).
- [x] **VIN-поиск + каталог по API** — подключён и работает на боевом сайте (PartsAPI: `VINdecodeOE` + `getPartsbyVIN`). На платном тарифе увеличьте число опрашиваемых групп в **Суперадмин → VIN-поиск** (демо-ключ ограничен ≈50 запросами/сутки).
- [ ] **Реальные цены каталога** — сейчас цены подставляются только для артикулов, совпавших со своим складом; остальное — «под заказ». Для цен на весь каталог можно подключить прайс поставщика и сопоставлять по артикулу.
- [ ] **Доставка в другие страны** — включить нужные страны в настройках телефона; обновить список зон доставки.
- [ ] **Интеграция со складом** — `api/warehouse.php` и `api/autoeuro.php` готовы, нужно прописать API-ключи в настройках.
- [ ] **SEO** — заполнить мета-описания для каждой категории и товара.
- [ ] **Email-уведомления** — настроить SMTP в суперадмин-настройках.
- [ ] **Логотип** — заменить `logo.png` на реальный логотип компании.

---

## Лицензия

Частный проект. Все права защищены.

**Контакты:**
- Issues: https://github.com/nnfirdavs96-cell/avtozapchast/issues
- Email: nnfirdavs96@gmail.com
