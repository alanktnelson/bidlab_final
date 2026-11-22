-- Run this ONLY if you already ran an older escrow.sql (its payouts table has no 'processing' status).
-- New installs: just run bidlab.sql, then escrow.sql, then admin.sql.
ALTER TABLE payouts
    MODIFY status ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    ADD COLUMN attempts TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER status;
