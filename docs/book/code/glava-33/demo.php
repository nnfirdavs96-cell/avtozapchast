<?php
/**
 * Демонстрация SQL-инъекции: один и тот же ввод против двух видов запроса.
 * Запускается на копии учебной базы, ничего боевого не трогает.
 */
$pdo = new PDO('sqlite:' . __DIR__ . '/../glava-26/magazin.sqlite', null, null, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

// То, что вводит злоумышленник в поле поиска
$vvod = "' OR '1'='1";

echo "Введено в поле: $vvod\n\n";

// --- Способ 1: склейка строк (так делать нельзя) ---
$sql = "SELECT id, nazvanie, cena FROM tovary WHERE artikul = '$vvod'";
echo "УЯЗВИМЫЙ ЗАПРОС:\n$sql\n";
$stroki = $pdo->query($sql)->fetchAll();
echo "Вернулось строк: " . count($stroki) . "  <-- утёк весь каталог\n\n";

// --- Способ 2: подготовленный запрос ---
$st = $pdo->prepare('SELECT id, nazvanie, cena FROM tovary WHERE artikul = ?');
$st->execute([$vvod]);
$stroki2 = $st->fetchAll();
echo "ПОДГОТОВЛЕННЫЙ ЗАПРОС:\nSELECT ... WHERE artikul = ?   параметр: $vvod\n";
echo "Вернулось строк: " . count($stroki2) . "  <-- база искала артикул с таким названием\n";
