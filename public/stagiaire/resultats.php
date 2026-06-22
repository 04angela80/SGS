<?php
// 1. DÉMARRAGE DE LA SESSION
session_start();

// 2. CONNEXION À LA BASE DE DONNÉES (SANS REDIRECTION EN CAS D'ERREUR)
require_once __DIR__ . '/../../config/db.php';

// Si la session n'existe pas, on crée une fausse variable ou on affiche un avertissement sans bloquer la page
$id_stagiaire_connecte = isset($_SESSION['id_stagiaire']) ? $_SESSION['id_stagiaire'] : null;
$noteTrouvee = null;
$commentaireTrouve = "";
$statutNote = "attente";

// 3. REQUÊTE SQL (Si le stagiaire est connecté)
if ($id_stagiaire_connecte) {
    try {
        $stmtEval = $bdd->prepare("SELECT note, commentaire FROM evaluations WHERE stagiaire_id = ?");
        $stmtEval->execute([$id_stagiaire_connecte]);
        $monEvaluation = $stmtEval->fetch(PDO::FETCH_ASSOC);

        if ($monEvaluation) {
            $statutNote = "disponible";
            $noteTrouvee = $monEvaluation['note'];
            $commentaireTrouve = $monEvaluation['commentaire'];
        }
    } catch (PDOException $e) {
        // En cas d'erreur SQL, on l'affiche directement pour que tu puisses la voir !
        echo "<div style='background:#fee2e2; color:#991b1b; padding:15px; margin:20px; border-radius:8px; font-weight:bold;'>";
        echo "Erreur SQL : " . $e->getMessage();
        echo "</div>";
    }
} else {
    // Message d'avertissement simple si tu testes sans être connecté, sans te rediriger
    echo "<div style='background:#fef3c7; color:#92400e; padding:10px; text-align:center; font-weight:bold;'>";
    echo "⚠️ Attention : Aucun stagiaire n'est connecté actuellement en session (id_stagiaire vide).";
    echo "</div>";
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <title>Résultats - Espace Stagiaire</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <style>
    body {
      margin: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
      display: flex;
      background: #f4f7fc;
      color: #334155;
    }

    /* ==========================================================================
       HEADER & SIDEBAR (CONSERVÉS À 100%)
       ========================================================================== */
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
      font-size: 1.4em; font-weight: bold; margin: 0; letter-spacing: 1px;
      position: absolute; left: 50%; transform: translateX(-50%);
    }
    .top-header .header-icons { position: absolute; right: 20px; display: flex; gap: 15px; font-size: 22px; cursor: pointer; }
    .top-header .header-icons span:hover { transform: scale(1.2); }
    .menu-btn { position: fixed; top: 15px; left: 15px; background: #0056b3; color: #fff; padding: 10px 15px; cursor: pointer; border-radius: 5px; z-index: 1000; display: inline-flex; align-items: center; gap: 6px; }
    
    .sidebar {
      position: fixed; left: -250px; top: 0; width: 250px; height: 100vh;
      background: linear-gradient(180deg, #0056b3, #003d80); color: #fff;
      padding: 20px; transition: left 0.5s ease; z-index: 999;
      overflow-y: auto; display: flex; flex-direction: column;
    }
    .sidebar.show { left: 0; }
    .logo-container { margin-top: 30px; margin-bottom: 25px; text-align: center; }
    .logo { width: 90px; height: 90px; border-radius: 50%; background: #fff; padding: 6px; box-shadow: 0 8px 20px rgba(0,0,0,0.3); transition: transform 0.3s ease; }
    .sidebar ul { list-style: none; padding: 0; margin: 0; flex: 1; }
    .sidebar ul li { margin: 20px 0; }
    .sidebar ul li a { color: #fff; text-decoration: none; font-weight: bold; display: flex; align-items: center; white-space: nowrap; padding: 12px 15px; border-radius: 6px; transition: background 0.3s; }
    .sidebar ul li a i { margin-right: 8px; font-size: 18px; flex-shrink: 0; }
    .sidebar ul li a:hover, .sidebar ul li a.active { background: rgba(255,255,255,0.2); }
    .logout { margin-top: auto; }

    /* ==========================================================================
       CONTENU CENTRAL
       ========================================================================== */
    .main-content {
      flex: 1;
      padding: 40px;
      margin-top: 80px;
      display: flex;
      flex-direction: column;
      align-items: center;
      box-sizing: border-box;
    }

    .notifications {
      background: #eff6ff; border: 1px solid #bfdbfe; color: #1e40af;
      padding: 15px; margin-bottom: 25px; border-radius: 10px;
      width: 100%; max-width: 680px; box-shadow: 0 4px 6px rgba(0,0,0,0.02); font-weight: 500;
    }
    .hidden { display: none !important; }

    /* CARD D'ATTENTE AVEC TON DESIGN STRICT ET ANCIEN CODE */
    .waiting-card {
      background: #ffffff; padding: 40px; border-radius: 16px;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.06); border: 1px solid #e2e8f0;
      text-align: center; width: 100%; max-width: 680px; box-sizing: border-box;
    }

    .pulse-loader-box { position: relative; width: 120px; height: 120px; margin: 0 auto 25px auto; }
    .pulse-ring {
      position: absolute; top: 0; left: 0; width: 100%; height: 100%;
      border: 3px solid #0056b3; border-radius: 50%;
      animation: pulseRings 2.2s cubic-bezier(0.215, 0.610, 0.355, 1) infinite; opacity: 0;
    }
    .pulse-ring:nth-child(2) { animation-delay: 0.7s; }
    .pulse-ring:nth-child(3) { animation-delay: 1.4s; }

    .pulse-core {
      position: absolute; top: 10px; left: 10px; width: 100px; height: 100px;
      background: linear-gradient(135deg, #0056b3, #003d80); border-radius: 50%;
      display: flex; align-items: center; justify-content: center; color: white; font-size: 32px;
      box-shadow: 0 8px 20px rgba(0, 51, 128, 0.3);
    }
    .pulse-core i { animation: rotateHourglass 3s ease-in-out infinite; }

    @keyframes pulseRings {
      0% { transform: scale(0.6); opacity: 0; }
      50% { opacity: 0.4; }
      100% { transform: scale(1.3); opacity: 0; }
    }
    @keyframes rotateHourglass {
      0% { transform: rotate(0deg); }
      45% { transform: rotate(0deg); }
      55% { transform: rotate(180deg); }
      100% { transform: rotate(180deg); }
    }

    .waiting-card h2 { color: #0f172a; margin: 0 0 10px 0; font-size: 1.5em; font-weight: 700; }
    #dynamic-text { font-size: 1.05em; color: #2563eb; font-weight: 600; margin: 0 0 30px 0; letter-spacing: 0.3px; }

    .pipeline-container { border-top: 1px solid #e2e8f0; padding-top: 30px; margin-bottom: 25px; text-align: left; }
    .pipeline-title { font-size: 0.88em; text-transform: uppercase; color: #64748b; font-weight: 700; letter-spacing: 0.8px; margin-bottom: 20px; text-align: center; }
    
    .steps-list { display: flex; flex-direction: column; gap: 15px; }
    .step-item { display: flex; align-items: center; gap: 15px; padding: 10px 15px; border-radius: 8px; background: #f8fafc; border: 1px solid #e2e8f0; }
    .step-icon { width: 28px; height: 28px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 13px; font-weight: bold; }
    
    .step-item.done .step-icon { background: #d1fae5; color: #065f46; }
    .step-item.current .step-icon { background: #dbeafe; color: #1d4ed8; animation: microPulse 1.5s infinite alternate; }
    
    .step-text { font-size: 0.92em; font-weight: 600; color: #334155; }
    .step-badge { margin-left: auto; font-size: 0.78em; padding: 3px 8px; border-radius: 4px; font-weight: 700; text-transform: uppercase; }
    .step-item.done .step-badge { background: #e8f5e9; color: #2e7d32; }
    .step-item.current .step-badge { background: #e3f2fd; color: #0d47a1; }

    @keyframes microPulse { 0% { box-shadow: 0 0 0 0 rgba(29, 78, 216, 0.4); } 100% { box-shadow: 0 0 0 6px rgba(29, 78, 216, 0); } }

    #refresh-btn {
      background: #0056b3; color: #fff; border: none; border-radius: 8px;
      padding: 12px 24px; font-size: 0.95em; font-weight: 600; cursor: pointer;
      display: inline-flex; align-items: center; gap: 8px;
      box-shadow: 0 4px 12px rgba(0, 86, 179, 0.2); transition: all 0.2s;
    }
    #refresh-btn:hover { background: #003d80; transform: translateY(-1px); }

    /* ==========================================================================
       STYLE DU CADRE DE RÉSULTAT DE FIN DE STAGE
       ========================================================================== */
    .result-box {
      background: white; padding: 35px; border-radius: 16px;
      box-shadow: 0 10px 30px rgba(15, 23, 42, 0.08); border: 1px solid #e2e8f0;
      text-align: center; width: 100%; max-width: 600px; box-sizing: border-box;
      border-top: 6px solid #0056b3; animation: fadeIn 0.6s ease;
    }
    .note-circle {
      width: 120px; height: 120px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.2em; font-weight: bold; margin: 20px auto; color: white;
    }
    .note-circle.vert { background: #10b981; box-shadow: 0 8px 20px rgba(16,185,129,0.3); }
    .note-circle.rouge { background: #ef4444; box-shadow: 0 8px 20px rgba(239,68,68,0.3); }
    .comment-text { font-style: italic; color: #4a5568; background: #f8fafc; padding: 15px; border-radius: 8px; border-left: 4px solid #cbd5e1; margin-top: 20px; text-align: left; }
    
    @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
  </style>
</head>
<body>

  <div class="top-header">
    <h1 class="header-title"><i class="fas fa-chart-line"></i> Résultats</h1>
    <div class="header-icons">
      <span class="bell" onclick="showNotifications()"><i class="fas fa-bell"></i></span>
      <span class="user"><i class="fas fa-user-circle"></i></span>
    </div>
  </div>

  <div class="menu-btn" onclick="toggleMenu()"><i class="fas fa-bars"></i> Menu</div>

  <div id="sidebar" class="sidebar">
    <div class="logo-container"><img src="../../LOGO.jpeg" alt="Logo" class="logo"></div>
    <ul>
      <li><a href="stagiaire.php"><i class="fas fa-home"></i> Accueil</a></li>
      <li><a href="taches.php"><i class="fas fa-tasks"></i> Mes tâches</a></li>
      <li><a href="rapport.php"><i class="fas fa-file-alt"></i> Rapports</a></li>
      <li><a href="resultats.php" class="active"><i class="fas fa-chart-line"></i> Résultats</a></li>
      <li><a href="profil.php"><i class="fas fa-cog"></i> Profil</a></li>
      <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Déconnexion</a></li>
    </ul>
  </div>

  <div class="main-content">
    
    <div id="notifications" class="notifications hidden">
      <p><i class="fas fa-info-circle"></i> Aucune notification pour l’instant. Veuillez patienter, l’administrateur vous contactera.</p>
    </div>

    <div id="bloc-attente" class="waiting-card">
      <div class="pulse-loader-box">
        <div class="pulse-ring"></div>
        <div class="pulse-ring"></div>
        <div class="pulse-ring"></div>
        <div class="pulse-core"><i class="fas fa-hourglass-half"></i></div>
      </div>

      <h2>Délibération des notes de stage</h2>
      <p id="dynamic-text">Synchronisation avec le jury en cours…</p>

      <div class="pipeline-container">
        <div class="pipeline-title">Étapes de validation du dossier</div>
        <div class="steps-list">
          <div class="step-item done">
            <div class="step-icon"><i class="fas fa-check"></i></div>
            <span class="step-text">Clôture des fiches de tâches quotidiennes</span>
            <span class="step-badge">Terminé</span>
          </div>
          <div class="step-item done">
            <div class="step-icon"><i class="fas fa-check"></i></div>
            <span class="step-text">Dépôt et réception du rapport de fin de stage</span>
            <span class="step-badge">Validé</span>
          </div>
          <div class="step-item current">
            <div class="step-icon"><i class="fas fa-sync-alt fa-spin"></i></div>
            <span class="step-text">Calcul des mentions & signature de l'administration</span>
            <span class="step-badge">En cours</span>
          </div>
        </div>
      </div>

      <button id="refresh-btn" onclick="revelerMonResultat();">
        <i class="fas fa-sync-alt"></i> Actualiser ma feuille de note
      </button>
    </div>

    <div id="bloc-resultat" class="result-box hidden">
        <h3 style="margin:0; color:#003d80; font-size:1.5em;"><i class="fas fa-graduation-cap"></i> Résultats de Fin de Stage</h3>
        <p style="color:#64748b; font-size:0.95em; margin-top:5px;">Fiche de note officielle générée par l'administration</p>
        
        <div id="cercle-note" class="note-circle">
            <span id="valeur-note">00</span><span style="font-size:0.5em; font-weight:normal;">/20</span>
        </div>

        <p id="texte-mention" style="font-weight:bold; font-size:1.3em; margin: 15px 0;"></p>

        <div class="comment-text">
            <strong>Mention & Remarques du jury :</strong><br>
            <span id="valeur-commentaire">...</span>
        </div>
    </div>

  </div>

  <script>
    // Passage des données PHP vers des variables JavaScript sécurisées
    const bddStatut = "<?php echo $statutNote; ?>";
    const bddNote = "<?php echo $noteTrouvee; ?>";
    const bddCommentaire = <?php echo json_encode($commentaire遊eve ?? $commentaireTrouve); ?>;

    function toggleMenu() {
      document.getElementById("sidebar").classList.toggle("show");
    }

    function showNotifications() {
      document.getElementById("notifications").classList.toggle("hidden");
    }

    // Animation de texte tournant
    document.addEventListener("DOMContentLoaded", () => {
      const dynamicText = document.getElementById("dynamic-text");
      if (dynamicText) {
          const messages = [
            "Calcul de la moyenne générale en cours…",
            "Génération de votre certificat officiel…",
            "En attente de la signature de votre tuteur…"
          ];
          let index = 0;
          setInterval(() => {
            dynamicText.textContent = messages[index];
            index = (index + 1) % messages.length;
          }, 4000);
      }
    });

    // ACTION DU BOUTON BLEU
    function revelerMonResultat() {
      const textDynamique = document.getElementById("dynamic-text");
      textDynamique.textContent = "🔄 Reconnexion sécurisée aux serveurs académiques…";

      setTimeout(() => {
        if (bddStatut === 'disponible') {
          // 1. Cacher le sablier
          document.getElementById('bloc-attente').classList.add('hidden');
          
          // 2. Assigner la note et le commentaire
          const note = parseFloat(bddNote);
          document.getElementById('valeur-note').textContent = note;
          document.getElementById('valeur-commentaire').textContent = bddCommentaire;
          
          const cercle = document.getElementById('cercle-note');
          const mention = document.getElementById('texte-mention');
          
          // 3. Adapter les couleurs (Vert si >= 10, sinon rouge)
          if (note >= 10) {
            cercle.className = "note-circle vert";
            mention.textContent = "Stage Validé avec Succès !";
            mention.style.color = "#10b981";
          } else {
            cercle.className = "note-circle rouge";
            mention.textContent = "Stage Non Validé / Réajustement requis ⚠️";
            mention.style.color = "#ef4444";
          }
          
          // 4. Ouvrir le cadre des résultats des fins de stage !
          document.getElementById('bloc-resultat').classList.remove('hidden');
        } else {
          // Si aucune note n'est prête en BDD
          textDynamique.textContent = "Aucune note publiée. Le conseil d'administration délibère actuellement.";
        }
      }, 1500); // Petit délai d'attente fluide de 1.5s
    }
  </script>
</body>
</html>