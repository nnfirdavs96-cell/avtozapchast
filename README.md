# АвтоЗапчасть — Интернет-магазин автозапчастей

Полнофункциональный интернет-магазин автозапчастей на PHP с многоязычной поддержкой (RU / TG / EN), мульти-валютностью, ролевой системой и панелями управления для разных ролей.

**Технологии:** PHP 8.0+, MySQL 8.0+/MariaDB 10.4+, Nginx/Apache, Bootstrap 4, Mazlay HTML-template, jQuery, Owl Carousel.

---

## Содержание

1. [Системные требования](#системные-требования)
2. [Быстрый старт](#быстрый-старт)
3. [Подробная установка](#подробная-установка)
4. [Настройка веб-сервера](#настройка-веб-сервера)
5. [Аккаунты по умолчанию](#аккаунты-по-умолчанию)
6. [Структура проекта](#структура-проекта)
7. [Роли и права доступа](#роли-и-права-доступа)
8. [Функционал по ролям](#функционал-по-ролям)
9. [API загрузки изображений](#api-загрузки-изображений)
10. [Многоязычность](#многоязычность)
11. [Валюты](#валюты)
12. [Резервное копирование](#резервное-копирование)
13. [Решение проблем](#решение-проблем)
14. [Разработка](#разработка)
15. [Архитектура](#архитектура)

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
CREATE USER 'avtouser'@'localhost' IDENTIFIED BY 'Avto@2024!';
GRANT ALL ON avtozapchast.* TO 'avtouser'@'localhost'; FLUSH PRIVILEGES;"

# 3. Применить схемы
for f in schema.sql schema_v2.sql schema_v3.sql schema_v4.sql; do
    mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/$f
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
CREATE USER 'avtouser'@'localhost' IDENTIFIED BY 'Avto@2024!';

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
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema.sql

# Версия 2: wishlist, currencies, languages, blog_posts
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema_v2.sql

# Версия 3: backups, warehouse_api_log
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema_v3.sql

# Версия 4: sliders + image_path в блоге
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema_v4.sql

# CMS: категории блога + разделы страницы «О нас» (site_sections)
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_cms.sql

# Отзывы на товары (product_reviews) — применять ПОСЛЕ migrate_cms
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_reviews.sql

# Отзывы о магазине + флаг витрины (shop_reviews, is_featured)
# ВАЖНО: строго ПОСЛЕ migrate_reviews.sql
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_reviews_v2.sql

# VIN-поиск (декодер + аналоги)
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_vin.sql
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_vin_v2.sql

# Глобальная/категорийная наценка (site_settings global_markup, колонки markup_percent)
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_markup.sql

# Таджикский рынок: язык/валюта/контакты по умолчанию
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_tajik_market.sql

# Только сомони (TJS / СМН), отключить прочие валюты
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/only_tjs_currency.sql

# Прямое ценообразование в СМН: курс TJS = 1.0 (цена в БД = цена на витрине 1:1)
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/tjs_direct_pricing.sql

# Переименование сайта АвтоЗапчасть → AvtoDoc (обновляет site_settings)
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/rename_to_avtodoc.sql

# Гранулярные права: таблица user_permissions (суперадмин раздаёт разделы)
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_permissions.sql

# AutoEuro API (склад, поиск/заказ) — опционально, если используете внешний склад
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema_autoeuro.sql
```

> ⚠️ Если выводятся ошибки `Duplicate entry` — это нормально. Это значит, что данные уже есть в базе.
>
> ⚠️ Порядок миграций отзывов важен: `migrate_reviews.sql` создаёт
> `product_reviews`, а `migrate_reviews_v2.sql` добавляет к ней колонку
> `is_featured` и создаёт `shop_reviews`. Все миграции идемпотентны
> (`IF NOT EXISTS` / `INSERT IGNORE`) — можно запускать повторно.

Проверить, что таблицы созданы:
```bash
mysql -u avtouser -p'Avto@2024!' avtozapchast -e "SHOW TABLES;"
```

Должны появиться: `users`, `categories`, `brands`, `parts`, `orders`, `order_items`, `cart`, `wishlist`, `site_settings`, `currencies`, `languages`, `blog_posts`, `sliders`, `backups`, `warehouse_api_log`, `site_sections`, `product_reviews`, `shop_reviews`.

### Шаг 4: Настройка config.php

Открыть `config/config.php`:
```bash
nano config/config.php
```

Изменить `APP_URL`:
```php
// Для локальной разработки (относительные URL):
define('APP_URL', '');

// Для продакшена:
define('APP_URL', 'https://yourdomain.com');

// Для тестирования по IP:
define('APP_URL', 'http://192.168.88.3');
```

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

### Шаг 5: Настройка database.php

Открыть `config/database.php` и убедиться, что данные подключения совпадают:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'avtouser');
define('DB_PASS', 'Avto@2024!');  // ваш пароль
define('DB_NAME', 'avtozapchast');
```

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
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/rename_to_avtodoc.sql  # один раз
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
│   ├── backup.php          #   - Управление резервными копиями
│   ├── backup_cron.php     #   - CLI-скрипт для cron (авто-бэкап)
│   └── _backup_lib.php     #   - Библиотека бэкапов
│
├── buyer/                  # 👤 Личный кабинет покупателя
│   ├── index.php           #   - Дашборд (последние заказы)
│   ├── orders.php          #   - История заказов
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
gunzip -c storage/backups/backup_2026-05-11.sql.gz | mysql -u avtouser -p'Avto@2024!' avtozapchast
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
- Существует ли пользователь: `mysql -u avtouser -p'Avto@2024!' -e "SELECT 1;"`

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
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_cms.sql
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_reviews.sql
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_reviews_v2.sql

sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx
```

> Код устойчив к отсутствию таблиц отзывов (запросы в `try/catch`), но
> миграции всё равно нужно применить, чтобы функционал заработал.

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
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/migrate_permissions.sql   # права (один раз)
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/tjs_direct_pricing.sql    # цены 1:1 в СМН
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/rename_to_avtodoc.sql     # имя сайта → AvtoDoc
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

## Лицензия

Частный проект. Все права защищены.

**Контакты:**
- Issues: https://github.com/nnfirdavs96-cell/avtozapchast/issues
- Email: nnfirdavs96@gmail.com
