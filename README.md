# Bidlab

An online auction marketplace for individuals and businesses. Sellers list a permitted item with photos, a starting price and a closing time. Buyers place higher and higher bids (English auction), and the highest bid at closing wins.

Built with PHP, MySQL, HTML, CSS and JavaScript only.

## Folder structure

```
index.php         Entry point, loads views/home.php
logout.php
actions/          Handle form posts, then redirect
controllers/      Validation and rules (*_ctr functions)
classes/          Database queries (extend Database)
core/             Helpers (core.php), connection (db_class.php), credentials (db_cred.php)
views/            Pages
views/layout/     Header, footer and shared parts
views/account/    Account pages
css/              Styles
js/               Countdowns, live updates and form checks
views/admin/      Admin pages (admins only)
actions/admin/    Admin form posts
cron/             escrow_jobs.php, run every 5 minutes by the server
database/         bidlab.sql, escrow.sql, admin.sql
images/auctions/  Uploaded photos
error/            error.log
```

A request flows through `views` → `actions` → `controllers` → `classes` → the database.

## Run it on localhost

1. Start MySQL and Apache in XAMPP.
2. Put this folder in `htdocs` as `htdocs/bidlab`.
3. Create a database called `bidlab`, select it in phpMyAdmin, and run `database/bidlab.sql`, then `database/escrow.sql`, then `database/admin.sql` in the SQL tab. Make yourself admin: `UPDATE users SET user_role = 'admin' WHERE user_email = 'you@example.com';` The tables can also share an existing database, such as a school account's.
4. Copy `core/db_cred.example.php` to `core/db_cred.php` and enter your database details.
5. Let the web server write photos and logs: `chmod 777 images/auctions error`. This is for your own laptop only.
6. Open <http://localhost/bidlab/>.

## Team

| Person | Looks after | Branch |
|---|---|---|
| Danita | `views/`, `css/`, `js/` | `frontend` |
| Attrams | `actions/`, `controllers/`, `classes/`, `core/` | `backend` |
| Bridgetta | `database/` | `database` |
| Alan | Merging and testing the app together | `integration`, `dev`, `main` |

## Not built yet

- Email notifications (won, pay now, paid, shipped, dispute, payout).
- Delivery address on orders, user reports, offering the item to the next bidder when a winner does not pay.
- Promoted auctions and password reset.
- Login rate limiting, which should be added before any public launch.

## Decisions still open

These values are placeholders at the top of `core/core.php`:

- Currency: `GHS`
- Minimum bid increment: 5.00
- Shortest and longest auction: 2 minutes (handy for demos) and 30 days

Escrow settings (Paystack, 5% fee, 48 hours to pay, 7 days to auto-release) are in `core/escrow_config.php`. Promotion fee and reserve prices are undecided.
