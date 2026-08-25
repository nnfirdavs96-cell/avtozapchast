<?php
declare(strict_types=1);

namespace Magazin;

final class Korzina
{
    /** @var array<int, array{cena:int, kol:int}> */
    private array $pozicii = [];

    public function dobavit(int $tovar_id, int $cena, int $kol = 1): void
    {
        $this->pozicii[$tovar_id]['cena'] = $cena;
        $this->pozicii[$tovar_id]['kol']  = ($this->pozicii[$tovar_id]['kol'] ?? 0) + $kol;
    }

    public function itogo(): int
    {
        $summa = 0;
        foreach ($this->pozicii as $p) {
            $summa += $p['cena'] * $p['kol'];
        }
        return $summa;
    }
}
