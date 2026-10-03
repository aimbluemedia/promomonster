-- Where a review request sends the customer.
--
-- Until now there was one answer and it was not written down: every request
-- pointed at locations.google_review_url, because Google was the only place a
-- review could be left. A business can now host its own reviews here, so the
-- same email has two possible destinations and the row has to say which -- the
-- click handler resolves the link at click time, days after the send, and it
-- cannot guess.
--
-- DEFAULT 'google' is not a preference, it is the truth about every row that
-- already exists: they were all sent before this column existed and they all
-- pointed at Google. The form defaults to PromoMonster, which is a separate
-- decision made in the view.
--
-- Written for phpMyAdmin: every quoted string on one line, one ALTER per
-- column, each behind its own guard so the file can be pasted twice.

SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND COLUMN_NAME = 'destination'), 'DO 0', 'ALTER TABLE review_requests ADD COLUMN destination ENUM(''google'', ''promomonster'') NOT NULL DEFAULT ''google'' AFTER channel'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- The lookup all three pages make: one location's requests of one destination,
-- newest first. Without it the Google page and the PromoMonster page both scan
-- every request the account has ever sent to show their own ten.
SET @sql := (SELECT IF(EXISTS(SELECT 1 FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'review_requests' AND INDEX_NAME = 'requests_destination_index'), 'DO 0', 'CREATE INDEX requests_destination_index ON review_requests (location_id, destination, created_at)'));
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
