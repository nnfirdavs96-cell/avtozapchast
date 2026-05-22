#!/bin/bash
# Экспорт базы данных с локального сервера для загрузки на Timeweb
# Запустить на сервере 10.230.13.107:
#   bash deploy/timeweb/export-db.sh

DATE=$(date +%Y%m%d_%H%M%S)
DUMP_FILE="autodoc_export_${DATE}.sql"

echo "Экспорт базы данных avtozapchast..."
mysqldump \
    -u avtouser \
    -p'Avto@2024!' \
    --single-transaction \
    --routines \
    --triggers \
    avtozapchast > "$DUMP_FILE"

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
