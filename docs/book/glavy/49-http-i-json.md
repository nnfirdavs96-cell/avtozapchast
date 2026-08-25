# Глава 49. HTTP-запросы из PHP и JSON

> **Часть X. Внешний мир** · Глава 49 из 60
> [← Глава 48](48-buy-box.md) · [Глава 50 →](50-chuzhoy-api.md)

## 🎯 Зачем эта глава

До сих пор наш сайт был замкнут на себя: своя база, свои страницы, свои данные.

Настоящие проекты так не живут. Магазину нужно:

- узнать курс валют, чтобы пересчитать цену поставщика;
- спросить у поставщика наличие и цену;
- отправить сообщение в Telegram;
- принять уведомление об оплате от банка;
- отдать данные своему JavaScript.

Всё это — **обмен данными по HTTP**. Тот самый протокол из главы 2, только
теперь запрос отправляет не браузер, а ваш PHP.

## 📖 Кто кому клиент

Помните из главы 2: клиент просит, сервер отвечает. Роли меняются
в зависимости от ситуации.

```
Покупатель → браузер → ВАШ САЙТ          сайт — сервер
                          ↓
                    AutoEuro API          сайт — клиент
```

Один и тот же PHP-скрипт может быть сервером для покупателя и клиентом
для поставщика. В этом нет противоречия — это просто разные роли в разных
разговорах.

## 📖 JSON — язык обмена

**JSON** (*JavaScript Object Notation*) — формат, на котором сегодня
разговаривают почти все API.

```json
{
    "artikul": "0986424815",
    "nazvanie": "Тормозные колодки Bosch",
    "cena": 250.00,
    "v_nalichii": true,
    "sklady": [
        {"gorod": "Москва", "ostatok": 12, "srok": 3},
        {"gorod": "Худжанд", "ostatok": 2, "srok": 0}
    ],
    "foto": null
}
```

Узнаёте? Это почти объект из JavaScript (глава 15) и почти ассоциативный
массив из PHP (глава 22). Тот же принцип «ключ: значение».

### **Правила JSON, о которые спотыкаются**

| Правило | Пример |
|---|---|
| Ключи **в двойных кавычках** | `"cena"`, не `'cena'` и не `cena` |
| Строки **только в двойных** | `"текст"` |
| Числа без кавычек | `250`, а не `"250"` |
| Логические — `true` / `false` | Строчными буквами |
| Пусто — `null` | Не `NULL`, не `nil` |
| **Запятой после последнего элемента нет** | Частая ошибка |
| Комментариев **не бывает** | Вообще |

### **JSON в PHP**

```php
// Массив → строка JSON
$json = json_encode($massiv, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

// Строка JSON → массив
$massiv = json_decode($json, true);
```

⚠️ **Два флага, без которых будет неудобно:**

**`JSON_UNESCAPED_UNICODE`** — без него русский текст превратится
в `Торм...`. Формально верно, читать невозможно.

**`true` вторым аргументом `json_decode`** — без него получите объект
класса `stdClass` вместо массива, и обращаться придётся через `->`
вместо `['...']`.

### **Проверка ошибок — обязательна**

```php
$dannye = json_decode($otvet, true);

if ($dannye === null && json_last_error() !== JSON_ERROR_NONE) {
    throw new RuntimeException('Сервер вернул не JSON: ' . json_last_error_msg());
}
```

⚠️ **`json_decode` возвращает `null` и при ошибке, и при валидном `null`
в ответе.** Различить можно только через `json_last_error()`.

Без этой проверки сломанный ответ превратится в загадочные ошибки
где-то дальше по коду.

В PHP 7.3+ можно проще:

```php
try {
    $dannye = json_decode($otvet, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    throw new RuntimeException('Сервер вернул не JSON: ' . $e->getMessage());
}
```

## 📖 Отправляем запрос: cURL

В PHP есть простой способ и правильный.

### **Простой — и почему он не годится**

```php
$otvet = file_get_contents('https://api.example.com/kurs');
```

Работает, но:

- **нет таймаута** — сервер не отвечает, скрипт висит;
- **нет кода ответа** — не отличить успех от ошибки;
- нельзя отправить POST с заголовками;
- может быть отключено в настройках хостинга.

### **Правильный: cURL**

```php
<?php
// includes/http.php
declare(strict_types=1);

/**
 * HTTP-запрос к внешнему сервису.
 *
 * Возвращает ['kod' => int, 'telo' => string].
 * Бросает RuntimeException при сетевой ошибке.
 */
function http_zapros(string $url, array $nastroyki = []): array
{
    $metod     = strtoupper($nastroyki['metod'] ?? 'GET');
    $dannye    = $nastroyki['dannye'] ?? null;
    $zagolovki = $nastroyki['zagolovki'] ?? [];
    $tajmaut   = $nastroyki['tajmaut'] ?? 10;

    $ch = curl_init();

    $opcii = [
        CURLOPT_URL            => $url,
        CURLOPT_RETURNTRANSFER => true,   // вернуть ответ, а не печатать
        CURLOPT_TIMEOUT        => $tajmaut,
        CURLOPT_CONNECTTIMEOUT => min(5, $tajmaut),
        CURLOPT_FOLLOWLOCATION => true,   // идти за перенаправлениями
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_SSL_VERIFYPEER => true,   // проверять сертификат
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_USERAGENT      => SAIT_NAZVANIE . ' bot',
    ];

    if ($metod === 'POST') {
        $opcii[CURLOPT_POST] = true;
        $opcii[CURLOPT_POSTFIELDS] = is_array($dannye)
            ? http_build_query($dannye)
            : (string) $dannye;
    } elseif ($metod !== 'GET') {
        $opcii[CURLOPT_CUSTOMREQUEST] = $metod;
        if ($dannye !== null) {
            $opcii[CURLOPT_POSTFIELDS] = is_array($dannye)
                ? http_build_query($dannye)
                : (string) $dannye;
        }
    }

    if ($zagolovki) {
        $spisok = [];
        foreach ($zagolovki as $imya => $znachenie) {
            $spisok[] = $imya . ': ' . $znachenie;
        }
        $opcii[CURLOPT_HTTPHEADER] = $spisok;
    }

    curl_setopt_array($ch, $opcii);

    $telo = curl_exec($ch);
    $kod  = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $oshibka = curl_error($ch);
    $vremya = curl_getinfo($ch, CURLINFO_TOTAL_TIME);

    curl_close($ch);

    if ($telo === false) {
        throw new RuntimeException('Сетевая ошибка: ' . $oshibka);
    }

    // Медленные запросы стоит замечать до того, как они станут проблемой
    if ($vremya > 3) {
        error_log(sprintf('Медленный запрос (%.1f с): %s', $vremya, $url));
    }

    return ['kod' => $kod, 'telo' => (string) $telo];
}

/** То же, но сразу разбирает JSON. */
function http_json(string $url, array $nastroyki = []): array
{
    $otvet = http_zapros($url, $nastroyki);

    if ($otvet['kod'] < 200 || $otvet['kod'] >= 300) {
        throw new RuntimeException(
            'Сервис ответил ' . $otvet['kod'] . ': ' . mb_substr($otvet['telo'], 0, 200)
        );
    }

    try {
        return json_decode($otvet['telo'], true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $e) {
        throw new RuntimeException('Ответ не является JSON: ' . $e->getMessage());
    }
}
```

### **Разбор важных настроек**

| Настройка | Зачем |
|---|---|
| `CURLOPT_RETURNTRANSFER` | Без неё ответ **печатается на страницу** вместо возврата |
| `CURLOPT_TIMEOUT` | **Обязательно.** Иначе скрипт висит, пока не отвалится браузер |
| `CURLOPT_CONNECTTIMEOUT` | Отдельный таймаут на **установку связи** |
| `CURLOPT_SSL_VERIFYPEER` | Проверка сертификата. **Не отключайте** |
| `CURLOPT_FOLLOWLOCATION` | Идти за перенаправлениями |
| `CURLOPT_USERAGENT` | Представиться. Некоторые API без этого отказывают |

⚠️ **Про `SSL_VERIFYPEER => false`.**

В интернете часто советуют отключить проверку сертификата, если запрос
не работает. **Не делайте этого.** Проверка сертификата — то, что отличает
`https` от `http`: без неё любой в сети между вами и сервером может
подменить ответ.

Если сертификат не проходит проверку — проблема в устаревшем списке
корневых сертификатов на сервере. Лечится обновлением, а не отключением защиты.

## 💻 Пример: курс валют

```php
<?php
// includes/kurs.php
declare(strict_types=1);

/**
 * Курс рубля к сомони. Результат кэшируется на час:
 * курс меняется раз в день, дёргать сервис на каждый показ товара незачем.
 */
function kurs_rublya(): float
{
    $kesh = __DIR__ . '/../storage/cache/kurs.json';
    $zhizn = 3600;

    // 1. Свежий кэш — берём из него
    if (file_exists($kesh) && (time() - filemtime($kesh)) < $zhizn) {
        $dannye = json_decode((string) file_get_contents($kesh), true);
        if (isset($dannye['kurs'])) {
            return (float) $dannye['kurs'];
        }
    }

    // 2. Спрашиваем сервис
    try {
        $otvet = http_json('https://api.exchangerate.host/latest?base=RUB&symbols=TJS',
                           ['tajmaut' => 5]);

        $kurs = (float) ($otvet['rates']['TJS'] ?? 0);

        if ($kurs <= 0) {
            throw new RuntimeException('Сервис вернул некорректный курс');
        }

        if (!is_dir(dirname($kesh))) {
            mkdir(dirname($kesh), 0775, true);
        }
        file_put_contents($kesh, json_encode([
            'kurs'     => $kurs,
            'obnovlen' => date('c'),
        ]));

        return $kurs;

    } catch (Throwable $e) {
        error_log('Не удалось получить курс: ' . $e->getMessage());

        // 3. Сервис недоступен — берём последний известный курс,
        //    даже если он протух. Лучше вчерашний курс, чем сломанный сайт.
        if (file_exists($kesh)) {
            $dannye = json_decode((string) file_get_contents($kesh), true);
            if (isset($dannye['kurs'])) {
                return (float) $dannye['kurs'];
            }
        }

        // 4. Совсем ничего нет — запасное значение из настроек
        return (float) KURS_ZAPASNOY;
    }
}
```

### **Три уровня надёжности**

Обратите внимание на структуру функции. Это **типовой приём работы
с внешними сервисами**:

1. **Свежий кэш** — быстро, без запроса;
2. **Живой запрос** — если кэш устарел;
3. **Протухший кэш** — если сервис недоступен;
4. **Запасное значение** — если нет вообще ничего.

**Сайт должен работать, даже когда чужой сервис лежит.** Курс вчерашний —
неприятно, но терпимо. Белый экран с ошибкой — недопустимо.

Запомните эту лестницу: она применима почти к любому внешнему источнику.

## 📖 Отдаём JSON: свой API

Обратная сторона — когда данные просят у вас. В главе 35 мы делали
подсказки поиска, теперь оформим это правильно.

```php
<?php
// api/tovary.php
declare(strict_types=1);
require_once __DIR__ . '/../includes/bootstrap.php';

/** Единый формат ответа: и для успеха, и для ошибки. */
function otvet_json(array $dannye, int $kod = 200): never
{
    http_response_code($kod);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($dannye, JSON_UNESCAPED_UNICODE);
    exit;
}

function oshibka_json(string $soobshenie, int $kod = 400): never
{
    otvet_json(['ok' => false, 'oshibka' => $soobshenie], $kod);
}

// --- Проверки ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    oshibka_json('Метод не разрешён', 405);
}

$zapros = trim($_GET['q'] ?? '');
if (mb_strlen($zapros) < 2) {
    otvet_json(['ok' => true, 'tovary' => []]);
}

$limit = min(50, max(1, (int) ($_GET['limit'] ?? 20)));

// --- Данные ---
$tovary = zapros('
    SELECT id, nazvanie, artikul, brend, cena, ostatok
    FROM tovary
    WHERE aktivnyi = 1 AND status = "opublikovan"
      AND (nazvanie LIKE ? OR artikul LIKE ?)
    ORDER BY ostatok DESC
    LIMIT ' . $limit,
    ['%' . $zapros . '%', '%' . $zapros . '%']
);

// Приводим типы: из базы всё приходит строками,
// а JSON должен отдавать числа числами
$tovary = array_map(fn($t) => [
    'id'         => (int) $t['id'],
    'nazvanie'   => $t['nazvanie'],
    'artikul'    => $t['artikul'],
    'brend'      => $t['brend'],
    'cena'       => (int) $t['cena'],
    'cena_text'  => somoni((int) $t['cena']) . ' сомони',
    'ostatok'    => (int) $t['ostatok'],
    'v_nalichii' => (int) $t['ostatok'] > 0,
], $tovary);

otvet_json([
    'ok'     => true,
    'vsego'  => count($tovary),
    'tovary' => $tovary,
]);
```

### **Правила своего API**

**Единый формат ответа.** Всегда `ok`, дальше данные или `oshibka`.
Тому, кто пишет клиент, не придётся гадать.

**Правильные коды ответа.** 200 — успех, 400 — плохой запрос, 401 — не вошёл,
403 — нельзя, 404 — нет, 405 — не тот метод, 500 — сломалось у нас.
Помните главу 2.

**Приводите типы.** Из базы `cena` приходит строкой `"25000"`. В JSON
это должно быть число `25000`, иначе клиенту придётся преобразовывать.

**Отдавайте и готовый текст.** `cena_text` избавляет клиента от форматирования
и гарантирует, что цена везде выглядит одинаково.

**Ограничивайте `limit` сверху.** Иначе `?limit=999999` положит сервер.

## 📖 Приём уведомлений: вебхуки

Есть и третий случай — когда **чужой сервис приходит к вам**. Так работают
уведомления об оплате, доставке, изменении статуса.

Это называется **вебхук** — «обратный вызов».

```php
<?php
// api/webhook/oplata.php
require_once __DIR__ . '/../../includes/bootstrap.php';

// 1. Только POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

// 2. Сырое тело: вебхуки обычно шлют JSON, а не форму
$telo = file_get_contents('php://input');

// 3. ПРОВЕРКА ПОДПИСИ — самое важное
$podpis = $_SERVER['HTTP_X_SIGNATURE'] ?? '';
$nasha_podpis = hash_hmac('sha256', $telo, WEBHOOK_SEKRET);

if (!hash_equals($nasha_podpis, $podpis)) {
    error_log('Вебхук с неверной подписью с ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
    http_response_code(401);
    exit;
}

// 4. Разбираем
try {
    $dannye = json_decode($telo, true, 512, JSON_THROW_ON_ERROR);
} catch (JsonException $e) {
    http_response_code(400);
    exit;
}

// 5. Идемпотентность: одно и то же уведомление могут прислать дважды
$vneshniy_id = $dannye['event_id'] ?? '';
if ($vneshniy_id === '') {
    http_response_code(400);
    exit;
}

$uzhe = zapros_znachenie('SELECT id FROM webhook_sobytiya WHERE vneshniy_id = ?',
                         [$vneshniy_id]);
if ($uzhe) {
    http_response_code(200);   // уже обработали, отвечаем успехом
    exit;
}

// 6. Обрабатываем
db()->beginTransaction();
try {
    vypolnit('INSERT INTO webhook_sobytiya (vneshniy_id, telo) VALUES (?, ?)',
             [$vneshniy_id, $telo]);

    if (($dannye['status'] ?? '') === 'paid') {
        vypolnit('UPDATE zakazy SET status = "podtverzhden" WHERE nomer = ?',
                 [$dannye['order_id'] ?? '']);
    }

    db()->commit();
} catch (Throwable $e) {
    db()->rollBack();
    error_log('Ошибка обработки вебхука: ' . $e->getMessage());
    http_response_code(500);   // пусть пришлют ещё раз
    exit;
}

http_response_code(200);
```

⚠️ **Три правила вебхуков, каждое обязательно:**

**Проверяйте подпись.** Адрес вебхука открыт всему интернету. Без подписи
любой сможет отправить «заказ оплачен» и получить товар бесплатно.

**`hash_equals`, а не `===`.** Помните главу 36: сравнение секретов —
за постоянное время.

**Идемпотентность.** Сервисы повторяют уведомления при неполучении ответа.
Без проверки одна оплата обработается трижды.

И четвёртое: **отвечайте быстро**. Тяжёлую работу откладывайте в очередь
(глава 43) — многие сервисы считают ответ дольше 5 секунд неудачей
и присылают повтор.

## 🔤 Разбор по словам

| Запись | Что делает |
|---|---|
| **JSON** | Формат обмена данными: `{"ключ": значение}` |
| `json_encode(..., JSON_UNESCAPED_UNICODE)` | Массив → JSON с читаемой кириллицей |
| `json_decode($s, true)` | JSON → **массив** (без `true` будет объект) |
| `JSON_THROW_ON_ERROR` | Бросить исключение вместо тихого `null` |
| **cURL** | Библиотека для HTTP-запросов из PHP |
| `CURLOPT_RETURNTRANSFER` | Вернуть ответ, а не напечатать |
| `CURLOPT_TIMEOUT` | **Обязательный** таймаут |
| `CURLOPT_SSL_VERIFYPEER` | Проверка сертификата. **Не отключать** |
| `php://input` | Сырое тело запроса |
| **Вебхук** | Чужой сервис приходит к вам с уведомлением |
| `hash_hmac('sha256', ...)` | Подпись для проверки подлинности |
| `never` в типе возврата | Функция не возвращает управление (PHP 8.1+) |

## ⚠️ Грабли

**Запрос без таймаута.** Чужой сервис завис — завис и ваш сайт.

**`SSL_VERIFYPEER => false`.** Открывает возможность подмены ответа.

**Не проверять код ответа.** 500 от сервиса разберётся как пустые данные.

**`json_decode` без проверки ошибок.** Сломанный ответ даст загадочный `null`.

**Забыть `true` в `json_decode`.** Получите объект вместо массива.

**Забыть `JSON_UNESCAPED_UNICODE`.** Русский текст в виде `Т...`.

**Не кэшировать редко меняющееся.** Курс валют не нужно спрашивать
на каждый показ товара.

**Падать, когда чужой сервис недоступен.** Лестница: кэш → запрос →
протухший кэш → запасное значение.

**Вебхук без проверки подписи.** Любой сможет отметить заказ оплаченным.

**Вебхук без идемпотентности.** Одна оплата обработается несколько раз.

## 🏋️ Задачи

**Задача 49.1.** Напишите `http_zapros` и `http_json` из главы.

**Задача 49.2.** Получите курс валют с бесплатного сервиса и выведите
на страницу.

**Задача 49.3.** Реализуйте кэш курса на час. Проверьте: второй вызов
не должен идти в сеть.

**Задача 49.4.** Проверьте отказоустойчивость: подставьте неверный адрес
сервиса. Сайт работает? Какой курс показался?

**Задача 49.5.** Уберите таймаут и обратитесь к заведомо медленному адресу.
Сколько грузится страница? Верните таймаут.

**Задача 49.6.** Сделайте свой API для поиска товаров с единым форматом
ответа и правильными кодами.

**Задача 49.7.** Проверьте свой API через `curl`:

```bash
curl -i "http://localhost:8000/api/tovary.php?q=фильтр"
curl -i -X POST "http://localhost:8000/api/tovary.php"
curl -i "http://localhost:8000/api/tovary.php?q=a&limit=999999"
```

Все три случая обработаны правильно?

**Задача 49.8.** Найдите ошибки:

```php
$otvet = file_get_contents('https://api.example.com/data');
$dannye = json_decode($otvet);
foreach ($dannye['items'] as $i) {
    echo $i['name'];
}
```

Их здесь четыре.

**Задача 49.9.** Напишите приёмник вебхука с проверкой подписи
и идемпотентностью. Проверьте: отправьте одно уведомление дважды.

*Ответы — в [Приложении Г](../prilozheniya/4-G-otvety.md).*

## 🔗 В бою

В этом репозитории обращения к внешним сервисам собраны в `includes/catalog/`
и `includes/parts/`.

Загляните в `AutoEuroPriceProvider.php` — там запрос к API поставщика
с таймаутом, обработкой ошибок и запасным поведением, если сервис не ответил.

Обратите внимание на важную деталь: цена от поставщика приходит **в рублях**
и пересчитывается в сомони по курсу с наценкой и надбавкой за доставку
Москва—Худжанд. Одно обращение к API превращается в цепочку расчётов —
и каждый шаг должен быть устойчив к сбою.

## 📌 Итог

- Ваш сайт бывает и сервером, и **клиентом** для чужих сервисов.
- **JSON** — формат обмена. Двойные кавычки, без запятой в конце,
  без комментариев.
- `json_decode($s, **true**)` — иначе объект вместо массива.
- **`JSON_UNESCAPED_UNICODE`** для читаемой кириллицы.
- Проверяйте ошибки разбора: `JSON_THROW_ON_ERROR`.
- **cURL** вместо `file_get_contents`: таймаут, коды, заголовки.
- **`CURLOPT_TIMEOUT` обязателен.** `SSL_VERIFYPEER` не отключать.
- Лестница надёжности: **кэш → запрос → протухший кэш → запасное значение**.
- Свой API: единый формат, правильные коды, приведение типов,
  ограничение `limit`.
- **Вебхук**: подпись через `hash_equals`, идемпотентность, быстрый ответ.

Дальше — как работать с чужим API, не получив бан.

[← Глава 48](48-buy-box.md) · [Глава 50. Чужой API →](50-chuzhoy-api.md)
