<?php
// Configuration de la base
define('DB_HOST', '127.0.0.1');   // PAS 8080, c'est Apache. Ici c'est MySQL.
define('DB_PORT', '3307');        // Port MySQL par défaut
define('DB_NAME', 'sgs');         // Nom de ta base
define('DB_USER', 'root');        // Utilisateur MySQL
define('DB_PASS', '');            // Mot de passe MySQL (souvent vide en local)

// Connexion PDO
try {
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8";
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✅ Connexion réussie à la base<br>";

    // Test lecture
    $result = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_ASSOC);
    echo "Tables disponibles : <pre>" . print_r($result, true) . "</pre>";

} catch (PDOException $e) {
    die("❌ Erreur connexion DB: " . $e->getMessage());
}
?>