<?php
session_start();

// 1. Inclusions indispensables (BDD uniquement)
require_once __DIR__ . '/../../config/db.php';

// 2. Sécurité de session : si le stagiaire n'est pas connecté, retour au login
if (!isset($_SESSION['id_stagiaire'])) {
    header('Location: ../login.php');
    exit();
}

$stagiaire_id = intval($_SESSION['id_stagiaire']);

try {
    $sqlStg = "SELECT s.nom, s.prenom, s.filiere, ser.nom_service, ser.nom_encadrant " .
               "FROM stagiaires s " .
               "LEFT JOIN services ser ON s.id_service_affecte = ser.id_service " .
               "WHERE s.id = :id_stagiaire AND (LOWER(s.statut) = 'validé' OR LOWER(s.statut) = 'valide')";
    $stmtStg = $bdd->prepare($sqlStg);
    $stmtStg->execute(['id_stagiaire' => $stagiaire_id]);
    $monProfil = $stmtStg->fetch(PDO::FETCH_ASSOC);

    if (!$monProfil) {
        session_destroy();
        header('Location: ../login.php');
        exit();
    }

} catch (PDOException $e) {
    die("Erreur de synchronisation BDD : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Espace Stagiaire - Accueil</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    :root {
      --primary-gradient: linear-gradient(135deg, #0056b3 0%, #002347 100%);
      --card-shadow: 0 10px 25px rgba(0,0,0,0.05);
      --card-hover-shadow: 0 15px 35px rgba(0, 86, 179, 0.15);
    }

    body {
      margin: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      background: #f4f7fc;
      display: flex;
    }

    /* Header */
    .top-header {
      position: fixed;
      top: 0; left: 0; right: 0;
      height: 65px;
      background: linear-gradient(90deg, #0056b3, #003d80);
      color: #fff;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 2px 8px rgba(0,0,0,0.15);
      z-index: 1000;
    }
    .header-title { font-size: 1.3em; font-weight: bold; margin: 0; letter-spacing: 0.5px; }
    
    /* Icônes à droite */
    .header-icons { 
      position: absolute; 
      right: 25px; 
      display: flex; 
      align-items: center;
      gap: 15px; 
      font-size: 24px; 
    }
    .user-info-top {
      font-size: 0.65em;
      font-weight: 500;
      background: rgba(255,255,255,0.15);
      padding: 6px 12px;
      border-radius: 20px;
    }

    /* Bouton menu */
    .menu-btn {
      position: fixed; top: 15px; left: 15px;
      background: #0056b3; color: #fff;
      padding: 10px 15px; border-radius: 5px;
      cursor: pointer; z-index: 1001;
      font-weight: bold;
      box-shadow: 0 4px 10px rgba(0,0,0,0.1);
    }

    /* Sidebar (Intacte) */
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
      display: flex;
      flex-direction: column;
    }
    .sidebar.show { left: 0; }

    .logo-container {
      text-align: center;
      margin: 40px 0 25px;
    }
    .logo {
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: #fff;
      padding: 6px;
      box-shadow: 0 8px 20px rgba(0,0,0,0.3);
      object-fit: cover;
    }

    /* Liste menu */
    .sidebar ul {
      list-style: none;
      padding: 0;
      margin: 0;
      flex: 1;
    }
    .sidebar ul li { margin: 15px 0; }
    .sidebar ul li a {
      color: #fff;
      text-decoration: none;
      font-weight: bold;
      display: flex;
      align-items: center;
      white-space: nowrap;
      padding: 12px 15px;
      border-radius: 8px;
      transition: background 0.3s;
    }
    .sidebar ul li a i { margin-right: 12px; font-size: 18px; flex-shrink: 0; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.18); }
    .logout { margin-top: auto; }

    /* Contenu principal maximisé et enrichi */
    .main-content {
      flex: 1;
      margin-top: 95px;
      margin-left: 30px;
      margin-right: 30px;
      padding: 10px 20px 40px 20px;
      max-width: 1200px;
      width: 100%;
    }

    /* BANNIÈRE DE BIENVENUE VIVANTE */
    .welcome-card {
      background: var(--primary-gradient);
      color: white;
      padding: 35px;
      border-radius: 18px;
      margin-bottom: 35px;
      box-shadow: 0 10px 25px rgba(0, 86, 179, 0.2);
      display: flex;
      justify-content: space-between;
      align-items: center;
      position: relative;
      overflow: hidden;
    }
    .welcome-card::after {
      content: '\f19d';
      font-family: 'Font Awesome 5 Free';
      font-weight: 900;
      position: absolute;
      right: -20px;
      bottom: -30px;
      font-size: 14em;
      opacity: 0.06;
    }
    .welcome-text h2 { margin: 0 0 10px 0; font-size: 2.1em; font-weight: 700; }
    .welcome-text p { margin: 0 0 18px 0; opacity: 0.9; font-size: 1.1em; }
    .profile-badges { display: flex; flex-wrap: wrap; gap: 12px; }
    .badge-info {
      background: rgba(255, 255, 255, 0.15);
      padding: 8px 16px;
      border-radius: 30px;
      font-size: 0.9em;
      font-weight: 500;
      backdrop-filter: blur(5px);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    /* GRILLE DASHBOARD DYNAMIQUE */
    .section-title {
      font-size: 1.25em;
      font-weight: 700;
      color: #002347;
      margin-bottom: 20px;
      padding-left: 5px;
      border-left: 4px solid #0056b3;
    }
    
    .dashboard {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
      gap: 25px;
    }

    /* CARTES ACTIONS RE-STYLISÉES ET MODERNES */
    .card {
      background: #fff;
      padding: 28px 24px;
      border-radius: 16px;
      box-shadow: var(--card-shadow);
      text-align: left;
      transition: all 0.35s cubic-bezier(0.4, 0, 0.2, 1);
      cursor: pointer;
      border: 1px solid rgba(0,0,0,0.02);
      display: flex;
      flex-direction: column;
      justify-content: space-between;
      position: relative;
    }
    .card:hover { 
      transform: translateY(-8px); 
      box-shadow: var(--card-hover-shadow);
    }

    .card-icon-box {
      width: 55px; height: 55px;
      border-radius: 12px;
      display: flex; align-items: center; justify-content: center;
      margin-bottom: 22px;
    }
    .card i { font-size: 26px; }

    /* Couleurs harmonieuses thématiques par carte */
    .card.taches .card-icon-box { background: #eef2ff; color: #4f46e5; }
    .card.encadrement .card-icon-box { background: #ecfdf5; color: #10b981; }
    .card.rapport .card-icon-box { background: #fff7ed; color: #f97316; }
    .card.resultat .card-icon-box { background: #fdf2f8; color: #db2777; }
    .card.profil .card-icon-box { background: #f0f9ff; color: #0284c7; }

    .card h2 {
      color: #1e293b;
      margin: 0 0 8px 0;
      font-size: 1.25em;
      font-weight: 700;
    }
    .card p {
      font-size: 0.92em;
      color: #64748b;
      margin: 0 0 20px 0;
      line-height: 1.5;
    }
    
    .card-footer-action {
      font-size: 0.85em;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 5px;
      color: #0056b3;
      margin-top: auto;
    }
    .card:hover .card-footer-action i {
      transform: translateX(5px);
    }
    .card-footer-action i {
      transition: transform 0.2s ease;
    }

    @media (max-width: 768px) {
      .welcome-card { padding: 25px; }
      .welcome-text h2 { font-size: 1.7em; }
    }
  </style>
</head>
<body>

  <!-- TOP HEADER ÉPURÉ (SANS CLOCHE) -->
  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-cubes"></i> Mon Portail Stagiaire</h1>
    <div class="header-icons">
      <div class="user-info-top">
        <i class="fas fa-circle-user"></i> <?php echo htmlspecialchars($monProfil['prenom']); ?>
      </div>
    </div>
  </div>

  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <!-- SIDEBAR (COULEURS ET LIENS INTACTS) -->
  <div id="sidebar" class="sidebar">
    <div class="logo-container">
      <img src="../../LOGO.jpeg" alt="Logo" class="logo">
    </div>
    <ul>
      <li><a href="stagiaire.php" class="active"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="taches.php"><i class="fas fa-tasks"></i> Mes tâches</a></li>
       <li><a href="rapport.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
      <li><a href="resultats.PHP"><i class="fas fa-chart-line"></i> Résultats</a></li>
      <li><a href="profil.php"><i class="fas fa-cog"></i> Profil</a></li>
      <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </div>

  <!-- DEBUT DU CONTENU PRINCIPAL -->
  <div class="main-content">
    
    <!-- BANNIÈRE DE BIENVENUE CHALEUREUSE ET PRO -->
    <section class="welcome-card">
      <div class="welcome-text">
        <h2>Ravi de vous revoir, <?php echo htmlspecialchars($monProfil['prenom'] . ' ' . $monProfil['nom']); ?> 👋</h2>
        <p>Votre espace de travail est prêt. Consultez votre progression et gérez vos livrables de stage en toute simplicité.</p>
        
        <div class="profile-badges">
          <div class="badge-info">
            <i class="fas fa-graduation-cap"></i> <?php echo htmlspecialchars($monProfil['filiere']); ?>
          </div>
          <div class="badge-info">
            <i class="fas fa-building"></i> <?php echo htmlspecialchars($monProfil['nom_service'] ?? 'Affectation en cours...'); ?>
          </div>
          <?php if(!empty($monProfil['nom_encadrant'])): ?>
            <div class="badge-info">
              <i class="fas fa-user-tie"></i> Encadrant : <?php echo htmlspecialchars($monProfil['nom_encadrant']); ?>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </section>

    <!-- GRILLE D'ACCÈS DU DASHBOARD INTERACTIF -->
    <h3 class="section-title">Raccourcis de suivi</h3>
    <section class="dashboard">
      
      <div class="card taches" onclick="window.location.href='taches.php'">
        <div>
          <div class="card-icon-box"><i class="fas fa-tasks"></i></div>
          <h2>Mes tâches</h2>
          <p>Visualisez vos missions en cours, l'historique et vos objectifs du jour.</p>
        </div>
        <div class="card-footer-action">Consulter ma feuille <i class="fas fa-arrow-right"></i></div>
      </div>
      <div class="card rapport" onclick="window.location.href='rapport.php'">
        <div>
          <div class="card-icon-box"><i class="fas fa-file-alt"></i></div>
          <h2>Rapports</h2>
          <p>Déposez vos rapports d'étape ou finaux et observez les validations.</p>
        </div>
        <div class="card-footer-action">Déposer un document <i class="fas fa-arrow-right"></i></div>
      </div>

      <div class="card resultat" onclick="window.location.href='resultats.php'">
        <div>
          <div class="card-icon-box"><i class="fas fa-chart-line"></i></div>
          <h2>Résultats</h2>
          <p>Consultez vos notes globales et vos évaluations acquises durant le projet.</p>
        </div>
        <div class="card-footer-action">Voir mes notes <i class="fas fa-arrow-right"></i></div>
      </div>

      <div class="card profil" onclick="window.location.href='profil.php'">
        <div>
          <div class="card-icon-box"><i class="fas fa-user-gear"></i></div>
          <h2>Mon Profil</h2>
          <p>Gérez vos préférences de compte et vos détails d'identification.</p>
        </div>
        <div class="card-footer-action">Configuration <i class="fas fa-arrow-right"></i></div>
      </div>

    </section>
  </div>

  <script>
    function toggleMenu() {
      document.getElementById("sidebar").classList.toggle("show");
    }
  </script>
</body>
</html>