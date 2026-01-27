START TRANSACTION;

-- Preview counts
SELECT COUNT(*) AS point_transactions_count
FROM point_transactions
WHERE user_id = 1
  AND DATE(created_at) = '2026-01-22';

SELECT COUNT(*) AS user_points_count
FROM user_points
WHERE user_id = 1
  AND DATE(created_at) = '2026-01-22';

-- Delete from both tables
DELETE FROM point_transactions
WHERE user_id = 1
  AND DATE(created_at) = '2026-01-22';

DELETE FROM user_points
WHERE user_id = 1
  AND DATE(created_at) = '2026-01-22';

COMMIT;
