# Глава 47. Балансы и выплаты

> **Часть IX. Маркетплейс** · Глава 47 из 60
> [← Глава 46](46-razbivka-zakaza.md) · [Глава 48 →](48-buy-box.md)

## 🎯 Зачем эта глава

Продавцы отгрузили товар. Покупатели заплатили площадке. Теперь площадка
должна рассчитаться с продавцами.

Здесь начинается **бухгалтерия**, и подход к ней принципиально отличается
от всего, что мы делали раньше. Обычные данные можно поправить, если ошиблись.
Деньги — нельзя: любое изменение должно быть объяснимо.

В этой главе — приём, на котором держится любая финансовая система мира.
Он простой, и после него вы будете смотреть на хранение денег иначе.

## 📖 Главное правило: баланс не хранят

Естественное решение — добавить продавцу поле:

```sql
-- ❌ Так делать нельзя
ALTER TABLE prodavcy ADD COLUMN balans INT NOT NULL DEFAULT 0;
```

И менять его: заказ доставлен — прибавили, выплатили — убавили.

**Работать будет. Но однажды случится следующее.**

Продавец пишет: «у меня 3400 сомони, а должно быть 3800». Вы открываете базу
и видите число `340000`. И **не можете объяснить, откуда оно взялось**.
Было ли начисление по заказу №112? Была ли выплата? Не сбилось ли что-то
при обновлении?

Число не помнит своей истории.

### **Правильно: журнал операций**

```sql
CREATE TABLE seller_ledger (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    prodavec_id   INT NOT NULL,

    tip           ENUM('nachislenie','uderzhanie','vyplata','korrektirovka')
                  NOT NULL,
    summa         INT NOT NULL COMMENT 'в дирамах: плюс приход, минус расход',

    -- На что ссылается операция
    zakaz_id         INT DEFAULT NULL,
    order_seller_id  INT DEFAULT NULL,
    vyplata_id       INT DEFAULT NULL,

    kommentariy   VARCHAR(255) NOT NULL DEFAULT '',
    sozdal_id     INT DEFAULT NULL COMMENT 'кто провёл, если вручную',
    sozdano       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_prodavec (prodavec_id, sozdano),
    KEY idx_order_seller (order_seller_id),

    CONSTRAINT fk_ledger_prodavec
        FOREIGN KEY (prodavec_id) REFERENCES prodavcy(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

**Баланс — это не поле, а сумма журнала:**

```php
function balans_prodavca(int $prodavec_id): int
{
    return (int) zapros_znachenie('
        SELECT COALESCE(SUM(summa), 0) FROM seller_ledger WHERE prodavec_id = ?
    ', [$prodavec_id]);
}
```

### **Что это даёт**

| Свойство | Почему важно |
|---|---|
| **Каждый сомони объясним** | Видно, из каких операций сложился баланс |
| **Ничего не теряется** | Записи только добавляются, не изменяются |
| **Ошибка исправляется записью** | Не «поправить число», а «провести корректировку» |
| **История полная** | Можно посчитать баланс на любую дату в прошлом |

⚠️ **Записи журнала никогда не изменяют и не удаляют.** Ошиблись — добавляют
корректирующую запись с объяснением. Так устроена бухгалтерия последние
пятьсот лет, и не зря.

Такой подход называется **append-only** — «только добавление».

## 📖 Когда начислять

Ключевой вопрос: в какой момент деньги становятся деньгами продавца?

| Момент | Риск |
|---|---|
| Заказ создан | Покупатель откажется — придётся отбирать назад |
| Заказ отправлен | Потеряется по дороге — то же самое |
| **Заказ доставлен** | **Разумный компромисс** |
| Через 14 дней после доставки | Учитывает возвраты, но продавцы ждут долго |

**Начисляем при доставке.** Возвраты обрабатываем отдельной операцией
удержания — так честнее и понятнее обеим сторонам.

```php
/**
 * Начислить продавцу за доставленный подзаказ.
 * Вызывается один раз при переходе подзаказа в статус «доставлен».
 */
function nachislit_za_podzakaz(int $order_seller_id): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pz = zapros_odin('
            SELECT * FROM order_sellers WHERE id = ? FOR UPDATE
        ', [$order_seller_id]);

        if ($pz === null) {
            throw new RuntimeException('Подзаказ не найден');
        }
        if ($pz['status'] !== 'dostavlen') {
            throw new RuntimeException('Начисляем только за доставленные заказы');
        }

        // Защита от повторного начисления — самое важное здесь
        $uzhe = zapros_znachenie('
            SELECT id FROM seller_ledger
            WHERE order_seller_id = ? AND tip = "nachislenie"
        ', [$order_seller_id]);

        if ($uzhe) {
            $pdo->rollBack();
            return;   // уже начислено, молча выходим
        }

        // Начисляем полную стоимость товаров
        vypolnit('
            INSERT INTO seller_ledger
                (prodavec_id, tip, summa, zakaz_id, order_seller_id, kommentariy)
            VALUES (?, "nachislenie", ?, ?, ?, ?)
        ', [
            $pz['prodavec_id'],
            (int) $pz['summa_tovarov'],
            $pz['zakaz_id'],
            $order_seller_id,
            'Начисление за заказ',
        ]);

        // И тут же удерживаем комиссию — отдельной записью
        if ((int) $pz['komissiya_summa'] > 0) {
            vypolnit('
                INSERT INTO seller_ledger
                    (prodavec_id, tip, summa, zakaz_id, order_seller_id, kommentariy)
                VALUES (?, "uderzhanie", ?, ?, ?, ?)
            ', [
                $pz['prodavec_id'],
                -(int) $pz['komissiya_summa'],
                $pz['zakaz_id'],
                $order_seller_id,
                sprintf('Комиссия %s%%', rtrim(rtrim((string) $pz['komissiya_procent'], '0'), '.')),
            ]);
        }

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
```

### **Почему две записи, а не одна**

Можно было бы начислить сразу `k_vyplate` — стоимость минус комиссия.
Одна запись вместо двух.

Но тогда **пропадает информация**. Продавец видит «начислено 450» и не знает,
что товаров было на 500, а 50 удержала площадка.

Две записи делают расчёт прозрачным:

```
Начисление за заказ №112       +500.00
Комиссия 10%                    −50.00
                              ─────────
                                450.00
```

**Прозрачность в расчётах дороже экономии одной строки в базе.** Продавец,
который понимает, откуда взялась сумма, не пишет претензий.

### **Защита от двойного начисления**

Обратите внимание на проверку `$uzhe`. Она критична.

Менеджер может случайно дважды перевести заказ в «доставлен». Или скрипт
запустится повторно. Или продавец нажмёт кнопку дважды.

Без проверки продавцу начислится вдвое — и заметят это в лучшем случае
при сверке через месяц.

**Правило: любая денежная операция должна быть защищена от повторения.**
Это свойство называется **идемпотентность** — повторный вызов не меняет результат.

## 💻 Выплаты

```sql
CREATE TABLE seller_payouts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    prodavec_id  INT NOT NULL,
    summa        INT NOT NULL COMMENT 'в дирамах, всегда положительная',

    status       ENUM('sozdana','v_rabote','vyplachena','otmenena')
                 NOT NULL DEFAULT 'sozdana',
    sposob       ENUM('nalichnye','perevod','karta') NOT NULL DEFAULT 'perevod',
    rekvizity    VARCHAR(255) NOT NULL DEFAULT '',
    kommentariy  VARCHAR(255) NOT NULL DEFAULT '',

    sozdal_id    INT DEFAULT NULL,
    sozdano      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    vyplacheno   DATETIME DEFAULT NULL,

    KEY idx_prodavec (prodavec_id, status),

    CONSTRAINT fk_payout_prodavec
        FOREIGN KEY (prodavec_id) REFERENCES prodavcy(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```php
/**
 * Создать выплату. Деньги списываются с баланса сразу, при создании, —
 * чтобы одну и ту же сумму нельзя было выплатить дважды.
 */
function sozdat_vyplatu(int $prodavec_id, int $summa, string $sposob,
                        string $rekvizity, string $kommentariy = ''): int
{
    if ($summa <= 0) {
        throw new RuntimeException('Сумма выплаты должна быть больше нуля');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // Блокируем журнал этого продавца, чтобы баланс не изменился
        // между проверкой и списанием
        zapros('SELECT id FROM seller_ledger WHERE prodavec_id = ? FOR UPDATE',
               [$prodavec_id]);

        $balans = balans_prodavca($prodavec_id);

        if ($summa > $balans) {
            throw new RuntimeException(sprintf(
                'На балансе %s сомони, выплатить %s нельзя',
                somoni($balans), somoni($summa)
            ));
        }

        vypolnit('
            INSERT INTO seller_payouts
                (prodavec_id, summa, sposob, rekvizity, kommentariy, sozdal_id)
            VALUES (?, ?, ?, ?, ?, ?)
        ', [$prodavec_id, $summa, $sposob, $rekvizity, $kommentariy,
            tekushiy_polzovatel()['id'] ?? null]);

        $vyplata_id = posledniy_id();

        // Списываем с баланса записью в журнал
        vypolnit('
            INSERT INTO seller_ledger
                (prodavec_id, tip, summa, vyplata_id, kommentariy, sozdal_id)
            VALUES (?, "vyplata", ?, ?, ?, ?)
        ', [$prodavec_id, -$summa, $vyplata_id,
            'Выплата №' . $vyplata_id, tekushiy_polzovatel()['id'] ?? null]);

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    return $vyplata_id;
}

/** Отмена выплаты возвращает деньги на баланс — тоже записью, а не удалением. */
function otmenit_vyplatu(int $vyplata_id, string $prichina): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $v = zapros_odin('SELECT * FROM seller_payouts WHERE id = ? FOR UPDATE',
                         [$vyplata_id]);

        if ($v === null) {
            throw new RuntimeException('Выплата не найдена');
        }
        if ($v['status'] === 'vyplachena') {
            throw new RuntimeException('Выплаченное отменить нельзя — проведите возврат');
        }
        if ($v['status'] === 'otmenena') {
            throw new RuntimeException('Уже отменена');
        }

        vypolnit('UPDATE seller_payouts SET status = "otmenena" WHERE id = ?',
                 [$vyplata_id]);

        // Возвращаем на баланс новой записью
        vypolnit('
            INSERT INTO seller_ledger
                (prodavec_id, tip, summa, vyplata_id, kommentariy, sozdal_id)
            VALUES (?, "korrektirovka", ?, ?, ?, ?)
        ', [$v['prodavec_id'], (int) $v['summa'], $vyplata_id,
            'Отмена выплаты №' . $vyplata_id . ': ' . $prichina,
            tekushiy_polzovatel()['id'] ?? null]);

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}
```

⚠️ **Заметьте: отмена не удаляет записи.** Она добавляет обратную операцию.

В журнале остаётся и выплата, и её отмена. Через год можно будет объяснить
каждое движение — а это ровно то, ради чего затевался журнал.

## 💻 Кабинет продавца: баланс

```php
<?php
// seller/finance.php
require_once __DIR__ . '/_zashita.php';

$magazin = moy_magazin();
$balans = balans_prodavca((int) $magazin['id']);

// Сводка по типам операций
$svodka = zapros('
    SELECT tip, SUM(summa) AS summa, COUNT(*) AS shtuk
    FROM seller_ledger WHERE prodavec_id = ?
    GROUP BY tip
', [$magazin['id']]);
$po_tipam = array_column($svodka, null, 'tip');

// Последние операции
$operacii = zapros('
    SELECT l.*, z.nomer AS zakaz_nomer
    FROM seller_ledger AS l
    LEFT JOIN zakazy AS z ON z.id = l.zakaz_id
    WHERE l.prodavec_id = ?
    ORDER BY l.sozdano DESC, l.id DESC
    LIMIT 50
', [$magazin['id']]);

$zagolovok = 'Баланс';
require __DIR__ . '/_shapka.php';
?>

<h1>Баланс</h1>

<div class="balans-glavnyy">
    <span class="balans-summa"><?= somoni($balans) ?></span>
    <span class="balans-podpis">сомони к выплате</span>
</div>

<div class="plitki">
    <div class="plitka">
        <span class="chislo"><?= somoni((int) ($po_tipam['nachislenie']['summa'] ?? 0)) ?></span>
        <span class="podpis">начислено всего</span>
    </div>
    <div class="plitka">
        <span class="chislo"><?= somoni(abs((int) ($po_tipam['uderzhanie']['summa'] ?? 0))) ?></span>
        <span class="podpis">удержано комиссии</span>
    </div>
    <div class="plitka">
        <span class="chislo"><?= somoni(abs((int) ($po_tipam['vyplata']['summa'] ?? 0))) ?></span>
        <span class="podpis">выплачено</span>
    </div>
</div>

<h2>Операции</h2>

<table class="spisok plotnaya">
    <thead>
        <tr><th>Дата</th><th>Операция</th><th>Заказ</th><th>Сумма</th></tr>
    </thead>
    <tbody>
    <?php foreach ($operacii as $o): ?>
        <tr>
            <td class="tihoe"><?= date('d.m.Y H:i', strtotime($o['sozdano'])) ?></td>
            <td>
                <?= e($o['kommentariy']) ?>
                <span class="tip-operacii t-<?= e($o['tip']) ?>">
                    <?= e(tip_operacii_podpis($o['tip'])) ?>
                </span>
            </td>
            <td class="mono"><?= e($o['zakaz_nomer'] ?? '') ?></td>
            <td class="chislo-yacheyka mono <?= (int) $o['summa'] >= 0 ? 'plyus' : 'minus' ?>">
                <?= (int) $o['summa'] >= 0 ? '+' : '−' ?><?= somoni(abs((int) $o['summa'])) ?>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php require __DIR__ . '/_podval.php'; ?>
```

**Продавец видит каждое движение денег.** Не итоговое число, а весь путь:
начислили, удержали комиссию, выплатили. Это снимает 90% вопросов
и претензий.

## 📖 Сверка: обязательная процедура

Раз в месяц имеет смысл проверять, что расчёты сходятся:

```php
/**
 * Проверка: баланс по журналу должен совпадать
 * с расчётом по заказам и выплатам.
 */
function sverka_balansov(): array
{
    $rashozhdeniya = [];

    $prodavcy = zapros('SELECT id, nazvanie FROM prodavcy');

    foreach ($prodavcy as $p) {
        $po_zhurnalu = balans_prodavca((int) $p['id']);

        // Считаем независимо: доставленные заказы минус комиссия минус выплаты
        $nachisleno = (int) zapros_znachenie('
            SELECT COALESCE(SUM(k_vyplate), 0) FROM order_sellers
            WHERE prodavec_id = ? AND status = "dostavlen"
        ', [$p['id']]);

        $vyplacheno = (int) zapros_znachenie('
            SELECT COALESCE(SUM(summa), 0) FROM seller_payouts
            WHERE prodavec_id = ? AND status <> "otmenena"
        ', [$p['id']]);

        $dolzhno_byt = $nachisleno - $vyplacheno;

        if ($po_zhurnalu !== $dolzhno_byt) {
            $rashozhdeniya[] = [
                'prodavec'   => $p['nazvanie'],
                'po_zhurnalu' => $po_zhurnalu,
                'dolzhno'    => $dolzhno_byt,
                'raznica'    => $po_zhurnalu - $dolzhno_byt,
            ];
        }
    }

    return $rashozhdeniya;
}
```

⚠️ **Расхождение — всегда повод разобраться, а не «поправить число».**
Обычно причина в одном из трёх: двойное начисление, пропущенное удержание,
корректировка без объяснения.

Найдите причину, добавьте корректирующую запись **с комментарием** —
и разберитесь, почему это стало возможным.

## 📖 Что здесь не рассматривается

Честная оговорка: мы разобрали **учёт** денег, а не их **движение**.

Приём платежей картой требует эквайринга — договора с банком, подключения
платёжного шлюза, соблюдения требований к хранению карточных данных.
Это отдельная большая тема, и в разных странах она устроена по-разному.

В проекте autodoc.tj эта часть отмечена как незавершённая именно поэтому:
«деньги наружу» (балансы, реестр выплат) работают, а «деньги внутрь»
(онлайн-оплата) упираются в эквайринг.

**Учёт при этом нужен в любом случае** — платите вы переводом, наличными
или через шлюз. Журнал операций от способа оплаты не зависит.

## 🔤 Разбор по словам

| Запись | Что означает |
|---|---|
| **Журнал операций** (ledger) | Записи о движении денег, только добавляются |
| **Баланс = `SUM(summa)`** | Не хранится, а вычисляется |
| **append-only** | Записи не изменяют и не удаляют |
| Плюс и минус в `summa` | Приход и расход одной колонкой |
| Две записи вместо одной | Начисление и комиссия видны раздельно |
| **Идемпотентность** | Повторный вызов не меняет результат |
| `FOR UPDATE` на журнале | Баланс не изменится между проверкой и списанием |
| **Сверка** | Независимый пересчёт для контроля |

## ⚠️ Грабли

**Хранить баланс числом.** Невозможно объяснить, откуда оно взялось.

**Изменять или удалять записи журнала.** История теряется. Только корректировки.

**Не защищать начисление от повтора.** Двойное начисление найдут не сразу.

**Начислять до доставки.** Придётся отбирать назад при отказе.

**Одна запись вместо начисления и комиссии.** Продавец не поймёт расчёт.

**Не блокировать журнал при выплате.** Одна сумма уйдёт дважды.

**Не делать сверку.** Расхождение накопится незаметно.

**Округлять при каждой операции.** Копейки разъедутся за год.

## 🏋️ Задачи

**Задача 47.1.** Создайте таблицы журнала и выплат.

**Задача 47.2.** Реализуйте начисление при доставке — двумя записями.

**Задача 47.3.** Проверьте защиту от повтора: вызовите начисление дважды.
Баланс изменился один раз?

**Задача 47.4.** Сделайте страницу баланса продавца со списком операций.

**Задача 47.5.** Реализуйте создание выплаты с проверкой достатка средств.

**Задача 47.6.** Попробуйте выплатить больше, чем на балансе. Что произошло?

**Задача 47.7.** Реализуйте отмену выплаты корректирующей записью.
Убедитесь, что исходная запись осталась.

**Задача 47.8.** Напишите сверку и запустите. Расхождения есть?

**Задача 47.9.** Продавец вернул товар покупателю. Какие записи должны
появиться в журнале? Напишите функцию.

**Задача 47.10.** Посчитайте руками: продавцу начислено 500, 45 и 780
по трём заказам, комиссия 10%, выплачено 900. Каков баланс? Проверьте кодом.

*Ответы — в [Приложении Г](../prilozheniya/G-otvety.md).*

## 🔗 В бою

Откройте `includes/seller_finance.php`, `seller/finance.php`
и `admin/payouts.php` в этом репозитории. Это Фаза 3a проекта, готовая целиком.

Там ровно та схема, что в главе: журнал `seller_ledger`, баланс как `SUM`,
реестр `seller_payouts`, начисление при переходе заказа в «доставлен».

Одна деталь, которую стоит заметить: **начисление привязано к подзаказу**,
а не к заказу. Иначе при отмене части заказа пришлось бы разбираться,
чью долю отменять.

И ещё: реестр выплат сделан **раньше**, чем онлайн-оплата. Это разумная
очерёдность — сначала научитесь считать деньги, потом принимать. Считать
придётся в любом случае, а способ приёма может измениться.

## 📌 Итог

- **Баланс не хранят числом.** Хранят журнал операций, баланс — это `SUM`.
- Журнал **только пополняется**. Ошибка исправляется корректирующей записью.
- Начисление и удержание комиссии — **две отдельные записи**. Прозрачность
  дороже экономии.
- Начисляем **при доставке**, не раньше.
- Любая денежная операция **защищена от повтора** — идемпотентна.
- При выплате **блокируем журнал**, иначе одна сумма уйдёт дважды.
- Отмена — **обратная запись**, а не удаление.
- Продавец видит **все движения**, а не итоговое число.
- **Сверка раз в месяц**. Расхождение — повод искать причину.

Дальше — buy-box: как показать один товар от нескольких продавцов.

[← Глава 46](46-razbivka-zakaza.md) · [Глава 48. Buy-box →](48-buy-box.md)
