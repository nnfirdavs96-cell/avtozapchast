---
name: frontend-design
description: Create distinctive, production-grade frontend interfaces with high design quality. Use this skill when the user asks to build web components, pages, or applications. Generates creative, polished code that avoids generic AI aesthetics.
license: Complete terms in LICENSE.txt
---

This skill guides creation of distinctive, production-grade frontend interfaces that avoid generic "AI slop" aesthetics. Implement real working code with exceptional attention to aesthetic details and creative choices.

The user provides frontend requirements: a component, page, application, or interface to build. They may include context about the purpose, audience, or technical constraints.

## Design Thinking

Before coding, understand the context and commit to a BOLD aesthetic direction:
- **Purpose**: What problem does this interface solve? Who uses it?
- **Tone**: Pick an extreme: brutally minimal, maximalist chaos, retro-futuristic, organic/natural, luxury/refined, playful/toy-like, editorial/magazine, brutalist/raw, art deco/geometric, soft/pastel, industrial/utilitarian, etc. There are so many flavors to choose from. Use these for inspiration but design one that is true to the aesthetic direction.
- **Constraints**: Technical requirements (framework, performance, accessibility).
- **Differentiation**: What makes this UNFORGETTABLE? What's the one thing someone will remember?

**CRITICAL**: Choose a clear conceptual direction and execute it with precision. Bold maximalism and refined minimalism both work - the key is intentionality, not intensity.

Then implement working code (HTML/CSS/JS, React, Vue, etc.) that is:
- Production-grade and functional
- Visually striking and memorable
- Cohesive with a clear aesthetic point-of-view
- Meticulously refined in every detail

## Frontend Aesthetics Guidelines

Focus on:
- **Typography**: Choose fonts that are beautiful, unique, and interesting. Avoid generic fonts like Arial and Inter; opt instead for distinctive choices that elevate the frontend's aesthetics; unexpected, characterful font choices. Pair a distinctive display font with a refined body font.
- **Color & Theme**: Commit to a cohesive aesthetic. Use CSS variables for consistency. Dominant colors with sharp accents outperform timid, evenly-distributed palettes.
- **Motion**: Use animations for effects and micro-interactions. Prioritize CSS-only solutions for HTML. Use Motion library for React when available. Focus on high-impact moments: one well-orchestrated page load with staggered reveals (animation-delay) creates more delight than scattered micro-interactions. Use scroll-triggering and hover states that surprise.
- **Spatial Composition**: Unexpected layouts. Asymmetry. Overlap. Diagonal flow. Grid-breaking elements. Generous negative space OR controlled density.
- **Backgrounds & Visual Details**: Create atmosphere and depth rather than defaulting to solid colors. Add contextual effects and textures that match the overall aesthetic. Apply creative forms like gradient meshes, noise textures, geometric patterns, layered transparencies, dramatic shadows, decorative borders, custom cursors, and grain overlays.

NEVER use generic AI-generated aesthetics like overused font families (Inter, Roboto, Arial, system fonts), cliched color schemes (particularly purple gradients on white backgrounds), predictable layouts and component patterns, and cookie-cutter design that lacks context-specific character.

Interpret creatively and make unexpected choices that feel genuinely designed for the context. No design should be the same. Vary between light and dark themes, different fonts, different aesthetics. NEVER converge on common choices (Space Grotesk, for example) across generations.

**IMPORTANT**: Match implementation complexity to the aesthetic vision. Maximalist designs need elaborate code with extensive animations and effects. Minimalist or refined designs need restraint, precision, and careful attention to spacing, typography, and subtle details. Elegance comes from executing the vision well.

Remember: Claude is capable of extraordinary creative work. Don't hold back, show what can truly be created when thinking outside the box and committing fully to a distinctive vision.
---

# Контекст проекта autodoc.tj (для работы в этом репозитории)

> Раздел выше — общие правила фронтенд-дизайна. Ниже — конкретика этого проекта.
> Полные карты: **ARCHITECTURE.md** (кодовая база), **NAVIGATION.md** (full-stack + история PR),
> **README.md** (установка/эксплуатация), **CATALOG_PLAN.md** (каталог и поиск).

## Что это
**AutoDoc / autodoc.tj** — интернет-магазин автозапчастей для Таджикистана, который
развивается в **нишевый маркетплейс** (мультипродавец, модель Ozon/WB).

## Стек и принципы
- **PHP 8 без фреймворка**, MySQL/MariaDB через PDO, каждая страница — отдельный файл.
- Шаблон **Mazlay** (Bootstrap 4 + jQuery + Owl Carousel), шрифт **Rubik**.
- Все наши стили — **`assets/css/custom.css`** (cache-busting через `?v=filemtime`).
  Файлы темы `assets/mazlay-*` не редактируем — только перебиваем в `custom.css`.
- Миграции **идемпотентны** (`CREATE TABLE IF NOT EXISTS`, `dbAddColumnIfMissing()`).

## Жёсткие правила
1. **Секреты — никогда в git.** API-ключи и пароли живут в таблице `site_settings`
   (форма в суперадминке) или в git-ignored `config/db_credentials.php`.
2. **В `main` не пушить напрямую** — ветка → PR → squash-merge.
3. **Не ломать рабочее.** Витрина должна работать после любого коммита; правки витрины
   делать точечно, не трогая логику AutoEuro/цен без явной задачи.
4. **Массовый перебор чужих API и парсинг сайтов поставщиков запрещён** — это бан рабочего
   аккаунта. Массовые данные берём из официальных выгрузок (прайс-лист AutoEuro).

## Ключевые подсистемы
| Подсистема | Где | Суть |
|---|---|---|
| VIN + каталог OEM | `pages/vin.php`, `includes/catalog/` | VIN → авто → узлы → схемы → OEM (провайдер Tradesoft/Parts-Catalogs) |
| Цены поставщика | `includes/catalog/AutoEuroPriceProvider.php` | AutoEuro: RUB → сомони по курсу + наценка + **надбавка Москва→Худжанд** (`autoeuro_khj_*`) |
| Поиск витрины | `search/index.php`, `includes/parts/supplier_cards.php` | артикул → живой AutoEuro; название/бренд → словарь `ae_part_dictionary` (цена всё равно живая) |
| Маркетплейс | `seller/`, `admin/sellers.php`, `admin/product_moderation.php` | продавцы выкладывают товары → модерация → каталог |
| Заказы по продавцам | `buyer/checkout.php`, `seller/orders.php`, `admin/orders.php` | заказ бьётся на подзаказы `order_sellers`; комиссия фиксируется в момент заказа |
| Библиотека каталога | `superadmin/catalog_library.php`, `catalog_library_*` | всё, что пришло от Parts-Catalogs, лежит у нас: повтор — без запросов к API |
| Заявки менеджеру | `api/vin_order_request.php`, `includes/messaging.php` | «Купить» у поставщика = заявка в переписку, не автозаказ |

## Что важно помнить про данные
- **AutoEuro API ищет только по артикулу** и **не отдаёт фото**. Поиск по названию работает
  через наш словарь; фото — наше по совпадению артикула, иначе заглушка.
- **Цены поставщика не кэшируем надолго** — они меняются; храним только названия.
- ⚠️ **Тариф Parts-Catalogs: правила расхода НЕ ПОДТВЕРЖДЕНЫ.** Ранее считалось, что обход
  дерева и сбор схем тариф не тратят — письмо Tradesoft (999/1000) это опровергло. Пакетный
  сбор **остановлен** до ответа поставщика; витрина живёт из библиотеки.
- **Токен комплектации протухает** — `groups2`/`parts2` начинают отвечать 404
  «Комплектация не найдена». Лечится только новым декодированием VIN (стоит запроса).
- **Полнота дерева узлов** — не 120, как было зашито, а 229…1000 узлов. Регулируется
  настройками `catalog_nodes_limit` (300, потолок 2000) и `catalog_nodes_depth` (5).
- **Всё, что связано со сбором каталога, — только superadmin/admin.** Покупатель не должен
  видеть ни имени поставщика, ни остатка квоты, ни того, что мы что-то собираем.

## Стадия
- **Фаза 1** (продавцы, товары, модерация) — **готова**.
- **Фаза 2** (заказы с разбивкой по продавцам, статусы, комиссия) — **готова**, сквозная
  проверка на боевом отложена заказчиком.
- **Фаза 3** (онлайн-оплата, комиссия/выплаты) — упирается в эквайринг в Таджикистане.
- **Фаза 4** (отзывы, возвраты/споры, buy-box) — не начата.
- **Каталог** — библиотека 96 машин / 87 деревьев / 10 099 схем, сбор на паузе (см. выше).

## Язык общения
Заказчик пишет по-русски — **отвечать по-русски**. Комментарии в коде — по-русски,
объясняющие *почему*, а не *что*.
