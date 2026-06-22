<?php
// Inclusion de la connexion BDD (Chemin adapté vers ta racine)
require_once __DIR__ . '/../../config/db.php';
// Inclusion du moteur de notifications mis à jour avec tes attributs
require_once __DIR__ . '/../../config/notifications_moteur.php';

$erreur_message = "";

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom = isset($_POST['nom']) ? trim($_POST['nom']) : '';
    $prenom = isset($_POST['prenom']) ? trim($_POST['prenom']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $sexe = isset($_POST['sexe']) ? trim($_POST['sexe']) : '';
    $service = isset($_POST['service']) ? trim($_POST['service']) : '';
    $filiere = isset($_POST['filiere']) ? trim($_POST['filiere']) : '';

    if (!empty($nom) && !empty($prenom) && !empty($email) && isset($_FILES['lettre_pdf'])) {
        try {
            // 1. VÉRIFIER SI L'EMAIL EXISTE DÉJÀ
            $checkEmail = $bdd->prepare("SELECT id FROM stagiaires WHERE email = ?");
            $checkEmail->execute([$email]);
            
            if ($checkEmail->rowCount() > 0) {
                $erreur_message = "Cette adresse e-mail est déjà utilisée.";
            } else {
                
                // 2. GESTION DE L'UPLOAD DU FICHIER PDF
                $file = $_FILES['lettre_pdf'];
                $fileName = $file['name'];
                $fileTmpName = $file['tmp_name'];
                $fileSize = $file['size'];
                $fileError = $file['error'];
                
                // Extraction de l'extension du fichier
                $fileExt = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                
                if ($fileExt === 'pdf') {
                    if ($fileError === 0) {
                        if ($fileSize <= 5000000) { // Limite fixée à 5 Mo maximum
                            
                            // Création d'un nom de fichier unique et propre
                            $dossier_destination = 'uploads/lettres/';
                            
                            // Si le dossier n'existe pas, PHP le crée automatiquement
                            if (!is_dir($dossier_destination)) {
                                mkdir($dossier_destination, 0777, true);
                            }
                            
                            $nouveau_nom_fichier = "lettre_" . strtolower($nom) . "_" . strtolower($prenom) . "_" . time() . ".pdf";
                            $chemin_complet_destination = $dossier_destination . $nouveau_nom_fichier;
                            
                            // Déplacement du fichier temporaire vers le dossier définitif
                            if (move_uploaded_file($fileTmpName, $chemin_complet_destination)) {
                                
                                // 3. INSERTION DANS LA BASE DE DONNÉES (Le chemin du PDF est stocké dans 'lettre')
                                $insert = $bdd->prepare("INSERT INTO stagiaires (nom, prenom, email, sexe, service, filiere, lettre, statut) VALUES (?, ?, ?, ?, ?, ?, ?, 'En attente')");
                                $resultat = $insert->execute([$nom, $prenom, $email, $sexe, $service, $filiere, $chemin_complet_destination]);
                                
                                if ($resultat) {
                                    $id_nouveau = $bdd->lastInsertId();
                                    
                                    // Envoi de la notification à l'administrateur
                                    ajouterNotification($bdd, 1, 'admin', 'Nouvelle Inscription', 'Le stagiaire ' . htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom) . ' vient de soumettre son dossier pour le service ' . htmlspecialchars($service) . '.', 'non_lu');
                                    
                                    // Envoi de la notification au stagiaire
                                    ajouterNotification($bdd, $id_nouveau, 'stagiaire', 'Inscription reçue', 'Votre dossier a bien été enregistré et sera traité par l’administration. Vous serez informé(e) dès qu’il sera validé.', 'non_lu');

                                    // Redirection vers la page d'attente
                                    header("Location: attente.php?id=" . $id_nouveau);
                                    exit();
                                }
                                
                            } else {
                                $erreur_message = "Une erreur est survenue lors du déplacement de votre fichier sur le serveur.";
                            }
                        } else {
                            $erreur_message = "Votre fichier est trop lourd (Maximum 5 Mo).";
                        }
                    } else {
                        $erreur_message = "Une erreur est survenue lors du téléchargement de votre fichier.";
                    }
                } else {
                    $erreur_message = "Seuls les fichiers au format PDF sont acceptés pour la lettre de demande de stage.";
                }
            }
        } catch (PDOException $e) {
            $erreur_message = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    } else {
        $erreur_message = "Veuillez remplir tous les champs obligatoires et joindre votre lettre en PDF.";
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>SGS - Inscription Stagiaire</title>
    <style>
        /* =======================
           RESET
        ======================= */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        /* =======================
           BODY
        ======================= */
        body {
            font-family: 'Poppins', sans-serif;
            min-height: 100vh;
            background: linear-gradient(135deg, #f8fafc, #eef2ff);
            display: flex;
            justify-content: center;
            align-items: center;
            color: #1f2937;
            padding: 40px 0;
        }
        
        /* =======================
           CONTAINER
        ======================= */
        .container {
            width: 100%;
            max-width: 980px;
            background: linear-gradient(
                135deg,
                #38bdf8,
                #7c3aed,
                #c084fc
            );
            border-radius: 36px;
            padding: 50px 30px;
            box-shadow: 0 45px 90px rgba(76, 29, 149, 0.35);
            animation: slideIn 1s ease forwards;
        }
        
        /* =======================
           HEADER
        ======================= */
        .header {
            text-align: center;
            margin-bottom: 40px;
            color: #ffffff;
        }
        
        .logo-container {
            margin-bottom: 20px;
        }
        
        .logo {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 50%;
            background: #ffffff;
            padding: 8px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.35);
        }
        
        .header h1 {
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 15px;
            opacity: 0.92;
        }
        
        /* =======================
           MAIN
        ======================= */
        .main-content {
            display: flex;
            justify-content: center;
        }
        
        /* =======================
           CARD FORM
        ======================= */
        .card {
            width: 100%;
            max-width: 520px;
            background: rgba(255,255,255,0.96);
            border-radius: 30px;
            padding: 42px 36px;
            box-shadow: 0 30px 65px rgba(0,0,0,0.28);
        }
        
        /* =======================
           FORM GROUPS
        ======================= */
        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 18px;
        }
        
        .form-group label {
            font-weight: 600;
            margin-bottom: 6px;
            color: #374151;
        }
        
        .form-group input,
        .form-group select {
            padding: 13px 16px;
            border-radius: 14px;
            border: 2px solid #e5e7eb;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 4px rgba(124,58,237,0.25);
        }
        
        /* Style spécial pour l'input file */
        .form-group input[type="file"] {
            padding: 10px;
            background: #f9fafb;
            cursor: pointer;
        }
        
        /* =======================
           BUTTON
        ======================= */
        .btn {
            width: 100%;
            padding: 15px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(
                135deg,
                #38bdf8,
                #7c3aed
            );
            box-shadow: 0 20px 45px rgba(124,58,237,0.55);
            transition: all 0.35s ease;
            margin-top: 10px;
        }
        
        .btn:hover {
            transform: translateY(-3px) scale(1.05);
            box-shadow: 0 30px 65px rgba(124,58,237,0.7);
        }
        
        /* =======================
           ANIMATIONS
        ======================= */
        @keyframes slideIn {
            0% {
                opacity: 0;
                transform: translateY(60px);
            }
            100% {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* =======================
           RESPONSIVE
        ======================= */
        @media (max-width: 768px) {
            .container { padding: 40px 20px; }
            .header h1 { font-size: 26px; }
            .card { padding: 32px 26px; }
            .logo { width: 90px; height: 90px; }
        }
    </style>
</head>
<body>

    <div class="container">
        <header class="header">
            <div class="logo-container">
                <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo">
            </div>
            <h1>Inscription du stagiaire</h1>
            <p>Veuillez remplir soigneusement le formulaire ci-dessous</p>
            
            <?php if(!empty($erreur_message)): ?>
                <p style="color: #ef4444; font-weight: bold; margin-top: 15px; background: rgba(0,0,0,0.2); padding: 10px; border-radius: 10px;"><?php echo $erreur_message; ?></p>
            <?php endif; ?>
        </header>

        <main class="main-content">
            <form id="formInscription" class="card form-stagiaire" action="INSCRPTION.php" method="POST" enctype="multipart/form-data">
         
                <div class="form-group">
                    <label for="nom">Nom</label>
                    <input type="text" id="nom" name="nom" required>
                </div>

                <div class="form-group">
                    <label for="prenom">Prénom</label>
                    <input type="text" id="prenom" name="prenom" required>
                </div>

                <div class="form-group">
                    <label for="email">Adresse e-mail</label>
                    <input type="email" id="email" name="email" required>
                </div>

                <div class="form-group">
                    <label for="sexe">Sexe</label>
                    <select id="sexe" name="sexe" required>
                        <option value="" disabled selected>Sélectionnez votre sexe</option>
                        <option value="M">Masculin</option>
                        <option value="F">Féminin</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="service">Service demandé</label>
                    <select id="service" name="service" required>
                        <option value="" disabled selected>Sélectionnez un service</option>
                        <option value="Informatique">Informatique</option>
                        <option value="Finance">Finance</option>
                        <option value="Marketing">Marketing</option>
                        <option value="Ressources Humaines">Ressources Humaines</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="filiere">Filière souhaiter</label>
                    <input type="text" id="filiere" name="filiere" required>
                </div>

                <div class="form-group">
                    <label for="lettre_pdf">Lettre de demande de stage (Format PDF unique)</label>
                    <input type="file" id="lettre_pdf" name="lettre_pdf" accept=".pdf" required>
                </div>

                <button type="submit" class="btn">S'inscrire</button>
            </form>
        </main>
    </div>

</body>
</html>