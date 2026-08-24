<?php
declare(strict_types=1);

function cena_pozicii(int $cena, int $kol): int
{
    return $cena * $kol;
}

function itogo(array $korzina): int
{
    $summa = 0;
    foreach ($korzina as $poziciya) {
        $summa += cena_pozicii($poziciya['cena'], $poziciya['kol'] ?? null);
    }
    return $summa;
}

echo itogo([
    ['cena' => 250, 'kol' => 2],
    ['cena' => 780],              // ← у этой позиции забыли количество
]);
