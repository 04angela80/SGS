<?php
require_once __DIR__ . '/../../config/db.php';

try {
    // 1. Récupération de TOUS les rapports des stagiaires dont le dossier est VALIDÉ
    // (Inclusion de plusieurs formes de statuts validés pour éviter qu'un rapport ne disparaisse)
    $stmt = $bdd->prepare("
        SELECT r.id, r.titre, r.contenu, r.fichier, r.date_rapport, s.nom, s.prenom, s.filiere, s.service 
        FROM rapports r 
        JOIN stagiaires s ON r.stagiaire_id = s.id 
        WHERE LOWER(TRIM(s.statut)) IN ('valide', 'validé', 'validée', '1', 'actif', 'approuve', 'approuvé')
        ORDER BY r.date_rapport DESC
    ");
    $stmt->execute();
    $rapports = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. Nombre total de rapports déposés par les stagiaires validés
    $stmtCount = $bdd->query("
        SELECT COUNT(r.id) 
        FROM rapports r 
        JOIN stagiaires s ON r.stagiaire_id = s.id 
        WHERE LOWER(TRIM(s.statut)) IN ('valide', 'validé', 'validée', '1', 'actif', 'approuve', 'approuvé')
    ");
    $totalRapports = intval($stmtCount->fetchColumn());

    // 3. Calcul dynamique basé sur l'effectif exact des stagiaires VALIDÉS
    // Total des stagiaires dont l'inscription est validée (s'ajuste dès qu'un nouveau dossier est validé)
    $totalStagiaires = intval($bdd->query("
        SELECT COUNT(*) 
        FROM stagiaires 
        WHERE LOWER(TRIM(statut)) IN ('valide', 'validé', 'validée', '1', 'actif', 'approuve', 'approuvé')
    ")->fetchColumn());
    
    // Nombre de stagiaires validés uniques ayant au moins un rapport enregistré
    $stagiairesAyantDepose = intval($bdd->query("
        SELECT COUNT(DISTINCT r.stagiaire_id) 
        FROM rapports r
        JOIN stagiaires s ON r.stagiaire_id = s.id
        WHERE LOWER(TRIM(s.statut)) IN ('valide', 'validé', 'validée', '1', 'actif', 'approuve', 'approuvé')
    ")->fetchColumn());
    
    // Stagiaires validés n'ayant pas encore déposé de rapport
    $sansRapportCount = max(0, $totalStagiaires - $stagiairesAyantDepose);

} catch (PDOException $e) {
    die('Erreur BDD : ' . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Rapports Admin</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { margin: 0; font-family: Arial, sans-serif; background: #f0f8ff; }
    .top-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(90deg, #0056b3, #003d80); color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 1000; }
    .top-header .header-title { font-size: 1.4em; margin: 0; }
    .top-header .header-icons { position: absolute; right: 20px; display: flex; gap: 15px; font-size: 22px; cursor: pointer; }
    .top-header .header-icons span:hover { transform: scale(1.1); }
    
    /* SIDEBAR & BOUTON INTELLIGENT */
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
   
    .container { padding: 110px 40px 40px 40px; max-width: 1200px; margin: 0 auto; }
    .summary-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
    .summary-card { background: #fff; border-radius: 16px; padding: 24px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); text-align: center; }
    .summary-card h3 { margin: 0 0 10px 0; color: #0f172a; }
    .summary-card p { margin: 0; font-size: 2.2em; font-weight: bold; color: #2563eb; }
    
    /* STYLE DES BADGES D'ALERTE */
    .badge-alert-warning { display: inline-block; margin-top: 8px; padding: 4px 12px; border-radius: 12px; background: #fff7ed; color: #c2410c; font-size: 0.85em; font-weight: bold; border: 1px solid #ffedd5; }
    .badge-alert-success { display: inline-block; margin-top: 8px; padding: 4px 12px; border-radius: 12px; background: #f0fdf4; color: #15803d; font-size: 0.85em; font-weight: bold; border: 1px solid #dcfce7; }

    .reports-table { width: 100%; border-collapse: collapse; background: #fff; border-radius: 16px; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.08); }
    .reports-table th, .reports-table td { padding: 14px 18px; border-bottom: 1px solid #e2e8f0; text-align: left; }
    .reports-table th { background: #2a5298; color: #fff; text-transform: uppercase; font-size: 0.85em; letter-spacing: 0.5px; }
    .reports-table tr:hover { background: #f8fafc; }
    .report-link { color: #0f4c81; font-weight: bold; text-decoration: none; }
    .report-link:hover { text-decoration: underline; }
    .status-chip { display: inline-block; padding: 6px 12px; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: 0.85em; }
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
  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-file-alt"></i> Rapports des stagiaires</h1>
    <div class="header-icons">
      <span class="admin"><i class="fas fa-user-shield"></i></span>
    </div>
  </div>
  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>
  <aside class="sidebar" id="sidebar">
    <div class="logo-container"><img src="../../LOGO.jpeg" alt="Logo SGS" class="logo"></div>
    <ul>
       <li><a href="liste.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="suivi.php"><i class="fas fa-chart-bar"></i>Suivi des stagiaires</a></li>
      <li><a href="rapport.php" class="active"><i class="fas fa-thumbtack"></i>Rapports</a></li>
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
    <div class="summary-cards">
      <div class="summary-card">
        <h3>Total rapports déposés</h3>
        <p><?php echo $totalRapports; ?></p>
      </div>
      <div class="summary-card">
        <h3>Stagiaires sans rapport</h3>
        <p style="color: <?php echo $sansRapportCount > 0 ? '#dc2626' : '#16a34a'; ?>;">
          <?php echo $sansRapportCount; ?>
        </p>
        <?php if ($sansRapportCount > 0): ?>
          <span class="badge-alert-warning"><i class="fas fa-exclamation-triangle"></i> <?php echo $sansRapportCount; ?> sur <?php echo $totalStagiaires; ?> à relancer</span>
        <?php else: ?>
          <span class="badge-alert-success"><i class="fas fa-check-circle"></i> Tous les stagiaires sont à jour</span>
        <?php endif; ?>
      </div>
    </div>

    <table class="reports-table">
      <thead>
        <tr>
          <th>Stagiaire</th>
          <th>Service</th>
          <th>Filière</th>
          <th>Titre</th>
          <th>Résumé</th>
          <th>Fichier</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($rapports)): ?>
          <tr><td colspan="7" style="text-align:center; color:#64748b;">Aucun rapport n'a encore été déposé par un stagiaire validé.</td></tr>
        <?php else: ?>
          <?php foreach ($rapports as $rapport): ?>
            <tr>
              <td><?php echo htmlspecialchars(($rapport['prenom'] ?? '') . ' ' . strtoupper($rapport['nom'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($rapport['service'] ?? 'Non renseigné', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($rapport['filiere'] ?? 'Non renseignée', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars($rapport['titre'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
              <td><?php echo htmlspecialchars(mb_strimwidth($rapport['contenu'] ?? '', 0, 80, '...'), ENT_QUOTES, 'UTF-8'); ?></td>
              <td>
                <?php if (!empty($rapport['fichier'])): ?>
                  <a class="report-link" href="../stagiaire/uploads/rapports/<?php echo htmlspecialchars($rapport['fichier'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><i class="fas fa-download"></i> Ouvrir</a>
                <?php else: ?>
                  <span class="status-chip">Aucun fichier</span>
                <?php endif; ?>
              </td>
              <td><?php echo !empty($rapport['date_rapport']) ? date('d/m/Y H:i', strtotime($rapport['date_rapport'])) : '-'; ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </main>

  <script>
    function toggleMenu() { document.getElementById('sidebar').classList.toggle('show'); }
  </script>
</body>
</html>