# Приложение А. Шпаргалки

> **Справочник** · Приложение А

Всё, что приходится вспоминать чаще всего, — на одной странице каждое.
Это приложение не для чтения подряд, а чтобы открывать во время работы.

---

## HTML: теги, которые нужны всегда

### Скелет страницы

```html
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Заголовок вкладки</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <!-- содержимое -->
    <script src="/assets/app.js"></script>
</body>
</html>
```

### Структура и текст

| Тег | Что это | Когда |
|---|---|---|
| `<div>` | Блок без смысла | Обёртка для вёрстки |
| `<span>` | Кусок строки без смысла | Подкрасить часть текста |
| `<header>` `<footer>` | Шапка, подвал | Вместо `div` — понятнее |
| `<nav>` | Навигация | Меню |
| `<main>` | Основное содержимое | Одно на страницу |
| `<section>` | Раздел со своим заголовком | Блоки страницы |
| `<article>` | Самостоятельная единица | Карточка товара, новость |
| `<aside>` | Побочное | Боковая колонка, фильтры |
| `<h1>`…`<h6>` | Заголовки | `<h1>` — один на страницу |
| `<p>` | Абзац | Текст |
| `<ul>` `<ol>` `<li>` | Списки | Ненумерованный, нумерованный |
| `<strong>` `<em>` | Важно, с акцентом | Не для красоты — для смысла |
| `<br>` | Перенос строки | Только внутри текста, не для отступов |
| `<hr>` | Разделитель | Смена темы |

### Ссылки, картинки, таблицы

```html
<a href="/katalog.php">Каталог</a>
<a href="https://example.tj" target="_blank" rel="noopener">Внешняя</a>
<a href="tel:+992900000000">Позвонить</a>
<a href="mailto:zakaz@magazin.tj">Написать</a>

<img src="/img/filtr.jpg" alt="Фильтр масляный MANN" width="300" height="200" loading="lazy">

<table>
    <thead><tr><th>Товар</th><th>Цена</th></tr></thead>
    <tbody><tr><td>Фильтр</td><td>250</td></tr></tbody>
</table>
```

### Формы

```html
<form action="/zakaz.php" method="post">
    <label for="imya">Имя</label>
    <input type="text" id="imya" name="imya" required maxlength="60">

    <input type="email"    name="email"    required>
    <input type="tel"      name="telefon"  pattern="[0-9+ ]+">
    <input type="number"   name="kol"      min="1" max="99" value="1">
    <input type="password" name="parol"    minlength="8">
    <input type="hidden"   name="tovar_id" value="42">

    <select name="gorod">
        <option value="hudzhand">Худжанд</option>
        <option value="dushanbe" selected>Душанбе</option>
    </select>

    <textarea name="kommentariy" rows="4"></textarea>

    <label><input type="checkbox" name="soglasie" required> Согласен</label>
    <label><input type="radio" name="dostavka" value="kurer"> Курьер</label>

    <button type="submit">Оформить</button>
</form>
```

**Главное про формы:** `name` — под этим именем значение придёт на сервер.
Нет `name` — поле не отправится. Проверки в браузере (`required`, `min`) —
удобство, **не защита**: на сервере проверять всё заново.

---

## CSS: то, что применяется каждый день

### Как подключить и как выбрать

```css
p            { }   /* все абзацы           */
.tovar       { }   /* class="tovar"        */
#shapka      { }   /* id="shapka"          */
.tovar .cena { }   /* .cena внутри .tovar  */
.tovar > p   { }   /* только прямые дети   */
a:hover      { }   /* при наведении        */
input:focus  { }   /* в фокусе             */
.tovar:first-child { }
input[type="number"] { }
```

**Приоритет** (кто победит при конфликте): `style=""` в теге → `#id` →
`.class` → тег. `!important` перебивает всё — и потому применяется
в крайнем случае.

### Отступы и размеры

```css
.karta {
    padding: 1rem 1.5rem;      /* внутри рамки: сверху-снизу, слева-справа */
    margin: 0 auto 2rem;       /* снаружи: 0 сверху, авто по бокам, 2rem снизу */
    border: 1px solid #ddd;
    border-radius: 8px;
    box-sizing: border-box;    /* padding входит в width — так удобнее */
    width: 100%;
    max-width: 320px;
}
```

Единицы: `px` — точно; `rem` — от размера шрифта страницы (**для отступов
и шрифтов лучше всего**); `%` — от родителя; `vh`/`vw` — от экрана.

### Flexbox: строка или столбец

```css
.ryad {
    display: flex;
    gap: 1rem;                      /* расстояние между элементами */
    justify-content: space-between; /* вдоль оси: flex-start | center | space-around */
    align-items: center;            /* поперёк оси: stretch | flex-start | center */
    flex-wrap: wrap;                /* переносить, если не влезает */
}
.ryad > .rastyanut { flex: 1; }     /* занять всё свободное место */
```

### Grid: сетка

```css
.setka {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
    gap: 1.5rem;
}
```

Эта запись — готовый адаптивный каталог: колонки не уже 240px, сколько
влезет, столько и будет.

### Мобильные

```css
/* Сначала пишем для телефона, потом расширяем — mobile first */
.katalog { grid-template-columns: 1fr; }

@media (min-width: 640px)  { .katalog { grid-template-columns: repeat(2, 1fr); } }
@media (min-width: 1024px) { .katalog { grid-template-columns: repeat(4, 1fr); } }
```

### Переменные и частые свойства

```css
:root {
    --osnovnoy: #B5411F;
    --tekst: #221E1A;
}
.knopka { background: var(--osnovnoy); }

.element {
    color: #333;  background: #fff;
    font-family: 'Rubik', sans-serif;  font-size: 1rem;
    font-weight: 500;  line-height: 1.6;  text-align: center;
    display: none;        /* block | inline-block | flex | grid */
    position: relative;   /* absolute | fixed | sticky */
    overflow: hidden;     /* auto | scroll */
    opacity: .5;  cursor: pointer;
    transition: all .2s;  box-shadow: 0 2px 8px rgba(0,0,0,.1);
}
```

---

## JavaScript: браузерный минимум

### Переменные и типы

```js
const cena = 250;              // не меняется — по умолчанию используйте const
let kolichestvo = 2;           // меняется
const nazvanie = 'Фильтр';
const est = true;
const spisok = [1, 2, 3];
const tovar = { id: 1, cena: 250, nazvanie: 'Фильтр' };
```

### Условия и циклы

```js
if (kolichestvo > 0)      { /* ... */ }
else if (kolichestvo === 0) { /* ... */ }
else                      { /* ... */ }

const itog = est ? 'в наличии' : 'нет';       // короткое условие

for (const t of spisok) { console.log(t); }   // по значениям массива
spisok.forEach((t, i) => console.log(i, t));
while (uslovie) { /* ... */ }
```

`===` сравнивает **и значение, и тип** — используйте только его.
`==` приводит типы и преподносит сюрпризы (`'1' == 1` → `true`).

### Массивы

```js
spisok.push(4);                       // добавить в конец
spisok.length;                        // сколько элементов
spisok.map(n => n * 2);               // преобразовать каждый
spisok.filter(n => n > 1);            // оставить подходящие
spisok.find(n => n === 2);            // найти первый
spisok.reduce((s, n) => s + n, 0);    // свернуть в одно значение (сумма)
spisok.includes(2);                   // есть ли такой
```

### Работа со страницей

```js
document.querySelector('#itog');            // первый подходящий
document.querySelectorAll('.tovar');        // все подходящие

el.textContent = 'Текст';                   // безопасно: вставляет как текст
el.innerHTML   = '<b>Жирный</b>';           // ОПАСНО с чужими данными (XSS)
el.value;                                   // значение поля ввода
el.classList.add('aktiv');
el.classList.remove('aktiv');
el.classList.toggle('aktiv');
el.dataset.id;                              // из data-id="42"
el.setAttribute('disabled', '');
```

### События

```js
knopka.addEventListener('click', (e) => { /* ... */ });
forma.addEventListener('submit', (e) => { e.preventDefault(); /* ... */ });
pole.addEventListener('input', (e) => { /* при каждом символе */ });

// Делегирование: один обработчик на список вместо сотни на кнопки
spisok.addEventListener('click', (e) => {
    const knopka = e.target.closest('.kupit');
    if (!knopka) return;
    dobavit(knopka.dataset.id);
});
```

### Запрос к серверу

```js
const otvet = await fetch('/api/tovary.php?q=filtr');
if (!otvet.ok) throw new Error('Ошибка ' + otvet.status);
const dannye = await otvet.json();

await fetch('/api/korzina.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ tovar_id: 42, kol: 2 }),
});
```

### Хранилище в браузере

```js
localStorage.setItem('korzina', JSON.stringify(korzina));
const korzina = JSON.parse(localStorage.getItem('korzina') || '[]');
localStorage.removeItem('korzina');
```

---

## PHP: серверный минимум

### Основы

```php
<?php
declare(strict_types=1);

$cena = 250;                      // int
$nazvanie = 'Фильтр';             // string
$est = true;                      // bool
$spisok = [1, 2, 3];              // список
$tovar = ['id' => 1, 'cena' => 250];   // ассоциативный массив

echo "Цена: $cena сомони";        // двойные кавычки подставляют переменные
echo 'Цена: $cena';               // одинарные — печатают как есть
echo "Товар: {$tovar['cena']}";   // фигурные скобки для элементов массива
```

### Условия, циклы, функции

```php
if ($kol > 0)      { }
elseif ($kol === 0) { }
else                { }

$status = match(true) {
    $kol > 10 => 'много',
    $kol > 0  => 'есть',
    default   => 'нет',
};

foreach ($spisok as $znachenie) { }
foreach ($tovar as $klyuch => $znachenie) { }
for ($i = 0; $i < 10; $i++) { }

function itogo(array $korzina, float $skidka = 0): int
{
    $summa = 0;
    foreach ($korzina as $p) {
        $summa += $p['cena'] * $p['kol'];
    }
    return (int) round($summa * (1 - $skidka / 100));
}
```

### Массивы и строки

```php
count($spisok);                    // сколько элементов
$spisok[] = 4;                     // добавить
in_array(2, $spisok, true);        // есть ли значение
array_keys($tovar);                // ключи
array_map(fn($n) => $n * 2, $spisok);
array_filter($spisok, fn($n) => $n > 1);
array_sum($spisok);
implode(', ', $spisok);            // массив → строка
explode(',', '1,2,3');             // строка → массив

trim($s);  mb_strlen($s);  mb_strtolower($s);
str_replace('а', 'о', $s);
str_contains($s, 'фильтр');
number_format(1234.5, 2, '.', ' ');   // 1 234.50
sprintf('%05.2f', 3.1);               // 03.10
```

### Данные из браузера

```php
$_GET['stranica']        // из адресной строки: ?stranica=2
$_POST['imya']           // из формы method="post"
$_SESSION['user_id']     // сессия (после session_start())
$_FILES['foto']          // загруженный файл
$_SERVER['REQUEST_URI']  // адрес запроса

// Никогда не доверяем: всегда с проверкой и значением по умолчанию
$stranica = max(1, (int) ($_GET['stranica'] ?? 1));
$imya = trim($_POST['imya'] ?? '');
```

### База через PDO

```php
$pdo = new PDO('mysql:host=localhost;dbname=magazin;charset=utf8mb4', $user, $parol, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
]);

// Несколько строк
$stmt = $pdo->prepare('SELECT * FROM tovary WHERE kategoriya_id = ? AND cena < ?');
$stmt->execute([$kategoriya, $max]);
$tovary = $stmt->fetchAll();

// Одна строка
$stmt = $pdo->prepare('SELECT * FROM tovary WHERE id = ?');
$stmt->execute([$id]);
$tovar = $stmt->fetch();
if (!$tovar) { http_response_code(404); exit('Товар не найден'); }

// Вставка и полученный id
$stmt = $pdo->prepare('INSERT INTO zakazy (pokupatel_id, summa) VALUES (?, ?)');
$stmt->execute([$pokupatel, $summa]);
$zakaz_id = (int) $pdo->lastInsertId();

// Транзакция: либо всё, либо ничего
$pdo->beginTransaction();
try {
    /* несколько запросов */
    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    throw $e;
}
```

**Правило без исключений:** значения — только через `?` и `execute()`.
Склеивание строкой = SQL-инъекция.

### Безопасность

```php
echo htmlspecialchars($tekst, ENT_QUOTES, 'UTF-8');   // вывод чужого текста
$hash = password_hash($parol, PASSWORD_DEFAULT);      // хранение пароля
password_verify($vvedennyy, $hash);                   // проверка

// CSRF: токен в форму и проверка при приёме
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
if (!hash_equals($_SESSION['csrf'], $_POST['csrf'] ?? '')) { exit('Отказано'); }
```

### Сессии, заголовки, файлы

```php
session_start();
$_SESSION['user_id'] = $id;
session_destroy();

header('Location: /katalog.php', true, 302);  exit;
header('Content-Type: application/json; charset=utf-8');
http_response_code(404);

echo json_encode($dannye, JSON_UNESCAPED_UNICODE);
$dannye = json_decode($stroka, true);

file_get_contents($put);
file_put_contents($put, $tekst, FILE_APPEND | LOCK_EX);
```

---

## SQL: запросы, которые нужны

### Выборка

```sql
SELECT * FROM tovary;
SELECT nazvanie, cena FROM tovary;

SELECT * FROM tovary WHERE cena < 500;
SELECT * FROM tovary WHERE brend = 'MANN' AND cena BETWEEN 100 AND 500;
SELECT * FROM tovary WHERE nazvanie LIKE '%фильтр%';
SELECT * FROM tovary WHERE kategoriya_id IN (1, 2, 5);
SELECT * FROM tovary WHERE foto IS NULL;

SELECT * FROM tovary ORDER BY cena ASC;         -- DESC — по убыванию
SELECT * FROM tovary ORDER BY cena DESC LIMIT 10;
SELECT * FROM tovary LIMIT 20 OFFSET 40;        -- третья страница по 20
```

### Изменение

```sql
INSERT INTO tovary (nazvanie, cena, brend) VALUES ('Фильтр', 250, 'MANN');
UPDATE tovary SET cena = 260 WHERE id = 42;      -- WHERE обязателен!
DELETE FROM tovary WHERE id = 42;                -- WHERE обязателен!
```

Запомните намертво: `UPDATE`/`DELETE` **без `WHERE`** меняют **всю таблицу**.
Привычка: сначала пишем `WHERE`, потом всё остальное.

### Подсчёты и группировка

```sql
SELECT COUNT(*) FROM tovary;
SELECT AVG(cena), MIN(cena), MAX(cena), SUM(cena) FROM tovary;

SELECT brend, COUNT(*) AS skolko
FROM tovary
GROUP BY brend
HAVING COUNT(*) > 5
ORDER BY skolko DESC;
```

`WHERE` фильтрует **строки до** группировки, `HAVING` — **группы после**.

### Соединения

```sql
-- только совпавшие
SELECT z.id, p.imya FROM zakazy z
INNER JOIN pokupateli p ON p.id = z.pokupatel_id;

-- все слева, даже без пары справа
SELECT t.nazvanie, COUNT(o.id) AS otzyvov FROM tovary t
LEFT JOIN otzyvy o ON o.tovar_id = t.id
GROUP BY t.id, t.nazvanie;
```

### Таблицы и индексы

```sql
CREATE TABLE IF NOT EXISTS tovary (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    nazvanie      VARCHAR(255)   NOT NULL,
    cena_diram    INT            NOT NULL,          -- деньги — в целых!
    kategoriya_id INT            NOT NULL,
    opisanie      TEXT           NULL,
    sozdan        TIMESTAMP      DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_kategoriya (kategoriya_id),
    FOREIGN KEY (kategoriya_id) REFERENCES kategorii(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE INDEX idx_cena ON tovary (cena_diram);
SHOW INDEX FROM tovary;
EXPLAIN SELECT * FROM tovary WHERE kategoriya_id = 5;
```

Индекс нужен там, где столбец стоит в `WHERE`, `JOIN` или `ORDER BY`.
Индекс на всё подряд замедляет запись — ставьте по делу.

---

## Git: команды на каждый день

```bash
# Начало
git init
git clone https://github.com/user/repo.git

# Ежедневное
git status                     # что изменилось и где лежит
git diff                       # какие строки изменились
git add fayl.php               # положить в индекс
git add .                      # положить всё
git commit -m "Описание"
git log --oneline              # история
git log --oneline --graph --all

# Ветки
git branch                     # список
git switch -c korzina          # создать и перейти
git switch main                # перейти
git merge korzina              # влить
git branch -d korzina          # удалить влитую

# Сервер
git remote -v
git push -u origin korzina
git pull origin main
git fetch origin               # скачать, но не сливать

# Откат
git restore fayl.php           # вернуть файл (не коммитили)
git restore --staged fayl.php  # убрать из индекса
git revert abc1234             # обратный коммит (история цела)
```

### Что писать в `.gitignore`

```
config/db_credentials.php
vendor/
node_modules/
kesh/
logs/
*.log
zagruzki/
.DS_Store
.idea/
.vscode/
```

---

## Терминал: тот минимум, без которого никуда

```bash
pwd                     # где я сейчас
ls -la                  # что в папке, включая скрытое
cd papka                # войти в папку
cd ..                   # на уровень выше
mkdir -p a/b/c          # создать папки
cp fayl.php kopiya.php  # копировать
mv staroe.php novoe.php # переместить или переименовать
rm fayl.php             # удалить (без корзины!)
cat fayl.php            # показать файл
tail -f logs/site.log   # смотреть конец файла вживую
grep -rn "iskomoe" .    # искать по всем файлам
chmod 755 papka         # права

php -S localhost:8000   # запустить сайт локально
php -v                  # версия PHP
mysql -u root -p        # войти в базу
mysqldump -u root -p baza > backup.sql
```

---

## Коды ответа HTTP

| Код | Значит | Когда отдавать |
|---|---|---|
| **200** | Всё хорошо | Обычный ответ |
| **301** | Переехало навсегда | Редирект на HTTPS, смена адреса |
| **302** | Временно | После формы, чтобы F5 не отправлял повторно |
| **400** | Плохой запрос | Данные не прошли проверку |
| **401** | Не представились | Нужен вход |
| **403** | Нельзя | Вошёл, но прав нет |
| **404** | Не найдено | Нет такого товара или страницы |
| **422** | Данные не годятся | Форма заполнена неверно |
| **429** | Слишком часто | Превышен лимит запросов |
| **500** | Ошибка сервера | Упало у вас |
| **502 / 504** | Не дождались | Проблема выше по цепочке |

[← К оглавлению](../README.md) · [Приложение Б →](2-B-oshibki-novichka.md)
