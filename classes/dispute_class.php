<?php
require_once __DIR__ . '/../core/escrow_db.php';

class dispute_class extends escrow_db
{
    public function create($escrow_id, $opened_by, $reason, $details)
    {
        $this->db()->prepare(
            'INSERT INTO disputes (escrow_id, opened_by, reason, details, status, created_at) VALUES (?, ?, ?, ?, \'open\', ?)'
        )->execute([$escrow_id, $opened_by, $reason, $details, self::now()]);
        return (int) $this->db()->lastInsertId();
    }

    public function find($dispute_id)
    {
        return $this->fetch_one('SELECT * FROM disputes WHERE dispute_id = ?', [$dispute_id]);
    }

    public function find_for_update($dispute_id)
    {
        return $this->fetch_one('SELECT * FROM disputes WHERE dispute_id = ? FOR UPDATE', [$dispute_id]);
    }

    public function find_open_by_escrow($escrow_id)
    {
        return $this->fetch_one("SELECT * FROM disputes WHERE escrow_id = ? AND status = 'open'", [$escrow_id]);
    }

    public function for_escrow($escrow_id)
    {
        return $this->fetch_all('SELECT * FROM disputes WHERE escrow_id = ? ORDER BY created_at DESC', [$escrow_id]);
    }

    /** Open disputes with the escrow details an admin needs, oldest first. */
    public function list_open()
    {
        return $this->fetch_all(
            "SELECT d.*, e.auction_id, e.buyer_id, e.seller_id, e.total_minor, e.currency, e.handover, e.paid_at, e.shipped_at
               FROM disputes d
               JOIN escrows e ON e.escrow_id = d.escrow_id
              WHERE d.status = 'open'
              ORDER BY d.created_at",
            []
        );
    }

    /** Close an open dispute. Returns false if it was already resolved. */
    public function resolve($dispute_id, $status, $note, $resolved_by)
    {
        $statement = $this->db()->prepare(
            "UPDATE disputes SET status = ?, resolution_note = ?, resolved_by = ?, resolved_at = ?
              WHERE dispute_id = ? AND status = 'open'"
        );
        $statement->execute([$status, $note, $resolved_by, self::now(), $dispute_id]);
        return $statement->rowCount() === 1;
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
