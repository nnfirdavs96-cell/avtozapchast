# Глава 48. Buy-box: одна карточка, много продавцов

> **Часть IX. Маркетплейс** · Глава 48 из 60
> [← Глава 47](47-balansy-i-vyplaty.md) · [Глава 49 →](49-http-i-json.md)

## 🎯 Зачем эта глава

Три продавца выложили одни и те же колодки Bosch с артикулом `0986424815`.
В каталоге появилось три одинаковые карточки, отличающиеся только ценой
и продавцом.

Покупатель видит дубли, не понимает разницы и уходит. Поиск засорён. SEO
страдает: три страницы конкурируют друг с другом за один и тот же запрос.

**Buy-box** — решение, которое придумали крупные маркетплейсы: **одна карточка
товара, много предложений**. Покупатель видит товар, а не список продавцов.
Площадка выбирает лучшее предложение и показывает его кнопкой «Купить».

Разберём, как это устроено.

## 📖 Разделяем товар и предложение

Ключевая идея: **товар** и **предложение** — разные сущности.

| | Товар (карточка) | Предложение (оффер) |
|---|---|---|
| Что описывает | Что это за вещь | Условия конкретного продавца |
| Поля | Название, артикул, бренд, описание, фото | Цена, остаток, срок, продавец |
| Сколько на артикул | **Одна** | Сколько угодно |
| Кто владеет | Площадка | Продавец |

```sql
-- Карточка товара: одна на артикул
CREATE TABLE tovary (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    artikul       VARCHAR(64) NOT NULL,
    brend         VARCHAR(64) NOT NULL DEFAULT '',
    nazvanie      VARCHAR(255) NOT NULL,
    opisanie      TEXT,
    kategoriya_id INT DEFAULT NULL,
    foto          VARCHAR(255) NOT NULL DEFAULT '',
    aktivnyi      TINYINT(1) NOT NULL DEFAULT 1,

    UNIQUE KEY uniq_artikul_brend (artikul, brend)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Предложения продавцов на эту карточку
CREATE TABLE predlozheniya (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    tovar_id     INT NOT NULL,
    prodavec_id  INT NOT NULL,

    cena         INT NOT NULL COMMENT 'в дирамах',
    ostatok      INT NOT NULL DEFAULT 0,
    srok_dney    INT NOT NULL DEFAULT 0 COMMENT '0 = со склада',
    sostoyanie   ENUM('novoe','b_u','vosstanovlennoe') NOT NULL DEFAULT 'novoe',

    status       ENUM('chernovik','na_moderacii','aktivno','otkloneno','skryto')
                 NOT NULL DEFAULT 'chernovik',

    sozdano      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    izmeneno     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_tovar_prodavec (tovar_id, prodavec_id),
    KEY idx_vybor (tovar_id, status, ostatok, cena),

    CONSTRAINT fk_pred_tovar FOREIGN KEY (tovar_id) REFERENCES tovary(id) ON DELETE CASCADE,
    CONSTRAINT fk_pred_prodavec FOREIGN KEY (prodavec_id) REFERENCES prodavcy(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

⚠️ **`UNIQUE KEY (tovar_id, prodavec_id)`** — один продавец, одно предложение
на карточку. Без этого продавец выложил бы десять предложений на один товар
и занял всю выдачу.

## 📖 Как продавец попадает на существующую карточку

Продавец вводит артикул. Дальше система решает сама:

```php
/**
 * Найти карточку по артикулу или создать новую.
 * Возвращает id товара.
 */
function nayti_ili_sozdat_kartochku(string $artikul, string $brend, array $d): int
{
    $artikul = mb_strtoupper(preg_replace('/[\s\-\.]/', '', trim($artikul)));
    $brend = trim($brend);

    // Ищем существующую
    $est = zapros_odin('
        SELECT id FROM tovary WHERE artikul = ? AND brend = ?
    ', [$artikul, $brend]);

    if ($est !== null) {
        return (int) $est['id'];
    }

    // Нет такой — заводим новую, она пойдёт на модерацию
    vypolnit('
        INSERT INTO tovary (artikul, brend, nazvanie, opisanie, kategoriya_id, aktivnyi)
        VALUES (?, ?, ?, ?, ?, 1)
    ', [$artikul, $brend, $d['nazvanie'], $d['opisanie'] ?? '',
        $d['kategoriya_id'] ?: null]);

    return posledniy_id();
}
```

### **Про нормализацию артикула**

Обратите внимание на первую строку:

```php
$artikul = mb_strtoupper(preg_replace('/[\s\-\.]/', '', trim($artikul)));
```

Один и тот же артикул продавцы напишут по-разному:

```
0986424815
0986-424-815
0986 424 815
0986.424.815
```

Без приведения к единому виду получится четыре карточки вместо одной —
то есть ровно та проблема, которую buy-box должен решать.

**Правило: всё, по чему сравниваете, нужно приводить к единому виду.**
Это касается артикулов, телефонов, почтовых адресов, названий брендов.

⚠️ Осторожно: слишком агрессивная нормализация склеит **разные** товары.
Артикулы `AB-123` и `AB123` почти наверняка одно и то же, а вот `123` и `1.23` —
уже вопрос. Начинайте с мягкой нормализации и смотрите на дубли.

## 📖 Выбор победителя

Главный вопрос buy-box: **чьё предложение показать кнопкой «Купить»**.

Наивный ответ — самое дешёвое. Но это плохо работает:

- продавец с ценой на сомони ниже, но сроком 10 дней выиграет
  у того, кто отгрузит сегодня;
- продавец с одной штукой на складе получит все заказы и не справится;
- ненадёжный продавец с низкой ценой испортит впечатление о площадке.

**Правильный выбор — по совокупности условий.**

```php
/**
 * Лучшее предложение по карточке.
 *
 * Порядок важен: сначала отсекаем непригодные, потом сортируем
 * по тому, что реально важно покупателю.
 */
function luchshee_predlozhenie(int $tovar_id): ?array
{
    return zapros_odin('
        SELECT pr.*, p.nazvanie AS prodavec, p.gorod, p.reyting
        FROM predlozheniya AS pr
        INNER JOIN prodavcy AS p ON p.id = pr.prodavec_id
        WHERE pr.tovar_id = ?
          AND pr.status = "aktivno"
          AND pr.ostatok > 0
          AND p.status = "odobren"
        ORDER BY
            pr.srok_dney ASC,      -- 1. кто отгрузит быстрее
            pr.cena ASC,           -- 2. кто дешевле
            p.reyting DESC,        -- 3. у кого репутация лучше
            pr.ostatok DESC        -- 4. у кого больше запас
        LIMIT 1
    ', [$tovar_id]);
}

/** Все предложения по карточке — для блока «другие продавцы». */
function vse_predlozheniya(int $tovar_id): array
{
    return zapros('
        SELECT pr.*, p.nazvanie AS prodavec, p.gorod, p.reyting
        FROM predlozheniya AS pr
        INNER JOIN prodavcy AS p ON p.id = pr.prodavec_id
        WHERE pr.tovar_id = ?
          AND pr.status = "aktivno"
          AND pr.ostatok > 0
          AND p.status = "odobren"
        ORDER BY pr.srok_dney ASC, pr.cena ASC
    ', [$tovar_id]);
}
```

### **Почему срок важнее цены**

Это неочевидное решение, и оно намеренное.

Человек ищет запчасть, потому что **машина не едет**. Разница в 20 сомони
его волнует меньше, чем разница между «сегодня» и «через неделю».

Для магазина запчастей срок — главный фактор. Для магазина книг или одежды
порядок был бы другим: там цена важнее.

**Вывод шире технического: правила ранжирования должны отражать то, что
на самом деле важно вашему покупателю.** Скопировать их у Ozon нельзя —
у него другой товар и другая ситуация покупки.

## 💻 Карточка товара с предложениями

```php
<?php
// tovar.php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$tovar = zapros_odin('
    SELECT t.*, k.nazvanie AS kategoriya
    FROM tovary AS t
    LEFT JOIN kategorii AS k ON k.id = t.kategoriya_id
    WHERE t.id = ? AND t.aktivnyi = 1
', [$id]);

if ($tovar === null) {
    http_response_code(404);
    exit('Товар не найден');
}

$luchshee = luchshee_predlozhenie($id);
$vse = vse_predlozheniya($id);
$drugie = array_filter($vse, fn($p) => (int) $p['id'] !== (int) ($luchshee['id'] ?? 0));

$zagolovok = $tovar['nazvanie'] . ' — ' . SAIT_NAZVANIE;
require __DIR__ . '/includes/header.php';
?>

<article class="tovar-podrobno">
    <h1><?= e($tovar['nazvanie']) ?></h1>
    <p class="tihoe">
        Артикул: <?= e($tovar['artikul']) ?>
        <?php if ($tovar['brend']): ?> · <?= e($tovar['brend']) ?><?php endif; ?>
    </p>

    <?php if ($luchshee === null): ?>

        <div class="net-predlozheniy">
            <h2>Нет в продаже</h2>
            <p>Сейчас этот товар никто не продаёт. Оставьте заявку —
               сообщим, когда появится.</p>
            <a class="knopka" href="zayavka.php?tovar=<?= (int) $tovar['id'] ?>">
                Сообщить о поступлении
            </a>
        </div>

    <?php else: ?>

        <div class="buy-box">
            <div class="bb-cena"><?= somoni((int) $luchshee['cena']) ?> <span>сомони</span></div>

            <div class="bb-usloviya">
                <?php if ((int) $luchshee['srok_dney'] === 0): ?>
                    <span class="bb-srok bystro">Со склада, сегодня</span>
                <?php else: ?>
                    <span class="bb-srok">Под заказ, <?= (int) $luchshee['srok_dney'] ?>
                        <?= sklonenie((int) $luchshee['srok_dney'], 'день', 'дня', 'дней') ?></span>
                <?php endif; ?>

                <span class="bb-ostatok">В наличии: <?= (int) $luchshee['ostatok'] ?> шт.</span>
            </div>

            <div class="bb-prodavec">
                Продавец: <strong><?= e($luchshee['prodavec']) ?></strong>
                <?php if ($luchshee['gorod']): ?>
                    · <?= e($luchshee['gorod']) ?>
                <?php endif; ?>
                <?php if ($luchshee['reyting']): ?>
                    · рейтинг <?= number_format((float) $luchshee['reyting'], 1) ?>
                <?php endif; ?>
            </div>

            <form method="POST" action="korzina.php">
                <?= csrf_pole() ?>
                <input type="hidden" name="deystvie" value="dobavit">
                <!-- В корзину кладём ПРЕДЛОЖЕНИЕ, а не карточку:
                     покупатель выбрал условия конкретного продавца -->
                <input type="hidden" name="predlozhenie_id" value="<?= (int) $luchshee['id'] ?>">
                <input type="number" name="kolichestvo" value="1" min="1"
                       max="<?= (int) $luchshee['ostatok'] ?>">
                <button type="submit">В корзину</button>
            </form>
        </div>

        <?php if ($drugie): ?>
            <section class="drugie-predlozheniya">
                <h2>Ещё <?= count($drugie) ?>
                    <?= sklonenie(count($drugie), 'предложение', 'предложения', 'предложений') ?></h2>

                <table class="spisok">
                    <thead>
                        <tr><th>Продавец</th><th>Срок</th><th>Наличие</th>
                            <th>Цена</th><th></th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($drugie as $p): ?>
                        <tr>
                            <td>
                                <?= e($p['prodavec']) ?>
                                <?php if ($p['gorod']): ?>
                                    <div class="tihoe"><?= e($p['gorod']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= (int) $p['srok_dney'] === 0
                                    ? 'сегодня'
                                    : (int) $p['srok_dney'] . ' дн.' ?></td>
                            <td class="chislo-yacheyka"><?= (int) $p['ostatok'] ?> шт.</td>
                            <td class="chislo-yacheyka mono"><?= somoni((int) $p['cena']) ?></td>
                            <td>
                                <form method="POST" action="korzina.php" class="vstroennaya">
                                    <?= csrf_pole() ?>
                                    <input type="hidden" name="deystvie" value="dobavit">
                                    <input type="hidden" name="predlozhenie_id"
                                           value="<?= (int) $p['id'] ?>">
                                    <button type="submit" class="knopka-tihaya">Выбрать</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>

    <?php endif; ?>

    <?php if (!empty($tovar['opisanie'])): ?>
        <section class="opisanie-tovara">
            <h2>Описание</h2>
            <p><?= nl2br(e($tovar['opisanie'])) ?></p>
        </section>
    <?php endif; ?>
</article>

<?php require __DIR__ . '/includes/footer.php'; ?>
```

### **Ключевая деталь: в корзину кладём предложение**

```html
<input type="hidden" name="predlozhenie_id" value="...">
```

Не `tovar_id`, а `predlozhenie_id`. Причина важная.

Покупатель выбрал **конкретное предложение**: эту цену, этот срок,
этого продавца. Если положить в корзину карточку, то при оформлении система
снова выберет «лучшее» — и оно может оказаться другим.

Человек добавил товар за 250 у продавца из Худжанда, а получил за 245
из Душанбе со сроком неделя. Формально дешевле, фактически — не то,
что он выбирал.

**Правило: в корзине лежит то, что человек выбрал, а не то, что система
считает лучшим.**

## 📖 Каталог с buy-box

```php
function katalog_s_buy_box(array $filtry, int $stranica = 1, int $na_stranice = 12): array
{
    // Подзапрос выбирает минимальную цену среди активных предложений.
    // Так каталог показывает "от какой цены" и не дублирует карточки.
    $sql = '
        SELECT t.id, t.nazvanie, t.artikul, t.brend, t.foto,
               MIN(pr.cena) AS min_cena,
               MIN(pr.srok_dney) AS min_srok,
               COUNT(DISTINCT pr.prodavec_id) AS prodavcov,
               SUM(pr.ostatok) AS vsego_ostatok
        FROM tovary AS t
        INNER JOIN predlozheniya AS pr ON pr.tovar_id = t.id
        INNER JOIN prodavcy AS p ON p.id = pr.prodavec_id
        WHERE t.aktivnyi = 1
          AND pr.status = "aktivno"
          AND pr.ostatok > 0
          AND p.status = "odobren"
        GROUP BY t.id, t.nazvanie, t.artikul, t.brend, t.foto
        ORDER BY t.nazvanie
        LIMIT ' . (int) $na_stranice . ' OFFSET ' . (int) (($stranica - 1) * $na_stranice);

    return zapros($sql);
}
```

Карточка в каталоге показывает:

```php
<article class="tovar">
    <h3><a href="tovar.php?id=<?= (int) $t['id'] ?>"><?= e($t['nazvanie']) ?></a></h3>
    <p class="cena">
        <?php if ((int) $t['prodavcov'] > 1): ?>
            <span class="ot">от</span>
        <?php endif; ?>
        <?= somoni((int) $t['min_cena']) ?> <span>сомони</span>
    </p>
    <?php if ((int) $t['prodavcov'] > 1): ?>
        <p class="tihoe"><?= (int) $t['prodavcov'] ?>
            <?= sklonenie((int) $t['prodavcov'], 'продавец', 'продавца', 'продавцов') ?></p>
    <?php endif; ?>
</article>
```

Слово «от» появляется, только если продавцов больше одного. Мелочь,
но она честно объясняет, почему цена в каталоге и в карточке может отличаться.

## 📖 Что buy-box меняет в остальном коде

Переход на эту схему затрагивает почти всё:

| Что | Как было | Как стало |
|---|---|---|
| Корзина | `tovar_id` | **`predlozhenie_id`** |
| Списание остатка | `tovary.ostatok` | **`predlozheniya.ostatok`** |
| Продавец заказа | `tovary.prodavec_id` | **`predlozheniya.prodavec_id`** |
| Модерация | Товар целиком | Карточка и предложение **отдельно** |
| Поиск | По товарам | По карточкам, цена — минимальная |

⚠️ **Это серьёзная переделка.** Именно поэтому buy-box не делают
с первого дня: сначала магазин должен заработать в простой схеме,
и только когда появляются дубли — есть смысл переходить.

В проекте autodoc.tj buy-box внедрялся **четырьмя этапами**, каждый
отдельным изменением: модель данных → витрина → карточка → формы товаров.
Так безопаснее, чем менять всё разом.

**Правило больших переделок: делите на этапы, каждый из которых оставляет
сайт работающим.**

## 🔤 Разбор по словам

| Запись | Что означает |
|---|---|
| **Buy-box** | Одна карточка товара, много предложений продавцов |
| **Карточка** | Что это за вещь: название, артикул, описание |
| **Предложение** (оффер) | Условия продавца: цена, срок, остаток |
| `UNIQUE (tovar_id, prodavec_id)` | Одно предложение от продавца на карточку |
| Нормализация артикула | Приведение к единому виду перед сравнением |
| `ORDER BY srok, cena, reyting` | Правила выбора победителя |
| `predlozhenie_id` в корзине | Покупатель выбрал конкретные условия |
| `MIN(cena)` в каталоге | Цена «от» при нескольких продавцах |

## ⚠️ Грабли

**Не нормализовать артикул.** Получите дубли вместо одной карточки.

**Слишком агрессивная нормализация.** Склеите разные товары.

**Выбирать победителя только по цене.** Покупатель получит долгий срок
и плохого продавца.

**Класть в корзину карточку вместо предложения.** Человек получит не то,
что выбирал.

**Не показывать другие предложения.** Смысл маркетплейса в выборе.

**Разрешить продавцу несколько предложений на карточку.** Займёт всю выдачу.

**Не пересчитывать победителя при изменении остатка.** Кнопка «Купить»
будет вести к тому, чего нет.

**Делать buy-box сразу.** Сначала пусть заработает простая схема.

## 🏋️ Задачи

**Задача 48.1.** Создайте таблицу предложений и перенесите товары в новую
схему.

**Задача 48.2.** Реализуйте нормализацию артикула. Проверьте на четырёх
вариантах написания.

**Задача 48.3.** Напишите выбор победителя и проверьте на трёх предложениях
с разными сроками и ценами.

**Задача 48.4.** Сделайте карточку с buy-box и блоком других предложений.

**Задача 48.5.** Переведите корзину на `predlozhenie_id`. Что ещё пришлось
поменять?

**Задача 48.6.** Сделайте каталог с ценой «от» и количеством продавцов.

**Задача 48.7.** Придумайте свои правила выбора победителя для магазина
продуктов. Что там важнее — срок, цена или что-то ещё?

**Задача 48.8.** Что произойдёт, если у победителя закончится товар,
пока покупатель заполняет форму заказа? Продумайте и реализуйте.

**Задача 48.9.** Разбейте переход на buy-box на четыре этапа так, чтобы
после каждого сайт продолжал работать. Запишите план.

*Ответы — в [Приложении Г](../prilozheniya/4-G-otvety.md).*

## 🔗 В бою

Buy-box — самая свежая часть проекта autodoc.tj, и её внедрение хорошо
задокументировано в истории изменений: четыре последовательных этапа
от модели данных до форм товаров.

Загляните в код витрины и карточки товара. Обратите внимание, что
в каталоге группировка идёт по карточке, а цена берётся минимальная
среди активных предложений — ровно как в этой главе.

И одна практическая деталь: при переходе на buy-box в проекте пришлось
**дважды править запросы**, потому что первая версия использовала оконные
функции, которые повели себя иначе на боевой версии MySQL, чем на тестовой.

Вывод на будущее: **проверяйте на той же версии базы, что стоит на бою**.
Различия между версиями встречаются там, где их не ждёшь.

## 📌 Итог

- **Buy-box**: одна карточка товара, много предложений продавцов.
- **Товар** — что это за вещь. **Предложение** — условия конкретного продавца.
- Один продавец = одно предложение на карточку.
- **Нормализуйте артикул** перед сравнением, иначе будут дубли.
- Победитель выбирается **по совокупности**: срок, цена, рейтинг, запас.
- Правила ранжирования зависят от товара. Для запчастей **срок важнее цены**.
- В корзину кладём **предложение**, а не карточку.
- В каталоге — **цена «от»** и количество продавцов.
- Переход на buy-box делится на **этапы**, каждый оставляет сайт работающим.

**Часть IX закончена.** У вас маркетплейс: продавцы, модерация, комиссии,
выплаты, buy-box.

Дальше — выход во внешний мир: чужие API, курсы валют, кэш.

[← Глава 47](47-balansy-i-vyplaty.md) · [Глава 49. HTTP-запросы и JSON →](49-http-i-json.md)
