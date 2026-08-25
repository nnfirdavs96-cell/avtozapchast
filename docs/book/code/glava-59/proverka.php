<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';   // ← одна строка вместо десятка require

use Magazin\Korzina;

$korzina = new Korzina();
$korzina->dobavit(1, 25000, 2);   // фильтр, 250.00 сомони
$korzina->dobavit(2, 78000);      // тормозные колодки

echo 'Итого: ', number_format($korzina->itogo() / 100, 2, '.', ' '), " сомони\n";
