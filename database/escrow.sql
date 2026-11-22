-- Escrow tables for Bidlab.
-- Money is stored in minor units (pesewas), like the rest of the site.
-- Times are stored in UTC.

CREATE TABLE IF NOT EXISTS escrows (
    escrow_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    auction_id          INT UNSIGNED NOT NULL,
    buyer_id            INT UNSIGNED NOT NULL,
    seller_id           INT UNSIGNED NOT NULL,
    item_amount_minor   INT UNSIGNED NOT NULL,              -- winning bid
    delivery_fee_minor  INT UNSIGNED NOT NULL DEFAULT 0,
    platform_fee_minor  INT UNSIGNED NOT NULL DEFAULT 0,    -- taken from the seller's payout
    total_minor         INT UNSIGNED NOT NULL,              -- what the buyer pays
    payout_minor        INT UNSIGNED NOT NULL,              -- what the seller receives
    currency            CHAR(3) NOT NULL DEFAULT 'GHS',
    handover            ENUM('pickup', 'delivery') NOT NULL,
    status              ENUM('awaiting_payment', 'funds_held', 'shipped', 'disputed', 'released', 'refunded', 'cancelled')
                        NOT NULL DEFAULT 'awaiting_payment',
    payment_reference   VARCHAR(100) NULL,
    refund_reference    VARCHAR(100) NULL,
    pay_by              DATETIME NOT NULL,
    paid_at             DATETIME NULL,
    shipped_at          DATETIME NULL,
    auto_release_at     DATETIME NULL,
    released_at         DATETIME NULL,
    refunded_at         DATETIME NULL,
    cancelled_at        DATETIME NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (escrow_id),
    UNIQUE KEY uq_escrow_auction (auction_id),
    UNIQUE KEY uq_escrow_payment_ref (payment_reference),
    KEY idx_escrow_buyer (buyer_id),
    KEY idx_escrow_seller (seller_id),
    KEY idx_escrow_status_payby (status, pay_by),
    KEY idx_escrow_status_release (status, auto_release_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Every status change, who made it and why. Never updated or deleted.
CREATE TABLE IF NOT EXISTS escrow_events (
    event_id     INT UNSIGNED NOT NULL AUTO_INCREMENT,
    escrow_id    INT UNSIGNED NOT NULL,
    from_status  VARCHAR(20) NULL,
    to_status    VARCHAR(20) NOT NULL,
    actor_id     INT UNSIGNED NULL,          -- NULL means the system did it
    note         VARCHAR(255) NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_event_escrow (escrow_id),
    CONSTRAINT fk_event_escrow FOREIGN KEY (escrow_id) REFERENCES escrows (escrow_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS disputes (
    dispute_id       INT UNSIGNED NOT NULL AUTO_INCREMENT,
    escrow_id        INT UNSIGNED NOT NULL,
    opened_by        INT UNSIGNED NOT NULL,
    reason           ENUM('not_received', 'not_as_described', 'damaged', 'other') NOT NULL,
    details          TEXT NOT NULL,
    status           ENUM('open', 'resolved_refund', 'resolved_release') NOT NULL DEFAULT 'open',
    resolution_note  VARCHAR(1000) NULL,
    resolved_by      INT UNSIGNED NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at      DATETIME NULL,
    PRIMARY KEY (dispute_id),
    KEY idx_dispute_escrow (escrow_id),
    KEY idx_dispute_status (status),
    CONSTRAINT fk_dispute_escrow FOREIGN KEY (escrow_id) REFERENCES escrows (escrow_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Where each seller gets paid.
CREATE TABLE IF NOT EXISTS payout_accounts (
    seller_id       INT UNSIGNED NOT NULL,
    method          ENUM('mobile_money', 'bank') NOT NULL,
    provider        VARCHAR(60) NOT NULL,        -- e.g. MTN, Telecel, AirtelTigo, or the bank name
    account_name    VARCHAR(120) NOT NULL,
    account_number  VARCHAR(30) NOT NULL,
    recipient_code  VARCHAR(100) NULL,           -- the payment provider's ID for this account
    updated_at      DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (seller_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One payout per released escrow.
CREATE TABLE IF NOT EXISTS payouts (
    payout_id           INT UNSIGNED NOT NULL AUTO_INCREMENT,
    escrow_id           INT UNSIGNED NOT NULL,
    seller_id           INT UNSIGNED NOT NULL,
    amount_minor        INT UNSIGNED NOT NULL,
    status              ENUM('pending', 'processing', 'sent', 'failed') NOT NULL DEFAULT 'pending',
    attempts            TINYINT UNSIGNED NOT NULL DEFAULT 0,  -- each attempt gets its own transfer reference
    transfer_reference  VARCHAR(100) NULL,
    failure_reason      VARCHAR(255) NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at             DATETIME NULL,
    PRIMARY KEY (payout_id),
    UNIQUE KEY uq_payout_escrow (escrow_id),
    KEY idx_payout_status (status),
    CONSTRAINT fk_payout_escrow FOREIGN KEY (escrow_id) REFERENCES escrows (escrow_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
