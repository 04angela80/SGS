<?php
session_start();

// 1. Connexion à la base de données
require_once __DIR__ . '/../../config/db.php';

// 2. Récupération de l'ID du stagiaire depuis l'URL
$stagiaire_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($stagiaire_id > 0) {
    // 3. On va chercher le statut mis à jour dans la base de données
    $req = $bdd->prepare("SELECT nom, prenom, statut FROM stagiaires WHERE id = ?");
    $req->execute([$stagiaire_id]);
    $stagiaire = $req->fetch();

    if (!$stagiaire) {
        die("Stagiaire introuvable.");
    }

    // 4. LA LOGIQUE RECADRÉE : Si l'admin a validé, on envoie vers validation.php
    $statut_nettoye = strtolower(trim($stagiaire['statut'] ?? ''));
    if ($statut_nettoye === 'validé' || $statut_nettoye === 'valide') {
        $_SESSION['id_stagiaire'] = $stagiaire_id;
        // Changement ici : redirection vers la page de félicitations / validation
        header("Location: VALIDATION.php?id=" . $stagiaire_id);
        exit();
    }
} else {
    die("Accès non autorisé. ID manquant.");
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SGS - Suivi de votre demande</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Poppins', sans-serif; }
        body { min-height: 100vh; background: radial-gradient(circle at top, #0f2027, #203a43, #2c5364); display: flex; justify-content: center; align-items: center; color: #fff; }
        .card-confirmation { background: rgba(255, 255, 255, 0.08); backdrop-filter: blur(12px); padding: 50px 60px; border-radius: 20px; text-align: center; max-width: 500px; box-shadow: 0 30px 60px rgba(0, 0, 0, 0.4); }
        .logo { width: 110px; height: 110px; object-fit: cover; border-radius: 50%; background: #fff; padding: 8px; margin-bottom: 25px; }
        h1 { font-size: 32px; margin-bottom: 15px; }
        p { font-size: 16px; opacity: 0.9; margin-bottom: 35px; line-height: 1.6; }
        .btn { padding: 14px 32px; border: none; border-radius: 30px; background: linear-gradient(135deg, #ffc107, #ff8f00); color: #fff; font-weight: 600; font-size: 15px; cursor: pointer; text-decoration: none; display: inline-block; transition: all 0.3s; }
        .btn:hover { transform: scale(1.05); box-shadow: 0 10px 20px rgba(255, 143, 0, 0.3); }
        .btn-danger { background: linear-gradient(135deg, #ff4d4d, #c30000); }
        .btn-danger:hover { box-shadow: 0 10px 20px rgba(244, 67, 54, 0.3); }
    </style>
</head>
<body>
    <div class="card-confirmation">
        <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo">
        
        <p style="font-weight: bold; font-size: 18px; color: #38bdf8; margin-bottom: 10px;">
            Bonjour <?php echo htmlspecialchars($stagiaire['prenom'] . ' ' . $stagiaire['nom']); ?> !
        </p>

        <?php if ($stagiaire['statut'] === 'Refusé'): ?>
            <h1 style="color: #ff4d4d;">Inscription Refusée</h1>
            <p>Désolé, votre demande d'inscription au sein de l'entreprise a été rejetée par l'administration après examen.</p>
            
        <?php else: ?>
            <h1 style="color: #ffc107;">Inscription en attente</h1>
            <p>Votre inscription a été reçue.<br>L'administration étudie actuellement vos informations. Votre dossier est en attente de traitement.</p>
            
            <button class="btn" onclick="window.location.reload();">🔄 Rafraîchir mon statut</button>
        <?php endif; ?>
    </div>
</body>
</html>