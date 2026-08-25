# Глава 31. Практика: схема базы магазина

> **Часть VI. База данных** · Глава 31 из 60
> [← Глава 30](30-indeksy.md) · [Глава 32 →](32-pdo.md)

## 🎯 Зачем эта глава

Соберём всё, что узнали, в готовую схему базы данных — ту самую, на которой
дальше будет работать магазин.

Это важная глава. Структура базы — фундамент: код переписать легко,
а перестроить таблицы, когда в них сто тысяч записей и работающий сайт, — тяжело.

Спроектируем сразу правильно, с запасом на маркетплейс из части IX.

## 📖 Сначала — что нам нужно хранить

Прежде чем писать `CREATE TABLE`, ответьте словами: какие **сущности** есть
в магазине?

| Сущность | Что это | Таблица |
|---|---|---|
| Пользователь | Покупатель, продавец, менеджер, админ | `polzovateli` |
| Продавец | Магазин на маркетплейсе | `prodavcy` |
| Категория | Раздел каталога | `kategorii` |
| Товар | Позиция каталога | `tovary` |
| Заказ | Покупка целиком | `zakazy` |
| Позиция заказа | Один товар в заказе | `zakaz_tovary` |
| Корзина | Что отложено, но не куплено | `korzina` |

Дальше — **связи**:

```
polzovateli ──1:1──→ prodavcy         один пользователь = один магазин
prodavcy    ──1:N──→ tovary           у продавца много товаров
kategorii   ──1:N──→ tovary           в категории много товаров
polzovateli ──1:N──→ zakazy           у покупателя много заказов
zakazy      ──1:N──→ zakaz_tovary     в заказе много позиций
tovary      ──1:N──→ zakaz_tovary     товар встречается во многих заказах
```

**1:N** («один ко многим») — самая частая связь. Читается: «у одного продавца
много товаров, но у каждого товара один продавец».

Реализуется просто: в таблице «многих» лежит `id` из таблицы «одного».
У `tovary` есть `prodavec_id`.

**N:M** («многие ко многим») — когда с обеих сторон много. Например, товар
подходит к нескольким автомобилям, и к одному автомобилю подходит много товаров.
Такая связь **всегда** требует третьей таблицы — как `zakaz_tovary`
между заказами и товарами.

## 💻 Схема целиком

```sql
-- ============================================================
-- Пользователи: покупатели, продавцы, менеджеры, администраторы
-- ============================================================
CREATE TABLE polzovateli (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    email        VARCHAR(255) NOT NULL,
    parol_hash   VARCHAR(255) NOT NULL COMMENT 'хеш, никогда не сам пароль',
    imya         VARCHAR(255) NOT NULL,
    telefon      VARCHAR(32)  NOT NULL DEFAULT '',
    rol          ENUM('pokupatel','prodavec','manager','admin')
                 NOT NULL DEFAULT 'pokupatel',
    aktivnyi     TINYINT(1) NOT NULL DEFAULT 1,
    sozdan       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    izmenen      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_email (email),
    KEY idx_rol (rol)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Продавцы: магазины на маркетплейсе
-- ============================================================
CREATE TABLE prodavcy (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    polzovatel_id     INT NOT NULL,
    nazvanie          VARCHAR(255) NOT NULL,
    gorod             VARCHAR(128) NOT NULL DEFAULT '',
    opisanie          TEXT,
    komissiya_procent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    status            ENUM('na_proverke','odobren','zablokirovan')
                      NOT NULL DEFAULT 'na_proverke',
    sozdan            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_polzovatel (polzovatel_id),
    KEY idx_status (status),
    CONSTRAINT fk_prodavec_polzovatel
        FOREIGN KEY (polzovatel_id) REFERENCES polzovateli(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Категории каталога, с вложенностью
-- ============================================================
CREATE TABLE kategorii (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    roditel_id    INT DEFAULT NULL COMMENT 'NULL = раздел верхнего уровня',
    nazvanie      VARCHAR(128) NOT NULL,
    chpu          VARCHAR(128) NOT NULL COMMENT 'для адреса: tormoznye-kolodki',
    poryadok      INT NOT NULL DEFAULT 0,
    aktivnaya     TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY uniq_chpu (chpu),
    KEY idx_roditel (roditel_id),
    CONSTRAINT fk_kategoriya_roditel
        FOREIGN KEY (roditel_id) REFERENCES kategorii(id)
        ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Товары
-- ============================================================
CREATE TABLE tovary (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    prodavec_id   INT DEFAULT NULL COMMENT 'NULL = товар самого магазина',
    kategoriya_id INT DEFAULT NULL,

    nazvanie      VARCHAR(255) NOT NULL,
    artikul       VARCHAR(64)  NOT NULL,
    brend         VARCHAR(64)  NOT NULL DEFAULT '',
    opisanie      TEXT,

    cena          INT NOT NULL COMMENT 'в дирамах: 1 сомони = 100',
    cena_zakupki  INT NOT NULL DEFAULT 0 COMMENT 'покупателю не показывается',
    ostatok       INT NOT NULL DEFAULT 0,

    foto          VARCHAR(255) NOT NULL DEFAULT '',
    status        ENUM('chernovik','na_moderacii','opublikovan','otklonen')
                  NOT NULL DEFAULT 'chernovik',
    aktivnyi      TINYINT(1) NOT NULL DEFAULT 1,

    sozdan        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    izmenen       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    KEY idx_artikul (artikul),
    KEY idx_brend (brend),
    KEY idx_prodavec (prodavec_id),
    KEY idx_kategoriya (kategoriya_id),
    KEY idx_katalog (aktivnyi, status, ostatok),
    FULLTEXT KEY ft_poisk (nazvanie, opisanie),

    CONSTRAINT fk_tovar_prodavec
        FOREIGN KEY (prodavec_id) REFERENCES prodavcy(id) ON DELETE RESTRICT,
    CONSTRAINT fk_tovar_kategoriya
        FOREIGN KEY (kategoriya_id) REFERENCES kategorii(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Заказы
-- ============================================================
CREATE TABLE zakazy (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    nomer           VARCHAR(32) NOT NULL COMMENT 'человеческий номер: 2026-001024',
    polzovatel_id   INT DEFAULT NULL COMMENT 'NULL = заказ без регистрации',

    klient_imya     VARCHAR(255) NOT NULL,
    klient_telefon  VARCHAR(32)  NOT NULL,
    klient_adres    VARCHAR(500) NOT NULL DEFAULT '',

    summa_tovarov   INT NOT NULL DEFAULT 0 COMMENT 'в дирамах',
    summa_dostavki  INT NOT NULL DEFAULT 0,
    summa_itogo     INT NOT NULL DEFAULT 0,

    sposob_dostavki ENUM('samovyvoz','kurier','pochta') NOT NULL DEFAULT 'samovyvoz',
    sposob_oplaty   ENUM('nalichnye','karta','perevod') NOT NULL DEFAULT 'nalichnye',

    status          ENUM('novyi','podtverzhden','sobran','otpravlen','dostavlen','otmenen')
                    NOT NULL DEFAULT 'novyi',
    kommentariy     TEXT,

    sozdan          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    izmenen         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_nomer (nomer),
    KEY idx_polzovatel (polzovatel_id),
    KEY idx_status (status),
    KEY idx_sozdan (sozdan),

    CONSTRAINT fk_zakaz_polzovatel
        FOREIGN KEY (polzovatel_id) REFERENCES polzovateli(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Позиции заказа
-- ============================================================
CREATE TABLE zakaz_tovary (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    zakaz_id          INT NOT NULL,
    tovar_id          INT DEFAULT NULL COMMENT 'NULL, если товар потом удалили',
    prodavec_id       INT DEFAULT NULL,

    -- Копии на момент заказа: их нельзя брать из справочников
    nazvanie          VARCHAR(255) NOT NULL,
    artikul           VARCHAR(64)  NOT NULL DEFAULT '',
    cena              INT NOT NULL COMMENT 'цена в момент покупки',
    kolichestvo       INT NOT NULL DEFAULT 1,
    komissiya_procent DECIMAL(5,2) NOT NULL DEFAULT 0,

    KEY idx_zakaz (zakaz_id),
    KEY idx_tovar (tovar_id),
    KEY idx_prodavec (prodavec_id),

    CONSTRAINT fk_pozicia_zakaz
        FOREIGN KEY (zakaz_id) REFERENCES zakazy(id) ON DELETE CASCADE,
    CONSTRAINT fk_pozicia_tovar
        FOREIGN KEY (tovar_id) REFERENCES tovary(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================================
-- Корзина
-- ============================================================
CREATE TABLE korzina (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    polzovatel_id INT DEFAULT NULL,
    sessiya       VARCHAR(128) NOT NULL DEFAULT '' COMMENT 'для незарегистрированных',
    tovar_id      INT NOT NULL,
    kolichestvo   INT NOT NULL DEFAULT 1,
    izmenena      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_pozicia (polzovatel_id, sessiya, tovar_id),
    KEY idx_izmenena (izmenena),

    CONSTRAINT fk_korzina_tovar
        FOREIGN KEY (tovar_id) REFERENCES tovary(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## 📖 Разбор ключевых решений

Каждое из них — ответ на реальную проблему. Разберём, чтобы вы понимали
не «как», а «почему».

### **1. `ENUM` для статусов**

```sql
status ENUM('novyi','podtverzhden','sobran','otpravlen','dostavlen','otmenen')
```

`ENUM` — список допустимых значений. База **не даст** записать что-то другое.

Альтернатива — `VARCHAR`, но тогда однажды в базе появятся `novyi`, `Новый`,
`new` и `NEW` — и никакие отчёты не сойдутся.

⚠️ Минус `ENUM`: добавить новый статус — значит изменить структуру таблицы,
а это блокировка на больших таблицах. Если статусов много и они меняются —
делайте отдельную таблицу-справочник.

### **2. Копии данных в позиции заказа**

```sql
nazvanie VARCHAR(255) NOT NULL,
cena INT NOT NULL,
komissiya_procent DECIMAL(5,2) NOT NULL
```

Мы уже говорили об этом в главе 29, но повторим — это важно.

В `zakaz_tovary` **дублируются** название, цена и процент комиссии. Кажется
нарушением правила «не дублировать».

Но это осознанное исключение. Заказ — **документ о факте**, а не ссылка
на текущее состояние. Товар подорожает, продавец сменит условия, товар вообще
уберут из каталога — старый заказ должен остаться таким, каким был.

**Правило: справочники хранят текущее, документы — зафиксированное.**

### **3. `ON DELETE` — что делать со связанными записями**

| Поведение | Что происходит | Где применили |
|---|---|---|
| `RESTRICT` | **Запретить** удаление, пока есть связанные | Продавец с товарами |
| `CASCADE` | Удалить связанные тоже | Позиции при удалении заказа |
| `SET NULL` | Обнулить ссылку | Категория у товара |

Выбор не случаен:

- Продавца с товарами удалить нельзя — `RESTRICT`. Иначе останутся товары-сироты.
- Позиции без заказа бессмысленны — `CASCADE`.
- Товар без категории существовать может — `SET NULL`.

### **4. Корзина в базе, а не только в браузере**

Помните `localStorage` из главы 18? Корзина там жила только в одном браузере.

Теперь она в базе, и это даёт:

- корзина переезжает между устройствами;
- видно, что люди откладывают, но не покупают;
- можно напомнить о брошенной корзине.

Поле `sessiya` — для незарегистрированных: корзина привязывается к сессии
браузера, а при входе переносится на пользователя.

### **5. Составной уникальный ключ**

```sql
UNIQUE KEY uniq_pozicia (polzovatel_id, sessiya, tovar_id)
```

Уникальность по **трём полям сразу**: один и тот же товар не может попасть
в одну корзину дважды. Второе добавление — не новая строка, а увеличение
количества.

База следит за этим сама, PHP не нужно ничего проверять.

### **6. Человеческий номер заказа**

```sql
nomer VARCHAR(32) NOT NULL COMMENT '2026-001024'
```

У заказа два номера: `id` для базы и `nomer` для людей.

Зачем: `id` выдаёт информацию о бизнесе. Заказ №7 говорит покупателю,
что вы работаете вторую неделю. Отдельный номер решает это и позволяет
задать любой формат.

## 📖 Заполняем начальными данными

```sql
-- Категории
INSERT INTO kategorii (nazvanie, chpu, poryadok) VALUES
('Тормозная система', 'tormoznaya-sistema', 1),
('Фильтры',           'filtry',             2),
('Двигатель',         'dvigatel',           3),
('Электрика',         'elektrika',          4),
('Подвеска',          'podveska',           5);

-- Подкатегории
INSERT INTO kategorii (roditel_id, nazvanie, chpu, poryadok) VALUES
(1, 'Тормозные колодки', 'tormoznye-kolodki', 1),
(1, 'Тормозные диски',   'tormoznye-diski',   2),
(2, 'Масляные фильтры',  'maslyanye-filtry',  1),
(2, 'Воздушные фильтры', 'vozdushnye-filtry', 2);

-- Администратор
INSERT INTO polzovateli (email, parol_hash, imya, rol) VALUES
('admin@autodoc.tj', '$2y$10$ЗАМЕНИТЬ_НА_НАСТОЯЩИЙ_ХЕШ', 'Администратор', 'admin');
```

⚠️ **`parol_hash`, а не `parol`.** Пароли **никогда** не хранятся в открытом виде.
Ни в каком виде, ни при каких обстоятельствах. Как правильно — в главе 39.

## 📖 Проверьте себя перед тем, как продолжить

Хороший приём: прежде чем писать код, убедитесь, что схема отвечает
на нужные вопросы. Попробуйте написать запросы для:

1. Каталог: активные опубликованные товары с остатком, по 20 на странице.
2. Товары конкретного продавца.
3. Все заказы покупателя с суммами.
4. Состав заказа с названиями товаров.
5. Сколько заработал каждый продавец за месяц.
6. Товары на модерации.
7. Брошенные корзины старше трёх дней.
8. Топ-10 продаваемых товаров.

Если для какого-то вопроса данных не хватает — **лучше узнать это сейчас**,
а не когда база уже заполнена.

## ⚠️ Грабли

**Хранить пароль в открытом виде.** Утечка базы = утечка всех паролей,
включая те, которыми люди пользуются в почте и банке.

**Не фиксировать цену в заказе.** История будет переписываться при каждом
изменении прайса.

**`FLOAT` для денег.** Суммы разойдутся.

**Забыть индекс на внешнем ключе.** Каждый `JOIN` — полный перебор.

**Не думать про `ON DELETE`.** Однажды кто-то удалит продавца, и половина
каталога останется без владельца.

**Одна таблица на всё.** Товары, заказы и пользователи в одной таблице
с полем «тип» — так делать нельзя.

**Не заложить `sozdan` и `izmenen`.** Понадобятся обязательно, а прошлое
не восстановить.

## 🏋️ Задачи

**Задача 31.1.** Создайте всю схему у себя. Проверьте, что внешние ключи
работают: попробуйте добавить товар несуществующего продавца.

**Задача 31.2.** Напишите все восемь запросов из раздела «Проверьте себя».

**Задача 31.3.** Спроектируйте таблицу отзывов: оценка, текст, кто написал,
о каком товаре, дата, статус модерации. Какие индексы нужны?

**Задача 31.4.** Спроектируйте связь «товар подходит к автомобилю».
Понадобятся таблицы марок, моделей и связей.

**Задача 31.5.** Что произойдёт при каждой попытке? Ответьте, потом проверьте:

```sql
DELETE FROM prodavcy WHERE id = 1;         -- у продавца есть товары
DELETE FROM zakazy WHERE id = 101;         -- у заказа есть позиции
DELETE FROM kategorii WHERE id = 1;        -- у категории есть товары
```

**Задача 31.6.** Добавьте таблицу для журнала действий администраторов:
кто, что, когда, над каким объектом.

**Задача 31.7.** Придумайте, как хранить несколько фотографий у одного товара.
Сейчас поле `foto` одно.

**Задача 31.8.** Спроектируйте таблицу для истории изменения цен товара.
Зачем она может понадобиться?

**Задача 31.9.** Сравните свою схему со схемой в `sql/` этого репозитория.
Что есть там, чего нет у вас? Выпишите три поля и объясните, зачем каждое.

*Ответы — в [Приложении Г](../prilozheniya/4-G-otvety.md).*

## 🔗 В бою

Откройте `sql/` — там вся структура настоящего магазина в виде миграций.

Вы увидите таблицы, которых у нас пока нет: `order_sellers` (подзаказы
по продавцам), `seller_ledger` (журнал начислений), `seller_payouts` (реестр
выплат). Это Фаза 3 проекта — деньги продавцов.

Особенно интересна `seller_ledger`. Баланс продавца там **не хранится числом**.
Хранятся все движения: начислено, удержано, выплачено. Баланс — это `SUM`
по журналу.

Почему так: число можно случайно перезаписать, и никто не узнает, откуда оно
взялось. Журнал позволяет объяснить каждый сомони. Так устроена любая
бухгалтерия в мире, и это одно из тех решений, которые окупаются при первом же
споре с продавцом.

## 📌 Итог

- Сначала **сущности и связи словами**, потом `CREATE TABLE`.
- **1:N** — `id` в таблице «многих». **N:M** — всегда третья таблица.
- `ENUM` для статусов: база сама не даст записать мусор.
- **Справочники хранят текущее, документы — зафиксированное.** Цена и комиссия
  копируются в позицию заказа.
- `ON DELETE`: `RESTRICT` защищает, `CASCADE` удаляет вместе, `SET NULL` обнуляет.
- Корзина в базе переезжает между устройствами.
- Составной `UNIQUE KEY` защищает от дублей на уровне базы.
- Отдельный человеческий номер заказа не выдаёт обороты бизнеса.
- **Пароли только хешами.**
- Проверьте схему запросами **до** того, как зальёте данные.

**Часть VI закончена.** База спроектирована.

Дальше — соединим её с PHP и наконец заменим массив в файле на настоящие данные.

[← Глава 30](30-indeksy.md) · [Глава 32. PDO: подключаемся к базе →](32-pdo.md)
