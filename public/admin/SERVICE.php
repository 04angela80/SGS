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
        // Nettoyage des espaces pour éviter les décalages de clés
        $key = trim($row['service']);
        $counts[$key] = $row['total'];
    }

    // 3. REQUÊTE POUR LES MODALES : On récupère les stagiaires validés et on utilise leur 'filiere' comme département
    $stmtStagiaires = $bdd->query("SELECT nom, prenom, service, filiere FROM stagiaires WHERE statut = 'validé' ORDER BY filiere ASC, nom ASC");
    $stagiaires_par_service = [];

    while ($stg = $stmtStagiaires->fetch(PDO::FETCH_ASSOC)) {
        $srv = trim($stg['service']);
        // Si jamais la filière est vide en BDD, on met "General" par sécurité
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

// 🌟 CONFIGURATION ADAPTÉE SANS ACCENTS SUR LES CLÉS DE RECHERCHE
$config_services = [
    "Informatique" => ["icone" => "fas fa-laptop-code", "min" => 10, "desc" => "Developpement web, Reseaux et Securite"],
    "Finance" => ["icone" => "fas fa-coins", "min" => 4, "desc" => "Comptabilite et Audit"],
    "Marketing" => ["icone" => "fas fa-bullhorn", "min" => 4, "desc" => "Digital et Etudes de marche"],
    "Ressources Humaines" => ["icone" => "fas fa-users-cog", "min" => 4, "desc" => "Recrutement et Formation"]
];
// Configuration calquée exactement sur les majuscules de ta base de données
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
  <style>
    /* =======================
       RESET & BASE STYLE
    ======================= */
    * { margin: 0; padding: 0; box-sizing: border-box; }

    body {
      font-family: Arial, sans-serif;
      margin: 0;
      background: #f4f4f9;
      display: flex;
    }

    /* =======================
       HEADER SUPÉRIEUR
    ======================= */
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

    .top-header .header-icons span:hover { transform: scale(1.2); }

    /* Bouton menu sidebar */
    .menu-btn {
      position: fixed; top: 15px; left: 15px;
      background: #0056b3; color: #fff;
      padding: 10px 15px; cursor: pointer;
      border-radius: 5px; z-index: 1000;
      display: inline-flex; align-items: center; gap: 6px;
    }

    /* =======================
       SIDEBAR (BARRE LATÉRALE)
    ======================= */
    .sidebar {
      position: fixed; left: -250px; top: 0;
      width: 250px; height: 100vh;
      background: linear-gradient(180deg, #0056b3, #003d80);
      color: #fff; padding: 20px;
      transition: left 0.5s ease; z-index: 999;
      overflow-y: auto; display: flex; flex-direction: column;
    }

    .sidebar.show { left: 0; }

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

    /* =======================
       CONTENU DU DESIGN SERVICES
    ======================= */
    .main-content {
      flex: 1;
      padding: 40px;
      margin-top: 60px;
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
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-tools"></i> Services</h1>
    <div class="header-icons">
      <span class="notif" style="position: relative; display: inline-block;">
        <?php if ($total_notifs_admin > 0): ?>
          <i class="fas fa-bell" style="color: #ff4d4d; animation: bell-ring 0.4s ease infinite alternate;"></i>
          <span style="position: absolute; top: -8px; right: -8px; background: #ff3333; color: white; border-radius: 50%; padding: 2px 7px; font-size: 12px; font-weight: bold;">
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
       <li><a href="liste.php" ><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="evaluations.html"><i class="fas fa-chart-bar"></i> Évaluation & Résultats</a></li>
      <li><a href="suivi.php"><i class="fas fa-thumbtack"></i> Suivi des taches</a></li>
      <li><a href="rapport.php"><i class="fas fa-thumbtack"></i> Rapports</a></li>
      <li><a href="SERVICE.php"class="active"><i class="fas fa-tools"></i> Services</a></li>
      <li class="logout"><a href="../../public/index.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </aside>

  <main class="main-content">
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
    // Gestion de l'affichage de la barre latérale
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

    // Récupération sécurisée des données préparées par PHP
    const bddStagiaires = <?php echo json_encode($stagiaires_par_service); ?>;
    const configServices = <?php echo json_encode($config_services); ?>;

    // Fonction d'ouverture et de construction de la liste des départements
    function abrirModal(htmlContent) {
      document.getElementById("serviceDetails").innerHTML = htmlContent;
      document.getElementById("modalService").classList.remove("hidden");
    }

    function ouvrirService(serviceName) {
      document.getElementById("serviceTitle").innerText = serviceName;
      
      let detailsHTML = `<p style="font-size: 13px; color: #6b7280; margin-bottom: 15px;">
                          <strong>Quotas de l'equipe :</strong> Capacite minimale fixee a ${configServices[serviceName].min} personnes.
                         </p>`;
      
      detailsHTML += `<div class="departements-box">`;

      // Vérification si le service possède des spécialités et des stagiaires associés
      if (bddStagiaires[serviceName] && Object.keys(bddStagiaires[serviceName]).length > 0) {
          
          for (const dept in bddStagiaires[serviceName]) {
              // Génération dynamique du sous-titre de la filière (sans problème d'accents désormais)
              detailsHTML += `<h4><i class="fas fa-folder-open" style="color: #0056b3; margin-right: 6px;"></i> Specialite : ${dept}</h4><ul>`;
              
              // Liste des noms associés à cette filière
              bddStagiaires[serviceName][dept].forEach(stagiaire => {
                  detailsHTML += `<li><i class="fas fa-user-check"></i> ${stagiaire}</li>`;
              });
              
              detailsHTML += `</ul>`;
          }
      } else {
          // Message si aucun stagiaire n'est validé dans ce service
          detailsHTML += `<p style="font-style: italic; color: #9ca3af; text-align: center; margin-top: 25px; font-size: 14px;">
                            <i class="fas fa-exclamation-circle"></i> Aucun stagiaire valide n'occupe ce service actuellement.
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