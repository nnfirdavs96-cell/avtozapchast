# ARCHITECTURE — полная карта кодовой базы autodoc.tj

> Назначение: **исчерпывающий навигатор** по проекту. Здесь всё: каждый файл,
> функции, настройки, таблицы БД, контракты API, JS. Секретов нет (ключи — только
> в БД `site_settings` и git-ignored `config/db_credentials.php`).
> Быстрый поиск задачи → раздел 12 «хочу изменить X → файл Y».

---

## 1. Технический стек

| Слой | Что | Детали |
|------|-----|--------|
| Язык | PHP 8.0+ | без фреймворка; каждая страница — отдельный php-файл |
| БД | MySQL 8 / MariaDB 10.4+ | PDO, `ERRMODE_EXCEPTION`, utf8mb4 |
| Фронт | Bootstrap 4 + шаблон Mazlay + jQuery + Owl Carousel | VIN-страница — своя вёрстка (CSS-токены `--vx-*`: red `#C70909`, ink `#181a1f`, bg `#f4f5f7`) |
| HTTP к внешним API | `httpGet()` — cURL с CA-bundle → fallback `file_get_contents` | почему: на shared-хостинге fopen по HTTPS молча падает |
| i18n | 3 языка ru/tg/en, `t('key')`, переключение через сессию | `lang/ru.php`, `lang/tg.php`, `lang/en.php` |
| Валюта | база хранения — **RUB**; `formatPrice($rub)` конвертирует в активную (сомони TJS) | курсы — таблица `currencies` |
| Хостинг | Timeweb shared (`vh464`, корень `~/public_html`) | PHP-FPM; `.htaccess` для ЧПУ |
| Деплой | git: ветка `claude/initial-setup-BNMb8` → PR → merge `main` → на сервере `git pull origin main` | **в `main` напрямую не пушить** |

---

## 2. Бутстрап и конфиг

**`config/config.php`** — точка входа каждого файла (`require config/config.php`):
- стартует сессию, определяет `APP_URL` автоматически (переживает `git pull`), `APP_ROOT`;
- подключает по порядку: `config/database.php` → `includes/functions.php` → `includes/cart_lib.php` → `includes/i18n.php` → `includes/currency.php`.

**`config/database.php`** — `getDB(): PDO` (singleton). Реальные креды БД — в
`config/db_credentials.php` (**git-ignored**, у каждого сервера свой).

---

## 3. Структура папок (все файлы)

### 3.1 `includes/` — ядро
| Файл | Назначение |
|------|-----------|
| `functions.php` (~1850 строк) | все общие хелперы — полный список функций в разделе 4 |
| `cart_lib.php` | корзина гостя (сессия) + юзера (БД), merge при входе, привязка заказа гостя по телефону |
| `vin_service.php` | класс `VinService` — декодер VIN (NHTSA / PartsAPI / локальная WMI-база), совместимость, аналоги, история |
| `catalog.php` | 1 строка: подключает `catalog/Manager.php` → фасад `Catalog::` |
| `catalog_api.php` | класс `CatalogApi` — боевой PartsAPI (getPartsbyVIN, getCrosses) + публичная `enrichItemsFromWarehouse()` (обогащение складом для ВСЕХ адаптеров) |
| `autoeuro.php` | класс `AutoEuro` — клиент поставщика (баланс, склады, `searchItems`/`searchItemsSmart`, заказ, `debugRaw`) |
| `messaging.php` | чат покупатель ↔ менеджер (таблица `messages`, ветки поддержки/по заказам, автосообщения) |
| `seller.php` | маркетплейс: `currentSeller()` (магазин текущего пользователя), `requireSeller()` (гейт кабинета), `sellerStatusInfo()` / `sellerProductStatusInfo()` — человеко-понятные статусы для UI |
| `seller_nav.php` | навигация кабинета продавца (активный пункт — `$sellerNavActive`) |
| `parts/supplier_cards.php` | блок «Найдено у поставщика AutoEuro» на странице поиска: сетка карточек + модалка выбора доставки + JS (ленивая цена, кнопка «Показать ещё»). Классы `.sup-*` — VIN-страницу не трогает |
| `parts/supplier_card_render.php` | общий рендер ОДНОЙ карточки поставщика (`supplierCardHtml()`) + `supplierHybridImage()` (фото из нашего каталога по артикулу, иначе заглушка). Используют и страница поиска, и дозагрузка `api/supplier_search.php` — разметка одинаковая |
| `partsapi_cats.php` | константы `PARTSAPI_CATS` (751 товарная группа id→название), `PARTSAPI_POPULAR` |
| `header.php` / `footer.php` | витринная шапка (меню, мини-корзина, **двойной поиск: запчасти + VIN**, языки/валюта) / подвал. На мобильном/планшете (`≤991px`) поиск в виде **сегментированного переключателя «Запчасти / По VIN»** (класс `.msearch_tabs` + `.search_unibar`, data-mode) — одно поле за раз. Поиск дублируется в «прилипающей» полосе — при прокрутке остаётся доступен (класс `.sticky` вешает тема — правило `.sticky-header.sticky` в `mazlay-css/style.css:795`; стили `.sticky_search` в `custom.css`) |
| `admin-header.php` / `admin-footer.php` | единый макет админки; сайдбар по ролям `renderRoleSidebar()` |
| `nav.php` | навигационное меню витрины |
| `i18n.php` | `initLang/getLang/loadTranslations/t/tField` |
| `currency.php` | `initCurrency/getActiveCurrency/getCurrencies/getCurrencyRate/getCurrencySymbol/formatPrice/convertPrice` |
| `manual_pdf.php` | генерация PDF-руководства для суперадмина |

### 3.2 `includes/catalog/` — слой каталога (12 файлов, детально в разделе 5)
`Provider.php`, `Manager.php`, `PartsCatalogsAdapter.php`, `PartsApiAdapter.php`,
`LaximoAdapter.php`, `GenericRestAdapter.php`, `CatalogProfiles.php`, `MockAdapter.php`,
`PriceProvider.php`, `WarehousePriceProvider.php`, `AutoEuroPriceProvider.php`, `PriceAggregator.php`.

### 3.3 `api/` — AJAX-эндпоинты (JSON; контракты в разделе 6)
| Файл | Что делает |
|------|-----------|
| `vin_catalog.php` | детали узла / полный каталог по VIN |
| `vin_nodes.php` | дерево узлов авто (по VIN или carId+catalogId) |
| `vin_scheme.php` | взрыв-схема узла: картинка + хотспоты + детали |
| `vin_params.php` | каскад «По параметрам» (brands/models/carparams/cars) |
| `vin_price.php` | цена по OEM (склад→AutoEuro) |
| `vin_crosses.php` | аналоги-кроссы по артикулу |
| `vin_analogs.php` | аналоги детали из СВОЕГО каталога (part_analogs + авто-детект) |
| `cart.php` | корзина: add/remove/update/count/mini (гость+юзер) |
| `wishlist.php` | избранное: toggle/count |
| `search.php` | живой поиск (подсказки) по товарам |
| `sms_auth.php` | отправка одноразового SMS-кода (регистрация/вход по телефону) |
| `review_submit.php` / `shop_review_submit.php` | отзывы на товар / на магазин |
| `upload.php` | загрузка изображений (только залогиненным сотрудникам) |
| `autoeuro_search.php` / `autoeuro_order.php` | прокси к AutoEuro: поиск предложений / создание заказа |
| `supplier_search.php` | дозагрузка карточек поставщика по названию (кнопка «Показать ещё»): `?q=&offset=&limit=` → `{html, has_more, next_offset, count}`. Отдаёт только разметку — цена подгружается на клиенте через `vin_price.php` |

### 3.4 `pages/` — публичные страницы
| Файл | Что |
|------|-----|
| `vin.php` (~1280 строк) | **главная страница VIN+каталог** — детально в разделе 7 |
| `about.php`, `contact.php`, `faq.php` | CMS-страницы (контент из таблицы `site_sections`) |
| `blog.php` / `blog-detail.php` | блог (таблица `blog_posts`) |
| `reviews.php` | отзывы о магазине (`shop_reviews`) |
| `403.php` / `404.php` | страницы ошибок в стиле Mazlay (`denyAccess()` рендерит 403) |

### 3.5 `catalog/` — витрина магазина
| Файл | Что |
|------|-----|
| `index.php` | каталог товаров: фильтры (категория/бренд/цена/в наличии), сортировка, пагинация, сайдбар, верхний баннер из админки |
| `category.php` | страница категории |
| `part.php` | карточка товара: галерея, цена/скидка, отзывы, аналоги, Schema.org JSON-LD, canonical на ЧПУ `/product/{id}-{slug}`, 301 со старых `?id=` |

### 3.6 `buyer/` — кабинет покупателя
| Файл | Что |
|------|-----|
| `cart.php` | корзина (доступна гостю) |
| `checkout.php` | оформление: адрес/город (зоны доставки по странам), способы оплаты (нал. / банк / **онлайн со скидкой**), заказ гостя привязывается по телефону |
| `orders.php` | список/детали заказов, отмена покупателем |
| `profile.php` | профиль (имя/адрес/телефон) |
| `wishlist.php` | избранное |
| `messages.php` | переписка с менеджером: поддержка + ветки по заказам (§ чат) |
| `index.php` | дашборд покупателя |

### 3.7 `auth/`
| Файл | Что |
|------|-----|
| `login.php` | вход: email+пароль ИЛИ телефон (SMS-код; для сотрудников — PIN). Троттлинг: 5 неудач → блок 15 мин (`login_attempts`). CSRF, валидация redirect |
| `register.php` | регистрация: телефон (SMS) или email |
| `logout.php` | выход |

### 3.8 `seller/` — кабинет продавца (маркетплейс, см. § 16)
| Файл | Что |
|------|-----|
| `index.php` | обзор: статус магазина (на модерации / одобрен / заблокирован) с подсказкой, счётчики товаров по статусам, кнопка «Добавить товар» (заблокирована до одобрения), блок «Как это работает» |
| `products.php` | мои товары: список со статусами модерации + причина отклонения, изменить/удалить (только свои — `WHERE seller_id = ?`) |
| `product_edit.php` | добавление/редактирование товара: название, артикул, бренд, категория, цена/старая цена, наличие, вес, габариты, описание, до 6 фото (через `api/upload.php?type=products`). Сохраняется с `seller_id` + `moderation_status='pending'`; **правка тоже уходит на повторную проверку** |

Гейт — `requireSeller()` из `includes/seller.php`. Публичная витрина магазина —
`seller_shop.php?slug=` в корне.

### 3.9 `admin/` (роль admin) — товары и оформление витрины
`index.php` (дашборд+выручка), `products.php` (товары+фото+цены+наценка),
`orders.php` (заказы+статусы+автосообщения+«заявка поставщику»), `users.php`,
`messages.php` (входящие переписки покупателей), `sliders.php` (слайдер: блочный
текст-редактор с live-preview, 9 позиций текста, шрифты), `banners.php` (баннеры + placement),
**`sellers.php`** (модерация продавцов: одобрить/заблокировать с причиной, комиссия, фильтр по статусу),
**`product_moderation.php`** (модерация листингов продавцов: одобрить → публикация / отклонить с причиной).

### 3.10 `manager/` (роль manager) — контент
`index.php`, `parts.php`, `categories.php`, `brands.php`, `blog.php`, `pages.php` (CMS), `reviews.php` (модерация).

### 3.11 `superadmin/` (роль superadmin) — всё управление
| Файл | Что |
|------|-----|
| `vin.php` | **настройки VIN и каталога**: провайдер, ключи (PartsAPI/Parts-Catalogs/Laximo), язык, схемы, профили JSON, OEM-узлы, тест соединения, кэш; + автомобили (`car_models`) и совместимость (`parts_compatibility`) |
| `settings.php` | общие настройки: контакты, соцсети, email-вход, онлайн-оплата (скидка/бесплат.доставка), SEO meta, карта |
| `users.php` | пользователи, роли, PIN сотрудников |
| `permissions.php` | делегирование разделов админки по пользователям |
| `warehouse.php` | AutoEuro: ключ, delivery/payer, тест |
| `currencies.php` / `languages.php` | валюты (курсы) / языки |
| `delivery.php` | зоны доставки (город+страна+цена+срок) |
| `blog.php` | блог |
| `backup.php` / `backup_cron.php` / `_backup_lib.php` | SQL-бэкапы (UI + cron), ротация |
| `manual.php` | руководство (PDF) |
| `catalog_library.php` (+`_cron`, `_seed`) | библиотека каталога: список авто плитками, пересчёт дерева, сбор схем, автосбор |
| `index.php` | дашборд |

**CLI-скрипты каталога и словаря** (только из консоли, см. § 16.4 и § 17):
| Файл | Что |
|------|-----|
| `catalog_library_rebuild.php` | **основной инструмент обслуживания**: `plan` (оценка без изменений), `recount` (пересчёт деревьев), `schemes` (добор схем), `revin` (починка протухших комплектаций — ЕДИНСТВЕННЫЙ режим, тратящий тариф). Возобновляемый, с файловой блокировкой от параллельного запуска |
| `pc_tree_probe.php` | диагностика: реальный размер дерева узлов без потолка (только чтение) |
| `pc_quota_probe.php` | диагностика: заголовки ответа API — сообщает ли он остаток квоты |
| `ae_dict_import_csv.php` / `ae_dict_batch.php` | наполнение словаря названий AutoEuro (см. § 16.4) |

### 3.12 Прочее
- `search/index.php` — страница поиска.
- `index.php` (корень) — главная (слайдер, скидки, категории) + **фолбэк-роутер ЧПУ** `/product/{id}-{slug}` для nginx.
- `.htaccess` — правило ЧПУ для Apache.
- `assets/` — css (`custom.css` — все наши правки), js (`app.js`, `main.js`), img.
- `deploy/` — заметки по деплою. `storage/` — логи (`sms.log`).

---

## 4. `includes/functions.php` — все функции

### Авторизация, роли, доступ
| Функция | Что делает |
|---|---|
| `isLoggedIn(): bool` | есть ли user_id в сессии |
| `getCurrentUser(): ?array` | текущий юзер из БД (кэш в сессии) |
| `hasRole($role): bool` / `requireRole($role)` | проверка/требование роли (строка или массив) |
| `denyAccess()` | рендер страницы 403 (вместо редиректа) |
| `permissionSections(): array` | каталог разделов админки key→название |
| `permissionAlias(string)` | алиасы разделов |
| `roleDefaultSections(string $role)` | разделы по умолчанию для роли |
| `getUserConfiguredSections(int)` / `effectiveAllowedSections(int,string)` | персональные права из `user_permissions` |
| `userCan(string $section): bool` / `requirePermission($section)` | точечный доступ к разделу |
| `requireAdminPort()` | (опц.) отдельный порт для админки |
| `loginUser(array $user)` | логин: regenerate session id, запись user_id/role + **merge гостевой корзины** |

### CSRF, флеш, редирект, экранирование
`generateCsrfToken()`, `verifyCsrfToken(?string)`, `flashMessage(type,msg)`,
`getFlashMessage()`, `redirect(url)`, `sanitize($input)` (htmlspecialchars).

### Настройки и HTTP
| Функция | Что делает |
|---|---|
| `getSetting(key, default)` | значение из `site_settings` (кэш на запрос) |
| `setSetting(key, value)` | upsert настройки |
| **`httpGet(url, timeout=12, headers=[])`** | GET: cURL (verify → retry без verify при 60/51/35) → fopen. Возврат `['body','status','error','transport']` |

### Каталог витрины / товары
`getCategories()`, `getCategoryTree()`, `getBrands()`, `getStockStatus(stock)`,
`discountPercent(part)`, `isNewProduct(part)`, `productBadges(part)` (бейджи −XX%/Новый),
`priceBox(part)` (цена+зачёркнутая старая), `productImageUrl($images)`,
`getEffectiveMarkup(partId, categoryId)` (наценка: товар → категория → `global_markup`),
`partUrl($part)` (ЧПУ `/product/{id}-{slug}`), `categorySlugify(name)`.

### Отзывы/рейтинги
`getProductRatings(ids)`, `productStarsInline`, `starsHtml(float)`,
`userPurchasedPart(userId, partId)` (отзыв только купившим), `getShopRatingSummary()`.

### Корзина/избранное (шапка)
`getCartCount()`, `getMiniCart()`, `getMiniCartTotal()` (все — через cart_lib, работают у гостя), `getWishlistCount()`.

### Заказы
`getOrderStatusLabel/Class(status)`, `formatShippingAddress(json)`. Смена статуса в `admin/orders.php` шлёт покупателю автосообщение в переписку заказа (подтверждён/отправлен/доставлен/отменён).

### Чат покупатель ↔ менеджер (`includes/messaging.php`)
Таблица `messages`; ветка = пара (`user_id`, `order_id`): `order_id IS NULL` — общая поддержка, число — переписка по заказу. Отправитель `customer`/`staff`/`system`. Функции: `postMessage`/`postSystemMessage`, `getThreadMessages`, `getCustomerThreads`/`getStaffThreads`, `markThreadRead`, `customerUnreadCount`/`staffUnreadCount`. UI: `buyer/messages.php`, `admin/messages.php`; значок непрочитанного в обоих меню. Автосообщения: «чек» при оформлении (`checkout.php`) и статусы заказа. Вся логика в try/catch — при недоступности БД страница не падает.

### Телефон (показ)
`formatPhone($raw)` → «+992 XX XXX-XX-XX» из любого ввода; `phoneTel($raw)` → цифры для `tel:`. Номер магазина — настройка `site_phone`.

### Телефонная авторизация / SMS
| Функция | Что делает |
|---|---|
| `normalizePhone(raw)` | цифры; 8→7; 9 цифр → +992 |
| `phoneCountriesCatalog()` / `enabledPhoneCountries()` | справочник стран (флаг, код, маска) |
| `smsConfigured()` / `sendSms(phone,msg)` | боевой шлюз **OsonSMS** (`sms_provider=osonsms` → `osonSmsSend()`); пусто → ТЕСТ-режим: код в `storage/sms.log` + на экран |
| `osonSmsSend(phone,msg)` / `osonSmsLocalPhone(phone)` | HTTP-клиент OsonSMS (GET + `Authorization: Bearer <hash>`) и приведение номера к локальному 9-значному формату |
| `createPhoneOtp(phone,purpose)` / `verifyPhoneOtp(...)` | одноразовые коды (таблица `phone_otp`) |
| `findUserByPhone(phone)` | поиск юзера по `phone_e164` |
| `emailAuthEnabled()` | тумблер `auth_email_enabled` |
| `isStaffRole(role)` | manager/admin/superadmin |
| `ensurePhoneAuthSchema()` / `ensureStaffPinSchema()` | идемпотентные миграции (колонки phone_e164, pin_hash) |

### Троттлинг входа (C2)
`ensureLoginThrottleSchema()`, `loginThrottleKey`, `loginThrottleStatus`,
`registerFailedLogin`, `clearLoginAttempts`, `loginLockMessage` — 5 неудач по паре IP+логин → 15 мин.

### Онлайн-оплата со скидкой
`onlinePaymentSettings()` (enabled/type/value/free_ship), `onlinePaymentEnabled()`,
`onlinePaymentDiscount(subtotal)`, `onlinePaymentIncentiveLabel()`.

### Слайдер (блочный редактор)
`sliderFonts()/sliderFontStack()/sliderFontsGoogleUrl()/sliderWeights()`,
`normalizeSliderBlocks(raw)` — JSON-блоки текста слайда (размер/вес/цвет/шрифт/отступ).

### Сидеры (одноразовое наполнение, флаг в site_settings)
`seedCategorySubcategories()`, `fillMissingProductImages()`, `seedBrands()`,
`seedSliderTemplate()`, `seedSliderText()`, `seedBanners()`, `seedDemoProducts()` (42 демо-товара).

### Разное
`truncate(str,len)`, `breadcrumb(items)`, `renderBuyerAccountNav(active)`,
`paginate(countSql,dataSql,params,page,perPage)` + `paginationHtml`,
`renderRoleSidebar(active)`, **`dbAddColumnIfMissing(pdo,table,col,ddl)`** —
портируемая миграция (MySQL 8 без `IF NOT EXISTS` у колонок).

### `includes/cart_lib.php`
| Функция | Что |
|---|---|
| `cartIsGuest()` | !isLoggedIn |
| `cartRawMap(db)` | [part_id=>qty] из сессии или таблицы cart |
| `cartAdd/cartSetQty/cartRemove/cartClearAny` | операции (гость→сессия, юзер→БД; qty 1..99) |
| `cartDetailedItems(db)` | позиции + join parts (name,price,images,stock,brand) |
| `cartCountAny/cartTotalAny` | количество/сумма |
| `cartMergeGuestIntoUser(db,userId)` | слить сессию в БД при входе |
| `guestOrderUserId(db,phone,...)` | найти/создать аккаунт по телефону для заказа гостя |

### `includes/vin_service.php` — класс VinService
| Метод | Что |
|---|---|
| `validate(vin)` | 17 симв., [A-HJ-NPR-Z0-9], БЕЗ check-digit (Япония/ЕС не соблюдают) |
| `decode(vin)` | локальный WMI-разбор + провайдер (`vin_api_provider`: nhtsa/partsapi/custom), merge, кэш `vin_cache` 30 дн (версия `DECODE_VER`) |
| `searchCompatibleParts(make,model,year,catId)` | свои товары через `parts_compatibility` |
| `getCategoryFacets(...)` | фасеты категорий для фильтра |
| `getAnalogs(partId)` | явные `part_analogs` + авто (та же категория + общая машина) |
| `recordSearch/getUserHistory` | история VIN юзера (`vin_search_history`) |
| `getStats()` / `clearCache()` | статистика/сброс кэша |

### `includes/autoeuro.php` — класс AutoEuro
`fromSettings(): ?self` (null если выключен/нет ключа), `getBalance`, `getDeliveries`,
`getWarehouses`, `getPayers`, `getBrands`, `searchBrands(code)`,
`searchItems(brand, code, deliveryKey, withCrosses, withOffers)`,
`createOrder(deliveryKey, payerKey, items[], ...)`, `getOrders`, `getStatuses`.
База `https://api.autoeuro.ru/api/v2/json`, ключ в заголовке `key:`. Цены в RUB.

---

## 5. Слой каталога `includes/catalog/` (система «VIN + каталог»)

### 5.1 Идея
Провайдер каталога **сменный** и выбирается в админке (`catalog_provider`).
Фронт и эндпоинты зовут ТОЛЬКО `Catalog::provider()` — им всё равно, какой сервис под капотом.
Слой цен независим: каталог даёт OEM-номер → `Catalog::price()` даёт цену.

```
pages/vin.php + api/vin_*.php
   └── Catalog::provider()  (Manager.php, по настройке catalog_provider)
         ├── 'partspc'  → PartsCatalogsAdapter   ★ боевой сейчас (OEM + визуальные схемы)
         ├── 'partsapi' → PartsApiAdapter        (PartsAPI.ru, данные без схем)
         ├── 'laximo'   → LaximoAdapter          (каркас: HMAC ec.api, ssd-цепочка)
         ├── 'mock'     → MockAdapter            (демо без ключа)
         └── '<id>'     → GenericRestAdapter(профиль из CatalogProfiles)
   └── Catalog::price() → PriceAggregator → Warehouse → AutoEuro
```

### 5.2 `Provider.php` — интерфейс CatalogProvider (контракт)
```php
id(): string; title(): string; enabled(): bool; hasKey(): bool;
searchByVin(string $vin, bool $useCache=true): array;      // весь каталог
searchByVinCat(string $vin, $cat, bool $useCache=true): array; // один узел ($cat — СТРОКА у PC!)
oemNodes(): array;                                          // [['cat','name'],…]
crossesWithWarehouse(string $article, string $brand=''): array;
testConnection(string $vin=''): array;   // ['ok','message','count','sample']
clearCache(): void;
```
Единый формат item:
```php
['name','group','brand','part_number','in_catalog'(bool),
 'part_id'(?int),'price'(?float RUB),'stock'(?int),'url'(?string), 'pos'(№ выноски, у схем)]
```

### 5.3 `Manager.php` — фасад Catalog
`Catalog::provider()` (кэш на запрос), `Catalog::available()` (код-адаптеры + профили,
для выпадающего списка), `Catalog::make(id)`, `Catalog::reset()` (после смены настроек),
`Catalog::price(): PriceProvider`.

### 5.4 `PartsCatalogsAdapter.php` (★ активный, id `partspc`)
REST `https://api.parts-catalogs.com/`, формат сверен по клиенту `alex-ello/pc-client-slim`.

**Цепочка данных:** `v1/car/info/?q={VIN}` → `{carId, catalogId, criteria}` →
`v1/catalogs/{catalogId}/groups2?carId&criteria` (дерево узлов; листья `hasParts`,
у групп есть `img` — миниатюра) → `v1/catalogs/{catalogId}/parts2?carId&groupId&criteria`
(**схема**: `img`, `positions[{number, coordinates[x,y,w,h]}]`, `partGroups[].parts[{number=OEM, name, positionNumber}]`).

**Каскад «По параметрам»:** `pcBrands()` → `v1/catalogs/`;
`pcModels(catalogId)` → `/models`; `pcCarParams(catalogId, modelId, paramCsv)` →
`/cars-parameters` (итеративные уточнения год/кузов/двигатель);
`pcCars(...)` → `/cars2` → конкретные `{carId, criteria}` — дальше как по VIN.
`oemNodesForVin(vin)` / `oemNodesForCar(carId, catalogId, criteria, brand)`.
`schemeByVinCat(vin, cat)` → данные для `api/vin_scheme.php`.

**Авторизация** (переключается в админке `catalog_pc_auth`):
`header` = `Authorization: <ключ>` (сырой) | `bearer` | `query ?api_key=` (имя — `catalog_pc_key_param`).
**Язык**: `catalog_pc_lang` (ru/en) — шлём `Accept-Language` + `Language` + `?lang=` (best-effort;
если API игнорирует — язык переключается в аккаунте Tradesoft).
**Кэш**: таблица `partsapi_kv_cache`, префикс `pc:`, версия `CACHE_VER`; TTL: car/узлы/схемы 24ч
(тарификация PC — за VIN/сутки, повторные просмотры бесплатны), каталоги 30 дн.
Лимит: 429/`limit+exceed|quota` → `rate_limited: true` (фронт показывает сообщение).

**Обслуживание дерева (добавлено при разборе неполного каталога):**
| Метод | Зачем |
|---|---|
| `nodesLimit()` / `nodesDepth()` | лимиты обхода из настроек (было жёстко 120/4 — резало каталог вдвое) |
| `rewalkNodes(carId, catalogId, criteria, brand)` | **свежий обход БЕЗ чтения библиотеки/кэша и без записи**. Нужен пересчёту: раньше приходилось сперва удалять дерево (иначе вернётся сохранённое), и прерывание посреди обхода теряло данные |
| `storeNodes(carId, catalogId, nodes)` | сохранить готовое дерево. Пара к `rewalkNodes`: сначала обходим, потом заменяем — прерывание больше не опасно |
| `decodeVinFresh(vin)` | расшифровка VIN **мимо библиотеки и кэша**. ⚠️ ТРАТИТ 1 ЗАПРОС ТАРИФА. Единственный способ починить авто с протухшим токеном комплектации (см. § 14) |

---

### 5.5 `PartsApiAdapter.php` + `catalog_api.php` (id `partsapi`)
PartsAPI.ru: `getPartsbyVIN(vin, type, cat)` — перебор товарных групп (справочник
`partsapi_cats.php`), `getCrosses`. Формат parts: `"БРЕНД|АРТИКУЛ,БРЕНД|АРТИКУЛ"`.
Лимит демо-ключа: HTTP 401 `error_code 5000` (лимит с IP) → ранний выход, не кэшируем.
Кэш: `partsapi_catalog_cache` (по VIN) + `partsapi_kv_cache`.

### 5.6 `GenericRestAdapter.php` + `CatalogProfiles.php` (профили — провайдер БЕЗ кода)
Профиль = JSON в настройке `catalog_profiles` (правится в админке):
```jsonc
{"umapi": {"title":"UMAPI", "base_url":"https://api.umapi.ru/",
  "auth":"query|bearer|header", "key_param":"key", "timeout":12,
  "endpoints": {"parts":"?method=getParts&vin={VIN}&cat={CAT}&key={KEY}",
                 "crosses":"?method=getCrosses&art={ART}&key={KEY}"},
  "parse": {"list_path":"data.array", "mode":"objects|pairs",
             "brand_field":"brand", "article_field":"article",
             "name_field":"name", "group_field":"group",
             "parts_field":"parts", "parts_sep":",", "pair_sep":"|"},
  "nodes": ["1=Двигатель","2=Тормоза"]}}
```
Плейсхолдеры: `{VIN}{KEY}{CAT}{ART}{BRAND}{TYPE}`. Чистые функции:
`buildUrl`, `getByPath('a.b.c')`, `parseParts` (режимы objects/pairs) — тестируются без сети.

### 5.7 `LaximoAdapter.php` (id `laximo`, каркас)
Шлюз `https://ws.laximo.ru/ec.api/`. Подпись: `base64(md5(command.secret, raw))`;
логин+подпись в query, команда в POST `request`. `testConnection` шлёт
`GetListCatalogs:Locale=ru_RU` — на боевом аккаунте видно ответ. Выдача деталей
(ssd-цепочка FindVehicle→ListUnits→ListDetailByUnit) достраивается на боевом ключе;
до этого graceful-пусто.

### 5.8 `MockAdapter.php` (id `mock`)
Демо-каталог без ключа: 4 узла (Двигатель/Тормоза/Подвеска/Электрика), реальные
бренды/артикулы (MANN W712/52, BREMBO P50090…) — совпавшие со складом получают цену.

### 5.9 Слой цен
| Файл | Логика |
|------|--------|
| `PriceProvider.php` | интерфейс `priceByOem(oem, brand): ?['price'(RUB),'stock','source','delivery','part_id','url']` |
| `WarehousePriceProvider.php` | свой склад: нормализованное совпадение `part_number` (без регистра/разделителей) в `parts`, отдаёт цену/сток/ссылку на товар |
| `AutoEuroPriceProvider.php` | AutoEuro `searchItemsSmart(brand, oem)`: сперва `search_brands` (резолвит каноничные бренд+код — AutoEuro хранит код в своём формате, напр. с пробелами), затем `search_items` с `with_offers=1` (иначе цен нет). **`offersByOem()`** — список вариантов с ТОЧНЫМ совпадением кода: сперва **в наличии** (по цене), затем **под заказ** (по дате прибытия); дубли по цене+сроку схлопываются, срез до `autoeuro_offers_limit`. **`priceByOem()`** — лучший из списка. Цена в РУБЛЯХ → ×`autoeuro_rub_rate` (сомони/рубль) → ×(1+наценка) → **+ надбавка Москва→Худжанд**. Наценка: `autoeuro_markup` если задана, иначе `global_markup`. Маппинг брендов `autoeuro_brand_map`; несовпадения в лог `autoeuro_price_miss` |
| `PriceAggregator.php` | склад (без кэша, живой сток) → если пусто и `catalog_price_autoeuro=1` → AutoEuro (кэш `catalog_price_cache`: найдено 6ч / «не найдено» 1ч). `offersByOem()` — то же для списка вариантов (ключ кэша `…|offers`; свой склад = одна позиция) |

**Поиск витрины по поставщику (`offersByArticle`).** Для главного поисковика добавлен
метод **`offersByArticle(oem, maxBrands)`**: AutoEuro ищет по паре бренд+код, поэтому
сперва `search_brands($oem)` отдаёт все бренды с этим артикулом, затем по каждому —
`search_items` (без кроссов), и собирается **одна карточка на бренд** (лучшее
предложение + список вариантов для модалки). Число HTTP-запросов ограничено
`maxBrands` (по умолчанию 8) — иначе популярный артикул с десятками брендов подвесил бы
страницу. Разбор предложений вынесен в общий `buildOffers()` (его же использует
`offersByOem`). Карточки сортируются: сперва наличие, затем по цене.

**Словарь названий `ae_part_dictionary` (поиск по НАЗВАНИЮ).** API AutoEuro ищет только
по артикулу — по слову «масло» он ничего не найдёт. Поэтому названия хранятся **у себя**:
- `rememberName(oem, brand, name)` — **ленивое обучение**: имя берётся из ответа, который
  и так пришёл при поиске по артикулу. Отдельных запросов ради имён нет → бана нет.
- `dictionarySearch(q, limit, offset)` — поиск **по нашей БД**, совпадение **по словам** в
  любом порядке («тормозные колодки» находит «Колодки Тормозные Дисковые»). Быстрый путь —
  `FULLTEXT MATCH … AGAINST` в boolean-режиме (`+слово*`), фолбэк — LIKE, если индекса нет.
  `offset` — для кнопки «Показать ещё» (8 → 16 → 24 …).
- **Цена в словаре НЕ хранится** — она всегда живая из API (`vin_price.php`).
- Массовое наполнение — из **прайс-листа AutoEuro** (см. § 16.4), а не перебором API.

**Доставка Москва→Худжанд.** В цене AutoEuro заложена доставка только **склад→Москва**
(отдельного поля стоимости доставки в API нет — проверено `get_warehouses`; разброс цен
между предложениями = разные склады). Логистику Москва→Худжанд добавляем сами по
настройкам `autoeuro_khj_*`: тумблер, надбавка **суммой или процентом**, и **дни** к сроку.
Покупателю показывается итог **до Худжанда**: `цена + надбавка`, `дата AutoEuro + наши дни`.

---

## 6. Контракты AJAX-эндпоинтов `api/vin_*`

Все проверяют `Catalog::provider()->enabled()` → иначе `{success:false, error:'disabled'}`.

### `vin_nodes.php`
`GET ?vin=<17>` ИЛИ `?carId=&catalogId=&criteria=&brand=` (режим «по параметрам»)
→ `{success, count, nodes:[{cat, name, img}]}` (`img` — миниатюра схемы узла или '').

### `vin_scheme.php`
`GET ?vin=&cat=<groupId>` (+ carId/catalogId/criteria в режиме параметров)
→ `{success, enabled, rate_limited, img, caption, hotspots:[{n,x,y,w,h}],
    parts:[{name, group, brand, part_number, pos, in_catalog, part_id, price(отформатир.), stock, url}]}`
Хотспоты в ПИКСЕЛЯХ исходной картинки; фронт пересчитывает в % по naturalWidth/Height.
Связь: `hotspots[].n == parts[].pos`.

### `vin_catalog.php`
`GET ?vin=&cat=<id>` (один узел) или без cat (полный перебор)
→ `{success, count, cat, groups_scanned, errors, rate_limited, type, from_cache, items:[…]}`.

### `vin_params.php`
`GET ?step=brands` → `{items:[{id,name}]}`;
`?step=models&catalogId=` → `{items:[{id,name,img}]}`;
`?step=carparams&catalogId=&modelId=&parameter=idx1,idx2` → `{items:[{key,name,values:[{idx,value}]}]}`;
`?step=cars&...&parameter=` → `{items:[{carId,catalogId,criteria,name,modelName,brand}]}`.

### `vin_price.php`
`GET ?oem=&brand=` → `{success, found, price(строка в валюте), price_raw, stock, source('warehouse'|'autoeuro'), delivery, part_id, url,`
`offer_mode('A'|'B'), options:[{price, price_raw, stock, in_stock, delivery_ae, delivery_days, delivery_total, source}]}`.
Плоские поля — лучший вариант (обратная совместимость), `options[]` — все варианты для
выбора покупателем. `delivery_ae` — прибытие в Москву, `delivery_total` — в Худжанд.
`offer_mode`: `A` — показывать только итоговый срок, `B` — с разбивкой.

### `vin_order_request.php`
`POST {_csrf, oem, brand, name, qty, price, delivery_total, delivery_ae, delivery_days, in_stock, comment}`
→ `{success, message, messages_url}` либо `{redirect}` (если не авторизован).
Заявка на деталь поставщика с выбранным вариантом доставки: у деталей AutoEuro нет
`part_id`, поэтому обычная корзина неприменима — заявка уходит **менеджеру в переписку**
(`includes/messaging.php`, ветка поддержки) + подтверждение покупателю. Требует входа.

### `vin_crosses.php`
`GET ?article=&brand=` → `{success, count, rate_limited, from_cache,
 items:[{brand, part_number, is_original, in_catalog, part_id, price, stock, url}]}`.

### `cart.php`
GET `?action=count|mini|remove(+part_id,_csrf)`;
POST JSON/form `{action:add|remove|update|count, part_id, quantity, _csrf}`
→ `{success, cart_count, cart_total, cart_total_html, message}` (mini добавляет items_html).
CSRF обязателен на мутациях. Работает для гостя.

---

## 7. Фронтенд `pages/vin.php` — карта одного файла

**PHP-верх (строки ~1–120):** `Catalog::provider()->enabled()`, `$pcProvider`
(`catalog_provider==='partspc'`), `$pcSchema` (`catalog_pc_schema`); декод VIN →
`$result`; режим «по параметрам» (`?carId&catalogId&criteria&carName` → `$carMode`);
`getCarImageUrl()` — фото авто (Wikipedia для СНГ-брендов).

**Секции HTML (по порядку):** промо-баннер → hero → карточка поиска
(`.vx-scard`, табы `#vx-t-vin`/`#vx-t-params`, панели `#vx-p-vin`/`#vx-p-params`;
в параметрах: курируемый `.vx-params` + живой `#vxPcCascade`) → результат
(карточка авто, совместимые со склада, **каталог `#vinCatalog`**) → популярные марки →
«Как это работает» → эксперт (WhatsApp/Telegram из настроек) → trust.

**Блок каталога `#vinCatalog`:** `.vin-cat7` = сайдбар `#vinNodeList` (sticky) +
`.vin-cat7-main` с двумя состояниями: `#vinNodeGrid` (карточки узлов с миниатюрами)
↔ `#vinNodeView` (кнопка «← Все узлы», `#vinNodeTitle`, панель схемы `#vinSchemePanel`
c `#vinSchemeImg`+`#vinSchemeHot`, статус `#vinCatalogStatus`, детали `#vinCatalogBody`).

**JS-функции (все):**
| Функция | Что |
|---|---|
| `vxTab(t)` | переключение табов VIN/параметры |
| `vxFillExample` / `vxOnMake` / `vxOnModel` / `vxParamsSubmit` / `vxPickBrand` | курируемый фуннел параметров (когда нет живого каскада) |
| IIFE «Живой каскад» (~стр. 800) | самоактивация: `step=brands` вернул марки → прячет курируемый, строит селекты Марка→Модель→уточнения→карточки авто (ссылки `?carId=...`) |
| `vinLoadPcNodes()` | тянет `vin_nodes.php`, строит сайдбар + сетку карточек (миниатюра `img`/SVG-плейсхолдер `VIN_NODE_PH`) |
| `vinLoadNode(cat, btn)` | активная карточка + `vinShowView(имя)` + (схема? `vinLoadScheme` : `vinCatalogFetch('&cat=')`) |
| `vinShowView(title)` / `vinBackToNodes()` | смена контента НА МЕСТЕ (grid↔view) + прокрутка к началу каталога |
| `vinLoadScheme(cat)` → `vinRenderScheme(d)` | fetch схемы; хотспоты px→% (`vinScaleHot`), клик по точке — скролл к карточке |
| `vinCatalogFetch(extra)` → `vinRenderCatalog(d)` | режим без схем (PartsAPI/Mock/профили) |
| `vinBuildPartsHtml(items)` | карточки деталей: бейдж №, **`.vin-pos-box`** (кликабельный номер вместо фото — у OEM фото деталей НЕТ), цена/«под заказ», «В корзину»/«Найти в каталоге», кнопка кроссов |
| `vinExpandAbbr(s)` | расшифровка сокращений: К-т→Комплект, Компл.→Комплект, доосн.→дооснащения, нерж.→нержавеющей, Облиц.→Облицовка, солнцезащ.→солнцезащитные |
| `vinFillPrices(scope)` | ленивые цены: `.vin-price-ph[data-oem]` → `vin_price.php` → красный ценник + «склад/поставщик · N дн» |
| `vinCrosses(article, brand, btn, cid)` | разворот кроссов под карточкой |
| `vinHi(pos, on)` / `vinFocusPos(pos)` | подсветка «хотспот ↔ карточка» / скролл к схеме+мигание |
| `vinAddToCart(partId, btn)` | добавление в корзину (работает у гостя) |
| `vinCtxQuery()` | добавляет carId/catalogId/criteria к запросам в режиме «по параметрам» |
| `escapeHtml/jsAttr/vinCssEsc` | экранирование |

**Ключевые CSS-классы:** `.vx .container{max-width:min(1560px,94vw)}`;
`.vin-cat7-side` (250px, sticky); `.vin-node-grid` (minmax 185px); `.vin-node-thumb` (118px);
`.vin-scheme-box img{max-height:70vh;object-fit:contain}`; `.vin-pcard`, `.vin-price`,
`.vin-cart`, `.vin-pos-box`, `.vin-hot` (хотспот), `.hot` (подсветка).

**Глобальные JS-флаги:** `VX_PC`, `VX_PC_SCHEMA`, `VX_PRICE_LAZY`, `VX_MAKES`, `VX_CAR_CTX`.

---

## 8. Все настройки `site_settings` (по группам)

### Каталог: выбор провайдера
| Ключ | Значения / смысл |
|---|---|
| `catalog_provider` | `partspc` \| `partsapi` \| `laximo` \| `mock` \| id профиля |
| `catalog_api_enabled` | '1'/'0' — общий тумблер каталога |
| `catalog_api_type` | `oem` (оригинал) \| '' (аналоги) — для PartsAPI |
| `catalog_api_oem_nodes` | построчно `ID=Название` — общий справочник узлов (фолбэк) |
| `catalog_profiles` | JSON профилей REST-провайдеров (GenericRestAdapter) |

### PartsAPI
`catalog_api_key`, `catalog_api_base` (https://api.partsapi.ru/), `catalog_api_max_groups` (0=все 751), `catalog_api_timeout`.

### Parts-Catalogs (Tradesoft)
`catalog_pc_key`, `catalog_pc_base` (https://api.parts-catalogs.com/), `catalog_pc_timeout` (20),
`catalog_pc_auth` (header|bearer|query), `catalog_pc_key_param` (api_key|auth_key),
`catalog_pc_schema` ('1' — показывать взрыв-схемы), `catalog_pc_lang` (ru|en).

### Laximo
`catalog_laximo_login`, `catalog_laximo_secret`.

### Цены
`catalog_price_autoeuro` ('1' — фолбэк цен из AutoEuro), `global_markup` (% наценки по умолчанию).

### AutoEuro
`autoeuro_enabled`, `autoeuro_api_key`, `autoeuro_delivery_key` (пункт получения — брать московский, он агрегирует все склады), `autoeuro_payer_key` (только для заказа), `autoeuro_rub_rate` (сомони за 1 рубль, по умолч. 0.11), `autoeuro_markup` (наценка % на товары AutoEuro; пусто → `global_markup`), `autoeuro_brand_map` (строки «ИСХОДНЫЙ = Замена» при расхождении названий брендов). Включение цен на витрине — `catalog_price_autoeuro` (стр. VIN-настроек).

### AutoEuro — доставка Москва→Худжанд и показ вариантов
В API AutoEuro доставки до Худжанда нет (цена покрывает только склад→Москва), поэтому:
`autoeuro_khj_enabled` (тумблер надбавки), `autoeuro_khj_mode` (`sum` — сумма в сомони /
`percent` — процент), `autoeuro_khj_value` (значение), `autoeuro_khj_days` (дни к сроку).
Показ покупателю: `autoeuro_offer_mode` (`A` — только итог срока / `B` — с разбивкой
«Москва + N дн → Худжанд»), `autoeuro_offers_limit` (сколько вариантов показывать, деф. 3).
Все — Суперадмин → Склад; сохранение сбрасывает `catalog_price_cache`.

### Каталог: полнота дерева узлов
`catalog_nodes_limit` — максимум узлов на авто (по умолчанию **300**, потолок 2000),
`catalog_nodes_depth` — максимальная глубина обхода (по умолчанию 5).
Раньше эти значения были **жёстко зашиты (120 и 4)**, из-за чего у «богатых» авто
каталог резался почти вдвое — замер `pc_tree_probe.php`: у Lexus **229** реальных
узлов, сохранялось 120. Форма — Суперадмин → VIN-поиск → «Полнота дерева узлов».
⚠️ Каждая ветка дерева = отдельный запрос к API (обход Lexus стоил 163 запроса ≈ 80 сек).

### Маркетплейс
`marketplace_commission` — комиссия площадки по умолчанию (%) для новых магазинов;
читается в `auth/register.php` и подставляется в `sellers.commission_percent` при создании
магазина, дальше редактируется персонально в Админ → Продавцы.
⚠️ **Отдельной формы для этой настройки в админке пока нет** — значение правится прямо в
`site_settings` (по умолчанию `0`). Значение копируется в `order_sellers.commission_percent`
в момент заказа и оттуда попадает в баланс продавца (§ 16.6).

### VIN-декодер
`vin_search_enabled`, `vin_api_provider` (nhtsa|partsapi|custom), `vin_api_url`
(шаблон c `{VIN}`/`{KEY}`), `vin_api_key`, `vin_api_timeout`.

### Аутентификация / SMS
`auth_email_enabled` (тумблер email-входа), `sms_provider` (пусто = тест-режим, `osonsms` = боевая отправка), `sms_osonsms_login`/`sms_osonsms_hash`/`sms_osonsms_sender`/`sms_osonsms_server` (реквизиты OsonSMS), `phone_countries` (вкл. страны).

### Онлайн-оплата
`online_payment_enabled`, `online_discount_type` (percent|fixed), `online_discount_value`, `online_free_shipping`.

### Сайт/контакты/SEO/карта
`site_name`, `site_phone`, `site_email`, `site_address`, `site_whatsapp`, `site_telegram`,
`site_facebook`, `site_instagram`, `site_youtube`, `site_tiktok`,
`meta_description`, `meta_keywords`, `map_lat/lng/zoom`, `contact_intro`,
`slider_interval_sec`, тексты отзывов `review_msg_*`.

### Служебные флаги сидеров (не трогать)
`banners_seed_done`, `brands_seed_done`, `cat_subseed_v2`, `demo_products_v1`,
`prod_imgseed_done`, `slider_photos_template_v1`, `slider_text_v1`,
`phone_auth_schema_v1`, `staff_pin_schema_v1`, `login_throttle_schema_v1`, `order_discount_col_v1`.

---

## 9. База данных — все таблицы

### Основные (`sql/schema.sql`)
| Таблица | Колонки |
|---|---|
| `users` | id, username, email, password_hash, role(buyer/admin/manager/superadmin), phone, created_at, updated_at, is_active. +миграции: phone_e164, pin_hash, first/last_name, address, city, zip_code, country |
| `parts` | id, part_number, name, description, brand_id, category_id, price(RUB), old_price, stock, weight, dimensions, images(JSON), is_active, created_by, created_at, updated_at. +cost_price, markup_percent |
| `categories` | id, name, slug, parent_id, description, image_path(+_mobile), sort_order, is_active, markup_percent |
| `brands` | id, name, slug, logo_path, country, description, is_active, sort_order |
| `cart` | id, user_id, part_id, quantity, added_at (UNIQUE user+part) |
| `orders` | id, user_id, status(enum), total_amount, discount_amount, shipping_cost, shipping_address(JSON-текст), notes, payment_method, created_at, updated_at |
| `order_items` | id, order_id, part_id, quantity, unit_price |
| `delivery_zones` | id, city, country, cost, delivery_days, is_active, sort_order |
| `site_settings` | id, key(UNIQUE), value, updated_at |

### Из миграций (sql/*.sql)
| Таблица | Откуда / зачем |
|---|---|
| `wishlist`, `currencies`, `languages`, `blog_posts`, `warehouse_api_log`, `parts_i`, `categories_i` | schema_v2 (избранное, валюты, языки, блог, лог AutoEuro, i18n-таблицы) |
| `backups` | schema_v3 (реестр SQL-бэкапов) |
| `sliders` | schema_v4 (+text_blocks JSON, title_highlight) |
| `car_models`, `parts_compatibility`, `vin_cache` | migrate_vin (свои авто, совместимость, кэш декода) |
| `vin_search_history`, `part_analogs` | migrate_vin_v2 |
| `site_sections` | migrate_cms (контент страниц about/contact/faq) |
| `product_reviews`, `shop_reviews` | migrate_reviews / v2 |
| `phone_otp` | add_phone_auth (SMS-коды) |
| `banners` | (сидер) баннеры с placement |
| `user_permissions` | migrate_permissions (персональные разделы) |
| `login_attempts` | рантайм (троттлинг входа) |
| `messages` | рантайм (чат покупатель↔менеджер: user_id, order_id, sender, body, read-флаги) |
| `warehouse_api_log` | лог тестов AutoEuro + `autoeuro_price_miss` (несовпадения цен) |

### Рантайм-кэши (создаются кодом, `CREATE TABLE IF NOT EXISTS`)
| Таблица | Кто пишет | Ключи |
|---|---|---|
| `partsapi_catalog_cache` | CatalogApi (PartsAPI, по VIN) | vin PK, result JSON, cached_at |
| `partsapi_kv_cache` | PC-адаптер и др. | k(96) PK: `pc:car:*`, `pc:nodes:*`, `pc:parts:*`, `pc:scheme:*`, `pc:brands:*`, `pc:models:*`, `pc:cparams:*`, `pc:cars:*`, `cr:*`, `g:*` |
| `catalog_price_cache` | PriceAggregator (AutoEuro) | ck PK (oem\|brand), result, cached_at |
| `ae_part_dictionary` | `AutoEuroPriceProvider::rememberName()` (лениво) + импорт прайса `superadmin/ae_dict_import_csv.php` | `oem_key` (нормализованный артикул) + `brand` — UNIQUE; `oem`, `name`, `source` ('search'/'price'), `hits`; `FULLTEXT(name)`. **Только названия, без цен** |

### Маркетплейс (`sql/marketplace_phase1.sql`, применяется один раз)
| Таблица / колонка | Что |
|---|---|
| `users.role` | добавлено значение **`seller`** (было buyer/admin/manager/superadmin) |
| `sellers` | магазин продавца: `user_id` (UNIQUE), `shop_name`, `slug` (UNIQUE), `phone`, `description`, `logo`, `status` ENUM('pending','approved','blocked'), `commission_percent`, `reject_reason` |
| `parts.seller_id` | NULL = товар нашего каталога (виден как раньше); иначе — товар продавца |
| `parts.moderation_status` | ENUM('draft','pending','active','rejected'), **DEFAULT 'active'** — чтобы существующие товары остались видимыми |
| `parts.reject_reason` | причина отклонения листинга (показывается продавцу) |

### Маркетплейс, Фаза 2 (`sql/marketplace_phase2.sql`)
| Таблица / колонка | Что |
|---|---|
| `order_sellers` | **подзаказ на каждого продавца внутри заказа**: `order_id`, `seller_id` (NULL = наш каталог), `status`, `subtotal`, `commission_percent`, `commission_amount`, `payout_amount`. Ставка комиссии копируется В МОМЕНТ ЗАКАЗА — поздняя правка ставки не переписывает историю |
| `order_items.seller_id` | продавец позиции (копия из `parts` на момент заказа) |
| `order_items.order_seller_id` | к какому подзаказу относится позиция |

> Все миграции идемпотентны: `dbAddColumnIfMissing()` / `CREATE TABLE IF NOT EXISTS` — безопасно для MySQL 8.

---

## 10. Роли и доступ

| Роль | Разделы |
|---|---|
| `buyer` | кабинет buyer/ (заказы, профиль, избранное, корзина) |
| `seller` | кабинет seller/ (свой магазин, свои товары). Гейт `requireSeller()`; добавлять товары можно только при `sellers.status='approved'`. Может загружать фото (`api/upload.php?type=products`) |
| `manager` | контент: товары/категории/бренды/блог/страницы/отзывы (manager/) |
| `admin` | + товары/наценки/слайдеры/заказы/пользователи (admin/) |
| `superadmin` | всё + настройки/VIN/каталог/валюты/доставка/права/бэкапы (superadmin/) |

Точечные права: `permissionSections()` (ключи: products, markup, sliders, orders,
users, categories, brands, blog, pages, reviews, vin, settings, …) →
`userCan('vin')` / `requirePermission('vin')`. Персональные наборы — `user_permissions`
(редактор — superadmin/permissions.php). Вход сотрудника: телефон + PIN
(`pin_hash`), либо email если `auth_email_enabled=1` (`?staff=1`).

---

## 11. Внешние сервисы (сводно)

| Сервис | Роль | Файл | Доступ |
|---|---|---|---|
| **Parts-Catalogs / Tradesoft** | OEM-каталог + визуальные схемы (боевой) | `PartsCatalogsAdapter.php` | ключ `catalog_pc_key`; тариф — за VIN/сутки |
| PartsAPI.ru | данные (детали по VIN, кроссы 428 млн) | `catalog_api.php` | `catalog_api_key`+`vin_api_key`; демо 50 req/сутки/IP (`error_code 5000`) |
| Laximo | оригинал (каркас) | `LaximoAdapter.php` | логин+секрет |
| NHTSA | бесплатный VIN-декод (US) | `vin_service.php` | без ключа |
| AutoEuro | цены/наличие поставщика (боевой, на витрине VIN) | `autoeuro.php`, `AutoEuroPriceProvider.php` | `autoeuro_api_key`+delivery_key+`catalog_price_autoeuro`; RUB→сомони по `autoeuro_rub_rate`; `offersByOem()` — варианты с выбором срока + надбавка/дни Москва→Худжанд (`autoeuro_khj_*`); покупатель оформляет **заявку менеджеру** (`api/vin_order_request.php`). Автозаказ (`create_order`) — не подключён (денежная ветка) |
| SMS | коды входа | `sendSms()` | боевой шлюз **OsonSMS** (`sms_provider=osonsms`); пусто = ТЕСТ-режим (лог) |

---

## 12. Быстрый индекс: «хочу изменить X → файл Y»

| Задача | Где |
|---|---|
| Вид страницы VIN (дерево/схема/карточки/ширина) | `pages/vin.php` (CSS в `<style>` блока каталога, JS внизу) |
| Добавить REST-провайдер каталога БЕЗ кода | админка → `catalog_profiles` JSON (движок `GenericRestAdapter.php`) |
| Parts-Catalogs: язык/авторизация/парсинг/кэш | `includes/catalog/PartsCatalogsAdapter.php` |
| PartsAPI: перебор групп/кроссы/лимит | `includes/catalog_api.php` |
| Логика цен (склад/AutoEuro/наценка/TTL) | `includes/catalog/PriceAggregator.php`, `*PriceProvider.php` |
| Формат ответа эндпоинта | `api/vin_*.php` (тонкие, вся логика в адаптерах) |
| Декод VIN (WMI, годы, провайдеры) | `includes/vin_service.php` |
| Корзина/гость/слияние | `includes/cart_lib.php`; UI — `buyer/cart.php`, `api/cart.php` |
| Оформление заказа/доставка/оплата | `buyer/checkout.php`; зоны — `superadmin/delivery.php` |
| Вход/регистрация/SMS/PIN/троттлинг | `auth/login.php`, `auth/register.php`, `functions.php` (SMS/OTP/throttle) |
| Настройки каталога в админке (форма) | `superadmin/vin.php` |
| Общие настройки/соцсети/оплата/SEO | `superadmin/settings.php` |
| HTTP к внешнему API | `httpGet()` в `includes/functions.php` |
| Наценка на товар | `getEffectiveMarkup()`; поля parts.markup_percent / categories.markup_percent / `global_markup` |
| Переводы интерфейса | `lang/ru.php`, `lang/tg.php`, `lang/en.php` + `t()` |
| Валюта/курс | `includes/currency.php`, таблица `currencies`, `superadmin/currencies.php` |
| ЧПУ товара | `partUrl()`, `.htaccess`, роутер в корневом `index.php`, canonical в `catalog/part.php` |
| Слайдер главной | `admin/sliders.php` (+`normalizeSliderBlocks`), рендер в корневом `index.php` |
| Баннеры (placement) | `admin/banners.php`, вывод в `catalog/index.php` |
| Роли/права/разделы | `functions.php` (permission*), `superadmin/permissions.php` |
| Бэкапы | `superadmin/backup.php`, `_backup_lib.php`, cron — `backup_cron.php` |
| Новая колонка/таблица | `sql/` + `dbAddColumnIfMissing()` (идемпотентно) |
| Демо-режим каталога (без ключа) | админка: провайдер «Демо»; код — `MockAdapter.php` |
| Блок «Найдено у поставщика» на поиске | `includes/parts/supplier_cards.php` (+ рендер карточки `supplier_card_render.php`) |
| Логика «что искать в AutoEuro» (артикул vs название) | `search/index.php` — детектор `$looksLikeArticle` |
| Поиск по названию / словарь | `AutoEuroPriceProvider::dictionarySearch()`; наполнение — `superadmin/ae_dict_import_csv.php` |
| Кабинет продавца / его товары | `seller/*.php`, гейт — `includes/seller.php` |
| Модерация продавцов / листингов | `admin/sellers.php` / `admin/product_moderation.php` |
| Пункты меню админки (+бейджи) | `renderRoleSidebar()` в `includes/functions.php` |

---

## 13. Документы репозитория
- **`ARCHITECTURE.md`** — этот файл (полная карта).
- **`CATALOG_PLAN.md`** — план/этапы универсального каталога (все 5 этапов ✅) + поиск по названию.
- **`NAVIGATION.md`** — full-stack карта + история PR.
- **`README.md`** — установка, деплой, changelog всех PR, разделы «как работает».
- **`CHANGES.md`** — краткая сводка изменений.

## 14. Известные ограничения / «остаётся»
- SMS — боевой шлюз OsonSMS подключён (`sendSms()`→`osonSmsSend()`); тест-режим (лог) остаётся, если провайдер не выбран в настройках.
- **AutoEuro — цены на витрине работают** (`catalog_price_autoeuro=1`, ключ+delivery_key+курс). Не подключено: **автозаказ** у поставщика (`create_order`) — денежная ветка, под явным решением; сейчас закупка — вручную через «Заявку поставщику» на карточке заказа. Владелец держит актуальными `autoeuro_rub_rate` (курс рубля), `autoeuro_markup` (наценка), `autoeuro_khj_*` (доставка Москва→Худжанд).
- Онлайн-оплата фиксирует способ и скидку; реальный платёжный шлюз — отдельная интеграция (нужен договор эквайринга с банком Таджикистана).
- Laximo — каркас (ssd-выдача деталей достраивается на боевом аккаунте).
- У OEM-каталогов НЕТ фото отдельных деталей (только взрыв-схема) — это свойство данных, на карточке показывается кликабельный номер-выноска.
- Часть названий узлов может приходить на en/de — непереведённые позиции в данных Tradesoft (лечится на их стороне).
- Локализация ru/tg/en: каркас VIN-страницы через `t()`, часть текстов блока результатов — на русском.
- **Фото деталей поставщика.** API AutoEuro фото **не отдаёт** (в ответе только коммерческие
  поля), и метода выгрузки картинок нет. На карточках поставщика — фото из нашего каталога,
  если артикул совпал, иначе заглушка. Реальные фото по всему рынку = только каталог
  TecDoc-класса (платная лицензия) либо поставщик со встроенным каталогом (Emex/Autopiter).
- **Поиск по названию** работает по НАШЕМУ словарю (`ae_part_dictionary`): найдётся только
  то, что импортировано из прайса/накоплено. По названию сам API AutoEuro не ищет.
- **Маркетплейс — Фазы 1, 2 и 3a готовы.** Продавцы, их товары, модерация, витрина
  магазина, **заказы с разбивкой по продавцам** (подзаказы, статусы, комиссия),
  **балансы и реестр выплат** (§ 16.6). Не сделано: онлайн-оплата (Фаза 3b, упирается
  в эквайринг), отзывы о продавцах, возвраты/споры, **buy-box** (одна деталь —
  предложения нескольких продавцов; мешает `parts.part_number UNIQUE` по всей базе).
- **Выплаты продавцам не автоматические** — владелец переводит деньги вне сайта и
  отмечает факт в реестре. Автоматизация требует банковского API, как и Фаза 3b.
- **⚠️ Тариф Parts-Catalogs — правила расхода НЕ ПОДТВЕРЖДЕНЫ.** Договор (п. 1.2.2)
  определяет «запрос» как обращение к каталогам **по VIN**, и на этом основании
  считалось, что обходы дерева (`groups2`) и схемы (`parts2`) лимит не тратят. **Практика
  это опровергла**: при пакете 1000 пришло предупреждение «израсходовано 999», и сбор
  схем получил отказ по лимиту. Что именно считается — **выясняется у Tradesoft**.
  До ответа массовые прогоны не запускать. При исчерпании услуга **автоматически
  отключается до 1 числа** (п. 2.4) — витрина при этом работает, VIN-каталог отдаётся
  из библиотеки (см. § 17).
- **Токен комплектации протухает.** В `criteria` Parts-Catalogs зашивает временный токен
  (`17*WA1VFAF82HA000123(2017!31a3cee7`). После протухания `groups2` отвечает
  **404 «Комплектация не найдена»** — и с `criteria`, и без него, а `cars2` требует
  `modelId`. Единственное лечение — новая расшифровка VIN (`decodeVinFresh`, режим
  `revin`), стоит 1 запрос тарифа на авто. Практический вывод: **собирать схемы лучше
  сразу после VIN-поиска, пока токен свежий**.

## 15. CSS-фронтенд: ключевые «ловушки» темы Mazlay

Файл `assets/css/custom.css` (~3200 строк, cache-bust по `filemtime`, UTF-8) — все
переопределения темы. Тема Mazlay склонна к нестандартным приёмам, о которых
здесь напоминания на будущее.

**Что тема прячет и по наведению выдвигает:**
- `.action_links` карточки товара — `position: absolute; visibility: hidden`, при
  hover `bottom: -55px`. На тач-экранах недоступно, `overflow: hidden` карточки-плитки
  всё обрезает. Наше правило `.shop_wrapper .single_product .action_links` возвращает
  кнопку в **обычный поток**, всегда видимую. Сердечко избранного — плавающая иконка
  `.wishlist_float` на самом фото (тема хочет всё внизу).
- `.quick_button` (иконка «глаз» быстрого просмотра) — тоже hover-выдвижение. Скрыт
  через `.shop_wrapper .single_product .quick_button { display: none }` (выглядел как
  всплывающее окошко поверх фото).

**Ловушки форм поиска (`.search_container form`):**
- Тема на `≤991px` ставит `flex-direction: column-reverse; align-items: center` — вторая
  форма (VIN) переворачивалась (кнопка над полем). Возвращаем `row + stretch`.
- Кнопка `.search_box button` у темы — `position: absolute; top: 0; height: 100%;
  min-width: 128px`, а инпут имеет `padding-right: 145px`. При фиксированной ширине
  контейнера видимая часть текста схлопывалась (было заметно, когда рядом стояло VIN-поле).
- Мобильный поиск: сегментированный переключатель `.msearch_tabs` показывает одно поле
  за раз (иначе два поля подряд не влезали). На десктопе оба поля видны — переключатель
  скрыт.

**Ловушки sticky/scroll:**
- Тема сама вешает `.sticky` на `.header_bottom.sticky-header` при `scroll > 100px`
  (`mazlay-js/main.js:20`). Наш `.sticky_search` показывается только когда шапка прилипла.
- `overflow-x: clip` у `.shop_area` на мобильном — не ломает `position: sticky` сайдбара,
  т.к. правило только в мобильном медиа-запросе.

**Ловушки текста в тёмной шапке:**
- `.header_top a { color: #e8e8e8 !important }` красит **все** ссылки — включая
  пункты `.dropdown_language` на белом фоне. Явно возвращаем `#222` для выпадающих
  панелей (`.dropdown_language li a`, `.dropdown_currency`, `.dropdown_links`).

**Карточка каталога — как удержать равную высоту без «зазора перед кнопкой»:**
- НЕ растягивать `.single_product` на высоту ряда (`.row` — `align-items: flex-start`).
- НЕ прижимать `.action_links` к низу через `margin-top: auto` — обычный `padding-top`.
- НЕ резервировать `min-height` под цену/название/рейтинг (при отсутствии растяжки они
  становятся пустотой).
- Секрет равного размера: **название 2 строки** (`line-clamp`), **цена ВСЕГДА одна
  строка** (старая+новая в ряд, а не столбиком — иначе карточки со скидкой выше). Все
  секции одинаковы → карточки равны сами по себе.

**Промо-карточка «Товары со скидкой» (`index.php`):**
- Отдельный класс `.promo_deal` (не общее правило `.single_product`), т.к. у неё
  своя раскладка (фото слева, текст справа) и `flex-direction: row` — конфликтует
  с колонкой обычных карточек.

**Особенности `.grid_content` / `.list_content`:**
- Тема выводит **оба** блока и скрывает лишний через
  `.product_content.list_content { display: none }`. Специфичность 0,2,0. Наше правило
  `.single_product .product_content { display: flex }` имеет ту же силу и грузится
  позже — перебивало скрытие, содержимое **дублировалось**. Исключаем оба варианта
  через `:not(.grid_content):not(.list_content)` там, где нужен flex.

**UTF-8 для статики (`.htaccess`):**
- `AddCharset UTF-8 .css .js` — иначе браузер читает `custom.css` в системной кодировке
  и русские комментарии выглядят как «РҺРІС‚Рѕ…». На работу стилей не влияет, только
  на читаемость. Плюс `@charset "UTF-8";` первой строкой файла как fallback.

**`.action_links` темы — абсолютный и скрыт до наведения:**
- Тема позиционирует `.action_links` абсолютно (`bottom: -55px`, показ по hover). В
  карточках с `overflow: hidden` (например `.sup-card` поставщика) кнопка обрезалась
  до тонкой полоски. Лечение — вернуть в поток и сделать видимой явно:
  `position: static !important; opacity: 1 !important; visibility: visible !important;
  transform: none !important; bottom: auto !important`.

**Цвет ссылок «прилипшей» шапки протекает в выпадашки:**
- `.sticky-header.sticky .main_menu nav ul li a { color: #e8e8e8 }` красит **все**
  ссылки внутри меню — включая пункты мега-меню на белом фоне: при прокрутке текст
  подкатегорий становился почти белым и «сливался». Лечение — явные тёмные цвета с
  `!important` для `.az-megamenu__head/__sub/__list/__footer` **в обоих состояниях**
  (обычном и `.sticky-header.sticky …`), т.к. правило темы идёт без `!important`.

**Мега-меню: две разные разметки — не перепутать:**
- `.categories_menu_toggle` (простое меню категорий) и **`.az-megamenu`** (широкая
  выпадашка «МАГАЗИН» в `includes/header.php`: `__inner`/`__col`/`__group`/`__head`/
  `__sub`/`__footer` + промо-колонка). Правки, написанные для первого селектора, ко
  второму **не применяются** — сначала проверить, какая разметка на странице.
- Высота под вьюпорт: ограничивать `.az-megamenu--wide .az-megamenu__inner`
  (`max-height` + `overflow-y: auto`), причём **по-разному** для двух состояний —
  вверху страницы полоса меню стоит ~180px ниже (`calc(100vh - 210px)`), а в прилипшем
  состоянии она у самого верха (`calc(100vh - 80px)`).

**`overflow-x: hidden` на `html, body` ломает прилипающую шапку:**
- Защита от горизонтального вылета нужна и на десктопе (раньше стояла только в
  мобильной медиа-выборке), но `overflow-x: hidden` на корне **создаёт scroll-контейнер**
  — и `position: fixed` шапка (класс `.sticky` от темы) перестаёт появляться при
  прокрутке. Правильно — **`overflow-x: clip`**: обрезает вылет, но контейнер прокрутки
  не создаёт.

**Owl-карусель обрезает увеличенные карточки:**
- У `.owl-stage-outer` стоит `overflow: hidden`, поэтому hover-эффект `scale()` на
  карточках главной обрезается по краям. На главной — только тень/рамка без `scale`
  (+ `margin: 2px 4px` у карточки внутри `.owl-item`, чтобы тень не срезалась).
  `overflow: visible` ставить нельзя — ломается пагинация карусели.

---

## 16. Маркетплейс (мультипродавец) — Фазы 1–3a

Цель — площадка, где **сторонние продавцы выкладывают свои товары** (модель Ozon/WB),
в нише автозапчастей. **Фазы 1, 2 и 3a завершены**: продавец регистрируется, выкладывает
товар, проходит модерацию, получает заказ, ведёт его статус и видит, сколько ему должны;
владелец рассчитывается по реестру выплат. Осталась Фаза 3b — приём денег онлайн.

### 16.1 Сущности и поток
```
Регистрация (Покупатель | Продавец)
        └─ Продавец → users.role='seller' + sellers(status='pending')
                └─ Админ → Продавцы → Одобрить (status='approved')
                        └─ Кабинет /seller/ → добавить товар
                                 → parts(seller_id, moderation_status='pending')
                                        └─ Админ → Модерация товаров → Одобрить
                                                 → moderation_status='active'
                                                        └─ Товар в каталоге/поиске,
                                                           на карточке «Продавец: …»
```

### 16.2 Файлы
| Файл | Роль |
|---|---|
| `sql/marketplace_phase1.sql` | миграция (роль `seller`, таблица `sellers`, колонки `parts`) — **только добавления**, существующие данные не трогает |
| `includes/seller.php`, `includes/seller_nav.php` | хелперы кабинета + навигация |
| `seller/index.php`, `seller/products.php`, `seller/product_edit.php` | кабинет продавца |
| `admin/sellers.php` | модерация продавцов (одобрить/блок/комиссия) |
| `admin/product_moderation.php` | модерация листингов (одобрить/отклонить с причиной) |
| `seller_shop.php` | публичная витрина магазина (`?slug=`) |

### 16.3 Видимость на витрине (важно)
Везде, где выбираются товары, добавлено условие
`(p.seller_id IS NULL OR p.moderation_status = 'active')` — иначе непроверенные
листинги утекли бы в каталог. Затронуты: `catalog/index.php`, `search/index.php`,
`index.php` (4 запроса главной), `catalog/part.php` (плюс закрывает прямой доступ по URL).

### 16.4 Наполнение словаря названий из прайс-листа AutoEuro
Массово перебирать API нельзя (бан + нет списка артикулов). Легальный путь — **прайс-лист**
из личного кабинета AutoEuro (Настройки → Прайс-лист; формат **CSV**, кодировка **UTF-8**).
Столбцы прайса: `Производитель;Марка;КаталожныйНомер;НомерПроизводителя;ОригинальныйНомер;Применение;Цена;…`
Берём: **Производитель → бренд**, **НомерПроизводителя → артикул**, **Применение → название**.

| Скрипт | Назначение |
|---|---|
| `superadmin/ae_dict_import_csv.php` | **основной** — импорт прайса: `php superadmin/ae_dict_import_csv.php price.csv` (флаг `--dry` — проверка разбора без БД). Пакетный upsert, создаёт `FULLTEXT(name)`. Ноль запросов к API |
| `superadmin/ae_dict_batch.php` | запасной — добор названий через API по артикулам, которые уже есть в БД: `php … 100 500` (100 шт., пауза 500мс). Пропускает известные, ≤3 бренда на артикул — чтобы не словить бан |

Проверено на боевом прайсе: 229 129 строк → **229 127 названий** в словаре.


### 16.5 Фаза 2 — заказы с разбивкой по продавцам ✅
Заказ был плоским (`orders` + `order_items`, продавца нигде нет): корзина с товарами трёх
продавцов давала ОДИН заказ с ОДНИМ статусом, и продавец не видел свою часть.

```
orders               заказ покупателя (оплата, адрес, итог)
  └─ order_sellers      подзаказ продавца (статус, сумма, комиссия, выплата)
       └─ order_items      позиции этого подзаказа
```

| Где | Что |
|---|---|
| `buyer/checkout.php` | группирует корзину по `seller_id`, создаёт подзаказ на каждого; ставка комиссии берётся из `sellers` и **фиксируется в подзаказе**; наш каталог — `seller_id NULL`, без комиссии. Всё внутри существующей транзакции |
| `seller/orders.php` | заказы продавца: позиции, сумма, комиссия, выплата, фильтр по статусам; переходы `pending → processing → shipped → delivered` + отмена. **Все запросы ограничены `seller_id`** — чужой подзаказ не тронуть. Адрес и телефон покупателя открываются только после принятия |
| `buyer/orders.php` | покупателю видна разбивка: магазин, сумма, свой статус. Блок показывается только при реальном продавце или нескольких частях |
| `admin/orders.php` | таблица подзаказов: продавец, контакт, сумма, комиссия, выплата, статус + общая комиссия площадки |
| `seller/index.php` | счётчик новых заказов |
| `includes/cart_lib.php` | `cartDetailedItems()` отдаёт `seller_id` **под проверкой колонки** — иначе падение запроса вернуло бы покупателю пустую корзину |

Везде проверяется наличие схемы: **без миграции всё работает по-старому**.

---

### 16.6 Фаза 3a — балансы продавцов и выплаты ✅

Фаза 2 считала комиссию по каждому подзаказу, но нигде её не накапливала: продавец
не знал, сколько ему должны, а у владельца не было реестра выплат. Эта часть закрывает
расчёты. **Эквайринг не нужен** — деньги площадка уже получает при доставке; вопрос
только в том, чтобы честно рассчитаться с продавцом и оставить след для сверки.

**Модель — журнал, а не изменяемое поле «баланс»** (`sql/marketplace_phase3_payouts.sql`):

```
seller_ledger      одна строка = одно движение денег, amount ХРАНИТСЯ СО ЗНАКОМ
  earning     +    начислено за доставленный подзаказ (order_sellers.payout_amount)
  reversal    −    сторно, если доставленный подзаказ потом отменили
  payout      −    выплата продавцу (ссылка на seller_payouts)
  adjustment  ±    ручная правка владельцем (штраф, компенсация, итог спора)

баланс продавца = SUM(seller_ledger.amount)

seller_payouts     факт выплаты: сумма, способ, номер перевода, период, кто провёл
```

Почему журнал, а не колонка `balance`: колонку можно испортить двойным начислением или
гонкой, и потом невозможно доказать, откуда взялась цифра. Журнал воспроизводим.

**Защита от двойного начисления** — `UNIQUE (order_seller_id, type)`. Продавец может
щёлкнуть «Доставлен» дважды или вернуть заказ в «Отправлен» и снова в «Доставлен» —
вставка просто не пройдёт. У `payout`/`adjustment` поле `order_seller_id` = NULL, а MySQL
разрешает сколько угодно NULL в UNIQUE-индексе, поэтому их ограничение не касается.

**Правила начисления** (все в одном месте — `sellerLedgerSyncOrderSeller()`):
- начисляем при переходе подзаказа в `delivered` — заработано то, что дошло до покупателя;
- сумма = `payout_amount`, зафиксированная в момент заказа (subtotal − комиссия);
- отмена уже доставленного сторнируется ровно на начисленную сумму;
- наш собственный каталог (`seller_id IS NULL`) в журнал не попадает — площадка не должна
  денег сама себе.

| Файл | Что делает |
|---|---|
| `includes/seller_finance.php` | вся логика: `sellerFinanceReady()`, `sellerLedgerAdd()` (INSERT IGNORE), `sellerLedgerSyncOrderSeller()` (идемпотентная реакция на статус), `sellerLedgerSyncAll()` (back-fill по старым заказам), `sellerFinanceSummary()`, `sellerPayoutRegistry()`, `sellerPayoutCreate()` (реестр + движение одной транзакцией) |
| `seller/orders.php` | после успешной смены статуса зовёт `sellerLedgerSyncOrderSeller()` — единственная точка, где статус меняется |
| `seller/finance.php` | выписка продавца: «к выплате сейчас», в пути, удержанная комиссия, история движений, выплаты. **Только чтение** |
| `admin/payouts.php` | реестр «кому сколько должны», проведение выплаты, корректировка с обязательной причиной, «Пересчитать начисления», выгрузка CSV (с BOM — иначе Excel ломает кириллицу) |
| `seller/index.php` | плитка «К выплате» на дашборде |

**Грабли, заложенные осознанно:**
- Переплату не пропускаем: выплатить больше остатка нельзя — иначе баланс уходит в минус
  и потом не отличить долг продавца от опечатки.
- В модалке выплаты сумма показывается «сырым» числом, а не через `formatPrice()`: поле
  ввода принимает сумму в валюте хранения, и лимит проверки обязан совпадать с подсказкой.
- `sellerPayoutCreate()` открывает транзакцию только если её ещё нет (`inTransaction()`) —
  вложенный `beginTransaction()` уронил бы внешнюю.
- Выплаты **не автоматические**: владелец переводит деньги вне сайта и отмечает факт.
  Автоматизация упирается в тот же эквайринг/банковский API, что и Фаза 3b.

---

## 17. Обслуживание каталога Parts-Catalogs (важное)

### 17.1 Что выяснилось на боевых данных
1. **Дерево узлов резалось вдвое.** Жёсткие лимиты `depth > 4 || count >= 120` обрезали
   каталог: у Lexus **229** реальных узлов вместо 120, у BMW — 745, у Volvo — 1000+.
   То есть половина каталога для сайта не существовала. Лимиты вынесены в настройки.
2. **Токен комплектации протухает** → 404 «Комплектация не найдена» (см. § 14).
3. **Расход тарифа не соответствует ожиданиям** — правила выясняются (см. § 14).

### 17.2 Инструменты
```bash
php superadmin/catalog_library_rebuild.php plan                 # оценка, ничего не меняет
php superadmin/catalog_library_rebuild.php recount 30 0 300     # пересчёт деревьев
php superadmin/catalog_library_rebuild.php schemes 0 5000 300   # добор схем
php superadmin/catalog_library_rebuild.php revin 11 0 300       # ⚠️ тратит тариф
php superadmin/pc_tree_probe.php --lib                          # реальный размер дерева
```
Аргументы: `что` · `бюджет_авто` · `бюджет_схем` · `пауза_мс`. Скрипт **возобновляемый**
и защищён файловой блокировкой — второй параллельный запуск не стартует.

### 17.3 Грабли, на которые уже наступили
- **`getDB()` держал одно соединение без переподключения** — обход дерева это минуты HTTP,
  MySQL закрывал сессию по `wait_timeout`, и прогон падал с «server has gone away».
  Лечение: `dbKeepAlive()` в `config/database.php` (для долгих CLI; на страницы не влияет).
- **Удаление дерева до обхода теряло данные** при прерывании. Теперь: `rewalkNodes()` →
  и только при непустом результате `storeNodes()`.
- **Авто без дерева выпадало из очереди** (`JOIN` вместо `LEFT JOIN`) — потеря не
  восстанавливалась сама.
- **Авто, упёршееся в текущий лимит, переобходилось каждый прогон** впустую (Volvo — 1000
  запросов на ровном месте). Теперь такие исключены и показываются отдельной строкой.
- **Два параллельных прогона** удваивали нагрузку на API и гонялись за одни и те же авто —
  добавлена блокировка.
- **Класс адаптера подключается лениво** (`Manager.php`, внутри `Catalog::provider()`):
  статический вызов `PartsCatalogsAdapter::...` на странице, где провайдер не создаётся,
  роняет страницу молча (при выключенном `display_errors` просто обрывается вывод).

### 17.4 Что видит покупатель при блокировке
Тексты об отказах **нейтральны**: ни поставщик, ни наши лимиты не называются —
«Каталог по этому автомобилю временно недоступен. Напишите менеджеру — подберём деталь
вручную». Конфиденциальность цепочки поставок — требование заказчика.
