<?php
/**
 * Приём загруженных картинок (единая точка для форм вне api/upload.php).
 *
 * api/upload.php работает только для залогиненных через JS. А логотип магазина
 * нужен и на форме регистрации (пользователь ещё не вошёл), и в кабинете —
 * поэтому общая серверная функция: проверяет тип/размер и кладёт файл в
 * assets/uploads/<type>/, возвращая относительный URL (как хранятся картинки
 * товаров). Пустая строка + текст в $error — если что-то не так.
 */

/**
 * Сохранить загруженный файл-картинку. Возвращает URL (UPLOAD_URL/...) или ''.
 * $file — элемент из $_FILES; $type — подпапка (sellers, products, ...).
 * $opts['square'] = N → вписать в квадрат N×N на белом фоне (пропорции
 * сохраняются, картинка не растягивается и не увеличивается сверх оригинала),
 * сохранить как PNG. Нужно для логотипов магазина.
 */
function saveUploadedImage(array $file, string $type, string &$error, array $opts = []): string
{
    $error = '';
    $err   = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) { $error = 'Файл не выбран.'; return ''; }
    if ($err !== UPLOAD_ERR_OK) {
        $error = ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE)
            ? 'Файл слишком большой.' : 'Ошибка загрузки файла.';
        return '';
    }
    if (($file['size'] ?? 0) > 5 * 1024 * 1024) { $error = 'Файл больше 5 МБ.'; return ''; }
    if (empty($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        $error = 'Некорректный файл.'; return '';
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = $finfo ? finfo_file($finfo, $file['tmp_name']) : '';
    if ($finfo) finfo_close($finfo);

    $exts = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    if (!isset($exts[$mime])) { $error = 'Допустимые форматы: JPG, PNG, WEBP, GIF.'; return ''; }

    $type = preg_replace('/[^a-z0-9_]/', '', strtolower($type)) ?: 'misc';
    $dir  = APP_ROOT . '/assets/uploads/' . $type . '/';
    if (!is_dir($dir) && !@mkdir($dir, 0755, true) && !is_dir($dir)) {
        $error = 'Не удалось создать папку для загрузки.'; return '';
    }

    // Квадратный ресайз (логотип): вписываем по центру на белый фон, сохраняем PNG.
    $square = isset($opts['square']) ? (int)$opts['square'] : 0;
    if ($square > 0 && function_exists('imagecreatetruecolor')) {
        $filename = uniqid($type . '_', true) . '.png';
        if (imageSaveSquarePng($file['tmp_name'], $mime, $dir . $filename, $square)) {
            return UPLOAD_URL . $type . '/' . $filename;
        }
        // GD не справился (редкий/битый файл) — сохраняем как есть, ниже.
    }

    $filename = uniqid($type . '_', true) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        $error = 'Не удалось сохранить файл.'; return '';
    }
    return UPLOAD_URL . $type . '/' . $filename;
}

/**
 * Вписать картинку в квадрат $size×$size на белом фоне (пропорции сохраняются,
 * вверх не масштабируем) и сохранить PNG в $dest. Возвращает true при успехе.
 */
function imageSaveSquarePng(string $src, string $mime, string $dest, int $size): bool
{
    $load = [
        'image/jpeg' => 'imagecreatefromjpeg', 'image/png' => 'imagecreatefrompng',
        'image/webp' => 'imagecreatefromwebp', 'image/gif' => 'imagecreatefromgif',
    ];
    if (!isset($load[$mime]) || !function_exists($load[$mime])) return false;

    $srcImg = @$load[$mime]($src);
    if (!$srcImg) return false;

    $w = imagesx($srcImg); $h = imagesy($srcImg);
    if ($w < 1 || $h < 1) { imagedestroy($srcImg); return false; }

    $scale = min($size / $w, $size / $h, 1);   // только уменьшаем
    $nw = max(1, (int)round($w * $scale));
    $nh = max(1, (int)round($h * $scale));

    $canvas = imagecreatetruecolor($size, $size);
    $white  = imagecolorallocate($canvas, 255, 255, 255);
    imagefilledrectangle($canvas, 0, 0, $size, $size, $white);

    $dx = (int)(($size - $nw) / 2);
    $dy = (int)(($size - $nh) / 2);
    imagecopyresampled($canvas, $srcImg, $dx, $dy, 0, 0, $nw, $nh, $w, $h);

    $ok = imagepng($canvas, $dest);
    imagedestroy($srcImg);
    imagedestroy($canvas);
    return (bool)$ok;
}
