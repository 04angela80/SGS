<?php
session_start();

require_once __DIR__ . '/../../config/db.php';

if (!isset($_SESSION['id_stagiaire'])) {
    header('Location: ../../public/index.php');
    exit();
}

$id_stagiaire_connecte = $_SESSION['id_stagiaire'];
$messages = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_FILES['rapport_fichier']) || $_FILES['rapport_fichier']['error'] !== UPLOAD_ERR_OK) {
        $messages[] = 'Veuillez sélectionner un fichier valide pour le rapport.';
    } else {
        $titre = trim($_POST['titre'] ?? '');
        $contenu = trim($_POST['contenu'] ?? '');
        $fichier = $_FILES['rapport_fichier'];

        if ($titre === '' || $contenu === '') {
            $messages[] = 'Le titre et le contenu du rapport sont obligatoires.';
        } else {
            $allowed = ['application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'];
            $tmpName = $fichier['tmp_name'];
            $mimeType = mime_content_type($tmpName);
            $extension = pathinfo($fichier['name'], PATHINFO_EXTENSION);
            $safeName = preg_replace('/[^a-zA-Z0-9-_\.]/', '_', basename($fichier['name']));
            $targetDir = __DIR__ . '/uploads/rapports';
            $targetFile = $targetDir . '/' . time() . '_' . $safeName;

            if (!in_array($mimeType, $allowed, true)) {
                $messages[] = 'Type de fichier non autorisé. Autorisé : PDF, DOC, DOCX.';
            } elseif (!is_dir($targetDir) && !mkdir($targetDir, 0755, true)) {
                $messages[] = 'Impossible de créer le dossier de stockage des rapports.';
            } elseif (!move_uploaded_file($tmpName, $targetFile)) {
                $messages[] = 'Impossible de transférer le fichier sur le serveur.';
            } else {
                try {
                    $stmt = $bdd->prepare('INSERT INTO rapports (stagiaire_id, titre, contenu, fichier) VALUES (:stagiaire_id, :titre, :contenu, :fichier)');
                    $stmt->execute([
                        'stagiaire_id' => $id_stagiaire_connecte,
                        'titre' => $titre,
                        'contenu' => $contenu,
                        'fichier' => basename($targetFile),
                    ]);
                    header('Location: rapport.php?msg=' . urlencode('Rapport déposé avec succès.'));
                    exit();
                } catch (PDOException $e) {
                    $messages[] = 'Erreur lors de l’enregistrement du rapport : ' . $e->getMessage();
                }
            }
        }
    }
}

try {
    $stmtProfil = $bdd->prepare('SELECT nom, prenom, filiere FROM stagiaires WHERE id = :id');
    $stmtProfil->execute(['id' => $id_stagiaire_connecte]);
    $profil = $stmtProfil->fetch(PDO::FETCH_ASSOC);

    if (!$profil) {
        session_destroy();
        header('Location: ../../public/index.php');
        exit();
    }

    $stmtRapports = $bdd->prepare('SELECT titre, contenu, fichier, date_rapport FROM rapports WHERE stagiaire_id = :id ORDER BY date_rapport DESC');
    $stmtRapports->execute(['id' => $id_stagiaire_connecte]);
    $mesRapports = $stmtRapports->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die('Erreur de synchronisation BDD : ' . $e->getMessage());
}

if (isset($_GET['msg']) && trim($_GET['msg']) !== '') {
    $messages[] = htmlspecialchars($_GET['msg'], ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Rapports - Espace Stagiaire</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body { margin: 0; font-family: 'Segoe UI', Arial, sans-serif; background: #f0f8ff; }
    .top-header { position: fixed; top: 0; left: 0; right: 0; height: 60px; background: linear-gradient(90deg, #0056b3, #003d80); color: #fff; display: flex; align-items: center; justify-content: center; padding: 0 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.2); z-index: 1000; }
    .top-header .header-title { font-size: 1.4em; margin: 0; }
    .menu-btn { position: fixed; top: 15px; left: 15px; background: #0056b3; color: #fff; padding: 10px 15px; border-radius: 5px; cursor: pointer; z-index: 1001; }
    .sidebar { position: fixed; left: -250px; top: 0; width: 250px; height: 100vh; background: linear-gradient(180deg, #0056b3, #003d80); color: #fff; padding: 20px; transition: left 0.5s ease; z-index: 999; overflow-y: auto; display: flex; flex-direction: column; }
    .sidebar.show { left: 0; }
    .logo-container { text-align: center; margin-top: 30px; margin-bottom: 25px; }
    .logo { width: 90px; height: 90px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); }
    .sidebar ul { list-style: none; padding: 0; margin: 0; flex: 1; }
    .sidebar ul li { margin: 20px 0; }
    .sidebar ul li a { color: #fff; text-decoration: none; font-weight: bold; display: flex; align-items: center; padding: 12px 15px; border-radius: 6px; }
    .sidebar ul li a i { margin-right: 8px; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.2); }
    .logout { margin-top: auto; }
    .container { padding: 110px 40px 40px 40px; max-width: 960px; margin: 0 auto; }
    .title-card { background: white; padding: 24px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); margin-bottom: 30px; }
    .title-card h2 { margin: 0; color: #0056b3; }
    .messages { margin-bottom: 20px; }
    .message { background: #e0f2fe; border-left: 4px solid #0284c7; color: #0c4a6e; padding: 14px 18px; border-radius: 10px; margin-bottom: 12px; }
    .card { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 8px 22px rgba(0,0,0,0.06); margin-bottom: 30px; }
    .section-title { display: flex; align-items: center; gap: 10px; font-size: 1.2em; color: #0f172a; margin-bottom: 18px; }
    .rapport-form label { display: block; margin-top: 18px; color: #475569; font-weight: 600; }
    .rapport-form input[type="text"], .rapport-form textarea, .rapport-form input[type="file"] { width: 100%; padding: 12px 14px; border-radius: 12px; border: 1px solid #cbd5e1; margin-top: 8px; font-size: 0.95em; }
    .rapport-form textarea { min-height: 140px; resize: vertical; }
    .rapport-form button { margin-top: 20px; background: #0056b3; color: white; border: none; padding: 14px 18px; border-radius: 12px; cursor: pointer; font-weight: bold; }
    .rapport-form button:hover { background: #003d80; }
    .report-list { list-style: none; padding: 0; margin: 0; }
    .report-list li { background: #fff; border: 1px solid #e2e8f0; padding: 18px; border-radius: 14px; margin-bottom: 14px; display: flex; justify-content: space-between; align-items: center; }
    .report-meta { max-width: 75%; }
    .report-meta h4 { margin: 0 0 6px 0; color: #1e293b; }
    .report-meta p { margin: 4px 0; color: #475569; font-size: 0.95em; }
    .report-actions a { text-decoration: none; color: #0056b3; font-weight: bold; }
    .report-actions a:hover { text-decoration: underline; }
  </style>
</head>
<body>
  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-file-alt"></i> Rapports</h1>
  </div>
  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>
  <aside class="sidebar" id="sidebar">
    <div class="logo-container"><img src="../../LOGO.jpeg" alt="Logo" class="logo"></div>
    <ul>
      <li><a href="stagiaire.php" ><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="taches.php"><i class="fas fa-tasks"></i> Mes tâches</a></li>
      <li><a href="rapport.php" class="active"><i class="fas fa-file-alt"></i> Rapports</a></li>
      <li><a href="resultats.php"><i class="fas fa-chart-line"></i> Résultats</a></li>
      <li><a href="profil.php"><i class="fas fa-cog"></i> Profil</a></li>
      <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
  </aside>

  <main class="container">
    <div class="title-card">
      <h2>Déposer un rapport</h2>
      <p>Vous pouvez déposer votre rapport de stage ici. Le document sera enregistré et une copie restera disponible pour consultation.</p>
    </div>

    <?php if (!empty($messages)): ?>
    <div class="messages">
      <?php foreach ($messages as $message): ?>
        <div class="message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <section class="card rapport-form">
      <form action="rapport.php" method="POST" enctype="multipart/form-data">
        <label for="titre">Titre du rapport</label>
        <input type="text" id="titre" name="titre" required>

        <label for="contenu">Résumé / Commentaire</label>
        <textarea id="contenu" name="contenu" rows="6" required></textarea>

        <label for="rapport_fichier">Fichier PDF / DOC / DOCX</label>
        <input type="file" id="rapport_fichier" name="rapport_fichier" accept=".pdf,.doc,.docx" required>

        <button type="submit"><i class="fas fa-paper-plane"></i> Envoyer le rapport</button>
      </form>
    </section>

    <section class="card">
      <div class="section-title"><i class="fas fa-folder-open"></i> Mes rapports déposés</div>
      <ul class="report-list">
        <?php if (empty($mesRapports)): ?>
          <li>Aucun rapport déposé pour le moment.</li>
        <?php else: ?>
          <?php foreach ($mesRapports as $rapport): ?>
            <li>
              <div class="report-meta">
                <h4><?php echo htmlspecialchars($rapport['titre'], ENT_QUOTES, 'UTF-8'); ?></h4>
                <p><?php echo nl2br(htmlspecialchars($rapport['contenu'], ENT_QUOTES, 'UTF-8')); ?></p>
                <p style="margin-top:8px; color:#64748b; font-size:0.9em;">Déposé le <?php echo date('d/m/Y H:i', strtotime($rapport['date_rapport'])); ?></p>
              </div>
              <div class="report-actions">
                <?php if (!empty($rapport['fichier'])): ?>
                  <a href="uploads/rapports/<?php echo htmlspecialchars($rapport['fichier'], ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><i class="fas fa-download"></i> Télécharger</a>
                <?php endif; ?>
              </div>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      </ul>
    </section>
  </main>

  <script>
    function toggleMenu() { document.getElementById('sidebar').classList.toggle('show'); }
  </script>
</body>
</html>
