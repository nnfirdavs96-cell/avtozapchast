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
 */
function saveUploadedImage(array $file, string $type, string &$error): string
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

    $filename = uniqid($type . '_', true) . '.' . $exts[$mime];
    if (!move_uploaded_file($file['tmp_name'], $dir . $filename)) {
        $error = 'Не удалось сохранить файл.'; return '';
    }
    return UPLOAD_URL . $type . '/' . $filename;
}
