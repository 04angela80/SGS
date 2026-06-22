<?php
// 1. DÉMARRAGE DE LA SESSION
session_start();

// 2. Connexion à la base de données
require_once __DIR__ . '/../../config/db.php';

$erreur = "";

// 3. Traitement du Formulaire Email
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_connexion'])) {
    $email = trim($_POST['email']);

    if (!empty($email)) {
        try {
            $sql = "SELECT id, nom, prenom, statut FROM stagiaires WHERE email = :email";
            $stmt = $bdd->prepare($sql);
            $stmt->execute(['email' => $email]);
            $stagiaire = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($stagiaire) {
                // On garde l'ID en session par sécurité
                $_SESSION['id_stagiaire'] = $stagiaire['id'];
                
                // Nettoyage pour éviter les pièges de majuscules
                $statut = strtolower(trim($stagiaire['statut'] ?? ''));

                // 🔥 LES 3 POSSIBILITÉS D'AIGUILLAGE AVEC ID INCLUS :
                
                if ($statut === 'en attente' || $statut === 'attente' || $statut === '') {
                    // CAS 1 : Tête Dure (En attente) -> Va vers attente.php avec son ID
                    header('Location: attente.php?id=' . $stagiaire['id']);
                    exit();
                    
                } elseif ($statut === 'refusé' || $statut === 'refuse') {
                    // CAS 2 : Divine (Refusé) -> Va AUSSI vers attente.php avec son ID pour voir le message rouge
                    header('Location: attente.php?id=' . $stagiaire['id']);
                    exit();
                    
                } elseif ($statut === 'validé' || $statut === 'valide') {
                    // CAS 3 : Camille (Validé) -> Va vers validation.php
                    header('Location: VALIDATION.php?id=' . $stagiaire['id']);
                    exit();
                    
                } else {
                    $erreur = "Statut du dossier non reconnu.";
                }

            } else {
                $erreur = "Aucun compte trouvé avec cette adresse email.";
            }
        } catch (PDOException $e) {
            $erreur = "Erreur technique : " . $e->getMessage();
        }
    } else {
        $erreur = "Veuillez entrer votre adresse email.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>SGS - Espace d'Orientation Stagiaire</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: linear-gradient(135deg, #0056b3, #003d80); height: 100vh; display: flex; align-items: center; justify-content: center; }
    .container { width: 100%; max-width: 850px; display: flex; gap: 30px; padding: 20px; box-sizing: border-box; }
    .bloc-card { flex: 1; background: white; padding: 40px 30px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.25); text-align: center; display: flex; flex-direction: column; justify-content: space-between; }
    .icon-header { font-size: 50px; margin-bottom: 15px; }
    .bloc-card.nouveau .icon-header { color: #ff9800; }
    .bloc-card.ancien .icon-header { color: #0056b3; }
    h2 { margin: 0 0 12px 0; color: #1e293b; font-size: 1.5em; }
    p { color: #64748b; font-size: 0.95em; line-height: 1.5; margin-bottom: 25px; }
    .btn-action { display: inline-block; text-decoration: none; background: #ff9800; color: white; padding: 14px; border-radius: 8px; font-weight: bold; font-size: 1em; cursor: pointer; border: none; width: 100%; box-sizing: border-box; transition: background 0.2s; text-align: center; }
    .btn-action:hover { background: #e65100; }
    .btn-connexion { background: #0056b3; }
    .btn-connexion:hover { background: #003d80; }
    .input-email { width: 100%; padding: 12px 15px; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 1em; box-sizing: border-box; outline: none; margin-bottom: 15px; text-align: center; }
    .input-email:focus { border-color: #0056b3; }
    .error-msg { background: #ffeeef; color: #dc3545; padding: 10px; border-radius: 6px; border-left: 4px solid #dc3545; font-size: 0.85em; margin-bottom: 15px; text-align: left; }
  </style>
</head>
<body>

  <div class="container">
    <div class="bloc-card nouveau">
      <div>
        <div class="icon-header"><i class="fas fa-user-plus"></i></div>
        <h2>Vous êtes un nouveau stagiaire ?</h2>
        <p>Cliquez ici pour soumettre votre demande, remplir votre fiche administrative d'inscription et intégrer le système du projet.</p>
      </div>
      <a href="INSCRPTION.php" class="btn-action">
        <i class="fas fa-file-signature"></i> Commencer l'inscription
      </a>
    </div>

    <div class="bloc-card ancien">
      <div>
        <div class="icon-header"><i class="fas fa-user-check"></i></div>
        <h2>Vous avez déjà un compte ?</h2>
        <p>Entrez votre adresse email pour suivre l'état de validation de votre dossier ou accéder directement à votre espace.</p>
        
        <?php if(!empty($erreur)): ?>
          <div class="error-msg">
            <i class="fas fa-exclamation-circle"></i> <?php echo $erreur; ?>
          </div>
        <?php endif; ?>
      </div>

      <form action="connexion_stagiaire.php" method="POST">
        <input type="email" name="email" class="input-email" placeholder="Ex: votre.email@mail.com" required>
        <button type="submit" name="action_connexion" class="btn-action btn-connexion">
          <i class="fas fa-search"></i> Vérifier mon statut
        </button>
      </form>
    </div>
  </div>

</body>
</html>