<?php
  // ex: 1 ou $_SESSION['admin_id']
$notif_user_type = 'admin';

// 1. Inclusions indispensables (BDD + Moteur de notifications)
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/notifications_moteur.php';

// ID de l'administrateur (1 par défaut)
$admin_id = 1; 

// 2. RÉCUPÉRATION DES STATISTIQUES POUR LES CARTES
try {
    // Nombre total de stagiaires inscrits
    $stmt = $bdd->query("SELECT COUNT(*) FROM stagiaires");
    $total = $stmt->fetchColumn();

    // Nombre de stagiaires validés
    $stmt = $bdd->query("SELECT COUNT(*) FROM stagiaires WHERE statut = 'validé'");
    $valides = $stmt->fetchColumn();

    // Nombre de stagiaires en attente
    $stmt = $bdd->query("SELECT COUNT(*) FROM stagiaires WHERE statut = 'en attente'");
    $attente = $stmt->fetchColumn();

    // Nombre de stagiaires refusés
    $stmt = $bdd->query("SELECT COUNT(*) FROM stagiaires WHERE statut = 'refusé'");
    $refuses = $stmt->fetchColumn();

    // Nombre de rapports soumis
    $stmtRapports = $bdd->query("SELECT COUNT(*) FROM rapports");
    $rapports = $stmtRapports->fetchColumn();

    // Récupération de la liste complète des stagiaires (incluant l'université)
    $stmtStagiaires = $bdd->query("SELECT nom, prenom, sexe, universite, service, filiere, lettre, statut FROM stagiaires ORDER BY id DESC");
    $liste_stagiaires = $stmtStagiaires->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $total = $total ?? 0;
    $valides = $valides ?? 0;
    $attente = $attente ?? 0;
    $refuses = $refuses ?? 0;
    $rapports = $rapports ?? 0;
    $liste_stagiaires = [];
}

// 3. CHARGEMENT DES NOTIFICATIONS POUR LA CLOCHE
$total_notifs_admin = compterNotificationsNonLues($bdd, $admin_id, 'admin');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Administrateur</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* STRUCTURE GLOBALE CALQUÉE STRICTEMENT SUR GESTION.PHP */
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #334155; }
    
    /* HEADER INITIAL RECONSTITUÉ */
    .top-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(90deg, #0056b3, #003d80); color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 1000; }
    .top-header .header-title { font-size: 1.4em; font-weight: bold; margin: 0; letter-spacing: 1px; position: absolute; left: 50%; transform: translateX(-50%); }
    .top-header .header-icons { position: absolute; right: 20px; display: flex; gap: 15px; font-size: 22px; cursor: pointer; }
    .top-header .header-icons span:hover { transform: scale(1.1); }
    .bell-count { position: absolute; top: -5px; right: -7px; background: #ff4d4d; color: white; font-size: 11px; padding: 1px 5px; border-radius: 10px; font-weight: bold; }
    
  /* SIDEBAR & BOUTON INTELLIGENT CORRIGÉ */
    .menu-btn { position: fixed; top: 15px; left: 15px; background: #0056b3; color: #fff; padding: 10px 15px; cursor: pointer; border-radius: 5px; z-index: 1001; display: inline-flex; align-items: center; gap: 6px; }
    
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
    
    .container { padding: 80px 60px 60px 60px; max-width: 1200px; margin: 0 auto; }
    
    .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .card { background: white; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); padding: 22px 20px; text-align: center; transition: transform 0.3s, box-shadow 0.3s; cursor: pointer; border: 1px solid #e2e8f0; }
    .card:hover { transform: translateY(-4px); box-shadow: 0 8px 20px rgba(0, 86, 179, 0.1); }
    .card h2 { font-size: 0.92em; text-transform: uppercase; letter-spacing: 0.5px; color: #64748b; margin-bottom: 10px; display: flex; align-items: center; justify-content: center; gap: 8px; font-weight: 700; }
    .card p { font-size: 28px; font-weight: 800; color: #0f172a; margin: 0; }
    
    .card:nth-child(1) h2 i { color: #3b82f6; }
    .card:nth-child(2) h2 i { color: #10b981; }
    .card:nth-child(3) h2 i { color: #f59e0b; }
    .card:nth-child(4) h2 i { color: #ef4444; }
    .card:nth-child(5) h2 i { color: #8b5cf6; }

    .quick-actions-section { background: #ffffff; padding: 22px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.06); margin-bottom: 30px; border: 1px solid #e2e8f0; }
    .quick-actions-section h3 { font-size: 1.1em; color: #1e3a8a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; font-weight: 700; }
    .actions-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 15px; }
    .action-link-btn { display: flex; align-items: center; gap: 12px; padding: 14px 18px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; text-decoration: none; color: #334155; font-weight: 600; font-size: 0.9em; transition: all 0.2s ease; }
    .action-link-btn:hover { background: #eff6ff; border-color: #bfdbfe; color: #0056b3; }
    .action-link-btn i { font-size: 1.1em; color: #0056b3; }

    /* STRUCTURE DU TABLEAU ET ÉVITANCE DU DÉBORDEMENT */
    .table-section h2 { font-size: 1.4em; color: #1e3a8a; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .table-wrapper { background: #ffffff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; overflow-x: auto; }
    .custom-table { width: 100%; border-collapse: collapse; text-align: left; font-size: 13.5px; }
    .custom-table th, .custom-table td { padding: 12px 14px; border-bottom: 1px solid #f1f5f9; white-space: nowrap; }
    .custom-table th { background: #0056b3; color: #ffffff; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; font-size: 0.85em; }
    .custom-table tr:hover { background-color: #f8fafc; }
    
    /* Tronquage élégant des universités longues pour garder la même taille de tableau */
    .univ-cell { max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: #475569; font-weight: 500; }
    
    .btn-pdf { display: inline-flex; align-items: center; gap: 6px; padding: 5px 10px; background-color: #7c3aed; color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 11.5px; transition: background 0.2s; }
    .btn-pdf:hover { background-color: #6d28d9; }
    .status-badge { padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.82em; display: inline-flex; align-items: center; gap: 5px; }
    .status-attente { background-color: #fef3c7; color: #92400e; }
    .status-valide { background-color: #d1fae5; color: #065f46; }
    .status-refuse { background-color: #fee2e2; color: #991b1b; }

    @keyframes bell-ring { 0% { transform: rotate(-15deg); } 100% { transform: rotate(15deg); } }
  </style>
</head>
<body>

  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-home"></i> Dashboard</h1>
    <div class="header-icons">
      <span class="admin"><i class="fas fa-user-shield"></i></span>
    </div>
  </div>

  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

 <aside class="sidebar" id="sidebar">
    <div class="logo-container">
      <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo">
    </div>
    <ul>
       <li><a href="liste.php" class="active"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="suivi.php"><i class="fas fa-chart-bar"></i>Suivi des stagiaires</a></li>
      <li><a href="rapport.php"><i class="fas fa-thumbtack"></i>Rapports</a></li>
      <li><a href="evaluations.php"><i class="fas fa-file-alt"></i>Évaluation & Résultats</a></li>
      <li><a href="SERVICE.php"><i class="fas fa-tools"></i> Services</a></li>
      <li class="logout"><a href="../../public/index.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>

    <div class="sidebar-footer">
      <hr class="sidebar-divider">
      <div class="footer-text">
        <span><i class="fas fa-user-shield"></i> SGS • Admin</span>
        <span class="footer-sub">Systeme de Gestion des Stagiaires@2026</span>
      </div>
    </div>
  </aside>

  <main class="container">
    
    <section class="dashboard-grid">
      <div class="card" onclick="window.location.href='liste_stagiaires.php?statut=all'">
        <h2><i class="fas fa-user-graduate"></i> Inscrits</h2>
        <p><?php echo $total; ?></p>
      </div>
      <div class="card" onclick="window.location.href='liste_stagiaires.php?statut=validé'">
        <h2><i class="fas fa-check-circle"></i> Validés</h2>
        <p><?php echo $valides; ?></p>
      </div>
      <div class="card" onclick="window.location.href='liste_stagiaires.php?statut=en attente'">
        <h2><i class="fas fa-hourglass-half"></i> En attente</h2>
        <p><?php echo $attente; ?></p>
      </div>
      <div class="card" onclick="window.location.href='liste_stagiaires.php?statut=refusé'">
        <h2><i class="fas fa-times-circle"></i> Refusés</h2>
        <p><?php echo $refuses; ?></p>
      </div>
      <div class="card" onclick="window.location.href='rapport.php'">
        <h2><i class="fas fa-file-alt"></i> Rapports</h2>
        <p><?php echo $rapports; ?></p>
      </div>
    </section>

    <section class="quick-actions-section">
      <h3><i class="fas fa-star" style="color:#eab308;"></i> Accès rapides de gestion</h3>
      <div class="actions-grid">
        <a href="gestion.php" class="action-link-btn"><i class="fas fa-user-plus"></i> Traiter les dossiers d'inscription</a>
        <a href="suivi.php" class="action-link-btn"><i class="fas fa-calendar-check"></i> Effectuer les pointages de présence</a>
        <a href="evaluations.php" class="action-link-btn"><i class="fas fa-pen-fancy"></i> Attribuer les notes et mentions</a>
        <a href="SERVICE.php" class="action-link-btn"><i class="fas fa-cubes"></i> Consulter l'occupation des services</a>
      </div>
    </section>

    <section class="table-section">
      <h2><i class="fas fa-list" style="color: #0056b3;"></i> Liste des Demandes d'Inscription récentes</h2>
      <div class="table-wrapper">
        <table class="custom-table">
          <thead>
            <tr>
              <th>Nom</th>
              <th>Prénom</th>
              <th>Sexe</th>
              <th>Université</th>
              <th>Service Demandé</th>
              <th>Département</th>
              <th>Statut</th>
              <th>Lettre de Stage</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($liste_stagiaires)): ?>
              <tr>
                <td colspan="8" style="text-align: center; color: #888; font-style: italic;">Aucun stagiaire inscrit pour le moment.</td>
              </tr>
            <?php else: ?>
              <?php foreach ($liste_stagiaires as $stagiaire): ?>
                <tr>
                  <td style="font-weight: 600; color: #1e293b;"><strong><?php echo htmlspecialchars(strtoupper($stagiaire['nom'])); ?></strong></td>
                  <td><?php echo htmlspecialchars($stagiaire['prenom']); ?></td>
                  <td style="text-align:center;"><?php echo htmlspecialchars($stagiaire['sexe']); ?></td>
                  
                  <!-- COLONNE UNIVERSITÉ RAJOUTÉE PROPREMENT -->
                  <td class="univ-cell" title="<?php echo htmlspecialchars($stagiaire['universite'] ?? ''); ?>">
                    <?php echo !empty($stagiaire['universite']) ? htmlspecialchars($stagiaire['universite']) : '<em style="color:#a1a1aa;">Non spécifiée</em>'; ?>
                  </td>

                  <td style="font-weight: 500; color: #0056b3;"><?php echo htmlspecialchars($stagiaire['service']); ?></td>
                  <td><?php echo htmlspecialchars($stagiaire['filiere']); ?></td>
                  <td>
                    <?php 
                      $status_clean = strtolower(trim($stagiaire['statut']));
                      if ($status_clean === 'validé' || $status_clean === 'valide') {
                          echo '<span class="status-badge status-valide"><i class="fas fa-check"></i> Validé</span>';
                      } elseif ($status_clean === 'refusé' || $status_clean === 'refuse') {
                          echo '<span class="status-badge status-refuse"><i class="fas fa-times"></i> Refusé</span>';
                      } else {
                          echo '<span class="status-badge status-attente"><i class="fas fa-hourglass-half"></i> En attente</span>';
                      }
                    ?>
                  </td>
                  <td>
                    <?php if (!empty($stagiaire['lettre'])): ?>
                      <a href="../stagiaire/<?php echo htmlspecialchars($stagiaire['lettre']); ?>" target="_blank" class="btn-pdf">
                        <i class="fas fa-file-pdf"></i> Ouvrir le PDF
                      </a>
                    <?php else: ?>
                      <span style="color: #9ca3af; font-style: italic;">Aucun fichier</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

  </main>

  <script>
    function toggleMenu() { document.getElementById('sidebar').classList.toggle('show'); }
  </script>
</body>
</html>