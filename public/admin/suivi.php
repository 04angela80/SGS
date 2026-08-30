<?php
session_start();
// ── CONFIGURATION ET CONNEXIONS ──────────────────────────────────────────────
require_once __DIR__ . '/../../config/db.php';                
$admin_id = $_SESSION['admin_id'] ?? 1;

// ── GESTION DU TEMPS : 4 SEMAINES STRICTES PAR MOIS ─────────────────────────
$annee_courante = 2026;

if (isset($_GET['action_mois']) && !empty($_GET['mois_choisi'])) {
    $mois_actuel_vue = $_GET['mois_choisi']; 
} else {
    $mois_actuel_vue = isset($_GET['mois_contexte']) ? trim($_GET['mois_contexte']) : date('Y-m');
}

if (substr($mois_actuel_vue, 0, 4) !== "2026") {
    $mois_actuel_vue = "2026-" . date('m');
}

function get4SemainesFixesDuMois2026($annee, $mois) {
    $semaines = [];
    $premierJourMois = new DateTime("$annee-$mois-01");
    
    for ($indexSem = 0; $indexSem < 4; $indexSem++) {
        $debutSemaine = clone $premierJourMois;
        $debutSemaine->modify("+" . ($indexSem * 7) . " days");
        
        if ($debutSemaine->format('N') == 6) { $debutSemaine->modify('+2 days'); }
        elseif ($debutSemaine->format('N') == 7) { $debutSemaine->modify('+1 day'); }
        
        $lundi = clone $debutSemaine;
        if ($lundi->format('N') != 1) {
            $lundi->modify('last monday');
        }
        
        $joursSemaine = [];
        $noms = ['Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi'];
        
        for ($i = 0; $i < 5; $i++) {
            $d = clone $lundi;
            if ($i > 0) { $d->modify("+$i days"); }
            $joursSemaine[] = [
                'nom'   => $noms[$i], 
                'date'  => $d->format('Y-m-d'), 
                'label' => $d->format('d/m')
            ];
        }

        $semaines[] = [
            'num_global' => (int)$lundi->format('W'),
            'jours' => $joursSemaine
        ];
    }
    return $semaines;
}

list($yyyy, $mm) = explode('-', $mois_actuel_vue);
$listeSemainesDuMois = get4SemainesFixesDuMois2026($yyyy, $mm);

$index_semaine_choisie = isset($_GET['index_sem_mois']) ? intval($_GET['index_sem_mois']) : 0;
if ($index_semaine_choisie < 0) { $index_semaine_choisie = 0; }
if ($index_semaine_choisie >= 4) { $index_semaine_choisie = 3; }

$semaine_active_data = $listeSemainesDuMois[$index_semaine_choisie];
$num_semaine_annee_strict = $semaine_active_data['num_global'];
$semaine = $semaine_active_data['jours'];

$url_prev = ($index_semaine_choisie > 0) 
  ? "?mois_contexte=$mois_actuel_vue&index_sem_mois=" . ($index_semaine_choisie - 1) 
  : "#";
$url_next = ($index_semaine_choisie < 3) 
  ? "?mois_contexte=$mois_actuel_vue&index_sem_mois=" . ($index_semaine_choisie + 1) 
  : "#";

// ── CHARGEMENT STRICT DES STAGIAIRES DONT LE DOSSIER EST VALIDÉ ─────────────
try {
    $sql = "SELECT s.*, 
                   ser.description AS nom_service, 
                   ser.nom_encadrant 
            FROM stagiaires s 
            LEFT JOIN services ser ON LOWER(TRIM(ser.nom_service)) = LOWER(TRIM(s.filiere)) 
            WHERE LOWER(TRIM(s.statut)) IN ('valide', 'validé', 'validée', '1', 'actif')
            ORDER BY s.id DESC";
    $stmt = $bdd->query($sql);
    $stagiaires = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) { 
    try {
        $sql = "SELECT * FROM stagiaires 
                WHERE LOWER(TRIM(statut)) IN ('valide', 'validé', 'validée', '1', 'actif') 
                ORDER BY id DESC";
        $stagiaires = $bdd->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $ex) {
        $stagiaires = array();
    }
}

$listeMois = array(
    '2026-01' => 'Janvier 2026', '2026-02' => 'Février 2026', '2026-03' => 'Mars 2026',
    '2026-04' => 'Avril 2026',   '2026-05' => 'Mai 2026',     '2026-06' => 'Juin 2026',
    '2026-07' => 'Juillet 2026', '2026-08' => 'Août 2026',    '2026-09' => 'Septembre 2026',
    '2026-10' => 'Octobre 2026', '2026-11' => 'Novembre 2026','2026-12' => 'Décembre 2026'
);

// BANQUE SIMULÉE DE TÂCHES PAR SEMAINE
$banque_taches_par_semaine = [
    0 => [
        ["description" => "Installation et prise en main de l'environnement de travail", "statut" => "valide"],
        ["description" => "Lecture de la documentation technique et architecture globale", "statut" => "valide"],
        ["description" => "Première rédaction du journal de bord hebdomadaire", "statut" => "valide"]
    ],
    1 => [
        ["description" => "Analyse et diagnostic des dysfonctionnements signalés", "statut" => "valide"],
        ["description" => "Implémentation du module principal et tests unitaires", "statut" => "en_cours"],
        ["description" => "Rédaction du rapport d'étape préliminaire", "statut" => "a_refaire"]
    ],
    2 => [
        ["description" => "Correction des anomalies identifiées en revue de code", "statut" => "valide"],
        ["description" => "Optimisation des performances et nettoyage de la base", "statut" => "valide"],
        ["description" => "Préparation des cas de test d'intégration", "statut" => "en_cours"]
    ],
    3 => [
        ["description" => "Recette fonctionnelle finale et validation des livrables", "statut" => "valide"],
        ["description" => "Rédaction du rapport de synthèse mensuel", "statut" => "valide"],
        ["description" => "Présentation orale du bilan devant l'encadrant", "statut" => "valide"]
    ]
];
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>SGS – Tableau de Bord & Suivi Stagiaires</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
  :root {
    --primary-blue: #0056b3;
    --dark-blue: #003d80;
    --bg-global: #f8fafc;
    --text-main: #334155;
    --border-color: #cbd5e1;
    --success: #16a34a;
    --warning: #d97706;
    --danger: #dc2626;
  }

  body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: var(--bg-global); color: var(--text-main); }

  .top-header { 
    position: fixed; top: 0; left: 0; right: 0; height: 60px; 
    background: linear-gradient(90deg, #0056b3, #003d80); color: #fff; 
    display: flex; align-items: center; justify-content: center; padding: 0 20px; 
    box-shadow: 0 2px 8px rgba(0,0,0,0.15); z-index: 1000; 
  }
  .top-header .header-title { font-size: 1.25em; font-weight: bold; margin: 0; }
  .top-header .header-icons { position: absolute; right: 20px; display: flex; gap: 15px; font-size: 22px; cursor: pointer; }
    .top-header .header-icons span:hover { transform: scale(1.1); }
  
  .menu-btn { 
    position: fixed; top: 12px; left: 15px; background: #0056b3; color: #fff; 
    padding: 8px 14px; cursor: pointer; border-radius: 5px; z-index: 1001; 
    display: inline-flex; align-items: center; gap: 8px; font-weight: bold;
  }

  .sidebar { 
    position: fixed; left: -260px; top: 0; width: 250px; height: 100vh; 
    background: linear-gradient(180deg, #0056b3, #003d80); color: #fff; 
    padding: 20px; transition: left 0.3s ease; z-index: 999; display: flex; flex-direction: column; 
  }
  .sidebar.show { left: 0; }
  .logo-container { margin-top: 40px; margin-bottom: 20px; text-align: center; }
  .logo { width: 80px; height: 80px; border-radius: 50%; background: #fff; padding: 4px; }

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
   
  .main-wrapper { margin-top: 75px; padding: 20px; max-width: 1200px; margin-left: auto; margin-right: auto; }

  /* FILTRE & NAVIGATION PAR SEMAINE */
  .filter-dashboard-bar { background: #ffffff; padding: 16px 20px; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 20px; box-shadow: 0 2px 4px rgba(0,0,0,0.03); }
  .row-controls { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; }

  .select-month-input { border: 1px solid var(--border-color); border-radius: 6px; padding: 7px 12px; font-weight: bold; background: #fff; color: #0f172a; }
  
  .week-nav-container { display: flex; align-items: center; gap: 10px; background: #f1f5f9; padding: 6px 14px; border-radius: 6px; border: 1px solid #e2e8f0; }
  .btn-nav-week { text-decoration: none; color: #0056b3; font-weight: bold; padding: 4px 10px; border-radius: 4px; background: #fff; border: 1px solid #cbd5e1; transition: all 0.2s; }
  .btn-nav-week:hover { background: #0056b3; color: #fff; }
  .btn-nav-week.disabled { color: #94a3b8; pointer-events: none; background: #f8fafc; border-color: #e2e8f0; }

  .search-input { width: 100%; padding: 12px 14px 12px 40px; border-radius: 6px; border: 1px solid var(--border-color); margin-bottom: 20px; outline: none; box-sizing: border-box; font-size: 0.95em; }

  /* DESIGN CARTE STAGIAIRE & PULSEURS */
  .stagiaire-card { background: #ffffff; border-radius: 8px; border: 1px solid var(--border-color); margin-bottom: 16px; overflow: hidden; box-shadow: 0 2px 5px rgba(0,0,0,0.03); transition: transform 0.2s, box-shadow 0.2s; }
  .stagiaire-card:hover { border-color: #94a3b8; box-shadow: 0 4px 12px rgba(0,0,0,0.07); }
  
  .stagiaire-header { padding: 16px 20px; display: flex; align-items: center; justify-content: space-between; cursor: pointer; user-select: none; background: #ffffff; border-bottom: 1px solid #f1f5f9; }
  .stg-meta { display: flex; align-items: center; gap: 14px; }
  .stg-avatar { width: 46px; height: 46px; border-radius: 50%; background: #e0f2fe; color: #0369a1; display: flex; align-items: center; justify-content: center; font-weight: bold; font-size: 1.1em; border: 1px solid #bae6fd; }
  .stg-name { font-weight: 700; color: #0f172a; font-size: 1.1em; }

  /* SYSTEME DE PULSEURS VISUELS */
  .pulse-container { display: flex; align-items: center; gap: 8px; padding: 4px 12px; border-radius: 20px; background: #f8fafc; border: 1px solid #e2e8f0; }
  .pulse-dot { width: 12px; height: 12px; border-radius: 50%; position: relative; }
  
  .pulse-green { background-color: #16a34a; box-shadow: 0 0 0 rgba(22, 163, 74, 0.4); animation: pulse-green-anim 2s infinite; }
  @keyframes pulse-green-anim {
    0% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(22, 163, 74, 0); }
    100% { box-shadow: 0 0 0 0 rgba(22, 163, 74, 0); }
  }

  .pulse-orange { background-color: #d97706; box-shadow: 0 0 0 rgba(217, 119, 6, 0.4); animation: pulse-orange-anim 1.5s infinite; }
  @keyframes pulse-orange-anim {
    0% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0.7); }
    70% { box-shadow: 0 0 0 8px rgba(217, 119, 6, 0); }
    100% { box-shadow: 0 0 0 0 rgba(217, 119, 6, 0); }
  }

  .pulse-red { background-color: #dc2626; box-shadow: 0 0 0 rgba(220, 38, 38, 0.4); animation: pulse-red-anim 1s infinite; }
  @keyframes pulse-red-anim {
    0% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0.8); }
    70% { box-shadow: 0 0 0 10px rgba(220, 38, 38, 0); }
    100% { box-shadow: 0 0 0 0 rgba(220, 38, 38, 0); }
  }

  /* PULSEUR GRIS NEUTRE POUR LES NOUVEAUX STAGIAIRES */
  .pulse-gray { background-color: #94a3b8; }

  /* ACCORDÉON BODY */
  .stagiaire-body { display: none; padding: 22px; background: #ffffff; border-top: 1px solid #e2e8f0; }
  .stagiaire-card.open .stagiaire-body { display: block; }

  .rapport-title { font-size: 1.05em; font-weight: 700; color: #0f172a; margin-bottom: 16px; display: flex; align-items: center; gap: 8px; border-bottom: 2px solid #e2e8f0; padding-bottom: 8px; }
  .synthese-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin-bottom: 16px; }
  @media(max-width: 768px){ .synthese-grid { grid-template-columns: 1fr; } }

  .progress-bar-bg { background: #e2e8f0; height: 12px; border-radius: 6px; overflow: hidden; margin: 8px 0; }
  .progress-bar-fill { height: 100%; border-radius: 6px; transition: width 0.4s ease; }

  .badge-status { padding: 6px 14px; border-radius: 20px; font-size: 0.85em; font-weight: bold; display: inline-block; text-align: center; }
  .badge-status.regulier { background: #dcfce7; color: #15803d; border: 1px solid #86efac; }
  .badge-status.irregulier { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
  .badge-status.negligent { background: #fee2e2; color: #b91c1c; border: 1px solid #fca5a5; }
  .badge-status.attente { background: #f1f5f9; color: #64748b; border: 1px solid #cbd5e1; }

  .week-days-row { display: grid; grid-template-columns: repeat(5, 1fr); gap: 8px; margin-top: 10px; }
  .day-box-mini { border: 1px solid var(--border-color); border-radius: 6px; padding: 8px 4px; text-align: center; background: #fff; }
  .badge-pa { width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin: 6px auto; font-weight: bold; font-size: 12px; }
  .badge-pa.p { background: #dcfce7; color: #15803d; }
  .badge-pa.a { background: #fee2e2; color: #b91c1c; }
  .badge-pa.attente { background: #f1f5f9; color: #94a3b8; }

  .actions-row { display: flex; gap: 12px; justify-content: flex-end; margin-top: 18px; }
  .btn-download { background: #0056b3; color: #fff; border: none; padding: 10px 18px; border-radius: 6px; font-weight: bold; cursor: pointer; display: flex; align-items: center; gap: 8px; }
  .btn-download:hover { background: #003d80; }
  </style>
</head>
<body>

<div class="top-header">
  <h1 class="header-title"><i class="fas fa-thumbtack"></i> Consultation & Suivi Hebdomadaire</h1>
  <div class="header-icons">
      <span class="admin"><i class="fas fa-user-shield"></i></span>
    </div>
</div>

<div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

<aside class="sidebar" id="sidebar">
  <div class="logo-container">
    <img src="../../LOGO.jpeg" alt="Logo SGS" class="logo" onerror="this.style.display='none'">
  </div>
  <ul>
      <li><a href="liste.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="suivi.php" class="active"><i class="fas fa-chart-bar"></i> Suivi des stagiaires</a></li>
      <li><a href="rapport.php"><i class="fas fa-thumbtack"></i> Rapports</a></li>
      <li><a href="evaluations.php"><i class="fas fa-file-alt"></i> Évaluation & Résultats</a></li>
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

<div class="main-wrapper">
  
  <!-- BARRE DE FILTRE ET SELECTION DES SEMAINES -->
  <div class="filter-dashboard-bar">
    <div class="row-controls">
      <form method="GET" action="" style="display:inline-flex; align-items:center; gap:8px;">
        <input type="hidden" name="action_mois" value="1">
        <label><strong>Période : </strong></label>
        <select name="mois_choisi" class="select-month-input" onchange="this.form.submit()">
          <?php foreach ($listeMois as $valMois => $labelMois): ?>
            <option value="<?= $valMois ?>" <?= ($mois_actuel_vue === $valMois) ? 'selected' : '' ?>><?= $labelMois ?></option>
          <?php endforeach; ?>
        </select>
      </form>

      <div class="week-nav-container">
        <a href="<?= $url_prev ?>" class="btn-nav-week <?= ($index_semaine_choisie == 0) ? 'disabled' : '' ?>">
          <i class="fas fa-chevron-left"></i> Précédente
        </a>
        <strong style="font-size:0.9em; color:#1e293b;">
          Semaine <?= ($index_semaine_choisie + 1) ?> (du <?= $semaine[0]['label'] ?> au <?= $semaine[4]['label'] ?> 2026)
        </strong>
        <a href="<?= $url_next ?>" class="btn-nav-week <?= ($index_semaine_choisie == 3) ? 'disabled' : '' ?>">
          Suivante <i class="fas fa-chevron-right"></i>
        </a>
      </div>
    </div>
  </div>

  <!-- RECHERCHE RECTANGLE INSTANTANÉE -->
  <div style="position:relative;">
    <i class="fas fa-search" style="position:absolute; left:14px; top:14px; color:#94a3b8;"></i>
    <input type="text" id="stgSearch" class="search-input" placeholder="Rechercher instantanément un stagiaire par nom ou filière..." onkeyup="filterStagiaires()">
  </div>

  <!-- LISTE DES STAGIAIRES OU MESSAGE EN CAS DE BASE VIDE -->
  <div id="stagiairesList">
    <?php if (empty($stagiaires)): ?>
      <div style="background:#fff; padding:40px; text-align:center; border-radius:8px; border:1px solid #cbd5e1; color:#64748b;">
        <i class="fas fa-user-slash" style="font-size:2.5em; margin-bottom:10px; color:#94a3b8;"></i>
        <p style="font-size:1.1em; font-weight:bold; margin:0;">Aucun stagiaire dont l'inscription est validée n'a été trouvé.</p>
        <p style="font-size:0.9em; margin-top:5px;">Veuillez valider des inscriptions depuis la section "Gestion des stagiaires".</p>
      </div>
    <?php else: ?>
      <?php 
      // Obtenir l'ID le plus élevé pour simuler automatiquement le stagiaire le plus récent inscrit
      $max_id = max(array_column($stagiaires, 'id') ?: [0]);

      foreach ($stagiaires as $stg): 
        $sid = $stg['id'] ?? $stg['id_stagiaire'] ?? 1;
        $nom = htmlspecialchars($stg['nom'] ?? 'Nom');
        $prenom = htmlspecialchars($stg['prenom'] ?? 'Prénom');
        $filiere_txt = htmlspecialchars($stg['filiere'] ?? $stg['domaine'] ?? 'Informatique / Réseaux');
        
        // Nom de l'encadrant
        $nom_encadreur = !empty($stg['nom_encadrant']) ? htmlspecialchars($stg['nom_encadrant']) : "Non assigné";

        // ── SIMULATION DYNAMIQUE : NOUVEAU STAGIAIRE EN ATTENTE ─────────────
        // Si le stagiaire a un champ spécifique ou s'il s'agit du plus récent inscrit/validé (ID max)
        $est_nouveau = (isset($stg['est_nouveau']) && $stg['est_nouveau'] == 1) || ($sid == $max_id && count($stagiaires) > 1);

        if ($est_nouveau) {
            // Un nouveau stagiaire validé est EN ATTENTE
            $pulse_class = "pulse-gray";
            $pulse_label = "En attente";
            $status_class = "attente";
            $p_count = 0; 
            $a_count = 0;
            $taches_actuelles = [];
        } else {
            // Simulation pour les stagiaires plus anciens
            $variation_semaine = ($sid + $index_semaine_choisie) % 4;

            if ($variation_semaine == 0) { $p_count = 5; $a_count = 0; } 
            elseif ($variation_semaine == 1) { $p_count = 4; $a_count = 1; } 
            elseif ($variation_semaine == 2) { $p_count = 3; $a_count = 2; } 
            else { $p_count = 2; $a_count = 3; }

            if ($a_count == 0) {
                $pulse_class = "pulse-green";
                $pulse_label = "Régulier (100%)";
                $status_class = "regulier";
            } elseif ($a_count <= 2) {
                $pulse_class = "pulse-orange";
                $pulse_label = "Vigilance ($a_count abs)";
                $status_class = "irregulier";
            } else {
                $pulse_class = "pulse-red";
                $pulse_label = "Négligent ($a_count abs)";
                $status_class = "negligent";
            }

            $taches_actuelles = $banque_taches_par_semaine[$index_semaine_choisie];
        }
      ?>
        <div class="stagiaire-card" data-fullname="<?= strtolower($nom.' '.$prenom.' '.$filiere_txt) ?>">
          
          <!-- EN-TÊTE ACCORDÉON AVEC PULSEUR LUMINEUX -->
          <div class="stagiaire-header" onclick="toggleStagiaire(<?= $sid ?>)">
            <div class="stg-meta">
              <div class="stg-avatar"><?= mb_strtoupper(mb_substr($prenom,0,1).mb_substr($nom,0,1)) ?></div>
              <div>
                <div class="stg-name"><?= $nom.' '.$prenom ?></div>
                <div style="font-size:0.85em; color:#64748b; font-weight:600;"><i class="fas fa-graduation-cap"></i> <?= $filiere_txt ?></div>
              </div>
            </div>

            <!-- BLOC INDICATEUR LUMINOSITÉ / PULSEUR -->
            <div style="display:flex; align-items:center; gap:20px;">
              <div class="pulse-container">
                <div class="pulse-dot <?= $pulse_class ?>"></div>
                <span style="font-size:0.82em; font-weight:bold; color:#334155;"><?= $pulse_label ?></span>
              </div>
              <i class="fas fa-chevron-down" style="color: #64748b; transition:transform 0.2s;" id="icon-stg-<?= $sid ?>"></i>
            </div>
          </div>

          <!-- CONTENU RAPPORT DÉROULANT -->
          <div class="stagiaire-body" id="body-stg-<?= $sid ?>">
            
            <div class="rapport-title">
              <i class="fas fa-user-check" style="color:#0056b3;"></i> Encadrant du Service : <span style="color:#0056b3; margin-left:4px;"><?= $nom_encadreur ?></span>
            </div>

            <div class="synthese-grid">
              
              <!-- CÔTÉ GAUCHE : ASSIDUITÉ ET POINTAGE SEMAINE -->
              <div>
                <strong style="font-size:0.85em; text-transform:uppercase; color:#475569;"><i class="fas fa-calendar-check"></i> Bilan d'Assiduité — Semaine <?= ($index_semaine_choisie + 1) ?></strong>
                
                <?php if ($est_nouveau): ?>
                  <!-- AFFICHAGE SIMULÉ "EN ATTENTE" POUR NOUVEAU STAGIAIRE -->
                  <div style="font-size:0.95em; font-weight:bold; margin-top:10px; color:#64748b;">
                    Taux de présence : En attente
                  </div>
                  <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: 0%; background: #94a3b8;"></div>
                  </div>

                  <div style="margin-top:12px;">
                    <span class="badge-status attente"><i class="fas fa-clock"></i> En attente</span>
                  </div>

                  <div style="margin-top:16px;">
                    <strong style="font-size:0.82em; color:#64748b; display:block; margin-bottom:6px;">Détail des jours :</strong>
                    <div class="week-days-row">
                      <?php foreach ($semaine as $j): ?>
                        <div class="day-box-mini">
                          <div style="font-size:.7em; font-weight:bold; color:#475569;"><?= $j['nom'] ?></div>
                          <div class="badge-pa attente" title="En attente">-</div>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>

                <?php else: ?>
                  <!-- AFFICHAGE POUR STAGIAIRE ACTIF EN COURS -->
                  <div style="font-size:0.95em; font-weight:bold; margin-top:10px;">
                    Taux de présence : <?= round(($p_count/5)*100) ?>% (<?= $p_count ?> jours présent, <?= $a_count ?> absent)
                  </div>
                  <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?= ($p_count/5)*100 ?>%; background: <?= $a_count == 0 ? '#16a34a' : ($a_count <= 2 ? '#d97706' : '#dc2626') ?>;"></div>
                  </div>

                  <div style="margin-top:12px;">
                    <span class="badge-status <?= $status_class ?>">Statut Semaine <?= ($index_semaine_choisie + 1) ?> : <?= $pulse_label ?></span>
                  </div>

                  <div style="margin-top:16px;">
                    <strong style="font-size:0.82em; color:#64748b; display:block; margin-bottom:6px;">Détail des jours :</strong>
                    <div class="week-days-row">
                      <?php foreach ($semaine as $idx => $j): ?>
                        <div class="day-box-mini">
                          <div style="font-size:.7em; font-weight:bold; color:#475569;"><?= $j['nom'] ?></div>
                          <?php if ($idx < $p_count): ?>
                            <div class="badge-pa p" title="Présent">P</div>
                          <?php else: ?>
                            <div class="badge-pa a" title="Absent">A</div>
                          <?php endif; ?>
                        </div>
                      <?php endforeach; ?>
                    </div>
                  </div>
                <?php endif; ?>
              </div>

              <!-- CÔTÉ DROIT : TÂCHES SPÉCIFIQUES DE LA SEMAINE -->
              <div>
                <strong style="font-size:0.85em; text-transform:uppercase; color:#475569;"><i class="fas fa-tasks"></i> Tâches de la Semaine <?= ($index_semaine_choisie + 1) ?></strong>
                
                <?php if ($est_nouveau || empty($taches_actuelles)): ?>
                  <!-- SIMULATION TÂCHES EN ATTENTE -->
                  <div style="font-size:0.95em; font-weight:bold; margin-top:10px; color:#64748b;">
                    Avancement des travaux : En attente
                  </div>
                  <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: 0%; background: #94a3b8;"></div>
                  </div>

                  <div style="margin-top:16px; background:#f1f5f9; padding:12px; border-radius:6px; color:#64748b; font-size:0.88em; border:1px dashed #cbd5e1;">
                    <i class="fas fa-hourglass-half"></i> En attente d'attribution des tâches pour ce nouveau stagiaire.
                  </div>

                <?php else: 
                  $valides = array_filter($taches_actuelles, fn($t) => $t['statut'] === 'valide');
                  $count_valides = count($valides);
                  $total_t = count($taches_actuelles);
                  $pct_t = round(($count_valides / $total_t) * 100);
                ?>
                  <div style="font-size:0.95em; font-weight:bold; margin-top:10px;">
                    Avancement des travaux : <?= $count_valides ?> / <?= $total_t ?> validées
                  </div>
                  <div class="progress-bar-bg">
                    <div class="progress-bar-fill" style="width: <?= $pct_t ?>%; background: #16a34a;"></div>
                  </div>

                  <div style="margin-top:14px;">
                    <ul style="margin: 0; padding-left: 18px; font-size: 0.88em;">
                      <?php foreach ($taches_actuelles as $tk): ?>
                        <li style="margin-bottom: 8px;">
                          <strong><?= htmlspecialchars($tk['description']) ?></strong> — 
                          <?php if ($tk['statut'] === 'valide'): ?>
                            <span style="color:#16a34a; font-weight:bold;"><i class="fas fa-check-circle"></i> Validée</span>
                          <?php elseif ($tk['statut'] === 'en_cours'): ?>
                            <span style="color:#0056b3; font-weight:bold;"><i class="fas fa-spinner"></i> En cours</span>
                          <?php else: ?>
                            <span style="color:#dc2626; font-weight:bold;"><i class="fas fa-exclamation-circle"></i> À refaire</span>
                          <?php endif; ?>
                        </li>
                      <?php endforeach; ?>
                    </ul>
                  </div>
                <?php endif; ?>
              </div>

            </div>

            <!-- ACTION BOUTON IMPRIMER -->
            <div class="actions-row">
              <button type="button" class="btn-download" onclick="window.print()">
                <i class="fas fa-file-pdf"></i> Imprimer / Exporter le Rapport
              </button>
            </div>

          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

</div>

<script>
// Menu latéral
function toggleMenu() {
  document.getElementById('sidebar').classList.toggle('show');
}

// Déroulement accordéon
function toggleStagiaire(id) {
  const card = document.getElementById('body-stg-' + id).parentElement;
  const icon = document.getElementById('icon-stg-' + id);
  
  card.classList.toggle('open');
  if (card.classList.contains('open')) {
    icon.style.transform = "rotate(180deg)";
  } else {
    icon.style.transform = "rotate(0deg)";
  }
}

// Recherche instantanée
function filterStagiaires() {
  const val = document.getElementById('stgSearch').value.toLowerCase().trim();
  const cards = document.querySelectorAll('.stagiaire-card');

  cards.forEach(card => {
    const name = card.getAttribute('data-fullname') || '';
    card.style.display = name.includes(val) ? 'block' : 'none';
  });
}
</script>

</body>
</html>