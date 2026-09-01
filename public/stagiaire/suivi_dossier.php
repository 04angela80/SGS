<?php
// 🔥 INDISPENSABLE : On démarre la session tout en haut pour propager l'ID
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/db.php';

$erreur_message = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (!empty($email)) {
        try {
            // Chercher le stagiaire par son email
            $req = $bdd->prepare("SELECT id, statut FROM stagiaires WHERE email = ?");
            $req->execute([$email]);
            $stagiaire = $req->fetch();

            if ($stagiaire) {
                // 🔥 SÉCURITÉ & LIEN : On enregistre l'ID réel dans la session globale PHP
                $_SESSION['id_stagiaire'] = intval($stagiaire['id']);

                // Si son dossier est déjà accepté par l'admin, on l'envoie sur son espace d'accueil stagiaire
                if ($stagiaire['statut'] === 'Validé' || strtolower($stagiaire['statut']) === 'validé' || strtolower($stagiaire['statut']) === 'valide') {
                    header("Location: stagiaire.php");
                } else {
                    // Sinon, on le laisse voir sa page d'attente dynamique
                    header("Location: attente.php?id=" . $stagiaire['id']);
                }
                exit();
            } else {
                $erreur_message = "Aucun dossier trouvé pour cette adresse e-mail.";
            }
        } catch (PDOException $e) {
            $erreur_message = "Erreur de base de données : " . $e->getMessage();
        }
    } else {
        $erreur_message = "Veuillez entrer votre adresse e-mail.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SGS - Suivre mon Dossier</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Poppins', sans-serif; min-height: 100vh; background: linear-gradient(135deg, #f8fafc, #eef2ff); display: flex; justify-content: center; align-items: center; color: #1f2937; }
        .container { width: 100%; max-width: 980px; background: linear-gradient(135deg, #38bdf8, #7c3aed, #c084fc); border-radius: 36px; padding: 50px 30px; box-shadow: 0 45px 90px rgba(76, 29, 149, 0.35); }
        .header { text-align: center; margin-bottom: 40px; color: #ffffff; }
        .logo { width: 110px; height: 110px; object-fit: cover; border-radius: 50%; background: #ffffff; padding: 8px; margin-bottom: 20px; box-shadow: 0 15px 35px rgba(0,0,0,0.35); }
        .header h1 { font-size: 32px; font-weight: 800; margin-bottom: 10px; }
        .card { width: 100%; max-width: 520px; background: rgba(255,255,255,0.96); border-radius: 30px; padding: 42px 36px; box-shadow: 0 30px 65px rgba(0,0,0,0.28); margin: 0 auto; }
        .form-group { display: flex; flex-direction: column; margin-bottom: 18px; }
        .form-group label { font-weight: 600; margin-bottom: 6px; color: #374151; }
        .form-group input { padding: 13px 16px; border-radius: 14px; border: 2px solid #e5e7eb; font-size: 14px; }
        .form-group input:focus { outline: none; border-color: #7c3aed; box-shadow: 0 0 0 4px rgba(124,58,237,0.25); }
        .btn { width: 100%; padding: 15px; border-radius: 50px; border: none; cursor: pointer; font-size: 16px; font-weight: 700; color: #ffffff; background: linear-gradient(135deg, #38bdf8, #7c3aed); box-shadow: 0 20px 45px rgba(124,58,237,0.55); transition: all 0.35s ease; }
        .btn:hover { transform: translateY(-3px) scale(1.05); }
        .link-back { display: block; text-align: center; margin-top: 20px; color: #7c3aed; text-decoration: none; font-weight: 600; }
        /* Rend le tableau défilable sur mobile pour éviter qu'il ne déborde */
.table-container {
    width: 100%;
    overflow-x: auto;
}

/* Sur écran mobile (téléphones) */
@media (max-width: 768px) {
    /* Les champs et boutons prennent toute la largeur pour être faciles à cliquer */
    input, select, button, .btn {
        width: 100% !important;
        margin-bottom: 10px;
    }

    /* Réduit un peu les marges internes pour gagner de la place */
    .container, .content {
        padding: 10px !important;
    }
}
    </style>
</head>
<body>
    <div class="container">
        <header class="header">
            <img src="../LOGO.jpeg" alt="Logo SGS" class="logo">
            <h1>Suivre mon dossier</h1>
            <p>Entrez l'e-mail utilisé lors de votre inscription pour voir votre statut</p>
            <?php if(!empty($erreur_message)): ?>
                <p style="color: #ef4444; font-weight: bold; margin-top: 15px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 10px;"><?php echo $erreur_message; ?></p>
            <?php endif; ?>
        </header>

        <main class="main-content">
            <form class="card" action="suivi_dossier.php" method="POST">
                <div class="form-group">
                    <label for="email">Votre adresse e-mail</label>
                    <input type="email" id="email" name="email" placeholder="exemple@mail.com" required>
                </div>
                <button type="submit" class="btn">Vérifier mon statut</button>
                <a href="INSCRPTION.php" class="link-back">Retour à l'inscription</a>
            </form>
        </main>
    </div>
</body>
</html>