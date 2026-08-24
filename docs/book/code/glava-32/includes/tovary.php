<?php
require_once __DIR__ . '/db.php';

function vse_tovary(): array
{
    return zapros('
        SELECT id, nazvanie, artikul, brend, kategoriya, cena AS zakup, ostatok
        FROM tovary
        WHERE aktivnyi = 1
        ORDER BY nazvanie
    ');
}

function tovar_po_id(int $id): ?array
{
    return zapros_odin('
        SELECT id, nazvanie, artikul, brend, kategoriya, cena AS zakup, ostatok
        FROM tovary
        WHERE id = ? AND aktivnyi = 1
    ', [$id]);
}

function vse_brendy(): array
{
    $stroki = zapros('SELECT DISTINCT brend FROM tovary WHERE aktivnyi = 1 ORDER BY brend');
    return array_column($stroki, 'brend');
}

function vse_kategorii(): array
{
    $stroki = zapros('SELECT DISTINCT kategoriya FROM tovary WHERE aktivnyi = 1 ORDER BY kategoriya');
    return array_column($stroki, 'kategoriya');
}
