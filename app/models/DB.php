<?php
class DB
{
    private static $pdo;

    public static function get()
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        if (isset($GLOBALS['bdd']) && $GLOBALS['bdd'] instanceof PDO) {
            self::$pdo = $GLOBALS['bdd'];
            return self::$pdo;
        }

        $config = __DIR__ . '/../../config/db.php';
        if (file_exists($config)) {
            require $config; // defines $bdd
        }

        if (isset($bdd) && $bdd instanceof PDO) {
            self::$pdo = $bdd;
            return self::$pdo;
        }

        throw new Exception('PDO instance not available. Vérifiez config/db.php');
    }
}
