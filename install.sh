#!/bin/bash
set -e

echo "======================================"
echo "  АвтоЗапчасть — Установка сервера"
echo "======================================"

# 1. Обновление системы и установка пакетов
echo "[1/6] Устанавливаем Apache, PHP, MariaDB..."
sudo apt update -q
sudo apt install -y apache2 php8.2 php8.2-mysql php8.2-mbstring php8.2-curl php8.2-xml php8.2-gd mariadb-server git

# 2. Запуск сервисов
echo "[2/6] Запускаем сервисы..."
sudo systemctl start apache2
sudo systemctl enable apache2
sudo systemctl start mariadb
sudo systemctl enable mariadb

# 3. Создание базы данных и пользователя
echo "[3/6] Создаём базу данных..."
sudo mysql -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS avtozapchast CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'avto'@'localhost' IDENTIFIED BY 'avto123';
GRANT ALL PRIVILEGES ON avtozapchast.* TO 'avto'@'localhost';
FLUSH PRIVILEGES;
SQL

# 4. Клонирование проекта
echo "[4/6] Скачиваем проект..."
cd /var/www/html
sudo rm -rf avtozapchast
sudo git clone https://github.com/nnfirdavs96-cell/avtozapchast.git
cd avtozapchast

# Настраиваем конфиг БД
sudo tee config/database.php > /dev/null <<'PHP'
<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'avto');
define('DB_PASS', 'avto123');
define('DB_NAME', 'avtozapchast');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                ]
            );
        } catch (PDOException $e) {
            die(json_encode(['error' => 'DB connection failed: ' . $e->getMessage()]));
        }
    }
    return $pdo;
}
PHP

# Настраиваем APP_URL
SERVER_IP_CFG=$(hostname -I | awk '{print $1}')
sudo tee config/config.php > /dev/null <<PHP
<?php
define('APP_NAME', 'АвтоЗапчасть');
define('APP_URL', 'http://$SERVER_IP_CFG/avtozapchast');
define('APP_ROOT', dirname(__DIR__));
define('UPLOAD_PATH', APP_ROOT . '/assets/uploads/');
define('UPLOAD_URL', APP_URL . '/assets/uploads/');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once APP_ROOT . '/config/database.php';
require_once APP_ROOT . '/includes/functions.php';
PHP

# 5. Заливаем схему и данные в БД
echo "[5/6] Заливаем базу данных..."
mysql -u avto -pavto123 avtozapchast < /var/www/html/avtozapchast/sql/schema.sql

# 6. Права на папку
echo "[6/6] Настраиваем права..."
sudo chown -R www-data:www-data /var/www/html/avtozapchast
sudo chmod -R 755 /var/www/html/avtozapchast
sudo mkdir -p /var/www/html/avtozapchast/assets/uploads
sudo chmod -R 775 /var/www/html/avtozapchast/assets/uploads

# Получаем IP сервера
SERVER_IP=$(hostname -I | awk '{print $1}')

echo ""
echo "======================================"
echo "  Готово! Сайт установлен."
echo "======================================"
echo ""
echo "  Откройте в браузере:"
echo "  http://$SERVER_IP/avtozapchast/"
echo ""
echo "  Тестовые аккаунты (пароль: Password123!):"
echo "  buyer@avtozapchast.ru      — покупатель"
echo "  manager@avtozapchast.ru    — менеджер"
echo "  admin@avtozapchast.ru      — администратор"
echo "  superadmin@avtozapchast.ru — суперадмин"
echo "======================================"
