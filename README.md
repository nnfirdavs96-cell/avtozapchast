# АвтоЗапчасть — Интернет-магазин автозапчастей

PHP-приложение для интернет-магазина автозапчастей. Поддерживает три языка (RU / TG / EN), несколько валют, роли пользователей и панели управления для разных ролей.

---

## Требования

| Компонент | Версия |
|-----------|--------|
| PHP | 8.0+ |
| MySQL / MariaDB | 8.0+ / 10.4+ |
| Nginx или Apache | любая актуальная |
| PHP-расширения | pdo_mysql, mbstring, fileinfo, zlib |

---

## Установка на сервер

### 1. Клонировать репозиторий

```bash
git clone https://github.com/nnfirdavs96-cell/avtozapchast.git /var/www/html/avtozapchast
cd /var/www/html/avtozapchast
```

### 2. Создать базу данных и пользователя

```sql
CREATE DATABASE avtozapchast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'avtouser'@'localhost' IDENTIFIED BY 'Avto@2024!';
GRANT ALL PRIVILEGES ON avtozapchast.* TO 'avtouser'@'localhost';
FLUSH PRIVILEGES;
```

### 3. Применить схему базы данных

Выполнять **по порядку**:

```bash
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema.sql
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema_v2.sql
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema_v3.sql
mysql -u avtouser -p'Avto@2024!' avtozapchast < sql/schema_v4.sql
```

### 4. Настроить конфиг

Файл `config/database.php` — данные подключения к БД:

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'avtouser');
define('DB_PASS', 'Avto@2024!');
define('DB_NAME', 'avtozapchast');
```

Файл `config/config.php` — URL приложения:

```php
// Для локальной разработки оставить пустым:
define('APP_URL', '');

// Для продакшена указать домен:
define('APP_URL', 'https://yourdomain.com');
```

### 5. Права на папку uploads

```bash
chown -R www-data:www-data /var/www/html/avtozapchast/assets/uploads
chmod -R 755 /var/www/html/avtozapchast/assets/uploads
```

---

## Настройка веб-сервера

### Nginx

Создать файл `/etc/nginx/sites-enabled/avtozapchast.conf`:

```nginx
server {
    listen 80;
    server_name yourdomain.com;   # или _ для любого
    root /var/www/html/avtozapchast;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(git|env) {
        deny all;
    }

    location /assets/uploads {
        expires 30d;
        add_header Cache-Control "public";
    }
}
```

```bash
systemctl reload nginx
```

### Apache

Включить `mod_rewrite` и создать виртуальный хост:

```bash
a2enmod rewrite
```

```apache
<VirtualHost *:80>
    ServerName yourdomain.com
    DocumentRoot /var/www/html/avtozapchast

    <Directory /var/www/html/avtozapchast>
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/avtozapchast_error.log
    CustomLog ${APACHE_LOG_DIR}/avtozapchast_access.log combined
</VirtualHost>
```

```bash
systemctl reload apache2
```

---

## Аккаунты по умолчанию

| Роль | Email | Пароль |
|------|-------|--------|
| Суперадмин | superadmin@avtozapchast.ru | Password123! |
| Администратор | admin@avtozapchast.ru | Password123! |
| Менеджер | manager@avtozapchast.ru | Password123! |
| Покупатель | buyer@avtozapchast.ru | Password123! |

> **Обязательно смените пароли** после первого входа через панель профиля.

---

## Структура проекта

```
avtozapchast/
├── admin/              # Панель администратора
│   ├── index.php       # Дашборд
│   ├── products.php    # Управление товарами + загрузка фото
│   ├── sliders.php     # Слайдер главной страницы
│   ├── orders.php      # Заказы
│   └── users.php       # Пользователи
├── manager/            # Панель менеджера
│   ├── index.php       # Дашборд
│   ├── parts.php       # Запчасти + загрузка фото
│   ├── categories.php  # Категории
│   ├── brands.php      # Бренды
│   └── blog.php        # Блог (RU/TG/EN)
├── superadmin/         # Панель суперадмина
│   ├── index.php       # Дашборд + статистика
│   ├── users.php       # Все пользователи + роли
│   ├── settings.php    # Настройки сайта
│   ├── currencies.php  # Валюты (авто-курс ЦБ РФ)
│   ├── warehouse.php   # API склада
│   ├── blog.php        # Блог
│   └── backup.php      # Резервные копии БД
├── buyer/              # Личный кабинет покупателя
│   ├── index.php
│   ├── orders.php
│   ├── cart.php
│   ├── wishlist.php
│   └── profile.php
├── auth/               # Авторизация
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── api/
│   └── upload.php      # Загрузка изображений
├── assets/
│   ├── css/custom.css  # Кастомные стили
│   ├── uploads/        # Загруженные изображения
│   │   ├── products/
│   │   ├── sliders/
│   │   └── blog/
│   └── img/            # Статичные изображения
├── config/
│   ├── config.php      # Основной конфиг
│   └── database.php    # Подключение к БД
├── includes/
│   ├── functions.php   # Вспомогательные функции
│   ├── header.php      # Шапка сайта
│   ├── footer.php      # Подвал сайта
│   ├── i18n.php        # Интернационализация
│   └── currency.php    # Конвертация валют
├── lang/
│   ├── ru.php          # Русский язык
│   ├── tg.php          # Таджикский язык
│   └── en.php          # Английский язык
├── sql/
│   ├── schema.sql      # Основная схема БД
│   ├── schema_v2.sql   # Wishlist, currencies, blog
│   ├── schema_v3.sql   # Backups, warehouse log
│   └── schema_v4.sql   # Sliders, blog image
└── pages/              # Статические страницы
    ├── about.php
    ├── contact.php
    ├── faq.php
    └── 404.php
```

---

## Роли и права доступа

| Возможность | Суперадмин | Администратор | Менеджер | Покупатель |
|-------------|:----------:|:-------------:|:--------:|:----------:|
| Управление пользователями | ✅ | ❌ | ❌ | ❌ |
| Настройки сайта / API-ключи | ✅ | ❌ | ❌ | ❌ |
| Валюты и языки | ✅ | ❌ | ❌ | ❌ |
| Резервные копии БД | ✅ | ❌ | ❌ | ❌ |
| Склад API | ✅ | ❌ | ❌ | ❌ |
| Товары + изображения | ✅ | ✅ | ✅ | ❌ |
| Слайдер главной страницы | ✅ | ✅ | ❌ | ❌ |
| Заказы | ✅ | ✅ | ❌ | своими |
| Блог | ✅ | ✅ | ✅ | ❌ |
| Категории / Бренды | ✅ | ✅ | ✅ | ❌ |
| Корзина / Избранное | ✅ | ✅ | ✅ | ✅ |

---

## Загрузка изображений

- Endpoint: `POST /api/upload.php?type=products|sliders|blog`
- Доступно для ролей: `manager`, `admin`, `superadmin`
- Допустимые форматы: JPG, PNG, WEBP, GIF
- Максимальный размер: **5 МБ**
- Файлы сохраняются в `assets/uploads/{type}/`

---

## Языки и валюты

**Языки:** русский (по умолчанию), таджикский, английский.  
Переключение через GET-параметр `?lang=ru|tg|en` или из меню сайта.

**Валюты:** RUB (по умолчанию), USD, TJS.  
Автоматическое обновление курсов через API ЦБ РФ: `Суперадмин → Валюты → Получить курсы ЦБ`.

---

## Разработка

```bash
# Клонировать
git clone https://github.com/nnfirdavs96-cell/avtozapchast.git
cd avtozapchast

# Работать на отдельной ветке
git checkout -b feature/my-feature

# После изменений
git add .
git commit -m "Описание изменений"
git push origin feature/my-feature
```

Все основные изменения проходят через Pull Request в ветку `main`.

---

## Лицензия

Частный проект. Все права защищены.
