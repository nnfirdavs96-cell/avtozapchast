# NAVIGATION — карта проекта autodoc.tj

Единая точка входа для понимания кодовой базы. Прочитав только этот файл, можно
понять структуру, назначение компонентов и историю изменений — без пересканирования кода.

- Глубокая детализация (все функции `functions.php`, контракты каждого адаптера, все ключи
  `site_settings`) — в `ARCHITECTURE.md`.
- Ранняя история изменений (слайдер/баннеры) — в `CHANGES.md`.
- Подробный план слоя каталога — в `CATALOG_PLAN.md`.

> Проект — интернет-магазин автозапчастей для рынка Таджикистана (autodoc.tj):
> витрина + VIN-подбор запчастей из внешних OEM-каталогов + админка на 3 роли.

---

## 1. Технический стек

| Слой | Технология |
|---|---|
| Язык | PHP (процедурный + классы, без фреймворка) |
| БД | MySQL / MariaDB (PDO, prepared statements) |
| Фронтенд | Server-rendered PHP + ванильный JS (`assets/js/app.js`), без сборки/npm |
| Шаблон | Кастомизированный «Mazlay» (CSS/JS в `assets/mazlay-*`) |
| Внешний HTTP | cURL с собственным CA-bundle (fallback — `file_get_contents`); см. `httpGet()` |
| Хостинг | Shared-хостинг Timeweb (`deploy/timeweb/`), деплой через `git pull` |
| Внешние API | Parts-Catalogs (OEM-каталоги+схемы), PartsAPI/TecDoc, AutoEuro (цены), Laximo (каркас) |
| CI/CD | Нет. Все изменения — PR в ветку `main`, ручной мёрдж |

**Особенности инфраструктуры:**
- Нет Composer/vendor, нет Docker, нет автотестов. Валидация — `php -l` + `node --check`.
- Миграции БД **самонакатывающиеся** в рантайме (`ensure*Schema()`, `dbAddColumnIfMissing()`,
  `CREATE TABLE IF NOT EXISTS`) — SQL-файлы в `sql/` дублируют это для явного деплоя.
- `config/db_credentials.php` — git-ignored, свой на каждом сервере (prod БД: `cs360870_auto`).

---

## 2. Бутстрап (поток запроса)

```
Запрос → config/config.php (константы APP_URL/APP_ROOT, session_start, no-cache заголовки)
        → config/database.php  (getDB() — синглтон PDO; креды из db_credentials.php)
        → includes/functions.php (ядро: auth, настройки, HTTP, товары, заказы, SMS…)
        → includes/{cart_lib,i18n,currency}.php
        → страница (index.php / catalog/* / buyer/* / pages/* / admin|manager|superadmin/*)
```

- Роль-гейтинг: `requireRole([...])` + `requirePermission('section')` в начале защищённых страниц.
- Локализация: `t('key')` (языки в `lang/{ru,tg,en}.php`); валюта — сомони (TJS), `formatPrice()`.

---

## 3. Навигация по папкам

| Путь | За что отвечает |
|---|---|
| `config/` | Бутстрап: `config.php` (константы, сессия), `database.php` (PDO) |
| `includes/` | **Ядро.** `functions.php` (всё общее), шапки/подвалы, cart/i18n/currency, `vin_service.php`, `autoeuro.php` |
| `includes/catalog/` | **Слой каталога** (провайдеры OEM + слой цен) — см. §5 |
| `api/` | AJAX-эндпоинты (JSON). VIN-подбор, корзина, поиск, отзывы, SMS, загрузка |
| `pages/` | Публичные страницы: `vin.php` (★ VIN-подбор), `vin_kp.php` (КП), блог, контакты, 403/404 |
| `catalog/` | Витрина магазина: `index.php` (список), `category.php`, `part.php` (карточка товара) |
| `search/` | Поиск по товарам витрины |
| `buyer/` | Кабинет покупателя: корзина, чекаут, заказы, профиль, избранное |
| `auth/` | Вход/регистрация/выход (email+пароль, телефон+SMS/PIN) |
| `admin/` | Роль **admin**: товары, заказы, слайдер, баннеры, пользователи |
| `manager/` | Роль **manager**: контент — блог, страницы, категории, бренды, отзывы, товары |
| `superadmin/` | Роль **superadmin**: настройки, каталог/VIN, склад, доставка, валюты, языки, права, бэкапы, **библиотека каталога** |
| `lang/` | Переводы `ru`/`tg`/`en` |
| `sql/` | Миграции (дублируют рантайм-самонакат) |
| `storage/` | `backups/` (SQL-дампы), `manual/`, `sms.log` (тест-режим SMS) |
| `deploy/timeweb/` | Конфиги/скрипты прод-деплоя |
| `assets/` | CSS/JS/шрифты/изображения; `mazlay-*` — шаблон; `uploads/` — загрузки |
| `index.php` | Главная (витрина + слайдер + баннеры) |
| `sitemap.php` / `robots.txt` | SEO |
| корень `*.md` | Документация (этот файл, ARCHITECTURE, CATALOG_PLAN, CHANGES, CLAUDE) |

**Правило поиска «где что»:** конфиг → `config/`; общая логика → `includes/functions.php`;
каталог/провайдеры → `includes/catalog/`; AJAX → `api/`; экраны админки → `admin|manager|superadmin/`.

---

## 4. Ключевые файлы

| Файл | Назначение |
|---|---|
| `includes/functions.php` | ~1700 строк: auth/роли/права, CSRF/флеш/редирект, `getSetting/setSetting`, `httpGet`, товары витрины, заказы, **телефон+SMS (OTP)**, троттлинг входа, онлайн-оплата, сидеры |
| `includes/catalog/Manager.php` | Фасад `Catalog::provider()` — выбирает активный адаптер по `catalog_provider` |
| `includes/catalog/PartsCatalogsAdapter.php` | ★ Активный провайдер (`partspc`): VIN→авто→узлы→схемы+детали; кэш; **библиотека**; сбор схем; спрос |
| `includes/vin_service.php` | `VinService`: валидация/декод VIN, локальный WMI-разбор, кэш `vin_cache` |
| `pages/vin.php` | ★ Фронтенд VIN-подбора: карточка авто, дерево узлов, взрыв-схемы, лайтбокс, кнопка КП |
| `pages/vin_kp.php` | Печатная страница КП (список деталей узла + живые цены → PDF из браузера) |
| `superadmin/vin.php` | Настройки VIN/каталога + вкладка «Совместимость» (с предложениями из библиотеки) |
| `superadmin/catalog_library.php` | Библиотека каталога: список авто, экспорт, сбор схем, все тумблеры, аналитика спроса |
| `superadmin/catalog_library_cron.php` | CLI-дособиратель схем (по тумблеру автосбора) |
| `buyer/checkout.php` | Оформление заказа (способы оплаты — см. §7) |
| `auth/login.php` `auth/register.php` | Вход/регистрация: email+пароль ИЛИ телефон+SMS/PIN |
| `api/sms_auth.php` | AJAX-отправка OTP-кода |

---

## 5. Слой каталога `includes/catalog/`

**Идея:** единый интерфейс `CatalogProvider` (`Provider.php`), несколько взаимозаменяемых
адаптеров, выбор через настройку `catalog_provider`. Цены — отдельный слой (`PriceProvider`).

| Файл | Роль |
|---|---|
| `Provider.php` | Интерфейс `CatalogProvider` (контракт всех адаптеров) |
| `Manager.php` | Фасад `Catalog` (`provider()`, `available()`, `reset()`, `price()`) |
| `PartsCatalogsAdapter.php` | ★ `partspc` — OEM-каталоги Parts-Catalogs с визуальными взрыв-схемами |
| `PartsApiAdapter.php` (+ `includes/catalog_api.php`) | `partsapi` — TecDoc/PartsAPI по VIN; `catalog_api.php` даёт `enrichItemsFromWarehouse()` (цены/сток) |
| `GenericRestAdapter.php` + `CatalogProfiles.php` | Профили — подключение любого REST-каталога **без кода** (JSON-конфиг) |
| `LaximoAdapter.php` | `laximo` — каркас |
| `MockAdapter.php` | `mock` — демо без ключа |
| `PriceProvider.php` + `PriceAggregator.php` + `WarehousePriceProvider.php` + `AutoEuroPriceProvider.php` | Слой цен: свой склад → AutoEuro → сомони |

**Поток данных VIN-подбора (Parts-Catalogs):**
```
VIN → car/info → {carId, catalogId, criteria}
    → groups2 (дерево узлов) → parts2 (взрыв-схема + детали узла)
    → enrichItemsFromWarehouse (цена/наличие со своего склада)
```

**Кэш и библиотека (порядок чтения — экономия лимита API):**
```
Запрос → [1] Библиотека (постоянная, без TTL)  → нашлось? отдать, 0 запросов к API
        → [2] TTL-кэш (partsapi_kv_cache, 30д) → нашлось? отдать
        → [3] Живой запрос к Parts-Catalogs    → записать в кэш И в библиотеку
```

---

## 6. Библиотека каталога (постоянный архив OEM-данных)

Накапливает ответы Parts-Catalogs без TTL → экономия лимита, резерв на сбой API, база для
совместимости/аналитики. Наполняется автоматически при каждом реальном поиске (VIN и «по параметрам»).

**Таблицы:**
| Таблица | Содержимое |
|---|---|
| `catalog_library_cars` | Карточка авто: `catalog_id/car_id`, `vin` (пусто для «по параметрам»), `brand`, `attrs_json` |
| `catalog_library_nodes` | Дерево узлов авто (`nodes_json`) |
| `catalog_library_schemes` | Схема узла: `img`, `hotspots_json`, `parts_json` |
| `catalog_demand` | Счётчик обращений (авто/узлы) — аналитика спроса |
| `partsapi_kv_cache` | TTL-кэш (не библиотека); сбрасывается при `CACHE_VER` |

**Тумблеры (site_settings):**
| Ключ | Что делает | Дефолт |
|---|---|---|
| `catalog_library_read_first` | Читать библиотеку до API (вся экономия лимита) | `1` |
| `catalog_kp_enabled` | Кнопка «Скачать КП» + доступ к `vin_kp.php` | `1` |
| `catalog_compat_suggestions_enabled` | Панель предложений совместимости | `1` |
| `catalog_demand_enabled` | Аналитика спроса (счётчик обращений) | `0` |
| `catalog_library_autocollect` | Фоновый cron-дособиратель схем | `0` |

**Настройка cron дособирателя:** `*/5 * * * * php <APP_ROOT>/superadmin/catalog_library_cron.php`

---

## 7. Аутентификация и оплата (текущий статус)

**Вход (`auth/`, функции в `functions.php`):**
- Email + пароль — работает.
- Телефон + **SMS-код** (OTP): вся логика готова (хэш кода, TTL 5 мин, rate-limit, троттлинг).
  ⚠️ **Реальная отправка SMS НЕ подключена** — `sendSms()` в тест-режиме (код пишется в
  `storage/sms.log` и показывается на экране). Нужен: провайдер СНГ + HTTP-вызов + форма настроек.
- Телефон + **PIN** — для персонала (`pin_hash`).

**Оплата (`buyer/checkout.php`):**
- ⚠️ **Реальной онлайн-оплаты (карта/QR) НЕТ.** «Оплата онлайн» — маркетинговый чекбокс со
  скидкой/бесплатной доставкой, без формы карты/редиректа/webhook. Работает по факту только
  «наложенный платёж» и «банковский перевод» (сверка вручную).
- Для запуска нужен: эквайринг-провайдер ТДж (Alif/Corti/Eskhata/…) + редирект + callback.

---

## 8. Схема БД (ключевые таблицы)

| Таблица | Назначение | Миграция |
|---|---|---|
| `users` | Пользователи (+`phone_e164`, `pin_hash`; email/пароль опциональны) | `add_phone_auth.sql` |
| `phone_otp` | Одноразовые SMS-коды | `add_phone_auth.sql` |
| `parts` / `categories` / `brands` | Каталог витрины | `schema*.sql` |
| `orders` / `order_items` | Заказы (+`shipping_cost`, `discount_amount`, `payment_method`) | `schema*`, `add_order_*` |
| `car_models` / `parts_compatibility` | Совместимость запчастей с авто | `migrate_vin.sql` |
| `vin_cache` | Кэш VIN-декода | `migrate_vin.sql` |
| `catalog_library_*` / `catalog_demand` | Библиотека каталога + спрос | `migrate_catalog_library.sql` (+ рантайм) |
| `site_settings` | Все настройки (key/value) | `schema*.sql` |
| `sliders` / `banners` / `blog_*` / `pages` | CMS-контент | `migrate_cms.sql` |
| `reviews` / `shop_reviews` | Отзывы | `migrate_reviews*.sql` |

---

## 9. История изменений по PR

Проект развивается через PR в `main`. Ниже — содержательные PR по темам (ранние #1–174 — базовая
витрина, шаблон Mazlay, CMS, адаптив; здесь опущены).

### Библиотека каталога + план экономии лимита (#237–252) — актуальный слой
| PR | Что сделано | Файлы |
|---|---|---|
| #237 | Постоянная библиотека архива Parts-Catalogs + выгрузка в суперадмине | `PartsCatalogsAdapter`, `superadmin/catalog_library.php`, `sql/migrate_catalog_library.sql` |
| #238–239 | Фиксы миграции (жёсткое имя БД; `ROWS` — зарезервированное слово) | `sql/migrate_catalog_library.sql` |
| #240 | Лайтбокс схемы узла в библиотеке | `superadmin/catalog_library.php` |
| #244 | Массовый сбор схем: кнопка + отключаемый cron | `PartsCatalogsAdapter`, `catalog_library.php`, `catalog_library_cron.php` |
| #245 | Фикс дублей в дереве узлов («101 из 120») | `PartsCatalogsAdapter`, `catalog_library.php` |
| #246 | **Шаг 1:** библиотека как источник чтения до API + TTL 30д | `PartsCatalogsAdapter` |
| #247 | **Шаг 2:** печатное КП по узлу (PDF из браузера) | `pages/vin.php`, `pages/vin_kp.php` |
| #248 | **Шаг 3:** предложения совместимости из библиотеки (с подтверждением) | `superadmin/vin.php` |
| #249 | **Шаг 4:** аналитика спроса за тумблером (выключена) | `PartsCatalogsAdapter`, `catalog_library.php` |
| #250 | Тумблеры для шагов 1–3 | `PartsCatalogsAdapter`, `vin.php`, `vin_kp.php`, `catalog_library.php`, `superadmin/vin.php` |
| #251 | Фикс: авто «по параметрам» не попадало в библиотеку (сироты) | `PartsCatalogsAdapter` |
| #252 | Понятная подпись «без VIN (по параметрам)» вместо «–» | `superadmin/catalog_library.php` |

### VIN-карточка и взрыв-схемы (#219–236)
| PR | Что сделано |
|---|---|
| #219–220 | Реальные данные авто из Parts-Catalogs `car/info`; порядок секций |
| #221–222 | Двухколоночная раскладка узла; лайтбокс схемы + тултипы |
| #223 | Заметная подсветка выноски (свечение + пульсация) |
| #224 | Нормализация номеров выносок (`01`=`1`) + фолбэк для деталей без точки |
| #225–228 | «Фото» карточки = вырезка схемы; зум фрагмента детали на весь экран |
| #229 | Липкая схема под фиксированным меню |
| #231 | Фикс «пустого каталога»: не кэшировать пустой результат узлов |
| #232 | Атрибуты авто в режиме «по параметрам» из `cars2` |
| #233–235 | Новый дизайн карточки авто (вариант F, двухцветный сплит) |
| #241–243 | Мобильные баги (pinch-zoom, тултип, бейджи); редизайн списка узлов + свечение лого |
| #230, #236 | Changelog в README |

### Слой каталога и провайдеры (#175–212)
| PR | Что сделано |
|---|---|
| #175 | Вход сотрудников телефон+PIN + тумблер email-входа |
| #176–190 | Интеграция PartsAPI/TecDoc: VIN-декод, `getPartsbyVIN`, cURL-транспорт, обработка лимита |
| #191 | Дерево каталога по узлам + аналоги-кроссы |
| #196 | Редизайн страницы VIN + табы «По VIN / По параметрам» |
| #197–201 | Универсальная архитектура: каркас провайдеров, профили без кода, слой цен, Laximo-каркас |
| #200 | Гостевая корзина (заказ без регистрации) |
| #204–211 | Parts-Catalogs: визуальные схемы, переключаемая авторизация, «по параметрам», язык, нечисловые ID |

### Аудит и база (#173–195, выборочно)
| PR | Что сделано |
|---|---|
| #195 | C2 защита входа, C3 единый макет персонала, C5 ЧПУ, онлайн-оплата со скидкой |
| #193–194 | Адаптив по UX-аудиту; чистка остатков шаблона + страница 403 |
| #173–174 | Фиксы мини-корзины (оверлей/z-index/Escape) |

---

## 10. Точки внимания (техдолг / незавершённое)

- **SMS-отправка** — тест-режим, реальный провайдер не подключён (§7).
- **Онлайн-оплата** — только маркетинговый чекбокс, реального эквайринга/QR нет (§7).
- **SEO-страницы по авто из библиотеки** — не сделано намеренно (риск лицензии Parts-Catalogs).
- **Временные скрипты в корне** — `diag_partsapi.php`, `fix_vin_catalog.php`, `setup_catalog.php`
  (одноразовые, кандидаты на удаление), плюс архивы `mazlay-template.zip`, `site2.zip`.
- **Нет тестов/CI** — валидация только `php -l` / `node --check` вручную.
