# Глава 41. Оформление заказа

> **Часть VIII. Магазин** · Глава 41 из 60
> [← Глава 40](40-roli-i-dostup.md) · [Глава 42 →](42-adminka.md)

## 🎯 Зачем эта глава

Оформление заказа — самое ответственное место магазина. Здесь сходится всё:
корзина, права, деньги, остатки на складе.

И здесь же больше всего способов ошибиться. Заказ создался, а остатки
не списались. Два человека купили последнюю единицу. Цена в заказе оказалась
старой. Покупатель нажал F5 и получил два одинаковых заказа.

Каждая из этих ошибок случается в реальных магазинах. Разберём, как их избежать.

## 📖 Что должно произойти при оформлении

Заказ — это не одна запись, а **цепочка связанных действий**:

1. Проверить, что корзина не пуста;
2. Проверить наличие каждого товара **прямо сейчас**;
3. Пересчитать сумму **по текущим ценам из базы**;
4. Создать запись заказа;
5. Создать позиции заказа с **зафиксированными** ценами;
6. Уменьшить остатки на складе;
7. Очистить корзину;
8. Отправить уведомления.

⚠️ **Шаги 4–6 должны выполниться все или ни один.** Если между ними что-то
оборвётся — заказ создан, а склад показывает старые остатки. Или наоборот:
остатки списаны, а заказа нет.

Для этого существуют транзакции. Пришло время применить их по-настоящему.

## 📖 Транзакция

```php
try {
    db()->beginTransaction();

    // ... несколько запросов ...

    db()->commit();

} catch (Throwable $e) {
    db()->rollBack();
    throw $e;
}
```

| Команда | Что делает |
|---|---|
| `beginTransaction()` | Начать. Изменения «черновые» |
| `commit()` | Записать всё разом |
| `rollBack()` | Отменить всё, как будто ничего не было |

⚠️ `Throwable` вместо `Exception` — ловит **и ошибки, и исключения**.
С обычным `Exception` фатальная ошибка PHP пролетела бы мимо, и транзакция
осталась бы открытой.

## 📖 Гонка за последним товаром

Вот проблема, о которой не думают, пока она не случится.

Два покупателя одновременно оформляют последний аккумулятор:

```
Покупатель А                     Покупатель Б
──────────────────────────────────────────────────
читает остаток: 1
                                 читает остаток: 1
проверяет: 1 >= 1, можно
                                 проверяет: 1 >= 1, можно
записывает остаток 0
                                 записывает остаток 0
```

**Оба заказа приняты. Аккумулятор один.** Кому-то придётся звонить
и извиняться.

Это называется **состояние гонки**. На маленьком магазине случается редко,
на популярном — каждый день.

### **Решение: блокировка строки**

```php
// SELECT ... FOR UPDATE блокирует строку до конца транзакции.
// Второй покупатель подождёт и увидит уже обновлённый остаток.
$tovar = zapros_odin('
    SELECT id, nazvanie, cena, ostatok
    FROM tovary
    WHERE id = ?
    FOR UPDATE
', [$tovar_id]);
```

**`FOR UPDATE`** говорит базе: «я собираюсь менять эту строку, никого к ней
не пускай».

⚠️ Работает **только внутри транзакции** и **только на InnoDB**. Вот зачем мы
в главе 26 указывали движок явно.

### **Второй рубеж: условие в UPDATE**

```php
$izmeneno = vypolnit('
    UPDATE tovary
    SET ostatok = ostatok - ?
    WHERE id = ? AND ostatok >= ?
', [$kolichestvo, $tovar_id, $kolichestvo]);

if ($izmeneno === 0) {
    throw new RuntimeException('Товар закончился, пока вы оформляли заказ');
}
```

Приём из главы 28. Если между проверкой и списанием остаток изменился,
условие `ostatok >= ?` не выполнится, и `rowCount()` вернёт ноль.

**Два рубежа лучше одного.** Блокировка предотвращает гонку, проверка
на результат ловит всё остальное.

## 💻 Оформление заказа

```php
<?php
// includes/zakazy.php
declare(strict_types=1);

/**
 * Создать заказ из корзины.
 *
 * Возвращает данные созданного заказа.
 * Бросает RuntimeException с понятным для покупателя текстом.
 */
function sozdat_zakaz(array $dannye): array
{
    $korzina = korzina_soderzhimoe();

    if (empty($korzina)) {
        throw new RuntimeException('Корзина пуста');
    }

    $pdo = db();
    $pdo->beginTransaction();

    try {
        $pozicii = [];
        $summa_tovarov = 0;

        foreach ($korzina as $tovar_id => $kolichestvo) {
            // Блокируем строку до конца транзакции
            $t = zapros_odin('
                SELECT id, nazvanie, artikul, cena, ostatok, prodavec_id
                FROM tovary
                WHERE id = ? AND aktivnyi = 1 AND status = "opublikovan"
                FOR UPDATE
            ', [$tovar_id]);

            if ($t === null) {
                throw new RuntimeException('Один из товаров снят с продажи');
            }

            if ((int) $t['ostatok'] < $kolichestvo) {
                throw new RuntimeException(sprintf(
                    '«%s»: осталось %d шт., а в корзине %d',
                    $t['nazvanie'], (int) $t['ostatok'], $kolichestvo
                ));
            }

            // ЦЕНА БЕРЁТСЯ ИЗ БАЗЫ, а не из корзины и не из формы
            $stoimost = (int) $t['cena'] * $kolichestvo;
            $summa_tovarov += $stoimost;

            $pozicii[] = [
                'tovar_id'    => (int) $t['id'],
                'prodavec_id' => $t['prodavec_id'] ? (int) $t['prodavec_id'] : null,
                'nazvanie'    => $t['nazvanie'],
                'artikul'     => $t['artikul'],
                'cena'        => (int) $t['cena'],
                'kolichestvo' => $kolichestvo,
            ];
        }

        // Доставку тоже считаем на сервере
        $dostavka = $dannye['sposob_dostavki'] === 'kurier'
            ? cena_dostavki($summa_tovarov)
            : 0;

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
            $nomer,
            $ya['id'] ?? null,
            $dannye['imya'],
            $dannye['telefon'],
            $dannye['adres'] ?? '',
            $summa_tovarov,
            $dostavka,
            $itogo,
            $dannye['sposob_dostavki'],
            $dannye['sposob_oplaty'],
            $dannye['kommentariy'] ?? '',
        ]);

        $zakaz_id = posledniy_id();

        foreach ($pozicii as $p) {
            vypolnit('
                INSERT INTO zakaz_tovary
                    (zakaz_id, tovar_id, prodavec_id, nazvanie, artikul, cena, kolichestvo)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ', [
                $zakaz_id, $p['tovar_id'], $p['prodavec_id'],
                $p['nazvanie'], $p['artikul'], $p['cena'], $p['kolichestvo'],
            ]);

            // Второй рубеж защиты остатка
            $izmeneno = vypolnit('
                UPDATE tovary
                SET ostatok = ostatok - ?
                WHERE id = ? AND ostatok >= ?
            ', [$p['kolichestvo'], $p['tovar_id'], $p['kolichestvo']]);

            if ($izmeneno === 0) {
                throw new RuntimeException(
                    '«' . $p['nazvanie'] . '» закончился, пока вы оформляли заказ'
                );
            }
        }

        $pdo->commit();

    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }

    // Корзину и письма — уже после успешной транзакции
    korzina_ochistit();

    return [
        'id'     => $zakaz_id,
        'nomer'  => $nomer,
        'itogo'  => $itogo,
    ];
}

/** Человеческий номер вида 2026-001024. */
function sgenerirovat_nomer_zakaza(): string
{
    $god = date('Y');
    $za_god = (int) zapros_znachenie(
        'SELECT COUNT(*) FROM zakazy WHERE YEAR(sozdan) = ?', [$god]
    );
    return sprintf('%s-%06d', $god, $za_god + 1);
}
```

### **Три решения, которые стоит заметить**

**Цена берётся из базы внутри транзакции.** Не из корзины, не из формы,
не из того, что показали покупателю пять минут назад. Только текущая цена
в момент оформления.

**Очистка корзины и письма — вне транзакции.** Если письмо не отправится,
заказ всё равно должен остаться. Внутри транзакции держат только то,
что обязано быть согласованным.

**Сообщения об ошибках понятны покупателю.** Не «Integrity constraint violation»,
а «осталось 2 шт., а в корзине 5». Человек должен понять, что делать.

## 💻 Страница оформления

```php
<?php
// checkout.php
require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/korzina.php';
require_once __DIR__ . '/includes/zakazy.php';

$k = korzina_podrobno();

if (empty($k['pozicii'])) {
    header('Location: korzina.php');
    exit;
}

$ya = tekushiy_polzovatel();
$oshibki = [];
$oshibka_obshaya = null;

// Подставляем данные вошедшего — не заставляем вводить заново
$dannye = [
    'imya'            => $ya['imya'] ?? '',
    'telefon'         => $ya['telefon'] ?? '',
    'adres'           => '',
    'sposob_dostavki' => 'samovyvoz',
    'sposob_oplaty'   => 'nalichnye',
    'kommentariy'     => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_proverit();

    foreach (array_keys($dannye) as $pole) {
        $dannye[$pole] = trim($_POST[$pole] ?? '');
    }

    // --- Проверка ---
    if (mb_strlen($dannye['imya']) < 2) {
        $oshibki['imya'] = 'Введите имя';
    }
    if (!preg_match('/^\+?[0-9\s\-()]{9,20}$/', $dannye['telefon'])) {
        $oshibki['telefon'] = 'Проверьте номер телефона';
    }
    if (!in_array($dannye['sposob_dostavki'], ['samovyvoz', 'kurier'], true)) {
        $oshibki['sposob_dostavki'] = 'Выберите способ получения';
    }
    if ($dannye['sposob_dostavki'] === 'kurier' && mb_strlen($dannye['adres']) < 10) {
        $oshibki['adres'] = 'Укажите адрес доставки подробнее';
    }
    if (!in_array($dannye['sposob_oplaty'], ['nalichnye', 'perevod'], true)) {
        $oshibki['sposob_oplaty'] = 'Выберите способ оплаты';
    }

    if (empty($oshibki)) {
        try {
            $zakaz = sozdat_zakaz($dannye);

            // Уведомления — после успешного заказа, и их падение
            // не должно ронять оформление
            try {
                otpravit_uvedomleniya_o_zakaze((int) $zakaz['id']);
            } catch (Throwable $e) {
                error_log('Не удалось отправить уведомление: ' . $e->getMessage());
            }

            // PRG: F5 больше не создаст дубль
            header('Location: spasibo.php?nomer=' . urlencode($zakaz['nomer']));
            exit;

        } catch (RuntimeException $e) {
            // Понятная покупателю причина
            $oshibka_obshaya = $e->getMessage();
            $k = korzina_podrobno();   // пересчитаем: что-то изменилось

        } catch (Throwable $e) {
            error_log('Ошибка оформления заказа: ' . $e->getMessage());
            $oshibka_obshaya = 'Не удалось оформить заказ. Попробуйте ещё раз '
                             . 'или позвоните нам: ' . SAIT_TELEFON;
        }
    }
}

$dostavka = $dannye['sposob_dostavki'] === 'kurier'
    ? cena_dostavki($k['summa'])
    : 0;

$zagolovok = 'Оформление заказа — ' . SAIT_NAZVANIE;
require __DIR__ . '/includes/header.php';
?>

<h1>Оформление заказа</h1>

<?php if ($oshibka_obshaya !== null): ?>
    <div class="oshibka-obshaya"><?= e($oshibka_obshaya) ?></div>
<?php endif; ?>

<div class="checkout">

    <form method="POST" class="forma">
        <?= csrf_pole() ?>

        <h2>Ваши данные</h2>

        <div class="pole">
            <label for="imya">Имя</label>
            <input type="text" id="imya" name="imya" required
                   value="<?= e($dannye['imya']) ?>"
                   class="<?= isset($oshibki['imya']) ? 'plohoe' : '' ?>">
            <?php if (isset($oshibki['imya'])): ?>
                <span class="podskazka"><?= e($oshibki['imya']) ?></span>
            <?php endif; ?>
        </div>

        <div class="pole">
            <label for="telefon">Телефон</label>
            <input type="tel" id="telefon" name="telefon" required
                   value="<?= e($dannye['telefon']) ?>"
                   placeholder="+992 92 777 77 77"
                   class="<?= isset($oshibki['telefon']) ? 'plohoe' : '' ?>">
            <?php if (isset($oshibki['telefon'])): ?>
                <span class="podskazka"><?= e($oshibki['telefon']) ?></span>
            <?php endif; ?>
        </div>

        <h2>Доставка</h2>

        <div class="pole">
            <label class="galka">
                <input type="radio" name="sposob_dostavki" value="samovyvoz"
                       <?= $dannye['sposob_dostavki'] === 'samovyvoz' ? 'checked' : '' ?>>
                Самовывоз, Худжанд — бесплатно
            </label>
            <label class="galka">
                <input type="radio" name="sposob_dostavki" value="kurier"
                       <?= $dannye['sposob_dostavki'] === 'kurier' ? 'checked' : '' ?>>
                Курьером — <?= somoni(DOSTAVKA_DIRAM) ?> сомони,
                от <?= somoni(BESPLATNO_OT_DIRAM) ?> бесплатно
            </label>
        </div>

        <div class="pole">
            <label for="adres">Адрес доставки</label>
            <textarea id="adres" name="adres" rows="2"
                      class="<?= isset($oshibki['adres']) ? 'plohoe' : '' ?>"
                      placeholder="Город, улица, дом, квартира"><?= e($dannye['adres']) ?></textarea>
            <?php if (isset($oshibki['adres'])): ?>
                <span class="podskazka"><?= e($oshibki['adres']) ?></span>
            <?php endif; ?>
        </div>

        <h2>Оплата</h2>

        <div class="pole">
            <label class="galka">
                <input type="radio" name="sposob_oplaty" value="nalichnye"
                       <?= $dannye['sposob_oplaty'] === 'nalichnye' ? 'checked' : '' ?>>
                Наличными при получении
            </label>
            <label class="galka">
                <input type="radio" name="sposob_oplaty" value="perevod"
                       <?= $dannye['sposob_oplaty'] === 'perevod' ? 'checked' : '' ?>>
                Переводом на карту
            </label>
        </div>

        <div class="pole">
            <label for="kommentariy">Комментарий</label>
            <textarea id="kommentariy" name="kommentariy" rows="2"
                      placeholder="Марка авто, год, объём двигателя"><?= e($dannye['kommentariy']) ?></textarea>
        </div>

        <button type="submit">Оформить заказ</button>
    </form>

    <aside class="itogo-blok">
        <h2>Ваш заказ</h2>

        <?php foreach ($k['pozicii'] as $p): ?>
            <div class="itogo-stroka">
                <span><?= e($p['nazvanie']) ?> × <?= $p['kolichestvo'] ?></span>
                <span class="mono"><?= somoni($p['stoimost']) ?></span>
            </div>
        <?php endforeach; ?>

        <div class="itogo-stroka">
            <span>Товары</span>
            <span class="mono"><?= somoni($k['summa']) ?></span>
        </div>
        <div class="itogo-stroka">
            <span>Доставка</span>
            <span class="mono"><?= $dostavka > 0 ? somoni($dostavka) : 'бесплатно' ?></span>
        </div>
        <div class="itogo-stroka itogo-glavnaya">
            <span>К оплате</span>
            <span class="mono"><?= somoni($k['summa'] + $dostavka) ?> сомони</span>
        </div>

        <p class="tihoe">
            Мы позвоним в течение часа в рабочее время, чтобы подтвердить заказ.
        </p>
    </aside>

</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
```

## 📖 Страница «Спасибо»

```php
<?php
// spasibo.php
require_once __DIR__ . '/includes/bootstrap.php';

$nomer = trim($_GET['nomer'] ?? '');

$zakaz = $nomer !== ''
    ? zapros_odin('SELECT * FROM zakazy WHERE nomer = ?', [$nomer])
    : null;

if ($zakaz === null) {
    header('Location: /');
    exit;
}

// Чужой заказ по номеру смотреть нельзя
$ya = tekushiy_polzovatel();
$moy = $ya && (int) $zakaz['polzovatel_id'] === (int) $ya['id'];
$tolko_chto = (time() - strtotime($zakaz['sozdan'])) < 600;

if (!$moy && !$tolko_chto && !rol_ne_nizhe('manager')) {
    http_response_code(403);
    exit('Нет доступа к этому заказу');
}

$zagolovok = 'Заказ принят';
require __DIR__ . '/includes/header.php';
?>

<div class="uspeh-bolshoy">
    <h1>Заказ №<?= e($zakaz['nomer']) ?> принят</h1>
    <p>Сумма к оплате: <strong><?= somoni((int) $zakaz['summa_itogo']) ?> сомони</strong></p>
    <p>Мы позвоним на <?= e($zakaz['klient_telefon']) ?> в течение часа.</p>

    <?php if (voshel()): ?>
        <a class="knopka" href="kabinet.php">Мои заказы</a>
    <?php else: ?>
        <p class="tihoe">Сохраните номер заказа — по нему мы вас найдём.</p>
    <?php endif; ?>

    <a class="knopka-tihaya" href="/">Вернуться в каталог</a>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
```

⚠️ Обратите внимание на проверку доступа. Номер заказа можно подобрать —
`2026-000001`, `2026-000002` и так далее. Без проверки чужие телефоны
и адреса утекли бы одним циклом.

Гостю, только что оформившему заказ, показываем по времени: десять минут
после создания. Дальше — только своим и менеджерам.

## 🔤 Разбор по словам

| Запись | Что делает |
|---|---|
| `beginTransaction()` / `commit()` / `rollBack()` | Всё или ничего |
| `catch (Throwable $e)` | Ловит **и ошибки, и исключения** |
| `SELECT ... FOR UPDATE` | Заблокировать строку до конца транзакции |
| `UPDATE ... WHERE ostatok >= ?` | Второй рубеж защиты остатка |
| `rowCount() === 0` | Условие не выполнилось, ничего не изменилось |
| **Состояние гонки** | Двое читают одно и то же и оба записывают |
| `posledniy_id()` | Номер созданной записи |
| PRG | Перенаправление после POST |

## ⚠️ Грабли

**Брать цену из корзины или формы.** Только из базы, внутри транзакции.

**Забыть транзакцию.** Заказ создастся, остатки не спишутся.

**`catch (Exception)` вместо `Throwable`.** Фатальная ошибка пролетит мимо,
транзакция останется открытой.

**Не блокировать строку.** Двое купят последнюю единицу.

**Держать в транзакции отправку писем.** Почтовый сервер тормозит — блокировка
висит, остальные покупатели ждут.

**Не сделать PRG.** F5 создаст второй заказ.

**Показывать заказ по номеру без проверки.** Чужие телефоны и адреса утекут.

**Технический текст ошибки покупателю.** «SQLSTATE[23000]» ничего ему не говорит.

## 🏋️ Задачи

**Задача 41.1.** Реализуйте оформление заказа с транзакцией. Проверьте,
что остатки списываются.

**Задача 41.2.** Проверьте откат: временно добавьте `throw new RuntimeException('тест')`
перед `commit()`. Заказ создался? Остатки изменились?

**Задача 41.3.** Смоделируйте гонку. Поставьте товару остаток 1, откройте
оформление в двух браузерах и отправьте почти одновременно. Что произошло?

**Задача 41.4.** Проверьте PRG: оформите заказ и нажмите F5. Появился дубль?

**Задача 41.5.** Что случится и почему?

```php
$pdo->beginTransaction();
vypolnit('INSERT INTO zakazy ...');
otpravit_pismo($email, 'Заказ принят', '...');   // ← 30 секунд
vypolnit('UPDATE tovary SET ostatok = ostatok - 1 ...');
$pdo->commit();
```

**Задача 41.6.** Сделайте так, чтобы вошедшему покупателю поля имени и телефона
заполнялись сами.

**Задача 41.7.** Добавьте расчёт доставки без перезагрузки: выбрал курьера —
сумма пересчиталась.

**Задача 41.8.** Реализуйте отмену заказа покупателем — только если статус
ещё `novyi`. Не забудьте вернуть остатки.

**Задача 41.9.** Попробуйте открыть чужой заказ, подставив номер в адрес.
Получилось? Почините.

*Ответы — в [Приложении Г](../prilozheniya/4-G-otvety.md).*

## 🔗 В бою

Откройте `buyer/checkout.php` в этом репозитории — оформление настоящего
магазина. Найдёте транзакцию, блокировку строк и пересчёт цены из базы.

Одна вещь там устроена сложнее, чем у нас: заказ **разбивается на подзаказы
по продавцам** в таблицу `order_sellers`. Один заказ покупателя может касаться
трёх разных магазинов, и каждому нужно видеть только своё.

Там же фиксируется **комиссия на момент заказа**. Причина та же, что с ценой:
условия могут измениться, но по старым заказам расчёт должен остаться прежним.

Разберём это в главе 46.

## 📌 Итог

- Оформление — **цепочка действий**, которые должны выполниться все или ни одно.
- **Транзакция**: `beginTransaction` → `commit`, при ошибке `rollBack`.
- Ловите `Throwable`, а не `Exception`.
- **Цена берётся из базы внутри транзакции.** Никогда из корзины или формы.
- **Состояние гонки**: `SELECT ... FOR UPDATE` плюс `WHERE ostatok >= ?`.
- Проверяйте `rowCount()` после `UPDATE` — ноль означает, что условие не прошло.
- Письма и очистка корзины — **после** транзакции.
- **PRG** обязателен: F5 не должен создавать дубль.
- Заказ по номеру — только своему или менеджеру.
- Ошибки формулируйте так, чтобы покупатель понял, что делать.

Дальше — админка, где всеми этими заказами управляют.

[← Глава 40](40-roli-i-dostup.md) · [Глава 42. Админка →](42-adminka.md)
