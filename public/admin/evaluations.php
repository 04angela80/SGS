<?php
// 1. Connexion à la base de données
require_once __DIR__ . '/../../config/db.php';

$messages = [];
$success_msg = "";

// ==========================================================================
// ACTION ADMIN : PUBLICATION OFFICIELLE DU RÉSULTAT FINAL
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'publier_resultat') {
    $stagiaire_id = isset($_POST['stagiaire_id']) ? intval($_POST['stagiaire_id']) : 0;

    if ($stagiaire_id > 0) {
        try {
            $stmt = $bdd->prepare("UPDATE stagiaires SET statut = 'Résultat Publié' WHERE id = ?");
            $stmt->execute([$stagiaire_id]);
            
            $success_msg = "Le résultat final du stagiaire a été officiellement publié !";
        } catch (PDOException $e) {
            $messages[] = "Erreur lors de la publication : " . $e->getMessage();
        }
    } else {
        $messages[] = "Veuillez sélectionner un stagiaire valide à publier.";
    }
}

// ==========================================================================
// REQUÊTE ASYNCHRONE (AJAX) : CHARGER LE BILAN DU STAGIAIRE SÉLECTIONNÉ
// ==========================================================================
if (isset($_GET['action_ajax']) && $_GET['action_ajax'] === 'charger_bilan_encadrant') {
    header('Content-Type: application/json; charset=UTF-8');
    $stagiaire_id = isset($_GET['stagiaire_id']) ? intval($_GET['stagiaire_id']) : 0;

    if ($stagiaire_id > 0) {
        try {
            $sql = "SELECT e.note, e.commentaire, e.date_evaluation, 
                           s.id, s.nom, s.prenom, s.filiere, s.statut,
                           srv.nom_service, srv.nom_encadrant
                    FROM stagiaires s
                    LEFT JOIN evaluations e ON s.id = e.stagiaire_id
                    LEFT JOIN services srv ON (s.id_service_affecte = srv.id_service OR LOWER(s.filiere) LIKE LOWER(CONCAT('%', srv.nom_service, '%')) OR LOWER(srv.nom_service) LIKE LOWER(CONCAT('%', s.filiere, '%')))
                    WHERE s.id = ? AND (LOWER(s.statut) = 'validé' OR LOWER(s.statut) = 'résultat publié')
                    LIMIT 1";
            $stmt = $bdd->prepare($sql);
            $stmt->execute([$stagiaire_id]);
            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($data) {
                $hasNote = !is_null($data['note']);
                
                $noteVal = $hasNote ? number_format($data['note'], 2, '.', '') : 'N/A';
                $commentVal = $hasNote ? $data['commentaire'] : "L'encadreur n'a pas encore transmis la note.";
                $dateVal = ($hasNote && !empty($data['date_evaluation'])) ? date('d/m/Y', strtotime($data['date_evaluation'])) : '-';

                $nomService = !empty($data['nom_service']) ? $data['nom_service'] : (!empty($data['filiere']) ? $data['filiere'] : 'Général');
                $nomEncadrant = !empty($data['nom_encadrant']) ? $data['nom_encadrant'] : 'Encadreur non assigné';

                echo json_encode([
                    'success' => true,
                    'nom' => strtoupper($data['nom']) . ' ' . $data['prenom'],
                    'service' => $nomService,
                    'encadrant' => $nomEncadrant,
                    'note' => $noteVal,
                    'commentaire' => $commentVal,
                    'date_eval' => $dateVal,
                    'statut' => $data['statut'],
                    'est_evalue' => $hasNote
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Stagiaire non trouvé ou dossier non validé']);
            }
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'ID Stagiaire invalide']);
    }
    exit();
}

// ==========================================================================
// RÉCUPÉRATION DES STAGIAIRES VALIDÉS ET LECTURE DIRECTE DE LA TABLE EVALUATIONS
// ==========================================================================
try {
    $sqlEvals = "SELECT s.id as stg_id, s.nom, s.prenom, s.filiere, s.statut,
                        e.id as eval_id, e.note, e.commentaire, e.date_evaluation
                 FROM stagiaires s
                 LEFT JOIN evaluations e ON s.id = e.stagiaire_id
                 WHERE LOWER(s.statut) = 'validé' OR LOWER(s.statut) = 'résultat publié'
                 ORDER BY s.nom ASC";
    $rawStagiaires = $bdd->query($sqlEvals)->fetchAll(PDO::FETCH_ASSOC);

    $evaluationsEncadrants = [];
    $totalStagiaires = count($rawStagiaires);
    $nbFichesRecues = 0;
    $nbAdmis = 0;
    $sommeNotes = 0;

    foreach ($rawStagiaires as $row) {
        // La note est prise UNIQUEMENT si elle existe réellement dans la table `evaluations`
        $hasNote = !is_null($row['note']);

        if ($hasNote) {
            $nbFichesRecues++;
            $noteNum = floatval($row['note']);
            $sommeNotes += $noteNum;
            if ($noteNum >= 10) {
                $nbAdmis++;
            }
        }

        $evaluationsEncadrants[] = [
            'stg_id' => $row['stg_id'],
            'nom' => $row['nom'],
            'prenom' => $row['prenom'],
            'filiere' => $row['filiere'],
            'statut' => $row['statut'],
            'note' => $hasNote ? $row['note'] : null,
            'commentaire' => $hasNote ? $row['commentaire'] : "L'encadreur n'a pas encore transmis la note.",
            'date_eval' => ($hasNote && !empty($row['date_evaluation'])) ? date('d/m/Y', strtotime($row['date_evaluation'])) : null,
            'est_evalue' => $hasNote
        ];
    }

    $moyenneGenerale = $nbFichesRecues > 0 ? round($sommeNotes / $nbFichesRecues, 2) : 0;

} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Évaluation & Validation des Résultats - SGS</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; color: #334155; }
    
    .top-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(90deg, #0056b3, #003d80); color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 1000; }
    .top-header .header-title { font-size: 1.4em; font-weight: bold; margin: 0; letter-spacing: 1px; position: absolute; left: 50%; transform: translateX(-50%); }
    .top-header .header-icons { position: absolute; right: 20px; display: flex; gap: 15px; font-size: 22px; cursor: pointer; }
    .top-header .header-icons span:hover { transform: scale(1.1); }
    
    .menu-btn { position: fixed; top: 15px; left: 15px; background: #0056b3; color: #fff; padding: 10px 15px; cursor: pointer; border-radius: 5px; z-index: 1001; display: inline-flex; align-items: center; gap: 6px; font-weight: bold; }
    
    .sidebar { 
      position: fixed; left: -250px; top: 0; width: 250px; height: 100vh; 
      background: linear-gradient(180deg, #0056b3, #003d80); color: #fff; 
      padding: 20px; transition: left 0.5s ease; z-index: 999; display: flex; flex-direction: column; 
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

    .sidebar-footer { margin-top: 15px; text-align: center; padding-bottom: 50px; }
    .sidebar-divider { height: 1.5px; background: #ffffff; margin: 10px 0; border: none; }
    .footer-text { font-size: 13px; color: #ffffff; font-weight: 600; letter-spacing: 0.5px; line-height: 1.4; }
    .footer-sub { font-size: 11px; display: block; font-weight: 400; color: #f1f5f9; margin-top: 2px; }
   
    .main-content { 
      max-width: 1200px; 
      margin: 0 auto; 
      padding: 100px 30px 40px 30px; 
      box-sizing: border-box; 
    }
    
    .page-title-banner {
      background: linear-gradient(135deg, #ffffff 0%, #f1f5f9 100%);
      border-left: 5px solid #0056b3;
      padding: 20px 25px;
      border-radius: 8px;
      box-shadow: 0 2px 10px rgba(0,0,0,0.04);
      margin-bottom: 30px;
    }
    .page-title-banner h2 { margin: 0; color: #0056b3; font-size: 1.5em; display: flex; align-items: center; gap: 12px; }
    .page-title-banner p { margin: 6px 0 0 0; color: #64748b; font-size: 0.95em; }

    .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 35px; }
    .stat-card {
      background: #ffffff;
      padding: 22px;
      border-radius: 12px;
      box-shadow: 0 4px 15px rgba(0,0,0,0.04);
      border: 1px solid #e2e8f0;
      display: flex;
      align-items: center;
      gap: 18px;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }
    .stat-card:hover { transform: translateY(-4px); box-shadow: 0 8px 25px rgba(0,0,0,0.08); }
    .stat-icon { width: 55px; height: 55px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 1.5em; }
    .stat-icon.blue { background: #e0f2fe; color: #0284c7; }
    .stat-icon.green { background: #dcfce7; color: #16a34a; }
    .stat-icon.purple { background: #f3e8ff; color: #9333ea; }
    .stat-info h3 { margin: 0; font-size: 0.85em; text-transform: uppercase; color: #64748b; letter-spacing: 0.5px; }
    .stat-info p { margin: 4px 0 0 0; font-size: 1.6em; font-weight: bold; color: #0f172a; }

    .section-card {
      background: #ffffff;
      border-radius: 12px;
      border: 1px solid #e2e8f0;
      box-shadow: 0 4px 15px rgba(0,0,0,0.03);
      padding: 25px;
      margin-bottom: 35px;
    }
    .section-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; border-bottom: 1px solid #f1f5f9; padding-bottom: 15px; }
    .section-header h3 { margin: 0; color: #0f172a; font-size: 1.2em; display: flex; align-items: center; gap: 10px; }
    
    .origin-info-banner {
      background: #f0f9ff;
      border: 1px solid #bae6fd;
      color: #0369a1;
      padding: 12px 18px;
      border-radius: 8px;
      font-size: 0.9em;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .eval-table { width: 100%; border-collapse: separate; border-spacing: 0; }
    .eval-table th { background: #f8fafc; color: #475569; font-weight: 700; text-transform: uppercase; font-size: 0.8em; padding: 14px 16px; border-bottom: 2px solid #e2e8f0; text-align: left; }
    .eval-table td { padding: 16px; border-bottom: 1px solid #f1f5f9; font-size: 0.93em; vertical-align: middle; }
    .eval-table tr:last-child td { border-bottom: none; }
    .eval-table tr:hover { background: #f8fafc; }

    .note-text-simple { font-size: 1.15em; font-weight: 700; letter-spacing: 0.3px; }
    .note-text-simple.admis { color: #16a34a; }
    .note-text-simple.echec { color: #d97706; }
    .note-text-simple.attente { color: #64748b; font-size: 0.9em; font-weight: 600; font-style: italic; }

    .publish-container {
      background: linear-gradient(135deg, #1e3a8a 0%, #0056b3 100%);
      color: #ffffff;
      border-radius: 12px;
      padding: 30px;
      box-shadow: 0 10px 25px rgba(0, 61, 128, 0.2);
    }
    .publish-container h3 { margin-top: 0; font-size: 1.3em; display: flex; align-items: center; gap: 10px; color: #ffffff; }
    .publish-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-top: 20px; }
    @media(max-width: 850px){ .publish-grid { grid-template-columns: 1fr; } }

    .select-custom { width: 100%; padding: 12px 15px; border-radius: 8px; border: 1px solid #93c5fd; font-size: 1em; font-weight: 600; background: #ffffff; color: #0f172a; outline: none; }
    
    .card-preview {
      background: rgba(255, 255, 255, 0.1);
      backdrop-filter: blur(10px);
      border: 1px solid rgba(255, 255, 255, 0.2);
      border-radius: 10px;
      padding: 20px;
      display: none;
    }
    .preview-item { margin-bottom: 12px; font-size: 0.95em; }
    .preview-label { font-size: 0.8em; text-transform: uppercase; letter-spacing: 0.5px; opacity: 0.8; display: block; }
    .preview-val { font-weight: bold; font-size: 1.1em; }

    .btn-publish {
      background: #10b981;
      color: #ffffff;
      border: none;
      padding: 14px 24px;
      font-size: 1em;
      font-weight: 700;
      border-radius: 8px;
      cursor: pointer;
      width: 100%;
      margin-top: 15px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      box-shadow: 0 4px 12px rgba(16, 185, 129, 0.3);
      transition: background 0.2s, transform 0.2s;
    }
    .btn-publish:hover { background: #059669; transform: translateY(-2px); }

    .flash-box-success { background: #dcfce7; color: #15803d; border: 1px solid #86efac; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-weight: bold; display: flex; align-items: center; gap: 10px; }
    .flash-box-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; font-weight: bold; display: flex; align-items: center; gap: 10px; }
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
    <h1 class="header-title"><i class="fas fa-award"></i> Validation & Publication des Évaluations</h1>
     <div class="header-icons">
      <span class="admin"><i class="fas fa-user-shield"></i></span>
    </div>
  </div>

  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <aside class="sidebar" id="sidebar">
    <div class="logo-container"><img src="../../LOGO.jpeg" class="logo" onerror="this.style.display='none'"></div>
    <ul>
      <li><a href="liste.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="suivi.php"><i class="fas fa-chart-bar"></i> Suivi des stagiaires</a></li>
      <li><a href="rapport.php"><i class="fas fa-thumbtack"></i> Rapports</a></li>
      <li><a href="evaluations.php" class="active"><i class="fas fa-file-alt"></i> Évaluation & Résultats</a></li>
      <li><a href="SERVICE.php"><i class="fas fa-tools"></i> Services</a></li>
      <li class="logout"><a href="../../public/index.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
    <div class="sidebar-footer">
      <hr class="sidebar-divider">
      <div class="footer-text">
        <span><i class="fas fa-user-shield"></i> SGS • Admin</span>
        <span class="footer-sub">Système de Gestion des Stagiaires @2026</span>
      </div>
    </div>
  </aside>

  <main class="main-content">
    
    <div class="page-title-banner">
      <h2><i class="fas fa-clipboard-check"></i> Espace d'Homologation des Notes</h2>
      <p>Consultez les résultats transmis par les encadreurs de chaque département et publiez officiellement le bilan de chaque stagiaire qualifié.</p>
    </div>

    <?php if (!empty($success_msg)): ?>
        <div class="flash-box-success"><i class="fas fa-check-circle" style="font-size:1.2em;"></i> <?= $success_msg ?></div>
    <?php endif; ?>

    <?php foreach ($messages as $msg): ?>
        <div class="flash-box-danger"><i class="fas fa-exclamation-triangle" style="font-size:1.2em;"></i> <?= $msg ?></div>
    <?php endforeach; ?>

    <!-- COMPTEURS DYNAMIQUES -->
    <div class="stats-grid">
      <div class="stat-card">
        <div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div>
        <div class="stat-info">
          <h3>Fiches reçues</h3>
          <p><?= $nbFichesRecues ?> / <?= $totalStagiaires ?></p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon green"><i class="fas fa-user-check"></i></div>
        <div class="stat-info">
          <h3>Stages Validés (>=10)</h3>
          <p><?= $nbAdmis ?> / <?= $totalStagiaires ?></p>
        </div>
      </div>

      <div class="stat-card">
        <div class="stat-icon purple"><i class="fas fa-calculator"></i></div>
        <div class="stat-info">
          <h3>Moyenne Générale</h3>
          <p><?= $moyenneGenerale ?> / 20</p>
        </div>
      </div>
    </div>

    <!-- TABLEAU DES ÉVALUATIONS -->
    <div class="section-card">
      <div class="section-header">
        <h3><i class="fas fa-user-check" style="color:#0056b3;"></i> Bilan des Évaluations Transmises par les Encadreurs de Service</h3>
        <span style="font-size:0.85em; font-weight:bold; color:#64748b; background:#f1f5f9; padding:6px 12px; border-radius:20px;">
          Effectif admis : <?= $totalStagiaires ?> Stagiaires Validés
        </span>
      </div>

      <div class="origin-info-banner">
        <i class="fas fa-info-circle" style="font-size:1.2em;"></i>
        <span><strong>Origine des données :</strong> Ce tableau liste les stagiaires dont les encadreurs de chaque departement ont eu a leur evaluer durant leur periode de cycle de stage .</span>
      </div>

      <table class="eval-table">
        <thead>
          <tr>
            <th>Stagiaire</th>
            <th>Note de Stage</th>
            <th>Appréciation de l'Encadreur</th>
            <th>Statut Final</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($evaluationsEncadrants)): ?>
              <tr><td colspan="4" style="text-align:center; color:#94a3b8; padding:30px; font-style:italic;">Aucun stagiaire validé pour le moment.</td></tr>
          <?php else: ?>
              <?php foreach ($evaluationsEncadrants as $row): 
                  $estEvalue = $row['est_evalue'];
                  $noteVal = $estEvalue ? floatval($row['note']) : 0;
                  
                  if ($estEvalue) {
                      $noteClass = ($noteVal >= 10) ? 'admis' : 'echec';
                  } else {
                      $noteClass = 'attente';
                  }
              ?>
              <tr>
                <td style="font-weight: 700; color:#0f172a;">
                  <?= htmlspecialchars(strtoupper($row['nom']) . ' ' . $row['prenom']) ?>
                  <div style="font-size: 0.8em; color: #64748b; font-weight: normal;"><?= htmlspecialchars($row['filiere'] ?? 'Filière non renseignée') ?></div>
                </td>

                <td>
                  <?php if ($estEvalue): ?>
                    <span class="note-text-simple <?= $noteClass ?>">
                      <?= number_format($noteVal, 2, '.', '') ?> / 20
                    </span>
                  <?php else: ?>
                    <span class="note-text-simple attente">
                      <i class="fas fa-hourglass-half"></i> En attente
                    </span>
                  <?php endif; ?>
                </td>

                <td style="font-style: italic; color:#334155; max-width: 320px;">
                  <?php if ($estEvalue): ?>
                    "<?= htmlspecialchars($row['commentaire']) ?>"
                    <div style="font-size:0.75em; color:#94a3b8; margin-top:4px; font-style:normal;"><i class="fas fa-file-signature"></i> Transmis le <?= $row['date_eval'] ?></div>
                  <?php else: ?>
                    <span style="color:#94a3b8; font-size:0.88em;"><i class="fas fa-exclamation-circle"></i> L'encadreur n'a pas encore transmis la note.</span>
                  <?php endif; ?>
                </td>

                <td>
                  <?php if ($row['statut'] === 'Résultat Publié'): ?>
                    <span style="background:#dcfce7; color:#15803d; border:1px solid #86efac; padding:4px 10px; border-radius:20px; font-weight:bold; font-size:0.8em;">
                      <i class="fas fa-globe"></i> Résultat Publié
                    </span>
                  <?php elseif ($estEvalue): ?>
                    <span style="background:#fef3c7; color:#b45309; border:1px solid #fde68a; padding:4px 10px; border-radius:20px; font-weight:bold; font-size:0.8em;">
                      <i class="fas fa-clock"></i> Prêt pour publication
                    </span>
                  <?php else: ?>
                    <span style="background:#f1f5f9; color:#64748b; border:1px solid #cbd5e1; padding:4px 10px; border-radius:20px; font-weight:bold; font-size:0.8em;">
                      <i class="fas fa-spinner fa-spin"></i> En attente de cotation
                    </span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <!-- MODULE DE PUBLICATION DU RÉSULTAT -->
    <div class="publish-container">
      <h3><i class="fas fa-paper-plane"></i> Publier Officiellement le Résultat Final</h3>
      <p style="opacity:0.9; margin-top:4px; font-size:0.92em;">
        Sélectionnez un stagiaire validé dans la liste pour consulter sa note transmise et publier son résultat officiel.
      </p>

      <form method="POST" action="">
        <input type="hidden" name="action" value="publier_resultat">
        
        <div class="publish-grid">
          <div>
            <label style="display:block; font-weight:bold; margin-bottom:8px;">Sélectionner un stagiaire :</label>
            <select name="stagiaire_id" class="select-custom" required onchange="chargerBilanPreview(this.value)">
              <option value="">-- Choisir un stagiaire validé --</option>
              <?php foreach ($evaluationsEncadrants as $ev): ?>
                <option value="<?= $ev['stg_id'] ?>">
                  <?= htmlspecialchars(strtoupper($ev['nom']) . ' ' . $ev['prenom']) ?>
                </option>
              <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-publish" id="btn_submit_pub" style="display:none;">
              <i class="fas fa-check-double"></i> Publier le Résultat Officiel
            </button>
          </div>

          <!-- APERÇU EN DIRECT -->
          <div>
            <div id="card_preview" class="card-preview">
              <div class="preview-item">
                <span class="preview-label">Stagiaire :</span>
                <span class="preview-val" id="prev_nom">-</span>
              </div>
              <div style="display:flex; gap:20px;">
                <div class="preview-item">
                  <span class="preview-label">Service / Encadreur :</span>
                  <span class="preview-val" id="prev_encadrant" style="font-size:0.95em;">-</span>
                </div>
                <div class="preview-item">
                  <span class="preview-label">Note Finale :</span>
                  <span class="preview-val" id="prev_note" style="color:#10b981; font-size:1.3em;">-</span>
                </div>
              </div>
              <div class="preview-item" style="margin-bottom:0;">
                <span class="preview-label">Appréciation transmise par l'encadreur :</span>
                <p id="prev_commentaire" style="margin:4px 0 0 0; font-style:italic; opacity:0.9; font-size:0.9em;"></p>
              </div>
            </div>
          </div>
        </div>
      </form>
    </div>

  </main>

  <script>
    function toggleMenu() { document.getElementById('sidebar').classList.toggle('show'); }

    function chargerBilanPreview(stagiaireId) {
      const card = document.getElementById('card_preview');
      const btnPub = document.getElementById('btn_submit_pub');
      
      if (!stagiaireId) {
        card.style.display = "none";
        btnPub.style.display = "none";
        return;
      }

      fetch(`evaluations.php?action_ajax=charger_bilan_encadrant&stagiaire_id=${stagiaireId}`)
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            document.getElementById('prev_nom').innerText = data.nom;
            document.getElementById('prev_encadrant').innerText = data.service + ' (' + data.encadrant + ')';
            
            if (data.est_evalue) {
              document.getElementById('prev_note').innerText = data.note + ' / 20';
              document.getElementById('prev_note').style.color = "#10b981";
              btnPub.style.display = "flex";
            } else {
              document.getElementById('prev_note').innerText = "En attente";
              document.getElementById('prev_note').style.color = "#f59e0b";
              btnPub.style.display = "none";
            }

            document.getElementById('prev_commentaire').innerText = '"' + data.commentaire + '"';
            card.style.display = "block";
          } else {
            card.style.display = "none";
            btnPub.style.display = "none";
          }
        })
        .catch(err => console.error("Erreur chargement aperçu :", err));
    }
  </script>
</body>
</html>