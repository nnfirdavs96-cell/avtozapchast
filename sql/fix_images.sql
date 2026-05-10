-- Reset all parts.images to empty array so productImageUrl falls back to placeholder.jpg
-- Run this on the server to fix demo_*.svg 404 errors

USE avtozapchast;
UPDATE parts SET images = '[]' WHERE images LIKE '%demo_%' OR images LIKE '%.svg%' OR images IS NULL OR images = '';
SELECT COUNT(*) AS reset_count FROM parts WHERE images = '[]';
