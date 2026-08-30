<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/notifications_moteur.php';

// Chargement de Dompdf pour la génération automatique de la lettre
if (file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
}

use Dompdf\Dompdf;
use Dompdf\Options;

$erreur_message = "";

// Charger les services depuis la BDD pour les listes déroulantes
$reqServices = $bdd->query(
    "SELECT id_service, nom_service, description FROM services ORDER BY description, nom_service"
);
$servicesData = $reqServices->fetchAll(PDO::FETCH_ASSOC);

// Construire la map : domaine → [liste de filières/nom_service]
$servicesByDomain = [];
foreach ($servicesData as $svc) {
    $servicesByDomain[$svc['description']][] = $svc['nom_service'];
}
ksort($servicesByDomain);

// Traitement du formulaire d'inscription
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nom        = isset($_POST['nom'])        ? trim($_POST['nom'])        : '';
    $prenom     = isset($_POST['prenom'])     ? trim($_POST['prenom'])     : '';
    $email      = isset($_POST['email'])      ? trim($_POST['email'])      : '';
    $sexe       = isset($_POST['sexe'])       ? trim($_POST['sexe'])       : '';
    $universite = isset($_POST['Universite']) ? trim($_POST['Universite']) : '';
    $service    = isset($_POST['service'])    ? trim($_POST['service'])    : '';
    $filiere    = isset($_POST['filiere'])    ? trim($_POST['filiere'])    : '';

    /**********************************************************************************
     * 1. VÉRIFICATION DU FICHIER REÇU :
     * Le formulaire exige la présence d'un fichier PDF (`lettre_pdf`).
     **********************************************************************************/
    if (!empty($nom) && !empty($prenom) && !empty($email) && !empty($universite) && !empty($service) && !empty($filiere) && isset($_FILES['lettre_pdf'])) {
        try {
            $checkEmail = $bdd->prepare("SELECT id FROM stagiaires WHERE email = ?");
            $checkEmail->execute([$email]);

            if ($checkEmail->rowCount() > 0) {
                $erreur_message = "Cette adresse e-mail est déjà utilisée.";
            } else {
                // Le fichier PDF transmis par l'étudiant est stocké temporairement par PHP dans $_FILES['lettre_pdf']
                $file      = $_FILES['lettre_pdf'];
                $fileError = $file['error'];
                $fileExt   = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if ($fileExt === 'pdf') {
                    if ($fileError === 0) {
                        
                        $dossier_destination = 'uploads/lettres/';
                        if (!is_dir($dossier_destination)) {
                            mkdir($dossier_destination, 0777, true);
                        }
                        
                        // Définition du nom final du fichier enregistré sur le serveur
                        $nouveau_nom_fichier        = "lettre_" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $nom)) . "_" . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $prenom)) . "_" . time() . ".pdf";
                        $chemin_complet_destination = $dossier_destination . $nouveau_nom_fichier;

                        // --- GÉNÉRATION DU CONTENU OFFICIEL DE LA LETTRE UNIVERSITAIRE ---
                        $nomComplet     = htmlspecialchars(strtoupper($nom) . ' ' . ucfirst($prenom));
                        $universiteMaj  = htmlspecialchars(strtoupper($universite));
                        $dateAujourdhui = date('d/m/Y');

                        $html_lettre = '
                        <!DOCTYPE html>
                        <html lang="fr">
                        <head>
                            <meta charset="UTF-8">
                            <title>Recommandation de Stage Académique</title>
                            <style>
                                @page { margin: 25px 35px; }
                                body { font-family: "Helvetica", "Arial", sans-serif; font-size: 12px; line-height: 1.5; color: #1e293b; }
                                
                                .header-universite { float: left; width: 52%; font-size: 11px; }
                                .header-destinataire { float: right; width: 42%; font-size: 11px; text-align: right; margin-top: 15px; }
                                .clearfix { clear: both; }
                                
                                .date-lieu { text-align: right; margin-top: 20px; margin-bottom: 15px; font-style: italic; font-size: 11px; }
                                .objet { font-weight: bold; font-size: 13px; margin-bottom: 20px; border-bottom: 2px solid #1e3a8a; padding-bottom: 4px; color: #1e3a8a; }
                                
                                .corps { text-align: justify; text-indent: 20px; margin-bottom: 12px; }
                                
                                /* Zone Signature et Tampon */
                                .footer-container { margin-top: 25px; width: 100%; }
                                .box-signature { float: right; width: 230px; text-align: center; }
                                .titre-doyen { font-weight: bold; font-size: 12px; margin-bottom: 5px; color: #0f172a; }
                                
                                /* Simulation du Tampon/Sceau Universitaire */
                                .stamp {
                                    width: 130px;
                                    height: 130px;
                                    border: 3px double #1e3a8a;
                                    border-radius: 50%;
                                    margin: 5px auto;
                                    padding: 5px;
                                    text-align: center;
                                    color: #1e3a8a;
                                    font-size: 8px;
                                    font-weight: bold;
                                    text-transform: uppercase;
                                    box-sizing: border-box;
                                    background: rgba(30, 58, 138, 0.03);
                                }
                                .stamp-inner {
                                    border: 1px dashed #1e3a8a;
                                    border-radius: 50%;
                                    height: 100%;
                                    display: flex;
                                    flex-direction: column;
                                    justify-content: center;
                                    align-items: center;
                                    padding: 4px;
                                }
                                .stamp-text-top { margin-top: 12px; font-size: 7px; }
                                .stamp-text-middle { margin: 6px 0; font-size: 9px; color: #b91c1c; }
                                .stamp-text-bottom { font-size: 7px; }
                                
                                .signature-script {
                                    font-family: "Courier", monospace;
                                    font-style: italic;
                                    font-weight: bold;
                                    font-size: 14px;
                                    color: #0f172a;
                                    margin-top: 5px;
                                }
                            </style>
                        </head>
                        <body>
                            <!-- ÉTABLISSEMENT EXPÉDITEUR -->
                            <div class="header-universite">
                                <strong>' . $universiteMaj . '</strong><br>
                                Direction des Affaires Académiques<br>
                                Secrétariat Général / Vicedoyen de la Faculté<br>
                                Email : contact@' . strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $universite)) . '.edu
                            </div>

                            <!-- DESTINATAIRE (ENTREPRISE D\'ACCUEIL) -->
                            <div class="header-destinataire">
                                <strong>À l\'attention de la Direction Générale</strong><br>
                                Service des Ressources Humaines & Stages<br>
                                Entreprise d\'Accueil
                            </div>

                            <div class="clearfix"></div>

                            <div class="date-lieu">
                                Fait le ' . $dateAujourdhui . '
                            </div>

                            <div class="objet">
                                Objet : Lettre de Recommandation et Demande de Stage Académique
                            </div>

                            <p class="corps">Madame, Monsieur le Directeur,</p>

                            <p class="corps">
                                La Direction de <strong>' . htmlspecialchars($universite) . '</strong> a l\'honneur de recommander auprès de votre bienveillante attention l\'étudiant(e) <strong>' . $nomComplet . '</strong>, régulièrement inscrit(e) au sein de notre établissement dans la filière <strong>' . htmlspecialchars($filiere) . '</strong>.
                            </p>

                            <p class="corps">
                                Dans le cadre de la finalisation de son cursus académique, notre établissement exige la réalisation d\'une immersion pratique en entreprise. À ce titre, nous sollicitons une opportunité de stage académique pour cet(te) étudiant(e) au sein de votre service <strong>' . htmlspecialchars($service) . '</strong>.
                            </p>

                            <p class="corps">
                                Durant son parcours au sein de notre université, <strong>' . $nomComplet . '</strong> a fait preuve de sé sérieux, d\'assiduité et d\'un grand sens des responsabilités. Nous sommes convaincus que son intégration au sein de vos équipes sera mutuellement bénéfique.
                            </p>

                            <p class="corps">
                                En vous remerciant par avance pour l\'accueil réservé à notre étudiant(e) et pour l\'appui apporté à sa formation professionnelle, nous vous prions d\'agréer, Madame, Monsieur le Directeur, l\'assurance de notre haute considération.
                            </p>

                            <!-- PIED DE PAGE : SIGNATURE DU DOYEN ET SCEAU OFFICIEL -->
                            <div class="footer-container">
                                <div class="box-signature">
                                    <div class="titre-doyen">Pour le Doyen de la Faculté,</div>
                                    <div class="titre-doyen" style="font-size: 10px; font-weight: normal;">Le Secrétaire Général / Vicedoyen</div>
                                    
                                    <!-- Cachet / Sceau simulé en HTML/CSS -->
                                    <div class="stamp">
                                        <div class="stamp-inner">
                                            <div class="stamp-text-top">★ ' . $universiteMaj . ' ★</div>
                                            <div class="stamp-text-middle">LE DOYEN</div>
                                            <div class="stamp-text-bottom">AFFAIRES ACADÉMIQUES</div>
                                        </div>
                                    </div>
                                    
                                    <div class="signature-script">Prof. Dr. A. MENDOZA</div>
                                </div>
                                <div class="clearfix"></div>
                            </div>
                        </body>
                        </html>';

                        $pdf_genere_avec_succes = false;

                        /**********************************************************************************
                         * 2. OÙ LE FICHIER DE L'ÉTUDIANT EST REMPLACÉ PAR LA LETTRE DU DOYEN (DOMPDF) :
                         * Au lieu de sauvegarder le fichier téléversé, le code ci-dessous génère le PDF
                         * de la lettre du Doyen et l'écrit DIRECTEMENT au chemin $chemin_complet_destination.
                         **********************************************************************************/
                        if (class_exists('Dompdf\Dompdf')) {
                            try {
                                $options = new Options();
                                $options->set('isHtml5ParserEnabled', true);
                                $dompdf = new Dompdf($options);
                                $dompdf->loadHtml($html_lettre);
                                $dompdf->setPaper('A4', 'portrait');
                                $dompdf->render();

                                // ACCÈS CLÉ : file_put_contents génère la lettre Dompdf à l'emplacement final.
                                // La lettre originale de l'étudiant transmise par le formulaire N'EST PAS enregistrée.
                                file_put_contents($chemin_complet_destination, $dompdf->output());
                                
                                $pdf_genere_avec_succes = true; // Confirmation que le PDF du Doyen est créé
                            } catch (Exception $ePdf) {
                                $pdf_genere_avec_succes = false;
                            }
                        }

                        /**********************************************************************************
                         * 3. REPLI (SEULEMENT EN CAS D'ÉCHEC DE DOMPDF) :
                         * La fonction move_uploaded_file() (qui déplace le fichier initial de l'étudiant)
                         * ne s'exécute QUE SI la génération Dompdf a échoué ($pdf_genere_avec_succes == false).
                         * Comme Dompdf fonctionne correctement, ce bloc est IGNORÉ.
                         **********************************************************************************/
                        if (!$pdf_genere_avec_succes) {
                            move_uploaded_file($file['tmp_name'], $chemin_complet_destination);
                        }

                        // Insertion dans la Base de Données du chemin vers la lettre générée
                        $insert = $bdd->prepare(
                            "INSERT INTO stagiaires (nom, prenom, email, sexe, universite, service, filiere, lettre, statut)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'En attente')"
                        );
                        $resultat = $insert->execute([$nom, $prenom, $email, $sexe, $universite, $service, $filiere, $chemin_complet_destination]);

                        if ($resultat) {
                            $id_nouveau = $bdd->lastInsertId();

                            // Notification à l'admin
                            ajouterNotification(
                                $bdd, 1, 'admin', 'Nouvelle Inscription',
                                'Le stagiaire ' . htmlspecialchars($prenom) . ' ' . htmlspecialchars($nom)
                                . ' (' . htmlspecialchars($universite) . ') souhaite rejoindre le service ' . htmlspecialchars($service)
                                . ' — Filière : ' . htmlspecialchars($filiere) . '.',
                                'non_lu'
                            );

                            // Notification de confirmation au stagiaire
                            ajouterNotification(
                                $bdd, $id_nouveau, 'stagiaire', 'Inscription reçue',
                                'Votre dossier a bien été enregistré pour le service '
                                . htmlspecialchars($service) . ' (Filière : ' . htmlspecialchars($filiere)
                                . '). L\'administration vous contactera dès que votre dossier sera traité.',
                                'non_lu'
                            );

                            header("Location: attente.php?id=" . $id_nouveau);
                            exit();
                        } else {
                            $erreur_message = "Erreur lors de l'enregistrement en base de données.";
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
        * { margin: 0; padding: 0; box-sizing: border-box; }

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

        .container {
            width: 100%;
            max-width: 980px;
            background: linear-gradient(135deg, #38bdf8, #7c3aed, #c084fc);
            border-radius: 36px;
            padding: 50px 30px;
            box-shadow: 0 45px 90px rgba(76, 29, 149, 0.35);
            animation: slideIn 1s ease forwards;
        }

        .header {
            text-align: center;
            margin-bottom: 40px;
            color: #ffffff;
        }

        .logo-container { margin-bottom: 20px; }

        .logo {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 50%;
            background: #ffffff;
            padding: 8px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.35);
        }

        .header h1 { font-size: 32px; font-weight: 800; margin-bottom: 10px; }
        .header p  { font-size: 15px; opacity: 0.92; }

        .main-content { display: flex; justify-content: center; }

        .card {
            width: 100%;
            max-width: 520px;
            background: rgba(255,255,255,0.96);
            border-radius: 30px;
            padding: 42px 36px;
            box-shadow: 0 30px 65px rgba(0,0,0,0.28);
        }

        .form-group {
            display: flex;
            flex-direction: column;
            margin-bottom: 18px;
        }

        .form-group label { font-weight: 600; margin-bottom: 6px; color: #374151; }

        .form-group input,
        .form-group select {
            padding: 13px 16px;
            border-radius: 14px;
            border: 2px solid #e5e7eb;
            font-size: 14px;
            transition: all 0.3s ease;
            font-family: inherit;
            background: #fff;
        }

        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: #7c3aed;
            box-shadow: 0 0 0 4px rgba(124,58,237,0.25);
        }

        .form-group select:disabled {
            background: #f3f4f6;
            color: #9ca3af;
            cursor: not-allowed;
            border-color: #e5e7eb;
        }

        .filiere-hint {
            font-size: 12px;
            color: #6b7280;
            margin-top: 5px;
            font-style: italic;
        }

        .form-group input[type="file"] { padding: 10px; background: #f9fafb; cursor: pointer; }

        .btn {
            width: 100%;
            padding: 15px;
            border-radius: 50px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            color: #ffffff;
            background: linear-gradient(135deg, #38bdf8, #7c3aed);
            box-shadow: 0 20px 45px rgba(124,58,237,0.55);
            transition: all 0.35s ease;
            margin-top: 10px;
        }

        .btn:hover { transform: translateY(-3px) scale(1.05); box-shadow: 0 30px 65px rgba(124,58,237,0.7); }

        @keyframes slideIn {
            0%   { opacity: 0; transform: translateY(60px); }
            100% { opacity: 1; transform: translateY(0); }
        }

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

        <?php if (!empty($erreur_message)): ?>
            <p style="color:#ef4444; font-weight:bold; margin-top:15px; background:rgba(0,0,0,0.2); padding:10px; border-radius:10px;">
                <?php echo $erreur_message; ?>
            </p>
        <?php endif; ?>
    </header>

    <main class="main-content">
        <form id="formInscription" class="card form-stagiaire"
              action="INSCRPTION.php" method="POST" enctype="multipart/form-data">

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
                <label for="Universite">Université</label>
                <input type="text" id="Universite" name="Universite" required>
            </div>

            <div class="form-group">
                <label for="service">Service demandé</label>
                <select id="service" name="service" required onchange="updateFilieres()">
                    <option value="" disabled selected>Sélectionnez un service</option>
                    <?php foreach (array_keys($servicesByDomain) as $domaine): ?>
                        <option value="<?php echo htmlspecialchars($domaine); ?>">
                            <?php echo htmlspecialchars($domaine); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="filiere">Quel département aimerez-vous adhéré?</label>
                <select id="filiere" name="filiere" required disabled>
                    <option value="" disabled selected>Choisissez d'abord un service</option>
                </select>
                <span class="filiere-hint">Les filières disponibles s'affichent selon le service choisi.</span>
            </div>

            <div class="form-group">
                <label for="lettre_pdf">Lettre de demande de stage (Format PDF unique)</label>
                <input type="file" id="lettre_pdf" name="lettre_pdf" accept=".pdf" required>
            </div>

            <button type="submit" class="btn">S'inscrire</button>
        </form>
    </main>
</div>

<script>
    const servicesByDomain = <?php echo json_encode($servicesByDomain, JSON_UNESCAPED_UNICODE); ?>;

    function updateFilieres() {
        const serviceSelect  = document.getElementById('service');
        const filiereSelect  = document.getElementById('filiere');
        const domaine        = serviceSelect.value;

        filiereSelect.innerHTML = '';

        if (domaine && servicesByDomain[domaine] && servicesByDomain[domaine].length > 0) {
            const defaultOpt    = document.createElement('option');
            defaultOpt.value    = '';
            defaultOpt.disabled = true;
            defaultOpt.selected = true;
            defaultOpt.textContent = 'Sélectionnez votre filière';
            filiereSelect.appendChild(defaultOpt);

            servicesByDomain[domaine].forEach(function(nom) {
                const opt       = document.createElement('option');
                opt.value       = nom;
                opt.textContent = nom;
                filiereSelect.appendChild(opt);
            });

            filiereSelect.disabled = false;
        } else {
            const defaultOpt    = document.createElement('option');
            defaultOpt.value    = '';
            defaultOpt.disabled = true;
            defaultOpt.selected = true;
            defaultOpt.textContent = 'Choisissez d\'abord un service';
            filiereSelect.appendChild(defaultOpt);
            filiereSelect.disabled = true;
        }
    }
</script>

</body>
</html>