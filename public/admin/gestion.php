<?php
require_once '../../config/db.php';
require_once __DIR__ . '/../../config/notifications_moteur.php';

// Traitement direct au clic sur l'action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];
    
    $nouveau_statut = '';
    if ($action === 'valider') $nouveau_statut = 'Validé';
    if ($action === 'refuser') $nouveau_statut = 'Refusé';
    if ($action === 'attente') $nouveau_statut = 'En attente';
    
    if (!empty($nouveau_statut)) {
        
        // Logique d'affectation automatique conservée intacte
        if ($nouveau_statut === 'Validé') {
            $reqStg = $bdd->prepare("SELECT service FROM stagiaires WHERE id = ?");
            $reqStg->execute([$id]);
            $service_demande = $reqStg->fetchColumn();
            
            $reqService = $bdd->prepare("SELECT id_service FROM services WHERE description = ? LIMIT 1");
            $reqService->execute([$service_demande]);
            $id_service_trouve = $reqService->fetchColumn();
            
            $update = $bdd->prepare("UPDATE stagiaires SET statut = ?, id_service_affecte = ? WHERE id = ?");
            $update->execute([$nouveau_statut, $id_service_trouve, $id]);

            $messageValidation = 'Votre dossier a été validé. '; 
            if (!empty($service_demande)) {
                $messageValidation .= 'Vous êtes affecté au service ' . htmlspecialchars($service_demande, ENT_QUOTES, 'UTF-8') . '.';
            }
            ajouterNotification($bdd, $id, 'stagiaire', 'Dossier validé', $messageValidation, 'non_lu');
        } else {
            $update = $bdd->prepare("UPDATE stagiaires SET statut = ?, id_service_affecte = NULL WHERE id = ?");
            $update->execute([$nouveau_statut, $id]);

            $messageAdmin = ($nouveau_statut === 'Refusé')
                ? 'Votre dossier a été refusé. Veuillez contacter l’administration pour plus de détails.'
                : 'Votre dossier reste en attente de validation. Nous vous informerons dès qu’il sera traité.';
            ajouterNotification($bdd, $id, 'stagiaire', 'Statut de dossier mis à jour', $messageAdmin, 'non_lu');
        }
    }
    
    header("Location: gestion.php");
    exit();
}

$queryStagiaires = $bdd->query("SELECT * FROM stagiaires ORDER BY id DESC");
$liste_stagiaires = $queryStagiaires->fetchAll();

$admin_id = 1;
$total_notifs_admin = compterNotificationsNonLues($bdd, $admin_id, 'admin');
$alertes_admin = recupererNotificationsRecentes($bdd, $admin_id, 'admin', 5);
?>

<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Gestion de dossiers d'inscription des stagiaires</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #334155; }
    
    /* HEADER (Totalement intact) */
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
      overflow-y: hidden; /* Supprime la barre noire de défilement */
      display: flex; 
      flex-direction: column; 
    }
    .sidebar.show { left: 0; }
    .logo-container { margin-top: 30px; margin-bottom: 20px; text-align: center; }
    .logo { width: 90px; height: 90px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
    
    /* MODIFICATION ICI : On réduit l'espace pour que tout remonte */
    .sidebar ul { list-style: none; padding: 0; margin: 0; }
    .sidebar ul li { margin: 12px 0; } /* Liens un peu plus serrés pour gagner de la place */
    .sidebar ul li a { color: #fff; text-decoration: none; font-weight: bold; display: flex; align-items: center; white-space: nowrap; padding: 10px 15px; border-radius: 6px; transition: background 0.3s; }
    .sidebar ul li a i { margin-right: 8px; font-size: 18px; flex-shrink: 0; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.2); }
    
    /* ON ENLÈVE LE MARGIN-TOP AUTO QUI CASSAIT TOUT */
    .logout { margin-top: 5px; } 

    /* LE STYLE DU FOOTER NET ET BIEN VISIBLE */
    .sidebar-footer {
      margin-top: 15px;
      text-align: center;
      padding-bottom: 50px; /* Force le texte à remonter bien au-dessus de la barre des tâches Windows */
    }
    .sidebar-divider { height: 1.5px; background: #ffffff; margin: 10px 0; border: none; }
    .footer-text { font-size: 13px; color: #ffffff; font-weight: 600; letter-spacing: 0.5px; line-height: 1.4; }
    .footer-sub { font-size: 11px; display: block; font-weight: 400; color: #f1f5f9; margin-top: 2px; }
    /* CONTENEUR UNIQUE NET ET PRO */
    .container { padding: 100px 40px 40px 40px; max-width: 1200px; margin: 0 auto; }
    
    /* EN-TÊTE DE LA ZONE DE TRAVAIL */
    .page-action-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
    .page-action-bar h2 { font-size: 1.4em; color: #1e3a8a; margin: 0; display: flex; align-items: center; gap: 8px; }
    
    /* BOUTON EXPORT SIMPLE */
    .btn-export { background: #fff; color: #475569; border: 1px solid #cbd5e1; padding: 8px 14px; border-radius: 6px; cursor: pointer; font-weight: 600; font-size: 0.9em; display: inline-flex; align-items: center; gap: 6px; transition: all 0.2s; }
    .btn-export:hover { background: #f1f5f9; border-color: #94a3b8; }

    /* TABLEAU CLASSIQUE MODERNISÉ (FLAT ET ÉPURÉ) */
    .table-wrapper { background: #ffffff; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.03); border: 1px solid #e2e8f0; overflow: hidden; }
    .dossier-table { width: 100%; border-collapse: collapse; text-align: left; }
    
    /* Titres des colonnes */
    .dossier-table th { background: #0056b3; color: #ffffff; font-weight: 600; padding: 16px 18px; font-size: 0.9em; text-transform: uppercase; letter-spacing: 0.5px; }
    
    /* Lignes du tableau */
    .dossier-table td { padding: 15px 18px; border-bottom: 1px solid #f1f5f9; font-size: 0.95em; vertical-align: middle; }
    .dossier-table tr:last-child td { border-bottom: none; }
    .dossier-table tr:hover { background-color: #f8fafc; }
    
    /* Badges de statuts minimalistes */
    .badge { padding: 4px 10px; border-radius: 6px; font-weight: 600; font-size: 0.85em; display: inline-flex; align-items: center; gap: 5px; }
    .badge.valide { background: #d1fae5; color: #065f46; }
    .badge.refuse { background: #fee2e2; color: #991b1b; }
    .badge.attente { background: #fef3c7; color: #92400e; }

    /* Boutons d'action minces et élégants */
    .btn-act { padding: 6px 12px; border: none; border-radius: 4px; cursor: pointer; display: inline-flex; align-items: center; gap: 4px; text-decoration: none; color: white; font-size: 12px; font-weight: 600; transition: background 0.2s; }
    .btn-c { background: #10b981; } .btn-c:hover { background: #059669; }
    .btn-r { background: #ef4444; } .btn-r:hover { background: #dc2626; }
    .btn-a { background: #f59e0b; } .btn-a:hover { background: #d97706; }

    .no-data { text-align: center; color: #94a3b8; padding: 40px !important; font-weight: 500; }
    @keyframes bell-ring { 0% { transform: rotate(-15deg); } 100% { transform: rotate(15deg); } }
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

  <!-- HEADER -->
  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-users"></i> Gestion de dossiers d'inscription</h1>
    <div class="header-icons">
      <span class="admin"><i class="fas fa-user-shield"></i></span>
    </div>
  </div>

  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <!-- MENU LATÉRAL -->
  <aside class="sidebar" id="sidebar">
    <div class="logo-container">
      <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo">
    </div>
    <ul>
       <li><a href="liste.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"class="active"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="suivi.php"><i class="fas fa-chart-bar"></i>Suivi des stagiaires</a></li>
      <li><a href="rapport.php"><i class="fas fa-thumbtack"></i>Rapports</a></li>
      <li><a href="evaluations.php"><i class="fas fa-file-alt"></i>Évaluation & Résultats</a></li>
      <li><a href="SERVICE.php"><i class="fas fa-tools"></i> Services</a></li>
      <li class="logout"><a href="../../public/index.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
    <!-- Le footer est maintenant placé ici, en dehors de la liste, pour remonter proprement -->
    <div class="sidebar-footer">
      <hr class="sidebar-divider">
      <div class="footer-text">
        <span><i class="fas fa-user-shield"></i> SGS • Admin</span>
        <span class="footer-sub">Systeme de Gestion des Stagiaires@2026</span>
      </div>
    </div>
  </aside>

  <!-- CONTENU PRINCIPAL -->
  <main class="container">
    
    <div class="page-action-bar">
      <h2><i class="fas fa-folder-open" style="color: #0056b3;"></i> Demandes d'inscription reçues</h2>
      <button class="btn-export" onclick="exportCSV()"><i class="fas fa-file-export"></i> Exporter en CSV</button>
    </div>
    
    <div class="table-wrapper">
      <table class="dossier-table">
        <thead>
          <tr>
            <th>Nom</th>
            <th>Prénom</th>
            <th>Adresse e-mail</th>
            <th>Service Demandé</th>
            <th>Statut actuel</th>
            <th style="text-align: center;">Actions</th>
          </tr>
        </thead>
        <tbody id="dossierBody">
          <?php if (count($liste_stagiaires) > 0): ?>
              <?php foreach ($liste_stagiaires as $row): ?>
              <tr>
                <td style="font-weight: 600; color: #1e293b;"><?php echo htmlspecialchars($row['nom']); ?></td>
                <td><?php echo htmlspecialchars($row['prenom']); ?></td>
                <td style="color: #64748b;"><?php echo htmlspecialchars($row['email']); ?></td>
                <td style="font-weight: 500; color: #0056b3;"><?php echo !empty($row['service']) ? htmlspecialchars($row['service']) : 'Non spécifié'; ?></td>
                <td>
                  <?php 
                  if ($row['statut'] === 'Validé') echo "<span class='badge valide'><i class='fas fa-check'></i> Validé</span>";
                  elseif ($row['statut'] === 'Refusé') echo "<span class='badge refuse'><i class='fas fa-times'></i> Refusé</span>";
                  else echo "<span class='badge attente'><i class='fas fa-hourglass-half'></i> En attente</span>";
                  ?>
                </td>
                <td style="text-align: center; white-space: nowrap;">
                  <a href="gestion.php?action=valider&id=<?php echo $row['id']; ?>" class="btn-act btn-c"><i class="fas fa-check"></i> Valider</a>
                  <a href="gestion.php?action=refuser&id=<?php echo $row['id']; ?>" class="btn-act btn-r"><i class="fas fa-times"></i> Refuser</a>
                  <a href="gestion.php?action=attente&id=<?php echo $row['id']; ?>" class="btn-act btn-a"><i class="fas fa-undo"></i> Attente</a>
                </td>
              </tr>
              <?php endforeach; ?>
          <?php else: ?>
              <tr>
                <td colspan="6" class="no-data">
                  <i class="fas fa-folder-open" style="font-size: 2em; display:block; margin-bottom:10px; color:#cbd5e1;"></i>
                  Aucun dossier d'inscription dans la base de données.
                </td>
              </tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

  </main>

  <script>
    function toggleMenu() { document.getElementById('sidebar').classList.toggle('show'); }
    
    function exportCSV() {
      let rows = document.querySelectorAll(".dossier-table tr");
      let csv = [];
      rows.forEach(row => {
        let cols = row.querySelectorAll("td, th");
        let data = [];
        cols.forEach((col, index) => {
          if(index < 5) { 
            data.push('"' + col.innerText.replace(/"/g, '""') + '"');
          }
        });
        if(data.length > 0) csv.push(data.join(","));
      });
      let blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
      let link = document.createElement("a");
      link.href = URL.createObjectURL(blob);
      link.download = "dossiers_inscriptions.csv";
      link.click();
    }
  </script>
</body>
</html>