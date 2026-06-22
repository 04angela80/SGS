<?php
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

    // 🌟 AJOUT : Récupération de la liste complète des stagiaires pour le tableau
    $stmtStagiaires = $bdd->query("SELECT nom, prenom, sexe, service, filiere, lettre, statut FROM stagiaires ORDER BY id DESC");
    $liste_stagiaires = $stmtStagiaires->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $total = $total ?? 0;
    $valides = $valides ?? 0;
    $attente = $attente ?? 0;
    $refuses = $refuses ?? 0;
    $rapports = $rapports ?? 0;
    $liste_stagiaires = [];
}

// 3. CHARGEMENT DES NOTIFICATIONS CENTRALISÉES
$total_notifs_admin = compterNotificationsNonLues($bdd, $admin_id, 'admin');
$alertes_admin = recupererNotificationsRecentes($bdd, $admin_id, 'admin'); 
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Dashboard Administrateur</title>
  <style>
    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background: #f4f4f9;
    }

    /* Header supérieur */
    .top-header {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: 60px;
      background: linear-gradient(90deg, #0056b3, #003d80);
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      padding: 0 20px;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
      z-index: 1000;
    }

    /* Titre centré */
    .top-header .header-title {
      font-size: 1.4em;
      font-weight: bold;
      margin: 0;
      letter-spacing: 1px;
      position: absolute;
      left: 50%;
      transform: translateX(-50%);
    }

    /* Icônes à droite */
    .top-header .header-icons {
      position: absolute;
      right: 20px;
      display: flex;
      gap: 15px;
      font-size: 22px;
      cursor: pointer;
    }

    .top-header .header-icons span:hover {
      transform: scale(1.2);
    }

    /* Bouton menu */
    .menu-btn {
      position: fixed; top: 15px; left: 15px;
      background: #0056b3; color: #fff;
      padding: 10px 15px; cursor: pointer;
      border-radius: 5px; z-index: 1000;
      display: inline-flex; align-items: center; gap: 6px;
    }

    /* Barre latérale */
    .sidebar {
      position: fixed; left: -250px; top: 0;
      width: 250px; height: 100vh;
      background: linear-gradient(180deg, #0056b3, #003d80);
      color: #fff; padding: 20px;
      transition: left 0.5s ease; z-index: 999;
      overflow-y: auto; display: flex; flex-direction: column;
    }

    .sidebar.show { left: 0; }

    /* Logo */
    .logo-container { margin-top: 30px; margin-bottom: 25px; text-align: center; }
    .logo {
      width: 90px; height: 90px; border-radius: 50%;
      background: #fff; padding: 6px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
      transition: transform 0.3s ease;
    }
    .logo:hover { transform: scale(1.05); }

    .sidebar ul { list-style: none; padding: 0; margin: 0; flex: 1; }
    .sidebar ul li { margin: 20px 0; }
    .sidebar ul li a {
      color: #fff; text-decoration: none; font-weight: bold;
      display: flex; align-items: center; white-space: nowrap;
      padding: 12px 15px; border-radius: 6px; transition: background 0.3s;
    }
    .sidebar ul li a i { margin-right: 8px; font-size: 18px; flex-shrink: 0; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.2); }
    .logout { margin-top: auto; }

    /* Dashboard cards */
    .dashboard {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px; padding: 40px 40px 10px 40px; margin-top: 100px; margin-left: 40px;
    }
    .card {
      background: white; border-radius: 12px;
      box-shadow: 0 4px 12px rgba(0,0,0,0.1); padding: 20px;
      text-align: center; transition: transform 0.3s; margin-top: 25px;
      cursor: pointer;
    }
    .card h2 {
      color: #2a5298; margin-bottom: 10px;
      display: flex; align-items: center; justify-content: center; gap: 6px;
    }
    .card p { font-size: 22px; font-weight: bold; color: #6a11cb; margin: 0; }
    .card:hover { transform: translateY(-5px); }

    /* Zone des Notifications */
    .notifications {
      background: #fff; margin: 20px 40px 20px 80px; padding: 20px;
      border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .notifications h2 { color: #2a5298; margin-bottom: 15px; display: flex; align-items: center; gap: 6px; }
    .notifications ul { list-style: none; padding: 0; }
    .notifications ul li {
      margin: 10px 0; padding: 12px;
      background: #f4f4f9; border-radius: 8px;
      display: flex; justify-content: space-between; align-items: center;
    }
    .badge-cible {
      background: #e0e7ff; color: #4338ca;
      padding: 3px 8px; border-radius: 4px;
      font-size: 11px; font-weight: bold; margin-right: 10px;
    }

    /* 🌟 STYLE AJOUTÉ POUR LE TABLEAU DES STAGIAIRES */
    .table-section {
      background: #fff; margin: 20px 40px 40px 80px; padding: 25px;
      border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    }
    .table-section h2 { color: #2a5298; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
    .custom-table {
      width: 100%; border-collapse: collapse; text-align: left; font-size: 14px;
    }
    .custom-table th, .custom-table td {
      padding: 12px 15px; border-bottom: 1px solid #e5e7eb;
    }
    .custom-table th { background-color: #f8fafc; color: #475569; font-weight: 700; }
    .custom-table tr:hover { background-color: #f1f5f9; }
    
    /* Bouton PDF stylisé */
    .btn-pdf {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 6px 12px; background-color: #7c3aed; color: white;
      text-decoration: none; border-radius: 6px; font-weight: bold;
      font-size: 12px; transition: background 0.2s;
    }
    .btn-pdf:hover { background-color: #6d28d9; }
    
    /* Badges de statut */
    .status-badge {
      padding: 4px 8px; border-radius: 6px; font-size: 11px; font-weight: bold; display: inline-block;
    }
    .status-attente { background-color: #fef3c7; color: #d97706; }
    .status-valide { background-color: #d1fae5; color: #059669; }
    .status-refuse { background-color: #fee2e2; color: #dc2626; }

    /* 🔔 Animation vibration téléphone pour la cloche active */
    @keyframes bell-ring {
      0% { transform: rotate(-15deg); }
      100% { transform: rotate(15deg); }
    }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-home"></i> Dashboard</h1>
    <div class="header-icons">
      
      <span class="notif" style="position: relative; display: inline-block;">
        <?php if ($total_notifs_admin > 0): ?>
          <i class="fas fa-bell" style="color: #ff4d4d; animation: bell-ring 0.4s ease infinite alternate;"></i>
          <span style="position: absolute; top: -8px; right: -8px; background: #ff3333; color: white; border-radius: 50%; padding: 2px 7px; font-size: 12px; font-weight: bold; box-shadow: 0 0 10px rgba(255,0,0,0.5);">
            <?php echo $total_notifs_admin; ?>
          </span>
        <?php else: ?>
          <i class="fas fa-bell" style="color: #fff;"></i>
        <?php endif; ?>
      </span>
      
      <span class="admin"><i class="fas fa-user-shield"></i></span>
    </div>
  </div>

  <div class="menu-btn"><i class="fas fa-bars"></i> Menu</div>

  <aside class="sidebar" id="sidebar">
    <div class="logo-container">
      <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo">
    </div>
    <ul>
      <li><a href="liste.php" class="active"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="evaluations.php"><i class="fas fa-chart-bar"></i> Évaluation & Résultats</a></li>
      <li><a href="suivi.php"><i class="fas fa-thumbtack"></i> Suivi des taches</a></li>
      <li><a href="rapport.php"><i class="fas fa-thumbtack"></i> Rapports</a></li>
      <li><a href="SERVICE.php"><i class="fas fa-tools"></i> Services</a></li>
      <li class="logout"><a href="../../public/index.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </aside>

  <main class="dashboard">
    <div class="card" onclick="window.location.href='liste_stagiaires.php?statut=all'">
      <h2><i class="fas fa-user-graduate"></i> Stagiaires inscrits</h2>
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
      <h2><i class="fas fa-file-alt"></i> Rapports soumis</h2>
      <p><?php echo $rapports; ?></p>
    </div>
  </main>

  <section class="notifications">
    <h2><i class="fas fa-bell"></i> Notifications récentes</h2>
    <ul>
      <?php if (empty($alertes_admin)): ?>
        <li><i class="fas fa-info-circle" style="color: #0056b3; margin-right: 8px;"></i> Aucune nouvelle notification pour le moment.</li>
      <?php else: ?>
        <?php foreach ($alertes_admin as $notif): ?>
          <li>
            <div>
              <span class="badge-cible">
                TYPE : <?php echo strtoupper(htmlspecialchars($notif['type'])); ?>
              </span>
              <?php echo htmlspecialchars($notif['contenu']); ?>
            </div>
            <small style="color: #888; font-size: 0.85em;">
              <?php echo date('d/m à H:i', strtotime($notif['date_notification'])); ?>
            </small>
          </li>
        <?php endforeach; ?>
      <?php endif; ?>
    </ul>
  </section>

  <section class="table-section">
    <h2><i class="fas fa-list"></i> Liste des Demandes d'Inscription</h2>
    <table class="custom-table">
      <thead>
        <tr>
          <th>Nom</th>
          <th>Prénom</th>
          <th>Sexe</th>
          <th>Service Demandé</th>
          <th>Filière / Spécialité</th>
          <th>Statut</th>
          <th>Lettre de Stage</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($liste_stagiaires)): ?>
          <tr>
            <td colspan="7" style="text-align: center; color: #888;">Aucun stagiaire inscrit pour le moment.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($liste_stagiaires as $stagiaire): ?>
            <tr>
              <td><strong><?php echo htmlspecialchars(strtoupper($stagiaire['nom'])); ?></strong></td>
              <td><?php echo htmlspecialchars($stagiaire['prenom']); ?></td>
              <td><?php echo htmlspecialchars($stagiaire['sexe']); ?></td>
              <td><?php echo htmlspecialchars($stagiaire['service']); ?></td>
              <td><?php echo htmlspecialchars($stagiaire['filiere']); ?></td>
              <td>
                <?php 
                  $status_clean = strtolower(trim($stagiaire['statut']));
                  if ($status_clean === 'validé') {
                      echo '<span class="status-badge status-valide">Validé</span>';
                  } elseif ($status_clean === 'refusé') {
                      echo '<span class="status-badge status-refuse">Refusé</span>';
                  } else {
                      echo '<span class="status-badge status-attente">En attente</span>';
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
  </section>

  <script>
    const menuBtn = document.querySelector('.menu-btn');
    const sidebar = document.getElementById('sidebar');

    menuBtn.addEventListener('click', () => {
      sidebar.classList.toggle('show');
    });

    document.addEventListener('click', (event) => {
      const isClickInside = sidebar.contains(event.target) || menuBtn.contains(event.target);
      if (!isClickInside && sidebar.classList.contains('show')) {
        sidebar.classList.remove('show');
      }
    });
  </script>
</body>
</html>