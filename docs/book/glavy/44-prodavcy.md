# Глава 44. Продавцы и владение товаром

> **Часть IX. Маркетплейс** · Глава 44 из 60
> [← Глава 43](43-uvedomleniya.md) · [Глава 45 →](45-moderaciya.md)

## 🎯 Зачем эта глава

До сих пор все товары были ваши. Теперь превращаем магазин в **маркетплейс**:
площадку, где торгуют другие, а вы берёте комиссию.

Это меняет модель бизнеса целиком:

| | Обычный магазин | Маркетплейс |
|---|---|---|
| Кто закупает товар | Вы | Продавцы |
| Кто держит склад | Вы | Продавцы |
| Кто рискует деньгами | Вы | Продавцы |
| Ваш доход | Наценка | **Комиссия** |
| Что вы даёте | Товар | **Покупателей и доверие** |

Именно так работают Ozon, Wildberries и Amazon. И именно этот путь выбрал
autodoc.tj — код которого лежит рядом с этой книгой.

Технически всё сводится к одному вопросу: **чей это товар и кто что может
с ним делать**. Разберём.

## 📖 Продавец — это отдельная сущность

Мы завели таблицу ещё в главе 31, но давайте разберём, почему она устроена
именно так.

```sql
CREATE TABLE prodavcy (
    id                INT AUTO_INCREMENT PRIMARY KEY,
    polzovatel_id     INT NOT NULL,
    nazvanie          VARCHAR(255) NOT NULL,
    gorod             VARCHAR(128) NOT NULL DEFAULT '',
    opisanie          TEXT,
    komissiya_procent DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    status            ENUM('na_proverke','odobren','zablokirovan')
                      NOT NULL DEFAULT 'na_proverke',
    ...
    UNIQUE KEY uniq_polzovatel (polzovatel_id)
);
```

**Почему продавец и пользователь — разные таблицы?**

Логично было бы добавить поля прямо в `polzovateli` — там же уже есть роль
`prodavec`. Но так делать не стоит:

- у магазина есть **свои данные**: название, город, описание, реквизиты.
  В таблице пользователей им не место;
- **комиссия договорная** — у каждого продавца своя;
- **статус модерации** относится к магазину, а не к человеку;
- позже понадобится **несколько сотрудников на один магазин** — и связь
  превратится из «один к одному» в «один ко многим».

**Правило: разные сущности — разные таблицы**, даже если сейчас они связаны
один к одному. Разделить потом дороже, чем сразу.

`UNIQUE KEY uniq_polzovatel` пока запрещает второй магазин на одного человека.
Когда понадобится — ключ снимается, и структура готова.

## 📖 Регистрация продавца

```php
<?php
// includes/prodavcy.php
declare(strict_types=1);

/**
 * Заявка на продавца. Магазин создаётся сразу, но со статусом «на проверке» —
 * до одобрения торговать нельзя.
 */
function podat_zayavku_prodavca(int $polzovatel_id, array $d): int
{
    $est = zapros_znachenie('SELECT id FROM prodavcy WHERE polzovatel_id = ?',
                            [$polzovatel_id]);
    if ($est) {
        throw new RuntimeException('Заявка уже подана');
    }

    db()->beginTransaction();
    try {
        vypolnit('
            INSERT INTO prodavcy (polzovatel_id, nazvanie, gorod, opisanie, status)
            VALUES (?, ?, ?, ?, "na_proverke")
        ', [$polzovatel_id, $d['nazvanie'], $d['gorod'], $d['opisanie'] ?? '']);

        $prodavec_id = posledniy_id();

        // Роль меняем сразу: кабинет должен открыться,
        // но публиковать товары он пока не сможет
        vypolnit('UPDATE polzovateli SET rol = "prodavec" WHERE id = ?', [$polzovatel_id]);

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    uvedomit_menedzherov('Новая заявка продавца: ' . $d['nazvanie']);

    return $prodavec_id;
}

/** Магазин текущего пользователя. */
function moy_magazin(): ?array
{
    $ya = tekushiy_polzovatel();
    if ($ya === null) return null;

    return zapros_odin('SELECT * FROM prodavcy WHERE polzovatel_id = ?', [$ya['id']]);
}

/** Может ли продавец публиковать товары. */
function magazin_odobren(): bool
{
    $m = moy_magazin();
    return $m !== null && $m['status'] === 'odobren';
}
```

⚠️ Обратите внимание: **роль меняется сразу, а право торговать — нет.**

Кабинет открывается, чтобы человек мог заполнить данные, загрузить документы,
подготовить товары. Но публикация закрыта до одобрения.

Это важное различие: **доступ к разделу и право на действие — разные вещи.**

## 📖 Владение товаром

Ключевое поле уже есть в таблице товаров:

```sql
prodavec_id INT DEFAULT NULL COMMENT 'NULL = товар самого магазина'
```

`NULL` означает, что товар принадлежит площадке. Так магазин может торговать
и сам — смешанная модель, которую используют почти все маркетплейсы.

### **Запросы продавца**

Помните правило из главы 40: **фильтр владения в самом запросе**.

```php
/** Товары продавца. Чужие сюда попасть не могут физически. */
function tovary_prodavca(int $prodavec_id, array $filtry = [],
                         int $stranica = 1, int $na_stranice = 20): array
{
    $usloviya = ['t.prodavec_id = ?'];
    $parametry = [$prodavec_id];

    if (!empty($filtry['status'])) {
        $usloviya[] = 't.status = ?';
        $parametry[] = $filtry['status'];
    }

    if (!empty($filtry['zapros'])) {
        $usloviya[] = '(t.nazvanie LIKE ? OR t.artikul LIKE ?)';
        $shablon = '%' . $filtry['zapros'] . '%';
        $parametry[] = $shablon;
        $parametry[] = $shablon;
    }

    $where = implode(' AND ', $usloviya);
    $offset = (max(1, $stranica) - 1) * $na_stranice;

    return [
        'tovary' => zapros("
            SELECT t.id, t.nazvanie, t.artikul, t.cena, t.ostatok, t.status, t.aktivnyi
            FROM tovary AS t
            WHERE $where
            ORDER BY t.izmenen DESC
            LIMIT $na_stranice OFFSET $offset
        ", $parametry),

        'vsego' => (int) zapros_znachenie(
            "SELECT COUNT(*) FROM tovary AS t WHERE $where", $parametry
        ),
    ];
}

/** Один товар продавца. Чужой просто не найдётся. */
function tovar_prodavca(int $tovar_id, int $prodavec_id): ?array
{
    return zapros_odin('
        SELECT * FROM tovary WHERE id = ? AND prodavec_id = ?
    ', [$tovar_id, $prodavec_id]);
}
```

**Ни в одной из этих функций нет отдельной проверки прав** — и это правильно.
Условие владения встроено в запрос, забыть его невозможно.

## 💻 Кабинет продавца

```php
<?php
// seller/index.php
require_once __DIR__ . '/_zashita.php';   // trebuetsya_rol('prodavec')

$magazin = moy_magazin();

if ($magazin === null) {
    header('Location: /seller/zayavka.php');
    exit;
}

$filtry = [
    'status' => $_GET['status'] ?? '',
    'zapros' => trim($_GET['q'] ?? ''),
];
$stranica = max(1, (int) ($_GET['page'] ?? 1));

$r = tovary_prodavca((int) $magazin['id'], $filtry, $stranica);

// Сводка по статусам — одним запросом
$po_statusam = zapros('
    SELECT status, COUNT(*) AS shtuk
    FROM tovary WHERE prodavec_id = ?
    GROUP BY status
', [$magazin['id']]);
$svodka = array_column($po_statusam, 'shtuk', 'status');

$zagolovok = 'Мои товары';
require __DIR__ . '/_shapka.php';
?>

<?php if ($magazin['status'] === 'na_proverke'): ?>
    <div class="preduprezhdenie">
        <strong>Магазин на проверке.</strong>
        Вы можете добавлять товары, но публиковать их получится
        после одобрения. Обычно это занимает один рабочий день.
    </div>
<?php elseif ($magazin['status'] === 'zablokirovan'): ?>
    <div class="oshibka-obshaya">
        <strong>Магазин заблокирован.</strong>
        Свяжитесь с нами: <?= e(SAIT_TELEFON) ?>
    </div>
<?php endif; ?>

<h1><?= e($magazin['nazvanie']) ?></h1>

<div class="plitki">
    <a class="plitka" href="?status=opublikovan">
        <span class="chislo"><?= (int) ($svodka['opublikovan'] ?? 0) ?></span>
        <span class="podpis">опубликовано</span>
    </a>
    <a class="plitka <?= !empty($svodka['na_moderacii']) ? 'trebuet-vnimaniya' : '' ?>"
       href="?status=na_moderacii">
        <span class="chislo"><?= (int) ($svodka['na_moderacii'] ?? 0) ?></span>
        <span class="podpis">на модерации</span>
    </a>
    <a class="plitka <?= !empty($svodka['otklonen']) ? 'trebuet-vnimaniya' : '' ?>"
       href="?status=otklonen">
        <span class="chislo"><?= (int) ($svodka['otklonen'] ?? 0) ?></span>
        <span class="podpis">отклонено</span>
    </a>
    <a class="plitka" href="?status=chernovik">
        <span class="chislo"><?= (int) ($svodka['chernovik'] ?? 0) ?></span>
        <span class="podpis">черновики</span>
    </a>
</div>

<div class="panel-deystviy">
    <a class="knopka" href="tovar.php">Добавить товар</a>
    <form method="GET" class="vstroennaya">
        <input type="search" name="q" value="<?= e($filtry['zapros']) ?>"
               placeholder="название или артикул">
        <button type="submit">Найти</button>
    </form>
</div>

<table class="spisok plotnaya">
    <thead>
        <tr><th>Товар</th><th>Артикул</th><th>Цена</th><th>Остаток</th><th>Статус</th><th></th></tr>
    </thead>
    <tbody>
    <?php foreach ($r['tovary'] as $t): ?>
        <tr>
            <td><a href="tovar.php?id=<?= (int) $t['id'] ?>"><?= e($t['nazvanie']) ?></a></td>
            <td class="mono"><?= e($t['artikul']) ?></td>
            <td class="chislo-yacheyka mono"><?= somoni((int) $t['cena']) ?></td>
            <td class="chislo-yacheyka <?= (int) $t['ostatok'] === 0 ? 'nulevoy' : '' ?>">
                <?= (int) $t['ostatok'] ?>
            </td>
            <td><span class="status s-<?= e($t['status']) ?>">
                <?= e(status_tovara_podpis($t['status'])) ?>
            </span></td>
            <td><a href="tovar.php?id=<?= (int) $t['id'] ?>">Изменить</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php if (empty($r['tovary'])): ?>
    <div class="pusto">
        <h3>Товаров пока нет</h3>
        <p>Добавьте первый товар — он попадёт на модерацию.</p>
        <a class="knopka" href="tovar.php">Добавить товар</a>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/_podval.php'; ?>
```

## 📖 Форма товара продавца

Отличается от админской: продавцу доступно **не всё**.

```php
<?php
// seller/tovar.php
require_once __DIR__ . '/_zashita.php';

$magazin = moy_magazin();
if ($magazin === null || $magazin['status'] === 'zablokirovan') {
    http_response_code(403);
    exit('Магазин не активен');
}

$id = (int) ($_GET['id'] ?? 0);
$tovar = $id > 0 ? tovar_prodavca($id, (int) $magazin['id']) : null;

// id есть, но товар не нашёлся — значит, чужой. Отдаём 404,
// чтобы даже не подтверждать существование такого товара
if ($id > 0 && $tovar === null) {
    http_response_code(404);
    exit('Товар не найден');
}

$novyi = $tovar === null;
$oshibki = [];
$d = $tovar ?? [
    'nazvanie' => '', 'artikul' => '', 'brend' => '', 'kategoriya_id' => 0,
    'cena' => 0, 'ostatok' => 0, 'opisanie' => '', 'status' => 'chernovik',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_proverit();

    $d['nazvanie']      = trim($_POST['nazvanie'] ?? '');
    $d['artikul']       = trim($_POST['artikul'] ?? '');
    $d['brend']         = trim($_POST['brend'] ?? '');
    $d['kategoriya_id'] = (int) ($_POST['kategoriya_id'] ?? 0);
    $d['cena']          = (int) round((float) ($_POST['cena'] ?? 0) * 100);
    $d['ostatok']       = max(0, (int) ($_POST['ostatok'] ?? 0));
    $d['opisanie']      = trim($_POST['opisanie'] ?? '');

    // Продавец выбирает: сохранить черновиком или отправить на модерацию
    $otpravit = ($_POST['deystvie'] ?? '') === 'na_moderaciyu';

    if (mb_strlen($d['nazvanie']) < 5) {
        $oshibki['nazvanie'] = 'Название от 5 символов — покупатель должен понять, что это';
    }
    if ($d['artikul'] === '') {
        $oshibki['artikul'] = 'Артикул обязателен';
    }
    if ($d['cena'] <= 0) {
        $oshibki['cena'] = 'Укажите цену';
    }
    if ($otpravit && mb_strlen($d['opisanie']) < 20) {
        $oshibki['opisanie'] = 'Для публикации нужно описание от 20 символов';
    }
    if ($otpravit && !magazin_odobren()) {
        $oshibki['obshaya'] = 'Магазин ещё на проверке. Пока можно сохранять черновики.';
    }

    if (empty($oshibki)) {
        $status = $otpravit ? 'na_moderacii' : 'chernovik';

        if ($novyi) {
            vypolnit('
                INSERT INTO tovary
                    (prodavec_id, nazvanie, artikul, brend, kategoriya_id,
                     cena, ostatok, opisanie, status, aktivnyi)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
            ', [
                $magazin['id'], $d['nazvanie'], $d['artikul'], $d['brend'],
                $d['kategoriya_id'] ?: null, $d['cena'], $d['ostatok'],
                $d['opisanie'], $status,
            ]);
            $id = posledniy_id();
        } else {
            // Изменили опубликованный товар — снова на модерацию
            $novyi_status = ($tovar['status'] === 'opublikovan' && $otpravit)
                ? 'na_moderacii'
                : $status;

            vypolnit('
                UPDATE tovary SET
                    nazvanie = ?, artikul = ?, brend = ?, kategoriya_id = ?,
                    cena = ?, ostatok = ?, opisanie = ?, status = ?
                WHERE id = ? AND prodavec_id = ?
            ', [
                $d['nazvanie'], $d['artikul'], $d['brend'],
                $d['kategoriya_id'] ?: null, $d['cena'], $d['ostatok'],
                $d['opisanie'], $novyi_status, $id, $magazin['id'],
            ]);
        }

        if ($otpravit) {
            uvedomit_menedzherov('Товар на модерацию: ' . $d['nazvanie']);
        }

        $_SESSION['flash'] = ['soobshenie' => $otpravit
            ? 'Товар отправлен на модерацию'
            : 'Черновик сохранён'];

        header('Location: tovar.php?id=' . $id);
        exit;
    }
}
```

### **Три решения, которые стоит заметить**

**Продавец не задаёт статус напрямую.** Он выбирает действие: «сохранить»
или «отправить на модерацию». Статус ставит система.

Иначе продавец просто выставил бы `opublikovan` и обошёл проверку.

**Изменение опубликованного товара возвращает его на модерацию.** Иначе схема
взлома очевидна: выложить приличный товар, дождаться одобрения, потом
поменять название и цену на что угодно.

**`WHERE id = ? AND prodavec_id = ?` даже в `UPDATE`.** Двойная защита:
даже если проверка выше как-то обойдётся, запрос не тронет чужой товар.

## 📖 Витрина: чей товар показывать

Каталог теперь смешанный:

```php
function katalog_marketplace(array $filtry, int $stranica = 1): array
{
    $usloviya = [
        't.aktivnyi = 1',
        't.status = "opublikovan"',
        // Товары заблокированных продавцов скрываем,
        // но собственные товары площадки (prodavec_id IS NULL) остаются
        '(t.prodavec_id IS NULL OR p.status = "odobren")',
    ];
    $parametry = [];

    // ... остальные фильтры ...

    $where = implode(' AND ', $usloviya);

    return zapros("
        SELECT t.id, t.nazvanie, t.artikul, t.cena, t.ostatok,
               p.nazvanie AS prodavec, p.gorod AS prodavec_gorod
        FROM tovary AS t
        LEFT JOIN prodavcy AS p ON p.id = t.prodavec_id
        WHERE $where
        ORDER BY t.nazvanie
        LIMIT 20
    ", $parametry);
}
```

⚠️ **`LEFT JOIN`, а не `INNER`.** Помните главу 29: `INNER JOIN` выбросил бы
товары самой площадки, у которых `prodavec_id` пустой.

Классическая ошибка, из-за которой половина каталога исчезает без объяснений.

### **Показывать ли продавца покупателю**

Вопрос не технический, а бизнесовый, и решается по-разному:

| Подход | Кто так делает | Смысл |
|---|---|---|
| Показывать явно | Ozon, Wildberries | Покупатель выбирает продавца по рейтингу |
| Не показывать | Многие нишевые | Площадка отвечает за всё сама |
| Показывать в карточке | Промежуточный | В списке не мешает, в карточке видно |

Для магазина запчастей разумен третий: в каталоге важна цена и наличие,
а в карточке полезно знать, что товар из Худжанда, а не из Душанбе —
это влияет на срок доставки.

## 🔤 Разбор по словам

| Запись | Что означает |
|---|---|
| **Маркетплейс** | Площадка, где торгуют другие, а вы берёте комиссию |
| `prodavec_id` | Владелец товара. `NULL` — сам магазин |
| `status = 'na_proverke'` | Магазин создан, но торговать нельзя |
| `UNIQUE KEY uniq_polzovatel` | Один магазин на человека |
| Фильтр владения в запросе | Чужое не находится физически |
| `LEFT JOIN prodavcy` | Товары площадки не потеряются |
| Изменение → снова модерация | Защита от подмены после одобрения |

## ⚠️ Грабли

**Складывать продавца в таблицу пользователей.** Разные сущности — разные
таблицы.

**Давать продавцу задавать статус напрямую.** Обойдёт модерацию.

**Не возвращать на модерацию после изменения.** Схема подмены товара.

**`INNER JOIN prodavcy` в каталоге.** Потеряются товары самой площадки.

**Проверять владение только на странице, но не в `UPDATE`.** Один пропуск —
и чужой товар изменён.

**Показывать одобренный магазин и заблокированный одинаково.** Товары
заблокированного должны исчезнуть с витрины.

**Открывать кабинет только после одобрения.** Человек не сможет подготовиться
и уйдёт.

## 🏋️ Задачи

**Задача 44.1.** Сделайте заявку на продавца и кабинет со списком своих товаров.

**Задача 44.2.** Проверьте владение: зайдите продавцом и попробуйте открыть
`tovar.php?id=` с чужим номером. Что произошло?

**Задача 44.3.** Реализуйте два действия в форме: «сохранить черновик»
и «отправить на модерацию».

**Задача 44.4.** Сделайте так, чтобы изменение опубликованного товара
возвращало его на модерацию.

**Задача 44.5.** Добавьте в каталог показ продавца и его города в карточке
товара.

**Задача 44.6.** Заблокируйте продавца и убедитесь, что его товары исчезли
с витрины, но остались в его кабинете.

**Задача 44.7.** Найдите ошибку:

```php
$tovary = zapros('
    SELECT t.*, p.nazvanie AS prodavec
    FROM tovary AS t
    INNER JOIN prodavcy AS p ON p.id = t.prodavec_id
    WHERE t.status = "opublikovan"
');
```

**Задача 44.8.** Подумайте и запишите: что должно произойти с заказами
продавца, которого заблокировали? А с его товарами в чужих корзинах?

*Ответы — в [Приложении Г](../prilozheniya/4-G-otvety.md).*

## 🔗 В бою

Откройте `seller/` и `admin/sellers.php` в этом репозитории — это Фаза 1
проекта, и она полностью готова.

Обратите внимание на одну деталь: комиссия хранится **у продавца**,
но при заказе **копируется в позицию заказа**. Мы уже видели этот приём
в главе 29 с ценой.

Причина та же: условия могут пересмотреть, но по старым заказам расчёт
должен остаться прежним. Иначе изменение комиссии задним числом переписало бы
всю историю выплат — и продавцы справедливо возмутились бы.

## 📌 Итог

- **Маркетплейс** зарабатывает на комиссии, а не на наценке.
- Продавец — **отдельная таблица**, даже при связи один к одному.
- Роль открывает **раздел**, статус магазина даёт **право торговать**.
  Это разные вещи.
- Владение проверяется **фильтром в запросе**, а не отдельной проверкой.
- Продавец **не задаёт статус** — только выбирает действие.
- Изменение опубликованного товара **возвращает его на модерацию**.
- В каталоге маркетплейса — **`LEFT JOIN`**, иначе исчезнут товары площадки.
- Комиссия фиксируется в момент заказа.

Дальше — модерация: как проверять то, что выкладывают продавцы.

[← Глава 43](43-uvedomleniya.md) · [Глава 45. Модерация →](45-moderaciya.md)
