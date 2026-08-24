<?php
declare(strict_types=1);

/**
 * Простейший файловый кэш. Ключ -> файл, внутри JSON и срок годности.
 * Никаких библиотек: чтобы было видно, что кэш — это просто «запиши и запомни до».
 */

const KESH_PAPKA = __DIR__ . '/kesh';

function kesh_put(string $klyuch, mixed $znachenie, int $sekund): void
{
    if (!is_dir(KESH_PAPKA)) {
        mkdir(KESH_PAPKA, 0777, true);
    }
    $dannye = ['do' => time() + $sekund, 'znachenie' => $znachenie];
    file_put_contents(kesh_put_k($klyuch), json_encode($dannye, JSON_UNESCAPED_UNICODE));
}

function kesh_get(string $klyuch): mixed
{
    $fayl = kesh_put_k($klyuch);
    if (!is_file($fayl)) {
        return null;                     // промах: такого ключа нет
    }
    $dannye = json_decode((string) file_get_contents($fayl), true);
    if (!is_array($dannye) || $dannye['do'] < time()) {
        @unlink($fayl);                  // протухло — выкидываем
        return null;
    }
    return $dannye['znachenie'];
}

/** Имя файла считаем от ключа: hash защищает от кириллицы, слэшей и длины. */
function kesh_put_k(string $klyuch): string
{
    return KESH_PAPKA . '/' . hash('sha256', $klyuch) . '.json';
}

/** Достать из кэша, а если нет — посчитать и положить. Главная функция кэша. */
function kesh_pomni(string $klyuch, int $sekund, callable $kak_schitat): mixed
{
    $iz_kesha = kesh_get($klyuch);
    if ($iz_kesha !== null) {
        return $iz_kesha;
    }
    $znachenie = $kak_schitat();
    kesh_put($klyuch, $znachenie, $sekund);
    return $znachenie;
}
