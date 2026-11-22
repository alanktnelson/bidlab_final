-- Admin section for Bidlab. Run once, after db/escrow.sql.
-- If your tables are not called users / auctions, change the names here and in core/escrow_config.php.

-- Roles and suspension on users.
ALTER TABLE users
    ADD COLUMN user_role        ENUM('user', 'admin') NOT NULL DEFAULT 'user',
    ADD COLUMN is_suspended     TINYINT(1) NOT NULL DEFAULT 0,
    ADD COLUMN suspended_reason VARCHAR(255) NULL,
    ADD COLUMN suspended_at     DATETIME NULL;

-- Removing a listing hides it instead of deleting it, so bids and orders keep their history.
ALTER TABLE auctions
    ADD COLUMN removed_at     DATETIME NULL,
    ADD COLUMN removed_reason VARCHAR(255) NULL,
    ADD COLUMN removed_by     INT UNSIGNED NULL,
    ADD KEY idx_auctions_removed (removed_at);

-- Every admin action: who, what, to what, and why. Never updated or deleted.
CREATE TABLE IF NOT EXISTS admin_log (
    log_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id     INT UNSIGNED NOT NULL,
    action       VARCHAR(40) NOT NULL,
    target_type  ENUM('user', 'auction', 'category', 'order', 'payout', 'dispute') NOT NULL,
    target_id    INT UNSIGNED NOT NULL,
    details      VARCHAR(500) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (log_id),
    KEY idx_admin_log_target (target_type, target_id),
    KEY idx_admin_log_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Make yourself the first admin (use your own email):
-- UPDATE users SET user_role = 'admin' WHERE user_email = 'you@example.com';
