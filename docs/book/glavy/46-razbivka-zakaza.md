# Глава 46. Разбивка заказа и комиссия

> **Часть IX. Маркетплейс** · Глава 46 из 60
> [← Глава 45](45-moderaciya.md) · [Глава 47 →](47-balansy-i-vyplaty.md)

## 🎯 Зачем эта глава

Покупатель положил в корзину три товара: колодки от «Автомира», фильтр
от «Запчасть+» и аккумулятор от «Мотор Плюс».

Для покупателя это **один заказ**. Для площадки — **три разных отгрузки**,
три продавца, три комиссии и три расчёта.

В этой главе научимся разбивать заказ на подзаказы и правильно считать деньги.
Тема денежная, поэтому цена ошибки высокая: недосчитали комиссию — потеряли доход,
пересчитали — конфликт с продавцом.

## 📖 Структура: заказ и подзаказы

```sql
CREATE TABLE order_sellers (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    zakaz_id          INT NOT NULL,
    prodavec_id       INT NOT NULL,

    summa_tovarov     INT NOT NULL DEFAULT 0 COMMENT 'в дирамах',
    komissiya_procent DECIMAL(5,2) NOT NULL COMMENT 'зафиксирована на момент заказа',
    komissiya_summa   INT NOT NULL DEFAULT 0,
    k_vyplate         INT NOT NULL DEFAULT 0 COMMENT 'товары минус комиссия',

    status            ENUM('novyi','sobran','otpravlen','dostavlen','otmenen')
                      NOT NULL DEFAULT 'novyi',

    sozdan            DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    izmenen           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uniq_zakaz_prodavec (zakaz_id, prodavec_id),
    KEY idx_prodavec (prodavec_id, status),

    CONSTRAINT fk_os_zakaz FOREIGN KEY (zakaz_id) REFERENCES zakazy(id) ON DELETE CASCADE,
    CONSTRAINT fk_os_prodavec FOREIGN KEY (prodavec_id) REFERENCES prodavcy(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

Получается три уровня:

```
zakazy              один заказ покупателя
  └── order_sellers   подзаказ на каждого продавца
        └── zakaz_tovary   позиции этого продавца
```

⚠️ **`UNIQUE KEY (zakaz_id, prodavec_id)`** гарантирует: на одного продавца
в одном заказе — ровно один подзаказ. База не даст ошибиться.

## 📖 Почему у подзаказа свой статус

Это неочевидно, но принципиально.

«Автомир» собрал и отправил свои колодки за час. «Мотор Плюс» ждёт поставку
аккумулятора три дня.

**Один общий статус заказа не может описать эту ситуацию.** «Отправлен»?
Неправда — аккумулятор ещё не отправлен. «Собирается»? Тоже неправда —
колодки уже в пути.

Поэтому статус живёт **у подзаказа**, а общий статус заказа **вычисляется**:

```php
/**
 * Общий статус заказа выводится из статусов подзаказов.
 * Правило: заказ настолько готов, насколько готов самый отстающий продавец.
 */
function obshiy_status_zakaza(int $zakaz_id): string
{
    $statusy = zapros('
        SELECT status, COUNT(*) AS shtuk
        FROM order_sellers WHERE zakaz_id = ?
        GROUP BY status
    ', [$zakaz_id]);

    $po_statusam = array_column($statusy, 'shtuk', 'status');
    $vsego = array_sum($po_statusam);

    if ($vsego === 0) return 'novyi';

    // Все отменены — заказ отменён
    if (($po_statusam['otmenen'] ?? 0) === $vsego) return 'otmenen';

    // Отменённые дальше не учитываем: они выбыли
    $aktivnyh = $vsego - ($po_statusam['otmenen'] ?? 0);

    if (($po_statusam['dostavlen'] ?? 0) === $aktivnyh) return 'dostavlen';
    if (($po_statusam['novyi'] ?? 0) > 0)                return 'novyi';
    if (($po_statusam['sobran'] ?? 0) > 0)               return 'sobran';

    return 'otpravlen';
}
```

**Принцип: заказ готов настолько, насколько готов самый отстающий продавец.**

Покупателю показываем общий статус плюс — если продавцов несколько —
подробности по каждому. Он должен понимать, что происходит.

## 💻 Разбивка при оформлении

Дополняем `sozdat_zakaz` из главы 41:

```php
function sozdat_zakaz_marketplace(array $dannye): array
{
    $korzina = korzina_soderzhimoe();
    if (empty($korzina)) {
        throw new RuntimeException('Корзина пуста');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        // --- 1. Собираем позиции, группируя по продавцам ---
        $po_prodavcam = [];
        $summa_tovarov = 0;

        foreach ($korzina as $tovar_id => $kolichestvo) {
            $t = zapros_odin('
                SELECT t.id, t.nazvanie, t.artikul, t.cena, t.ostatok,
                       t.prodavec_id, p.komissiya_procent
                FROM tovary AS t
                LEFT JOIN prodavcy AS p ON p.id = t.prodavec_id
                WHERE t.id = ? AND t.aktivnyi = 1 AND t.status = "opublikovan"
                FOR UPDATE
            ', [$tovar_id]);

            if ($t === null) {
                throw new RuntimeException('Один из товаров снят с продажи');
            }
            if ((int) $t['ostatok'] < $kolichestvo) {
                throw new RuntimeException(sprintf(
                    '«%s»: осталось %d шт.', $t['nazvanie'], (int) $t['ostatok']
                ));
            }

            // Товары площадки складываем в группу с ключом 0
            $kluch = $t['prodavec_id'] ? (int) $t['prodavec_id'] : 0;

            // Комиссия фиксируется здесь и больше не меняется
            $po_prodavcam[$kluch]['komissiya'] =
                (float) ($t['komissiya_procent'] ?? 0);

            $stoimost = (int) $t['cena'] * $kolichestvo;
            $summa_tovarov += $stoimost;

            $po_prodavcam[$kluch]['pozicii'][] = [
                'tovar_id'    => (int) $t['id'],
                'nazvanie'    => $t['nazvanie'],
                'artikul'     => $t['artikul'],
                'cena'        => (int) $t['cena'],
                'kolichestvo' => $kolichestvo,
                'stoimost'    => $stoimost,
            ];
        }

        // --- 2. Создаём заказ ---
        $dostavka = $dannye['sposob_dostavki'] === 'kurier'
            ? cena_dostavki($summa_tovarov) : 0;
        $itogo = $summa_tovarov + $dostavka;
        $nomer = sgenerirovat_nomer_zakaza();
        $ya = tekushiy_polzovatel();

        vypolnit('
            INSERT INTO zakazy (
                nomer, polzovatel_id, klient_imya, klient_telefon, klient_adres,
                summa_tovarov, summa_dostavki, summa_itogo,
                sposob_dostavki, sposob_oplaty, status, kommentariy
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, "novyi", ?)
        ', [
            $nomer, $ya['id'] ?? null,
            $dannye['imya'], $dannye['telefon'], $dannye['adres'] ?? '',
            $summa_tovarov, $dostavka, $itogo,
            $dannye['sposob_dostavki'], $dannye['sposob_oplaty'],
            $dannye['kommentariy'] ?? '',
        ]);

        $zakaz_id = posledniy_id();

        // --- 3. Подзаказ на каждого продавца ---
        foreach ($po_prodavcam as $prodavec_id => $gruppa) {

            $summa_gruppy = array_sum(array_column($gruppa['pozicii'], 'stoimost'));

            // Комиссия только с товаров продавцов.
            // Со своих товаров площадка комиссию себе не платит.
            $procent = $prodavec_id > 0 ? $gruppa['komissiya'] : 0.0;
            $komissiya = (int) round($summa_gruppy * $procent / 100);
            $k_vyplate = $summa_gruppy - $komissiya;

            $order_seller_id = null;

            if ($prodavec_id > 0) {
                vypolnit('
                    INSERT INTO order_sellers
                        (zakaz_id, prodavec_id, summa_tovarov,
                         komissiya_procent, komissiya_summa, k_vyplate)
                    VALUES (?, ?, ?, ?, ?, ?)
                ', [$zakaz_id, $prodavec_id, $summa_gruppy,
                    $procent, $komissiya, $k_vyplate]);

                $order_seller_id = posledniy_id();
            }

            // --- 4. Позиции и списание остатков ---
            foreach ($gruppa['pozicii'] as $p) {
                vypolnit('
                    INSERT INTO zakaz_tovary
                        (zakaz_id, order_seller_id, tovar_id, prodavec_id,
                         nazvanie, artikul, cena, kolichestvo, komissiya_procent)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ', [
                    $zakaz_id, $order_seller_id, $p['tovar_id'],
                    $prodavec_id > 0 ? $prodavec_id : null,
                    $p['nazvanie'], $p['artikul'], $p['cena'],
                    $p['kolichestvo'], $procent,
                ]);

                $izmeneno = vypolnit('
                    UPDATE tovary SET ostatok = ostatok - ?
                    WHERE id = ? AND ostatok >= ?
                ', [$p['kolichestvo'], $p['tovar_id'], $p['kolichestvo']]);

                if ($izmeneno === 0) {
                    throw new RuntimeException(
                        '«' . $p['nazvanie'] . '» закончился, пока вы оформляли заказ'
                    );
                }
            }
        }

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    korzina_ochistit();

    return ['id' => $zakaz_id, 'nomer' => $nomer, 'itogo' => $itogo,
            'prodavcov' => count($po_prodavcam)];
}
```

## 📖 Арифметика комиссии

Здесь легко ошибиться, поэтому разберём на числах.

Заказ на **1325.00 сомони** (132 500 дирамов), три продавца:

| Продавец | Товары | Комиссия | Удержано | К выплате |
|---|---|---|---|---|
| Автомир | 500.00 | 10% | 50.00 | 450.00 |
| Запчасть+ | 45.00 | 12% | 5.40 | 39.60 |
| Мотор Плюс | 780.00 | 8% | 62.40 | 717.60 |
| **Итого** | **1325.00** | | **117.80** | **1207.20** |

**Доход площадки — 117.80 сомони.** Товар она не закупала, склад не держала,
риска не несла.

### **Три правила расчёта денег**

**1. Считайте в целых числах.** Помните главу 20: `0.1 + 0.2 !== 0.3`.
Все суммы — в дирамах, целыми. Округление только при показе.

```php
$komissiya = (int) round($summa * $procent / 100);   // ✅ целое число дирамов
```

**2. Округляйте один раз, в конце.** Округлять каждую позицию отдельно —
значит накопить расхождение:

```php
// ❌ Копейки разъедутся на большом заказе
foreach ($pozicii as $p) {
    $komissiya += (int) round($p['stoimost'] * $procent / 100);
}

// ✅ Округляем от общей суммы
$komissiya = (int) round(array_sum(array_column($pozicii, 'stoimost')) * $procent / 100);
```

**3. Сумма частей должна сходиться с целым.** После расчёта проверьте:

```php
$proverka = $summa_tovarov === array_sum($summy_podzakazov);
if (!$proverka) {
    throw new RuntimeException('Суммы подзаказов не сходятся с заказом');
}
```

Такая проверка кажется избыточной, пока однажды не поймает ошибку округления
на заказе в сто позиций.

## 📖 Кто что видит

Ключевой момент маркетплейса: **каждый видит только своё**.

```php
/** Подзаказы продавца. Чужие не попадут в выборку. */
function zakazy_prodavca(int $prodavec_id, string $status = ''): array
{
    $usloviya = ['os.prodavec_id = ?'];
    $parametry = [$prodavec_id];

    if ($status !== '') {
        $usloviya[] = 'os.status = ?';
        $parametry[] = $status;
    }

    $where = implode(' AND ', $usloviya);

    return zapros("
        SELECT os.*, z.nomer, z.klient_imya, z.klient_telefon,
               z.sposob_dostavki, z.klient_adres, z.sozdan AS zakaz_sozdan
        FROM order_sellers AS os
        INNER JOIN zakazy AS z ON z.id = os.zakaz_id
        WHERE $where
        ORDER BY os.sozdan DESC
        LIMIT 100
    ", $parametry);
}

/** Позиции подзаказа. Проверка владения — прямо в запросе. */
function pozicii_podzakaza(int $order_seller_id, int $prodavec_id): array
{
    return zapros('
        SELECT zt.*
        FROM zakaz_tovary AS zt
        INNER JOIN order_sellers AS os ON os.id = zt.order_seller_id
        WHERE zt.order_seller_id = ? AND os.prodavec_id = ?
    ', [$order_seller_id, $prodavec_id]);
}
```

⚠️ **Продавец не должен видеть**:

- чужие позиции того же заказа;
- общую сумму заказа;
- других продавцов покупателя;
- размер чужой комиссии.

Всё это — коммерческая тайна площадки. Утечка приведёт к тому, что продавцы
начнут сравнивать условия и торговаться.

### **А что видит покупатель**

```php
// Заказ глазами покупателя: свои позиции, сгруппированные по отгрузкам
$podzakazy = zapros('
    SELECT os.id, os.status, p.nazvanie AS prodavec, p.gorod
    FROM order_sellers AS os
    INNER JOIN prodavcy AS p ON p.id = os.prodavec_id
    WHERE os.zakaz_id = ?
', [$zakaz_id]);
```

```php
<?php if (count($podzakazy) > 1): ?>
    <p class="tihoe">
        Заказ придёт <?= count($podzakazy) ?> отправлениями от разных продавцов.
    </p>
<?php endif; ?>

<?php foreach ($podzakazy as $pz): ?>
    <section class="otpravlenie">
        <h3>
            <?= e($pz['prodavec']) ?>
            <span class="status s-<?= e($pz['status']) ?>">
                <?= e(status_zakaza_podpis($pz['status'])) ?>
            </span>
        </h3>
        <!-- позиции этого отправления -->
    </section>
<?php endforeach; ?>
```

**Покупателю честно говорим, что посылок будет несколько.** Иначе он получит
одну коробку из трёх, решит, что заказ выполнен не полностью, и позвонит
с претензией.

## 📖 Отмена части заказа

Ситуация: «Мотор Плюс» не смог достать аккумулятор. Остальное отправлено.

```php
function otmenit_podzakaz(int $order_seller_id, string $prichina): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pz = zapros_odin('SELECT * FROM order_sellers WHERE id = ? FOR UPDATE',
                          [$order_seller_id]);

        if ($pz === null) {
            throw new RuntimeException('Подзаказ не найден');
        }
        if ($pz['status'] === 'otmenen') {
            throw new RuntimeException('Уже отменён');
        }
        if ($pz['status'] === 'dostavlen') {
            throw new RuntimeException('Доставленный заказ отменить нельзя');
        }

        vypolnit('UPDATE order_sellers SET status = "otmenen" WHERE id = ?',
                 [$order_seller_id]);

        // Товары возвращаем на склад
        $pozicii = zapros('SELECT tovar_id, kolichestvo FROM zakaz_tovary
                           WHERE order_seller_id = ?', [$order_seller_id]);
        foreach ($pozicii as $p) {
            if ($p['tovar_id']) {
                vypolnit('UPDATE tovary SET ostatok = ostatok + ? WHERE id = ?',
                         [$p['kolichestvo'], $p['tovar_id']]);
            }
        }

        // Пересчитываем сумму заказа: отменённая часть не оплачивается
        vypolnit('
            UPDATE zakazy SET
                summa_tovarov = (
                    SELECT COALESCE(SUM(summa_tovarov), 0) FROM order_sellers
                    WHERE zakaz_id = ? AND status <> "otmenen"
                ),
                status = ?
            WHERE id = ?
        ', [$pz['zakaz_id'], obshiy_status_zakaza((int) $pz['zakaz_id']), $pz['zakaz_id']]);

        vypolnit('UPDATE zakazy SET summa_itogo = summa_tovarov + summa_dostavki
                  WHERE id = ?', [$pz['zakaz_id']]);

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    uvedomit_pokupatelya_ob_otmene((int) $pz['zakaz_id'], $prichina);
}
```

⚠️ **Отмена части заказа — самая недооценённая часть маркетплейса.**

Забыть пересчитать сумму — значит выставить покупателю счёт за товар,
который не приедет. Забыть вернуть остатки — товар исчезнет со склада
навсегда. Забыть уведомить — покупатель узнает при получении.

## 🔤 Разбор по словам

| Запись | Что означает |
|---|---|
| `order_sellers` | Подзаказ: часть заказа, относящаяся к одному продавцу |
| `UNIQUE (zakaz_id, prodavec_id)` | Один подзаказ на продавца в заказе |
| `komissiya_procent` в подзаказе | **Зафиксирована** на момент заказа |
| Статус у подзаказа | У каждого продавца свой срок |
| `obshiy_status_zakaza()` | Вычисляется по самому отстающему |
| `k_vyplate` | Товары минус комиссия |
| Группировка по `prodavec_id` | Ключ 0 — товары самой площадки |
| Округление один раз | Иначе копейки разъедутся |

## ⚠️ Грабли

**Один статус на весь заказ.** Не описывает реальность при разных продавцах.

**Брать комиссию из таблицы продавцов при расчёте выплат.** Условия могли
измениться — берите зафиксированную в подзаказе.

**Округлять каждую позицию.** Суммы разойдутся.

**Считать в дробных числах.** Помните `0.1 + 0.2`.

**Показывать продавцу весь заказ.** Утечка коммерческой информации.

**Не предупредить покупателя о нескольких посылках.** Претензия гарантирована.

**Забыть пересчитать сумму при отмене части.** Счёт за непривезённый товар.

**Брать комиссию с собственных товаров площадки.** Бессмысленно — вы платите
сами себе.

## 🏋️ Задачи

**Задача 46.1.** Создайте таблицу `order_sellers` и разбивку заказа
по продавцам.

**Задача 46.2.** Оформите заказ с товарами трёх продавцов. Проверьте, что
создалось три подзаказа с правильными суммами.

**Задача 46.3.** Проверьте арифметику: сумма подзаказов должна точно совпадать
с суммой заказа. Напишите проверку, которая падает при расхождении.

**Задача 46.4.** Реализуйте `obshiy_status_zakaza()` и проверьте на всех
сочетаниях статусов.

**Задача 46.5.** Сделайте кабинет продавца: только свои подзаказы, только
свои позиции.

**Задача 46.6.** Проверьте изоляцию: войдите продавцом и попробуйте открыть
чужой подзаказ по номеру.

**Задача 46.7.** Реализуйте отмену подзаказа с возвратом остатков
и пересчётом суммы.

**Задача 46.8.** Посчитайте вручную и проверьте кодом: заказ 2450 сомони,
два продавца — 1500 под 10% и 950 под 12%. Сколько заработала площадка?
Сколько к выплате каждому?

**Задача 46.9.** Что должно произойти с комиссией, если покупатель вернул
товар? Продумайте и опишите словами.

*Ответы — в [Приложении Г](../prilozheniya/4-G-otvety.md).*

## 🔗 В бою

Откройте `buyer/checkout.php`, `seller/orders.php` и `admin/orders.php`
в этом репозитории — это Фаза 2 проекта, готовая целиком.

Обратите внимание на две вещи.

**Комиссия фиксируется в момент заказа.** Это записано в описании проекта
прямым текстом. Если завтра договориться с продавцом о другом проценте,
старые заказы должны считаться по-старому — иначе выплаты пересчитаются
задним числом.

**Админ видит заказ в разрезе продавцов.** Одна покупка, три колонки —
кто что должен собрать. Так менеджер понимает, кому звонить, если отгрузка
задерживается.

## 📌 Итог

- Один заказ покупателя = **несколько подзаказов**, по одному на продавца.
- **Статус — у подзаказа.** Общий статус вычисляется по самому отстающему.
- **Комиссия фиксируется в момент заказа** и больше не меняется.
- Деньги — **целыми числами**, округление **один раз** и от общей суммы.
- Проверяйте, что сумма подзаказов сходится с заказом.
- Продавец видит **только свои** позиции и суммы.
- Покупателя честно предупреждайте о нескольких отправлениях.
- Отмена части: вернуть остатки, **пересчитать сумму**, уведомить.
- С собственных товаров площадки комиссия не берётся.

Дальше — балансы продавцов и выплаты.

[← Глава 45](45-moderaciya.md) · [Глава 47. Балансы и выплаты →](47-balansy-i-vyplaty.md)
