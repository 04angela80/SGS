<?php
session_start();

// 1. SÉCURITÉ : Connexion à la base de données
require_once __DIR__ . '/../../config/db.php';

// Si aucun stagiaire n'est connecté en session, on met l'ID 1 par défaut pour tes tests
$id_stagiaire = isset($_SESSION['id_stagiaire']) ? $_SESSION['id_stagiaire'] : 1;

$message_succes = "";

// 2. TRAITEMENT DU FORMULAIRE (ENREGISTREMENT EN BASE DE DONNÉES)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Mise à jour de la table principale 'stagiaires'
    $stmtBase = $bdd->prepare("UPDATE stagiaires SET nom = ?, prenom = ?, email = ? WHERE id = ?");
    $stmtBase->execute([
        $_POST['nom'], 
        $_POST['prenom'], 
        $_POST['email'], 
        $id_stagiaire
    ]);

    // --- GESTION DE L'UPLOAD DE LA PHOTO ---
    $nom_photo = null;
    
    // On vérifie si l'utilisateur a envoyé un fichier sans erreur
    if (isset($_FILES['photo_profil']) && $_FILES['photo_profil']['error'] === 0) {
        $dossier_destination = __DIR__ . '/uploads/';
        
        // Sécurisation du nom du fichier pour éviter les doublons (ex: 1_avatar.jpg)
        $extension = pathinfo($_FILES['photo_profil']['name'], PATHINFO_EXTENSION);
        $nom_photo = $id_stagiaire . '_' . time() . '.' . $extension;
        
        // Déplacement du fichier temporaire vers ton vrai dossier 'uploads'
        move_uploaded_file($_FILES['photo_profil']['tmp_name'], $dossier_destination . $nom_photo);
    }

    // Vérification si la ligne dans la table 'profil' existe déjà
    $check = $bdd->prepare("SELECT id, photo_profil FROM profil WHERE stagiaire_id = ?");
    $check->execute([$id_stagiaire]);
    $profilExiste = $check->fetch();

    if ($profilExiste) {
        // Si aucune nouvelle photo n'a été envoyée, on garde l'ancienne qui est déjà en BDD
        if ($nom_photo === null) {
            $nom_photo = $profilExiste['photo_profil'];
        }

        // Mise à jour de la table profil
        $sql = "UPDATE profil SET sexe = ?, date_naissance = ?, lieu_naissance = ?, nationalite = ?, etat_civil = ?, province_origine = ?, district = ?, territoire = ?, secteur = ?, commune_adresse = ?, contact_urgence_nom = ?, contact_urgence_tel = ?, contact_urgence_adresse = ?, diplome_type = ?, diplome_numero = ?, diplome_section = ?, diplome_ecole = ?, photo_profil = ? WHERE stagiaire_id = ?";
        $stmt = $bdd->prepare($sql);
        $stmt->execute([
            $_POST['sexe'], $_POST['date_naissance'], $_POST['lieu_naissance'], $_POST['nationalite'], $_POST['etat_civil'],
            $_POST['province_origine'], $_POST['district'], $_POST['territoire'], $_POST['secteur'], $_POST['commune_adresse'],
            $_POST['contact_urgence_nom'], $_POST['contact_urgence_tel'], $_POST['contact_urgence_adresse'],
            $_POST['diplome_type'], $_POST['diplome_numero'], $_POST['diplome_section'], $_POST['diplome_ecole'],
            $nom_photo, // On sauvegarde le nom de la photo ici
            $id_stagiaire
        ]);
    } else {
        // Création initiale du profil
        $sql = "INSERT INTO profil (stagiaire_id, sexe, date_naissance, lieu_naissance, nationalite, etat_civil, province_origine, district, territoire, secteur, commune_adresse, contact_urgence_nom, contact_urgence_tel, contact_urgence_adresse, diplome_type, diplome_numero, diplome_section, diplome_ecole, photo_profil) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $bdd->prepare($sql);
        $stmt->execute([
            $id_stagiaire, $_POST['sexe'], $_POST['date_naissance'], $_POST['lieu_naissance'], $_POST['nationalite'], $_POST['etat_civil'],
            $_POST['province_origine'], $_POST['district'], $_POST['territoire'], $_POST['secteur'], $_POST['commune_adresse'],
            $_POST['contact_urgence_nom'], $_POST['contact_urgence_tel'], $_POST['contact_urgence_adresse'],
            $_POST['diplome_type'], $_POST['diplome_numero'], $_POST['diplome_section'], $_POST['diplome_ecole'],
            $nom_photo
        ]);
    }
    $message_succes = "Profil et photo enregistrés avec succès !";
}

// 3. RÉCUPÉRATION DES DONNÉES ENREGISTRÉES
$stmtStag = $bdd->prepare("SELECT id, nom, prenom, email, filiere, statut FROM stagiaires WHERE id = ?");
$stmtStag->execute([$id_stagiaire]);
$stagiaire = $stmtStag->fetch(PDO::FETCH_ASSOC) ?: ['id' => '', 'nom' => '', 'prenom' => '', 'email' => '', 'filiere' => '', 'statut' => ''];

$stmtProf = $bdd->prepare("SELECT * FROM profil WHERE stagiaire_id = ?");
$stmtProf->execute([$id_stagiaire]);
$profil = $stmtProf->fetch(PDO::FETCH_ASSOC) ?: [];

// Détermination de la photo à afficher (si vide, on met un avatar par défaut)
$avatar_src = "https://cdn-icons-png.flaticon.com/512/847/847969.png";
if (!empty($profil['photo_profil']) && file_exists(__DIR__ . '/uploads/' . $profil['photo_profil'])) {
    $avatar_src = 'uploads/' . $profil['photo_profil'];
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Profil - Espace Stagiaire</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; display: flex; background: #f4f7fc; color: #334155; }
    .top-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(90deg, #0056b3, #003d80); color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 1000; }
    .top-header .header-title { font-size: 1.4em; font-weight: bold; margin: 0; letter-spacing: 1px; position: absolute; left: 50%; transform: translateX(-50%); }
    .top-header .header-icons { position: absolute; right: 20px; display: flex; gap: 15px; font-size: 22px; cursor: pointer; }
    .menu-btn { position: fixed; top: 15px; left: 15px; background: #0056b3; color: #fff; padding: 10px 15px; cursor: pointer; border-radius: 5px; z-index: 1000; }
    
    .sidebar { position: fixed; left: -250px; top: 0; width: 250px; height: 100vh; background: linear-gradient(180deg, #0056b3, #003d80); color: #fff; padding: 20px; transition: left 0.5s ease; z-index: 999; overflow-y: auto; display: flex; flex-direction: column; }
    .sidebar.show { left: 0; }
    .logo-container { margin-top: 30px; margin-bottom: 25px; text-align: center; }
    .logo { width: 90px; height: 90px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
    .sidebar ul { list-style: none; padding: 0; margin: 0; flex: 1; }
    .sidebar ul li { margin: 20px 0; }
    .sidebar ul li a { color: #fff; text-decoration: none; font-weight: bold; display: block; padding: 12px 15px; border-radius: 6px; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.2); }
    .logout { margin-top: auto; }

    .main-content { flex: 1; padding: 40px; margin-top: 60px; }
    .profile-layout { display: flex; gap: 30px; align-items: flex-start; margin-top: 20px; }

    .profile-sidebar-card { width: 280px; background: #ffffff; padding: 30px 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05); border: 1px solid #e2e8f0; text-align: center; }
    .avatar-wrapper { width: 130px; height: 130px; margin: 0 auto 15px auto; }
    .avatar { border-radius: 50%; border: 4px solid #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.15); width: 100%; height: 100%; object-fit: cover; background: #f1f5f9; }
    .profile-sidebar-card h2 { font-size: 1.4em; color: #0f172a; margin: 10px 0 5px 0; font-weight: 700; }
    .profile-sidebar-card .meta-code { font-size: 0.9em; color: #64748b; font-weight: 600; margin-bottom: 20px; display: block; }

    .completion-box { background: #f8fafc; border-radius: 8px; padding: 12px; border: 1px solid #e2e8f0; text-align: left; }
    .completion-label { font-size: 0.8em; font-weight: 700; color: #475569; display: flex; justify-content: space-between; margin-bottom: 6px; }
    .completion-bar { width: 100%; height: 6px; background: #e2e8f0; border-radius: 4px; overflow: hidden; }
    .completion-progress { width: 35%; height: 100%; background: #10b981; }

    #edit-form { flex: 1; background: #ffffff; padding: 35px; border-radius: 12px; box-shadow: 0 4px 15px rgba(15, 23, 42, 0.05); border: 1px solid #e2e8f0; }
    .form-section { margin-bottom: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 25px; }
    .form-section h3 { color: #0056b3; font-size: 1.1em; margin: 0 0 20px 0; display: flex; align-items: center; gap: 8px; font-weight: 700; }

    .fields-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .field-group { display: flex; flex-direction: column; }
    .field-group.full-width { grid-column: span 2; }
    .field-group label { font-size: 0.85em; font-weight: 600; color: #475569; margin-bottom: 6px; }

    #edit-form input, #edit-form select { width: 100%; padding: 10px 14px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 0.92em; color: #334155; box-sizing: border-box; }
    .file-drop-zone { border: 2px dashed #cbd5e1; padding: 15px; border-radius: 8px; text-align: center; background: #f8fafc; }
    #edit-form button[type="submit"] { background: #10b981; color: #fff; cursor: pointer; font-weight: bold; font-size: 1em; padding: 14px; border: none; border-radius: 8px; width: 100%; margin-top: 10px; display: inline-flex; align-items: center; justify-content: center; gap: 8px; }
    #edit-form button[type="submit"]:hover { background: #059669; }
    
    .alert-succes { background: #d1fae5; color: #065f46; padding: 15px; border-radius: 8px; font-weight: bold; margin-bottom: 20px; border: 1px solid #a7f3d0; text-align: center; }
  </style>
</head>
<body>

  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-cog"></i> Profil</h1>
    <div class="header-icons">
      <span class="bell"><i class="fas fa-bell"></i></span>
      <span class="user"><i class="fas fa-user-circle"></i></span>
    </div>
  </div>

  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <div id="sidebar" class="sidebar">
    <div class="logo-container"><img src="../../LOGO.jpeg" alt="Logo" class="logo"></div>
    <ul>
      <li><a href="stagiaire.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="taches.php"><i class="fas fa-tasks"></i> Mes tâches</a></li>
      <li><a href="rapport.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
      <li><a href="resultats.php"><i class="fas fa-chart-line"></i> Résultats</a></li>
      <li><a href="profil.php" class="active"><i class="fas fa-cog"></i> Profil</a></li>
      <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </div>

  <div class="main-content">
    
    <?php if(!empty($message_succes)): ?>
        <div class="alert-succes"><i class="fas fa-check-circle"></i> <?php echo $message_succes; ?></div>
    <?php endif; ?>

    <div class="profile-layout">
      
      <aside class="profile-sidebar-card">
        <div class="avatar-wrapper">
          <img id="avatar" src="<?php echo $avatar_src; ?>" alt="Photo" class="avatar">
        </div>
        <h2><?php echo htmlspecialchars(($stagiaire['prenom'] . ' ' . $stagiaire['nom']) ?: 'Stagiaire'); ?></h2>
        <span class="meta-code">ID Stagiaire : <?php echo htmlspecialchars($stagiaire['id'] ?: 'Non renseigné'); ?></span>
        
        <div class="completion-box">
          <div class="completion-label">
            <span>Complétion du profil</span>
            <span>35%</span>
          </div>
          <div class="completion-bar">
            <div class="completion-progress"></div>
          </div>
        </div>
      </aside>

      <form id="edit-form" method="POST" action="profil.php" enctype="multipart/form-data">
          
          <div class="form-section">
            <h3><i class="fas fa-user"></i> Informations personnelles</h3>
            <div class="fields-grid">
              <div class="field-group">
                <label>Nom complet *</label>
                <input type="text" name="nom" value="<?php echo htmlspecialchars($stagiaire['nom']); ?>" required>
              </div>
              <div class="field-group">
                <label>Prénom *</label>
                <input type="text" name="prenom" value="<?php echo htmlspecialchars($stagiaire['prenom']); ?>" required>
              </div>
              <div class="field-group">
                <label>Adresse e-mail *</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($stagiaire['email']); ?>" required>
              </div>
              <div class="field-group">
                <label>Filière affectée</label>
                <input type="text" value="<?php echo htmlspecialchars($stagiaire['filiere'] ?: 'Non assignée'); ?>" disabled style="background:#f1f5f9; color:#64748b;">
              </div>
              <div class="field-group">
                <label>Sexe</label>
                <select name="sexe">
                  <option value="">Sélectionner</option>
                  <option value="Féminin" <?php if(($profil['sexe']??'')=='Féminin') echo 'selected'; ?>>Féminin</option>
                  <option value="Masculin" <?php if(($profil['sexe']??'')=='Masculin') echo 'selected'; ?>>Masculin</option>
                </select>
              </div>
              <div class="field-group">
                <label>Date de naissance</label>
                <input type="date" name="date_naissance" value="<?php echo htmlspecialchars($profil['date_naissance']??''); ?>">
              </div>
              <div class="field-group">
                <label>Lieu de naissance</label>
                <input type="text" name="lieu_naissance" value="<?php echo htmlspecialchars($profil['lieu_naissance']??''); ?>" placeholder="Ville">
              </div>
              <div class="field-group">
                <label>Nationalité</label>
                <input type="text" name="nationalite" value="<?php echo htmlspecialchars($profil['nationalite']??''); ?>" placeholder="Pays">
              </div>
              <div class="field-group full-width">
                <label>État civil</label>
                <select name="etat_civil">
                  <option value="">Sélectionner</option>
                  <option value="Célibataire" <?php if(($profil['etat_civil']??'')=='Célibataire') echo 'selected'; ?>>Célibataire</option>
                  <option value="Marié(e)" <?php if(($profil['etat_civil']??'')=='Marié(e)') echo 'selected'; ?>>Marié(e)</option>
                </select>
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3><i class="fas fa-map-marker-alt"></i> Origine & Résidence</h3>
            <div class="fields-grid">
              <div class="field-group">
                <label>Province d'origine</label>
                <input type="text" name="province_origine" value="<?php echo htmlspecialchars($profil['province_origine']??''); ?>" placeholder="Province">
              </div>
              <div class="field-group">
                <label>District</label>
                <input type="text" name="district" value="<?php echo htmlspecialchars($profil['district']??''); ?>" placeholder="District">
              </div>
              <div class="field-group">
                <label>Territoire</label>
                <input type="text" name="territoire" value="<?php echo htmlspecialchars($profil['territoire']??''); ?>" placeholder="Territoire">
              </div>
              <div class="field-group">
                <label>Secteur</label>
                <input type="text" name="secteur" value="<?php echo htmlspecialchars($profil['secteur']??''); ?>" placeholder="Secteur">
              </div>
              <div class="field-group full-width">
                <label>Commune / Adresse actuelle</label>
                <input type="text" name="commune_adresse" value="<?php echo htmlspecialchars($profil['commune_adresse']??''); ?>" placeholder="Adresse complète">
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3><i class="fas fa-exclamation-triangle"></i> Contact d’urgence</h3>
            <div class="fields-grid">
              <div class="field-group">
                <label>Nom du contact</label>
                <input type="text" name="contact_urgence_nom" value="<?php echo htmlspecialchars($profil['contact_urgence_nom']??''); ?>" placeholder="Nom du tuteur">
              </div>
              <div class="field-group">
                <label>Téléphone d'urgence</label>
                <input type="text" name="contact_urgence_tel" value="<?php echo htmlspecialchars($profil['contact_urgence_tel']??''); ?>" placeholder="Téléphone">
              </div>
              <div class="field-group full-width">
                <label>Adresse du contact</label>
                <input type="text" name="contact_urgence_adresse" value="<?php echo htmlspecialchars($profil['contact_urgence_adresse']??''); ?>" placeholder="Adresse">
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3><i class="fas fa-graduation-cap"></i> Dernier diplôme obtenu</h3>
            <div class="fields-grid">
              <div class="field-group">
                <label>Type de diplôme</label>
                <input type="text" name="diplome_type" value="<?php echo htmlspecialchars($profil['diplome_type']??''); ?>" placeholder="Ex: Licence">
              </div>
              <div class="field-group">
                <label>Numéro de diplôme</label>
                <input type="text" name="diplome_numero" value="<?php echo htmlspecialchars($profil['diplome_numero']??''); ?>" placeholder="N°">
              </div>
              <div class="field-group">
                <label>Section / Option</label>
                <input type="text" name="diplome_section" value="<?php echo htmlspecialchars($profil['diplome_section']??''); ?>" placeholder="Option">
              </div>
              <div class="field-group">
                <label>Établissement / Université</label>
                <input type="text" name="diplome_ecole" value="<?php echo htmlspecialchars($profil['diplome_ecole']??''); ?>" placeholder="École">
              </div>
            </div>
          </div>

          <div class="form-section">
            <h3><i class="fas fa-image"></i> Photo d'identité</h3>
            <div class="field-group full-width">
              <div class="file-drop-zone">
                <i class="fas fa-cloud-upload-alt" style="font-size: 24px; color:#0056b3; margin-bottom:8px;"></i><br>
                <input type="file" name="photo_profil" accept="image/*">
              </div>
            </div>
          </div>

          <button type="submit"><i class="fas fa-save"></i> Enregistrer définitivement</button>
      </form>
    </div>
  </div>

  <script>
    function toggleMenu() {
      document.getElementById("sidebar").classList.toggle("show");
    }
  </script>
</body>
</html>