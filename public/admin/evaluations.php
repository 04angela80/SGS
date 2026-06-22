<?php
// 1. Connexion à la base de données
require_once __DIR__ . '/../../config/db.php';

$messages = [];

// ==========================================================================
// TRAITEMENT : ENREGISTREMENT OU MISE A JOUR DE L'EVALUATION PAR L'ADMIN
// ==========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'soumettre_evaluation') {
    $stagiaire_id = isset($_POST['stagiaire_id']) ? intval($_POST['stagiaire_id']) : 0;
    $note = isset($_POST['note']) ? floatval($_POST['note']) : 0.0;
    $commentaire = trim($_POST['commentaire'] ?? '');

    if ($stagiaire_id > 0 && $note >= 0 && $note <= 20) {
        try {
            // On vérifie si ce stagiaire a déjà reçu une évaluation
            $check = $bdd->prepare("SELECT COUNT(*) FROM evaluations WHERE stagiaire_id = ?");
            $check->execute([$stagiaire_id]);
            $existe = $check->fetchColumn();

            if ($existe > 0) {
                // Mise à jour de sa note existante
                $stmt = $bdd->prepare("UPDATE evaluations SET note = ?, commentaire = ?, date_evaluation = NOW() WHERE stagiaire_id = ?");
                $stmt->execute([$note, $commentaire, $stagiaire_id]);
            } else {
                // Premier enregistrement de l'évaluation
                $stmt = $bdd->prepare("INSERT INTO evaluations (stagiaire_id, note, commentaire, date_evaluation) VALUES (?, ?, ?, NOW())");
                $stmt->execute([$stagiaire_id, $note, $commentaire]);
            }
            header('Location: evaluations.php?msg=success');
            exit();
        } catch (PDOException $e) {
            $messages[] = "Erreur lors de l'enregistrement : " . $e->getMessage();
        }
    } else {
        $messages[] = "Veuillez entrer une note valide comprise entre 0 et 20.";
    }
}

// ==========================================================================
// RECUPERATION DES DONNEES POUR LE TABLEAU ET LES CARTES
// ==========================================================================
try {
    // 1. Liste des stagiaires validés pour le formulaire d'options
    $listeStagiaires = $bdd->query("SELECT id, nom, prenom FROM stagiaires WHERE LOWER(statut) IN ('validé', 'valide') ORDER BY nom ASC")->fetchAll(PDO::FETCH_ASSOC);

    // 2. Liste complète des évaluations enregistrées avec le nom du stagiaire
    $sqlEvals = "SELECT e.note, e.commentaire, s.nom, s.prenom, s.id as stg_id
                 FROM evaluations e
                 JOIN stagiaires s ON e.stagiaire_id = s.id
                 ORDER BY e.date_evaluation DESC";
    $evaluationsCompletes = $bdd->query($sqlEvals)->fetchAll(PDO::FETCH_ASSOC);

    // 3. Calculs dynamiques pour les compteurs globaux
    $nbValides = 0;
    $nbAttente = 0;
    $sommeNotes = 0;
    $totalEvals = count($evaluationsCompletes);

    // On récupère le nombre total de stagiaires actifs pour savoir combien sont en attente
    $totalStagiairesActifs = count($listeStagiaires);

    foreach ($evaluationsCompletes as $ev) {
        $sommeNotes += $ev['note'];
        if ($ev['note'] >= 10) {
            $nbValides++;
        }
    }
    
    // Les profils en attente sont ceux qui n'ont pas encore de note enregistrée
    $nbAttente = $totalStagiairesActifs - $totalEvals;
    $moyenneGenerale = $totalEvals > 0 ? round($sommeNotes / $totalEvals, 2) : 0;

} catch (PDOException $e) {
    die("Erreur BDD : " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Évaluation & Résultats - Administration</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { margin: 0; font-family: Arial, sans-serif; display: flex; background: #f0f8ff; }
    .top-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(90deg, #0056b3, #003d80); color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 1000; }
    .top-header .header-title { font-size: 1.4em; font-weight: bold; margin: 0; letter-spacing: 1px; position: absolute; left: 50%; transform: translateX(-50%); }
    .menu-btn { position: fixed; top: 15px; left: 15px; background: #0056b3; color: #fff; padding: 10px 15px; cursor: pointer; border-radius: 5px; z-index: 1000; }
    
    .sidebar { position: fixed; left: -250px; top: 0; width: 250px; height: 100vh; background: linear-gradient(180deg, #0056b3, #003d80); color: #fff; padding: 20px; transition: left 0.5s ease; z-index: 999; overflow-y: auto; display: flex; flex-direction: column; box-sizing: border-box; }
    .sidebar.show { left: 0; }
    .logo-container { margin-top: 30px; margin-bottom: 25px; text-align: center; }
    .logo { width: 90px; height: 90px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
    .sidebar ul { list-style: none; padding: 0; margin: 0; flex: 1; }
    .sidebar ul li { margin: 20px 0; }
    .sidebar ul li a { color: #fff; text-decoration: none; font-weight: bold; display: block; padding: 12px 15px; border-radius: 6px; transition: background 0.3s; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.2); }
    
    .main-content { flex: 1; padding: 90px 40px 40px 40px; margin-left: 0; width: 100%; box-sizing: border-box; }
    h2 { color: #0056b3; margin-bottom: 20px; display: flex; align-items: center; gap: 10px; }
    
    .eval-table { width: 100%; border-collapse: collapse; margin-bottom: 30px; background: #fff; box-shadow: 0 4px 10px rgba(0,0,0,0.1); border-radius: 8px; overflow: hidden; }
    .eval-table th, .eval-table td { padding: 12px 15px; text-align: center; }
    .eval-table th { background: #0056b3; color: #fff; font-weight: bold; }
    .eval-table tr:nth-child(even) { background: #f9f9f9; }
    
    .progress { background: #e0e0e0; border-radius: 6px; overflow: hidden; height: 12px; width: 100px; margin: 0 auto; }
    .bar { height: 12px; background: #10b981; transition: width 0.5s ease; }
    
    .cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 40px; }
    .card { background: #fff; padding: 20px; border-radius: 8px; text-align: center; box-shadow: 0 4px 10px rgba(0,0,0,0.1); cursor: pointer; transition: transform 0.3s; }
    .card:hover { transform: translateY(-5px); }
    .card h3 { margin: 0; color: #003d80; font-size: 1.1em; }
    .card p { font-size: 1.6em; font-weight: bold; margin: 10px 0 0; }
    .valide { border-top: 4px solid #28a745; }
    .attente { border-top: 4px solid #ffc107; }
    .moyenne { border-top: 4px solid #17a2b8; }
    
    .hidden-list { display: none; margin-top: 10px; background: #fff; border-radius: 6px; padding: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); text-align: left; }
    .hidden-list ul { list-style: none; padding: 0; margin: 0; }
    .hidden-list li { padding: 8px 0; border-bottom: 1px solid #edf2f7; color: #2d3748; }
    
    /* FORMULAIRE DESIGN */
    .form-evaluation { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.05); max-width: 600px; }
    .form-group { margin-bottom: 15px; display: flex; flex-direction: column; gap: 5px; }
    .form-group label { font-weight: bold; color: #2d3748; }
    .form-group select, .form-group input, .form-group textarea { padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1em; }
    .btn-submit { background: #0056b3; color: white; border: none; padding: 12px; font-weight: bold; border-radius: 6px; cursor: pointer; transition: background 0.2s; }
    .btn-submit:hover { background: #003d80; }
    .flash-box { background: #d1fae5; color: #065f46; border: 1px solid #a7f3d0; padding: 12px 20px; border-radius: 6px; margin-bottom: 20px; font-weight: bold; }
  </style>
</head>
<body>

  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-chart-bar"></i> Évaluation & Résultats (Admin)</h1>
  </div>

  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <aside class="sidebar" id="sidebar">
    <div class="logo-container"><img src="../../LOGO.jpeg" class="logo"></div>
    <ul>
       <li><a href="liste.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="gestion.php"><i class="fas fa-users"></i> Gestion des stagiaires</a></li>
      <li><a href="evaluations.php" class="active"><i class="fas fa-chart-bar"></i> Évaluation & Résultats</a></li>
      <li><a href="suivi.php"><i class="fas fa-thumbtack"></i> Suivi des tâches</a></li>
      <li><a href="rapport.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
      <li><a href="SERVICE.php"><i class="fas fa-tools"></i> Services</a></li>
      <li class="logout"><a href="../../public/index.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </aside>

  <main class="main-content">
    
    <?php if (isset($_GET['msg']) && $_GET['msg'] === 'success'): ?>
        <div class="flash-box"><i class="fas fa-check-circle"></i> Côte et appréciation publiées avec succès sur l'espace du stagiaire !</div>
    <?php endif; ?>

    <h2><i class="fas fa-table"></i> Tableau des fiches d'évaluation actives</h2>
    <table class="eval-table">
      <thead>
        <tr>
          <th>Nom du Stagiaire</th>
          <th>Note Attribuée</th>
          <th>Mention / Commentaire d'encadrement</th>
          <th>Rendement Visuel</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($evaluationsCompletes)): ?>
            <tr><td colspan="4" style="color: #718096; font-style: italic;">Aucun stagiaire n'a encore été évalué.</td></tr>
        <?php else: ?>
            <?php foreach ($evaluationsCompletes as $row): 
                $pctBarre = ($row['note'] / 20) * 100;
            ?>
            <tr>
              <td style="font-weight: bold; text-align: left;"><?php echo htmlspecialchars(strtoupper($row['nom']) . " " . $row['prenom']); ?></td>
              <td><b style="color:#0056b3; font-size: 1.1em;"><?php echo $row['note']; ?></b> / 20</td>
              <td style="font-style: italic; text-align: left;"><?php echo htmlspecialchars($row['commentaires'] ?? $row['commentaire']); ?></td>
              <td>
                <div class="progress"><div class="bar" style="width:<?php echo $pctBarre; ?>%"></div></div>
              </td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>

    <section class="resultats">
      <h2><i class="fas fa-chart-pie"></i> Statistiques dynamiques du centre</h2>
      <div class="cards">
        <div class="card valide" onclick="toggleList('valide-list')">
          <h3><i class="fas fa-check-circle"></i> Ayant la moyenne (>=10)</h3>
          <p><?php echo $nbValides; ?></p>
        </div>
        <div id="valide-list" class="hidden-list">
          <ul>
            <?php foreach ($evaluationsCompletes as $row) { if($row['note']>=10) echo "li".htmlspecialchars($row['nom'])." : ".$row['note']."/20/li"; } ?>
          </ul>
        </div>

        <div class="card attente" onclick="toggleList('attente-list')">
          <h3><i class="fas fa-hourglass-half"></i> En attente de note</h3>
          <p><?php echo $nbAttente; ?></p>
        </div>

        <div class="card moyenne" onclick="toggleList('moyenne-list')">
          <h3><i class="fas fa-percentage"></i> Moyenne générale</h3>
          <p><?php echo $moyenneGenerale; ?> / 20</p>
        </div>
      </div>
    </section>

    <h2><i class="fas fa-pen-fancy"></i> Évaluer et attribuer les points</h2>
    <div class="form-evaluation">
        <form method="POST" action="">
            <input type="hidden" name="action" value="soumettre_evaluation">
            <div class="form-group">
                <label>Sélectionner un stagiaire</label>
                <select name="stagiaire_id" required>
                    <option value="">-- Choisir le profil --</option>
                    <?php foreach ($listeStagiaires as $s): ?>
                        <option value="<?php echo $s['id']; ?>"><?php echo htmlspecialchars(strtoupper($s['nom']) . " " . $s['prenom']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label>Note finale (Sur 20 points)</label>
                <input type="number" step="0.5" min="0" max="20" name="note" placeholder="Ex: 16.5" required>
            </div>
            <div class="form-group">
                <label>Appréciation générale & Remarques</label>
                <textarea name="commentaire" rows="3" placeholder="Excellent travail, stagiaire rigoureux..." required></textarea>
            </div>
            <button type="submit" class="btn-submit">Publier le résultat final</button>
        </form>
    </div>

  </main>

  <script>
    function toggleMenu() { document.getElementById('sidebar').classList.toggle('show'); }
    function toggleList(id) {
      const list = document.getElementById(id);
      list.style.display = (list.style.display === "block") ? "none" : "block";
    }
  </script>
</body>
</html>