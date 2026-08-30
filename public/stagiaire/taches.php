<?php
session_start();

// Connexion à la base de données
require_once __DIR__ . '/../../config/db.php';

// Si pas de session stagiaire définie, simulation pour la démo
$stagiaire_id = isset($_SESSION['stagiaire_id']) ? intval($_SESSION['stagiaire_id']) : 1;

// ==========================================================================
// TRAITEMENT AJAX : CLIC SUR UNE CASE À COCHER (COCHER / DÉCOCHER)
// ==========================================================================
if (isset($_POST['action_ajax']) && $_POST['action_ajax'] === 'toggle_tache') {
    header('Content-Type: application/json; charset=UTF-8');
    $tache_id = isset($_POST['tache_id']) ? intval($_POST['tache_id']) : 0;
    $est_faite = isset($_POST['est_faite']) ? intval($_POST['est_faite']) : 0;

    if ($tache_id > 0 && $stagiaire_id > 0) {
        try {
            if ($est_faite === 1) {
                $stmt = $bdd->prepare("INSERT INTO suivi_tache_stagiaire (stagiaire_id, tache_id, est_faite, date_validation) 
                                       VALUES (?, ?, 1, NOW()) 
                                       ON DUPLICATE KEY UPDATE est_faite = 1, date_validation = NOW()");
                $stmt->execute([$stagiaire_id, $tache_id]);
            } else {
                $stmt = $bdd->prepare("UPDATE suivi_tache_stagiaire SET est_faite = 0 WHERE stagiaire_id = ? AND tache_id = ?");
                $stmt->execute([$stagiaire_id, $tache_id]);
            }

            // Calcul du nouveau pourcentage basé sur la table 'tache'
            $totalTaches = $bdd->query("SELECT COUNT(*) FROM tache")->fetchColumn();
            $stmtDone = $bdd->prepare("SELECT COUNT(*) FROM suivi_tache_stagiaire WHERE stagiaire_id = ? AND est_faite = 1");
            $stmtDone->execute([$stagiaire_id]);
            $nbDone = $stmtDone->fetchColumn();

            $pourcentage = $totalTaches > 0 ? round(($nbDone / $totalTaches) * 100) : 0;

            echo json_encode([
                'success' => true, 
                'pourcentage' => $pourcentage,
                'nbDone' => $nbDone,
                'totalTaches' => $totalTaches
            ]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Données invalides']);
    }
    exit();
}

// ==========================================================================
// RÉCUPÉRATION DE L'INFORMATIONS DU STAGIAIRE & DES TÂCHES
// ==========================================================================
try {
    // Infos du stagiaire
    $stmtStg = $bdd->prepare("SELECT s.*, srv.nom_service, srv.nom_encadrant 
                             FROM stagiaires s 
                             LEFT JOIN services srv ON s.id_service_affecte = srv.id_service 
                             WHERE s.id = ?");
    $stmtStg->execute([$stagiaire_id]);
    $stagiaireInfo = $stmtStg->fetch(PDO::FETCH_ASSOC);

    $nomCompletStagiaire = $stagiaireInfo ? strtoupper($stagiaireInfo['nom']) . ' ' . $stagiaireInfo['prenom'] : "STAGIAIRE";
    $serviceNom = $stagiaireInfo['nom_service'] ?? "Non assigné";
    $encadrantNom = $stagiaireInfo['nom_encadrant'] ?? "Encadreur non assigné";

    // Récupération des tâches depuis la table 'tache' et de leur état pour ce stagiaire
    $sqlTaches = "SELECT t.*, IF(st.est_faite IS NULL, 0, st.est_faite) AS est_faite, st.date_validation
                  FROM tache t
                  LEFT JOIN suivi_tache_stagiaire st ON t.id = st.tache_id AND st.stagiaire_id = ?
                  ORDER BY t.ordre ASC";
    $stmtT = $bdd->prepare($sqlTaches);
    $stmtT->execute([$stagiaire_id]);
    $listeTaches = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    $totalTaches = count($listeTaches);
    $nbDone = 0;
    foreach ($listeTaches as $t) {
        if ($t['est_faite'] == 1) $nbDone++;
    }

    $progressionPct = $totalTaches > 0 ? round(($nbDone / $totalTaches) * 100) : 0;

} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Feuille de Route & Consignes - SGS Stagiaire</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #334155; }
    
    /* EN-TÊTE ET HEADER DU MENU */
    .top-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(90deg, #0056b3, #003d80); color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 1000; }
    .top-header .header-title { font-size: 1.4em; font-weight: bold; margin: 0; letter-spacing: 1px; position: absolute; left: 50%; transform: translateX(-50%); }
    .top-header .header-icons { position: absolute; right: 20px; display: flex; gap: 15px; font-size: 22px; cursor: pointer; }
    .top-header .header-icons span:hover { transform: scale(1.2); }

    .menu-btn { position: fixed; top: 15px; left: 15px; background: #0056b3; color: #fff; padding: 10px 15px; cursor: pointer; border-radius: 5px; z-index: 1001; display: inline-flex; align-items: center; gap: 6px; font-weight: bold; }
    
    .sidebar { 
      position: fixed; 
      left: -250px; 
      top: 0; 
      width: 250px; 
      height: 100vh; 
      background: linear-gradient(180deg, #0056b3, #003d80); 
      color: #fff;  
      padding: 20px; 
      transition: left 0.5s ease; 
      z-index: 999; 
      overflow-y: hidden;
      display: flex; 
      flex-direction: column; 
    }
    .sidebar.show { left: 0; }
    .logo-container { margin-top: 30px; margin-bottom: 20px; text-align: center; }
    .logo { width: 90px; height: 90px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
    
    .sidebar ul { list-style: none; padding: 0; margin: 0; }
    .sidebar ul li { margin: 12px 0; }
    .sidebar ul li a { color: #fff; text-decoration: none; font-weight: bold; display: flex; align-items: center; white-space: nowrap; padding: 10px 15px; border-radius: 6px; transition: background 0.3s; }
    .sidebar ul li a i { margin-right: 8px; font-size: 18px; flex-shrink: 0; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.2); }
    
    .logout { margin-top: 5px; } 

    .sidebar-footer {
      margin-top: 15px;
      text-align: center;
      padding-bottom: 50px;
    }
    .sidebar-divider { height: 1.5px; background: #ffffff; margin: 10px 0; border: none; }
    .footer-text { font-size: 13px; color: #ffffff; font-weight: 600; letter-spacing: 0.5px; line-height: 1.4; }
    .footer-sub { font-size: 11px; display: block; font-weight: 400; color: #f1f5f9; margin-top: 2px; }

    /* CONTENU PRINCIPAL */
    .main-content { 
      max-width: 1100px; 
      margin: 0 auto; 
      padding: 100px 30px 40px 30px; 
      box-sizing: border-box; 
    }
    
    .page-title-banner {
      background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
      border-left: 5px solid #0056b3;
      padding: 22px 25px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.04);
      margin-bottom: 30px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      flex-wrap: wrap;
      gap: 15px;
    }
    .page-title-banner h2 { margin: 0; color: #0056b3; font-size: 1.5em; display: flex; align-items: center; gap: 12px; }
    .page-title-banner p { margin: 6px 0 0 0; color: #64748b; font-size: 0.95em; }

    .user-badge { background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af; padding: 8px 16px; border-radius: 30px; font-weight: 600; font-size: 0.88em; display: flex; align-items: center; gap: 8px; }

    /* BLOC PROGRESSION */
    .progress-card {
      background: #ffffff;
      border-radius: 12px;
      padding: 25px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
      border: 1px solid #e2e8f0;
      margin-bottom: 30px;
    }
    .progress-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; }
    .progress-title { font-weight: bold; color: #0f172a; font-size: 1.1em; display: flex; align-items: center; gap: 10px; }
    .progress-percent { font-size: 1.5em; font-weight: 800; color: #0056b3; }

    .bar-outer { background: #f1f5f9; height: 16px; border-radius: 10px; overflow: hidden; width: 100%; border: 1px solid #e2e8f0; }
    .bar-inner { height: 100%; background: linear-gradient(90deg, #10b981, #059669); border-radius: 10px; transition: width 0.4s ease; }

    /* CARTE ET LISTE DES TÂCHES */
    .tasks-container {
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
      padding: 25px;
    }
    .tasks-header { border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; margin-bottom: 20px; }
    .tasks-header h3 { margin: 0; color: #0f172a; font-size: 1.2em; display: flex; align-items: center; gap: 10px; }

    .task-item {
      display: flex;
      align-items: flex-start;
      gap: 18px;
      padding: 18px 20px;
      border-radius: 10px;
      border: 1px solid #f1f5f9;
      background: #fafafa;
      margin-bottom: 15px;
      transition: all 0.25s ease;
      cursor: pointer;
    }
    .task-item:hover { background: #ffffff; border-color: #cbd5e1; transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.04); }
    .task-item.completed { background: #f0fdf4; border-color: #bbf7d0; }

    /* CASE À COCHER CUSTOMISEE */
    .checkbox-wrapper { display: flex; align-items: center; justify-content: center; margin-top: 2px; }
    .custom-checkbox {
      width: 26px; height: 26px; border-radius: 8px; border: 2px solid #cbd5e1;
      display: flex; align-items: center; justify-content: center; background: #ffffff;
      transition: all 0.2s ease; flex-shrink: 0;
    }
    .task-item.completed .custom-checkbox { background: #10b981; border-color: #10b981; color: #ffffff; }
    .custom-checkbox i { font-size: 14px; display: none; }
    .task-item.completed .custom-checkbox i { display: block; }

    .task-content { flex-grow: 1; }
    .task-title { font-weight: 700; font-size: 1.05em; color: #1e293b; margin-bottom: 4px; transition: color 0.2s; }
    .task-item.completed .task-title { text-decoration: line-through; color: #166534; }
    .task-desc { font-size: 0.92em; color: #64748b; line-height: 1.4; }

    .task-tag {
      font-size: 0.75em;
      font-weight: 700;
      text-transform: uppercase;
      padding: 4px 10px;
      border-radius: 20px;
      background: #e0f2fe;
      color: #0369a1;
      display: inline-block;
      margin-top: 8px;
    }
    .task-item.completed .task-tag { background: #dcfce7; color: #15803d; }

    .task-status-badge {
      font-size: 0.82em;
      font-weight: 700;
      padding: 6px 12px;
      border-radius: 20px;
      white-space: nowrap;
      display: flex;
      align-items: center;
      gap: 6px;
    }
    .status-todo { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }
    .status-done { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
  </style>
</head>
<body>

  <!-- EN-TÊTE HAUT DE PAGE -->
  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-tasks"></i> Feuille de Route du Stagiaire</h1>
    <div class="header-icons">
      <span class="user"><i class="fas fa-user-graduate"></i></span>
    </div>
  </div>
  <!-- BOUTON D'OUVERTURE DU MENU -->
  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <!-- MENU LATÉRAL SIDEBAR -->
  <aside class="sidebar" id="sidebar">
    <div class="logo-container"><img src="../../LOGO.jpeg" class="logo" onerror="this.style.display='none'"></div>
    <ul>
       <li><a href="stagiaire.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="taches.php"class="active"><i class="fas fa-tasks"></i> Mes tâches</a></li>
       <li><a href="rapport.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
      <li><a href="resultats.PHP"><i class="fas fa-chart-line"></i> Résultats</a></li>
      <li><a href="profil.php"><i class="fas fa-cog"></i> Profil</a></li>
      <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
    <div class="sidebar-footer">
      <hr class="sidebar-divider">
      <div class="footer-text">
        <span><i class="fas fa-user-graduate"></i> SGS • Stagiaire</span>
        <span class="footer-sub">Système de Gestion des Stagiaires @2026</span>
      </div>
    </div>
  </aside>

  <!-- CONTENU PRINCIPAL DE LA PAGE -->
  <main class="main-content">
    
    <!-- BANDEAU DE BIENVENUE -->
    <div class="page-title-banner">
      <div>
        <h2><i class="fas fa-clipboard-list"></i> Guide d'Intégration & Consignes de Stage</h2>
        <p>Cochez les étapes au fur et à mesure de votre progression au sein de votre service affecté.</p>
      </div>
      <div class="user-badge">
        <i class="fas fa-building"></i> <?= htmlspecialchars($serviceNom) ?> | Encadreur : <?= htmlspecialchars($encadrantNom) ?>
      </div>
    </div>

    <!-- CARTE DE PROGRESSION GLOBALE -->
    <div class="progress-card">
      <div class="progress-header">
        <div class="progress-title">
          <i class="fas fa-chart-line" style="color:#10b981;"></i> Votre Niveau d'Intégration
        </div>
        <div class="progress-percent" id="txt_pct"><?= $progressionPct ?>%</div>
      </div>
      <div class="bar-outer">
        <div class="bar-inner" id="bar_fill" style="width: <?= $progressionPct ?>%;"></div>
      </div>
      <div style="font-size:0.85em; color:#64748b; margin-top:10px; display:flex; justify-content:space-between;">
        <span id="txt_counter"><strong><?= $nbDone ?></strong> étape(s) réalisée(s) sur <strong><?= $totalTaches ?></strong></span>
        <span>Légende : Cliquez sur une ligne pour marquer l'étape comme terminée.</span>
      </div>
    </div>

    <!-- LISTE DES TÂCHES ET CONSIGNES -->
    <div class="tasks-container">
      <div class="tasks-header">
        <h3><i class="fas fa-check-double" style="color:#0056b3;"></i> Étapes de la Feuille de Route</h3>
      </div>

      <div id="tasks_wrapper">
        <?php foreach ($listeTaches as $tache): 
            $isDone = ($tache['est_faite'] == 1);
        ?>
          <div class="task-item <?= $isDone ? 'completed' : '' ?>" 
               id="task_card_<?= $tache['id'] ?>" 
               onclick="toggleTask(<?= $tache['id'] ?>)">
            
            <div class="checkbox-wrapper">
              <div class="custom-checkbox" id="check_box_<?= $tache['id'] ?>">
                <i class="fas fa-check"></i>
              </div>
            </div>

            <div class="task-content">
              <div class="task-title"><?= htmlspecialchars($tache['titre']) ?></div>
              <div class="task-desc"><?= htmlspecialchars($tache['description']) ?></div>
              <span class="task-tag"><i class="fas fa-tag"></i> <?= htmlspecialchars($tache['categorie']) ?></span>
            </div>

            <div>
              <?php if ($isDone): ?>
                <span class="task-status-badge status-done" id="status_badge_<?= $tache['id'] ?>">
                  <i class="fas fa-check-circle"></i> Réalisé
                </span>
              <?php else: ?>
                <span class="task-status-badge status-todo" id="status_badge_<?= $tache['id'] ?>">
                  <i class="fas fa-hourglass-start"></i> À faire
                </span>
              <?php endif; ?>
            </div>

          </div>
        <?php endforeach; ?>
      </div>
    </div>

  </main>

  <script>
    // Animation Ouverture/Fermeture du Menu
    function toggleMenu() { 
      document.getElementById('sidebar').classList.toggle('show'); 
    }

    // Gestion interactive des clics sur les tâches (AJAX)
    function toggleTask(tacheId) {
      const card = document.getElementById(`task_card_${tacheId}`);
      const isCurrentlyDone = card.classList.contains('completed');
      const nextState = isCurrentlyDone ? 0 : 1;

      // Mise à jour visuelle immédiate (mode réactif)
      if (nextState === 1) {
        card.classList.add('completed');
        document.getElementById(`status_badge_${tacheId}`).className = "task-status-badge status-done";
        document.getElementById(`status_badge_${tacheId}`).innerHTML = '<i class="fas fa-check-circle"></i> Réalisé';
      } else {
        card.classList.remove('completed');
        document.getElementById(`status_badge_${tacheId}`).className = "task-status-badge status-todo";
        document.getElementById(`status_badge_${tacheId}`).innerHTML = '<i class="fas fa-hourglass-start"></i> À faire';
      }

      // Envoi de la requête au serveur PHP
      const formData = new FormData();
      formData.append('action_ajax', 'toggle_tache');
      formData.append('tache_id', tacheId);
      formData.append('est_faite', nextState);

      fetch('taches.php', {
        method: 'POST',
        body: formData
      })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          // Mise à jour dynamique de la barre de progression
          document.getElementById('bar_fill').style.width = data.pourcentage + '%';
          document.getElementById('txt_pct').innerText = data.pourcentage + '%';
          document.getElementById('txt_counter').innerHTML = `<strong>${data.nbDone}</strong> étape(s) réalisée(s) sur <strong>${data.totalTaches}</strong>`;
        }
      })
      .catch(err => console.error("Erreur mise à jour tâche :", err));
    }
  </script>
</body>
</html>