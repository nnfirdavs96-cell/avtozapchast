# NAVIGATION — full-stack карта проекта autodoc.tj

Единая точка входа для нового разработчика. Прочитав этот файл, можно понять **весь**
проект (не только VIN): архитектуру, каждый модуль, БД, настройки, соглашения и полную
историю изменений — без пересканирования кода.

**Смежные документы:** `ARCHITECTURE.md` — исчерп. перечень всех функций/контрактов;
`README.md` — установка/деплой/серверные конфиги; `CATALOG_PLAN.md` — этапы слоя каталога;
`CHANGES.md` — ранняя история слайдера; [`docs/AutoDoc-tech-doc.pdf`](docs/AutoDoc-tech-doc.pdf) — техдокументация для клиента (PDF).

> **Что это:** интернет-магазин автозапчастей для рынка Таджикистана (autodoc.tj).
> Витрина + корзина/заказы + подбор запчастей по VIN/параметрам из внешних OEM-каталогов +
> админка на 4 роли + CMS. Валюта — сомони (TJS), языки ru/tg/en.

---

## Оглавление
1. Технический стек и инфраструктура
2. Как запустить (dev / prod)
3. Бутстрап и сквозные соглашения
4. Карта репозитория (папки)
5. Модули full-stack (что где живёт)
6. `includes/functions.php` — ядро (группы функций)
7. AJAX API (`api/`)
8. Слой каталога и VIN (`includes/catalog/`)
9. Библиотека каталога
10. База данных (все таблицы)
11. Настройки `site_settings`
12. Роли и права
13. Внешние сервисы
14. Индекс «изменить X → файл Y»
15. Техдолг / незавершённое
16. Полная история PR #1–253

---

## 1. Технический стек и инфраструктура

| Слой | Технология |
|---|---|
| Язык | PHP (процедурный + классы, **без фреймворка**) |
| БД | MySQL / MariaDB через PDO (prepared statements) |
| Фронтенд | Server-rendered PHP + ванильный JS (`assets/js/app.js`, `main.js`) — **без сборки/npm** |
| Шаблон | Кастомизированный «Mazlay» (`assets/mazlay-*`); наши правки — `assets/css/custom.css` |
| HTTP-клиент | `httpGet()` — cURL с CA-bundle → retry без verify → fopen-фолбэк |
| Хостинг | Shared Timeweb (`deploy/timeweb/`); деплой — `git pull` на сервере |
| CI/CD | **Нет.** Каждое изменение — отдельный PR в `main`, ручной squash-мёрдж |
| Тесты | **Нет.** Валидация: `php -l` (PHP) + `node --check` (JS) вручную |
| Внешние API | Parts-Catalogs (OEM+схемы), PartsAPI/TecDoc, AutoEuro (цены), Laximo (каркас), NHTSA (VIN) |

**Ключевые инфраструктурные факты:**
- **Нет Composer/vendor, нет Docker.** Всё подключается через `require_once`.
- **Миграции самонакатываются в рантайме:** `ensure*Schema()`, `dbAddColumnIfMissing()`,
  `CREATE TABLE IF NOT EXISTS`. Файлы `sql/*.sql` дублируют это для явного деплоя — но обычно
  ничего вручную гонять не нужно, схема достраивается при заходе на нужную страницу.
- `config/db_credentials.php` — **git-ignored**, свой на каждом сервере (prod-БД: `cs360870_auto`).
- Админка исторически защищалась отдельным портом (8888) — сейчас гейт выключен (`ADMIN_PORT=''`).

---

## 2. Как запустить

**Dev (локально):**
```bash
# 1. БД
mysql -u root -p -e "CREATE DATABASE avtozapchast CHARACTER SET utf8mb4;"
# 2. Схемы (порядок важен — миграции ссылаются на предыдущие)
mysql -u USER -p avtozapchast < sql/schema.sql
mysql -u USER -p avtozapchast < sql/schema_v2.sql   # ... v3, v4, migrate_*, add_*
# 3. Креды
cp config/db_credentials.php.example config/db_credentials.php   # если есть; иначе создать
# 4. Права на запись
chmod -R 775 assets/uploads storage
# 5. Запуск
php -S localhost:8000       # либо nginx/apache на корень проекта
```
Полный список миграций и порядок — в `README.md` (раздел «Применение схем БД»).
На проде большинство таблиц/колонок создаётся само (рантайм-миграции), но новые SQL-файлы
(напр. `migrate_catalog_library.sql`) лучше прогнать явно.

**Prod (Timeweb):** `git pull origin main` в `~/public_html`. Креды — в `config/db_credentials.php`
(не в git). Деталь серверной настройки (nginx, порт, ЧПУ) — в `README.md`.

---

## 3. Бутстрап и сквозные соглашения

**Поток запроса:**
```
Запрос → config/config.php   (APP_URL/APP_ROOT, session_start, no-cache заголовки, define ADMIN_PORT)
        → config/database.php (getDB(): синглтон PDO; креды из db_credentials.php)
        → includes/functions.php (+ cart_lib, i18n, currency)   ← ядро, всегда загружено
        → целевая страница
```

**Соглашения, которые действуют везде:**
| Тема | Как принято |
|---|---|
| Доступ | `requireRole(['admin','manager','superadmin'])` + `requirePermission('section')` в начале страницы |
| Формы | CSRF обязателен: `generateCsrfToken()` в форме, `verifyCsrfToken($_POST['csrf_token'])` в обработчике |
| Флеш-сообщения | `flashMessage('success'\|'danger'\|'warning', ...)` → `getFlashMessage()` на след. странице |
| Вывод | `sanitize($x)` (=htmlspecialchars) для любого пользовательского текста в HTML |
| Настройки | `getSetting('key', $default)` / `setSetting('key', $val)` (таблица `site_settings`) |
| Локализация | `t('key')` (файлы `lang/{ru,tg,en}.php`); поля БД — `tField()` |
| Деньги | `formatPrice($n)` → «650.00 смн»; валюта — TJS, курс обычно 1:1 (цена в БД = цена на витрине) |
| Новая колонка/таблица | `dbAddColumnIfMissing($db,$table,$col,$ddl)` или `CREATE TABLE IF NOT EXISTS` (идемпотентно, MySQL 8) |
| Внешний HTTP | только через `httpGet()` (несёт CA-bundle, работает на shared-хостинге) |
| ЧПУ товара | `/product/{id}-{slug}` — `partUrl()`, роутер в корневом `index.php`, `.htaccess`, canonical в `catalog/part.php` |
| Ветка/PR | разработка в фиче-ветке → PR в `main` → squash-merge |

---

## 4. Карта репозитория

| Путь | За что отвечает |
|---|---|
| `config/` | Бутстрап: `config.php` (константы/сессия), `database.php` (PDO) |
| `includes/` | **Ядро.** `functions.php` (всё общее), шапки/подвалы, `cart_lib`, `vin_service`, `autoeuro`, `catalog_api`, `i18n`, `currency`, `manual_pdf`, `partsapi_cats` |
| `includes/catalog/` | **Слой каталога** — 12 файлов: провайдеры OEM + слой цен (§8) |
| `api/` | AJAX-эндпоинты (JSON): VIN-подбор, корзина, избранное, поиск, отзывы, SMS, загрузка, AutoEuro-прокси (§7) |
| `pages/` | Публичные страницы: `vin.php` (★), `vin_kp.php` (КП), `about`/`contact`/`faq` (CMS), `blog`/`blog-detail`, `reviews`, `403`/`404` |
| `catalog/` | Витрина: `index.php` (список+фильтры), `category.php`, `part.php` (карточка товара) |
| `search/` | Страница поиска по товарам |
| `buyer/` | Кабинет покупателя: `cart`, `checkout`, `orders`, `profile`, `wishlist`, `index` |
| `auth/` | `login`, `register`, `logout` (email+пароль / телефон+SMS/PIN) |
| `admin/` | Роль **admin**: `products`, `orders`, `sliders`, `banners`, `users`, `index` |
| `manager/` | Роль **manager**: `parts`, `categories`, `brands`, `blog`, `pages`, `reviews`, `index` |
| `superadmin/` | Роль **superadmin**: `settings`, `vin`, `warehouse`, `delivery`, `currencies`, `languages`, `permissions`, `users`, `backup`(+cron+lib), `manual`, `catalog_library`(+cron), `index` |
| `lang/` | Переводы `ru`/`tg`/`en` |
| `sql/` | Миграции (дублируют рантайм-самонакат) |
| `storage/` | `backups/` (SQL-дампы), `manual/`, `sms.log` (тест-режим SMS) |
| `deploy/timeweb/` | Конфиги/скрипты прод-деплоя |
| `assets/` | `css/custom.css` (наши правки), `js/app.js`+`main.js`, `mazlay-*` (шаблон), `img`, `uploads/` |
| `index.php` (корень) | Главная (слайдер, скидки, категории) + **фолбэк-роутер ЧПУ** `/product/{id}-{slug}` |
| `sitemap.php` / `robots.txt` / `.htaccess` | SEO + правила ЧПУ (Apache) |
| корень `*.md` | Документация (этот файл, ARCHITECTURE, README, CATALOG_PLAN, CHANGES, CLAUDE) |
| корень (мусор) | `diag_partsapi.php`, `fix_vin_catalog.php`, `setup_catalog.php` (одноразовые), `*.zip`, `logo.png` |

---

## 5. Модули full-stack

Каждый модуль описан как вертикальный срез: **UI → обработчик → БД**.

### 5.1 Витрина (storefront)
- **Файлы:** `index.php` (главная), `catalog/index.php` (список+фильтры), `catalog/category.php`,
  `catalog/part.php` (карточка), `search/index.php` + `api/search.php` (живой поиск).
- **Логика:** `getCategories/getCategoryTree/getBrands`, `productBadges`(−XX%/Новый), `priceBox`,
  `getEffectiveMarkup` (наценка товар→категория→`global_markup`), `partUrl` (ЧПУ), `getStockStatus`.
- **БД:** `parts`, `categories`, `brands`. Бейджи/скидки — `parts.old_price`, `parts.stock`.
- **SEO:** per-page meta, Product JSON-LD, canonical на ЧПУ, 301 со старых `?id=`, `sitemap.php`.

### 5.2 Корзина и заказы
- **UI:** `buyer/cart.php`, `buyer/checkout.php`, `buyer/orders.php`; мини-корзина в шапке; `api/cart.php` (live).
- **Логика:** `includes/cart_lib.php` — гость→сессия, юзер→таблица `cart`, `cartMergeGuestIntoUser`
  при входе, `guestOrderUserId` (заказ гостя привязывается по телефону).
- **Чекаут:** зоны доставки по странам/городам, способы оплаты (нал / банк / **онлайн со скидкой**),
  `onlinePaymentDiscount`. Статусы заказа: `getOrderStatusLabel/Class`.
- **БД:** `cart`, `orders` (+`shipping_cost`,`discount_amount`,`payment_method`), `order_items`, `delivery_zones`.

### 5.3 Аутентификация и роли
- **UI:** `auth/login.php`, `auth/register.php`, `auth/logout.php`.
- **Способы входа:** email+пароль; телефон+**SMS-код** (OTP); телефон+**PIN** (персонал).
- **Логика:** `normalizePhone`, `createPhoneOtp`/`verifyPhoneOtp`, `findUserByPhone`,
  троттлинг `registerFailedLogin`/`loginThrottleStatus` (5 неудач → блок 15 мин), `loginUser` (+merge корзины).
- **БД:** `users` (+`phone_e164`,`pin_hash`; email/пароль опциональны), `phone_otp`, `login_attempts`.
- **Чат покупатель ↔ менеджер:** `includes/messaging.php` (таблица `messages`; ветка = пара user+order, `order_id=NULL` — поддержка). UI: `buyer/messages.php`, `admin/messages.php`; значок непрочитанного в обоих меню; автосообщение-чек при оформлении заказа.
- ✅ **SMS — боевой шлюз OsonSMS** подключён (`sendSms()`→`osonSmsSend()`); тест-режим (лог) — фолбэк, если провайдер не выбран (§15).
- Кнопка «Войти» на форме телефона скрыта до получения кода/пароля/PIN (`phone_login_submit`); Enter в поле телефона запускает «Получить код», а не сабмит формы.

### 5.4 Кабинет покупателя
- **Файлы:** `buyer/{index,profile,orders,wishlist,cart}.php`; навигация `renderBuyerAccountNav`.
- **Профиль:** имя/адрес/город/страна/аватар (сохранённый адрес подставляется в чекаут).
- **БД:** `users` (профильные поля), `wishlist`, `orders`.

### 5.5 CMS и контент
- **Блог:** `pages/blog.php`+`blog-detail.php`, редактор `manager/blog.php` → `blog_posts`.
- **Страницы:** `pages/about|contact|faq.php`, контент из `site_sections`, редактор `manager/pages.php`.
- **Отзывы:** `pages/reviews.php`, `api/review_submit.php`+`shop_review_submit.php`,
  модерация `manager/reviews.php` → `product_reviews`, `shop_reviews`. Рейтинги в каталоге (`getProductRatings`).
- **Слайдер:** `admin/sliders.php` (блочный редактор: 9 позиций текста, шрифты, десктоп/мобильный,
  `normalizeSliderBlocks`), рендер в корневом `index.php` → `sliders`.
- **Баннеры:** `admin/banners.php` (placement), вывод в `catalog/index.php` → `banners`.

### 5.6 Админка (3 роли персонала)
- **admin** (`admin/`): товары+фото+наценки, заказы+статусы, слайдер, баннеры, пользователи.
- **manager** (`manager/`): контент — товары, категории, бренды, блог, страницы, отзывы.
- **superadmin** (`superadmin/`): всё + настройки, VIN/каталог, склад, доставка, валюты, языки,
  **права**, бэкапы, руководство, **библиотека каталога**.
- **Единый макет:** `admin-header.php`/`admin-footer.php`, сайдбар `renderRoleSidebar()` (фильтр по `userCan()`).
- **Делегирование прав:** `superadmin/permissions.php` → `user_permissions` (любой раздел любому сотруднику).

### 5.7 Каталог и VIN-подбор — см. §8 (слой каталога) и §9 (библиотека).
- **UI:** `pages/vin.php` (★ карточка авто, дерево узлов, взрыв-схемы, лайтбокс, КП), `pages/vin_kp.php`.
- **Настройки:** `superadmin/vin.php` (провайдер, ключи, язык, схемы, профили, совместимость).

### 5.8 Цены, валюта, наценка
- **Цена детали каталога:** `Catalog::price()` → `PriceAggregator` (свой склад → AutoEuro → сомони).
- **Наценка витрины:** `getEffectiveMarkup` (parts.markup_percent → categories.markup_percent → `global_markup`).
- **Валюта:** `includes/currency.php`, `superadmin/currencies.php`, таблица `currencies` (обычно только TJS 1:1).

### 5.9 Доставка / Локализация / Бэкапы
- **Доставка:** `superadmin/delivery.php` → `delivery_zones` (город+страна+цена+срок); подстановка в чекаут.
- **Локализация:** `includes/i18n.php`, `lang/*.php`, `superadmin/languages.php`, таблица `languages`.
- **Бэкапы:** `superadmin/backup.php` (UI) + `backup_cron.php` (cron) + `_backup_lib.php` → `backups`, ротация.

---

## 6. `includes/functions.php` — ядро (~1850 строк)

Группы функций (полный перечень с сигнатурами — в `ARCHITECTURE.md` §4):

| Группа | Функции |
|---|---|
| Auth/роли | `isLoggedIn`, `getCurrentUser`, `hasRole`, `requireRole`, `denyAccess`, `loginUser`, `userCan`, `requirePermission`, `permissionSections`, `effectiveAllowedSections`, `roleDefaultSections` |
| CSRF/флеш/вывод | `generateCsrfToken`, `verifyCsrfToken`, `flashMessage`, `getFlashMessage`, `redirect`, `sanitize` |
| Настройки/HTTP | `getSetting`, `setSetting`, **`httpGet`** |
| Товары витрины | `getCategories`, `getCategoryTree`, `getBrands`, `productBadges`, `priceBox`, `getEffectiveMarkup`, `partUrl`, `getStockStatus`, `discountPercent`, `isNewProduct`, `productImageUrl` |
| Отзывы | `getProductRatings`, `starsHtml`, `userPurchasedPart`, `getShopRatingSummary` |
| Корзина/избранное | `getCartCount`, `getMiniCart`, `getMiniCartTotal`, `getWishlistCount` |
| Заказы | `getOrderStatusLabel`, `getOrderStatusClass`, `formatShippingAddress` |
| Телефон/SMS | `normalizePhone`, `createPhoneOtp`, `verifyPhoneOtp`, `findUserByPhone`, `sendSms`, `smsConfigured`, `phoneCountriesCatalog`, `ensurePhoneAuthSchema`, `ensureStaffPinSchema` |
| Троттлинг входа | `loginThrottleStatus`, `registerFailedLogin`, `clearLoginAttempts`, `loginLockMessage`, `ensureLoginThrottleSchema` |
| Онлайн-оплата | `onlinePaymentSettings`, `onlinePaymentEnabled`, `onlinePaymentDiscount`, `onlinePaymentIncentiveLabel` |
| Слайдер | `sliderFonts`, `normalizeSliderBlocks`, `sliderFontsGoogleUrl` |
| Сидеры (одноразовые) | `seedBrands`, `seedBanners`, `seedDemoProducts`, `seedSliderTemplate`, `fillMissingProductImages`, `seedCategorySubcategories` |
| Утилиты | `truncate`, `breadcrumb`, `paginate`+`paginationHtml`, `renderRoleSidebar`, `renderBuyerAccountNav`, **`dbAddColumnIfMissing`** |

Прочие классы ядра: `cart_lib.php` (`cartAdd/cartSetQty/cartDetailedItems/cartMergeGuestIntoUser/guestOrderUserId`),
`vin_service.php` (`VinService`), `autoeuro.php` (`AutoEuro`).

---

## 7. AJAX API (`api/`, JSON)

| Файл | Что делает |
|---|---|
| `vin_nodes.php` | дерево узлов авто (по VIN или carId+catalogId) |
| `vin_scheme.php` | взрыв-схема узла: `img` + `hotspots[]` + `parts[]` |
| `vin_catalog.php` | детали узла / полный каталог по VIN |
| `vin_params.php` | каскад «по параметрам» (brands/models/carparams/cars) |
| `vin_price.php` | цена по OEM (склад → AutoEuro) |
| `vin_crosses.php` / `vin_analogs.php` | аналоги-кроссы по артикулу / аналоги из своего каталога |
| `cart.php` / `wishlist.php` | корзина (add/remove/update/count/mini) / избранное (toggle/count) |
| `search.php` | живой поиск-подсказки по товарам |
| `sms_auth.php` | отправка одноразового SMS-кода |
| `review_submit.php` / `shop_review_submit.php` | отзыв на товар / на магазин |
| `upload.php` | загрузка изображений (только сотрудникам) |
| `autoeuro_search.php` / `autoeuro_order.php` | прокси к AutoEuro |

Эндпоинты **тонкие** — вся логика в адаптерах (`includes/catalog/`) и `functions.php`.

---

## 8. Слой каталога и VIN (`includes/catalog/`)

**Идея:** провайдер каталога сменный (`catalog_provider`), фронт зовёт только `Catalog::provider()`.
Цены — независимый слой (`Catalog::price()`).

| Файл | Роль |
|---|---|
| `Provider.php` | интерфейс `CatalogProvider` (контракт) |
| `Manager.php` | фасад `Catalog` (`provider/available/reset/price`) |
| `PartsCatalogsAdapter.php` | ★ `partspc` — OEM Parts-Catalogs + визуальные взрыв-схемы; кэш; **библиотека**; сбор схем; спрос |
| `PartsApiAdapter.php` (+ `includes/catalog_api.php`) | `partsapi` — TecDoc/PartsAPI; `catalog_api.php` = `enrichItemsFromWarehouse()` (цены/сток) |
| `GenericRestAdapter.php` + `CatalogProfiles.php` | подключение любого REST-каталога **без кода** (JSON-профиль `catalog_profiles`) |
| `LaximoAdapter.php` | `laximo` — каркас |
| `MockAdapter.php` | `mock` — демо без ключа |
| `PriceProvider.php` + `PriceAggregator.php` + `WarehousePriceProvider.php` + `AutoEuroPriceProvider.php` | слой цен: свой склад → AutoEuro → сомони |

**Поток VIN-подбора (Parts-Catalogs):**
```
VIN → car/info → {carId,catalogId,criteria} → groups2 (дерево узлов)
    → parts2 (взрыв-схема + детали) → enrichItemsFromWarehouse (цена/сток со склада)
```
**Каскад «по параметрам»:** brands → models → cars-parameters → cars2 → (те же groups2/parts2).

**VIN-декодер** (`includes/vin_service.php`, отдельно от каталога): валидация (17 симв., без
check-digit), локальный WMI-разбор + провайдер (`vin_api_provider`: nhtsa/partsapi/custom),
кэш `vin_cache`.

---

## 9. Библиотека каталога (постоянный архив OEM)

Копит ответы Parts-Catalogs **без TTL** → экономия лимита API, резерв на сбой, база для
совместимости и аналитики. Наполняется автоматически при каждом реальном поиске (VIN и «по параметрам»).

**Порядок чтения (экономия лимита):** `[1] Библиотека (без TTL) → [2] TTL-кэш (30д) → [3] живой API`.
Живой ответ пишется одновременно в кэш и в библиотеку.

**Таблицы:** `catalog_library_cars` (карточка авто; `vin` пуст для «по параметрам»),
`catalog_library_nodes` (дерево узлов), `catalog_library_schemes` (схема+детали), `catalog_demand` (спрос).

**Экраны:** `superadmin/catalog_library.php` (список, экспорт JSON/CSV, сбор схем, тумблеры, аналитика),
`superadmin/catalog_library_cron.php` (CLI-дособиратель схем УЖЕ известных авто),
`superadmin/catalog_library_seed.php` (CLI-скрипт, ОТКРЫВАЕТ новые марки/модели/авто через
`pcBrands/pcModels/pcCars`, запускать вручную — платно, тариф за авто/сутки), кнопка КП →
`pages/vin_kp.php`, предложения совместимости → вкладка `superadmin/vin.php`.

**Тумблеры (`site_settings`):**
| Ключ | Что | Дефолт |
|---|---|---|
| `catalog_library_read_first` | читать библиотеку до API (вся экономия лимита) | `1` |
| `catalog_kp_enabled` | кнопка «Скачать КП» + доступ к `vin_kp.php` | `1` |
| `catalog_compat_suggestions_enabled` | панель предложений совместимости | `1` |
| `catalog_demand_enabled` | аналитика спроса (счётчик обращений) | `0` |
| `catalog_library_autocollect` | фоновый cron-дособиратель схем | `0` |

Cron: `*/5 * * * * php <APP_ROOT>/superadmin/catalog_library_cron.php`

---

## 10. База данных (все таблицы)

### Основные (`sql/schema*.sql`)
| Таблица | Назначение |
|---|---|
| `users` | пользователи; роль buyer/manager/admin/superadmin; +`phone_e164`,`pin_hash`,профильные поля |
| `parts` | товары (part_number, name, brand_id, category_id, price, old_price, stock, images JSON, cost_price, markup_percent) |
| `categories` / `brands` | категории (parent_id, slug, image_path, markup_percent) / бренды (logo_path, sort_order) |
| `cart` / `orders` / `order_items` | корзина юзера / заказы (+shipping_cost, discount_amount, payment_method) / позиции |
| `delivery_zones` | зоны доставки (city, country, cost, delivery_days) |
| `site_settings` | все настройки (key UNIQUE → value) |

### Из миграций (`sql/*.sql`)
| Таблица | Миграция / зачем |
|---|---|
| `wishlist`, `currencies`, `languages`, `blog_posts`, `warehouse_api_log` | schema_v2 |
| `backups` | schema_v3 (реестр SQL-бэкапов) |
| `sliders` | schema_v4 (+ блоки текста JSON, десктоп/мобильный) |
| `car_models`, `parts_compatibility`, `vin_cache` | migrate_vin |
| `vin_search_history`, `part_analogs` | migrate_vin_v2 |
| `site_sections` | migrate_cms (about/contact/faq) |
| `product_reviews`, `shop_reviews` | migrate_reviews / v2 |
| `phone_otp` | add_phone_auth (SMS-коды) |
| `banners` | сидер (placement) |
| `user_permissions` | migrate_permissions |
| `login_attempts` | рантайм (троттлинг) |
| `catalog_library_cars/nodes/schemes`, `catalog_demand` | migrate_catalog_library (+ рантайм) |

### Рантайм-кэши (`CREATE TABLE IF NOT EXISTS`)
| Таблица | Кто пишет |
|---|---|
| `partsapi_catalog_cache` | CatalogApi (PartsAPI по VIN) |
| `partsapi_kv_cache` | PC-адаптер (`pc:car/nodes/parts/scheme/brands/models/...`) |
| `catalog_price_cache` | PriceAggregator (AutoEuro) |

---

## 11. Настройки `site_settings` (по группам)

- **Каталог:** `catalog_provider`, `catalog_api_enabled`, `catalog_api_type`, `catalog_api_oem_nodes`, `catalog_profiles`.
- **PartsAPI:** `catalog_api_key`, `catalog_api_base`, `catalog_api_max_groups`, `catalog_api_timeout`.
- **Parts-Catalogs:** `catalog_pc_key`, `catalog_pc_base`, `catalog_pc_timeout`, `catalog_pc_auth`, `catalog_pc_key_param`, `catalog_pc_schema`, `catalog_pc_lang`.
- **Библиотека каталога:** `catalog_library_read_first`, `catalog_kp_enabled`, `catalog_compat_suggestions_enabled`, `catalog_demand_enabled`, `catalog_library_autocollect` (§9).
- **Laximo:** `catalog_laximo_login`, `catalog_laximo_secret`.
- **Цены:** `catalog_price_autoeuro` (вкл. цены AutoEuro на витрине), `global_markup`.
- **AutoEuro:** `autoeuro_enabled`, `autoeuro_api_key`, `autoeuro_delivery_key` (московский пункт — агрегирует склады), `autoeuro_payer_key` (для заказа), `autoeuro_rub_rate` (сомони/рубль, ~0.11), `autoeuro_markup` (наценка % на AutoEuro; пусто → `global_markup`), `autoeuro_brand_map` (маппинг брендов).
- **AutoEuro — доставка Москва→Худжанд** (в API AutoEuro её нет — цена покрывает только склад→Москва): `autoeuro_khj_enabled` (тумблер надбавки), `autoeuro_khj_mode` (`sum`/`percent`), `autoeuro_khj_value` (сумма в сомони или %), `autoeuro_khj_days` (дни к сроку). Показ покупателю: `autoeuro_offer_mode` (`A` — только итог срока / `B` — с разбивкой), `autoeuro_offers_limit` (сколько вариантов показывать, деф. 3).
- **VIN-декодер:** `vin_search_enabled`, `vin_api_provider`, `vin_api_url`, `vin_api_key`, `vin_api_timeout`.
- **Auth/SMS:** `auth_email_enabled`, `sms_provider` (пусто = тест-режим, `osonsms` = боевая отправка), `sms_osonsms_login/hash/sender/server`, `phone_countries`.
- **Онлайн-оплата:** `online_payment_enabled`, `online_discount_type`, `online_discount_value`, `online_free_shipping`.
- **Сайт/SEO/карта:** `site_name`, `site_phone`, `site_email`, `site_address`, соцсети (`site_whatsapp/telegram/instagram/...`), `meta_description`, `map_lat/lng/zoom`, `slider_interval_sec`.
- **Флаги сидеров/схем (не трогать):** `*_seed_done`, `*_schema_v1`, `demo_products_v1`, и т.п.

---

## 12. Роли и права

| Роль | Доступ |
|---|---|
| `buyer` | кабинет `buyer/` (заказы, профиль, избранное, корзина) |
| `manager` | контент: товары/категории/бренды/блог/страницы/отзывы (`manager/`) |
| `admin` | + товары/наценки/слайдеры/баннеры/заказы/пользователи (`admin/`) |
| `superadmin` | всё + настройки/VIN/каталог/склад/доставка/валюты/языки/права/бэкапы/библиотека (`superadmin/`) |

Точечные права: `permissionSections()` (ключи: products, orders, sliders, banners, categories,
brands, blog, pages, reviews, vin, settings, users, permissions, …) → `userCan('x')` /
`requirePermission('x')`. Персональные наборы — `user_permissions` (редактор `superadmin/permissions.php`).
`permissions`/`backup`/`manual`/`catalog_library` — только superadmin.

---

## 13. Внешние сервисы

| Сервис | Роль | Файл | Доступ |
|---|---|---|---|
| **Parts-Catalogs / Tradesoft** | OEM-каталог + визуальные схемы (боевой) | `PartsCatalogsAdapter.php` | `catalog_pc_key`; тариф — за VIN/сутки |
| PartsAPI.ru | детали по VIN, кроссы | `catalog_api.php` | `catalog_api_key`; демо 50 req/сутки/IP |
| AutoEuro | цены/наличие поставщика (боевой, на VIN) | `autoeuro.php`, `AutoEuroPriceProvider.php` | ключ+delivery_key+`catalog_price_autoeuro`; RUB→сомони по `autoeuro_rub_rate`; `offersByOem()` — список вариантов с выбором срока + надбавка/дни Москва→Худжанд (`autoeuro_khj_*`); заказ (`create_order`) не подключён |
| Laximo | оригинал (каркас) | `LaximoAdapter.php` | логин+секрет |
| NHTSA | бесплатный VIN-декод | `vin_service.php` | без ключа |
| SMS-шлюз | коды входа | `sendSms()` | ✅ OsonSMS (боевой); тест-режим — фолбэк без провайдера |

---

## 14. Индекс «изменить X → файл Y»

| Задача | Где |
|---|---|
| Вид страницы VIN (дерево/схема/карточки) | `pages/vin.php` (CSS в `<style>`, JS внизу) |
| REST-провайдер каталога без кода | админка → `catalog_profiles` JSON (`GenericRestAdapter.php`) |
| Parts-Catalogs: язык/авторизация/парсинг/кэш/библиотека | `includes/catalog/PartsCatalogsAdapter.php` |
| Логика цен (склад/AutoEuro/наценка) | `includes/catalog/PriceAggregator.php`, `*PriceProvider.php` |
| Декод VIN | `includes/vin_service.php` |
| Корзина/гость/слияние | `includes/cart_lib.php`; UI — `buyer/cart.php`, `api/cart.php` |
| Оформление/доставка/оплата | `buyer/checkout.php`; зоны — `superadmin/delivery.php` |
| Вход/регистрация/SMS/PIN/троттлинг | `auth/*.php`, `functions.php` (SMS/OTP/throttle) |
| Настройки каталога (форма) | `superadmin/vin.php` |
| Общие настройки/соцсети/оплата/SEO | `superadmin/settings.php` |
| Наценка | `getEffectiveMarkup()` + `global_markup`/`markup_percent` |
| Переводы | `lang/*.php` + `t()` |
| Слайдер / баннеры | `admin/sliders.php` / `admin/banners.php` |
| Роли/права | `functions.php` (permission*), `superadmin/permissions.php` |
| Бэкапы | `superadmin/backup.php`, `_backup_lib.php`, `backup_cron.php` |
| Библиотека каталога / КП / совместимость / спрос | `superadmin/catalog_library.php`, `pages/vin_kp.php`, `superadmin/vin.php` (compat), `PartsCatalogsAdapter.php` (demand) |
| Новая колонка/таблица | `sql/` + `dbAddColumnIfMissing()` |

---

## 15. Техдолг / незавершённое

- ~~**SMS-отправка** — тест-режим~~ ✅ сделано: боевой шлюз **OsonSMS** подключён (`sendSms()`→`osonSmsSend()`,
  настройки в `superadmin/settings.php`). Тест-режим (`storage/sms.log`) остаётся фолбэком, если `sms_provider` пуст.
- ~~**Цены AutoEuro**~~ ✅ сделано: цены/наличие поставщика на витрине VIN (`AutoEuroPriceProvider`
  → `searchItemsSmart` → RUB×`autoeuro_rub_rate`×наценка). **Не подключён автозаказ** (`create_order`,
  денежная ветка) — закупка вручную через «Заявку поставщику» на карточке заказа. Курс и наценку
  (`autoeuro_rub_rate`/`autoeuro_markup`) владелец держит актуальными.
- **Онлайн-оплата** — только маркетинговый чекбокс со скидкой; реального эквайринга/карты/QR нет.
  Нужен провайдер ТДж (Alif/Corti/Eskhata) + редирект + webhook.
- **SEO-страницы по авто из библиотеки** — не сделано намеренно (риск лицензии Parts-Catalogs).
- **Laximo** — каркас (ssd-выдача достраивается на боевом аккаунте).
- **OEM-каталоги без фото деталей** — только взрыв-схема (свойство данных, не баг).
- **Локализация VIN-результатов** — каркас через `t()`, часть текстов блока результатов на русском.
- **Мусор в корне** — `diag_partsapi.php`, `fix_vin_catalog.php`, `setup_catalog.php`, `*.zip` (кандидаты на удаление).
- **Нет тестов/CI** — валидация только `php -l` / `node --check` вручную.

---

## 16. Полная история PR #1–253

Все PR влиты в `main` (squash). Хронологически, сгруппировано по эпохам развития.
Ранние PR (#1–172) — базовая витрина/CMS/адаптив; #173–218 — каталог по VIN и универсальная
архитектура провайдеров; #219–253 — интерактивные схемы и библиотека каталога.

### #1–#24 · Фундамент: мультиязычный магазин, админка, мобильная адаптация (7–11 мая)

- **#1** (2026-05-07) — Claude/initial setup oi el h
- **#2** (2026-05-09) — Revert "Claude/initial setup oi el h"
- **#3** (2026-05-10) — Claude/build multilingual currency site a12ui
- **#4** (2026-05-10) — Fix DB credentials and replace hardcoded Russian strings with i18n keys
- **#5** (2026-05-10) — Claude/build multilingual currency site a12ui
- **#6** (2026-05-10) — Fix admin panels layout, checkout 500, product image 404s
- **#7** (2026-05-10) — Add backup system, CBR currency auto-fetch, fix all superadmin sidebars
- **#8** (2026-05-10) — Backups, CBR currency auto-fetch, Moscow warehouse API, Mazlay design polish
- **#9** (2026-05-10) — Fix header/nav/carousel layout conflicts with Mazlay CSS
- **#10** (2026-05-10) — Fix login: code used `password` column but DB has `password_hash`
- **#11** (2026-05-10) — Rebuild admin/manager panels with image upload and proper role features
- **#12** (2026-05-11) — Add README.md — инструкция по установке и работе с проектом
- **#13** (2026-05-11) — Fix sidebar nav consistency, mobile CSS, social links, and UX
- **#14** (2026-05-11) — Fix sidebar nav, slider, catalog crash, mobile CSS
- **#15** (2026-05-11) — Homepage slider from DB + full mobile rewrite (430px / iPhone 15 Pro Max)
- **#16** (2026-05-11) — Mobile P0: видимые кнопки Войти/Регистрация, гамбургер в шапке, без горизонтального скролла
- **#17** (2026-05-11) — README: CHANGELOG и руководство для разработчиков
- **#18** (2026-05-11) — Fix mobile horizontal scroll and burger menu overlay
- **#19** (2026-05-11) — Fix header icons overflow and login/register mobile layout
- **#20** (2026-05-11) — Fix second hamburger icon and oversized product cards on mobile
- **#21** (2026-05-11) — Mobile fix: newsletter, КОНТАКТЫ btn, categories dropdown, 2-col products
- **#22** (2026-05-11) — Mobile fix: categories toggle, product/category grid, Owl destroy on mobile
- **#23** (2026-05-11) — Mobile fix: категории 2 колонки, выпадающее меню поверх контента
- **#24** (2026-05-11) — docs(README): раздел "Мобильная адаптация" для разработчиков

### #25–#71 · Таджикский рынок, VIN-декодер, наценка, CMS, отзывы, права доступа, TJS (12–17 мая)

- **#25** (2026-05-12) — Claude/check euroauto api 9 o je y
- **#26** (—) — Claude/review content a ri3l
- **#27** (2026-05-13) — feat: таджикский рынок — Сомони по умолчанию, язык tg, адрес Худжанд
- **#28** (2026-05-13) — fix: карта Худжанд на странице контактов, телефон +992 92 646-46-46
- **#29** (2026-05-13) — feat: координаты карты из БД + редактирование в суперадмин-настройках
- **#30** (2026-05-13) — feat: система наценки товаров (cost_price, markup_percent)
- **#31** (2026-05-13) — feat: VIN-поиск запчастей — полный декодер + панель управления
- **#32** (2026-05-13) — feat: автодетект VIN в главной строке поиска
- **#33** (2026-05-13) — Добавить фото автомобиля в результаты VIN-поиска
- **#34** (2026-05-13) — VIN-поиск: фильтры категорий, аналоги, история, кнопка «В корзину»
- **#35** (2026-05-13) — Исправить миграцию vin_v2: INT → INT UNSIGNED для FK
- **#36** (2026-05-13) — Слайдер: контент в верхний-левый угол на мобильных
- **#37** (2026-05-13) — Мобильная: горизонтальный скролл для секций категорий, товаров и баннеров
- **#38** (2026-05-13) — Fix: горизонтальный скролл перекрывался поздними правилами в конце CSS
- **#39** (2026-05-13) — Мобильная: одна карточка = одна единица скролла + красивое оформление
- **#40** (2026-05-13) — Слайдер главной: автопрокрутка каждые 15 секунд
- **#41** (2026-05-15) — Слайдер: прогресс-таймер на активной точке
- **#42** (2026-05-15) — Fix: прогресс-таймер слайдера через DOM span (гарантированный рестарт)
- **#43** (2026-05-15) — Прогресс-таймер слайдера: белый цвет
- **#44** (2026-05-15) — Fix: прогресс-таймер слайдера срабатывает при первой загрузке
- **#45** (2026-05-15) — Fix: прогресс-таймер при первой загрузке (биндинг до owlCarousel)
- **#46** (2026-05-15) — Мега-меню dropdown навигация для десктопа
- **#47** (2026-05-15) — Исправление позиции выпадающих меню
- **#48** (2026-05-15) — Только TJS валюта, символ СМН
- **#49** (2026-05-15) — Исправление кнопок newsletter и scroll-to-top
- **#50** (2026-05-15) — Исправление кнопки scroll-to-top
- **#51** (2026-05-15) — Выравнивание высоты кнопки ТАМОС
- **#52** (2026-05-15) — Flex-layout для subscribe формы
- **#53** (2026-05-15) — Cache-busting для CSS/JS — фикс «всё также»
- **#54** (2026-05-15) — Фикс: фото товаров не отображались на витрине
- **#55** (2026-05-15) — CMS: управление страницей «О нас» и категориями блога
- **#56** (2026-05-15) — Расширение CMS страницы «О нас»: преимущества, FAQ, отзывы, подпись
- **#57** (2026-05-15) — Система отзывов: товары + магазин, рейтинги в каталоге, витрина «О нас»
- **#58** (2026-05-15) — UX отзывов: чистое подтверждение + редактирование текстов из админки
- **#59** (2026-05-15) — Админка: редактирование текстов сообщений отзывов
- **#60** (2026-05-15) — Фикс: 500-ошибка при установке языка по умолчанию
- **#61** (2026-05-16) — Фикс: пустое меню категорий на мобильной главной
- **#62** (2026-05-16) — Фикс: меню категорий уходит за баннер на мобиле (z-index/overflow)
- **#63** (2026-05-16) — Иконка ☰ рядом с «ВСЕ КАТЕГОРИИ» на мобильной (как на десктопе)
- **#64** (2026-05-16) — Фикс: баннер-слайдер зависает (стопор автоплея Owl после свайпа/ховера)
- **#65** (2026-05-16) — Прямое ценообразование в СМН + контроль курса суперадмином
- **#66** (2026-05-16) — Гранулярные права: суперадмин раздаёт разделы admin/manager по пользователям
- **#67** (2026-05-16) — «Права доступа» в меню всех страниц суперадмина + кнопка на форме пользователя
- **#68** (2026-05-16) — Права: любой раздел любому сотруднику + безопасные умолчания по роли
- **#69** (2026-05-16) — docs: README синхронизирован — права, цены СМН, мобильные фиксы
- **#70** (2026-05-16) — mobile: единый отступ 16px (выравнивание шапки и контента)
- **#71** (2026-05-16) — UI: формат цены «650.00 смн», фикс баннера и цвета категорий

### #72–#105 · Ребрендинг AutoDoc, логотип/favicon, порт админки, соцсети, профиль, бренды (17–20 мая)

- **#72** (2026-05-17) — Rename site АвтоЗапчасть → AvtoDoc
- **#73** (2026-05-17) — Logo: larger size + hover animation
- **#74** (2026-05-17) — Favicon AvtoDoc: тёмный фон, белая «A» + красная «D»
- **#75** (2026-05-17) — Защита админ-панели: доступ только по выделенному порту 8888
- **#76** (2026-05-17) — docs: README — реальная конфигурация прод-сервера (nginx + порт 8888)
- **#77** (2026-05-17) — fix: ADMIN_PORT gate disabled + nginx-докум. прод-сервера
- **#78** (2026-05-17) — deploy: nginx config with Basic Auth on admin paths
- **#79** (2026-05-17) — deploy: убрать Basic Auth (вернуть к работе на порту 80)
- **#80** (2026-05-19) — feat: векторный логотип AvtoDoc + тёмная шапка
- **#81** (2026-05-19) — feat: оригинальный логотип AvtoDoc (прозрачный) + favicon
- **#82** (2026-05-19) — feat: управление картинками категорий в панели менеджера
- **#83** (2026-05-19) — feat: управление логотипами партнёров и соцсетями из админки
- **#84** (2026-05-19) — feat: YouTube/TikTok в соцсетях + ссылка Партнёры в суперадмине
- **#85** (2026-05-19) — feat: подсказки с рекомендуемыми размерами для всех загрузок изображений
- **#86** (2026-05-19) — feat: аватар + адрес доставки в профиле и редизайн админ-панелей под стиль магазина
- **#87** (2026-05-19) — redesign: админ и профиль панели в фирменном стиле магазина
- **#88** (2026-05-19) — refactor: страницы покупателя в едином магазинном макете (как Корзина/Избранное)
- **#89** (2026-05-19) — fix: навигация аккаунта на всех 5 страницах покупателя
- **#90** (2026-05-20) — docs: README — актуальные миграции и CHANGELOG последних изменений
- **#91** (2026-05-20) — feat: новый логотип AutoDoc
- **#92** (2026-05-20) — fix: обновлённый логотип AutoDoc с прозрачным фоном
- **#93** (2026-05-20) — fix: убрана тёмная рамка вокруг логотипа
- **#94** (2026-05-20) — fix: полностью убран фон логотипа (flood fill)
- **#95** (2026-05-20) — fix: AvtoDoc → AutoDoc везде в коде
- **#96** (2026-05-20) — refactor: единый сайдбар для всех админ-панелей
- **#97** (2026-05-20) — feat: бренды — нумерация, сортировка, нет дубликатов в карусели
- **#98** (2026-05-20) — feat: стрелки и автопрокрутка карусели брендов
- **#99** (2026-05-20) — fix: стрелки карусели — крупнее, заметнее, без клика в каталог
- **#100** (2026-05-20) — fix: стрелки карусели — позиция по бокам, увеличен размер
- **#101** (2026-05-20) — fix: стрелки карусели — максимальная специфичность CSS
- **#102** (2026-05-20) — docs: README + мобильная карусель брендов
- **#103** (2026-05-20) — fix: размер лого брендов + полупрозрачные стрелки
- **#104** (2026-05-20) — fix: карточки брендов на полную ширину
- **#105** (2026-05-20) — fix: логотипы брендов масштабируются к карточке

### #106–#134 · Раздельный десктоп/мобильный контент, редактор слайдера, per-server БД, деплой-доки (23–25 мая)

- **#106** (2026-05-23) — Исправление багов: адрес доставки, баннеры, логотипы, Instagram, PDF-руководство
- **#107** (2026-05-23) — Увеличение логотипов брендов на мобильном (2 в ряд вместо 3)
- **#108** (2026-05-23) — Раздельные баннеры для десктопа и мобильного
- **#109** (2026-05-23) — Раздельные десктоп/мобильный изображения для слайдера и категорий
- **#110** (2026-05-23) — Исправление логотипов брендов: height:auto вместо object-fit:contain
- **#111** (2026-05-23) — Логотипы брендов заполняют бокс по ширине (a → block)
- **#112** (2026-05-23) — HTML-страницы не кешируются (Cache-Control: no-store)
- **#113** (2026-05-23) — Кнопка «В корзину» всегда видна на мобильном
- **#114** (2026-05-23) — Удалён конфликтующий блок «бренды 3 в ряд» (причина мелких логотипов)
- **#115** (2026-05-23) — БД-доступы per-server (подготовка к Timeweb)
- **#116** (2026-05-24) — feat(slider): two-line bold title controlled from admin
- **#117** (2026-05-24) — fix(admin): MySQL-compatible column migrations (fixes 500 on save)
- **#118** (2026-05-24) — feat(slider): full text-block editor with live preview
- **#119** (2026-05-24) — fix(slider-preview): pixel-accurate preview via transform:scale
- **#120** (2026-05-24) — fix(slider-preview): remove max-width cap that caused text to wrap
- **#121** (2026-05-24) — feat(slider): 9-position text alignment picker with live preview
- **#122** (2026-05-24) — fix(slider): mobile font scaling bypassed by inline style specificity
- **#123** (2026-05-24) — feat(slider): mobile font size control per block + Десктоп/Мобильный preview toggle
- **#124** (2026-05-24) — fix: stop redirecting to internal proxy IP (10.230.13.107) instead of autodoc.tj
- **#125** (2026-05-24) — docs: full setup/deploy guide + scrub DB passwords from repo
- **#126** (2026-05-24) — docs: document GitHub→Debian(test)→Timeweb(prod) workflow
- **#127** (2026-05-24) — fix(slider): дубликат блока позиции текста ломал сохранение
- **#128** (2026-05-24) — feat(slider): независимые настройки текста для десктопа и мобильного
- **#129** (2026-05-24) — fix(sliders): исправление внутреннего IP в кнопке + управление текстом кнопки
- **#130** (2026-05-24) — fix(slider): мобильное изображение подстраивается под пропорции (не обрезается)
- **#131** (2026-05-24) — fix(slider): полностью показывать мобильное изображение (убрать обрезку)
- **#132** (2026-05-24) — fix(slider): фиксированная высота 380px на мобиле, превью в админке совпадает с сайтом
- **#133** (2026-05-25) — docs/fix: рекомендация 1080×1080, добавлен CHANGES.md
- **#134** (2026-05-25) — docs: внести историю изменений слайдера в README

### #135–#172 · Телефон/страны/маска, SEO/JSON-LD, доставка, скидки, вход по SMS, живая корзина (26–31 мая)

- **#135** (2026-05-26) — feat(auth): кнопка показа пароля на входе и регистрации
- **#136** (2026-05-26) — fix(auth): сделать кнопку показа пароля ненавязчивой
- **#137** (2026-05-26) — fix(profile): разрешить покупателям загрузку аватара
- **#138** (2026-05-26) — feat(phone): маска телефона +992 (Таджикистан)
- **#139** (2026-05-26) — fix(phone): маску +992 теперь легко стереть (без отскока)
- **#140** (2026-05-26) — feat(phone): выбор страны для телефона + управление из настроек
- **#141** (2026-05-26) — feat(phone): больше стран + выпадающий список с поиском в админке
- **#142** (2026-05-26) — feat(phone): настоящие флаги вместо эмодзи
- **#143** (2026-05-26) — feat(catalog): автозаполнение картинок для товаров без фото
- **#144** (2026-05-26) — feat(catalog): подкатегории для меню ВСЕ КАТЕГОРИИ
- **#145** (2026-05-26) — Auto-seed subcategories & product images once on first page load
- **#146** (2026-05-26) — Fix subcategory seeding & show subcategory names in shop mega-menu
- **#147** (2026-05-26) — Show subcategories in catalog sidebar widget + scrollable list
- **#148** (2026-05-26) — Collapsible subcategories, brand scroll + seed, availability toggle
- **#149** (2026-05-26) — README: document all session changes + developer guide
- **#150** (2026-05-26) — feat(seo): per-page meta, Product JSON-LD, sitemap.xml, robots.txt
- **#151** (2026-05-27) — fix(checkout): add missing payment_method column to orders
- **#152** (2026-05-27) — feat(orders): buyer cancel for pending orders + support contact after confirmation
- **#153** (2026-05-27) — feat(delivery): per-city delivery (taxi) for Tajikistan
- **#154** (2026-05-27) — feat(delivery): city dropdown in admin, country dropdown in checkout
- **#155** (2026-05-27) — Checkout: динамическая смена городов по выбранной стране
- **#156** (2026-05-27) — Delivery: поддержка стран — выбор страны меняет список городов
- **#157** (2026-05-27) — Скидки, новинки и хиты продаж: бейджи и фильтры
- **#158** (2026-05-27) — Главная: блок «Товары со скидкой»
- **#159** (2026-05-27) — Главная: блок «Товары со скидкой» в стиле шаблона + фикс бага
- **#160** (2026-05-27) — README: документация доставки, скидок, SEO и новых миграций
- **#161** (2026-05-27) — Registration & login by phone (SMS) alongside email
- **#162** (2026-05-27) — Improve phone country selector dropdown styling
- **#163** (2026-05-27) — Control the catalog top banner from the admin panel
- **#164** (2026-05-27) — Organize admin sidebar, banner preview, full permission delegation
- **#165** (2026-05-27) — Баннеры из шаблона: автозаполнение из slider1/2/3.jpg
- **#166** (2026-05-28) — docs: документация телефонной авторизации, баннеров и админ-панели
- **#167** (2026-05-28) — Главная: фото слайдера из шаблона + карусель скидок + настраиваемый таймер слайдера
- **#168** (2026-05-28) — Карусель скидок со стрелками + настраиваемый таймер слайдера
- **#169** (2026-05-28) — Чистый заголовок «Товары со скидкой» как в шаблоне (стрелки + линия)
- **#170** (2026-05-31) — Исправления блока скидок + живое обновление корзины без перезагрузки
- **#171** (2026-05-31) — Живое обновление корзины (счётчик, сумма, список) без перезагрузки
- **#172** (2026-05-31) — Исправить зависание мини-корзины (закрытие по оверлею/крестику/Escape)

### #173–#196 · Аудит (троттлинг, макет персонала, ЧПУ), онлайн-оплата, адаптив, редизайн VIN (31 мая – 21 июня)

- **#173** (2026-05-31) — Исправить курсор и закрытие мини-корзины (оверлей/крестик/Escape)
- **#174** (2026-05-31) — Исправить перекрытие кнопок корзины серым оверлеем (z-index)
- **#175** (2026-06-20) — Вход сотрудников по телефону+PIN + тумблер email-входа в админке
- **#176** (2026-06-20) — Интеграция внешнего каталога запчастей + VIN по API (turnkey)
- **#177** (2026-06-20) — Настройка PartsAPI (VINdecodeOE + getPartsbyVIN) — одноразовый скрипт
- **#178** (2026-06-20) — Диагностика PartsAPI (временный скрипт)
- **#179** (2026-06-20) — Диагностика PartsAPI v2 — документация методов
- **#180** (2026-06-21) — Парсинг VINdecodeOE (PartsAPI) + поиск метода товарных групп
- **#181** (2026-06-21) — VIN-декодер: маппинг реальных полей PartsAPI (транслит)
- **#182** (2026-06-21) — Рабочий каталог запчастей по VIN (PartsAPI getPartsbyVIN)
- **#183** (2026-06-21) — Фикс разбора артикулов каталога (БРЕНД|АРТИКУЛ,...)
- **#184** (2026-06-21) — Фикс: не требовать check digit в VIN-валидации
- **#185** (2026-06-21) — README: актуализация раздела VIN + каталог
- **#186** (2026-06-21) — Фикс VIN/каталога: пустой кэш, type=all, версия декода, OEM-группы
- **#187** (2026-06-21) — fix(http): надёжный cURL-транспорт для PartsAPI (VIN + каталог) + диагностика
- **#188** (2026-06-21) — docs(readme): changelog интеграции PartsAPI + cURL-транспорт (#175–#187)
- **#189** (2026-06-21) — feat(catalog): штатная обработка лимита PartsAPI (error_code 5000 / HTTP 401)
- **#190** (2026-06-21) — docs(readme): статус интеграции PartsAPI по итогам диагностики
- **#191** (2026-06-21) — feat(vin): дерево каталога по узлам + аналоги-кроссы (getCrosses) — МОСТИК цепочки
- **#192** (2026-06-21) — docs(readme): проставить номер PR #191 в changelog
- **#193** (2026-06-21) — fix(responsive): доводка адаптивности по UX-аудиту + единый контент
- **#194** (2026-06-21) — fix(audit): чистка остатков шаблона + страница 403 в стиле Mazlay
- **#195** (2026-06-21) — Аудит: C2 защита входа, C3 единый макет персонала, C5 ЧПУ + наполнение каталога/слайдера + онлайн-оплата со скидкой
- **#196** (2026-06-22) — Редизайн страницы VIN-поиска (структура макета, скин под наш бренд) + табы «По VIN / По параметрам»

### #197–#201 · Каталог по VIN: PartsAPI + универсальная архитектура провайдеров (этапы 1–5), Laximo, гостевая корзина (20–28 июня)

- **#197** (2026-06-28) — feat(catalog): каркас сменных провайдеров — Этап 1 универсальной архитектуры
- **#198** (2026-06-28) — feat(catalog): универсальный движок профилей — Этап 2 (подключение REST без кода)
- **#199** (2026-06-28) — feat(catalog): слой цен — свой склад → AutoEuro → сомони (Этап 3)
- **#200** (2026-06-28) — feat(cart): гостевая корзина — заказ без регистрации (Этап 5)
- **#201** (2026-06-28) — feat(catalog): Laximo-адаптер (оригинальные OEM-каталоги) — Этап 4

### #202–#218 · Parts-Catalogs: визуальные OEM-схемы, «по параметрам», редизайн под 7zap, ширина/дизайн, ARCHITECTURE.md (28 июня – 6 июля)

- **#202** (2026-06-29) — feat(vin): редизайн каталога под макет — дерево узлов + карточки деталей
- **#203** (2026-06-29) — fix(vin): полный дефолтный список узлов + компактная сетка дерева
- **#204** (2026-07-01) — feat(catalog): Parts-Catalogs — визуальные OEM-схемы (кликабельные взрыв-схемы) + провайдер
- **#205** (2026-07-03) — feat(catalog): Parts-Catalogs/Tradesoft — переключаемая авторизация (заголовок/Bearer/параметр api_key)
- **#206** (2026-07-03) — fix(catalog): Parts-Catalogs — нечисловые ID узлов ломали визуальные схемы
- **#207** (2026-07-05) — feat(vin): реальный поиск «По параметрам» через Parts-Catalogs (Марка→Модель→уточнения→авто)
- **#208** (2026-07-03) — fix(vin): авто-прокрутка к схеме Parts-Catalogs при клике по узлу
- **#209** (2026-07-04) — feat(vin): каталог в стиле 7zap — русские названия, миниатюры узлов, смена контента на месте
- **#210** (2026-07-05) — fix(vin): «По параметрам» самоактивируется у Parts-Catalogs (все марк…
- **#211** (2026-07-05) — fix(catalog): Parts-Catalogs — язык через Accept-Language + query lan…
- **#212** (2026-07-05) — feat(vin): каталог — схема на всю ширину + расшифровка сокращений наз…
- **#213** (2026-07-05) — feat(vin): шире контейнер каталога + крупнее карточки узлов (меньше п…
- **#214** (2026-07-05) — fix(vin): каталог по VIN на всю ширину контейнера + шире сайдбар
- **#215** (2026-07-05) — fix(vin): карточка авто на всю ширину + шире карточка поиска (меньше …
- **#216** (2026-07-05) — feat(vin): номер-выноска вместо пустого «фото» в карточках деталей
- **#217** (2026-07-06) — Каталог по VIN: ширина/дизайн, расшифровка сокращений, номер-выноска + карта кода ARCHITECTURE.md
- **#218** (2026-07-06) — docs: ARCHITECTURE.md — развёрнутая полная карта кодовой базы

### #219–#236 · VIN-карточка авто (вариант F) + интерактивные взрыв-схемы: лайтбокс, подсветка, вырезки-фото (9–11 июля)

- **#219** (2026-07-09) — VIN: каталог выше совместимых + реальные данные авто из Parts-Catalogs
- **#220** (2026-07-09) — VIN: реальные данные авто на карточке из Parts-Catalogs (car/info)
- **#221** (2026-07-09) — VIN-каталог: схема узла залипает слева, детали справа
- **#222** (2026-07-10) — VIN-схема: увеличение (лайтбокс), тултип с именем детали, имя на схеме при наведении на карточку
- **#223** (2026-07-10) — VIN-схема: заметная подсветка выноски (свечение + пульсация)
- **#224** (2026-07-10) — VIN-схема: нормализация номеров (01=1) + честный фолбэк для деталей без точки
- **#225** (2026-07-10) — VIN-схема: «фото» карточки = вырезка (зум-фрагмент) схемы вокруг детали
- **#226** (2026-07-10) — VIN-схема: клик по вырезке-фото открывает лайтбокс, приближенный к детали
- **#227** (2026-07-10) — VIN-схема: клик по вырезке разворачивает только фрагмент детали на весь экран
- **#228** (2026-07-10) — VIN-схема: фрагмент детали открывается на весь экран надёжно (без раздутого тултипа)
- **#229** (2026-07-10) — VIN-схема: липкая схема залипает под фиксированным меню (не прячется за шапкой)
- **#230** (2026-07-10) — docs: changelog README — PR #217–#229 (VIN-каталог / схема / карточка авто)
- **#231** (2026-07-10) — VIN-каталог: не кэшировать пустой результат узлов (фикс пустого каталога «По параметрам»)
- **#232** (2026-07-11) — VIN: карточка в режиме «По параметрам» — реальные атрибуты авто из cars2
- **#233** (2026-07-11) — VIN: карточка авто — новый дизайн (вариант F, двухцветный сплит)
- **#234** (2026-07-11) — VIN-карточка F: чертёж меньше + бренд-вотермарк, читаемее характеристики
- **#235** (2026-07-11) — VIN-карточка F: характеристики в две колонки + левая панель уже
- **#236** (2026-07-11) — docs: changelog README — PR #230–#235

### #237–#253 · Библиотека каталога + план экономии лимита API: шаги 1–4, тумблеры, КП, совместимость, аналитика, NAVIGATION.md (11 июля)

- **#237** (2026-07-11) — Каталог: постоянная библиотека архива Parts-Catalogs + выгрузка в суперадмине
- **#238** (2026-07-11) — Фикс миграции библиотеки каталога: убран жёстко прописанный avtozapchast
- **#239** (2026-07-11) — Фикс миграции библиотеки каталога: ROWS — зарезервированное слово
- **#240** (2026-07-11) — Библиотека каталога: увеличение схемы узла по клику (лайтбокс)
- **#241** (2026-07-11) — VIN: мобильные баги — pinch-zoom схемы, залипший тултип, перенос заголовка баннера
- **#242** (2026-07-11) — VIN: редизайн списка узлов + свечение лого марки на карточке авто
- **#243** (2026-07-11) — VIN: чиним разъезжающиеся бейджи (Кэш/PARTS-CATALOGS/VIN) на карточке авто
- **#244** (2026-07-11) — Библиотека каталога: массовый сбор схем — кнопка + отключаемый cron
- **#245** (2026-07-11) — Фикс дублей в дереве узлов (лого «101 из 120» → правильный счёт)
- **#246** (2026-07-11) — Шаг 1 плана: библиотека как источник чтения до API + TTL 30д
- **#247** (2026-07-11) — Шаг 2 плана: печатное КП по узлу (PDF из браузера, без зависимостей)
- **#248** (2026-07-11) — Шаг 3 плана: предложения совместимости из библиотеки каталога (с подтверждением)
- **#249** (2026-07-11) — Шаг 4 плана: аналитика спроса за тумблером (выключена по умолчанию)
- **#250** (2026-07-11) — Тумблеры для шагов 1-3 плана (шаг 4 уже был с тумблером)
- **#251** (2026-07-11) — Фикс: авто «по параметрам» не попадало в библиотеку (сироты в nodes/schemes)
- **#252** (2026-07-11) — Библиотека каталога: понятная подпись вместо «–» у авто без VIN
- **#253** (2026-07-11) — docs: NAVIGATION.md — карта проекта (единая точка входа)
- **#255** (2026-07-12) — docs: полный перечень файлов (Приложение A) + PDF-техдокументация для клиента
- **#256** (2026-07-12) — docs: перечень файлов со ссылками + расширенный клиентский PDF (19 разделов)
- **#257** (2026-07-12) — feat(sms): боевой SMS-шлюз OsonSMS (`sendSms()`→`osonSmsSend()`, настройки в админке)
- **#258** (2026-07-12) — fix(auth): Enter в поле телефона запускает «Получить код», а не сабмит формы
- **#259** (2026-07-12) — fix(auth): кнопка «Войти» на форме телефона скрыта до кода/пароля/PIN
- **#260** (2026-07-12) — fix(auth): clearfix `.login_submit` — устранён наезд ссылки PIN-входа на «Войти по паролю»
- **#263–#264** (2026-07-12) — feat(catalog): CLI-прогрев библиотеки `catalog_library_seed.php` (round-robin по маркам, опция «все схемы»)
- **#265–#266** (2026-07-19) — feat(autoeuro): лог `autoeuro_price_miss`; маппинг брендов `autoeuro_brand_map`
- **#267** (2026-07-19) — feat(chat): переписка покупатель↔менеджер (`messages`, поддержка+по заказам, автосообщение-чек)
- **#268** (2026-07-19) — feat(orders): статусы заказа → автосообщение в чат; блок «Заявка поставщику» (ветка «человек в Москве»)
- **#269–#279** (2026-07-19) — fix(autoeuro): `with_offers=1`, резолв бренд+код через `search_brands` (`searchItemsSmart`), выбор московского delivery_key (кнопка «Выбрать», чистка пробелов), RUB→сомони по курсу `autoeuro_rub_rate`, «под заказ»+дата при stock=0, разделитель тысяч — пробел
- **#280** (2026-07-19) — feat(autoeuro): отдельная наценка `autoeuro_markup`
- **#282–#283** (2026-07-19) — feat(contacts): телефон `+992 92 612-22-22`, авто-формат `formatPhone()`/`phoneTel()`
- **#290** (2026-08-03) — feat(header): прилипающий поиск при прокрутке + отдельное поле VIN (двойной поиск вверху и в прилипшей полосе; `.sticky_search` в `custom.css`)
- **#289** (2026-08-03) — fix(catalog): каскад «По параметрам» не залипает на пустом кэше брендов/моделей (пустое = промах, пустое не кэшируем)
- **#288** (2026-08-03) — feat(catalog): в тесте Parts-Catalogs показывать сырой ответ VIN-декодинга (`v1/car/info/`) — видно причину нераспознавания
- **#287** (2026-07-22) — fix(ui): убрать ценник из карточки — оставить только кнопку «Купить»
- **#286** (2026-07-22) — feat(ui): кнопка «Купить» + модалка выбора доставки (бланк) на VIN; `api/vin_order_request.php` — заявка менеджеру через `messages` (вход обязателен)
- **#285** (2026-07-22) — feat(autoeuro): умный выбор предложения (наличие → дешёвое, иначе → быстрое под заказ), `offersByOem()` со списком вариантов; доставка Москва→Худжанд (`autoeuro_khj_*`: надбавка сумма/%, дни); выбор варианта покупателем + тумблер срока A/B (`autoeuro_offer_mode`, `autoeuro_offers_limit`)

---

## Приложение A. Полный перечень файлов (папка · файл · назначение · связи)

Каждый файл — кликабельная ссылка на его расположение в репозитории. Общий старт любой
страницы (`config/config.php` → `config/database.php` → `includes/functions.php`) в колонке
«Связи» не повторяется — там только специфичное для файла.


### Корень проекта

| Файл | Назначение | Связи |
|---|---|---|
| [`ARCHITECTURE.md`](ARCHITECTURE.md) | Исчерпывающая карта: все функции functions.php, контракты адаптеров, все настройки. | — |
| [`CATALOG_PLAN.md`](CATALOG_PLAN.md) | План/этапы универсального слоя каталога (5 этапов). | — |
| [`CHANGES.md`](CHANGES.md) | Ранняя история изменений (слайдер, баннеры). | — |
| [`CLAUDE.md`](CLAUDE.md) | Инструкции для AI-ассистента по стилю фронтенда. | — |
| [`NAVIGATION.md`](NAVIGATION.md) | Этот файл — единая точка входа: стек, модули, БД, история PR, перечень файлов. | — |
| [`README.md`](README.md) | Установка, деплой, серверные конфиги (nginx/порт), changelog, «как работает». | — |
| [`diag_partsapi.php`](diag_partsapi.php) | Одноразовый диагностический скрипт PartsAPI (кандидат на удаление). | catalog_api |
| [`fix_vin_catalog.php`](fix_vin_catalog.php) | Одноразовый скрипт починки VIN-каталога (кандидат на удаление). | catalog_api |
| [`index.php`](index.php) | Главная страница: слайдер, блок «Товары со скидкой», категории, бренды. Также фолбэк-роутер ЧПУ /product/{id}-{slug}. | sliders, banners, parts; partUrl() |
| [`setup_catalog.php`](setup_catalog.php) | Одноразовая настройка каталога PartsAPI (кандидат на удаление). | — |
| [`sitemap.php`](sitemap.php) | Динамический XML-sitemap для Google/Yandex. | parts, categories, blog_posts |

### config/ и deploy/

| Файл | Назначение | Связи |
|---|---|---|
| [`config/config.php`](config/config.php) | Константы (APP_URL/APP_ROOT), старт сессии, no-cache заголовки, ADMIN_PORT. | подключает database + functions + cart/i18n/currency |
| [`config/database.php`](config/database.php) | getDB() — синглтон PDO; читает креды из db_credentials.php (git-ignored). | используется всем проектом |
| [`deploy/timeweb/config.php`](deploy/timeweb/config.php) | Прод-конфиг для Timeweb (копия config.php). | — |
| [`deploy/timeweb/database.php`](deploy/timeweb/database.php) | Прод-конфиг БД для Timeweb. | — |

### includes/ — ядро

| Файл | Назначение | Связи |
|---|---|---|
| [`includes/admin-footer.php`](includes/admin-footer.php) | Подвал админки. | — |
| [`includes/admin-header.php`](includes/admin-header.php) | Единый макет админки (шапка) + сайдбар по ролям. | renderRoleSidebar, userCan |
| [`includes/autoeuro.php`](includes/autoeuro.php) | Класс AutoEuro: клиент поставщика. `searchItemsSmart` (search_brands→search_items, with_offers=1), `debugRaw` (диагностика ?raw=1). | httpGet, настройки autoeuro_* |
| [`includes/messaging.php`](includes/messaging.php) | Чат покупатель↔менеджер: таблица `messages`, ветки поддержки/по заказам, автосообщения, счётчики непрочитанного. | getDB (graceful) |
| [`includes/cart_lib.php`](includes/cart_lib.php) | Корзина гостя (сессия) и юзера (БД); merge при входе; заказ гостя по телефону. | getDB, cart |
| [`includes/catalog.php`](includes/catalog.php) | Одна строка — подключает слой каталога (фасад Catalog::). | catalog/Manager.php |
| [`includes/catalog_api.php`](includes/catalog_api.php) | Класс CatalogApi: боевой PartsAPI (getPartsbyVIN/getCrosses) + enrichItemsFromWarehouse(). | partsapi_catalog_cache, parts |
| [`includes/currency.php`](includes/currency.php) | Валюта: активная/курс/символ, formatPrice, convertPrice. | currencies |
| [`includes/footer.php`](includes/footer.php) | Витринный подвал: соцсети, ссылки, newsletter. | настройки site_* |
| [`includes/functions.php`](includes/functions.php) | «Сердце» проекта (~1857 строк): auth, роли/права, настройки, httpGet, товары, заказы, SMS/OTP, троттлинг входа, онлайн-оплата, сидеры, dbAddColumnIfMissing. | зовётся везде |
| [`includes/header.php`](includes/header.php) | Витринная шапка: меню, мини-корзина, **двойной поиск (запчасти + отдельное поле VIN)**, прилипающий поиск при прокрутке (`.sticky_search`), переключатели языка/валюты. | getMiniCart, getCategories |
| [`includes/i18n.php`](includes/i18n.php) | Локализация: initLang/t/tField. | lang/*.php, languages |
| [`includes/manual_pdf.php`](includes/manual_pdf.php) | Генератор PDF-руководства для суперадмина. | superadmin/manual.php |
| [`includes/nav.php`](includes/nav.php) | Навигационное меню витрины по ролям. | getCategories |
| [`includes/partsapi_cats.php`](includes/partsapi_cats.php) | Справочник 751 товарной группы PartsAPI (id→название). | catalog_api |
| [`includes/vin_service.php`](includes/vin_service.php) | Класс VinService: валидация/декод VIN, WMI-база, совместимость, аналоги, история. | vin_cache, car_models, parts_compatibility |

### includes/catalog/ — слой каталога

| Файл | Назначение | Связи |
|---|---|---|
| [`includes/catalog/AutoEuroPriceProvider.php`](includes/catalog/AutoEuroPriceProvider.php) | Цена от AutoEuro по OEM — фолбэк. | autoeuro.php |
| [`includes/catalog/CatalogProfiles.php`](includes/catalog/CatalogProfiles.php) | Реестр профилей провайдеров (настройка catalog_profiles). | GenericRestAdapter |
| [`includes/catalog/GenericRestAdapter.php`](includes/catalog/GenericRestAdapter.php) | Универсальный REST-адаптер: исполняет JSON-профиль (провайдер без кода). | CatalogProfiles, httpGet |
| [`includes/catalog/LaximoAdapter.php`](includes/catalog/LaximoAdapter.php) | Провайдер laximo — оригинальные каталоги (каркас). | Provider, httpGet |
| [`includes/catalog/Manager.php`](includes/catalog/Manager.php) | Фасад Catalog: provider/available/reset/price — выбирает адаптер по catalog_provider. | все адаптеры |
| [`includes/catalog/MockAdapter.php`](includes/catalog/MockAdapter.php) | Провайдер mock — демо-каталог без ключа. | Provider |
| [`includes/catalog/PartsApiAdapter.php`](includes/catalog/PartsApiAdapter.php) | Провайдер partsapi — обёртка CatalogApi в единый интерфейс. | catalog_api.php |
| [`includes/catalog/PartsCatalogsAdapter.php`](includes/catalog/PartsCatalogsAdapter.php) | ★ Основной провайдер partspc: OEM-каталоги Parts-Catalogs + взрыв-схемы; кэш; библиотека; сбор схем; аналитика спроса (~1039 строк). | Provider, catalog_api(enrich), catalog_library_*, partsapi_kv_cache |
| [`includes/catalog/PriceAggregator.php`](includes/catalog/PriceAggregator.php) | Слой цен: свой склад → AutoEuro → сомони. | Warehouse/AutoEuroPriceProvider, catalog_price_cache |
| [`includes/catalog/PriceProvider.php`](includes/catalog/PriceProvider.php) | Контракт слоя цен. | реализуют price-провайдеры |
| [`includes/catalog/Provider.php`](includes/catalog/Provider.php) | Интерфейс CatalogProvider — контракт всех адаптеров каталога. | реализуют все адаптеры |
| [`includes/catalog/WarehousePriceProvider.php`](includes/catalog/WarehousePriceProvider.php) | Цена со своего склада (parts) — приоритетный источник. | parts |

### api/ — AJAX-эндпоинты

| Файл | Назначение | Связи |
|---|---|---|
| [`api/autoeuro_order.php`](api/autoeuro_order.php) | Прокси к AutoEuro: создание заказа. | autoeuro.php |
| [`api/autoeuro_search.php`](api/autoeuro_search.php) | Прокси к AutoEuro: поиск предложений. | autoeuro.php |
| [`api/cart.php`](api/cart.php) | AJAX корзины: add/remove/update/count/mini. | cart_lib.php |
| [`api/review_submit.php`](api/review_submit.php) | Отправка отзыва на товар. | product_reviews |
| [`api/search.php`](api/search.php) | AJAX: живой поиск-подсказки по товарам. | parts |
| [`api/shop_review_submit.php`](api/shop_review_submit.php) | Отправка отзыва о магазине. | shop_reviews |
| [`api/sms_auth.php`](api/sms_auth.php) | AJAX: отправка одноразового SMS-кода. | createPhoneOtp, sendSms |
| [`api/upload.php`](api/upload.php) | Загрузка изображений (только сотрудникам). | assets/uploads/ |
| [`api/vin_analogs.php`](api/vin_analogs.php) | AJAX: аналоги из своего каталога. | VinService::getAnalogs |
| [`api/vin_catalog.php`](api/vin_catalog.php) | AJAX: детали узла / каталог по VIN. | Catalog::provider() |
| [`api/vin_crosses.php`](api/vin_crosses.php) | AJAX: аналоги-кроссы по артикулу. | catalog_api getCrosses |
| [`api/vin_nodes.php`](api/vin_nodes.php) | AJAX: дерево узлов авто (по VIN или carId+catalogId). | Catalog::provider() |
| [`api/vin_params.php`](api/vin_params.php) | AJAX: каскад «по параметрам» (марка→модель→уточнения→авто). | Catalog::provider() |
| [`api/vin_price.php`](api/vin_price.php) | AJAX: цена по OEM-номеру (ленивая подгрузка). | Catalog::price() |
| [`api/vin_scheme.php`](api/vin_scheme.php) | AJAX: взрыв-схема узла (img + hotspots + parts). | Catalog::provider() |
| [`api/wishlist.php`](api/wishlist.php) | AJAX избранного: toggle/count. | wishlist |

### pages/ — публичные страницы

| Файл | Назначение | Связи |
|---|---|---|
| [`pages/403.php`](pages/403.php) | Страница «Доступ запрещён» (стиль Mazlay). | denyAccess() |
| [`pages/404.php`](pages/404.php) | Страница «Не найдено». | — |
| [`pages/about.php`](pages/about.php) | CMS-страница «О нас». | site_sections |
| [`pages/blog-detail.php`](pages/blog-detail.php) | Статья блога. | blog_posts |
| [`pages/blog.php`](pages/blog.php) | Список статей блога. | blog_posts |
| [`pages/contact.php`](pages/contact.php) | Контакты + карта. | site_sections, map_* |
| [`pages/faq.php`](pages/faq.php) | Часто задаваемые вопросы. | site_sections |
| [`pages/reviews.php`](pages/reviews.php) | Отзывы о магазине. | shop_reviews |
| [`pages/vin.php`](pages/vin.php) | ★ Страница VIN+каталог (~1686 строк): карточка авто, дерево узлов, взрыв-схемы, лайтбокс, кнопка КП. | VinService, Catalog::provider(), api/vin_* |
| [`pages/vin_kp.php`](pages/vin_kp.php) | Печатное КП по узлу (список деталей + живые цены → PDF из браузера). | Catalog::provider(), склад |

### catalog/ и search/ — витрина

| Файл | Назначение | Связи |
|---|---|---|
| [`catalog/category.php`](catalog/category.php) | Страница отдельной категории. | categories, parts |
| [`catalog/index.php`](catalog/index.php) | Каталог товаров: фильтры (категория/бренд/цена/наличие), сортировка, пагинация, сайдбар, верхний баннер. | parts, categories, brands, banners |
| [`catalog/part.php`](catalog/part.php) | Карточка товара: галерея, цена/скидка, отзывы, аналоги, JSON-LD, canonical на ЧПУ. | parts, product_reviews, part_analogs |
| [`search/index.php`](search/index.php) | Страница результатов поиска по товарам. | parts |

### buyer/ — кабинет покупателя

| Файл | Назначение | Связи |
|---|---|---|
| [`buyer/cart.php`](buyer/cart.php) | Корзина (доступна гостю). | cart_lib.php |
| [`buyer/checkout.php`](buyer/checkout.php) | Оформление заказа: адрес, зоны доставки, способы оплаты; заказ гостя по телефону. | cart_lib, delivery_zones, orders, onlinePayment* |
| [`buyer/index.php`](buyer/index.php) | Дашборд покупателя. | orders |
| [`buyer/orders.php`](buyer/orders.php) | Список/детали заказов, отмена покупателем; кнопка «Написать по заказу». | orders, order_items, messaging |
| [`buyer/messages.php`](buyer/messages.php) | Переписка покупателя с менеджером: поддержка + ветки по заказам. | messaging (messages) |
| [`buyer/profile.php`](buyer/profile.php) | Профиль: имя/адрес/город/аватар. | users |
| [`buyer/wishlist.php`](buyer/wishlist.php) | Избранное. | wishlist |

### auth/ — вход и регистрация

| Файл | Назначение | Связи |
|---|---|---|
| [`auth/login.php`](auth/login.php) | Вход: email+пароль ИЛИ телефон (SMS-код/PIN); троттлинг 5 неудач→15 мин. | verifyPhoneOtp, loginThrottle*, loginUser |
| [`auth/logout.php`](auth/logout.php) | Выход (очистка сессии). | — |
| [`auth/register.php`](auth/register.php) | Регистрация: телефон (SMS) или email. | createPhoneOtp, users |

### admin/ — роль admin

| Файл | Назначение | Связи |
|---|---|---|
| [`admin/banners.php`](admin/banners.php) | Баннеры + placement (где показывать). | banners |
| [`admin/index.php`](admin/index.php) | Дашборд + выручка. | orders |
| [`admin/orders.php`](admin/orders.php) | Заказы + смена статусов (→ автосообщение в чат) + блок «Заявка поставщику». | orders, messaging |
| [`admin/messages.php`](admin/messages.php) | Входящие переписки покупателей (непрочитанные выше), ответ. | messaging (messages) |
| [`admin/products.php`](admin/products.php) | Товары: фото, цены, наценка. | parts, getEffectiveMarkup |
| [`admin/sliders.php`](admin/sliders.php) | Блочный редактор слайдера: 9 позиций текста, шрифты, десктоп/мобильный, live-preview. | sliders, normalizeSliderBlocks |
| [`admin/users.php`](admin/users.php) | Пользователи (для admin). | users |

### manager/ — роль manager

| Файл | Назначение | Связи |
|---|---|---|
| [`manager/blog.php`](manager/blog.php) | Редактор блога. | blog_posts |
| [`manager/brands.php`](manager/brands.php) | Бренды (+логотипы, сортировка). | brands |
| [`manager/categories.php`](manager/categories.php) | Категории (+картинки). | categories |
| [`manager/index.php`](manager/index.php) | Дашборд менеджера. | — |
| [`manager/pages.php`](manager/pages.php) | CMS: контент страниц about/contact/faq. | site_sections |
| [`manager/parts.php`](manager/parts.php) | Товары (для manager). | parts |
| [`manager/reviews.php`](manager/reviews.php) | Модерация отзывов. | product_reviews, shop_reviews |

### superadmin/ — роль superadmin

| Файл | Назначение | Связи |
|---|---|---|
| [`superadmin/_backup_lib.php`](superadmin/_backup_lib.php) | Общие функции бэкапа (для UI и cron). | backups |
| [`superadmin/backup.php`](superadmin/backup.php) | SQL-бэкапы: UI (создать/скачать/удалить). | backups, storage/backups/ |
| [`superadmin/backup_cron.php`](superadmin/backup_cron.php) | CLI-бэкап + ротация старых копий. | _backup_lib.php |
| [`superadmin/blog.php`](superadmin/blog.php) | Блог (управление на уровне суперадмина). | blog_posts |
| [`superadmin/catalog_library.php`](superadmin/catalog_library.php) | Библиотека каталога: список авто, экспорт JSON/CSV, сбор схем, тумблеры, аналитика спроса (~671 строка). | catalog_library_*, catalog_demand, PartsCatalogsAdapter |
| [`superadmin/catalog_library_cron.php`](superadmin/catalog_library_cron.php) | CLI-дособиратель схем библиотеки (по тумблеру автосбора). | PartsCatalogsAdapter::harvestSchemes |
| [`superadmin/catalog_library_seed.php`](superadmin/catalog_library_seed.php) | CLI-прогрев библиотеки: обход марок/моделей/авто (round-robin), запуск вручную. | pcBrands/pcModels/pcCars, oemNodesForCar |
| [`superadmin/catalog_library_seed.php`](superadmin/catalog_library_seed.php) | CLI-скрипт прогрева библиотеки: обходит марки/модели/авто (по параметрам, без VIN), сохраняя новые записи. Запускать вручную. | pcBrands, pcModels, pcCars, oemNodesForCar, harvestSchemes |
| [`superadmin/currencies.php`](superadmin/currencies.php) | Валюты и курсы. | currencies |
| [`superadmin/delivery.php`](superadmin/delivery.php) | Зоны доставки (город+страна+цена+срок). | delivery_zones |
| [`superadmin/index.php`](superadmin/index.php) | Дашборд суперадмина (сводная статистика). | — |
| [`superadmin/languages.php`](superadmin/languages.php) | Языки интерфейса. | languages |
| [`superadmin/manual.php`](superadmin/manual.php) | Руководство администратора (PDF). | manual_pdf.php |
| [`superadmin/permissions.php`](superadmin/permissions.php) | Делегирование разделов админки по пользователям. | user_permissions |
| [`superadmin/settings.php`](superadmin/settings.php) | Общие настройки: контакты, соцсети, email-вход, онлайн-оплата, SEO, карта. | site_settings |
| [`superadmin/users.php`](superadmin/users.php) | Пользователи, роли, PIN сотрудников. | users |
| [`superadmin/vin.php`](superadmin/vin.php) | Настройки VIN/каталога (провайдер, ключи, язык, схемы, профили) + авто/совместимость + предложения из библиотеки (~1079 строк). | Catalog, car_models, parts_compatibility, catalog_library_* |
| [`superadmin/warehouse.php`](superadmin/warehouse.php) | AutoEuro: ключ, delivery/payer, тест соединения. | autoeuro.php |

### lang/ и assets/

| Файл | Назначение | Связи |
|---|---|---|
| [`assets/css/custom.css`](assets/css/custom.css) | Все наши CSS-правки поверх шаблона. | витрина+админка |
| [`assets/css/style.css`](assets/css/style.css) | Базовый стиль шаблона Mazlay. | — |
| [`assets/js/app.js`](assets/js/app.js) | Основной клиентский JS: live-корзина, слайдер, SMS-кнопка, мобильные фиксы. | api/cart, api/sms_auth |
| [`assets/js/main.js`](assets/js/main.js) | Шаблонный JS (Owl-карусель и т.п.). | — |
| [`lang/en.php`](lang/en.php) | Английские переводы. | t() |
| [`lang/ru.php`](lang/ru.php) | Русские переводы интерфейса (317 ключей). | t() |
| [`lang/tg.php`](lang/tg.php) | Таджикские переводы. | t() |

### sql/ — миграции БД

| Файл | Назначение | Связи |
|---|---|---|
| [`sql/add_brand_logo.sql`](sql/add_brand_logo.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/add_brand_sort_order.sql`](sql/add_brand_sort_order.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/add_category_image.sql`](sql/add_category_image.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/add_delivery_zones.sql`](sql/add_delivery_zones.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/add_delivery_zones_country.sql`](sql/add_delivery_zones_country.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/add_old_price.sql`](sql/add_old_price.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/add_order_payment_method.sql`](sql/add_order_payment_method.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/add_phone_auth.sql`](sql/add_phone_auth.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/add_user_profile_fields.sql`](sql/add_user_profile_fields.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/cleanup_test_sliders.sql`](sql/cleanup_test_sliders.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/fix_images.sql`](sql/fix_images.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_catalog_library.sql`](sql/migrate_catalog_library.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_cms.sql`](sql/migrate_cms.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_markup.sql`](sql/migrate_markup.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_permissions.sql`](sql/migrate_permissions.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_reviews.sql`](sql/migrate_reviews.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_reviews_v2.sql`](sql/migrate_reviews_v2.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_tajik_market.sql`](sql/migrate_tajik_market.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_vin.sql`](sql/migrate_vin.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/migrate_vin_v2.sql`](sql/migrate_vin_v2.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/only_tjs_currency.sql`](sql/only_tjs_currency.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/rename_to_autodoc.sql`](sql/rename_to_autodoc.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/rename_to_avtodoc.sql`](sql/rename_to_avtodoc.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/schema.sql`](sql/schema.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/schema_autoeuro.sql`](sql/schema_autoeuro.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/schema_v2.sql`](sql/schema_v2.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/schema_v3.sql`](sql/schema_v3.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/schema_v4.sql`](sql/schema_v4.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |
| [`sql/tjs_direct_pricing.sql`](sql/tjs_direct_pricing.sql) | SQL-миграция (структура/данные БД). Идемпотентна; на проде часто накатывается рантаймом. | — |