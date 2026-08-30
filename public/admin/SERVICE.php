<?php
// 1. Inclusions indispensables (Connexion BDD + Moteur de notifications)
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/notifications_moteur.php';

// ID de l'administrateur par défaut
$admin_id = 1;

// Récupération du nombre de notifications non lues pour la cloche du header
$total_notifs_admin = compterNotificationsNonLues($bdd, $admin_id, 'admin');

try {
    // 2. COMPTEUR DES CARTES : On compte uniquement les stagiaires "validé" dans chaque service
    $stmtCount = $bdd->query("SELECT service, COUNT(*) as total FROM stagiaires WHERE statut = 'validé' GROUP BY service");
    $counts = [];
    while ($row = $stmtCount->fetch(PDO::FETCH_ASSOC)) {
        $key = trim($row['service']);
        $counts[$key] = $row['total'];
    }

    // 3. REQUÊTE POUR LES MODALES
    $stmtStagiaires = $bdd->query("SELECT nom, prenom, service, filiere FROM stagiaires WHERE statut = 'validé' ORDER BY filiere ASC, nom ASC");
    $stagiaires_par_service = [];

    while ($stg = $stmtStagiaires->fetch(PDO::FETCH_ASSOC)) {
        $srv = trim($stg['service']);
        $dept = !empty($stg['filiere']) ? trim($stg['filiere']) : 'General';
        $nomComplet = htmlspecialchars($stg['prenom']) . ' ' . htmlspecialchars(strtoupper($stg['nom']));

        if (!isset($stagiaires_par_service[$srv])) {
            $stagiaires_par_service[$srv] = [];
        }
        if (!isset($stagiaires_par_service[$srv][$dept])) {
            $stagiaires_par_service[$srv][$dept] = [];
        }
        $stagiaires_par_service[$srv][$dept][] = $nomComplet;
    }

} catch (PDOException $e) {
    die("Erreur lors du chargement des données : " . $e->getMessage());
}

// CONFIGURATION DES SERVICES
$config_services = [
    "Informatique" => ["icone" => "fas fa-laptop-code", "min" => 10, "desc" => "Developpement, Reseaux et Securite"],
    "Finance" => ["icone" => "fas fa-coins", "min" => 4, "desc" => "Comptabilite et Audit"],
    "Marketing" => ["icone" => "fas fa-bullhorn", "min" => 4, "desc" => "Digital et Etudes de marche"],
    "Ressources Humaines" => ["icone" => "fas fa-users-cog", "min" => 4, "desc" => "Recrutement et Formation"]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>SGS - Services</title>
  <link rel="stylesheet" href="style.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    /* =======================
       RESET & BASE STYLE
    ======================= */
    * { margin: 0; padding: 0; box-sizing: border-box; }
    
    body { 
      margin: 0; 
      font-family: 'Segoe UI', Arial, sans-serif; 
      background: #f8fafc; 
      color: #334155;
      overflow-x: hidden; 
    }
    
    /* HEADER FIXE */
    .top-header { 
      position: fixed; 
      top: 0; left: 0; right: 0; 
      height: 60px; 
      background: linear-gradient(90deg, #0056b3, #003d80); 
      color: #fff; 
      display: flex; 
      align-items: center; 
      padding: 0 20px; 
      box-shadow: 0 2px 8px rgba(0,0,0,0.2); 
      z-index: 1000; 
    }

    /* BOUTON MENU */
    .menu-btn { 
      font-weight: bold;
      color: #fff; 
      cursor: pointer; 
      display: inline-flex; 
      align-items: center; 
      gap: 8px; 
      font-size: 1.1em;
      user-select: none;
      z-index: 1001;
      margin-right: auto;
    }

    .top-header .header-title { 
      font-size: 1.4em; 
      font-weight: bold; 
      margin: 0; 
      letter-spacing: 1px; 
      position: absolute; 
      left: 50%; 
      transform: translateX(-50%); 
    }

    .top-header .header-icons { 
      position: absolute; 
      right: 20px; 
      display: flex; 
      gap: 15px; 
      font-size: 22px; 
      cursor: pointer; 
    }
    .top-header .header-icons span:hover { transform: scale(1.1); }
    .bell-count { 
      position: absolute; 
      top: -5px; 
      right: -7px; 
      background: #ff4d4d; 
      color: white; 
      font-size: 11px; 
      padding: 1px 5px; 
      border-radius: 10px; 
      font-weight: bold; 
    }

    /* SIDEBAR CORRIGÉE : Complètement vide lorsqu'elle est fermée */
    .sidebar { 
      position: fixed; 
      left: -225px; /* Laisse dépasser exactement 45px de bande bleue pure */
      top: 0; 
      width: 270px; /* Élargie à 270px pour éviter que le blanc ne coupe les écritures */
      height: 100vh; 
      background: linear-gradient(180deg, #0056b3, #003d80); 
      color: #fff; 
      padding: 15px 20px; 
      transition: left 0.4s ease; 
      z-index: 999; 
      display: flex; 
      flex-direction: column; 
      overflow: hidden; 
    }
    
    /* Quand on ouvre le menu */
    .sidebar.show { left: 0; }
    
    /* LOGO : S'affiche uniquement quand le menu est ouvert */
    .logo-container { 
      margin-top: 25px; 
      margin-bottom: 20px; 
      text-align: center; 
      transition: opacity 0.3s;
      opacity: 0; 
    }
    .sidebar.show .logo-container { opacity: 1; }

    .logo { 
      width: 90px; 
      height: 90px; 
      border-radius: 50%; 
      background: #fff; 
      padding: 6px; 
      box-shadow: 0 8px 20px rgba(0,0,0,0.3); 
    }

    /* LISTE DES MENUS ET CONTENUS TOTALEMENT CACHÉS QUAND FERMÉ */
    .sidebar ul { 
      list-style: none; 
      padding: 0; 
      margin: 0; 
      opacity: 0; /* Totalement invisible par défaut */
      transition: opacity 0.2s ease;
    }
    /* Devient visible uniquement quand la sidebar est ouverte */
    .sidebar.show ul { 
      opacity: 1; 
    }

    .sidebar ul li { margin: 12px 0; } 
    
    .sidebar ul li a { 
      color: #fff; 
      text-decoration: none; 
      font-weight: bold; 
      display: flex; 
      align-items: center; 
      white-space: nowrap; 
      padding: 12px 20px; 
      font-size: 15px; 
      border-radius: 8px; 
      transition: background 0.3s; 
    }
    
    .sidebar ul li a i { 
      margin-right: 15px; 
      font-size: 20px; 
      flex-shrink: 0; 
    }

    .sidebar ul li a:hover, .sidebar ul li a.active { 
      background: rgba(255,255,255,0.2); 
    }

    .logout { margin-top: 5px; }

    /* FOOTER DU MENU */
    .sidebar-footer {
      margin-top: auto;
      text-align: center;
      padding-bottom: 15px;
      opacity: 0;
      transition: opacity 0.2s;
    }
    .sidebar.show .sidebar-footer { opacity: 1; }

    .sidebar-divider { height: 1.5px; background: #ffffff; margin: 8px 0; border: none; }
    .footer-text { font-size: 12px; color: #ffffff; font-weight: 600; letter-spacing: 0.5px; line-height: 1.4; }
    .footer-sub { font-size: 10px; display: block; font-weight: 400; color: #f1f5f9; margin-top: 2px; }

    /* CONTENU PRINCIPAL */
    .main-content {
      margin-top: 90px;
      margin-left: 75px; 
      margin-right: 30px;
      padding: 10px 20px 40px 20px;
      transition: none; 
    }

    .services-hero {
      padding: 20px 0;
      display: flex;
      justify-content: center;
    }

    .services-container {
      width: 100%;
      max-width: 1100px;
      background: linear-gradient(135deg, #38bdf8, #7c3aed, #c084fc);
      border-radius: 36px;
      padding: 50px 40px;
      box-shadow: 0 30px 60px rgba(124, 58, 237, 0.3);
    }

    .header-services {
      text-align: center;
      margin-bottom: 40px;
      color: #ffffff;
    }

    .header-services h1 { font-size: 32px; font-weight: 800; margin-bottom: 10px; }
    .header-services p { opacity: 0.9; font-size: 15px; }

    .cards-services {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
      gap: 25px;
    }

    .card-service {
      background: rgba(255,255,255,0.96);
      border-radius: 24px;
      padding: 30px 20px;
      text-align: center;
      box-shadow: 0 20px 40px rgba(0,0,0,0.15);
      transition: all 0.3s ease;
      cursor: pointer;
    }

    .card-service:hover {
      transform: translateY(-8px);
      box-shadow: 0 30px 50px rgba(0,0,0,0.25);
    }

    .card-service h2 { font-size: 20px; color: #4c1d95; margin-bottom: 12px; }
    .card-service p { font-size: 13px; color: #4b5563; margin-bottom: 12px; }
    .min-stagiaires { font-weight: bold; color: #7c3aed; font-size: 13px; margin-bottom: 10px; }
    
    .badge-count {
      background: #7c3aed; color: white; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: bold; display: inline-block;
    }

    /* =======================
       FENÊTRE MODALE DYNAMIQUE
    ======================= */
    .modal {
      position: fixed;
      top: 0; left: 0; width: 100%; height: 100%;
      background: rgba(0,0,0,0.6);
      display: flex; justify-content: center; align-items: center;
      z-index: 2000;
    }
    .modal.hidden { display: none; }
    
    .modal-content {
      background: #fff; padding: 30px; border-radius: 20px; width: 100%; max-width: 500px; max-height: 85vh; overflow-y: auto; box-shadow: 0 20px 40px rgba(0,0,0,0.3);
    }
    
    .close { float: right; cursor: pointer; font-size: 26px; color: #6b7280; font-weight: bold; }
    .close:hover { color: #dc2626; }

    /* Sections des départements dans la modale */
    .departements-box { margin-top: 20px; }
    .departements-box h4 { font-size: 15px; font-weight: 700; color: #0056b3; border-bottom: 2px solid #e5e7eb; padding-bottom: 6px; margin-bottom: 10px; margin-top: 15px; text-transform: uppercase; letter-spacing: 0.5px; }
    .departements-box ul { list-style: none; padding: 0; }
    .departements-box ul li { background: #f3f4f6; margin-bottom: 6px; padding: 10px 12px; border-radius: 8px; font-size: 14px; color: #111827; display: flex; align-items: center; gap: 8px; font-weight: 500; }
    .departements-box ul li i { color: #10b981; }

    /* Animation de la cloche */
    @keyframes bell-ring {
      0% { transform: rotate(-15deg); }
      100% { transform: rotate(15deg); }
    }
  </style>
</head>
<body>

<div class="top-header">
  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>
  
  <h1 class="header-title"><i class="fas fa-tools"></i> Nos Services</h1>
  
  <div class="header-icons">
    <span class="admin"><i class="fas fa-user-shield"></i></span>
  </div>
</div>

<aside class="sidebar" id="sidebar">
  <div class="logo-container">
    <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo">
  </div>
  <ul>
      <li><a href="liste.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="suivi.php"><i class="fas fa-chart-bar"></i>Suivi des stagiaires</a></li>
      <li><a href="rapport.php"><i class="fas fa-thumbtack"></i>Rapports</a></li>
      <li><a href="evaluations.php"><i class="fas fa-file-alt"></i>Évaluation & Résultats</a></li>
      <li><a href="SERVICE.php"class="active"><i class="fas fa-tools"></i> Services</a></li>
      <li class="logout"><a href="../../public/index.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  <div class="sidebar-footer">
    <hr class="sidebar-divider">
    <div class="footer-text">
      <span><i class="fas fa-user-shield"></i> SGS • Admin</span>
      <span class="footer-sub">Système de Gestion des Stagiaires@2026</span>
    </div>
  </div>
</aside>

<main class="main-content" id="main-content">
  <section class="services-hero">
    <div class="services-container">
      <header class="header-services">
        <h1><i class="fas fa-cogs"></i> Structure des Services</h1>
        <p>Consultez l'état d'occupation et la répartition de vos stagiaires par spécialité</p>
      </header>

      <div class="cards-services">
        <?php foreach ($config_services as $nom_service => $details): ?>
          <?php $total_inscrits = isset($counts[$nom_service]) ? $counts[$nom_service] : 0; ?>
          
          <div class="card-service" onclick="ouvrirService('<?php echo htmlspecialchars(addslashes($nom_service)); ?>')">
            <h2><i class="<?php echo $details['icone']; ?>"></i> <?php echo htmlspecialchars($nom_service); ?></h2>
            <p><?php echo htmlspecialchars($details['desc']); ?></p>
            <p class="min-stagiaires">Minimum requis : <?php echo $details['min']; ?> stagiaires</p>
            <span class="badge-count"><?php echo $total_inscrits; ?> Affecte(s)</span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
</main>

<div id="modalService" class="modal hidden">
  <div class="modal-content">
    <span class="close" onclick="fermerModalService()">&times;</span>
    <h2 id="serviceTitle" style="color: #4c1d95; margin-bottom: 10px;"></h2>
    <div id="serviceDetails"></div>
  </div>
</div>

<script>
  const sidebar = document.getElementById('sidebar');

  function toggleMenu() {
    sidebar.classList.toggle('show');
  }

  document.addEventListener('click', (event) => {
    const menuBtn = document.querySelector('.menu-btn');
    const isClickInside = sidebar.contains(event.target) || menuBtn.contains(event.target);
    if (!isClickInside && sidebar.classList.contains('show')) {
      toggleMenu();
    }
  });

  const bddStagiaires = <?php echo json_encode($stagiaires_par_service); ?>;
  const configServices = <?php echo json_encode($config_services); ?>;

  function abrirModal(htmlContent) {
    document.getElementById("serviceDetails").innerHTML = htmlContent;
    document.getElementById("modalService").classList.remove("hidden");
  }

  function ouvrirService(serviceName) {
    document.getElementById("serviceTitle").innerText = serviceName;
    
    let detailsHTML = `<p style="font-size: 13px; color: #6b7280; margin-bottom: 15px;">
                        <strong>Quotas de l'équipe :</strong> Capacité minimale fixée à ${configServices[serviceName].min} personnes.
                       </p>`;
    
    detailsHTML += `<div class="departements-box">`;

    if (bddStagiaires[serviceName] && Object.keys(bddStagiaires[serviceName]).length > 0) {
        for (const dept in bddStagiaires[serviceName]) {
            detailsHTML += `<h4><i class="fas fa-folder-open" style="color: #0056b3; margin-right: 6px;"></i> Spécialité : ${dept}</h4><ul>`;
            
            bddStagiaires[serviceName][dept].forEach(stagiaire => {
                detailsHTML += `<li><i class="fas fa-user-check"></i> ${stagiaire}</li>`;
            });
            
            detailsHTML += `</ul>`;
        }
    } else {
        detailsHTML += `<p style="font-style: italic; color: #9ca3af; text-align: center; margin-top: 25px; font-size: 14px;">
                          <i class="fas fa-exclamation-circle"></i> Aucun stagiaire validé n'occupe ce service actuellement.
                        </p>`;
    }
    
    detailsHTML += `</div>`;
    abrirModal(detailsHTML);
  }

  function fermerModalService() {
    document.getElementById('modalService').classList.add('hidden');
  }
</script>
</body>
</html>