<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($zagolovok ?? SAIT_NAZVANIE) ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>
<div class="container">
    <header>
        <div>
            <div class="logo"><?= e(SAIT_NAZVANIE) ?></div>
            <div class="slogan"><?= e(SAIT_GOROD) ?></div>
        </div>
        <nav>
            <ul>
                <li><a href="/">Главная</a></li>
                <li><a href="/pages/catalog.php">Каталог</a></li>
                <li><a href="/pages/contacts.php">Контакты</a></li>
            </ul>
        </nav>
    </header>
    <main>
