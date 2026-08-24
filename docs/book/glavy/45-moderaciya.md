# Глава 45. Модерация

> **Часть IX. Маркетплейс** · Глава 45 из 60
> [← Глава 44](44-prodavcy.md) · [Глава 46 →](46-razbivka-zakaza.md)

## 🎯 Зачем эта глава

Продавцы выкладывают товары. Без проверки на витрине быстро появится:

- «Колодки» без указания, к какой машине;
- фотография из интернета вместо реального товара;
- цена 1 сомони, чтобы попасть в верх сортировки;
- запрещённые или опасные вещи;
- просто мусор в описании.

**Модерация — фильтр между продавцом и покупателем.** И одновременно место,
где легко всё испортить: слишком строгая отпугнёт продавцов, слишком мягкая
испортит витрину.

Разберём, как сделать её быстрой для менеджера и понятной для продавца.

## 📖 Жизненный цикл товара

```
chernovik ──отправил──→ na_moderacii ──одобрил──→ opublikovan
                              │                        │
                              └──отклонил──→ otklonen  │
                                     │                 │
                                     └──исправил и отправил снова
                                                       │
                              изменил опубликованный ──┘
```

Четыре состояния, и переход между ними всегда решает **система**, а не продавец.

Помните главу 44: продавец нажимает «отправить на модерацию», а `na_moderacii`
проставляет код.

## 📖 Автоматические проверки

Прежде чем звать человека, пусть часть работы сделает код.

```php
<?php
// includes/moderaciya.php
declare(strict_types=1);

/**
 * Автопроверка товара. Возвращает список замечаний.
 * Пустой список — можно отдавать человеку на проверку.
 */
function avtoproverka_tovara(array $t): array
{
    $zamechaniya = [];

    // Название
    if (mb_strlen($t['nazvanie']) < 10) {
        $zamechaniya[] = 'Слишком короткое название — покупатель не поймёт, что это';
    }
    if (mb_strtoupper($t['nazvanie']) === $t['nazvanie'] && mb_strlen($t['nazvanie']) > 15) {
        $zamechaniya[] = 'Название набрано заглавными буквами';
    }
    if (preg_match('/(.)\1{4,}/u', $t['nazvanie'])) {
        $zamechaniya[] = 'В названии повторяющиеся символы';
    }

    // Цена
    if ((int) $t['cena'] < 100) {
        $zamechaniya[] = 'Подозрительно низкая цена — проверьте, не ошибка ли';
    }

    // Описание
    if (mb_strlen($t['opisanie'] ?? '') < 20) {
        $zamechaniya[] = 'Нет описания';
    }
    if (preg_match('/(https?:\/\/|@|\+992|\bтел\b)/ui', $t['opisanie'] ?? '')) {
        $zamechaniya[] = 'В описании контакты или ссылки — покупатель должен '
                       . 'оформлять заказ на площадке';
    }

    // Артикул
    if (!preg_match('/^[A-Za-z0-9\.\-\/]{3,64}$/', $t['artikul'])) {
        $zamechaniya[] = 'Артикул выглядит неправильно';
    }

    // Дубликат
    $dublikat = zapros_odin('
        SELECT id, prodavec_id FROM tovary
        WHERE artikul = ? AND id <> ? AND status = "opublikovan"
    ', [$t['artikul'], $t['id'] ?? 0]);

    if ($dublikat && (int) $dublikat['prodavec_id'] === (int) $t['prodavec_id']) {
        $zamechaniya[] = 'У вас уже есть опубликованный товар с этим артикулом';
    }

    return $zamechaniya;
}
```

⚠️ Обратите внимание на проверку контактов в описании. Это не придирка:
продавцы пытаются увести покупателя мимо площадки, чтобы не платить комиссию.
На маркетплейсах это одна из главных причин блокировок.

**Автопроверка не заменяет человека**, а экономит его время. Явный мусор
отсеивается сразу, до очереди.

## 💻 Очередь модерации

```php
<?php
// admin/moderaciya.php
require_once __DIR__ . '/_zashita.php';

$tovary = zapros('
    SELECT t.*, p.nazvanie AS prodavec, p.gorod, p.status AS status_prodavca,
           (SELECT COUNT(*) FROM tovary
            WHERE prodavec_id = t.prodavec_id AND status = "otklonen") AS otklonen_ranee
    FROM tovary AS t
    LEFT JOIN prodavcy AS p ON p.id = t.prodavec_id
    WHERE t.status = "na_moderacii"
    ORDER BY t.izmenen
    LIMIT 50
');

$zagolovok = 'Модерация';
require __DIR__ . '/_shapka.php';
?>

<h1>На модерации <span class="tihoe"><?= count($tovary) ?></span></h1>

<?php if (empty($tovary)): ?>
    <div class="pusto"><h3>Очередь пуста</h3><p>Все товары проверены.</p></div>
<?php endif; ?>

<?php foreach ($tovary as $t): ?>
    <?php $zamechaniya = avtoproverka_tovara($t); ?>

    <article class="karta-moderacii">
        <div class="km-shapka">
            <div>
                <h2><?= e($t['nazvanie']) ?></h2>
                <p class="tihoe">
                    <?= e($t['artikul']) ?> ·
                    <?= e($t['prodavec'] ?? 'площадка') ?>
                    <?php if ($t['gorod']): ?> · <?= e($t['gorod']) ?><?php endif; ?>
                    <?php if ((int) $t['otklonen_ranee'] > 0): ?>
                        · <span class="metka-vnimanie">
                            ранее отклонено: <?= (int) $t['otklonen_ranee'] ?>
                          </span>
                    <?php endif; ?>
                </p>
            </div>
            <div class="km-cena"><?= somoni((int) $t['cena']) ?> сомони</div>
        </div>

        <?php if ($zamechaniya): ?>
            <ul class="km-zamechaniya">
                <?php foreach ($zamechaniya as $z): ?>
                    <li><?= e($z) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if (!empty($t['opisanie'])): ?>
            <p class="km-opisanie"><?= nl2br(e(mb_substr($t['opisanie'], 0, 400))) ?></p>
        <?php endif; ?>

        <div class="km-deystviya">
            <form method="POST" action="moderaciya_reshenie.php" class="vstroennaya">
                <?= csrf_pole() ?>
                <input type="hidden" name="tovar_id" value="<?= (int) $t['id'] ?>">
                <input type="hidden" name="reshenie" value="odobrit">
                <button type="submit" class="knopka-uspeh">Одобрить</button>
            </form>

            <form method="POST" action="moderaciya_reshenie.php" class="forma-otkloneniya">
                <?= csrf_pole() ?>
                <input type="hidden" name="tovar_id" value="<?= (int) $t['id'] ?>">
                <input type="hidden" name="reshenie" value="otklonit">

                <select name="prichina" required>
                    <option value="">Причина отклонения</option>
                    <option value="nazvanie">Неинформативное название</option>
                    <option value="opisanie">Нет или плохое описание</option>
                    <option value="cena">Сомнительная цена</option>
                    <option value="foto">Проблема с фотографией</option>
                    <option value="kontakty">Контакты в описании</option>
                    <option value="dublikat">Дубликат</option>
                    <option value="zapresheno">Запрещённый товар</option>
                </select>

                <input type="text" name="kommentariy" placeholder="Что исправить"
                       maxlength="255">

                <button type="submit" class="knopka-tihaya">Отклонить</button>
            </form>

            <a class="knopka-tihaya" href="/tovar.php?id=<?= (int) $t['id'] ?>"
               target="_blank" rel="noopener">Как увидит покупатель</a>
        </div>
    </article>
<?php endforeach; ?>

<?php require __DIR__ . '/_podval.php'; ?>
```

### **Почему очередь устроена именно так**

**Всё на одной странице.** Модератор не открывает карточки по одной —
он листает и решает. Пятьдесят товаров за десять минут вместо часа.

**Замечания автопроверки сразу видны.** Не нужно вчитываться: код уже
подсветил подозрительное.

**Причина отклонения — из списка.** Свободный текст модератор писать поленится,
и продавец получит «отклонено» без объяснений. Список решает это за один клик.

**Счётчик прошлых отклонений.** Продавец, у которого отклонено двадцать
товаров, требует более внимательной проверки.

**Сортировка по дате изменения** — сначала те, кто ждёт дольше всех.
Справедливо и не даёт заявкам зависать.

## 💻 Решение модератора

```php
<?php
// admin/moderaciya_reshenie.php
require_once __DIR__ . '/_zashita.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Метод не разрешён');
}
csrf_proverit();

$tovar_id = (int) ($_POST['tovar_id'] ?? 0);
$reshenie = $_POST['reshenie'] ?? '';

$tovar = zapros_odin('SELECT * FROM tovary WHERE id = ?', [$tovar_id]);
if ($tovar === null) {
    http_response_code(404);
    exit('Товар не найден');
}

$prichiny = [
    'nazvanie'   => 'Название не описывает товар. Укажите, что это и к чему подходит.',
    'opisanie'   => 'Нужно описание: характеристики, совместимость, состояние.',
    'cena'       => 'Проверьте цену — она выглядит ошибочной.',
    'foto'       => 'Нужна фотография реального товара.',
    'kontakty'   => 'Уберите контакты и ссылки из описания.',
    'dublikat'   => 'Такой товар у вас уже опубликован.',
    'zapresheno' => 'Товар нельзя продавать на площадке.',
];

if ($reshenie === 'odobrit') {

    vypolnit('UPDATE tovary SET status = "opublikovan" WHERE id = ?', [$tovar_id]);
    zapisat_deystvie('odobril_tovar', 'tovar', $tovar_id);

    uvedomit_prodavca($tovar, 'Товар опубликован',
        'Ваш товар «' . $tovar['nazvanie'] . '» прошёл проверку и появился в каталоге.');

} elseif ($reshenie === 'otklonit') {

    $kod = $_POST['prichina'] ?? '';
    if (!isset($prichiny[$kod])) {
        exit('Выберите причину отклонения');
    }

    $kommentariy = trim($_POST['kommentariy'] ?? '');
    $tekst = $prichiny[$kod] . ($kommentariy !== '' ? "\n\n" . $kommentariy : '');

    db()->beginTransaction();
    try {
        vypolnit('UPDATE tovary SET status = "otklonen" WHERE id = ?', [$tovar_id]);

        // Историю решений храним: пригодится в споре и для статистики
        vypolnit('
            INSERT INTO moderaciya_istoriya
                (tovar_id, moderator_id, reshenie, prichina, kommentariy)
            VALUES (?, ?, "otklonen", ?, ?)
        ', [$tovar_id, tekushiy_polzovatel()['id'], $kod, $kommentariy]);

        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }

    zapisat_deystvie('otklonil_tovar', 'tovar', $tovar_id);
    uvedomit_prodavca($tovar, 'Товар отклонён', $tekst);
}

header('Location: moderaciya.php');
exit;
```

### **Почему отказ должен объяснять**

Сравните два сообщения продавцу:

> ❌ «Товар отклонён.»

> ✅ «Название не описывает товар. Укажите, что это и к чему подходит.
> Например: „Колодки тормозные передние Bosch для Toyota Camry 2011–2017“.»

Первое вызывает раздражение и вопрос «а что не так?». Второе — исправление
за минуту.

**Модерация — это не запрет, а обучение продавца.** Хороший маркетплейс
делает так, чтобы со второго-третьего товара продавец выкладывал правильно
сам. Плохой — отклоняет молча, и продавец уходит.

## 📖 Что видит продавец

```php
// В кабинете продавца — отклонённые с причиной
$otklonennye = zapros('
    SELECT t.id, t.nazvanie, t.izmenen,
           m.prichina, m.kommentariy, m.sozdano AS reshenie_ot
    FROM tovary AS t
    LEFT JOIN moderaciya_istoriya AS m ON m.tovar_id = t.id
    WHERE t.prodavec_id = ? AND t.status = "otklonen"
    ORDER BY m.sozdano DESC
', [$magazin['id']]);
```

```php
<?php foreach ($otklonennye as $t): ?>
    <div class="karta-otkloneniya">
        <h3><?= e($t['nazvanie']) ?></h3>
        <p class="prichina"><?= e($prichiny[$t['prichina']] ?? 'Требуется доработка') ?></p>
        <?php if ($t['kommentariy']): ?>
            <p class="kommentariy-moderatora"><?= e($t['kommentariy']) ?></p>
        <?php endif; ?>
        <a class="knopka" href="tovar.php?id=<?= (int) $t['id'] ?>">Исправить и отправить снова</a>
    </div>
<?php endforeach; ?>
```

Кнопка ведёт **сразу к исправлению**. Не «понятно», не «закрыть» —
а конкретное следующее действие.

## 📖 Доверие: когда модерацию можно ослабить

Проверять каждый товар вечно — дорого. Разумный маркетплейс постепенно
доверяет проверенным продавцам.

```php
/**
 * Нужна ли ручная проверка этому товару.
 * Проверенным продавцам с чистой историей — автопубликация.
 */
function nuzhna_ruchnaya_proverka(array $tovar, array $prodavec): bool
{
    // Новичок — всегда проверяем
    $opublikovano = (int) zapros_znachenie('
        SELECT COUNT(*) FROM tovary
        WHERE prodavec_id = ? AND status = "opublikovan"
    ', [$prodavec['id']]);

    if ($opublikovano < 10) {
        return true;
    }

    // Были отклонения за последний месяц — проверяем
    $otklonenij = (int) zapros_znachenie('
        SELECT COUNT(*) FROM moderaciya_istoriya AS m
        INNER JOIN tovary AS t ON t.id = m.tovar_id
        WHERE t.prodavec_id = ? AND m.reshenie = "otklonen"
          AND m.sozdano > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ', [$prodavec['id']]);

    if ($otklonenij > 0) {
        return true;
    }

    // Автопроверка нашла замечания — проверяем
    if (avtoproverka_tovara($tovar) !== []) {
        return true;
    }

    return false;   // доверяем
}
```

⚠️ **Даже при автопубликации оставляйте выборочный контроль.** Например,
каждый десятый товар всё равно попадает к модератору. Иначе доверие однажды
подведёт, и никто этого не заметит.

## 🔤 Разбор по словам

| Запись | Что означает |
|---|---|
| Жизненный цикл | `chernovik → na_moderacii → opublikovan / otklonen` |
| Автопроверка | Код отсеивает явный мусор до человека |
| Причина из списка | Модератор не пишет текст руками |
| `moderaciya_istoriya` | История решений: для споров и статистики |
| Счётчик отклонений | Сигнал, что продавца надо смотреть внимательнее |
| Доверенный продавец | Автопубликация при чистой истории |
| Выборочный контроль | Проверять часть даже у доверенных |

## ⚠️ Грабли

**Отклонять без объяснения.** Продавец не поймёт и уйдёт.

**Свободный текст вместо списка причин.** Модератор поленится писать.

**Открывать каждый товар отдельной страницей.** Очередь станет неподъёмной.

**Не хранить историю решений.** В споре нечем подтвердить.

**Не проверять изменения опубликованных товаров.** Схема подмены из главы 44.

**Доверять всем сразу.** Витрина зарастёт мусором.

**Не доверять никогда.** Модерация станет узким местом, продавцы уйдут
к конкурентам.

**Забыть про контакты в описании.** Продавцы уведут покупателей мимо площадки.

## 🏋️ Задачи

**Задача 45.1.** Сделайте очередь модерации со всеми товарами на одной странице.

**Задача 45.2.** Реализуйте автопроверку и покажите замечания в очереди.

**Задача 45.3.** Сделайте отклонение с причиной из списка и уведомлением
продавцу.

**Задача 45.4.** Заведите таблицу истории модерации и записывайте туда решения.

**Задача 45.5.** Покажите продавцу отклонённые товары с причиной и кнопкой
«исправить».

**Задача 45.6.** Добавьте автопубликацию для продавцов с десятью товарами
и без отклонений за месяц.

**Задача 45.7.** Добавьте выборочный контроль: каждый десятый товар доверенного
продавца всё равно на проверку. Подсказка: `random_int(1, 10) === 1`.

**Задача 45.8.** Придумайте пять своих правил автопроверки для магазина
запчастей. Что ещё стоит ловить автоматически?

**Задача 45.9.** Отклоните свой товар и прочитайте сообщение глазами продавца.
Понятно ли, что делать? Перепишите, если нет.

*Ответы — в [Приложении Г](../prilozheniya/G-otvety.md).*

## 🔗 В бою

Откройте `admin/product_moderation.php` в этом репозитории. Увидите ту же
очередь: всё на одной странице, решение в один клик.

Обратите внимание, что модератор видит **ссылку «как увидит покупатель»**.
Это важнее, чем кажется: товар в форме и товар на витрине выглядят по-разному,
и часть проблем видна только на витрине.

И ещё: в проекте отклонение **не удаляет товар**. Он остаётся у продавца
со статусом `otklonen` и всеми данными — исправить и отправить снова можно
за минуту. Заставлять заводить заново — верный способ потерять продавца.

## 📌 Итог

- Модерация — **фильтр между продавцом и покупателем**.
- Жизненный цикл: черновик → на модерации → опубликован или отклонён.
- **Автопроверка** отсеивает мусор до человека и экономит его время.
- Ловите **контакты в описании**: продавцы уводят покупателей мимо площадки.
- Очередь — **всё на одной странице**, решение в один клик.
- **Причина отклонения из списка**, не свободным текстом.
- **Отказ должен объяснять, что исправить.** Модерация — это обучение.
- Храните **историю решений** — пригодится в споре.
- Доверенным продавцам — автопубликация, но с выборочным контролем.

Дальше — самое денежное: разбивка заказа по продавцам и комиссия.

[← Глава 44](44-prodavcy.md) · [Глава 46. Разбивка заказа и комиссия →](46-razbivka-zakaza.md)
