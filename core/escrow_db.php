<?php
/**
 * Shared database connection for the escrow classes.
 *
 * All escrow classes use the same PDO connection, so a controller can wrap
 * changes to several tables in one transaction.
 *
 * It uses the same database details as the rest of the site (core/db_cred.php).
 */
require_once __DIR__ . '/db_cred.php';

class escrow_db
{
    private static $pdo = null;

    protected static function connect()
    {
        if (self::$pdo === null) {
            self::$pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );
            self::$pdo->exec("SET time_zone = '+00:00'");
        }
        return self::$pdo;
    }

    protected function db()
    {
        return self::connect();
    }

    /**
     * Run $work inside a transaction. Rolls back and rethrows on any error.
     * Nested calls join the outer transaction.
     */
    public static function transaction(callable $work)
    {
        $pdo = self::connect();
        if ($pdo->inTransaction()) {
            return $work();
        }
        $pdo->beginTransaction();
        try {
            $result = $work();
            $pdo->commit();
            return $result;
        } catch (Throwable $error) {
            $pdo->rollBack();
            throw $error;
        }
    }

    protected static function now()
    {
        return gmdate('Y-m-d H:i:s');
    }
}
