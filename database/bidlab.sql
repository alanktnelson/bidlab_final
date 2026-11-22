-- Bidlab tables. Select your database first, then run this file (in phpMyAdmin: SQL tab).
-- Money is stored as whole minor units (pesewas), so 12050 means 120.50. Times are UTC.

CREATE TABLE users (
  user_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_name VARCHAR(80) NOT NULL,
  user_email VARCHAR(190) NOT NULL UNIQUE,
  user_pass VARCHAR(255) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Named auction_categories so it can share a database with other projects.
CREATE TABLE auction_categories (
  cat_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  cat_name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auctions (
  auction_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  seller_id INT UNSIGNED NOT NULL,
  cat_id INT UNSIGNED NOT NULL,
  title VARCHAR(120) NOT NULL,
  description TEXT NOT NULL,
  item_condition ENUM('new', 'like_new', 'good', 'fair', 'poor') NOT NULL,
  location VARCHAR(120) NOT NULL,
  offers_pickup TINYINT(1) NOT NULL DEFAULT 0,
  offers_delivery TINYINT(1) NOT NULL DEFAULT 0,
  delivery_fee_minor BIGINT UNSIGNED NOT NULL DEFAULT 0,
  starting_price_minor BIGINT UNSIGNED NOT NULL,
  closes_at DATETIME NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_auctions_closes_at (closes_at),
  CONSTRAINT fk_auctions_seller FOREIGN KEY (seller_id) REFERENCES users (user_id),
  CONSTRAINT fk_auctions_category FOREIGN KEY (cat_id) REFERENCES auction_categories (cat_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE auction_photos (
  photo_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auction_id INT UNSIGNED NOT NULL,
  filename VARCHAR(80) NOT NULL,
  position TINYINT UNSIGNED NOT NULL,
  UNIQUE KEY uq_photo_position (auction_id, position),
  CONSTRAINT fk_photos_auction FOREIGN KEY (auction_id) REFERENCES auctions (auction_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE bids (
  bid_id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  auction_id INT UNSIGNED NOT NULL,
  bidder_id INT UNSIGNED NOT NULL,
  amount_minor BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_bid_amount (auction_id, amount_minor),
  INDEX idx_bids_bidder (bidder_id),
  CONSTRAINT fk_bids_auction FOREIGN KEY (auction_id) REFERENCES auctions (auction_id) ON DELETE CASCADE,
  CONSTRAINT fk_bids_bidder FOREIGN KEY (bidder_id) REFERENCES users (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE watchlist (
  user_id INT UNSIGNED NOT NULL,
  auction_id INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (user_id, auction_id),
  CONSTRAINT fk_watch_user FOREIGN KEY (user_id) REFERENCES users (user_id) ON DELETE CASCADE,
  CONSTRAINT fk_watch_auction FOREIGN KEY (auction_id) REFERENCES auctions (auction_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO auction_categories (cat_name) VALUES
  ('Electronics'), ('Collectibles'), ('Fashion'), ('Home & Garden'), ('Books & Media'),
  ('Toys & Games'), ('Music'), ('Art'), ('Sports'), ('Other');
