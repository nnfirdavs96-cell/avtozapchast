<?php
// includes/korzina.php
declare(strict_types=1);

/** Что лежит в корзине: [id товара => количество]. */
function korzina_soderzhimoe(): array
{
    return $_SESSION['korzina'] ?? [];
}

/** Добавить. Возвращает текст ошибки или null, если всё хорошо. */
function korzina_dobavit(int $tovar_id, int $kolichestvo = 1): ?string
{
    if ($kolichestvo < 1) return 'Количество должно быть больше нуля';

    // Товар берём из базы — доверять пришедшему id нельзя
    $tovar = zapros_odin('
        SELECT id, nazvanie, ostatok
        FROM tovary
        WHERE id = ? AND aktivnyi = 1
    ', [$tovar_id]);

    if ($tovar === null) return 'Товар не найден или снят с продажи';

    $uzhe = korzina_soderzhimoe()[$tovar_id] ?? 0;
    $stanet = $uzhe + $kolichestvo;

    if ($stanet > (int) $tovar['ostatok']) {
        $ostalos = (int) $tovar['ostatok'];
        return $ostalos > 0
            ? "Доступно только $ostalos шт."
            : 'Товара нет в наличии';
    }

    $_SESSION['korzina'][$tovar_id] = $stanet;
    return null;
}

/** Изменить количество. Ноль — убрать позицию. */
function korzina_izmenit(int $tovar_id, int $kolichestvo): ?string
{
    if ($kolichestvo <= 0) {
        unset($_SESSION['korzina'][$tovar_id]);
        return null;
    }

    $ostatok = (int) zapros_znachenie('SELECT ostatok FROM tovary WHERE id = ?', [$tovar_id]);
    if ($kolichestvo > $ostatok) return "Доступно только $ostatok шт.";

    $_SESSION['korzina'][$tovar_id] = $kolichestvo;
    return null;
}

function korzina_ubrat(int $tovar_id): void
{
    unset($_SESSION['korzina'][$tovar_id]);
}

function korzina_ochistit(): void
{
    $_SESSION['korzina'] = [];
}

/**
 * Полные данные корзины: товары, суммы, предупреждения.
 * Цены и остатки берутся из базы прямо сейчас, а не из сессии.
 */
function korzina_podrobno(): array
{
    $soderzhimoe = korzina_soderzhimoe();

    if (empty($soderzhimoe)) {
        return ['pozicii' => [], 'summa' => 0, 'shtuk' => 0, 'preduprezhdeniya' => []];
    }

    // Один запрос на все товары — не N+1
    $metki = implode(',', array_fill(0, count($soderzhimoe), '?'));
    $tovary = zapros("
        SELECT id, nazvanie, artikul, cena, ostatok, aktivnyi
        FROM tovary WHERE id IN ($metki)
    ", array_keys($soderzhimoe));

    $po_id = array_column($tovary, null, 'id');

    $pozicii = [];
    $summa = 0;
    $shtuk = 0;
    $preduprezhdeniya = [];

    foreach ($soderzhimoe as $id => $kolichestvo) {
        $t = $po_id[$id] ?? null;

        // Товар исчез из каталога, пока лежал в корзине
        if ($t === null || (int) $t['aktivnyi'] !== 1) {
            unset($_SESSION['korzina'][$id]);
            $preduprezhdeniya[] = 'Один товар снят с продажи и убран из корзины';
            continue;
        }

        // Остаток уменьшился, пока товар лежал в корзине
        if ($kolichestvo > (int) $t['ostatok']) {
            $kolichestvo = (int) $t['ostatok'];
            if ($kolichestvo === 0) {
                unset($_SESSION['korzina'][$id]);
                $preduprezhdeniya[] = e($t['nazvanie']) . ' закончился и убран из корзины';
                continue;
            }
            $_SESSION['korzina'][$id] = $kolichestvo;
            $preduprezhdeniya[] = e($t['nazvanie']) . ": осталось $kolichestvo шт., количество уменьшено";
        }

        $stoimost = (int) $t['cena'] * $kolichestvo;
        $summa += $stoimost;
        $shtuk += $kolichestvo;

        $pozicii[] = [
            'id'          => (int) $t['id'],
            'nazvanie'    => $t['nazvanie'],
            'artikul'     => $t['artikul'],
            'cena'        => (int) $t['cena'],
            'kolichestvo' => $kolichestvo,
            'stoimost'    => $stoimost,
            'ostatok'     => (int) $t['ostatok'],
        ];
    }

    return [
        'pozicii'          => $pozicii,
        'summa'            => $summa,
        'shtuk'            => $shtuk,
        'preduprezhdeniya' => $preduprezhdeniya,
    ];
}

/** Сколько штук — для значка в шапке. */
function korzina_schetchik(): int
{
    return array_sum(korzina_soderzhimoe());
}
