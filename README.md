# АвтоЗапчасть / AutoDoc

Полнофункциональный интернет-магазин автозапчастей на чистом PHP/MySQL с мультиязычностью, мульти-валютой и админ-панелями для трёх ролей.

## Стек

| Слой        | Технологии                                              |
|-------------|---------------------------------------------------------|
| Backend     | PHP 8.0+, PDO, MySQL 5.7+ / MariaDB 10.3+              |
| Frontend    | Vanilla CSS (site2 inspired) + Vanilla JS              |
| Шрифты      | Rubik (Google Fonts)                                    |
| Без сборки  | Никаких npm/composer — раскинул на хостинг и работает   |

## Структура

```
avtozapchast/
├── admin/             панель администратора (dark)
├── api/               JSON-эндпоинты: cart, wishlist, compare, search, vin, lang, currency, newsletter
├── assets/
│   ├── css/           style.css (storefront), admin.css (dark dashboards)
│   ├── img/site/      placeholder.svg
│   ├── js/main.js
│   └── uploads/parts/ (загруженные изображения товаров)
├── auth/              login, register, logout
├── blog/              индекс + страница статьи
├── buyer/             личный кабинет, корзина, checkout, избранное, сравнение, заказы
├── catalog/           index + part page (с галереей, отзывами, совместимостью, JSON-LD)
├── config/            config.php, database.php
├── includes/          header, footer, functions, i18n, email, product_card, admin_layout, lang/{ru,tg,en}.php
├── manager/           управление товарами (с image upload), категориями, брендами, авто, отзывами
├── pages/             about, contacts, delivery, payment, privacy, terms
├── search/            index + vin
├── sql/               schema.sql, migrations.sql
├── superadmin/        users, settings, delivery, payment, i18n
├── index.php          главная
├── sitemap.php        динамический sitemap
├── robots.txt
└── .htaccess
```

## Установка

1. **Создайте БД и накатите схему:**
   ```bash
   mysql -u root -p < sql/schema.sql
   mysql -u root -p < sql/migrations.sql
   ```
2. **Заполните `config/database.php`** своими данными подключения.
3. **Поставьте `APP_URL`** в `config/config.php` (или env-переменной `APP_URL`).
4. **Дайте права на запись** папке `assets/uploads/`.
5. **Откройте сайт** в браузере. Готово.

## Демо-аккаунты

Все пароли: `Password123!`

| Роль        | E-mail                          |
|-------------|---------------------------------|
| Покупатель  | buyer@avtozapchast.ru           |
| Менеджер    | manager@avtozapchast.ru         |
| Администратор | admin@avtozapchast.ru         |
| Суперадмин  | superadmin@avtozapchast.ru      |

## Что внутри

### Витрина
- 🏠 Главная: hero, быстрый подбор по VIN/авто, категории, новинки, хиты, бренды, фичи, блог
- 📂 Каталог: фильтры (категория, бренд, авто, цена), сортировка, пагинация
- 📦 Карточка товара: галерея, отзывы (звёзды, модерация), совместимость с авто, JSON-LD schema, рекомендации
- 🔍 Поиск: классический + VIN-decoder
- ❤ Избранное (для авторизованных)
- ⚖ Сравнение (до 4 товаров, для гостей сохраняется в session)
- 🛒 Корзина: обновление количества, очистка, удаление позиций
- 💳 Checkout: контакты → адрес → доставка → оплата → подтверждение, e-mail-уведомления покупателю и админу
- 👤 Личный кабинет: статистика, заказы, профиль с обновлением пароля
- 📝 Блог: список + статья + JSON-LD BlogPosting
- 📄 Статические страницы: о компании, доставка, оплата, контакты (с формой), политика, условия

### Мультиязычность и валюты
- 3 языка: **Русский, Тоҷикӣ, English** — переключатель в топбаре, сохраняется в session+cookie
- 3 валюты: **₽ RUB, смн TJS, $ USD** — пересчёт по курсу из БД, форматирование
- Переводы сущностей через таблицу `translations`

### Авто-каталог
- 12 марок × 25 моделей
- Таблица `part_compatibility` связывает запчасти с моделями
- VIN-таблица: 5 примеров записей
- Каскадный select: марка → модель → год

### Менеджер
- Запчасти: создание/редактирование, **загрузка нескольких изображений**, главное фото
- Категории, бренды, авто (марки/модели), модерация отзывов

### Админ
- Заказы: смена статуса, статуса оплаты, трек-номер, e-mail-уведомление покупателю
- Покупатели: просмотр, блокировка
- Сообщения с сайта (контакт-форма)
- Блог: CRUD статей

### Суперадмин
- Все пользователи и роли
- Настройки сайта (название, контакты, SMTP, SEO, соцсети)
- Способы доставки и оплаты (CRUD)
- Языки и валюты (включение/выключение, курсы)

### SEO
- Динамический `sitemap.xml` (через `sitemap.php`)
- `robots.txt`
- Canonical URL, Open Graph, Twitter Card на каждой странице
- JSON-LD: `Product` (с offer + aggregateRating), `BlogPosting`
- Семантический HTML, читаемые URL, breadcrumbs

### Безопасность
- Все формы защищены **CSRF-токеном**
- PDO prepared statements (никакого ручного SQL)
- `password_hash` + `password_verify` (bcrypt)
- HTML-экранирование через `sanitize()` на всех выводах
- `.htaccess` отключает PHP-исполнение в `assets/uploads/`
- Заголовки `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy`

### Email
- PHP `mail()` с UTF-8 и HTML-шаблоном (`emailLayout`)
- Отправка при: регистрации, оформлении заказа, изменении статуса заказа, контактной форме, подписке на рассылку
- В dev-режиме (если `mail()` недоступна) — пишется в `assets/uploads/email.log`

### Дизайн
- **Витрина** — светлая тема в стиле site2, акцент `#C70909`, шрифт Rubik
- **Админка** — тёмная индустриальная (отдельный `admin.css`)
- Адаптивная вёрстка (5 брейкпойнтов)
- Анимации: `fade-up`, hover-эффекты на карточках товаров

## Версии

- v1: базовый сайт (тёмная тема)
- **v2 (текущая)**: полная переработка — site2 design, мультиязычность, мульти-валюта, авто-каталог, VIN, избранное/сравнение/отзывы, checkout, email, image upload, SEO

## Разработка

Запустить локально (PHP встроенный сервер):

```bash
php -S 127.0.0.1:8000
```

Затем `mysql -u root -p < sql/schema.sql` и `mysql -u root -p < sql/migrations.sql`.

Проверить синтаксис:
```bash
find . -name "*.php" -not -path "./template-main/*" -exec php -l {} \;
```
