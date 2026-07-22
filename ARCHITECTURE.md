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
| `partsapi_cats.php` | константы `PARTSAPI_CATS` (751 товарная группа id→название), `PARTSAPI_POPULAR` |
| `header.php` / `footer.php` | витринная шапка (меню, мини-корзина, поиск, языки/валюта) / подвал |
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

### 3.8 `admin/` (роль admin) — товары и оформление витрины
`index.php` (дашборд+выручка), `products.php` (товары+фото+цены+наценка),
`orders.php` (заказы+статусы+автосообщения+«заявка поставщику»), `users.php`,
`messages.php` (входящие переписки покупателей), `sliders.php` (слайдер: блочный
текст-редактор с live-preview, 9 позиций текста, шрифты), `banners.php` (баннеры + placement).

### 3.9 `manager/` (роль manager) — контент
`index.php`, `parts.php`, `categories.php`, `brands.php`, `blog.php`, `pages.php` (CMS), `reviews.php` (модерация).

### 3.10 `superadmin/` (роль superadmin) — всё управление
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
| `index.php` | дашборд |

### 3.11 Прочее
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
| `AutoEuroPriceProvider.php` | AutoEuro `searchItemsSmart(brand, oem)`: сперва `search_brands` (резолвит каноничные бренд+код — AutoEuro хранит код в своём формате, напр. с пробелами), затем `search_items` с `with_offers=1` (иначе цен нет). Берёт самое дешёвое предложение с ТОЧНЫМ совпадением кода. Цена в РУБЛЯХ → ×`autoeuro_rub_rate` (сомони/рубль) → ×(1+наценка). Наценка: `autoeuro_markup` если задана, иначе `global_markup`. Маппинг брендов `autoeuro_brand_map`; несовпадения в лог `autoeuro_price_miss`. Отдаёт `stock`/`delivery_time` (дата) |
| `PriceAggregator.php` | склад (без кэша, живой сток) → если пусто и `catalog_price_autoeuro=1` → AutoEuro (кэш `catalog_price_cache`: найдено 6ч / «не найдено» 1ч) |

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
`GET ?oem=&brand=` → `{success, found, price(строка в валюте), price_raw(RUB), stock, source('warehouse'|'autoeuro'), delivery, part_id, url}`.

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

> Все миграции идемпотентны: `dbAddColumnIfMissing()` / `CREATE TABLE IF NOT EXISTS` — безопасно для MySQL 8.

---

## 10. Роли и доступ

| Роль | Разделы |
|---|---|
| `buyer` | кабинет buyer/ (заказы, профиль, избранное, корзина) |
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
| AutoEuro | цены/наличие поставщика (боевой, на витрине VIN) | `autoeuro.php`, `AutoEuroPriceProvider.php` | `autoeuro_api_key`+delivery_key+`catalog_price_autoeuro`; заказ (`create_order`) — не подключён |
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

---

## 13. Документы репозитория
- **`ARCHITECTURE.md`** — этот файл (полная карта).
- **`CATALOG_PLAN.md`** — план/этапы универсального каталога (все 5 этапов ✅).
- **`README.md`** — установка, деплой, changelog всех PR, разделы «как работает».

## 14. Известные ограничения / «остаётся»
- SMS — боевой шлюз OsonSMS подключён (`sendSms()`→`osonSmsSend()`); тест-режим (лог) остаётся, если провайдер не выбран в настройках.
- **AutoEuro — цены на витрине работают** (`catalog_price_autoeuro=1`, ключ+delivery_key+курс). Не подключено: **автозаказ** у поставщика (`create_order`) — денежная ветка, под явным решением; сейчас закупка — вручную через «Заявку поставщику» на карточке заказа. Владелец держит актуальными `autoeuro_rub_rate` (курс рубля) и `autoeuro_markup` (наценка).
- Онлайн-оплата фиксирует способ и скидку; реальный платёжный шлюз — отдельная интеграция.
- Laximo — каркас (ssd-выдача деталей достраивается на боевом аккаунте).
- У OEM-каталогов НЕТ фото отдельных деталей (только взрыв-схема) — это свойство данных, на карточке показывается кликабельный номер-выноска.
- Часть названий узлов может приходить на en/de — непереведённые позиции в данных Tradesoft (лечится на их стороне).
- Локализация ru/tg/en: каркас VIN-страницы через `t()`, часть текстов блока результатов — на русском.
