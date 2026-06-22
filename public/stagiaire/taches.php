<?php
// 1. DÉMARRAGE DE LA SESSION (Indispensable pour gérer le poste partagé)
session_start();

// 2. Connexion à la base de données
require_once __DIR__ . '/../../config/db.php';

// SÉCURITÉ ABSOLUE : Si aucun stagiaire n'est connecté à cette session
if (!isset($_SESSION['id_stagiaire'])) {
    header('Location: ../../public/index.php');
    exit();
}

// Récupération de l'ID unique du stagiaire connecté actuellement sur l'ordinateur
$id_stagiaire_connecte = $_SESSION['id_stagiaire'];

try {
    // Récupération des infos minimales pour la sécurité du statut
    $sqlStg = "SELECT s.id 
               FROM stagiaires s
               WHERE s.id = :id_stagiaire AND (LOWER(s.statut) = 'validé' OR LOWER(s.statut) = 'valide')";
    
    $stmtStg = $bdd->prepare($sqlStg);
    $stmtStg->execute(['id_stagiaire' => $id_stagiaire_connecte]);
    $monProfil = $stmtStg->fetch(PDO::FETCH_ASSOC);

    if (!$monProfil) {
        session_destroy();
        header('Location: ../../public/index.php');
        exit();
    }

    // Récupération de TOUTES SES tâches enregistrées pour le suivi depuis la table 'taches'
    $sqlT = "SELECT description, statut, date_attribution FROM taches WHERE stagiaire_id = :id_stg ORDER BY date_attribution DESC";
    $stmtT = $bdd->prepare($sqlT);
    $stmtT->execute(['id_stg' => $id_stagiaire_connecte]);
    $mesTaches = $stmtT->fetchAll(PDO::FETCH_ASSOC);

    // Calcul dynamique des compteurs pour habiller le haut de la page
    $totalTaches = count($mesTaches);
    $tachesEnCours = 0;
    $tachesTerminees = 0;

    foreach ($mesTaches as $t) {
        $st = strtolower(trim($t['statut']));
        if ($st === 'terminé' || $st === 'termine' || $st === 'done') {
            $tachesTerminees++;
        } else {
            $tachesEnCours++;
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
  <title>Espace Stagiaire - Mes Tâches</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { 
      margin: 0; 
      font-family: 'Segoe UI', -apple-system, BlinkMacSystemFont, Roboto, sans-serif; 
      background: #f4f7fc; 
    }
    
    /* HEADER BANNER */
    .top-header { 
      position: fixed; 
      top: 0; left: 0; right: 0; 
      height: 65px; 
      background: linear-gradient(90deg, #0056b3, #003d80); 
      color: #fff; 
      display: flex; align-items: center; justify-content: center; 
      padding: 0 20px; 
      box-shadow: 0 2px 10px rgba(0,0,0,0.15); 
      z-index: 1000; 
    }
    .top-header .header-title { 
      font-size: 1.3em; 
      font-weight: bold; 
      margin: 0; 
      letter-spacing: 0.5px;
    }
    
    /* BARRE LATÉRALE (SIDEBAR INTACTE) */
    .menu-btn { 
      position: fixed; top: 16px; left: 15px; 
      background: #0056b3; color: #fff; 
      padding: 10px 15px; cursor: pointer; 
      border-radius: 5px; z-index: 1001; 
      display: inline-flex; align-items: center; gap: 6px; 
      font-weight: bold;
    }
    .sidebar { 
      position: fixed; left: -250px; top: 0; width: 250px; height: 100vh; 
      background: linear-gradient(180deg, #0056b3, #003d80); color: #fff; 
      padding: 20px; transition: left 0.5s ease; z-index: 999; 
      overflow-y: auto; display: flex; flex-direction: column; 
    }
    .sidebar.show { left: 0; }
    .logo-container { margin-top: 40px; margin-bottom: 25px; text-align: center; }
    .logo { width: 90px; height: 90px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
    .sidebar ul { list-style: none; padding: 0; margin: 0; flex: 1; }
    .sidebar ul li { margin: 15px 0; }
    .sidebar ul li a { color: #fff; text-decoration: none; font-weight: bold; display: flex; align-items: center; padding: 12px 15px; border-radius: 8px; transition: background 0.3s; }
    .sidebar ul li a i { margin-right: 12px; font-size: 18px; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.18); }
    .logout { margin-top: auto; }

    /* CONTENEUR PRINCIPAL */
    .container { 
      padding: 110px 30px 60px 30px; 
      max-width: 900px; 
      margin: 0 auto; 
      width: 100%;
      box-sizing: border-box;
    }

    /* MINI COMPTEURS DE PROGRESSION */
    .mini-stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
      gap: 20px;
      margin-bottom: 30px;
    }
    .stat-mini-card {
      background: white;
      padding: 18px 20px;
      border-radius: 12px;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.03);
      display: flex;
      align-items: center;
      justify-content: space-between;
      border: 1px solid #e2e8f0;
    }
    .stat-mini-info h5 { margin: 0; color: #64748b; font-size: 0.85em; text-transform: uppercase; letter-spacing: 0.5px; }
    .stat-mini-info p { margin: 4px 0 0 0; font-size: 1.6em; font-weight: 700; color: #1e293b; }
    .stat-mini-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 18px; }
    
    .stat-mini-card.total .stat-mini-icon { background: #eff6ff; color: #2563eb; }
    .stat-mini-card.progress .stat-mini-icon { background: #fff7ed; color: #ea580c; }
    .stat-mini-card.completed .stat-mini-icon { background: #ecfdf5; color: #16a34a; }

    /* CONSEILS DE STAGE (Habille superbement la page sans alourdir) */
    .tips-box {
      background: #f8fafc;
      border: 1px dashed #cbd5e1;
      padding: 18px 22px;
      border-radius: 12px;
      margin-bottom: 35px;
      display: flex;
      align-items: flex-start;
      gap: 15px;
    }
    .tips-box i { font-size: 22px; color: #0056b3; margin-top: 2px; }
    .tips-content h4 { margin: 0 0 4px 0; color: #0f172a; font-size: 1em; font-weight: 600; }
    .tips-content p { margin: 0; color: #64748b; font-size: 0.9em; line-height: 1.5; }

    /* TITRE DE SECTION MODERNISÉ */
    .section-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 20px;
      padding-bottom: 12px;
      border-bottom: 2px solid #e2e8f0;
    }
    .section-title { 
      color: #0f172a; 
      font-size: 1.3em; 
      display: flex; 
      align-items: center; 
      gap: 10px; 
      font-weight: 700; 
      margin: 0;
    }

    /* CONTENEUR DE CARTES PANNEAUX */
    .tasks-container { 
      display: flex; 
      flex-direction: column; 
      gap: 16px; 
    }
    
    /* INTERFACE CARTE PRO DE MISSION */
    .task-item { 
      background: white; 
      padding: 24px; 
      border-radius: 14px; 
      box-shadow: 0 4px 18px rgba(15, 23, 42, 0.04); 
      display: flex; 
      align-items: center; 
      justify-content: space-between; 
      transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
      border: 1px solid rgba(0,0,0,0.01);
    }
    .task-item:hover { 
      transform: translateY(-3px); 
      box-shadow: 0 10px 25px rgba(0, 86, 179, 0.08);
    }
    
    .task-content-block { display: flex; align-items: center; gap: 20px; width: 80%; }
    .task-badge-icon { 
      width: 46px; height: 46px; 
      border-radius: 12px; 
      display: flex; align-items: center; justify-content: center; 
      font-size: 18px; flex-shrink: 0; 
    }
    
    /* État En cours */
    .task-item.todo { border-left: 5px solid #f59e0b; }
    .task-item.todo .task-badge-icon { background: #fef3c7; color: #d97706; }
    
    /* État Terminé */
    .task-item.done { border-left: 5px solid #10b981; background: #f8fafc; opacity: 0.85; }
    .task-item.done .task-badge-icon { background: #d1fae5; color: #059669; }
    .task-item.done .task-title-text { text-decoration: line-through; color: #64748b; font-weight: 500; }

    /* Textes de la Tâche */
    .task-text-info { text-align: left; }
    .task-text-info h4 { margin: 0 0 6px 0; font-size: 1.15em; color: #1e293b; line-height: 1.4; font-weight: 600; }
    .task-meta-date { margin: 0; font-size: 0.88em; color: #64748b; display: flex; align-items: center; gap: 6px; }
    
    /* Pilules de Statuts */
    .status-pill { 
      padding: 6px 14px; 
      border-radius: 30px; 
      font-weight: 600; 
      font-size: 0.78em; 
      text-transform: uppercase; 
      letter-spacing: 0.5px; 
      white-space: nowrap;
    }
    .status-pill.todo { background: #fff7ed; color: #ea580c; border: 1px solid #ffedd5; }
    .status-pill.done { background: #ecfdf5; color: #047857; border: 1px solid #d1fae5; }

    /* Design Vide Épuré */
    .empty-state {
      background: white;
      padding: 50px 30px;
      border-radius: 14px;
      text-align: center;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
      color: #64748b;
    }
    .empty-state i { font-size: 3.5em; color: #cbd5e1; margin-bottom: 15px; }
    .empty-state p { margin: 0; font-weight: 600; font-size: 1.1em; }
  </style>
</head>
<body>

  <!-- HEADER UNIQUE -->
  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-tasks"></i> Suivi de mes Activités</h1>
  </div>

  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <!-- MENU LATÉRAL CONSERVÉ INTACT -->
  <aside class="sidebar" id="sidebar">
    <div class="logo-container"><img src="../../LOGO.jpeg" alt="Logo" class="logo"></div>
    <ul>
       <li><a href="stagiaire.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="taches.php"class="active"><i class="fas fa-tasks"></i> Mes tâches</a></li>
      <li><a href="rapport.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
      <li><a href="resultats.php"><i class="fas fa-chart-line"></i> Résultats</a></li>
      <li><a href="profil.php"><i class="fas fa-cog"></i> Profil</a></li>
      <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
  </aside>

  <!-- ESPACE DE TRAVAIL CENTRAL -->
  <main class="container">

    <!-- COMPTEURS DYNAMIQUES (Remplissent le vide de manière utile) -->
    <section class="mini-stats-grid">
      <div class="stat-mini-card total">
        <div class="stat-mini-info">
          <h5>Total Missions</h5>
          <p><?php echo $totalTaches; ?></p>
        </div>
        <div class="stat-mini-icon"><i class="fas fa-folder"></i></div>
      </div>
      <div class="stat-mini-card progress">
        <div class="stat-mini-info">
          <h5>En cours</h5>
          <p><?php echo $tachesEnCours; ?></p>
        </div>
        <div class="stat-mini-icon"><i class="fas fa-spinner fa-spin"></i></div>
      </div>
      <div class="stat-mini-card completed">
        <div class="stat-mini-info">
          <h5>Terminées</h5>
          <p><?php echo $tachesTerminees; ?></p>
        </div>
        <div class="stat-mini-icon"><i class="fas fa-check-circle"></i></div>
      </div>
    </section>

    <!-- PETIT BLOC ASTUCE/MEMENTO COHÉRENT -->
    <div class="tips-box">
      <i class="fas fa-lightbulb"></i>
      <div class="tips-content">
        <h4>Note de suivi quotidien</h4>
        <p>Toutes les tâches affichées ci-dessous vous sont assignées par votre tuteur de stage. Une fois une tâche accomplie sur le terrain, informez-en directement votre encadrant pour qu'il puisse valider son statut en direct.</p>
      </div>
    </div>
    
    <!-- SECTION FEUILLE DE ROUTE -->
    <div class="section-header">
      <h3 class="section-title"><i class="fas fa-clipboard-list" style="color: #0056b3;"></i> Feuille de route du stage</h3>
    </div>

    <div class="tasks-container">
      <?php if(empty($mesTaches)): ?>
        <div class="empty-state">
          <i class="fas fa-folder-open"></i>
          <p>Aucune tâche ne vous a encore été assignée pour le moment.</p>
        </div>
      <?php else: ?>
        <?php foreach($mesTaches as $tache): 
          $statutNettoye = strtolower(trim($tache['statut']));
          $estFait = ($statutNettoye === 'terminé' || $statutNettoye === 'termine' || $statutNettoye === 'done');
          $classeDesign = $estFait ? 'done' : 'todo';
          $iconeIndicateur = $estFait ? 'fa-check' : 'fa-spinner fa-pulse';
        ?>
          <div class="task-item <?php echo $classeDesign; ?>">
            <div class="task-content-block">
              <div class="task-badge-icon">
                <i class="fas <?php echo $iconeIndicateur; ?>"></i>
              </div>
              <div class="task-text-info">
                <h4 class="task-title-text"><?php echo htmlspecialchars($tache['description']); ?></h4>
                <p class="task-meta-date">
                  <i class="far fa-calendar-alt"></i> Planifiée le <?php echo date('d/m/Y', strtotime($tache['date_attribution'])); ?>
                </p>
              </div>
            </div>
            <div>
              <span class="status-pill <?php echo $classeDesign; ?>">
                <?php echo $estFait ? 'Terminée' : 'En cours'; ?>
              </span>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

  </main>

  <script>
    function toggleMenu() { document.getElementById('sidebar').classList.toggle('show'); }
  </script>
</body>
</html>