<?php
/*
 * Escrow and payment settings for Bidlab.
 *
 * Keep the Paystack keys OUT of this file. Set them as environment variables on the server
 * (e.g. in Apache: SetEnv PAYSTACK_SECRET_KEY sk_test_xxx) so they never end up in git.
 * Use the sk_test_ key while building and switch to sk_live_ when you go live.
 */

if (defined('ESCROW_CONFIG_LOADED')) {
    return;
}
define('ESCROW_CONFIG_LOADED', true);

/* ---------- Paystack ---------- */
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: '');
define('PAYSTACK_API_BASE', getenv('PAYSTACK_API_BASE') ?: 'https://api.paystack.co');
define('PAYSTACK_TIMEOUT_SECONDS', 30);

// The full address of your site, with no slash at the end. Paystack sends buyers back here after paying.
define('SITE_BASE_URL', rtrim(getenv('SITE_BASE_URL') ?: 'http://localhost/bidlab_fixed', '/'));

/* ---------- Money and time limits ---------- */
define('ESCROW_CURRENCY', 'GHS');
define('ESCROW_PLATFORM_FEE_PERCENT', 5);    // Bidlab's cut of the winning bid, taken from the seller's payout
define('ESCROW_PAYMENT_WINDOW_HOURS', 48);   // buyer must pay within this after the auction closes
define('ESCROW_AUTO_RELEASE_DAYS', 7);       // money goes to the seller this long after shipping if the buyer says nothing

/* ---------- Your existing tables ---------- */
// Admins are users with user_role = 'admin' (see db/admin.sql). Change these if your tables are named differently.
// Columns the admin pages expect:
//   users:      user_id, user_name, user_email (+ user_role, is_suspended... from db/admin.sql)
//   auctions:   auction_id, seller_id, cat_id, title, closes_at, created_at (+ removed_at... from db/admin.sql)
//   bids:       auction_id, bidder_id, amount_minor
//   auction_categories: cat_id, cat_name
define('USERS_TABLE', 'users');
define('USERS_ID_COLUMN', 'user_id');
define('USERS_EMAIL_COLUMN', 'user_email');
define('AUCTIONS_TABLE', 'auctions');
define('BIDS_TABLE', 'bids');
define('CATEGORIES_TABLE', 'auction_categories');

/* ---------- Mobile money networks (name shown to sellers => Paystack code) ---------- */
define('PAYSTACK_MOMO_CODES', [
    'MTN' => 'MTN',
    'Telecel' => 'VOD',
    'AirtelTigo' => 'ATL',
]);
