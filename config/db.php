<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'sgs');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // AJOUT DU PORT 3307 ICI :
    $bdd = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";port=3307;charset=utf8", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die("Erreur de connexion à la base de données : " . $e->getMessage());
}
?>