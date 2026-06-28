<?php
/**
 * Единая точка подключения слоя каталога. Подключив этот файл, код получает
 * доступ к фасаду Catalog::provider() и всем адаптерам. Внутри тянет и боевой
 * CatalogApi (через PartsApiAdapter), так что отдельно подключать catalog_api.php
 * не нужно.
 */
require_once __DIR__ . '/catalog/Manager.php';
