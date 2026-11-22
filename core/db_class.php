<?php
class Database {
    protected $conn;

    public function __construct() {
        require_once 'db_cred.php';
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
        } catch (mysqli_sql_exception $error) {
            error_log($error->getMessage());
            die('Connection failed.');
        }
        $this->conn->set_charset('utf8mb4');
        $this->conn->query("SET time_zone = '+00:00'");
    }

    /** Runs a prepared statement and returns all of its rows. */
    protected function fetch_all($sql, $types = '', ...$params) {
        $stmt = $this->conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    protected function fetch_one($sql, $types = '', ...$params) {
        return $this->fetch_all($sql, $types, ...$params)[0] ?? null;
    }

    protected function run($sql, $types = '', ...$params) {
        $stmt = $this->conn->prepare($sql);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        return $stmt->execute();
    }
}
?>
