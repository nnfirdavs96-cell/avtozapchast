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
```

> ⚠️ Если выводятся ошибки `Duplicate entry` — это нормально. Это значит, что данные уже есть в базе.

Проверить, что таблицы созданы:
```bash
mysql -u avtouser -p'Avto@2024!' avtozapchast -e "SHOW TABLES;"
```

Должны появиться: `users`, `categories`, `brands`, `parts`, `orders`, `order_items`, `cart`, `wishlist`, `site_settings`, `currencies`, `languages`, `blog_posts`, `sliders`, `backups`, `warehouse_api_log`.

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
│   └── blog.php            #   - CRUD блога (RU/TG/EN + обложка)
│
├── superadmin/             # 🔧 Панель суперадминистратора
│   ├── index.php           #   - Главный дашборд (вся статистика)
│   ├── users.php           #   - Полный CRUD пользователей + роли
│   ├── settings.php        #   - Настройки сайта (название, контакты)
│   ├── currencies.php      #   - Валюты + кнопка "обновить курсы ЦБ РФ"
│   ├── languages.php       #   - Управление языками
│   ├── warehouse.php       #   - Настройки API склада + тест соединения
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
│   └── upload.php          #   - Загрузка изображений (manager/admin/superadmin)
│
├── catalog/                # 🛒 Каталог
│   ├── shop.php            #   - Список товаров с фильтрами
│   └── product.php         #   - Карточка товара
│
├── pages/                  # 📄 Статичные страницы
│   ├── about.php           #   - О компании
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
│   └── schema_v4.sql       #   - v4: sliders, blog image
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
- Черновик / Опубликовано

### 👤 Покупатель

**Витрина** (`/index.php`):
- Просмотр товаров, поиск, фильтры
- Добавление в корзину/избранное

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

---

## Лицензия

Частный проект. Все права защищены.

**Контакты:**
- Issues: https://github.com/nnfirdavs96-cell/avtozapchast/issues
- Email: nnfirdavs96@gmail.com
