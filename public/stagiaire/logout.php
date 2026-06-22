<?php
// 1. Démarrage de la session pour cibler celle qui est active
session_start();

// 2. Nettoyage complet de toutes les variables temporaires
session_unset();

// 3. Destruction définitive de la session sur l'ordinateur unique partagé
session_destroy();

// 4. Redirection instantanée vers l'écran de connexion principal pour le stagiaire suivant
header('Location: ../../public/index.php');
exit();
?>