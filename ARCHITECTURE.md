# ARCHITECTURE — карта кодовой базы autodoc.tj

> Назначение: **быстрый навигатор**. Нужно что-то поправить — смотри «Быстрый
> индекс: хочу изменить X → файл Y» внизу, не читай всё подряд. Каждый раздел
> отвечает: где лежит, как работает, какие настройки/таблицы.

---

## 1. Технический стек

| Слой | Что |
|------|-----|
| Язык | **PHP 8.0+** (без фреймворка, собственный роутинг через страницы) |
| БД | **MySQL** через PDO (`ERRMODE_EXCEPTION`), подключение — `config/database.php` → `getDB()` |
| Фронт | Bootstrap 4 + шаблон **Mazlay**, jQuery; VIN-страница — своя вёрстка (токены `--vx-*`) |
| HTTP к API | общий хелпер **`httpGet()`** (cURL → fallback file_get_contents) в `includes/functions.php` |
| i18n | `includes/i18n.php` + `lang/ru.php|en.php|tg.php`, функция `t('ключ')` |
| Валюта | `includes/currency.php`, база — **RUB**, `formatPrice($rub)` → активная валюта (сомони) |
| Деплой | git → `main`; сервер `git pull origin main` (Timeweb, корень `public_html`) |

**Важно про потоки:** ветка разработки `claude/initial-setup-BNMb8` → PR → merge в `main`.
Никогда не пушить в `main` напрямую. Ключи API — только в БД (`site_settings`), не в git.

---

## 2. Структура папок

```
config/       — config.php (бутстрап, подключает всё), database.php
includes/     — ядро: functions.php, header/footer, currency, i18n, cart_lib, vin_service, autoeuro
includes/catalog/ — СЛОЙ КАТАЛОГА (провайдеры, слой цен) — см. раздел 4
api/          — AJAX-эндпоинты (JSON), см. раздел 4.4
pages/        — публичные страницы (vin.php — главная по каталогу)
catalog/      — витрина магазина (index, category, part)
buyer/        — кабинет покупателя (cart, checkout, orders, profile, wishlist)
auth/         — вход/регистрация (email + телефон/PIN/SMS)
admin/ manager/ superadmin/ — админ-панель по ролям
assets/       — css/js/картинки шаблона
lang/         — переводы ru/en/tg
sql/          — schema.sql + миграции
storage/      — логи (sms.log и т.п.)
```

**Бутстрап:** любой файл начинается с `require config/config.php`, который тянет
`database.php → functions.php → cart_lib.php → i18n.php → currency.php`.

---

## 3. Ключевые общие файлы (`includes/`)

| Файл | За что отвечает | Заметные функции |
|------|-----------------|------------------|
| `functions.php` (1800+ строк) | хелперы всего сайта | `getSetting/setSetting`, **`httpGet()`**, `getDB` (в database.php), `isLoggedIn/getCurrentUser/loginUser`, `getCartCount/getMiniCart/getMiniCartTotal`, `formatPrice` (в currency), `getEffectiveMarkup`, `normalizePhone`, `userCan/requirePermission`, `partUrl` |
| `cart_lib.php` | **корзина гостя+юзера** | `cartAdd/cartSetQty/cartRemove/cartClearAny/cartDetailedItems/cartCountAny/cartTotalAny`, `cartMergeGuestIntoUser`, `guestOrderUserId` |
| `vin_service.php` | **декодер VIN** (NHTSA/PartsAPI/локальная WMI-база) | `VinService::decode/validate`, кэш `vin_cache`, `DECODE_VER` |
| `catalog.php` | единая точка подключения слоя каталога → `Catalog::provider()` | — |
| `catalog_api.php` | боевой PartsAPI (getPartsbyVIN/getCrosses) + `enrichItemsFromWarehouse()` | обёртка склада для всех адаптеров |
| `autoeuro.php` | клиент поставщика AutoEuro (цены/заказ) | `AutoEuro::fromSettings/searchItems/createOrder` |
| `header.php/footer.php` | шапка/подвал витрины (мини-корзина в шапке) | — |
| `admin-header.php/admin-footer.php` | единый макет админки (роли) | `renderRoleSidebar` |
| `currency.php` | конвертация RUB→активная валюта | `formatPrice/convertPrice/getCurrencySymbol` |
| `partsapi_cats.php` | справочник 751 товарной группы PartsAPI | `PARTSAPI_CATS`, `PARTSAPI_POPULAR` |

---

## 4. СИСТЕМА «VIN + КАТАЛОГ» (главное)

Цель: каталог запчастей по VIN «как у autodoc.ru», но провайдер **сменный** и
настраивается из админки. Полный план — `CATALOG_PLAN.md`.

### 4.1. Архитектура: единый интерфейс + сменные провайдеры

```
Фронт (pages/vin.php) и эндпоинты (api/vin_*.php)
        ↓ работают только через
Catalog::provider()   ← includes/catalog/Manager.php (фабрика, читает настройку catalog_provider)
        ↓ возвращает один из:
CatalogProvider (интерфейс) ← includes/catalog/Provider.php
  ├── PartsApiAdapter        — PartsAPI.ru (данные, без схем)
  ├── PartsCatalogsAdapter   — Parts-Catalogs/Tradesoft (OEM + ВИЗУАЛЬНЫЕ схемы)  ★ активный
  ├── LaximoAdapter          — Laximo (каркас, HMAC)
  ├── GenericRestAdapter     — ЛЮБОЙ REST-сервис по ПРОФИЛЮ (без кода)
  └── MockAdapter            — демо без ключа
```

| Файл | Роль |
|------|------|
| `includes/catalog/Provider.php` | **интерфейс** `CatalogProvider` (контракт: `enabled/searchByVin/searchByVinCat/oemNodes/crossesWithWarehouse/testConnection/clearCache`) |
| `includes/catalog/Manager.php` | **фабрика** `Catalog::provider()/available()/make()/price()`. Выбор по `catalog_provider` |
| `includes/catalog/PartsCatalogsAdapter.php` | активный провайдер: VIN→car/info→groups2(узлы)→parts2(схема+детали). Методы каскада `pcBrands/pcModels/pcCarParams/pcCars`, `oemNodesForVin/oemNodesForCar`, `schemeByVinCat`. Кэш `partsapi_kv_cache` (префикс `pc:`) |
| `includes/catalog/GenericRestAdapter.php` | движок профилей: `buildUrl/getByPath/parseParts` (чистые, тестируемые) |
| `includes/catalog/CatalogProfiles.php` | реестр профилей (встроенные + `catalog_profiles` JSON) |
| `includes/catalog/PartsApiAdapter.php` | обёртка `CatalogApi` (PartsAPI) |
| `includes/catalog/LaximoAdapter.php` | Laximo (ec.api HMAC, каркас) |
| `includes/catalog/MockAdapter.php` | демо-данные |

### 4.2. Слой цен (независим от каталога)

Каталог даёт **OEM-номер** → слой цен возвращает цену в сомони.

| Файл | Роль |
|------|------|
| `includes/catalog/PriceProvider.php` | интерфейс `priceByOem(oem,brand)` |
| `includes/catalog/WarehousePriceProvider.php` | свой склад (таблица `parts`) — приоритет |
| `includes/catalog/AutoEuroPriceProvider.php` | фолбэк AutoEuro по OEM + наценка `global_markup` |
| `includes/catalog/PriceAggregator.php` | склад → AutoEuro; кэш `catalog_price_cache`. Фасад: `Catalog::price()` |

### 4.3. Фронтенд — `pages/vin.php` (единственный, ~1260 строк)

Одна страница, всё внутри. Ключевые зоны (ищи по имени):
- **Табы поиска**: `vxTab`, `#vx-p-vin` (по VIN), `#vx-p-params` (по параметрам).
- **Живой каскад «По параметрам»** (Parts-Catalogs): IIFE ~строка 800, ходит в `api/vin_params.php`, самоактивируется если API вернул марки.
- **Дерево узлов**: `.vin-cat7` (сайдбар `#vinNodeList` + сетка `#vinNodeGrid`), `vinLoadPcNodes()` тянет `api/vin_nodes.php`.
- **Вид узла**: `vinLoadNode → vinLoadScheme` (Parts-Catalogs, схема) или `vinCatalogFetch` (PartsAPI). Рендер схемы — `vinRenderScheme` (картинка `#vinSchemeImg` + хотспоты `#vinSchemeHot`).
- **Карточки деталей**: `vinBuildPartsHtml()` (номер-выноска `.vin-pos-box` вместо фото — у OEM фото нет, `vinFocusPos` подсвечивает деталь на схеме).
- **Ленивые цены**: `vinFillPrices()` → `api/vin_price.php`.
- **Кроссы**: `vinCrosses()` → `api/vin_crosses.php`.
- **Расшифровка сокращений**: `vinExpandAbbr()` (К-т→Комплект и т.п.).
- **Ширина**: `.vx .container{max-width:min(1560px,94vw)}`; каталог вынесен из узкой обёртки на всю ширину.

### 4.4. AJAX-эндпоинты (`api/`) — все через `Catalog::provider()`

| Эндпоинт | Что отдаёт |
|----------|------------|
| `api/vin_catalog.php` | детали узла/полный каталог (PartsAPI-режим) |
| `api/vin_nodes.php` | дерево узлов авто (`oemNodesForVin/ForCar`), поле `img` (миниатюра) |
| `api/vin_scheme.php` | визуальная взрыв-схема узла: `img`, `hotspots[{n,x,y,w,h}]`, `parts[]` |
| `api/vin_params.php` | каскад «По параметрам»: `step=brands/models/carparams/cars` |
| `api/vin_price.php` | цена по OEM (склад→AutoEuro) |
| `api/vin_crosses.php` | аналоги-кроссы + обогащение складом |
| `api/vin_analogs.php` | аналоги детали из своего каталога |

---

## 5. Корзина и заказ (гость + юзер)

Login-wall снят: гость подбирает и заказывает без регистрации.

- **`includes/cart_lib.php`** — единое хранилище: гость → сессия `$_SESSION['guest_cart']`, юзер → таблица `cart`. При входе — `cartMergeGuestIntoUser` (вызов из `loginUser`).
- **`api/cart.php`** — add/remove/update/count/mini.
- **`buyer/cart.php`** — страница корзины; **`buyer/checkout.php`** — оформление (заказ гостя привязывается к аккаунту по телефону через `guestOrderUserId`).
- Мини-корзина в шапке — `getCartCount/getMiniCart/getMiniCartTotal` (в `functions.php`).

---

## 6. Аутентификация и роли

- **`auth/login.php` / `auth/register.php`** — email (логин+пароль) и телефон (SMS-код / PIN для сотрудников). Тумблер email — `auth_email_enabled`. Защита от перебора — таблица `login_attempts` (5 попыток → блок 15 мин).
- Роли: `buyer` (покупатель), `manager`, `admin`, `superadmin`. Проверки — `userCan($section)/requirePermission($section)/requireRole([...])` в `functions.php`.
- **`superadmin/`** — настройки сайта, VIN/каталог, пользователи, бэкап. **`manager/`** — контент (страницы, блог, отзывы). **`admin/`** — товары, слайдеры, баннеры.

---

## 7. Настройки (`site_settings`, ключ→значение) — где что

Читаются `getSetting('ключ', 'дефолт')`, правятся в **Суперадмин → VIN-поиск** и др.

**Каталог/провайдер:**
`catalog_provider` (partsapi|partspc|laximo|mock|<профиль>), `catalog_api_enabled`,
`catalog_api_type`, `catalog_api_oem_nodes`, `catalog_profiles` (JSON).
**PartsAPI:** `catalog_api_key`, `catalog_api_base`, `catalog_api_max_groups`, `catalog_api_timeout`.
**Parts-Catalogs:** `catalog_pc_key`, `catalog_pc_base`, `catalog_pc_timeout`, `catalog_pc_auth` (header|bearer|query), `catalog_pc_key_param`, `catalog_pc_schema`, `catalog_pc_lang` (ru|en).
**Laximo:** `catalog_laximo_login`, `catalog_laximo_secret`.
**Цены:** `catalog_price_autoeuro`, `global_markup`; AutoEuro: `autoeuro_enabled/api_key/delivery_key/payer_key`.
**VIN-декодер:** `vin_search_enabled`, `vin_api_provider` (nhtsa|partsapi|custom), `vin_api_url`, `vin_api_key`, `vin_api_timeout`.

---

## 8. Таблицы БД (`sql/schema.sql` + миграции на лету)

**Основные:** `users`, `parts`, `categories`, `brands`, `cart`, `orders`, `order_items`, `delivery_zones`, `site_settings`.
**Создаются в рантайме (кэш каталога):** `partsapi_catalog_cache`, `partsapi_kv_cache` (узлы/схемы/кроссы/каскад, префиксы `pc:`), `catalog_price_cache`, `vin_cache`, `login_attempts`.

> Миграции идемпотентные (через `dbAddColumnIfMissing()` / `CREATE TABLE IF NOT EXISTS`) — совместимо с MySQL 8 на проде.

---

## 9. Быстрый индекс: «хочу изменить X → файл Y»

| Задача | Файл(ы) |
|--------|---------|
| Внешний вид страницы VIN, дерево, схема, карточки | `pages/vin.php` (CSS+JS вверху) |
| Добавить новый REST-провайдер каталога **без кода** | админка: `catalog_profiles` JSON (движок `GenericRestAdapter`) |
| Поведение Parts-Catalogs (язык, авторизация, поля) | `includes/catalog/PartsCatalogsAdapter.php` |
| Логика цен (склад/AutoEuro/наценка) | `includes/catalog/PriceAggregator.php` + `*PriceProvider.php` |
| Ответ эндпоинта каталога/схемы/узлов/цены | `api/vin_*.php` |
| Корзина (гость/юзер, слияние, заказ) | `includes/cart_lib.php`, `api/cart.php`, `buyer/checkout.php` |
| Расшифровка VIN (марка/год/страна) | `includes/vin_service.php` |
| Настройки каталога в админке | `superadmin/vin.php` |
| HTTP к любому внешнему API | `httpGet()` в `includes/functions.php` |
| Переводы текста | `lang/ru.php|en.php|tg.php` + `t()` |
| Цена/валюта/наценка | `includes/currency.php`, `getEffectiveMarkup` |
| Права/роли/доступ к разделам | `functions.php` (`userCan/requirePermission`) |
| Схема БД / новые колонки | `sql/schema.sql` (+ `dbAddColumnIfMissing`) |

---

## 10. Документация в репозитории

- **`ARCHITECTURE.md`** (этот файл) — карта кода.
- **`CATALOG_PLAN.md`** — план и этапы универсального каталога (архитектура, статусы).
- **`README.md`** — установка, деплой, changelog по PR, «как работает» разделы.
