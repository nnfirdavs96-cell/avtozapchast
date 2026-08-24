<?php
declare(strict_types=1);
require __DIR__ . '/kesh.php';

// Изображаем медленный запрос к поставщику: 300 мс сети + разбор ответа.
function sprosit_postavshchika(string $artikul): array
{
    usleep(300_000);
    return ['artikul' => $artikul, 'nazvanie' => 'Фильтр масляный', 'cena_rub' => 4300];
}

function zamer(callable $chto): array
{
    $start = microtime(true);
    $rezultat = $chto();
    return [round((microtime(true) - $start) * 1000, 1), $rezultat];
}

array_map('unlink', glob(__DIR__ . '/kesh/*.json') ?: []);

echo "Три обращения к одному и тому же артикулу\n\n";

for ($i = 1; $i <= 3; $i++) {
    [$ms, $tovar] = zamer(fn() => kesh_pomni(
        'nazvanie:MANN:W71480',
        3600,
        fn() => sprosit_postavshchika('W71480')
    ));
    $otkuda = $i === 1 ? 'промах — пошли к поставщику' : 'попадание — взяли из кэша';
    printf("запрос %d: %7.1f мс   %s\n", $i, $ms, $otkuda);
}

echo "\nЧто кэшируем, а что нет\n\n";

[$ms_nazv] = zamer(fn() => kesh_pomni('nazvanie:MANN:W71480', 86400,
    fn() => sprosit_postavshchika('W71480')));
[$ms_cena] = zamer(fn() => sprosit_postavshchika('W71480')['cena_rub']);

printf("название (кэш 24 ч): %6.1f мс — меняется раз в год\n", $ms_nazv);
printf("цена     (без кэша): %6.1f мс — меняется каждый час\n", $ms_cena);
