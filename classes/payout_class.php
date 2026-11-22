<?php
require_once __DIR__ . '/../core/escrow_db.php';

class payout_class extends escrow_db
{
    /* ---------- Seller payout accounts ---------- */

    public function get_account($seller_id)
    {
        return $this->fetch_one('SELECT * FROM payout_accounts WHERE seller_id = ?', [$seller_id]);
    }

    /** Insert or replace the seller's account. Clears the provider's recipient code because the details changed. */
    public function save_account($seller_id, array $account)
    {
        $this->db()->prepare(
            'INSERT INTO payout_accounts (seller_id, method, provider, account_name, account_number, recipient_code)
             VALUES (?, ?, ?, ?, ?, NULL)
             ON DUPLICATE KEY UPDATE method = VALUES(method), provider = VALUES(provider),
                 account_name = VALUES(account_name), account_number = VALUES(account_number), recipient_code = NULL'
        )->execute([
            $seller_id,
            $account['method'],
            $account['provider'],
            $account['account_name'],
            $account['account_number'],
        ]);
    }

    public function set_recipient_code($seller_id, $recipient_code)
    {
        $this->db()->prepare('UPDATE payout_accounts SET recipient_code = ? WHERE seller_id = ?')
            ->execute([$recipient_code, $seller_id]);
    }

    /* ---------- Payouts ---------- */

    /** Queue a payout. Safe to call twice for the same escrow: the second call does nothing. */
    public function create_payout($escrow_id, $seller_id, $amount_minor)
    {
        $this->db()->prepare(
            "INSERT IGNORE INTO payouts (escrow_id, seller_id, amount_minor, status, created_at) VALUES (?, ?, ?, 'pending', ?)"
        )->execute([$escrow_id, $seller_id, $amount_minor, self::now()]);
    }

    public function find_by_escrow($escrow_id)
    {
        return $this->fetch_one('SELECT * FROM payouts WHERE escrow_id = ?', [$escrow_id]);
    }

    /** Pending payouts whose seller has a payout account, ready to send. */
    public function pending_with_accounts($limit = 50)
    {
        return $this->fetch_all(
            "SELECT p.*, a.method, a.provider, a.account_name, a.account_number, a.recipient_code
               FROM payouts p
               JOIN payout_accounts a ON a.seller_id = p.seller_id
              WHERE p.status = 'pending'
              ORDER BY p.created_at
              LIMIT " . (int) $limit,
            []
        );
    }

    public function for_seller($seller_id)
    {
        return $this->fetch_all('SELECT * FROM payouts WHERE seller_id = ? ORDER BY created_at DESC', [$seller_id]);
    }

    public function find($payout_id)
    {
        return $this->fetch_one('SELECT * FROM payouts WHERE payout_id = ?', [$payout_id]);
    }

    /**
     * Take a pending payout so only one process sends it. Gives it a new transfer reference.
     * Returns the reference, or null if someone else already took it.
     */
    public function claim($payout_id)
    {
        $statement = $this->db()->prepare(
            "UPDATE payouts SET status = 'processing', attempts = attempts + 1, failure_reason = NULL
              WHERE payout_id = ? AND status = 'pending'"
        );
        $statement->execute([$payout_id]);
        if ($statement->rowCount() !== 1) {
            return null;
        }
        $payout = $this->find($payout_id);
        $reference = sprintf('bidlab-payout-%06d-%d', $payout_id, $payout['attempts']);
        $this->db()->prepare('UPDATE payouts SET transfer_reference = ? WHERE payout_id = ?')->execute([$reference, $payout_id]);
        return $reference;
    }

    /** Payouts stuck in 'processing' (no answer from the provider). An admin should check these. */
    public function processing()
    {
        return $this->fetch_all("SELECT * FROM payouts WHERE status = 'processing' ORDER BY created_at", []);
    }

    public function mark_sent($payout_id)
    {
        $statement = $this->db()->prepare(
            "UPDATE payouts SET status = 'sent', failure_reason = NULL, sent_at = ? WHERE payout_id = ? AND status = 'processing'"
        );
        $statement->execute([self::now(), $payout_id]);
        return $statement->rowCount() === 1;
    }

    public function mark_failed($payout_id, $reason)
    {
        $statement = $this->db()->prepare(
            "UPDATE payouts SET status = 'failed', failure_reason = ? WHERE payout_id = ? AND status IN ('pending', 'processing')"
        );
        $statement->execute([mb_substr($reason, 0, 255), $payout_id]);
        return $statement->rowCount() === 1;
    }

    /** Put a failed payout back in the queue, e.g. after the seller fixes their account. */
    public function retry($payout_id)
    {
        $this->db()->prepare("UPDATE payouts SET status = 'pending' WHERE payout_id = ? AND status = 'failed'")
            ->execute([$payout_id]);
    }

    private function fetch_one($sql, array $params)
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        $row = $statement->fetch();
        return $row === false ? null : $row;
    }

    private function fetch_all($sql, array $params)
    {
        $statement = $this->db()->prepare($sql);
        $statement->execute($params);
        return $statement->fetchAll();
    }
}
