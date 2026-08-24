<?php
declare(strict_types=1);

/**
 * Журнал событий. Одна строка — одно событие, всегда в одном формате:
 * время, уровень, сообщение, подробности в JSON.
 * Читается и глазами, и grep'ом — потому и формат один.
 */

const LOG_FAYL = __DIR__ . '/magazin.log';

function zapisat_v_log(string $uroven, string $soobshchenie, array $podrobnosti = []): void
{
    $stroka = sprintf(
        "%s  %-7s %s %s\n",
        date('Y-m-d H:i:s'),
        strtoupper($uroven),
        $soobshchenie,
        $podrobnosti ? json_encode($podrobnosti, JSON_UNESCAPED_UNICODE) : ''
    );
    file_put_contents(LOG_FAYL, $stroka, FILE_APPEND | LOCK_EX);
}

function log_info(string $s, array $p = []): void    { zapisat_v_log('info', $s, $p); }
function log_vnimanie(string $s, array $p = []): void { zapisat_v_log('warning', $s, $p); }
function log_oshibka(string $s, array $p = []): void  { zapisat_v_log('error', $s, $p); }
