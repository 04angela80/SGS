<?php
// 1. Connexion à la base de données
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/notifications_moteur.php';

$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_task') {
        $stagiaire_id = isset($_POST['stagiaire_id']) ? intval($_POST['stagiaire_id']) : 0;
        $description = trim($_POST['description'] ?? '');

        if ($stagiaire_id <= 0 || $description === '') {
            $messages[] = 'Veuillez sélectionner un stagiaire et décrire la tâche à ajouter.';
        } else {
            try {
                $stmt = $bdd->prepare("INSERT INTO taches (stagiaire_id, description, statut, date_attribution) VALUES (:stagiaire_id, :description, 'en_cours', NOW())");
                $stmt->execute([
                    'stagiaire_id' => $stagiaire_id,
                    'description' => $description,
                ]);

                ajouterNotification(
                    $bdd,
                    $stagiaire_id,
                    'stagiaire',
                    'Nouvelle tâche attribuée',
                    'Une nouvelle tâche vient de vous être attribuée : "' . htmlspecialchars($description, ENT_QUOTES, 'UTF-8') . '".',
                    'non_lu'
                );

                header('Location: suivi.php?msg=' . urlencode('Tâche ajoutée avec succès.'));
                exit();
            } catch (PDOException $e) {
                $messages[] = 'Erreur lors de l’ajout de la tâche : ' . $e->getMessage();
            }
        }
    } elseif ($action === 'add_presence') {
        $stagiaire_id = isset($_POST['stagiaire_id']) ? intval($_POST['stagiaire_id']) : 0;
        $date_fiche = trim($_POST['date_fiche'] ?? '');
        $etat_presence = trim($_POST['etat_presence'] ?? 'présent');
        $commentaire_admin = trim($_POST['commentaire_admin'] ?? '');

        if ($stagiaire_id <= 0 || $date_fiche === '' || ($etat_presence !== 'présent' && $etat_presence !== 'absent')) {
            $messages[] = 'Veuillez sélectionner un stagiaire, une date et un état de présence valide.';
        } else {
            try {
                $stmt = $bdd->prepare("INSERT INTO suivi (stagiaire_id, date_fiche, etat_presence, commentaire_admin) VALUES (:stagiaire_id, :date_fiche, :etat_presence, :commentaire_admin)");
                $stmt->execute([
                    'stagiaire_id' => $stagiaire_id,
                    'date_fiche' => $date_fiche,
                    'etat_presence' => $etat_presence,
                    'commentaire_admin' => $commentaire_admin ?: null,
                ]);

                $libelleEtat = ($etat_presence === 'présent' || $etat_presence === 'present') ? 'Présent' : 'Absent';
                $messageCommentaire = $commentaire_admin ? ' Observation : "' . htmlspecialchars($commentaire_admin, ENT_QUOTES, 'UTF-8') . '".' : '';
                ajouterNotification(
                    $bdd,
                    $stagiaire_id,
                    'stagiaire',
                    'Nouveau pointage enregistré',
                    'Votre présence du ' . date('d/m/Y', strtotime($date_fiche)) . ' a été enregistrée comme ' . $libelleEtat . '.' . $messageCommentaire,
                    'non_lu'
                );

                header('Location: suivi.php?msg=' . urlencode('Pointage ajouté avec succès.'));
                exit();
            } catch (PDOException $e) {
                $messages[] = 'Erreur lors de l’ajout du pointage : ' . $e->getMessage();
            }
        }
    }
}

if (isset($_GET['msg']) && trim($_GET['msg']) !== '') {
    $messages[] = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
}

try {
    $sqlStg = "SELECT s.id, s.nom, s.prenom, s.filiere, s.service AS service_demande, ser.nom_service, ser.nom_encadrant 
               FROM stagiaires s
               LEFT JOIN services ser ON COALESCE(s.id_service_affecte, 0) = ser.id_service
               WHERE LOWER(s.statut) = 'validé' OR LOWER(s.statut) = 'valide'
               ORDER BY s.nom ASC";
    $stmtStg = $bdd->query($sqlStg);
    $stagiaires = $stmtStg->fetchAll(PDO::FETCH_ASSOC);

    $sqlT = "SELECT stagiaire_id, description, statut, date_attribution FROM taches ORDER BY date_attribution DESC";
    $allTaches = $bdd->query($sqlT)->fetchAll(PDO::FETCH_ASSOC);

    $taches_organisees = [];
    foreach ($allTaches as $t) {
        $id_stg = $t['stagiaire_id'];
        $prefixe = ($t['statut'] === 'terminé' || $t['statut'] === 'Terminé') ? '🟢 [Terminé] ' : '🟡 [En cours] ';
        $taches_organisees[$id_stg][] = $prefixe . $t['description'] . " (du " . date('d/m/Y', strtotime($t['date_attribution'])) . ")";
    }

    $sqlS = "SELECT stagiaire_id, date_fiche, etat_presence, commentaire_admin FROM suivi ORDER BY date_fiche DESC";
    $allSuivi = $bdd->query($sqlS)->fetchAll(PDO::FETCH_ASSOC);

    $presences_organisees = [];
    $absences_organisees = [];
    $total_presents_global = 0;
    $total_absents_global = 0;

    foreach ($allSuivi as $s) {
        $id_stg = $s['stagiaire_id'];
        $date_f = date('d/m/Y', strtotime($s['date_fiche']));
        $com = !empty($s['commentaire_admin']) ? " - Obs: " . $s['commentaire_admin'] : "";
        
        $etat = strtolower(trim($s['etat_presence']));
        if ($etat === 'présent' || $etat === 'present') {
            $presences_organisees[$id_stg][] = "📅 Présent le " . $date_f . $com;
            $total_presents_global++;
        } else {
            $absences_organisees[$id_stg][] = "❌ Absent le " . $date_f . $com;
            $total_absents_global++;
        }
    }

} catch (PDOException $e) {
    die("Erreur de synchronisation BDD : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Administration - Suivi Général</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --bg-main: #f0f8ff; /* On garde ton fond bleu azur très léger d'origine */
      --primary: #0056b3;
      --primary-dark: #003d80;
      --accent: #2563eb;
      --accent-light: #eff6ff;
      --text-main: #334155;
      --text-muted: #64748b;
      --border-color: #cbd5e1;
      --success: #10b981;
      --danger: #ef4444;
    }

    body { 
      margin: 0; 
      font-family: 'Segoe UI', Arial, sans-serif; 
      background: var(--bg-main); 
      color: var(--text-main);
    }
    
    /* === HEADER SUPERIEUR D'ORIGINE === */
    .top-header { 
      position: fixed; 
      top: 0; 
      left: 0; 
      right: 0; 
      height: 60px; 
      background: linear-gradient(90deg, var(--primary), var(--primary-dark)); 
      color: #fff; 
      display: flex; 
      align-items: center; 
      justify-content: center; 
      padding: 0 20px; 
      box-shadow: 0 2px 8px rgba(0,0,0,0.2); 
      z-index: 1000; 
    }
    .top-header .header-title { 
      font-size: 1.4em; 
      font-weight: bold; 
      margin: 0; 
      position: absolute; 
      left: 50%; 
      transform: translateX(-50%); 
    }
    
    /* === BOUTON MENU D'ORIGINE === */
    .menu-btn { 
      position: fixed; 
      top: 13px; 
      left: 15px; 
      background: rgba(255, 255, 255, 0.15); 
      color: #fff; 
      padding: 8px 14px; 
      cursor: pointer; 
      border-radius: 5px; 
      z-index: 1001; 
      display: inline-flex; 
      align-items: center; 
      gap: 6px; 
      font-weight: bold;
      font-size: 0.9em;
      border: 1px solid rgba(255, 255, 255, 0.25);
      transition: background 0.2s;
    }
    .menu-btn:hover {
      background: rgba(255, 255, 255, 0.3);
    }

    /* === SIDEBAR COULISSANTE D'ORIGINE === */
    .sidebar { 
      position: fixed; 
      left: -250px; 
      top: 0; 
      width: 250px; 
      height: 100vh; 
      background: linear-gradient(180deg, var(--primary), var(--primary-dark)); 
      color: #fff; 
      padding: 20px; 
      box-sizing: border-box;
      transition: left 0.4s cubic-bezier(0.4, 0, 0.2, 1); 
      z-index: 999; 
      overflow-y: auto; 
      display: flex; 
      flex-direction: column; 
    }
    .sidebar.show { left: 0; }
    
    .logo-container { margin-top: 50px; margin-bottom: 25px; text-align: center; }
    .logo { width: 90px; height: 90px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); object-fit: cover; }
    
    .sidebar ul { list-style: none; padding: 0; margin: 0; }
    .sidebar ul li { margin: 15px 0; }
    .sidebar ul li a { 
      color: #fff; 
      text-decoration: none; 
      font-weight: bold; 
      display: flex; 
      align-items: center; 
      padding: 12px 15px; 
      border-radius: 6px; 
      transition: background 0.2s;
    }
    .sidebar ul li a i { margin-right: 10px; font-size: 18px; }
    .sidebar ul li a:hover, .sidebar ul li a.active { 
      background: rgba(255,255,255,0.2); 
    }

    /* === CONTENU GENERAL AJUSTE AVEC MARGE SUPERIEURE === */
    .container { 
      padding: 100px 30px 40px 30px; 
      max-width: 1200px; 
      margin: 0 auto; 
      box-sizing: border-box;
    }
    
    h2 { 
      color: var(--primary); 
      margin-top: 0;
      margin-bottom: 6px; 
      font-size: 1.7em; 
      font-weight: 700;
    }
    .page-subtitle {
      margin: 0 0 30px 0;
      color: var(--text-muted);
      font-size: 0.95em;
    }

    /* === FLASH MESSAGES === */
    .flash-box { 
      background: var(--accent-light); 
      border: 1px solid #93c5fd; 
      color: #1d4ed8; 
      padding: 14px 20px; 
      border-radius: 10px; 
      margin-bottom: 24px;
      font-size: 0.95em;
      display: flex;
      align-items: center;
      gap: 10px;
      box-shadow: 0 4px 12px rgba(59,130,246,0.05);
    }

    /* === CARTES DE STATISTIQUES HARMONIEUSES === */
    .stats-grid { 
      display: grid; 
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
      gap: 24px; 
      margin-bottom: 35px; 
    }
    .card-stat { 
      background: #fff; 
      padding: 20px 24px; 
      border-radius: 12px; 
      box-shadow: 0 4px 15px rgba(0,0,0,0.04);
      border: 1px solid rgba(0,0,0,0.02);
      display: flex;
      align-items: center;
      justify-content: space-between;
    }
    .card-stat-info h4 { margin: 0; color: var(--text-muted); font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; }
    .card-stat-info p { margin: 6px 0 0 0; font-size: 1.7em; font-weight: 700; color: var(--text-main); }
    .card-stat-icon { width: 44px; height: 44px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 18px; }
    .card-stat-icon.presence { background: #ecfdf5; color: var(--success); }
    .card-stat-icon.absence { background: #fef2f2; color: var(--danger); }

    /* === TABLEAU CONTENANT ET STYLE === */
    .table-container {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      overflow: hidden;
      margin-bottom: 40px;
      border: 1px solid rgba(0,0,0,0.03);
    }
    .suivi-table { width: 100%; border-collapse: collapse; text-align: left; }
    .suivi-table th { background: #f8fafc; color: var(--text-muted); font-weight: 600; font-size: 0.85em; text-transform: uppercase; padding: 16px 20px; border-bottom: 1px solid #e2e8f0; }
    .suivi-table td { padding: 16px 20px; border-bottom: 1px solid #f1f5f9; font-size: 0.95em; vertical-align: middle; }
    .suivi-table tr:last-child td { border-bottom: none; }
    .suivi-table tr:hover td { background-color: #fafbfc; }
    
    .stg-name { font-weight: 600; color: #0f172a; }
    .stg-filiere { color: var(--text-muted); font-size: 0.9em; }
    .encadrant-badge { display: inline-flex; align-items: center; gap: 6px; font-size: 0.9em; font-weight: 500; color: #475569; }

    /* === BOUTONS ACTIONS CELLULES === */
    .actions-cell { display: flex; gap: 8px; flex-wrap: wrap; }
    .btn-action { 
      background: #f8fafc; 
      color: var(--text-main); 
      border: 1px solid #e2e8f0; 
      padding: 8px 14px; 
      border-radius: 6px; 
      cursor: pointer; 
      display: inline-flex; 
      align-items: center; 
      gap: 6px; 
      font-weight: 600; 
      font-size: 0.85em; 
      transition: all 0.2s; 
    }
    .btn-action:hover { background: #f1f5f9; border-color: #cbd5e1; }
    .btn-action i { color: var(--primary); }
    .btn-action.btn-presence i { color: var(--success); }
    .btn-action.btn-absence i { color: var(--danger); }

    /* === FORMULAIRES ACTION BLOCS === */
    .admin-actions { 
      display: grid; 
      grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); 
      gap: 30px; 
    }
    .action-card { 
      background: white; 
      padding: 26px; 
      border-radius: 12px; 
      box-shadow: 0 4px 20px rgba(0,0,0,0.05);
      border: 1px solid rgba(0,0,0,0.02);
    }
    .action-card h3 { margin: 0 0 20px 0; font-size: 1.15em; color: #0f172a; font-weight: 600; display: flex; align-items: center; gap: 8px; }
    .action-card label { display: block; margin-bottom: 6px; color: var(--text-main); font-weight: 600; font-size: 0.9em; }
    
    .form-group { margin-bottom: 16px; }
    .action-card select,
    .action-card textarea,
    .action-card input[type="date"] { 
      width: 100%; 
      padding: 10px 14px; 
      border-radius: 8px; 
      border: 1px solid #cbd5e1; 
      font-size: 0.95em; 
      color: #0f172a; 
      box-sizing: border-box; 
      background-color: #fff;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .action-card select:focus,
    .action-card textarea:focus,
    .action-card input[type="date"]:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 3px rgba(0, 86, 179, 0.1);
    }
    .radio-group { display: flex; gap: 20px; padding: 4px 0; }
    .radio-group label { display: flex; align-items: center; gap: 6px; cursor: pointer; font-weight: 500; }
    
    .btn-submit {
      width: 100%;
      padding: 12px;
      border: none;
      border-radius: 8px;
      color: white;
      font-weight: 600;
      font-size: 0.95em;
      cursor: pointer;
      transition: opacity 0.2s;
      margin-top: 8px;
    }
    .btn-submit:hover { opacity: 0.9; }
    .btn-submit.task { background: var(--primary); }
    .btn-submit.presence { background: var(--success); }

    /* === DIALOG MODALE NETTOYEE === */
    dialog { 
      border: none; 
      border-radius: 16px; 
      padding: 26px; 
      width: 500px; 
      max-width: 90%; 
      box-shadow: 0 20px 25px -5px rgba(0,0,0,0.15); 
      background: #ffffff; 
    }
    dialog::backdrop { background: rgba(0, 0, 0, 0.4); backdrop-filter: blur(3px); }
    
    .modal-header { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; }
    .modal-header h3 { margin: 0; font-size: 1.2em; color: var(--primary); display: flex; align-items: center; gap: 8px; }
    .close-btn { cursor: pointer; font-size: 20px; color: var(--text-muted); }
    .close-btn:hover { color: #000; }
    
    .modal-subheader { font-size: 0.9em; color: var(--text-muted); margin-bottom: 16px; padding-bottom: 10px; border-bottom: 1px solid #e2e8f0; }
    .modal-subheader span { font-weight: 600; color: #0f172a; }

    dialog ul { list-style: none; padding: 0; margin: 0; max-height: 280px; overflow-y: auto; }
    dialog ul li { background: #f8fafc; margin-bottom: 8px; padding: 12px 14px; border-radius: 8px; font-size: 0.9em; border-left: 4px solid var(--primary); color: var(--text-main); line-height: 1.5; }
    dialog ul li.no-data { border-left-color: var(--text-muted); color: var(--text-muted); font-style: italic; background: #fafafa; }
  </style>
</head>
<body>

  <!-- HEADER SUPERIEUR D'ORIGINE -->
  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-thumbtack"></i> Suivi des tâches & Encadrement</h1>
  </div>

  <!-- BOUTON MENU D'ORIGINE -->
  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <!-- SIDEBAR FIXE D'ORIGINE -->
  <aside class="sidebar" id="sidebar">
    <div class="logo-container">
      <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo">
    </div>
    <ul>
      <li><a href="liste.php" ><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="evaluations.html"><i class="fas fa-chart-bar"></i> Évaluation & Résultats</a></li>
      <li><a href="suivi.php"class="active"><i class="fas fa-thumbtack"></i> Suivi des taches</a></li>
      <li><a href="rapport.php"><i class="fas fa-thumbtack"></i> Rapports</a></li>
      <li><a href="SERVICE.php"><i class="fas fa-tools"></i> Services</a></li>
      <li class="logout"><a href="../../public/index.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </aside>

  <!-- CONTENU RE-HARMONISÉ -->
  <main class="container">
    
    <h2>Contrôle Administratif des Objectifs</h2>
    <p class="page-subtitle">Pilotez l'activité et validez la présence en temps réel des stagiaires actifs.</p>

    <?php if (!empty($messages)): ?>
      <?php foreach ($messages as $message): ?>
        <div class="flash-box">
          <i class="fas fa-info-circle"></i>
          <span><?php echo htmlspecialchars($message); ?></span>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- STATS GLOBAL -->
    <section class="stats-grid">
      <div class="card-stat">
        <div class="card-stat-info">
          <h4>Présences Cumulées (Système)</h4>
          <p><?php echo $total_presents_global; ?> Jours</p>
        </div>
        <div class="card-stat-icon presence">
          <i class="fas fa-calendar-check"></i>
        </div>
      </div>
      <div class="card-stat">
        <div class="card-stat-info">
          <h4>Absences Cumulées (Système)</h4>
          <p><?php echo $total_absents_global; ?> Jours</p>
        </div>
        <div class="card-stat-icon absence">
          <i class="fas fa-calendar-times"></i>
        </div>
      </div>
    </section>

    <!-- VRAI TABLEAU EPURÉ ET MODERNE -->
    <div class="table-container">
      <table class="suivi-table">
        <thead>
          <tr>
            <th>Stagiaire</th>
            <th>Filière</th>
            <th>Encadrant Responsable</th>
            <th style="width: 360px;">Actions de Suivi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($stagiaires)): ?>
            <tr><td colspan="4" style="color: var(--text-muted); text-align: center; padding: 30px;">Aucun stagiaire validé actif dans le système.</td></tr>
          <?php else: ?>
            <?php foreach($stagiaires as $stg): ?>
              <?php 
                $id_stg = $stg['id'];
                $taches_js = isset($taches_organisees[$id_stg]) ? $taches_organisees[$id_stg] : [];
                $presences_js = isset($presences_organisees[$id_stg]) ? $presences_organisees[$id_stg] : [];
                $absences_js = isset($absences_organisees[$id_stg]) ? $absences_organisees[$id_stg] : [];
                
                $nom_complet = htmlspecialchars(strtoupper($stg['nom']) . " " . $stg['prenom'], ENT_QUOTES, 'UTF-8');
                
                if (!empty($stg['nom_encadrant'])) {
                    $encadrant_name = "M./Mme " . htmlspecialchars($stg['nom_encadrant']) . " (" . htmlspecialchars($stg['nom_service']) . ")";
                } else {
                    $encadrant_name = "En attente d'affectation (" . htmlspecialchars($stg['service_demande']) . ")";
                }
              ?>
              <tr>
                <td>
                  <div class="stg-name"><?php echo $nom_complet; ?></div>
                </td>
                <td><span class="stg-filiere"><?php echo htmlspecialchars($stg['filiere']); ?></span></td>
                <td>
                  <span class="encadrant-badge" style="<?php echo empty($stg['nom_encadrant']) ? 'color: var(--text-muted); font-style: italic;' : ''; ?>">
                    <i class="fas fa-user-tie" style="color: #a4b5cb;"></i> <?php echo $encadrant_name; ?>
                  </span>
                </td>
                <td>
                  <div class="actions-cell">
                    <button class="btn-action" onclick="ouvrirModale('<?php echo addslashes($nom_complet); ?>', 'Missions & Objectifs', <?php echo htmlspecialchars(json_encode($taches_js), ENT_QUOTES, 'UTF-8'); ?>, 'fa-tasks', 'var(--primary)')">
                      <i class="fas fa-tasks"></i> Tâches
                    </button>
                    <button class="btn-action btn-presence" onclick="ouvrirModale('<?php echo addslashes($nom_complet); ?>', 'Historique des Présences', <?php echo htmlspecialchars(json_encode($presences_js), ENT_QUOTES, 'UTF-8'); ?>, 'fa-calendar-check', 'var(--success)')">
                      <i class="fas fa-calendar-check"></i> Présences
                    </button>
                    <button class="btn-action btn-absence" onclick="ouvrirModale('<?php echo addslashes($nom_complet); ?>', 'Historique des Absences', <?php echo htmlspecialchars(json_encode($absences_js), ENT_QUOTES, 'UTF-8'); ?>, 'fa-calendar-times', 'var(--danger)')">
                      <i class="fas fa-calendar-times"></i> Absences
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Saisie Formulaires -->
    <section class="admin-actions">
      
      <!-- AJOUT TACHE -->
      <div class="action-card">
        <h3><i class="fas fa-plus-circle" style="color: var(--primary);"></i> Ajouter une tâche</h3>
        <form method="POST">
          <input type="hidden" name="action" value="add_task">
          
          <div class="form-group">
            <label for="task-stagiaire">Stagiaire concerné</label>
            <select id="task-stagiaire" name="stagiaire_id" required>
              <option value="">Sélectionnez un stagiaire validé</option>
              <?php foreach ($stagiaires as $stg): ?>
                <option value="<?php echo $stg['id']; ?>"><?php echo htmlspecialchars(strtoupper($stg['nom']) . ' ' . $stg['prenom']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="description">Description de la tâche</label>
            <textarea id="description" name="description" rows="3" required placeholder="Ex: Participer à la réunion de projet..."></textarea>
          </div>

          <button type="submit" class="btn-submit task">Ajouter la tâche</button>
        </form>
      </div>

      <!-- AJOUT POINTAGE -->
      <div class="action-card">
        <h3><i class="fas fa-check-square" style="color: var(--success);"></i> Ajouter un pointage</h3>
        <form method="POST">
          <input type="hidden" name="action" value="add_presence">
          
          <div class="form-group">
            <label for="presence-stagiaire">Stagiaire concerné</label>
            <select id="presence-stagiaire" name="stagiaire_id" required>
              <option value="">Sélectionnez un stagiaire validé</option>
              <?php foreach ($stagiaires as $stg): ?>
                <option value="<?php echo $stg['id']; ?>"><?php echo htmlspecialchars(strtoupper($stg['nom']) . ' ' . $stg['prenom']); ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <div class="form-group">
            <label for="date_fiche">Date</label>
            <input type="date" id="date_fiche" name="date_fiche" required>
          </div>

          <div class="form-group">
            <label>État de présence</label>
            <div class="radio-group">
              <label><input type="radio" name="etat_presence" value="présent" checked> <span style="color: var(--success); font-weight:600;">Présent</span></label>
              <label><input type="radio" name="etat_presence" value="absent"> <span style="color: var(--danger); font-weight:600;">Absent</span></label>
            </div>
          </div>

          <div class="form-group">
            <label for="commentaire_admin">Commentaire / Observation</label>
            <textarea id="commentaire_admin" name="commentaire_admin" rows="1" placeholder="Ex: Travail de qualité, retard justifié (Optionnel)"></textarea>
          </div>

          <button type="submit" class="btn-submit presence">Ajouter le pointage</button>
        </form>
      </div>

    </section>
  </main>

  <!-- MODALE NATIVE FLUIDE -->
  <dialog id="modalNatif">
    <div class="modal-header">
      <h3 id="modalTitle">Titre</h3>
      <span class="close-btn" onclick="fermerModale()"><i class="fas fa-times"></i></span>
    </div>
    <div class="modal-subheader">Stagiaire : <span id="modalStagiaire"></span></div>
    <ul id="modalList"></ul>
  </dialog>

  <script>
    // Initialiser le champ Date par défaut sur aujourd'hui
    document.getElementById('date_fiche').valueAsDate = new Date();

    function toggleMenu() { 
      document.getElementById('sidebar').classList.toggle('show'); 
    }

    function ouvrirModale(nomStagiaire, typeSuivi, donnees, iconeClasse, couleurBord) {
      const dialog = document.getElementById('modalNatif');
      document.getElementById('modalTitle').innerHTML = '<i class="fas ' + iconeClasse + '"></i> ' + typeSuivi;
      document.getElementById('modalTitle').querySelector('i').style.color = couleurBord;
      document.getElementById('modalStagiaire').innerText = nomStagiaire;
      
      let ul = document.getElementById('modalList');
      ul.innerHTML = "";
      
      if (!donnees || donnees.length === 0) {
        let li = document.createElement('li');
        li.className = "no-data";
        li.innerHTML = "<i class='fas fa-info-circle'></i> Aucune donnée enregistrée dans taches/suivi pour le moment.";
        ul.appendChild(li);
      } else {
        donnees.forEach(item => {
          let li = document.createElement('li');
          li.innerText = item;
          li.style.borderLeftColor = couleurBord;
          ul.appendChild(li);
        });
      }
      dialog.showModal();
    }

    function fermerModale() { 
      document.getElementById('modalNatif').close(); 
    }
  </script>
</body>
</html>