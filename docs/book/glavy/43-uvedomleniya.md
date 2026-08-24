# Глава 43. Уведомления и письма

> **Часть VIII. Магазин** · Глава 43 из 60
> [← Глава 42](42-adminka.md) · [Глава 44 →](44-prodavcy.md)

## 🎯 Зачем эта глава

Заказ оформлен. Покупатель видит «спасибо» — и уходит в неизвестность.
Приняли ли заказ? Когда позвонят? Не потерялся ли он?

Менеджер тем временем не знает, что заказ появился, пока не откроет админку.

Уведомления решают обе проблемы. Разберём три канала — письмо, сообщение
в мессенджер, уведомление внутри сайта — и то, о чём чаще всего забывают:
**что делать, когда отправка не удалась**.

## 📖 Главное правило

> **Уведомление не должно ронять основное действие.**

Заказ создан — значит, он создан. Даже если почтовый сервер лежит, письмо
не ушло, а мессенджер не ответил.

Помните главу 41: отправку писем мы намеренно вынесли **за пределы транзакции**
и обернули в отдельный `try/catch`. Это не перестраховка, а обязательное
требование.

```php
// ✅ Правильно
$zakaz = sozdat_zakaz($dannye);          // главное действие

try {
    otpravit_uvedomleniya($zakaz['id']);  // побочное
} catch (Throwable $e) {
    error_log('Уведомление не ушло: ' . $e->getMessage());
}

header('Location: spasibo.php?nomer=' . urlencode($zakaz['nomer']));
```

Покупатель получит подтверждение на экране в любом случае. А то, что письмо
не дошло, — проблема, которую разберёт администратор по логам.

## 📖 Письма из PHP

### **Почему не `mail()`**

```php
mail($komu, $tema, $tekst);   // ⚠️ так лучше не делать
```

Встроенная функция `mail()` работает, но у неё три беды:

1. **Письма уходят в спам.** Без подписи домена почтовые службы им не верят;
2. **Нет обратной связи.** Вернула `true` — а дошло письмо или нет, неизвестно;
3. **Кириллица и вложения** требуют возни с заголовками вручную.

### **Правильный способ: SMTP**

Отправка через настоящий почтовый сервер — свой или сервиса рассылок.
Библиотека PHPMailer ставится через Composer (глава 59):

```php
<?php
// includes/pochta.php
declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

function otpravit_pismo(string $komu, string $tema, string $html, string $tekst = ''): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_POLZOVATEL;
        $mail->Password   = SMTP_PAROL;      // из git-ignored файла!
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_OT_KOGO, SAIT_NAZVANIE);
        $mail->addAddress($komu);
        $mail->addReplyTo(SAIT_POCHTA, SAIT_NAZVANIE);

        $mail->isHTML(true);
        $mail->Subject = $tema;
        $mail->Body    = $html;
        // Текстовая версия для почтовиков без HTML и против спам-фильтров
        $mail->AltBody = $tekst !== '' ? $tekst : strip_tags($html);

        $mail->send();
        return true;

    } catch (PHPMailerException $e) {
        error_log('Письмо не отправлено на ' . $komu . ': ' . $mail->ErrorInfo);
        return false;
    }
}
```

⚠️ **`SMTP_PAROL` — секрет.** Хранится там же, где пароль от базы:
в файле, которого нет в репозитории. Помните главу 32.

### **Чтобы письма доходили**

Три записи в настройках домена. Без них письма будут падать в спам,
и никакой код это не исправит:

| Запись | Что делает |
|---|---|
| **SPF** | Говорит, какие серверы вправе отправлять письма от вашего домена |
| **DKIM** | Электронная подпись письма — подтверждает, что его не подменили |
| **DMARC** | Что делать с письмами, не прошедшими проверку |

Настраиваются один раз в панели управления доменом. Займёмся в главе 58.

**Проверить себя** можно на `mail-tester.com`: отправляете туда письмо
и получаете оценку из десяти баллов со списком проблем.

## 💻 Письма магазина

```php
<?php
// includes/uvedomleniya.php
declare(strict_types=1);

function otpravit_uvedomleniya_o_zakaze(int $zakaz_id): void
{
    $zakaz = zapros_odin('SELECT * FROM zakazy WHERE id = ?', [$zakaz_id]);
    if ($zakaz === null) return;

    $pozicii = zapros('
        SELECT nazvanie, artikul, cena, kolichestvo
        FROM zakaz_tovary WHERE zakaz_id = ?
    ', [$zakaz_id]);

    // 1. Покупателю — если оставил почту
    $pochta = pochta_pokupatelya($zakaz);
    if ($pochta !== null) {
        otpravit_pismo(
            $pochta,
            'Заказ №' . $zakaz['nomer'] . ' принят',
            shablon_pisma('zakaz_prinyat', compact('zakaz', 'pozicii'))
        );
    }

    // 2. Магазину — всегда
    otpravit_pismo(
        SAIT_POCHTA,
        'Новый заказ №' . $zakaz['nomer'] . ' на ' . somoni((int) $zakaz['summa_itogo']),
        shablon_pisma('novyi_zakaz_adminu', compact('zakaz', 'pozicii'))
    );

    // 3. Быстрое сообщение менеджеру
    otpravit_v_telegram(sprintf(
        "🛒 Заказ №%s\n%s, %s\nСумма: %s сомони\nПозиций: %d",
        $zakaz['nomer'],
        $zakaz['klient_imya'],
        $zakaz['klient_telefon'],
        somoni((int) $zakaz['summa_itogo']),
        count($pozicii)
    ));
}

function pochta_pokupatelya(array $zakaz): ?string
{
    if (empty($zakaz['polzovatel_id'])) return null;

    $pochta = zapros_znachenie('SELECT email FROM polzovateli WHERE id = ?',
                               [$zakaz['polzovatel_id']]);
    return $pochta ?: null;
}
```

### **Шаблоны писем**

Письма не собирают склейкой строк — их выносят в отдельные файлы:

```php
function shablon_pisma(string $imya, array $dannye): string
{
    $put = __DIR__ . '/../pisma/' . $imya . '.php';

    if (!file_exists($put)) {
        throw new RuntimeException("Нет шаблона письма: $imya");
    }

    extract($dannye);         // $zakaz, $pozicii становятся переменными
    ob_start();               // начинаем перехват вывода
    require $put;
    return ob_get_clean();    // забираем перехваченное как строку
}
```

**`ob_start()` и `ob_get_clean()`** — буферизация вывода. Всё, что шаблон
выводит через `echo`, попадает не на экран, а в буфер, откуда мы забираем
это строкой.

Приём полезный: так же собирают HTML для сохранения в файл или для ответа API.

```php
<?php // pisma/zakaz_prinyat.php ?>
<!DOCTYPE html>
<html lang="ru">
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #222; line-height: 1.6;">

    <h2 style="color: #b5411f;">Заказ №<?= e($zakaz['nomer']) ?> принят</h2>

    <p>Здравствуйте, <?= e($zakaz['klient_imya']) ?>!</p>
    <p>Мы получили ваш заказ и свяжемся с вами в течение часа
       по номеру <?= e($zakaz['klient_telefon']) ?>.</p>

    <table cellpadding="8" cellspacing="0"
           style="border-collapse: collapse; width: 100%; max-width: 600px;">
        <tr style="background: #f4ede1;">
            <th align="left">Товар</th>
            <th align="right">Кол-во</th>
            <th align="right">Сумма</th>
        </tr>
        <?php foreach ($pozicii as $p): ?>
            <tr style="border-bottom: 1px solid #e3d8c6;">
                <td>
                    <?= e($p['nazvanie']) ?><br>
                    <small style="color: #7c7166;"><?= e($p['artikul']) ?></small>
                </td>
                <td align="right"><?= (int) $p['kolichestvo'] ?></td>
                <td align="right">
                    <?= somoni((int) $p['cena'] * (int) $p['kolichestvo']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <tr>
            <td colspan="2" align="right"><strong>Итого</strong></td>
            <td align="right">
                <strong><?= somoni((int) $zakaz['summa_itogo']) ?> сомони</strong>
            </td>
        </tr>
    </table>

    <p style="margin-top: 24px; color: #7c7166; font-size: 14px;">
        <?= e(SAIT_NAZVANIE) ?> · <?= e(SAIT_TELEFON) ?><br>
        Если вы не оформляли этот заказ, просто ответьте на это письмо.
    </p>

</body>
</html>
```

⚠️ **Вёрстка писем — отдельный мир, живущий по правилам 2005 года.**

Почтовые программы понимают очень ограниченный набор возможностей:

- **никакого внешнего CSS** — только стили прямо в атрибуте `style`;
- **никакого Flexbox и Grid** — раскладка таблицами;
- **никакого JavaScript** — вырезается;
- **ширина не больше 600 пикселей**;
- **картинки часто отключены** по умолчанию.

Да, это те самые приёмы, которые в главе 7 мы называли устаревшими. В письмах
они всё ещё единственный работающий способ.

## 📖 Telegram — самый практичный канал

Для магазина в Таджикистане сообщение в мессенджер полезнее письма: менеджер
видит заказ мгновенно, даже не открывая компьютер.

```php
function otpravit_v_telegram(string $tekst): bool
{
    if (!defined('TELEGRAM_TOKEN') || TELEGRAM_TOKEN === '') {
        return false;
    }

    $url = 'https://api.telegram.org/bot' . TELEGRAM_TOKEN . '/sendMessage';

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => [
            'chat_id' => TELEGRAM_CHAT_ID,
            'text'    => $tekst,
        ],
        CURLOPT_TIMEOUT        => 5,     // не ждём дольше пяти секунд
        CURLOPT_CONNECTTIMEOUT => 3,
    ]);

    $otvet = curl_exec($ch);
    $kod = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($kod !== 200) {
        error_log('Telegram ответил ' . $kod . ': ' . $otvet);
        return false;
    }

    return true;
}
```

⚠️ **`CURLOPT_TIMEOUT` обязателен.** Без него, если Telegram не отвечает,
PHP будет ждать минуту — и покупатель всё это время смотрит на крутящийся
индикатор после нажатия «Оформить заказ».

**Правило: у любого обращения к внешнему сервису должен быть таймаут.**
Подробно — в главе 50.

Как завести бота: написать `@BotFather` в Telegram, получить токен,
добавить бота в группу менеджеров, узнать `chat_id`. Занимает пять минут.

## 📖 Уведомления внутри сайта

Третий канал — сообщения в личном кабинете:

```sql
CREATE TABLE uvedomleniya (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    polzovatel_id INT NOT NULL,
    tip           VARCHAR(64) NOT NULL,
    zagolovok     VARCHAR(255) NOT NULL,
    tekst         TEXT,
    ssylka        VARCHAR(255) DEFAULT NULL,
    prochitano    TINYINT(1) NOT NULL DEFAULT 0,
    sozdano       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_polzovatel (polzovatel_id, prochitano),
    KEY idx_sozdano (sozdano)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```php
function uvedomit(int $polzovatel_id, string $tip, string $zagolovok,
                  string $tekst = '', ?string $ssylka = null): void
{
    vypolnit('
        INSERT INTO uvedomleniya (polzovatel_id, tip, zagolovok, tekst, ssylka)
        VALUES (?, ?, ?, ?, ?)
    ', [$polzovatel_id, $tip, $zagolovok, $tekst, $ssylka]);
}

function neprochitannyh(int $polzovatel_id): int
{
    return (int) zapros_znachenie('
        SELECT COUNT(*) FROM uvedomleniya
        WHERE polzovatel_id = ? AND prochitano = 0
    ', [$polzovatel_id]);
}
```

Такие уведомления не теряются: письмо может уйти в спам, сообщение —
не дойти, а запись в базе останется.

## 📖 Когда уведомлять

Список событий, о которых стоит сообщать:

| Событие | Кому | Канал |
|---|---|---|
| Новый заказ | Покупателю | Письмо |
| Новый заказ | Магазину | Письмо + Telegram |
| Статус изменился | Покупателю | Письмо + в кабинет |
| Заказ отменён | Покупателю | Письмо |
| Товар на модерации | Менеджеру | Telegram |
| Товар одобрен или отклонён | Продавцу | Письмо + в кабинет |
| Товар заканчивается | Магазину | Раз в день, сводкой |
| Восстановление пароля | Пользователю | Только письмо |

⚠️ **Не уведомляйте о каждом чихе.** Человек, получающий двадцать писем в день,
перестаёт их читать — и пропустит важное.

Особенно это касается вещей вроде «товар заканчивается»: не отдельное письмо
на каждый товар, а **одна сводка раз в день**.

## 📖 Очередь для тяжёлых отправок

Отправка письма занимает 1-3 секунды. При оформлении заказа это заметно,
но терпимо.

А если нужно разослать сотне покупателей? Прямо в обработчике это не сделать:
браузер отвалится по таймауту.

Решение — **очередь**: записываем задание в базу, отправляем отдельно.

```sql
CREATE TABLE ochered_pisem (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    komu       VARCHAR(255) NOT NULL,
    tema       VARCHAR(255) NOT NULL,
    telo       TEXT NOT NULL,
    popytok    INT NOT NULL DEFAULT 0,
    otpravleno TINYINT(1) NOT NULL DEFAULT 0,
    oshibka    TEXT,
    sozdano    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    KEY idx_ochered (otpravleno, popytok)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

```php
function postavit_v_ochered(string $komu, string $tema, string $telo): void
{
    vypolnit('INSERT INTO ochered_pisem (komu, tema, telo) VALUES (?, ?, ?)',
             [$komu, $tema, $telo]);
}
```

```php
<?php
// cron/otpravit_pisma.php — запускается раз в минуту

require_once __DIR__ . '/../includes/bootstrap.php';

$pisma = zapros('
    SELECT * FROM ochered_pisem
    WHERE otpravleno = 0 AND popytok < 3
    ORDER BY id
    LIMIT 20
');

foreach ($pisma as $p) {
    $ok = otpravit_pismo($p['komu'], $p['tema'], $p['telo']);

    vypolnit('
        UPDATE ochered_pisem
        SET otpravleno = ?, popytok = popytok + 1, oshibka = ?
        WHERE id = ?
    ', [$ok ? 1 : 0, $ok ? null : 'Не удалось отправить', $p['id']]);
}
```

Запускается по расписанию — **cron**:

```
* * * * * php /var/www/magazin/cron/otpravit_pisma.php
```

⚠️ Обратите внимание на `popytok < 3`. Если адрес неверный, письмо не уйдёт
никогда — и без ограничения очередь будет пытаться вечно, забивая логи.
**Три попытки и сдаёмся.**

Подробнее про cron — в главе 57.

## 🔤 Разбор по словам

| Запись | Что делает |
|---|---|
| `mail()` | Встроенная отправка. **Письма уходят в спам** |
| **SMTP** | Отправка через настоящий почтовый сервер |
| **SPF / DKIM / DMARC** | Записи домена, без которых письма не доходят |
| `ob_start()` / `ob_get_clean()` | Перехватить вывод в строку |
| `extract($dannye)` | Массив в отдельные переменные |
| `AltBody` | Текстовая версия письма |
| `CURLOPT_TIMEOUT` | **Обязательный** таймаут для внешних сервисов |
| **Очередь** | Задание в базу, отправка отдельным процессом |
| **cron** | Запуск по расписанию |

## ⚠️ Грабли

**Ронять заказ из-за письма.** Отправка — в отдельном `try/catch`, вне транзакции.

**Пользоваться `mail()`.** Письма в спаме, обратной связи нет.

**Не настроить SPF и DKIM.** Никакой код не спасёт от спам-фильтра.

**Внешний CSS в письме.** Не работает. Только `style` в атрибуте.

**Flexbox в письме.** Не поддерживается. Таблицы.

**Обращение к внешнему сервису без таймаута.** Покупатель ждёт минуту.

**Слать письмо на каждое мелкое событие.** Их перестанут читать.

**Очередь без ограничения попыток.** Вечные попытки на неверный адрес.

**Секреты SMTP в репозитории.** То же правило, что для базы.

## 🏋️ Задачи

**Задача 43.1.** Настройте отправку через SMTP. Для проб подойдёт Mailtrap —
он ловит письма, не отправляя их по-настоящему.

**Задача 43.2.** Сделайте письмо о принятом заказе с таблицей товаров.
Проверьте, как оно выглядит в почте.

**Задача 43.3.** Проверьте письмо на `mail-tester.com`. Сколько баллов?
Что советуют исправить?

**Задача 43.4.** Заведите бота в Telegram и отправьте себе сообщение о заказе.

**Задача 43.5.** Что произойдёт и почему?

```php
$pdo->beginTransaction();
vypolnit('INSERT INTO zakazy ...');
otpravit_pismo($email, 'Заказ', '...');
$pdo->commit();
```

Найдите две проблемы.

**Задача 43.6.** Сделайте уведомления в личном кабинете со счётчиком
непрочитанных.

**Задача 43.7.** Реализуйте очередь писем и скрипт для cron.

**Задача 43.8.** Сделайте сводку «товары заканчиваются» — одно письмо в день
со списком, а не письмо на каждый товар.

**Задача 43.9.** Уберите таймаут из функции Telegram и подставьте неверный
адрес API. Сколько секунд грузится страница оформления заказа? Верните таймаут.

*Ответы — в [Приложении Г](../prilozheniya/G-otvety.md).*

## 🔗 В бою

В этом репозитории уведомления устроены необычно, и причина поучительная.

Загляните в `includes/messaging.php` и `api/vin_order_request.php`. Когда
покупатель нажимает «Купить» у товара поставщика, **автозаказ не создаётся**.
Вместо этого создаётся **заявка менеджеру** в переписке внутри сайта.

Почему так: цена и наличие у поставщика меняются, а массовые автоматические
обращения к его API приводят к блокировке рабочего аккаунта. Это записано
в CLAUDE.md проекта как жёсткое правило.

Вывод шире, чем про уведомления: **иногда правильное решение — не автоматизировать**.
Человек в цепочке медленнее, но надёжнее, и в этом случае обходится дешевле,
чем потерянный доступ к ценам.

## 📌 Итог

- **Уведомление не должно ронять основное действие.** Отдельный `try/catch`,
  вне транзакции.
- `mail()` не использовать — **SMTP** через библиотеку.
- **SPF, DKIM, DMARC** обязательны, иначе письма в спаме.
- Вёрстка писем: **таблицы, стили в атрибуте, ширина 600**, без JavaScript.
- Шаблоны писем — отдельные файлы, собираются через `ob_start`.
- **Telegram практичнее письма** для менеджера: мгновенно и без компьютера.
- У любого внешнего сервиса — **таймаут**.
- Уведомления в базе не теряются, в отличие от писем.
- Массовые рассылки — через **очередь** и cron, с ограничением попыток.
- Не уведомляйте о каждом событии: важное потеряется среди мелочей.

**Часть VIII закончена.** У вас полноценный магазин: каталог, корзина, заказы,
аккаунты, админка, уведомления.

Дальше превращаем его в маркетплейс — с продавцами, модерацией и комиссиями.

[← Глава 42](42-adminka.md) · [Глава 44. Продавцы →](44-prodavcy.md)
