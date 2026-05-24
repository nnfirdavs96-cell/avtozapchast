#!/bin/bash
# Экспорт базы данных с локального сервера для загрузки на Timeweb
# Запустить на сервере 10.230.13.107:
#   bash deploy/timeweb/export-db.sh

DATE=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="autodoc_export_${DATE}.sql"

# Реквизиты БД берём из config/db_credentials.php (в git его нет).
# Пароль не хранится в скрипте — mysqldump запросит его интерактивно (-p).
DB_NAME="${DB_NAME:-avtozapchast}"
DB_USER="${DB_USER:-avtouser}"

echo "Экспорт базы данных ${DB_NAME}..."
mysqldump \
    -u "$DB_USER" \
    -p \
    --single-transaction \
    --routines \
    --triggers \
    "$DB_NAME" > "$DUMP_FILE"

if [ $? -eq 0 ]; then
    SIZE=$(du -sh "$DUMP_FILE" | cut -f1)
    echo "Готово! Файл: $DUMP_FILE (размер: $SIZE)"
    echo ""
    echo "Следующий шаг: загрузить этот файл через phpMyAdmin на Timeweb"
    echo "  Панель Timeweb -> Базы данных -> phpMyAdmin -> Import"
else
    echo "ОШИБКА: экспорт не удался"
    exit 1
fi
