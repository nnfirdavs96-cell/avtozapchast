<?php
// Redirect to catalog with category filter
require_once dirname(__DIR__) . '/config/config.php';

$slug = trim($_GET['slug'] ?? '');
if (!$slug) redirect(APP_URL . '/catalog/index.php');

redirect(APP_URL . '/catalog/index.php?category=' . urlencode($slug));
